<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\VehiculeController;
use App\Http\Controllers\Api\V1\ChauffeurController;
use App\Http\Controllers\Api\V1\AffectationController;
use App\Http\Controllers\Api\V1\MaintenanceController;
use App\Http\Controllers\Api\V1\ChecklistController;
use App\Http\Controllers\Api\V1\SignalementController;
use App\Http\Controllers\Api\V1\CarburantController;
use App\Http\Controllers\Api\V1\PneumatiqueController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\BonCommandeController;
use App\Http\Controllers\Api\V1\FournisseurController;
use App\Http\Controllers\Api\V1\AlerteController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\RapportController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AgenceController;
use App\Http\Controllers\Api\V1\AxeLivraisonController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\EmailQueueController;
use App\Http\Controllers\Api\V1\RolePermissionController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\CommunicationController;
use Illuminate\Support\Facades\Route;

// ============================================================
// Routes publiques (sans authentification)
// ============================================================
Route::prefix('v1')->name('v1.')->group(function () {

    // Route publique — nom de l'application + logos (favicon, page de login)
    Route::get('app-info', function () {
        $config = \App\Models\ConfigEntreprise::instance();
        return response()->json([
            'app_name'     => config('app.name', 'Parc Auto'),
            'logo_url'     => $config->logo_url,
            'logo_app_url' => $config->logo_app_url,
        ]);
    })->name('app.info');

    // Auth
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login',           [AuthController::class, 'login'])->name('login');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('password.forgot');
    });

    // ============================================================
    // Routes protégées — nécessitent un token Sanctum valide
    // ============================================================
    Route::middleware([
        'auth:sanctum',
        'throttle:120,1',
        \App\Http\Middleware\EnsureAgenceAccess::class,
        \App\Http\Middleware\LogApiActivity::class,
    ])->group(function () {

        // --- Auth utilisateur connecté ---
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout'])->name('logout');
            Route::delete('logout-all',    [AuthController::class, 'logoutAll'])->name('logout.all');
            Route::get('me',               [AuthController::class, 'me'])->name('me');
            Route::put('profile',          [AuthController::class, 'updateProfile'])->name('profile.update');
            Route::post('password/change', [AuthController::class, 'changePassword'])->name('password.change');
        });

        // --- Dashboard ---
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('stats',            [DashboardController::class, 'stats'])->name('stats');
            Route::get('alertes-actives',  [DashboardController::class, 'alertesActives'])->name('alertes');
            Route::get('activite-recente', [DashboardController::class, 'activiteRecente'])->name('activite');
            Route::get('kpi-mensuel',      [DashboardController::class, 'kpiMensuel'])->name('kpi');
            Route::get('top-vehicules',    [DashboardController::class, 'topVehicules'])->name('top-vehicules');
        });

        // --- Agences (lecture publique pour tous les rôles authentifiés) ---
        Route::get('agences', [AgenceController::class, 'index'])
            ->name('agences.public');
        Route::get('admin/agences', [AgenceController::class, 'index'])
            ->name('agences.public.admin')
            ->withoutMiddleware(['check.role:super_admin,directeur']);

        // --- Axes de livraison (lecture publique pour tous les rôles authentifiés) ---
        Route::get('axes-livraison', [AxeLivraisonController::class, 'index'])
            ->name('axes-livraison.public');

        // --- Notifications in-app ---
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/',            [NotificationController::class, 'index'])->name('index');
            Route::get('compteur',     [NotificationController::class, 'compteur'])->name('compteur');
            Route::post('tout-lire',   [NotificationController::class, 'toutLire'])->name('tout-lire');
            Route::post('{id}/lire',   [NotificationController::class, 'marquerLue'])->name('lire');
            Route::delete('lues',      [NotificationController::class, 'viderLues'])->name('vider');
            Route::delete('{id}',      [NotificationController::class, 'destroy'])->name('destroy');
        });

        // --- Journal d'audit (accès adapté au rôle — voir AuditLogController) ---
        Route::prefix('audit-logs')->name('audit-logs.')->middleware('permission:admin.logs.view')->group(function () {
            Route::get('/',            [AuditLogController::class, 'index'])->name('index');
            Route::get('modules',      [AuditLogController::class, 'modules'])->name('modules');
            Route::get('utilisateurs', [AuditLogController::class, 'utilisateurs'])->name('utilisateurs');
        });

        // --- Communication (Annonces / Notes de service) ---
        // Routes accessibles à tous les rôles authentifiés ; le scoping fin
        // (gestion vs consultation) est appliqué par CommunicationPolicy
        // et CommunicationController.
        Route::prefix('communications')->name('communications.')->group(function () {
            // Consultation — tous rôles
            Route::get('actives',             [CommunicationController::class, 'actives'])->name('actives');
            Route::get('critiques-non-lues',  [CommunicationController::class, 'critiquesNonLues'])->name('critiques-non-lues');
            Route::get('historique',          [CommunicationController::class, 'historique'])->name('historique');
            Route::post('{communication}/lire', [CommunicationController::class, 'marquerLue'])->name('lire');

            // Gestion — protégée par CommunicationPolicy
            Route::get('/',                       [CommunicationController::class, 'index'])->name('index');
            Route::post('/',                      [CommunicationController::class, 'store'])->name('store');
            Route::get('{communication}',         [CommunicationController::class, 'show'])->name('show');
            Route::put('{communication}',         [CommunicationController::class, 'update'])->name('update');
            Route::delete('{communication}',      [CommunicationController::class, 'destroy'])->name('destroy');
            Route::post('{communication}/publier', [CommunicationController::class, 'publier'])->name('publier');
            Route::post('{communication}/archiver',[CommunicationController::class, 'archiver'])->name('archiver');
        });

        // --- Véhicules ---
        Route::prefix('vehicules')->name('vehicules.')->group(function () {
            Route::get('/',                        [VehiculeController::class, 'index'])->name('index');
            Route::post('/',                       [VehiculeController::class, 'store'])->name('store');
            Route::get('{vehicule}',               [VehiculeController::class, 'show'])->name('show');
            Route::put('{vehicule}',               [VehiculeController::class, 'update'])->name('update');
            Route::delete('{vehicule}',            [VehiculeController::class, 'destroy'])->name('destroy');
            Route::put('{vehicule}/kilometrage',   [VehiculeController::class, 'updateKilometrage'])->name('km');
            Route::get('{vehicule}/tco',           [VehiculeController::class, 'tco'])->name('tco');
            Route::get('{vehicule}/maintenances',  [VehiculeController::class, 'maintenances'])->name('maintenances');
            Route::get('{vehicule}/carburant',     [VehiculeController::class, 'carburant'])->name('carburant');
            Route::get('{vehicule}/documents',     [VehiculeController::class, 'documents'])->name('documents');
            Route::get('{vehicule}/checklists',    [VehiculeController::class, 'checklists'])->name('checklists');
            Route::get('{vehicule}/signalements',  [VehiculeController::class, 'signalements'])->name('signalements');
            Route::get('{vehicule}/affectations',  [VehiculeController::class, 'affectations'])->name('affectations');
            Route::get('{vehicule}/alertes',       [VehiculeController::class, 'alertes'])->name('alertes');
            // Photos
            Route::get('{vehicule}/photos',                          [VehiculeController::class, 'photos'])->name('photos');
            Route::post('{vehicule}/photos',                         [VehiculeController::class, 'uploadPhoto'])->name('photos.upload');
            Route::delete('{vehicule}/photos/{mediaId}',             [VehiculeController::class, 'deletePhoto'])->name('photos.delete');
            Route::put('{vehicule}/photos/{mediaId}/principale',     [VehiculeController::class, 'setPhotoPrincipale'])->name('photos.principale');
        });

        // --- Chauffeurs ---
        Route::prefix('chauffeurs')->name('chauffeurs.')->group(function () {
            Route::get('/',                            [ChauffeurController::class, 'index'])->name('index');
            Route::post('/',                           [ChauffeurController::class, 'store'])->name('store');
            Route::get('{chauffeur}',                  [ChauffeurController::class, 'show'])->name('show');
            Route::put('{chauffeur}',                  [ChauffeurController::class, 'update'])->name('update');
            Route::post('{chauffeur}',                 [ChauffeurController::class, 'update'])->name('update.multipart');
            Route::delete('{chauffeur}',               [ChauffeurController::class, 'destroy'])->name('destroy');
            Route::get('{chauffeur}/vehicule-actuel',  [ChauffeurController::class, 'vehiculeActuel'])->name('vehicule');
            Route::get('{chauffeur}/historique',       [ChauffeurController::class, 'historique'])->name('historique');
            Route::post('{chauffeur}/photo',           [ChauffeurController::class, 'uploadPhoto'])->name('photo');
            Route::delete('{chauffeur}/photo',         [ChauffeurController::class, 'deletePhoto'])->name('photo.delete');
        });

        // --- Affectations ---
        Route::prefix('affectations')->name('affectations.')->group(function () {
            Route::get('/',                        [AffectationController::class, 'index'])->name('index');
            Route::post('/',                       [AffectationController::class, 'store'])->name('store');
            Route::get('actives',                  [AffectationController::class, 'actives'])->name('actives');
            Route::get('{affectation}',            [AffectationController::class, 'show'])->name('show');
            Route::put('{affectation}',            [AffectationController::class, 'update'])->name('update');
            Route::post('{affectation}/cloturer',  [AffectationController::class, 'cloturer'])->name('cloturer');
        });

        // --- Checklists ---
        Route::prefix('checklists')->name('checklists.')->group(function () {
            Route::get('/',                        [ChecklistController::class, 'index'])->name('index');
            Route::post('/',                       [ChecklistController::class, 'store'])->name('store');
            Route::get('{checklist}',              [ChecklistController::class, 'show'])->name('show');
            Route::put('{checklist}',              [ChecklistController::class, 'update'])->name('update');
            Route::post('{checklist}/soumettre',   [ChecklistController::class, 'soumettre'])->name('soumettre');
            Route::post('{checklist}/valider',     [ChecklistController::class, 'valider'])->name('valider');
            Route::post('{checklist}/rejeter',     [ChecklistController::class, 'rejeter'])->name('rejeter');
        });

        // --- Signalements ---
        Route::prefix('signalements')->name('signalements.')->group(function () {
            Route::get('/',                                [SignalementController::class, 'index'])->name('index');
            Route::post('/',                               [SignalementController::class, 'store'])->name('store');
            Route::get('{signalement}',                    [SignalementController::class, 'show'])->name('show');
            Route::put('{signalement}',                    [SignalementController::class, 'update'])->name('update');
            Route::post('{signalement}/prendre-en-charge', [SignalementController::class, 'prendreEnCharge'])->name('pec');
            Route::post('{signalement}/resoudre',          [SignalementController::class, 'resoudre'])->name('resoudre');
            Route::post('{signalement}/creer-maintenance', [SignalementController::class, 'creerMaintenance'])->name('maintenance');
            Route::post('{signalement}/photos',            [SignalementController::class, 'uploadPhotos'])->name('photos');
        });

        // --- Maintenances ---
        Route::prefix('maintenances')->name('maintenances.')->group(function () {
            Route::get('/',                        [MaintenanceController::class, 'index'])->name('index');
            Route::post('/',                       [MaintenanceController::class, 'store'])->name('store');
            Route::get('stats',                    [MaintenanceController::class, 'stats'])->name('stats');
            Route::get('planifiees',               [MaintenanceController::class, 'planifiees'])->name('planifiees');
            Route::get('{maintenance}',            [MaintenanceController::class, 'show'])->name('show');
            Route::put('{maintenance}',            [MaintenanceController::class, 'update'])->name('update');
            Route::delete('{maintenance}',         [MaintenanceController::class, 'destroy'])->name('destroy');
            Route::post('{maintenance}/approuver', [MaintenanceController::class, 'approuver'])->name('approuver');
            Route::post('{maintenance}/cloturer',  [MaintenanceController::class, 'cloturer'])->name('cloturer');
        });

        // --- Carburant ---
        Route::prefix('carburant')->name('carburant.')->group(function () {
            Route::get('/',                        [CarburantController::class, 'index'])->name('index');
            Route::post('/',                       [CarburantController::class, 'store'])->name('store');
            Route::get('stats',                    [CarburantController::class, 'stats'])->name('stats');
            Route::get('dotations',                [CarburantController::class, 'dotations'])->name('dotations');
            Route::post('dotations',               [CarburantController::class, 'storeDotation'])->name('dotations.store');
            Route::put('dotations/{dotation}',     [CarburantController::class, 'updateDotation'])->name('dotations.update');
            Route::get('{carburant}',              [CarburantController::class, 'show'])->name('show');
            Route::put('{carburant}',              [CarburantController::class, 'update'])->name('update');
            Route::delete('{carburant}',           [CarburantController::class, 'destroy'])->name('destroy');
        });

        // --- Pneumatiques ---
        Route::get('pneumatiques/stats', [PneumatiqueController::class, 'stats'])->name('pneumatiques.stats');
        Route::apiResource('pneumatiques', PneumatiqueController::class);

        // --- Documents véhicules ---
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/',                        [DocumentController::class, 'index'])->name('index');
            Route::post('/',                       [DocumentController::class, 'store'])->name('store');
            Route::get('expiration-prochaine',     [DocumentController::class, 'expirationProchaine'])->name('expiration');
            Route::get('{document}',               [DocumentController::class, 'show'])->name('show');
            Route::put('{document}',               [DocumentController::class, 'update'])->name('update');
            Route::delete('{document}',            [DocumentController::class, 'destroy'])->name('destroy');
            Route::post('{document}/renouveler',   [DocumentController::class, 'renouveler'])->name('renouveler');
        });

        // --- Bons de commande ---
        Route::prefix('bons-commande')->name('bons-commande.')->group(function () {
            Route::get('/',                        [BonCommandeController::class, 'index'])->name('index');
            Route::post('/',                       [BonCommandeController::class, 'store'])->name('store');
            Route::get('{bonCommande}',            [BonCommandeController::class, 'show'])->name('show');
            Route::put('{bonCommande}',            [BonCommandeController::class, 'update'])->name('update');
            Route::delete('{bonCommande}',         [BonCommandeController::class, 'destroy'])->name('destroy');
            Route::post('{bonCommande}/soumettre', [BonCommandeController::class, 'soumettre'])->name('soumettre');
            Route::post('{bonCommande}/approuver', [BonCommandeController::class, 'approuver'])->name('approuver');
            Route::post('{bonCommande}/rejeter',   [BonCommandeController::class, 'rejeter'])->name('rejeter');
            Route::post('{bonCommande}/executer',  [BonCommandeController::class, 'executer'])->name('executer');
        });

        // --- Fournisseurs ---
        Route::apiResource('fournisseurs', FournisseurController::class);
        Route::get('fournisseurs/{fournisseur}/interventions', [FournisseurController::class, 'interventions']);
        Route::get('fournisseurs/{fournisseur}/stats',         [FournisseurController::class, 'stats']);
        Route::post('fournisseurs/{fournisseur}/logo',         [FournisseurController::class, 'uploadLogo']);
        Route::delete('fournisseurs/{fournisseur}/logo',       [FournisseurController::class, 'deleteLogo']);

        // --- Alertes ---
        Route::prefix('alertes')->name('alertes.')->group(function () {
            Route::get('/',                    [AlerteController::class, 'index'])->name('index');
            Route::get('non-lues',             [AlerteController::class, 'nonLues'])->name('non-lues');
            Route::put('{alerte}/lue',         [AlerteController::class, 'marquerLue'])->name('lue');
            Route::post('marquer-toutes-lues', [AlerteController::class, 'marquerToutesLues'])->name('toutes-lues');
        });

        // --- Rapports (données JSON + exports fichiers) ---
        Route::prefix('rapports')->name('rapports.')->group(function () {
            Route::get('maintenance', [RapportController::class, 'maintenance'])->name('maintenance');
            Route::get('carburant',   [RapportController::class, 'carburant'])->name('carburant');
            Route::get('tco',         [RapportController::class, 'tco'])->name('tco');
            Route::get('axes',        [RapportController::class, 'axes'])->name('axes');
            Route::get('parc-global', [RapportController::class, 'parcGlobal'])->name('parc');

            Route::prefix('pdf')->name('pdf.')->group(function () {
                Route::get('signalement/{signalement}',  [RapportController::class, 'pdfSignalement'])->name('signalement');
                Route::get('checklist/{checklist}',      [RapportController::class, 'pdfChecklist'])->name('checklist');
                Route::get('affectation/{affectation}',  [RapportController::class, 'pdfAffectation'])->name('affectation');
                Route::get('bon-commande/{bonCommande}', [RapportController::class, 'pdfBonCommande'])->name('bon_commande');
                Route::get('vehicule/{vehicule}',        [RapportController::class, 'pdfVehicule'])->name('vehicule');
                Route::post('maintenance',               [RapportController::class, 'pdfMaintenance'])->name('maintenance');
            });

            Route::prefix('excel')->name('excel.')->group(function () {
                Route::get('maintenance',   [RapportController::class, 'excelMaintenance'])->name('maintenance');
                Route::get('carburant',     [RapportController::class, 'excelCarburant'])->name('carburant');
                Route::get('bons-commande', [RapportController::class, 'excelBonsCommande'])->name('bons-commande');
                Route::get('parc-global',   [RapportController::class, 'excelParcGlobal'])->name('parc');
            });
        });

        // --- Administration ---
        Route::prefix('admin')->name('admin.')->middleware('check.role:super_admin,directeur')->group(function () {
            Route::apiResource('users', AdminController::class);
            Route::post('users/{user}/toggle-actif',   [AdminController::class, 'toggleActif'])->name('users.toggle');
            Route::post('users/{user}/reset-password', [AdminController::class, 'resetPassword'])->name('users.reset-pwd');
            Route::apiResource('agences',              AgenceController::class);
            Route::apiResource('axes-livraison',       AxeLivraisonController::class);
            Route::get('logs',                         [AdminController::class, 'logs'])->name('logs');

            // Rôles & permissions — super_admin uniquement
            Route::prefix('roles')->name('roles.')->middleware('check.role:super_admin')->group(function () {
                Route::get('/',                             [RolePermissionController::class, 'index'])->name('index');
                Route::get('{role}/permissions',            [RolePermissionController::class, 'show'])->name('permissions.show');
                Route::put('{role}/permissions',            [RolePermissionController::class, 'sync'])->name('permissions.sync');
                Route::post('{role}/permissions/attribuer', [RolePermissionController::class, 'attribuer'])->name('permissions.attribuer');
                Route::post('{role}/permissions/retirer',   [RolePermissionController::class, 'retirer'])->name('permissions.retirer');
            });

            // Configuration entreprise
            Route::get('config',  [AdminController::class, 'getConfig'])->name('config.get')
                ->withoutMiddleware('check.role:super_admin,directeur');
            Route::post('config', [AdminController::class, 'updateConfig'])->middleware('check.role:super_admin')->name('config.update');

            // File d'attente emails — audit et stats
            Route::prefix('email-queue')->name('email-queue.')->group(function () {
                Route::get('/',                [EmailQueueController::class, 'index'])->name('index');
                Route::get('stats',            [EmailQueueController::class, 'stats'])->name('stats');
                Route::post('{email}/relancer',[EmailQueueController::class, 'relancer'])->name('relancer');
                Route::delete('purger-envoyes',[EmailQueueController::class, 'purgerEnvoyes'])->name('purger');
            });

            // Tâches système — super_admin uniquement
            Route::post('system/run-command', [AdminController::class, 'runCommand'])
                ->middleware('check.role:super_admin')
                ->name('system.run-command');
        });

    }); // fin middleware auth:sanctum
}); // fin prefix v1