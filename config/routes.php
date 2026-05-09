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
        $builder->connect('/cards/{id}', ['controller' => 'Cards', 'action' => 'view'])
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
