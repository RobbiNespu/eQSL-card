<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/*
 * This file is loaded in the context of the `Application` class.
 * So you can use `$this` to reference the application class instance
 * if required.
 */
return function (RouteBuilder $routes): void {
    /*
     * The default class to use for all routes
     *
     * The following route classes are supplied with CakePHP and are appropriate
     * to set as the default:
     *
     * - Route
     * - InflectedRoute
     * - DashedRoute
     *
     * If no call is made to `Router::defaultRouteClass()`, the class used is
     * `Route` (`Cake\Routing\Route\Route`)
     *
     * Note that `Route` does not do any inflections on URLs which will result in
     * inconsistently cased URLs when used with `{plugin}`, `{controller}` and
     * `{action}` markers.
     */
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/', function (RouteBuilder $builder): void {
        /*
         * M5 T18 — PWA manifest, served dynamically so the start_url /
         * scope / icon URLs include the deploy's base path. Static file
         * approach broke for subfolder deploys (tools.example.com/qsl/)
         * where the icons end up referencing the parent host root.
         * PwaController::manifest is allowUnauthenticated so browsers
         * can fetch it pre-login to decide installability.
         */
        $builder->connect('/manifest.webmanifest', ['controller' => 'Pwa', 'action' => 'manifest'])
            ->setMethods(['GET']);

        /*
         * Public guest form (T19). The root URL renders the QSL generator
         * form; POST /generate produces the rendered card (T20).
         */
        $builder->connect('/', ['controller' => 'Public', 'action' => 'index']);
        $builder->connect('/generate', ['controller' => 'Public', 'action' => 'generate'])
            ->setMethods(['POST']);

        // Logged-in user landing page. Anonymous hits redirect to /login.
        $builder->connect('/dashboard', ['controller' => 'Dashboard', 'action' => 'index'])
            ->setMethods(['GET']);

        /*
         * Skeleton Pages controller is kept for /pages/* helper URLs.
         */
        $builder->connect('/pages/*', 'Pages::display');

        /*
         * Authentication routes (T14).
         */
        $builder->connect('/register', ['controller' => 'Auth', 'action' => 'register'])
            ->setMethods(['GET', 'POST']);
        $builder->connect('/login', ['controller' => 'Auth', 'action' => 'login'])
            ->setMethods(['GET', 'POST']);
        $builder->connect('/logout', ['controller' => 'Auth', 'action' => 'logout'])
            ->setMethods(['POST']);
        $builder->connect('/password/forgot', ['controller' => 'Auth', 'action' => 'forgot'])
            ->setMethods(['GET', 'POST']);
        $builder->connect('/password/reset/:token', ['controller' => 'Auth', 'action' => 'reset'])
            ->setPass(['token'])
            ->setMethods(['GET', 'POST']);

        /*
         * M4-T14: email verification endpoints.
         *
         * The static `/resend` route MUST be declared BEFORE the parametrized
         * `/email/verify/{token}` route, otherwise CakePHP would greedily
         * match `verify(token='resend')` against the literal `/resend` path.
         * The token pattern is additionally constrained to the 43-char
         * URL-safe base64 alphabet that `EmailVerificationService::issue()`
         * always emits, so anything else 404s at the routing layer.
         */
        $builder->connect('/email/verify/resend', ['controller' => 'Auth', 'action' => 'resendVerification'])
            ->setMethods(['POST']);
        $builder->connect('/email/verify/{token}', ['controller' => 'Auth', 'action' => 'verify'])
            ->setPass(['token'])
            ->setPatterns(['token' => '[A-Za-z0-9_\-]{43}'])
            ->setMethods(['GET']);

        // Public docs portal — no auth required.
        $builder->connect('/help', ['controller' => 'Help', 'action' => 'index']);
        $builder->connect('/help/{category}/{slug}', ['controller' => 'Help', 'action' => 'view'])
            ->setPatterns(['category' => '[a-z][a-z0-9-]*', 'slug' => '[a-z][a-z0-9-]*'])
            ->setPass(['category', 'slug']);

        /*
         * Logbook routes (M2-T2/T3).
         * `index` is paginated list with search/filter; `view` shows a single
         * QSO; `add`/`edit`/`delete` are the manual CRUD surfaces.
         *
         * Static segments (`/new`, `/{id}/edit`, `/{id}/delete`) MUST be
         * declared before the parametrized `/qsos/{id}` route, AND we
         * additionally constrain the latter's `id` to digits so a stray order
         * change in the future cannot cause `/qsos/new` to be matched as
         * `view(id='new')`.
         */
        $builder->connect('/qsos', ['controller' => 'Qsos', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/qsos/new', ['controller' => 'Qsos', 'action' => 'add'])
            ->setMethods(['GET', 'POST']);
        // M5 T7 — Quick-add: portable-first one-thumb entry surface.
        // Minimal form, auto-fills date/time UTC at submit, derives band from
        // frequency, carries operator name/QTH/grid from the user's previous
        // QSO. Stays on the page after save (no redirect) so the operator can
        // log the next contact without leaving the keyboard.
        $builder->connect('/qsos/quick', ['controller' => 'Qsos', 'action' => 'quick'])
            ->setMethods(['GET', 'POST']);
        $builder->connect('/qsos/import', ['controller' => 'Qsos', 'action' => 'import'])
            ->setMethods(['GET', 'POST']);

        // M5 T14 — Activations: portable-session grouping (POTA / SOTA /
        // IOTA / field day). Owner-scoped at the controller layer.
        // Static segments declared before parametrized /{id} so they
        // aren't shadowed by the action route.
        $builder->connect('/activations', ['controller' => 'Activations', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/activations', ['controller' => 'Activations', 'action' => 'start'])
            ->setMethods(['POST']);
        $builder->connect('/activations/{id}/end', ['controller' => 'Activations', 'action' => 'end'])
            ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['POST']);
        $builder->connect('/activations/{id}/edit', ['controller' => 'Activations', 'action' => 'edit'])
            ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['GET', 'POST', 'PUT', 'PATCH']);
        $builder->connect('/activations/{id}/delete', ['controller' => 'Activations', 'action' => 'delete'])
            ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['POST']);
        // M5 T17 — ADIF export per activation. Filename ends in .adi which
        // POTA / SOTA / LoTW portals all accept as the upload target.
        $builder->connect('/activations/{id}/export.adi', ['controller' => 'Activations', 'action' => 'export'])
            ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['GET']);
        // Callsign auto-complete JSON API. Authenticated; the QSO add form
        // calls this on debounced input change to pre-fill operator name /
        // QTH / grid square. See App\Service\CallsignLookup for the
        // orchestrator + provider chain.
        $builder->connect('/api/callsign/{callsign}', ['controller' => 'Callsign', 'action' => 'lookup'])
            ->setPass(['callsign'])
            ->setPatterns(['callsign' => '[A-Za-z0-9\/]{3,15}'])
            ->setMethods(['GET']);

        // M5 T25 — Dupe-check API for the quick-add callsign field.
        // Called per-keystroke (debounced 200 ms client-side) to colour
        // the inline traffic-light badge. Owner-scoped at SQL layer.
        $builder->connect('/api/qsos/dupe-check', ['controller' => 'Qsos', 'action' => 'dupeCheck'])
            ->setMethods(['GET']);

        $builder->connect('/qsos/{id}/edit', ['controller' => 'Qsos', 'action' => 'edit'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET', 'POST', 'PUT', 'PATCH']);
        $builder->connect('/qsos/{id}/delete', ['controller' => 'Qsos', 'action' => 'delete'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);
        // M2-T10: render-from-QSO. Static segment `/render` MUST be declared
        // BEFORE the parametrized `/qsos/{id}` route so it isn't shadowed.
        // The controller action is named `renderCard` because `render` collides
        // with `Cake\Controller\Controller::render()`.
        $builder->connect('/qsos/{id}/render', ['controller' => 'Qsos', 'action' => 'renderCard'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET', 'POST']);
        // M2-T11: bulk render endpoints. Two POST surfaces — the first mints a
        // session-scoped job token + renders chunk #1, subsequent calls supply
        // the token to render the next chunk. Both routes are declared BEFORE
        // the parametrized `/qsos/{id}` route so the static `bulk-render`
        // segment cannot be matched as `view(id='bulk-render')`.
        $builder->connect('/qsos/bulk-render', ['controller' => 'Qsos', 'action' => 'bulkRender'])
            ->setMethods(['POST']);
        $builder->connect('/qsos/bulk-render/{token}/next', ['controller' => 'Qsos', 'action' => 'bulkRenderNext'])
            ->setPass(['token'])
            ->setMethods(['POST']);
        $builder->connect('/qsos/{id}', ['controller' => 'Qsos', 'action' => 'view'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET']);

        /*
         * Card library routes (M2-T7 ships index; T8 wires up view).
         *
         * The `/cards/{id}` route is connected here in advance so T8 only has
         * to add the controller action. `id` is constrained to digits so any
         * future static segment (`/cards/new`, `/cards/import`, …) added
         * BEFORE this line will match cleanly without ambiguity.
         */
        // Background-image library (per-user). Edit/delete also serve admin
        // operating-on-anyone's backgrounds — controller-level role check.
        // Renamed from /uploads to /card-backgrounds when the underlying
        // table was rebranded; the legacy URLs below 301-redirect here so
        // existing bookmarks and shared links keep working.
        $builder->connect('/card-backgrounds', ['controller' => 'CardBackgrounds', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/card-backgrounds/{id}/edit', ['controller' => 'CardBackgrounds', 'action' => 'edit'])
            ->setPass(['id'])->setMethods(['GET', 'POST', 'PUT', 'PATCH'])->setPatterns(['id' => '\d+']);
        $builder->connect('/card-backgrounds/{id}/delete', ['controller' => 'CardBackgrounds', 'action' => 'delete'])
            ->setPass(['id'])->setMethods(['POST'])->setPatterns(['id' => '\d+']);

        // Back-compat: old /uploads URLs redirect (301) to the new
        // /card-backgrounds equivalents so existing bookmarks survive.
        $builder->redirect('/uploads', '/card-backgrounds', ['status' => 301]);
        $builder->redirect('/uploads/:id/edit', '/card-backgrounds/:id/edit', ['status' => 301, 'persist' => ['id']]);
        $builder->redirect('/uploads/:id/delete', '/card-backgrounds/:id/delete', ['status' => 301, 'persist' => ['id']]);

        $builder->connect('/cards', ['controller' => 'Cards', 'action' => 'index'])
            ->setMethods(['GET']);
        // `/cards/{id}/delete` (M2-T9 soft-delete) MUST be declared BEFORE
        // the parametrized `/cards/{id}` route — otherwise CakePHP would
        // greedily match `view(id='123/delete')` (or with the digit pattern,
        // simply fail). The id pattern is constrained to digits for the same
        // reason as the qsos delete route.
        $builder->connect('/cards/{id}/delete', ['controller' => 'Cards', 'action' => 'delete'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);
        // M2-T13 share endpoint. Like `/delete`, this static-suffixed route
        // MUST be declared BEFORE the parametrized `/cards/{id}` so it isn't
        // shadowed. The id pattern is constrained to digits for the same
        // reason as the qsos/cards delete routes — a future static segment
        // (`/cards/recent`, …) added BEFORE this line would still match
        // cleanly without ambiguity.
        $builder->connect('/cards/{id}/share', ['controller' => 'Cards', 'action' => 'share'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);
        // M2-T16 revoke endpoint. Same ordering rule as `/share` and `/delete`:
        // static-suffixed routes MUST come BEFORE the parametrized `/cards/{id}`
        // so they aren't shadowed; the digit pattern guards against future
        // static segments accidentally matching this slot.
        $builder->connect('/cards/{id}/revoke', ['controller' => 'Cards', 'action' => 'revoke'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);
        // Lazy PDF download — builds the PDF on demand from the rendered card
        // image so we don't have to persist a duplicate of the PNG bytes.
        // Same ordering rule as other static-suffixed /cards/{id}/* routes.
        $builder->connect('/cards/{id}/download.pdf', ['controller' => 'Cards', 'action' => 'downloadPdf'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET']);
        $builder->connect('/cards/{id}', ['controller' => 'Cards', 'action' => 'view'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET']);

        /*
         * Template designer routes (M3-T2 scaffold).
         *
         * `/templates/new` and `/templates/{id}/edit` both render the shared
         * designer view (`templates/Templates/edit.php`); the controller
         * passes a `mode` flag so the Alpine factory knows whether to POST
         * to add or edit (real save lands in M3-T4). Static segments are
         * declared BEFORE the parametrized `/templates/{id}` view route
         * (matching the qsos/cards ordering rule), and `id` is constrained
         * to digits so a future `/templates/gallery` (or similar) added
         * later still routes cleanly.
         */
        $builder->connect('/templates', ['controller' => 'Templates', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/templates/new', ['controller' => 'Templates', 'action' => 'add'])
            ->setMethods(['GET', 'POST']);
        // M3-T5: designer background uploader. Static segment MUST be declared
        // BEFORE the parametrized `/templates/{id}` view route so it isn't
        // matched as `view(id='upload-background')` — same ordering rule we
        // use for /qsos and /cards static-suffixed routes.
        $builder->connect('/templates/upload-background', ['controller' => 'Templates', 'action' => 'uploadBackground'])
            ->setMethods(['POST']);
        $builder->connect('/templates/{id}/edit', ['controller' => 'Templates', 'action' => 'edit'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET', 'POST', 'PUT', 'PATCH']);
        // M3-T8: clone-and-edit. Static-suffixed route MUST come BEFORE the
        // parametrized `/templates/{id}` view route so it isn't shadowed; the
        // digit pattern matches the same ordering rule used elsewhere.
        $builder->connect('/templates/{id}/clone', ['controller' => 'Templates', 'action' => 'clone'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);
        // Hard-delete a user's own template. POST-only and static-suffixed for
        // the same reasons as the qsos/cards delete routes.
        $builder->connect('/templates/{id}/delete', ['controller' => 'Templates', 'action' => 'delete'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);
        $builder->connect('/templates/{id}', ['controller' => 'Templates', 'action' => 'view'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET']);

        /*
         * M2-T14: Public share landing.
         *
         * `/qsl/{slug}` resolves a 43-char URL-safe base64 slug to a public
         * share page. The slug pattern is strict (43 chars from the URL-safe
         * base64 alphabet) — anything else 404s at the routing layer before
         * touching the controller.
         *
         * `/qsl/{slug}/unlock` is the password-gate target M2-T15 will fully
         * implement; the route + a stub controller action exist now so the
         * `share()` redirect for password-protected cards has a valid
         * destination today.
         */
        $builder->connect('/qsl/{slug}', ['controller' => 'Public', 'action' => 'share'])
            ->setPass(['slug'])
            ->setPatterns(['slug' => '[A-Za-z0-9_\-]{43}'])
            ->setMethods(['GET']);
        $builder->connect('/qsl/{slug}/unlock', ['controller' => 'Public', 'action' => 'unlock'])
            ->setPass(['slug'])
            ->setPatterns(['slug' => '[A-Za-z0-9_\-]{43}'])
            ->setMethods(['GET', 'POST']);
        // Share-respecting PDF download — checks revocation + password
        // unlock state before streaming. See PublicController::downloadSharePdf.
        $builder->connect('/qsl/{slug}/download.pdf', ['controller' => 'Public', 'action' => 'downloadSharePdf'])
            ->setPass(['slug'])
            ->setPatterns(['slug' => '[A-Za-z0-9_\-]{43}'])
            ->setMethods(['GET']);

        /*
         * M4-T15/T16: per-user profile + avatar upload.
         *
         * `/profile` is the form surface (GET renders + POST patches a fixed
         * allow-list of fields); `/profile/avatar` is POST-only because GET
         * to a destructive endpoint would let prefetchers / link-previews
         * accidentally trigger an upload-with-empty-payload flash error.
         */
        $builder->connect('/profile', ['controller' => 'Profile', 'action' => 'index'])
            ->setMethods(['GET', 'POST']);
        $builder->connect('/profile/avatar', ['controller' => 'Profile', 'action' => 'uploadAvatar'])
            ->setMethods(['POST']);

        // M6 — NCS dashboard (owner/co-logger; auth enforced in controller).
        // Static segments (/new) MUST be declared BEFORE parametrised /{id}
        // routes so they aren't shadowed. id patterns constrained to digits
        // for the same reason as all other CRUD resources here.
        $builder->connect('/net-sessions', ['controller' => 'NetSessions', 'action' => 'index']);
        $builder->connect('/net-sessions/new', ['controller' => 'NetSessions', 'action' => 'add'])
            ->setMethods(['GET', 'POST']);
        $builder->connect('/net-sessions/{id}/edit', ['controller' => 'NetSessions', 'action' => 'edit'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->connect('/net-sessions/{id}/start', ['controller' => 'NetSessions', 'action' => 'start'])
            ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['POST']);
        $builder->connect('/net-sessions/{id}/end', ['controller' => 'NetSessions', 'action' => 'end'])
            ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['POST']);
        $builder->connect('/net-sessions/{id}/delete', ['controller' => 'NetSessions', 'action' => 'delete'])
            ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['POST']);
        $builder->connect('/net-sessions/{id}', ['controller' => 'NetSessions', 'action' => 'view'])
            ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['GET']);

        /*
         * Connect catchall routes for all controllers.
         *
         * The `fallbacks` method is a shortcut for
         *
         * ```
         * $builder->connect('/{controller}', ['action' => 'index']);
         * $builder->connect('/{controller}/{action}/*', []);
         * ```
         *
         * It is NOT recommended to use fallback routes after your initial prototyping phase!
         * See https://book.cakephp.org/5/en/development/routing.html#fallbacks-method for more information
         */
        $builder->fallbacks();
    });

    /*
     * Admin-only surfaces (M2-T18). The `prefix('Admin', …)` scope makes
     * `/admin/upgrade` resolve to `App\Controller\Admin\UpgradeController`
     * with templates at `templates/Admin/Upgrade/`. Access control lives in
     * the controller's `beforeFilter()` (admin role required); anonymous
     * users hit the Authentication middleware first and get redirected to
     * `/login`.
     */
    $routes->prefix('Admin', function (\Cake\Routing\RouteBuilder $builder): void {
        // M4-T5: dashboard root. Admin landing page with counts, storage
        // usage, and the most recent audit-log rows. Declared first so
        // `/admin` resolves cleanly regardless of which Admin/* surfaces
        // get added later.
        $builder->connect('/', ['controller' => 'Dashboard', 'action' => 'index'])
            ->setMethods(['GET']);

        $builder->connect('/upgrade', ['controller' => 'Upgrade', 'action' => 'index'])
            ->setMethods(['GET', 'POST']);

        /*
         * Template moderation queue (M3-T10/T11/T12). Listing the pending
         * queue is GET; approve/reject are POST-only and pass the id.
         * Approve/reject id is constrained to digits, matching the same
         * ordering rule used elsewhere — a future static segment
         * (`/templates/recent`, …) added BEFORE the parametrized routes
         * still routes cleanly.
         */
        // Alias: bare `/admin/templates` lands on the pending queue (the only
        // listing surface this controller ships). Without it a trimmed URL or
        // a guessed-from-nav `/admin/templates/` 404s with a Missing Controller
        // page, which looks like a real bug rather than a misnavigation.
        $builder->connect('/templates', ['controller' => 'Templates', 'action' => 'pending'])
            ->setMethods(['GET']);
        $builder->connect('/templates/pending', ['controller' => 'Templates', 'action' => 'pending'])
            ->setMethods(['GET']);
        $builder->connect('/templates/{id}/approve', ['controller' => 'Templates', 'action' => 'approve'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);
        $builder->connect('/templates/{id}/reject', ['controller' => 'Templates', 'action' => 'reject'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);

        /*
         * Users CRUD (M4-T6). List with `?q=` search, edit role, soft-delete.
         * Static-suffixed `/edit` and `/delete` routes use the same digit-only
         * id pattern as the rest of the codebase so a future static segment
         * (e.g. `/users/import`) can be slotted in BEFORE these without
         * accidentally being shadowed by `view(id='import')`-style matches.
         */
        $builder->connect('/users', ['controller' => 'Users', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/users/{id}/edit', ['controller' => 'Users', 'action' => 'edit'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET', 'POST', 'PUT', 'PATCH']);
        $builder->connect('/users/{id}/delete', ['controller' => 'Users', 'action' => 'delete'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);

        /*
         * All-cards browser (M4-T7) and audit log viewer (M4-T8). Both are
         * read-only GET surfaces gated by the admin role check in their
         * respective controller `beforeFilter()`s.
         */
        $builder->connect('/cards', ['controller' => 'Cards', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/audit', ['controller' => 'Audit', 'action' => 'index'])
            ->setMethods(['GET']);

        // Admin all-backgrounds listing (deep-links into the user-facing
        // /card-backgrounds/{id}/edit and /delete with
        // ?return=/admin/card-backgrounds so the redirect round-trips back
        // here). Legacy /admin/uploads URL 301-redirects to keep old
        // bookmarks alive.
        $builder->connect('/card-backgrounds', ['controller' => 'CardBackgrounds', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->redirect('/uploads', '/admin/card-backgrounds', ['status' => 301]);

        /*
         * Cleanup tools (M4-T9/T10/T11). `index` renders a dry-run preview
         * (counts + 5-row sample) of (a) guest cards older than `?days=` and
         * (b) orphaned uploads not referenced by any card and older than the
         * same cutoff. The two POST surfaces actually delete the rows + their
         * on-disk files and log a `cleanup.*` audit event. Static-suffixed
         * `/purge-guests` and `/prune-uploads` routes are declared with
         * explicit method restrictions so a GET to either endpoint can never
         * trigger a destructive action.
         */
        $builder->connect('/cleanup', ['controller' => 'Cleanup', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/cleanup/purge-guests', ['controller' => 'Cleanup', 'action' => 'purgeGuests'])
            ->setMethods(['POST']);
        $builder->connect('/cleanup/prune-uploads', ['controller' => 'Cleanup', 'action' => 'pruneUploads'])
            ->setMethods(['POST']);
        // Soft-delete user cards older than card_retention_days (admin setting).
        // No-op when the setting is 0 / unset — operators opt in to retention.
        $builder->connect('/cleanup/expire-cards', ['controller' => 'Cleanup', 'action' => 'expireCards'])
            ->setMethods(['POST']);
        // Drop the callsign_lookups cache. Use after enabling a better
        // provider or to force a re-fetch of stale data.
        $builder->connect('/cleanup/callsign-cache', ['controller' => 'Cleanup', 'action' => 'callsignCache'])
            ->setMethods(['POST']);

        /*
         * Callsign auto-complete cache admin. Lists rows from `callsign_lookups`
         * (what the provider chain has accumulated), lets the admin edit /
         * delete individual cache entries, edit the provider source settings
         * inline, and wipe the cache. Static-suffixed routes MUST come BEFORE
         * the parametrized `/callsign-lookups/{id}/...` ones, and the digit
         * pattern on `{id}` keeps a future static slot from being shadowed.
         */
        $builder->connect('/callsign-lookups', ['controller' => 'CallsignLookups', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/callsign-lookups/all', ['controller' => 'CallsignLookups', 'action' => 'all'])
            ->setMethods(['GET']);
        $builder->connect('/callsign-lookups/settings', ['controller' => 'CallsignLookups', 'action' => 'saveSettings'])
            ->setMethods(['POST']);
        $builder->connect('/callsign-lookups/clear', ['controller' => 'CallsignLookups', 'action' => 'clear'])
            ->setMethods(['POST']);
        $builder->connect('/callsign-lookups/{id}/edit', ['controller' => 'CallsignLookups', 'action' => 'edit'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['GET', 'POST', 'PUT', 'PATCH']);
        $builder->connect('/callsign-lookups/{id}/delete', ['controller' => 'CallsignLookups', 'action' => 'delete'])
            ->setPass(['id'])
            ->setPatterns(['id' => '\d+'])
            ->setMethods(['POST']);
        // Per-provider settings live under /admin/callsign-lookups/provider/{code}
        // so the chain UI can hand off to a dedicated config page for each one.
        // `local` mounts the existing CallsignDirectory controller (CSV manager);
        // the other codes resolve to a generic `provider($code)` action on
        // CallsignLookupsController which renders a status/info page until
        // the corresponding scraper is implemented and needs real settings.
        $builder->connect('/callsign-lookups/provider/local', ['controller' => 'CallsignDirectory', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/callsign-lookups/provider/local/upload', ['controller' => 'CallsignDirectory', 'action' => 'upload'])
            ->setMethods(['POST']);
        $builder->connect('/callsign-lookups/provider/local/clear', ['controller' => 'CallsignDirectory', 'action' => 'clear'])
            ->setMethods(['POST']);
        $builder->connect('/callsign-lookups/provider/{code}', ['controller' => 'CallsignLookups', 'action' => 'provider'])
            ->setPass(['code'])
            ->setPatterns(['code' => 'qrz|radioid_database_dump|radioid_api|mcmc|marts|rapi'])
            ->setMethods(['GET']);
        $builder->connect('/callsign-lookups/provider/radioid_database_dump/refresh', [
                'controller' => 'CallsignLookups', 'action' => 'refreshRadioIdDump',
            ])
            ->setMethods(['POST']);
        $builder->connect('/callsign-lookups/provider/radioid_database_dump/clear', [
                'controller' => 'CallsignLookups', 'action' => 'clearRadioIdDump',
            ])
            ->setMethods(['POST']);

        /*
         * Callsign directory admin (M4-followup). CSV import / search / clear.
         * The directory is the LocalDirectoryProvider's backing store and slots
         * in front of external providers in the lookup chain.
         */
        $builder->connect('/callsign-directory', ['controller' => 'CallsignDirectory', 'action' => 'index'])
            ->setMethods(['GET']);
        $builder->connect('/callsign-directory/upload', ['controller' => 'CallsignDirectory', 'action' => 'upload'])
            ->setMethods(['POST']);
        $builder->connect('/callsign-directory/clear', ['controller' => 'CallsignDirectory', 'action' => 'clear'])
            ->setMethods(['POST']);

        // Filesystem maintenance: nuke cache files + Cake's in-memory caches,
        // truncate logs, drop active sessions. Each is POST-only so a stray
        // GET (link prefetch, link preview, accidental refresh) cannot trigger
        // destruction. /sessions also signs the calling admin out — handled
        // inside the controller.
        $builder->connect('/cleanup/cache', ['controller' => 'Cleanup', 'action' => 'cache'])
            ->setMethods(['POST']);
        $builder->connect('/cleanup/logs', ['controller' => 'Cleanup', 'action' => 'logs'])
            ->setMethods(['POST']);
        $builder->connect('/cleanup/sessions', ['controller' => 'Cleanup', 'action' => 'sessions'])
            ->setMethods(['POST']);

        /*
         * App settings UI (M4-T17/T18). Single GET/POST surface — GET renders
         * the form pre-populated from the AppSettings runtime loader, POST
         * applies the fixed allow-list of keys and PRG-redirects back to the
         * form. Access control lives in `Admin\SettingsController::beforeFilter()`
         * (admin role required), matching the rest of the Admin prefix.
         */
        $builder->connect('/settings', ['controller' => 'Settings', 'action' => 'index'])
            ->setMethods(['GET', 'POST']);
        $builder->connect('/settings/background', ['controller' => 'Settings', 'action' => 'background'])
            ->setMethods(['POST']);
        $builder->connect('/settings/background/reset', ['controller' => 'Settings', 'action' => 'backgroundReset'])
            ->setMethods(['POST']);
    });

    $routes->scope('/install', function (\Cake\Routing\RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Install', 'action' => 'index'])->setMethods(['GET']);
        $builder->connect('/system-check', ['controller' => 'Install', 'action' => 'systemCheck'])->setMethods(['GET']);
        $builder->connect('/database', ['controller' => 'Install', 'action' => 'database'])->setMethods(['GET', 'POST']);
        $builder->connect('/migrate', ['controller' => 'Install', 'action' => 'migrate'])->setMethods(['GET', 'POST']);
        $builder->connect('/admin', ['controller' => 'Install', 'action' => 'admin'])->setMethods(['GET', 'POST']);
        $builder->connect('/complete', ['controller' => 'Install', 'action' => 'complete'])->setMethods(['GET']);
    });

    /*
     * If you need a different set of middleware or none at all,
     * open new scope and define routes there.
     *
     * ```
     * $routes->scope('/api', function (RouteBuilder $builder): void {
     *     // No $builder->applyMiddleware() here.
     *
     *     // Parse specified extensions from URLs
     *     // $builder->setExtensions(['json', 'xml']);
     *
     *     // Connect API actions here.
     * });
     * ```
     */
};
