<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AppSettings;
use App\Service\CallsignLookup\CallsignLookupService;
use App\Service\CallsignLookup\Providers\LocalDirectoryProvider;
use App\Service\CallsignLookup\Providers\MartsProvider;
use App\Service\CallsignLookup\Providers\McmcProvider;
use App\Service\CallsignLookup\Providers\QrzProvider;
use App\Service\CallsignLookup\Providers\RadioIdProvider;
use App\Service\CallsignLookup\Providers\RapiProvider;

/**
 * JSON API for the QSO-form callsign auto-complete.
 *
 * GET /api/callsign/{call}
 *   200 application/json {result: {...}, source: 'radioid'} on hit
 *   204 No Content                                          on confirmed miss
 *   404 Not Found                                           when lookup disabled globally
 *
 * Authentication required (any signed-in user). Existing rate-limit
 * middleware doesn't cover this path; if abuse becomes a concern we can
 * wire a per-user throttle in the same middleware.
 */
class CallsignController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
        $this->viewBuilder()->setClassName('Json');
    }

    public function lookup(string $callsign)
    {
        // Build the provider chain. Keyed by code() so the orchestrator can
        // honor the admin priority order. Adding a new provider is a one-line
        // change here.
        // Provider chain. `local` is the admin-curated directory and goes
        // FIRST — once a callsign is in the directory, no external network
        // call should happen for it. Order after that matches registration;
        // admin can reorder via `callsign_lookup_providers` in settings.
        $service = new CallsignLookupService(
            providers: [
                'local'   => new LocalDirectoryProvider(),
                'qrz'     => new QrzProvider(),
                'mcmc'    => new McmcProvider(),
                'marts'   => new MartsProvider(),
                'radioid' => new RadioIdProvider(),
                'rapi'    => new RapiProvider(),
            ],
            settings: new AppSettings(),
        );

        if (!$service->isEnabled()) {
            // Return a JSON body explaining why; the front-end logs this
            // but doesn't surface it to the user.
            return $this->response
                ->withStatus(404)
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Callsign lookup is disabled on this install.']));
        }

        $result = $service->resolve($callsign);
        if ($result === null) {
            // 204 = no content. CakePHP's view layer would otherwise look
            // for a Json/lookup.php template here; returning the response
            // object directly short-circuits that.
            return $this->response->withStatus(204);
        }
        $this->set(['result' => $result->toArray()]);
        $this->viewBuilder()->setOption('serialize', ['result']);
        return null;
    }
}
