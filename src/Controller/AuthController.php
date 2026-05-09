<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Auth Controller
 *
 * Action stubs for the authentication flow. Real bodies land in T15-T17:
 *  - T15: register
 *  - T16: login / logout
 *  - T17: forgot / reset
 */
class AuthController extends AppController
{
    /**
     * Initialize hook.
     *
     * `/login`, `/register`, `/password/forgot`, and `/password/reset/{token}`
     * must be reachable by visitors who are not yet authenticated; otherwise
     * AuthenticationMiddleware would redirect them to `/login` and create
     * a loop on the login page itself.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->Authentication->allowUnauthenticated([
            'register',
            'login',
            'forgot',
            'reset',
        ]);
    }

    /**
     * Register action stub (T15).
     *
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * Login action stub (T16).
     *
     * @return void
     */
    public function login(): void
    {
    }

    /**
     * Logout action stub (T16).
     *
     * @return void
     */
    public function logout(): void
    {
    }

    /**
     * Forgot-password action stub (T17).
     *
     * @return void
     */
    public function forgot(): void
    {
    }

    /**
     * Reset-password action stub (T17).
     *
     * @param string $token Reset token from the password-reset email.
     * @return void
     */
    public function reset(string $token): void
    {
    }
}
