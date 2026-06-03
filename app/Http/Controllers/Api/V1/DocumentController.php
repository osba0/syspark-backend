<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\RenouvelerDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\DocumentVehicule;
use App\Models\Vehicule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DocumentController extends BaseApiController
{
    /**
     * GET /api/v1/documents
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocumentVehicule::class);

        $query = QueryBuilder::for(DocumentVehicule::class)
            ->allowedFilters([
                AllowedFilter::exact('vehicule_id'),
                AllowedFilter::exact('type_document'),
                AllowedFilter::exact('statut'),
                AllowedFilter::exact('est_actif'),
                // Filtre par agence via la relation véhicule
                AllowedFilter::callback('agence_id', function ($query, $value) {
                    $query->whereHas('vehicule', fn ($q) => $q->where('agence_id', $value));
                }),
            ])
            ->allowedSorts(['date_expiration', 'type_document', 'created_at'])
            ->allowedIncludes(['vehicule'])
            ->defaultSort('date_expiration')
            ->with(['vehicule:id,immatriculation,marque,modele,agence_id'])
            ->actifs();

        // Scope agence via vehicule
        $agenceId = $this->getAgenceScopeId($request);
        if ($agenceId) {
            $query->whereHas('vehicule', fn ($q) => $q->where('agence_id', $agenceId));
        }

        $documents = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => DocumentResource::collection($documents),
            'meta' => [
                'total'        => $documents->total(),
                'per_page'     => $documents->perPage(),
                'current_page' => $documents->currentPage(),
                'last_page'    => $documents->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/documents
     * Création + upload du fichier scanné
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', DocumentVehicule::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['statut']     = $this->calculerStatut($data['date_expiration'] ?? null);

        $disk = config('parc.uploads.disque', 'public');

        DB::beginTransaction();
        try {
            // Archiver l'ancien document actif du même type sur ce véhicule
            DocumentVehicule::where('vehicule_id', $data['vehicule_id'])
                ->where('type_document', $data['type_document'])
                ->where('est_actif', true)
                ->update(['est_actif' => false]);

            $document = DocumentVehicule::create($data);

            if ($request->hasFile('fichier')) {
                $fichier    = $request->file('fichier');
                $dossier    = 'documents/vehicules/' . $data['vehicule_id'];
                $nomFichier = sprintf(
                    '%s_%s_%s.%s',
                    $data['type_document'],
                    $data['vehicule_id'],
                    now()->format('Ymd_His'),
                    $fichier->getClientOriginalExtension()
                );

                // Créer le dossier si inexistant (évite les erreurs sur certains drivers)
                if (!Storage::disk($disk)->exists($dossier)) {
                    Storage::disk($disk)->makeDirectory($dossier);
                }

                $path = $fichier->storeAs($dossier, $nomFichier, $disk);

                if (!$path) {
                    throw new \RuntimeException('Échec de l\'écriture du fichier sur le disque ' . $disk);
                }

                $document->update([
                    'fichier_path' => $path,
                    'fichier_nom'  => $fichier->getClientOriginalName(),
                ]);
            }

            $this->syncVehiculeDocumentDates($document);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de l\'enregistrement : ' . $e->getMessage(), 500);
        }

        return $this->created(
            new DocumentResource($document->load('vehicule')),
            'Document enregistré avec succès.'
        );
    }

    /**
     * GET /api/v1/documents/{document}
     */
    public function show(DocumentVehicule $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->load(['vehicule.agence', 'createdBy']);

        return $this->success(new DocumentResource($document));
    }

    /**
     * PUT /api/v1/documents/{document}
     */
    public function update(Request $request, DocumentVehicule $document): JsonResponse
    {
        $this->authorize('update', $document);

        $request->validate([
            'numero'             => ['nullable', 'string', 'max:100'],
            'organisme_emetteur' => ['nullable', 'string', 'max:150'],
            'date_emission'      => ['nullable', 'date'],
            'date_expiration'    => ['nullable', 'date'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ]);

        $data = $request->only(['numero', 'organisme_emetteur', 'date_emission', 'date_expiration', 'notes']);

        if (isset($data['date_expiration'])) {
            $data['statut'] = $this->calculerStatut($data['date_expiration']);
        }

        $document->update($data);
        $this->syncVehiculeDocumentDates($document->fresh());

        return $this->success(new DocumentResource($document->fresh()));
    }

    /**
     * DELETE /api/v1/documents/{document}
     */
    public function destroy(DocumentVehicule $document): JsonResponse
    {
        $this->authorize('delete', $document);

        // Supprimer le fichier physique si présent
        if ($document->fichier_path) {
            $disk = config('parc.uploads.disque', 'public');
            if (Storage::disk($disk)->exists($document->fichier_path)) {
                Storage::disk($disk)->delete($document->fichier_path);
            }
        }

        $document->delete();

        return $this->noContent('Document supprimé.');
    }

    /**
     * POST /api/v1/documents/{document}/renouveler
     * Renouvellement : archive l'ancien, crée le nouveau
     */
    public function renouveler(RenouvelerDocumentRequest $request, DocumentVehicule $document): JsonResponse
    {
        $this->authorize('renouveler', $document);

        $data = $request->validated();

        DB::beginTransaction();
        try {
            // Archiver l'actuel
            $document->update(['est_actif' => false]);

            // Créer le nouveau
            $nouveau = DocumentVehicule::create([
                'vehicule_id'        => $document->vehicule_id,
                'type_document'      => $document->type_document,
                'intitule'           => $document->intitule,
                'numero'             => $data['numero'] ?? $document->numero,
                'date_emission'      => $data['date_emission'],
                'date_expiration'    => $data['date_expiration'],
                'organisme_emetteur' => $data['organisme_emetteur'] ?? $document->organisme_emetteur,
                'statut'             => $this->calculerStatut($data['date_expiration']),
                'est_actif'          => true,
                'notes'              => $data['notes'] ?? null,
                'created_by'         => $request->user()->id,
            ]);

            // Upload nouveau fichier si présent
            if ($request->hasFile('fichier')) {
                $fichier    = $request->file('fichier');
                $disk       = config('parc.uploads.disque', 'public');
                $dossier    = 'documents/vehicules/' . $document->vehicule_id;
                $nomFichier = sprintf(
                    '%s_%s_%s.%s',
                    $document->type_document,
                    $document->vehicule_id,
                    now()->format('Ymd_His'),
                    $fichier->getClientOriginalExtension()
                );

                if (!Storage::disk($disk)->exists($dossier)) {
                    Storage::disk($disk)->makeDirectory($dossier);
                }

                $path = $fichier->storeAs($dossier, $nomFichier, $disk);

                $nouveau->update([
                    'fichier_path' => $path,
                    'fichier_nom'  => $fichier->getClientOriginalName(),
                ]);
            }

            // Mettre à jour les dates sur le véhicule
            $this->syncVehiculeDocumentDates($nouveau);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors du renouvellement : ' . $e->getMessage(), 500);
        }

        return $this->created(
            new DocumentResource($nouveau->load('vehicule')),
            'Document renouvelé. L\'ancien a été archivé.'
        );
    }

    /**
     * GET /api/v1/documents/expiration-prochaine
     * Dashboard documents — reproduit le code couleur vert/orange/rouge
     */
    public function expirationProchaine(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocumentVehicule::class);

        $request->validate([
            'jours' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        $jours    = (int) $request->input('jours', 60);
        $agenceId = $this->getAgenceScopeId($request);

        $documentsQuery = DocumentVehicule::actifs()
            ->whereNotNull('date_expiration')
            ->where('date_expiration', '<=', now()->addDays($jours))
            ->with(['vehicule:id,immatriculation,marque,modele,agence_id', 'vehicule.agence:id,nom,code'])
            ->orderBy('date_expiration');

        if ($agenceId) {
            $documentsQuery->whereHas('vehicule', fn ($q) => $q->where('agence_id', $agenceId));
        }

        $documents = $documentsQuery->get()->map(fn ($d) => [
            'id'              => $d->id,
            'vehicule_id'     => $d->vehicule_id,
            'immatriculation' => $d->vehicule?->immatriculation,
            'marque_modele'   => $d->vehicule ? $d->vehicule->marque . ' ' . $d->vehicule->modele : null,
            'agence'          => $d->vehicule?->agence?->nom,
            'type_document'   => $d->type_document,
            'numero'          => $d->numero,
            'date_expiration' => $d->date_expiration?->format('Y-m-d'),
            'jours_restants'  => $d->jours_avant_expiration,
            'statut_calcule'  => $d->statut_calcule,  // valide | warning | critique | expire
        ]);

        // Grouper par criticité pour le dashboard
        return $this->success([
            'expires'   => $documents->filter(fn ($d) => $d['jours_restants'] < 0)->values(),
            'critiques' => $documents->filter(fn ($d) => $d['jours_restants'] >= 0 && $d['jours_restants'] <= 15)->values(),
            'warnings'  => $documents->filter(fn ($d) => $d['jours_restants'] > 15 && $d['jours_restants'] <= 30)->values(),
            'attention' => $documents->filter(fn ($d) => $d['jours_restants'] > 30)->values(),
            'total'     => $documents->count(),
        ]);
    }

    // ============================================================
    // Helpers privés
    // ============================================================

    private function calculerStatut(?string $dateExpiration): string
    {
        if (!$dateExpiration) return 'valide';

        $jours = now()->diffInDays($dateExpiration, false);

        if ($jours < 0)     return 'expire';
        if ($jours <= 15)   return 'a_renouveler';
        if ($jours <= 30)   return 'a_renouveler';
        return 'valide';
    }

    /**
     * Synchronise les dates clés du véhicule depuis ses documents actifs.
     */
    private function syncVehiculeDocumentDates(DocumentVehicule $document): void
    {
        $vehicule = $document->vehicule;
        if (!$vehicule) return;

        $updates = match($document->type_document) {
            'visite_technique' => [
                'date_derniere_visite_tech'  => $document->date_emission,
                'date_prochaine_visite_tech' => $document->date_expiration,
            ],
            'assurance' => [
                'date_expiration_assurance'  => $document->date_expiration,
                'numero_assurance'           => $document->numero,
            ],
            default => null,
        };

        if ($updates) {
            $vehicule->update(array_filter($updates, fn ($v) => $v !== null));
        }
    }
}