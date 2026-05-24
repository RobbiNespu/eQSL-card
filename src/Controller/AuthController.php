<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OperationLog;

/**
 * Auth Controller
 *
 * Handles all authentication flows: register, login, logout, forgot/reset
 * password, and email verification. All routes are open to unauthenticated
 * visitors except logout (which requires a valid session to do anything
 * meaningful, but is harmless to call unauthenticated).
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
            'verify',
            'resendVerification',
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
                OperationLog::event('auth.register', ['user_id' => (int)$entity->id]);
                // M4-T13: send a verification email. Failure here must not
                // block account creation — the user can still log in and
                // request a resend, so we swallow + log the error.
                try {
                    $svc = new \App\Service\EmailVerificationService();
                    $token = $svc->issue($entity->email);
                    $verifyUrl = (string)env('APP_BASE_URL', 'http://localhost:8080')
                        . '/email/verify/' . $token;
                    $mailer = new \Cake\Mailer\Mailer('default');
                    $mailer->setTo($entity->email)
                        ->setSubject('Verify your eQSL Card account')
                        ->setEmailFormat('both')
                        ->setViewVars(['verifyUrl' => $verifyUrl, 'name' => $entity->name])
                        ->viewBuilder()->setTemplate('email_verify');
                    $mailer->deliver();
                } catch (\Throwable $e) {
                    error_log('email verify send: ' . $e->getMessage());
                    OperationLog::failure('auth.verify_email_send', $e, ['user_id' => (int)$entity->id]);
                }
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
            $identity = $this->Authentication->getIdentity();
            OperationLog::event('auth.login', ['user_id' => (int)$identity->getIdentifier()]);

            return $this->redirect($this->request->getQuery('redirect') ?? '/');
        }
        if ($this->request->is('post') && (!$result || !$result->isValid())) {
            OperationLog::warning('auth.login.failed', []);
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
        $identity = $this->Authentication->getIdentity();
        if ($identity) {
            OperationLog::event('auth.logout', ['user_id' => (int)$identity->getIdentifier()]);
        }
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
            try {
                $token = $svc->issue($email);
                $link = (string)env('APP_BASE_URL', 'http://localhost:8080') . '/password/reset/' . $token;
                $mailer = new \Cake\Mailer\Mailer('default');
                $mailer->setTo($email)
                    ->setSubject('Reset your eQSL password')
                    ->setEmailFormat('both')
                    ->setViewVars(['link' => $link])
                    ->viewBuilder()->setTemplate('password_reset');
                $mailer->deliver();
                OperationLog::event('auth.password_reset_requested', []);
            } catch (\Throwable $e) {
                error_log('password reset send: ' . $e->getMessage());
                OperationLog::failure('auth.password_reset_send', $e, []);
            }
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
            OperationLog::event('auth.password_reset', ['user_id' => (int)$user->id]);
            $this->Flash->success('Password updated. Please log in.');

            return $this->redirect('/login');
        }
        $this->set('token', $token);

        return null;
    }

    /**
     * Email verification (M4-T14).
     *
     * GET-only endpoint. Consumes the token via `EmailVerificationService`,
     * which is single-shot and stamps `users.email_verified_at` on success.
     * On failure (unknown / used / expired token) we flash the error and
     * still redirect to /login so the user has a clear next step (they can
     * request a resend from there).
     *
     * @param string $token Verification token from the email link.
     * @return \Cake\Http\Response|null
     */
    public function verify(string $token)
    {
        $svc = new \App\Service\EmailVerificationService();
        try {
            $svc->consume($token);
            OperationLog::event('auth.email_verified', []);
            $this->Flash->success('Email verified — you can now log in.');
        } catch (\Throwable $e) {
            OperationLog::warning('auth.email_verify_failed', []);
            $this->Flash->error($e->getMessage());
        }

        return $this->redirect('/login');
    }

    /**
     * Resend the verification email (M4-T14).
     *
     * POST-only. Rate-limited to 1 send per email per hour via the file-cache
     * `RateLimiter` from M1-T22; the rate-limit key hashes the email so the
     * cache filename does not leak addresses on a shared host.
     *
     * Like `forgot()`, this endpoint does not reveal whether an account
     * exists or whether it is already verified — the flash message is the
     * same in every case to avoid leaking enumeration signal.
     *
     * @return \Cake\Http\Response
     */
    public function resendVerification()
    {
        $this->request->allowMethod('post');
        $email = trim((string)$this->request->getData('email', ''));
        if ($email === '') {
            $this->Flash->error('Email required.');

            return $this->redirect('/login');
        }

        $rl = new \App\Service\RateLimiter(TMP . 'cache/rate_limits');
        if (!$rl->hit('verify_resend', hash('sha256', $email), limit: 1, windowSeconds: 3600)) {
            $this->Flash->error('Verification email already sent recently. Try again later.');

            return $this->redirect('/login');
        }

        $user = $this->fetchTable('Users')->find()
            ->where(['email' => $email, 'email_verified_at IS' => null])
            ->first();
        if ($user) {
            try {
                $token = (new \App\Service\EmailVerificationService())->issue($email);
                $verifyUrl = (string)env('APP_BASE_URL', 'http://localhost:8080')
                    . '/email/verify/' . $token;
                $mailer = new \Cake\Mailer\Mailer('default');
                $mailer->setTo($email)
                    ->setSubject('Verify your eQSL Card account (resent)')
                    ->setEmailFormat('both')
                    ->setViewVars(['verifyUrl' => $verifyUrl, 'name' => $user->name])
                    ->viewBuilder()->setTemplate('email_verify');
                $mailer->deliver();
            } catch (\Throwable $e) {
                error_log('resend verify: ' . $e->getMessage());
                OperationLog::failure('auth.resend_verify_send', $e, ['user_id' => (int)$user->id]);
            }
        }

        $this->Flash->success('If that email exists and is not yet verified, a new verification link has been sent.');

        return $this->redirect('/login');
    }
}
