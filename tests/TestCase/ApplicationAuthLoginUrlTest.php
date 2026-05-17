<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Application;
use Authentication\AuthenticationService;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

/**
 * Regression test for the subfolder-deploy auth login URL bug.
 *
 * The bug: `loginUrl` was hardcoded to bare `/login`. On a subfolder
 * deploy (e.g. tools.example.com/qsl/), POSTs to /qsl/login were
 * silently rejected by the Form authenticator's _checkUrl path-equality
 * check, which returned FAIL_OTHER. The user saw "Invalid email or
 * password" even with correct credentials.
 *
 * Fix: getAuthenticationService() reads App.base from Configure and
 * prepends it to loginUrl + unauthenticatedRedirect. Root deploys
 * (App.base unset/empty) → loginUrl='/login' (unchanged). Subfolder
 * deploys (App.base='/qsl') → loginUrl='/qsl/login'.
 */
final class ApplicationAuthLoginUrlTest extends TestCase
{
    private mixed $originalBase = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalBase = Configure::read('App.base');
    }

    protected function tearDown(): void
    {
        Configure::write('App.base', $this->originalBase);
        parent::tearDown();
    }

    private function getAuthService(): AuthenticationService
    {
        $app = new Application(dirname(__DIR__, 2) . '/config');
        /** @var AuthenticationService $svc */
        $svc = $app->getAuthenticationService(new ServerRequest());
        return $svc;
    }

    public function testRootDeployLoginUrlIsBareLogin(): void
    {
        Configure::write('App.base', '');
        $svc = $this->getAuthService();

        $authenticators = $svc->authenticators();
        $form = $authenticators->get('Form');
        $this->assertNotNull($form);
        $this->assertSame('/login', $form->getConfig('loginUrl'));

        $this->assertSame('/login', $svc->getConfig('unauthenticatedRedirect'));
    }

    public function testSubfolderDeployLoginUrlIncludesBase(): void
    {
        Configure::write('App.base', '/qsl');
        $svc = $this->getAuthService();

        $form = $svc->authenticators()->get('Form');
        $this->assertSame('/qsl/login', $form->getConfig('loginUrl'),
            'Form authenticator loginUrl must include the deploy base path so its _checkUrl strict-path-match accepts /qsl/login POSTs.');

        $this->assertSame('/qsl/login', $svc->getConfig('unauthenticatedRedirect'),
            'Unauthenticated redirects must go to /qsl/login on a subfolder deploy, not bare /login.');
    }

    public function testSubfolderBaseWithTrailingSlashIsNormalised(): void
    {
        // Defensive: in case someone sets App.base='/qsl/' (with trailing
        // slash), the loginUrl should still be /qsl/login, not /qsl//login.
        Configure::write('App.base', '/qsl/');
        $svc = $this->getAuthService();

        $form = $svc->authenticators()->get('Form');
        $this->assertSame('/qsl/login', $form->getConfig('loginUrl'));
    }
}
