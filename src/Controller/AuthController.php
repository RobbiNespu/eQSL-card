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
     * Login.
     *
     * On a successful authentication result, redirect to `?redirect=` if
     * present, otherwise `/`. On a POST that did not authenticate, flash
     * "Invalid email or password" and re-render the form. GET simply
     * renders the form.
     *
     * @return \Cake\Http\Response|null
     */
    public function login()
    {
        $result = $this->Authentication->getResult();
        if ($result?->isValid()) {
            return $this->redirect($this->request->getQuery('redirect') ?? '/');
        }
        if ($this->request->is('post') && (!$result || !$result->isValid())) {
            $this->Flash->error('Invalid email or password');
        }

        return null;
    }

    /**
     * Logout. Clears the session via Authentication and redirects to /login.
     *
     * @return \Cake\Http\Response|null
     */
    public function logout()
    {
        $this->Authentication->logout();

        return $this->redirect('/login');
    }

    /**
     * Forgot-password.
     *
     * GET renders the email form. POST issues a single-use reset token via
     * `PasswordResetService` and sends an email containing the reset link.
     * The flash message intentionally does not reveal whether an account
     * exists, to avoid leaking user-enumeration signal.
     *
     * @return \Cake\Http\Response|null
     */
    public function forgot()
    {
        if ($this->request->is('post')) {
            $email = (string)$this->request->getData('email');
            $svc = new \App\Service\PasswordResetService();
            $token = $svc->issue($email);
            $link = (string)env('APP_BASE_URL', 'http://localhost:8080') . '/password/reset/' . $token;
            $mailer = new \Cake\Mailer\Mailer('default');
            $mailer->setTo($email)
                ->setSubject('Reset your eQSL password')
                ->setEmailFormat('both')
                ->setViewVars(['link' => $link])
                ->viewBuilder()->setTemplate('password_reset');
            $mailer->deliver();
            $this->Flash->success('If that email exists, a reset link has been sent.');

            return $this->redirect('/login');
        }

        return null;
    }

    /**
     * Reset-password.
     *
     * GET renders the new-password form (token is round-tripped to the
     * action via the URL — no need to embed in a hidden field, but we do
     * pass it to the view in case the template wants it).
     *
     * POST consumes the token via `PasswordResetService::consume`, which
     * is single-shot. On a bad token we flash and bounce to /password/forgot.
     * On success we set the user's password (the `_setPassword` mutator
     * applies Argon2id from T5) and send them to /login.
     *
     * @param string $token Reset token from the password-reset email.
     * @return \Cake\Http\Response|null
     */
    public function reset(string $token)
    {
        if ($this->request->is('post')) {
            $svc = new \App\Service\PasswordResetService();
            try {
                $email = $svc->consume($token);
            } catch (\Throwable $e) {
                $this->Flash->error($e->getMessage());

                return $this->redirect('/password/forgot');
            }
            $users = $this->fetchTable('Users');
            $user = $users->find()->where(['email' => $email])->firstOrFail();
            $user->password = (string)$this->request->getData('password');
            $users->saveOrFail($user);
            $this->Flash->success('Password updated. Please log in.');

            return $this->redirect('/login');
        }
        $this->set('token', $token);

        return null;
    }
}
