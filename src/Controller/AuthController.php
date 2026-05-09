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

        $this->loadComponent('Authentication.Authentication');
        $this->Authentication->allowUnauthenticated([
            'register',
            'login',
            'forgot',
            'reset',
        ]);
    }

    /**
     * Register a new user.
     *
     * GET renders the registration form. POST validates that
     * `password` and `password_confirm` match, then creates the user
     * with role `user` and redirects to `/login`. On mismatch we re-render
     * the form with a flash error so the integration test can assert the
     * "do not match" message in the response body.
     *
     * @return \Cake\Http\Response|null
     */
    public function register()
    {
        $users = $this->fetchTable('Users');
        $entity = $users->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            if (($data['password'] ?? null) !== ($data['password_confirm'] ?? null)) {
                $this->Flash->error('Passwords do not match');
                $this->set('user', $entity);

                return null;
            }
            $entity = $users->newEntity([
                'name' => $data['name'] ?? '',
                'callsign' => $data['callsign'] ?? '',
                'email' => $data['email'] ?? '',
                'password' => $data['password'] ?? '',
                'role' => 'user',
            ]);
            if ($users->save($entity)) {
                $this->Flash->success('Account created. Please log in.');

                return $this->redirect('/login');
            }
        }
        $this->set('user', $entity);

        return null;
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
