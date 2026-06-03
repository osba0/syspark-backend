<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Alerte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlerteController extends BaseApiController
{
    /**
     * GET /api/v1/alertes
     */
    public function index(Request $request): JsonResponse
    {
        $agenceId = $this->getAgenceScopeId($request);

        $alertes = Alerte::with(['vehicule:id,immatriculation,marque,modele', 'chauffeur:id,nom,prenom'])
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->when($request->statut, fn ($q) => $q->where('statut', $request->statut))
            ->when($request->niveau, fn ($q) => $q->where('niveau', $request->niveau))
            ->when($request->type_alerte, fn ($q) => $q->where('type_alerte', $request->type_alerte))
            ->orderByRaw("FIELD(niveau, 'danger', 'warning', 'info')")
            ->orderBy('echeance')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $alertes->items(),
            'meta' => ['total' => $alertes->total(), 'current_page' => $alertes->currentPage()],
        ]);
    }

    /**
     * GET /api/v1/alertes/non-lues
     */
    public function nonLues(Request $request): JsonResponse
    {
        $agenceId = $this->getAgenceScopeId($request);
        $userId   = $request->user()->id;

        $alertes = Alerte::actives()
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->with(['vehicule:id,immatriculation'])
            ->orderByRaw("FIELD(niveau, 'danger', 'warning', 'info')")
            ->get()
            ->filter(fn ($a) => !$a->est_lue_par);

        return $this->success([
            'total'  => $alertes->count(),
            'danger' => $alertes->where('niveau', 'danger')->count(),
            'alertes'=> $alertes->take(15)->values(),
        ]);
    }

    /**
     * PUT /api/v1/alertes/{alerte}/lue
     */
    public function marquerLue(Request $request, Alerte $alerte): JsonResponse
    {
        $alerte->marquerLuePar($request->user()->id);
        return $this->success(null, 'Alerte marquée comme lue.');
    }

    /**
     * POST /api/v1/alertes/marquer-toutes-lues
     */
    public function marquerToutesLues(Request $request): JsonResponse
    {
        $agenceId = $this->getAgenceScopeId($request);
        $userId   = $request->user()->id;

        $alertes = Alerte::actives()
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->get();

        foreach ($alertes as $alerte) {
            $alerte->marquerLuePar($userId);
        }

        return $this->success(null, $alertes->count() . ' alertes marquées comme lues.');
    }
}
