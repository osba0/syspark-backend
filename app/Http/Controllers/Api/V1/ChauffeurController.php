<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreChauffeurRequest;
use App\Http\Requests\UpdateChauffeurRequest;
use App\Http\Resources\ChauffeurResource;
use App\Models\Chauffeur;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChauffeurController extends BaseApiController
{
    /**
     * GET /api/v1/chauffeurs
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Chauffeur::class);

        $query = QueryBuilder::for(Chauffeur::class)
            ->allowedFilters([
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('statut'),
                AllowedFilter::partial('nom'),
                AllowedFilter::partial('prenom'),
                AllowedFilter::partial('telephone'),
                AllowedFilter::scope('permis_expirant_dans', 'permisExpirantDans'),
            ])
            ->allowedSorts(['nom', 'prenom', 'date_embauche', 'statut', 'created_at'])
            ->allowedIncludes(['agence', 'affectationActive.vehicule'])
            ->defaultSort('nom')
            ->with(['agence']);

        $this->applyAgenceScope($query, $request);

        $chauffeurs = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => ChauffeurResource::collection($chauffeurs),
            'meta' => [
                'total'        => $chauffeurs->total(),
                'per_page'     => $chauffeurs->perPage(),
                'current_page' => $chauffeurs->currentPage(),
                'last_page'    => $chauffeurs->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/chauffeurs
     */
    public function store(StoreChauffeurRequest $request): JsonResponse
    {
        $this->authorize('create', Chauffeur::class);

        $data = collect($request->validated())
            ->except(['photo_permis', 'creer_compte', 'compte_password'])
            ->toArray();

        DB::beginTransaction();
        try {
            // Création optionnelle d'un compte applicatif
            if ($request->boolean('creer_compte') && $request->filled('email')) {
                // Vérifier que l'email n'est pas déjà pris
                if (User::where('email', $request->email)->exists()) {
                    return $this->error(
                        "L'email {$request->email} est déjà utilisé par un compte existant.",
                        422
                    );
                }

                $user = User::create([
                    'agence_id' => $data['agence_id'] ?? null,
                    'name'      => $data['nom'],
                    'prenom'    => $data['prenom'],
                    'email'     => $data['email'],
                    'telephone' => $data['telephone'] ?? null,
                    'fonction'  => $request->input('compte_fonction'),
                    'password'  => Hash::make($request->compte_password),
                    'est_actif' => true,
                ]);

                // Assigner le rôle 'chauffeur' — guard 'web' explicite
                // (les rôles sont tous créés avec guard 'web' dans le seeder)
                $user->assignRole(
                    \Spatie\Permission\Models\Role::findByName('chauffeur', 'web')
                );
                $data['user_id'] = $user->id;
            }

            $chauffeur = Chauffeur::create($data);

            if ($request->hasFile('photo_permis')) {
                $path = $this->uploadPhotoPermis($request->file('photo_permis'), $chauffeur->id);
                $chauffeur->update(['photo' => $path]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de la création : ' . $e->getMessage(), 500);
        }

        return $this->created(
            new ChauffeurResource($chauffeur->load('agence')),
            'Chauffeur créé avec succès.' .
            ($data['user_id'] ?? null ? ' Compte applicatif créé.' : '')
        );
    }

    /**
     * GET /api/v1/chauffeurs/{chauffeur}
     */
    public function show(Chauffeur $chauffeur): JsonResponse
    {
        $this->authorize('view', $chauffeur);

        $chauffeur->load([
            'agence',
            'user',
            'affectationActive.vehicule',
            'affectationActive.axeLivraison',
        ]);

        return $this->success(new ChauffeurResource($chauffeur));
    }

    /**
     * PUT /api/v1/chauffeurs/{chauffeur}
     */
    public function update(UpdateChauffeurRequest $request, Chauffeur $chauffeur): JsonResponse
    {
        $this->authorize('update', $chauffeur);

        $data = collect($request->validated())
            ->except(['photo_permis', 'creer_compte', 'compte_password', 'compte_fonction'])
            ->toArray();

        DB::beginTransaction();
        try {
            $chauffeur->update($data);

            // Création de compte si demandé et chauffeur sans compte
            if ($request->boolean('creer_compte') && !$chauffeur->user_id) {
                if (!$request->filled('email') && empty($chauffeur->email)) {
                    DB::rollBack();
                    return $this->error(
                        'Un email est obligatoire pour créer un compte. Renseignez l\'email du chauffeur.',
                        422
                    );
                }

                $email = $chauffeur->fresh()->email ?? $request->email;

                if (User::where('email', $email)->exists()) {
                    DB::rollBack();
                    return $this->error(
                        "L'email {$email} est déjà utilisé par un compte existant.",
                        422
                    );
                }

                $user = User::create([
                    'agence_id' => $chauffeur->agence_id,
                    'name'      => $chauffeur->nom,
                    'prenom'    => $chauffeur->prenom,
                    'email'     => $email,
                    'telephone' => $chauffeur->telephone,
                    'fonction'  => $request->input('compte_fonction'),
                    'password'  => Hash::make($request->compte_password),
                    'est_actif' => true,
                ]);

                $user->assignRole(
                    \Spatie\Permission\Models\Role::findByName('chauffeur', 'web')
                );

                $chauffeur->update(['user_id' => $user->id]);
            }

            // Photo permis
            if ($request->hasFile('photo_permis')) {
                if ($chauffeur->photo) {
                    Storage::disk(config('parc.uploads.disque', 'public'))->delete($chauffeur->photo);
                }
                $path = $this->uploadPhotoPermis($request->file('photo_permis'), $chauffeur->id);
                $chauffeur->update(['photo' => $path]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de la mise à jour : ' . $e->getMessage(), 500);
        }

        return $this->success(
            new ChauffeurResource($chauffeur->fresh(['agence', 'user'])),
            'Chauffeur mis à jour.' . ($request->boolean('creer_compte') ? ' Compte créé.' : '')
        );
    }

    /**
     * DELETE /api/v1/chauffeurs/{chauffeur}
     */
    public function destroy(Chauffeur $chauffeur): JsonResponse
    {
        $this->authorize('delete', $chauffeur);

        if ($chauffeur->affectationActive) {
            return $this->error(
                'Impossible de supprimer un chauffeur avec une affectation active.',
                422
            );
        }

        $chauffeur->delete();

        return $this->noContent('Chauffeur supprimé.');
    }

    /**
     * GET /api/v1/chauffeurs/{chauffeur}/vehicule-actuel
     */
    public function vehiculeActuel(Chauffeur $chauffeur): JsonResponse
    {
        $this->authorize('view', $chauffeur);

        $affectation = $chauffeur->affectationActive()->with([
            'vehicule.agence',
            'axeLivraison',
        ])->first();

        if (!$affectation) {
            return $this->success(null, 'Aucun véhicule affecté actuellement.');
        }

        return $this->success([
            'affectation' => [
                'id'             => $affectation->id,
                'date_debut'     => $affectation->date_debut?->format('Y-m-d'),
                'type'           => $affectation->type_affectation,
                'axe_livraison'  => $affectation->axeLivraison?->nom,
                'km_debut'       => $affectation->kilometrage_debut,
            ],
            'vehicule' => $affectation->vehicule ? [
                'id'             => $affectation->vehicule->id,
                'immatriculation'=> $affectation->vehicule->immatriculation,
                'marque'         => $affectation->vehicule->marque,
                'modele'         => $affectation->vehicule->modele,
                'type_vehicule'  => $affectation->vehicule->type_vehicule,
                'statut'         => $affectation->vehicule->statut,
                'kilometrage'    => $affectation->vehicule->kilometrage_actuel,
            ] : null,
        ]);
    }

    /**
     * GET /api/v1/chauffeurs/{chauffeur}/historique
     */
    public function historique(Request $request, Chauffeur $chauffeur): JsonResponse
    {
        $this->authorize('view', $chauffeur);

        $affectations = $chauffeur->affectations()
            ->with(['vehicule', 'axeLivraison', 'agence'])
            ->orderBy('date_debut', 'desc')
            ->paginate($this->perPage($request));

        $stats = [
            'total_affectations' => $chauffeur->affectations()->count(),
            'total_maintenances' => $chauffeur->maintenances()->count(),
            'total_signalements' => $chauffeur->signalements()->count(),
            'total_km'           => $chauffeur->affectations()
                ->whereNotNull('kilometrage_fin')
                ->selectRaw('SUM(kilometrage_fin - kilometrage_debut) as total')
                ->value('total') ?? 0,
        ];

        return response()->json([
            'data'  => $affectations->items(),
            'stats' => $stats,
            'meta'  => [
                'total'        => $affectations->total(),
                'current_page' => $affectations->currentPage(),
            ],
        ]);
    }

    // ── Helpers privés ────────────────────────────────────────

    private function uploadPhotoPermis(UploadedFile $fichier, int $chauffeurId): string
    {
        $disk    = config('parc.uploads.disque', 'public');
        $dossier = "chauffeurs/{$chauffeurId}/permis";

        if (!Storage::disk($disk)->exists($dossier)) {
            Storage::disk($disk)->makeDirectory($dossier);
        }

        $nomFichier = sprintf(
            'permis_%d_%s.%s',
            $chauffeurId,
            now()->format('Ymd_His'),
            $fichier->getClientOriginalExtension()
        );

        $path = $fichier->storeAs($dossier, $nomFichier, $disk);

        if (!$path) {
            throw new \RuntimeException('Échec de l\'upload de la photo du permis.');
        }

        return $path;
    }
}