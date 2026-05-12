<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class HelpControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testIndexReturns200WhenLoggedOut(): void
    {
        // The /help/index.php template doesn't exist yet (lands in Task 2.2).
        // Skip until then so this commit stays revertable.
        $this->markTestSkipped('Awaits Task 2.2 (Help/index.php template)');
    }

    public function testKnownArticleReturns200(): void
    {
        // Article stubs ship in Task 4.1; until then the rendered template
        // is missing. Skip and let Task 4 enable the assertion.
        $this->markTestSkipped('Awaits Task 4.1 article stubs');
    }

    public function testUnknownCategoryReturns404(): void
    {
        $this->get('/help/not-a-category/welcome');
        $this->assertResponseCode(404);
    }

    public function testUnknownSlugReturns404(): void
    {
        $this->get('/help/getting-started/not-a-page');
        $this->assertResponseCode(404);
    }

    public function testRouteRejectsPathTraversalAttempt(): void
    {
        // The route regex (`[a-z][a-z0-9-]*`) on {category} and {slug}
        // means `/help/../../etc/passwd` cannot match the `view` route —
        // both `..` segments fail the pattern. Once the typed `view`
        // route is out of the way the request falls through to Cake's
        // `fallbacks()`, which synthesizes a controller action from the
        // path segment. That action doesn't exist on HelpController, so
        // dispatch raises MissingActionException → 404.
        //
        // The AuthenticationMiddleware sits BEFORE the controller in the
        // pipeline and only allowUnauthenticates the real {index, view}
        // actions, so an anonymous hit on the synthesized action would
        // bounce to /login (302). Stamp a session up-front to skip that
        // and prove the request never reaches a real HelpController
        // action regardless of auth state.
        $this->session(['Auth' => ['id' => 1]]);
        $this->get('/help/..%2f..%2fetc/passwd');
        $this->assertResponseCode(404);
    }
}
