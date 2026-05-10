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
        $builder->connect('/qsos/import', ['controller' => 'Qsos', 'action' => 'import'])
            ->setMethods(['GET', 'POST']);
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
