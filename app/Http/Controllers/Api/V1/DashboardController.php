<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\StatistiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function __construct(private StatistiqueService $statsService) {}

    /**
     * GET /api/v1/dashboard/stats
     * KPIs principaux — retournés depuis le cache (< 50ms)
     */
    public function stats(Request $request): JsonResponse
    {
        $agenceId = $this->getAgenceScopeId($request);
        $kpi      = $this->statsService->kpiGlobaux($agenceId);

        return $this->success($kpi);
    }

    /**
     * GET /api/v1/dashboard/alertes-actives
     */
    public function alertesActives(Request $request): JsonResponse
    {
        $agenceId = $this->getAgenceScopeId($request);

        $alertes = \App\Models\Alerte::actives()
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->with(['vehicule:id,immatriculation,marque,modele', 'chauffeur:id,nom,prenom'])
            ->orderByRaw("FIELD(niveau, 'danger', 'warning', 'info')")
            ->orderBy('echeance')
            ->limit(20)
            ->get();

        return $this->success($alertes->map(fn ($a) => [
            'id'           => $a->id,
            'type_alerte'  => $a->type_alerte,
            'titre'        => $a->titre,
            'message'      => $a->message,
            'niveau'       => $a->niveau,
            'echeance'     => $a->echeance?->format('Y-m-d'),
            'jours_restants'=> $a->jours_restants,
            'vehicule'     => $a->vehicule ? [
                'id'             => $a->vehicule->id,
                'immatriculation'=> $a->vehicule->immatriculation,
            ] : null,
            'chauffeur'    => $a->chauffeur ? [
                'id'  => $a->chauffeur->id,
                'nom' => $a->chauffeur->nom_complet,
            ] : null,
        ]));
    }

    /**
     * GET /api/v1/dashboard/activite-recente
     */
    public function activiteRecente(Request $request): JsonResponse
    {
        $agenceId = $this->getAgenceScopeId($request);
        $limit    = min((int)$request->input('limit', 10), 25);

        $maintenances = \App\Models\Maintenance::with('vehicule:id,immatriculation')
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->latest()->limit($limit)->get()
            ->map(fn ($m) => [
                'type'       => 'maintenance',
                'id'         => $m->id,
                'titre'      => $m->titre,
                'sous_titre' => $m->vehicule?->immatriculation,
                'montant'    => $m->montant_ttc,
                'statut'     => $m->statut,
                'date'       => $m->created_at?->toISOString(),
            ]);

        $signalements = \App\Models\Signalement::with('vehicule:id,immatriculation')
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->latest()->limit($limit)->get()
            ->map(fn ($s) => [
                'type'       => 'signalement',
                'id'         => $s->id,
                'titre'      => $s->titre,
                'sous_titre' => $s->vehicule?->immatriculation,
                'gravite'    => $s->gravite,
                'statut'     => $s->statut,
                'date'       => $s->created_at?->toISOString(),
            ]);

        $activites = $maintenances
            ->concat($signalements)
            ->sortByDesc('date')
            ->values()
            ->take($limit);

        return $this->success($activites);
    }

    /**
     * GET /api/v1/dashboard/kpi-mensuel
     * Évolution sur 12 mois pour les graphiques Recharts
     */
    public function kpiMensuel(Request $request): JsonResponse
    {
        $annee    = (int)$request->input('annee', date('Y'));
        $agenceId = $this->getAgenceScopeId($request);

        $evolution = $this->statsService->evolutionMensuelle($annee, $agenceId);

        return $this->success($evolution);
    }

    /**
     * GET /api/v1/dashboard/top-vehicules
     */
    public function topVehicules(Request $request): JsonResponse
    {
        $annee    = (int)$request->input('annee', date('Y'));
        $limite   = min((int)$request->input('limite', 10), 20);
        $agenceId = $this->getAgenceScopeId($request);

        $top = $this->statsService->topVehiculesCouteux($annee, $limite, $agenceId);

        return $this->success($top);
    }
}
