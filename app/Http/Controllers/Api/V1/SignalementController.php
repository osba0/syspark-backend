<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreSignalementRequest;
use App\Http\Resources\SignalementResource;
use App\Models\Maintenance;
use App\Models\Signalement;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SignalementController extends BaseApiController
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}
    /**
     * GET /api/v1/signalements
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Signalement::class);

        $query = QueryBuilder::for(Signalement::class)
            ->allowedFilters([
                AllowedFilter::exact('vehicule_id'),
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('statut'),
                AllowedFilter::exact('gravite'),
                AllowedFilter::exact('type_defaut'),
                AllowedFilter::exact('chauffeur_id'),
            ])
            ->allowedSorts(['-date_signalement', 'gravite', 'statut', 'created_at'])
            ->allowedIncludes(['vehicule', 'chauffeur', 'maintenance'])
            ->defaultSort('-date_signalement', '-gravite')
            ->with(['vehicule', 'chauffeur']);

        $this->applyAgenceScope($query, $request);

        // Scope chauffeur — ne voit que ses propres signalements
        $user = $request->user();
        if ($user->hasRole('chauffeur')) {
            $chauffeurId = $user->chauffeur()->first()?->id;
            if ($chauffeurId) {
                $query->where(function ($q) use ($user, $chauffeurId) {
                    $q->where('chauffeur_id', $chauffeurId)
                      ->orWhere('created_by', $user->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $signalements = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => SignalementResource::collection($signalements),
            'meta' => [
                'total'        => $signalements->total(),
                'per_page'     => $signalements->perPage(),
                'current_page' => $signalements->currentPage(),
                'last_page'    => $signalements->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/signalements
     * Reproduction de la FICHE DE SIGNALEMENT (PANNE, DÉFAUT, ANOMALIES)
     */
    public function store(StoreSignalementRequest $request): JsonResponse
    {
        $this->authorize('create', Signalement::class);

        $signalement = Signalement::create([
            ...$request->validated(),
            'statut'     => 'nouveau',
            'created_by' => $request->user()->id,
        ]);

        // Upload des photos si présentes
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $signalement->addMedia($photo)
                    ->toMediaCollection('photos');
            }
        }

        // Notification création
        $signalement->loadMissing(['vehicule', 'chauffeur']);
        $this->notificationService->signalementCree([
            'id'              => $signalement->id,
            'titre'           => $signalement->titre,
            'gravite'         => $signalement->gravite,
            'immatriculation' => $signalement->vehicule?->immatriculation ?? '—',
            'agence_id'       => $signalement->vehicule?->agence_id,
        ], $request->user()->id);

        return $this->created(
            new SignalementResource($signalement->load(['vehicule', 'chauffeur'])),
            'Signalement créé avec succès.'
        );
    }
    public function show(Signalement $signalement): JsonResponse
    {
        $this->authorize('view', $signalement);

        $signalement->load([
            'vehicule.agence',
            'chauffeur',
            'maintenance',
            'prisEnChargePar',
            'resoluPar',
            'createdBy',
        ]);

        return $this->success(new SignalementResource($signalement));
    }

    /**
     * PUT /api/v1/signalements/{signalement}
     */
    public function update(Request $request, Signalement $signalement): JsonResponse
    {
        $this->authorize('update', $signalement);

        $request->validate([
            'type_defaut'  => ['sometimes', 'string'],
            'gravite'      => ['sometimes', 'in:faible,moyenne,haute,critique'],
            'titre'        => ['sometimes', 'string', 'max:200'],
            'description'  => ['sometimes', 'string'],
            'etat_elements'=> ['nullable', 'array'],
        ]);

        $signalement->update($request->only([
            'type_defaut', 'gravite', 'titre', 'description', 'etat_elements',
        ]));

        return $this->success(new SignalementResource($signalement->fresh()));
    }

    /**
     * POST /api/v1/signalements/{signalement}/prendre-en-charge
     */
    public function prendreEnCharge(Signalement $signalement): JsonResponse
    {
        $this->authorize('prendreEnCharge', $signalement);

        if (!in_array($signalement->statut, ['nouveau'])) {
            return $this->error('Ce signalement est déjà pris en charge.', 422);
        }

        $signalement->update([
            'statut'             => 'en_cours',
            'pris_en_charge_par' => auth()->id(),
            'pris_en_charge_le'  => now(),
        ]);

        // Notification changement de statut
        $signalement->loadMissing('vehicule');
        $this->notificationService->signalementStatutChange([
            'id'              => $signalement->id,
            'statut'          => 'en_cours',
            'immatriculation' => $signalement->vehicule?->immatriculation ?? '—',
            'agence_id'       => $signalement->vehicule?->agence_id,
        ], $signalement->created_by);

        return $this->success(
            new SignalementResource($signalement->fresh()),
            'Signalement pris en charge.'
        );
    }

    /**
     * POST /api/v1/signalements/{signalement}/resoudre
     */
    public function resoudre(Request $request, Signalement $signalement): JsonResponse
    {
        $this->authorize('resoudre', $signalement);

        $request->validate([
            'commentaire_resolution' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        if (!in_array($signalement->statut, ['nouveau', 'en_cours', 'maintenance_creee'])) {
            return $this->error('Ce signalement ne peut pas être résolu.', 422);
        }

        $signalement->update([
            'statut'                  => 'resolu',
            'resolu_par'              => auth()->id(),
            'resolu_le'               => now(),
            'commentaire_resolution'  => $request->commentaire_resolution,
        ]);

        // Notification résolution
        $this->notificationService->signalementStatutChange([
            'id'              => $signalement->id,
            'statut'          => 'resolu',
            'immatriculation' => $signalement->vehicule?->immatriculation ?? '—',
            'agence_id'       => $signalement->vehicule?->agence_id,
        ], $signalement->created_by);

        return $this->success(
            new SignalementResource($signalement->fresh()),
            'Signalement marqué comme résolu.'
        );
    }

    /**
     * POST /api/v1/signalements/{signalement}/creer-maintenance
     * Crée une maintenance corrective à partir du signalement
     */
    public function creerMaintenance(Request $request, Signalement $signalement): JsonResponse
    {
        $this->authorize('create', Maintenance::class);

        if ($signalement->maintenance_id) {
            return $this->error('Une maintenance est déjà liée à ce signalement.', 422);
        }

        $request->validate([
            'fournisseur_id'    => ['nullable', 'exists:fournisseurs,id'],
            'date_travaux'      => ['required', 'date'],
            'description_travaux' => ['required', 'string'],
            'montant_ttc'       => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $maintenance = Maintenance::create([
                'vehicule_id'         => $signalement->vehicule_id,
                'agence_id'           => $signalement->agence_id,
                'chauffeur_id'        => $signalement->chauffeur_id,
                'fournisseur_id'      => $request->fournisseur_id,
                'date_travaux'        => $request->date_travaux,
                'kilometrage'         => $signalement->vehicule?->kilometrage_actuel ?? 0,
                'type_operation'      => 'reparation',
                'titre'               => 'Réparation — ' . $signalement->titre,
                'description_travaux' => $request->description_travaux,
                'montant_ttc'         => $request->montant_ttc ?? 0,
                'statut'              => 'en_cours',
                'signalement_id'      => $signalement->id,
                'created_by'          => auth()->id(),
                'necessite_approbation' => ($request->montant_ttc ?? 0) >= config('parc.maintenance.seuil_approbation'),
            ]);

            $signalement->update([
                'statut'         => 'maintenance_creee',
                'maintenance_id' => $maintenance->id,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur : ' . $e->getMessage(), 500);
        }

        return $this->created([
            'signalement' => new SignalementResource($signalement->fresh()),
            'maintenance' => [
                'id'     => $maintenance->id,
                'titre'  => $maintenance->titre,
                'statut' => $maintenance->statut,
            ],
        ], 'Maintenance corrective créée à partir du signalement.');
    }

    /**
     * POST /api/v1/signalements/{signalement}/photos
     */
    public function uploadPhotos(Request $request, Signalement $signalement): JsonResponse
    {
        $this->authorize('uploadPhoto', $signalement);

        $request->validate([
            'photos'    => ['required', 'array', 'max:5'],
            'photos.*'  => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $uploaded = [];
        foreach ($request->file('photos') as $photo) {
            $media = $signalement->addMedia($photo)->toMediaCollection('photos');
            $uploaded[] = [
                'id'  => $media->id,
                'url' => $media->getUrl(),
            ];
        }

        return $this->success($uploaded, count($uploaded) . ' photo(s) ajoutée(s).');
    }
}