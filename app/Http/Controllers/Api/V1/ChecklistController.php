<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreChecklistRequest;
use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Models\Signalement;
use App\Services\ChecklistService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChecklistController extends BaseApiController
{
    public function __construct(
        private ChecklistService    $checklistService,
        private NotificationService $notificationService,
    ) {}

    /**
     * GET /api/v1/checklists
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Checklist::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = QueryBuilder::for(Checklist::class)
            ->allowedFilters([
                AllowedFilter::exact('vehicule_id'),
                AllowedFilter::exact('chauffeur_id'),
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('type_checklist'),
                AllowedFilter::exact('statut'),
                AllowedFilter::exact('resultat_global'),
            ])
            ->allowedSorts(['date', 'created_at', 'statut'])
            ->allowedIncludes(['vehicule', 'chauffeur', 'validePar'])
            ->defaultSort('-date')
            ->with(['vehicule', 'chauffeur']);

        $this->applyAgenceScope($query, $request);

        // Scope chauffeur : un chauffeur ne voit que SES propres checklists
        $user = $request->user();
        if ($user->hasRole('chauffeur')) {
            // chauffeur() est HasMany → toujours utiliser ->first() jamais ->id directement
            $chauffeurId = $user->chauffeur()->first()?->id;
            if ($chauffeurId) {
                $query->where('chauffeur_id', $chauffeurId);
            } else {
                // Pas de fiche chauffeur liée → aucun résultat
                $query->whereRaw('1 = 0');
            }
        }

        $checklists = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => ChecklistResource::collection($checklists),
            'meta' => [
                'total'        => $checklists->total(),
                'per_page'     => $checklists->perPage(),
                'current_page' => $checklists->currentPage(),
                'last_page'    => $checklists->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/checklists
     * Créer une nouvelle checklist (brouillon ou soumise)
     */
    public function store(StoreChecklistRequest $request): JsonResponse
    {
        $this->authorize('create', Checklist::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validated();

        // Analyser les non-conformités automatiquement
        $nonConformites = $this->checklistService->detecterNonConformites(
            $data['data_json'],
            $data['type_checklist']
        );

        $resultat = empty($nonConformites) ? 'conforme' : 'non_conforme';

        $checklist = Checklist::create([
            ...$data,
            'non_conformites' => $nonConformites,
            'resultat_global' => $resultat,
            'statut'          => 'brouillon',
        ]);

        return $this->created(
            new ChecklistResource($checklist->load(['vehicule', 'chauffeur'])),
            'Checklist créée.'
        );
    }

    /**
     * GET /api/v1/checklists/{checklist}
     */
    public function show(Checklist $checklist): JsonResponse
    {
        $this->authorize('view', $checklist);

        $checklist->load(['vehicule', 'chauffeur', 'validePar', 'signalementGenere']);

        return $this->success(new ChecklistResource($checklist));
    }

    /**
     * PUT /api/v1/checklists/{checklist}
     * Modifier une checklist en brouillon seulement
     */
    public function update(Request $request, Checklist $checklist): JsonResponse
    {
        $this->authorize('update', $checklist);

        if ($checklist->statut !== 'brouillon') {
            return $this->error('Seule une checklist en brouillon peut être modifiée.', 422);
        }

        $request->validate([
            'data_json'    => ['sometimes', 'array'],
            'kilometrage'  => ['nullable', 'integer', 'min:0'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = $request->only(['data_json', 'kilometrage', 'observations']);

        if (isset($data['data_json'])) {
            $nonConformites = $this->checklistService->detecterNonConformites(
                $data['data_json'],
                $checklist->type_checklist
            );
            $data['non_conformites'] = $nonConformites;
            $data['resultat_global'] = empty($nonConformites) ? 'conforme' : 'non_conforme';
        }

        $checklist->update($data);

        return $this->success(new ChecklistResource($checklist->fresh()));
    }

    /**
     * POST /api/v1/checklists/{checklist}/soumettre
     * Chauffeur soumet la checklist pour validation
     */
    public function soumettre(Checklist $checklist): JsonResponse
    {
        $this->authorize('submit', $checklist);

        if ($checklist->statut !== 'brouillon') {
            return $this->error('Seule une checklist en brouillon peut être soumise.', 422);
        }

        $checklist->update(['statut' => 'soumis']);

        // Notification aux responsables
        $checklist->loadMissing(['vehicule', 'chauffeur']);
        $this->notificationService->checklistSoumise([
            'id'              => $checklist->id,
            'immatriculation' => $checklist->vehicule?->immatriculation ?? '—',
            'chauffeur'       => $checklist->chauffeur?->nom_complet ?? '—',
            'anomalies'       => count($checklist->non_conformites ?? []),
            'agence_id'       => $checklist->vehicule?->agence_id,
        ]);

        return $this->success(
            new ChecklistResource($checklist->fresh()),
            'Checklist soumise pour validation.'
        );
    }

    /**
     * POST /api/v1/checklists/{checklist}/valider
     * Responsable valide la checklist
     * Si non-conformités → génère automatiquement un signalement
     */
    public function valider(Request $request, Checklist $checklist): JsonResponse
    {
        $this->authorize('validate', $checklist);

        if ($checklist->statut !== 'soumis') {
            return $this->error('Seule une checklist soumise peut être validée.', 422);
        }

        $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $checklist->update([
                'statut'                  => 'valide',
                'valide_par'              => $request->user()->id,
                'valide_le'               => now(),
                'commentaire_validation'  => $request->commentaire,
            ]);

            // Générer automatiquement un signalement si non-conformités
            $signalement = null;
            if ($checklist->resultat_global === 'non_conforme' && !empty($checklist->non_conformites)) {
                $signalement = $this->checklistService->genererSignalement($checklist, $request->user());
                $checklist->update(['signalement_genere_id' => $signalement->id]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de la validation : ' . $e->getMessage(), 500);
        }

        return $this->success([
            'checklist'   => new ChecklistResource($checklist->fresh()),
            'signalement' => $signalement ? [
                'id'     => $signalement->id,
                'titre'  => $signalement->titre,
                'statut' => $signalement->statut,
            ] : null,
        ], $signalement
            ? 'Checklist validée. Un signalement a été généré automatiquement.'
            : 'Checklist validée avec succès.'
        );
    }

    /**
     * POST /api/v1/checklists/{checklist}/rejeter
     */
    public function rejeter(Request $request, Checklist $checklist): JsonResponse
    {
        $this->authorize('reject', $checklist);

        if ($checklist->statut !== 'soumis') {
            return $this->error('Seule une checklist soumise peut être rejetée.', 422);
        }

        $request->validate([
            'motif' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $checklist->update([
            'statut'                 => 'rejete',
            'valide_par'             => $request->user()->id,
            'valide_le'              => now(),
            'commentaire_validation' => 'REJETÉ : ' . $request->motif,
        ]);

        return $this->success(
            new ChecklistResource($checklist->fresh()),
            'Checklist rejetée.'
        );
    }
}