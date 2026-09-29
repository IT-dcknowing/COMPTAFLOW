<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        // ATTENTION : cette application tourne en production avec APP_ENV=local.
        // Se fier a l'environnement laisserait Telescope tout enregistrer sur
        // le serveur — mots de passe compris — et ouvrirait /telescope a tout
        // le monde. On exige donc un interrupteur explicite.
        $isLocal = $this->app->environment('local')
            && (bool) env('TELESCOPE_TOUT_ENREGISTRER', false);

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        // Meme remarque : on ne masque PAS moins parce que l'environnement se
        // dit local. Les jetons et mots de passe restent caches partout.
        if ($this->app->environment('local') && (bool) env('TELESCOPE_TOUT_ENREGISTRER', false)) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            // Telescope montre chaque requete, chaque payload et chaque
            // requete SQL du serveur. Seul un super administrateur y entre.
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        });
    }
}
