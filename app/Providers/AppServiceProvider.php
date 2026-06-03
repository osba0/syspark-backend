<?php

namespace App\Providers;

use App\Models\Affectation;
use App\Models\BonCommande;
use App\Models\Carburant;
use App\Models\Chauffeur;
use App\Models\Checklist;
use App\Models\DocumentVehicule;
use App\Models\Maintenance;
use App\Models\Signalement;
use App\Models\Vehicule;
use App\Observers\InvaliderCacheObserver;
use App\Observers\DocumentVehiculeObserver;
use App\Policies\AffectationPolicy;
use App\Policies\BonCommandePolicy;
use App\Policies\CarburantPolicy;
use App\Policies\ChauffeurPolicy;
use App\Policies\ChecklistPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\SignalementPolicy;
use App\Policies\VehiculePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // ============================================================
    // Policies
    // ============================================================
    protected array $policies = [
        Vehicule::class        => VehiculePolicy::class,
        Chauffeur::class       => ChauffeurPolicy::class,
        Affectation::class     => AffectationPolicy::class,
        Checklist::class       => ChecklistPolicy::class,
        Signalement::class     => SignalementPolicy::class,
        Maintenance::class     => MaintenancePolicy::class,
        BonCommande::class     => BonCommandePolicy::class,
        DocumentVehicule::class=> DocumentPolicy::class,
        Carburant::class       => CarburantPolicy::class,
    ];

    public function register(): void
    {
        // Bind services en singleton
        $this->app->singleton(\App\Services\AlerteService::class);
        $this->app->singleton(\App\Services\StatistiqueService::class);
        $this->app->singleton(\App\Services\TcoService::class);
        $this->app->singleton(\App\Services\ChecklistService::class);
    }

    public function boot(): void
    {
        // Enregistrer les Policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Super Admin bypass total
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        // Gate pour les rapports (utilisé dans RapportController)
        Gate::define('exportRapport', fn ($user) => $user->can('rapport.export'));

        // ============================================================
        // Observers — invalident le cache stats après chaque mutation
        // ============================================================
        Maintenance::observe(InvaliderCacheObserver::class);
        Carburant::observe(InvaliderCacheObserver::class);
        Vehicule::observe(InvaliderCacheObserver::class);
        Signalement::observe(InvaliderCacheObserver::class);

        // Résolution automatique des alertes document_manquant
        DocumentVehicule::observe(DocumentVehiculeObserver::class);

        // HTTPS en production
        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}