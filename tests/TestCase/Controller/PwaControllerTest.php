<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * PwaController — M5 T18 integration tests.
 *
 * Covers:
 *  - GET /manifest.webmanifest returns 200 with application/manifest+json
 *  - Anonymous users can fetch it (browsers fetch pre-login to decide
 *    installability)
 *  - Payload has the required PWA fields: name, start_url, scope, icons
 *  - Root deploy: URLs have no prefix
 *  - Subfolder deploy: URLs include the base path
 *    (BasePathMiddleware sets the webroot attribute; we simulate that)
 */
final class PwaControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testManifestServesAsJsonWithoutAuth(): void
    {
        $this->get('/manifest.webmanifest');
        $this->assertResponseOk();
        $this->assertContentType('application/manifest+json');
    }

    public function testManifestPayloadHasRequiredFields(): void
    {
        $this->get('/manifest.webmanifest');
        $body = json_decode((string)$this->_response->getBody(), true);

        $this->assertIsArray($body);
        $this->assertSame('eQSL Card', $body['name']);
        $this->assertSame('eQSL', $body['short_name']);
        $this->assertSame('standalone', $body['display']);
        $this->assertArrayHasKey('icons', $body);
        $this->assertCount(2, $body['icons']);
        $this->assertSame('192x192', $body['icons'][0]['sizes']);
        $this->assertSame('512x512', $body['icons'][1]['sizes']);
    }

    public function testRootDeployHasUnprefixedUrls(): void
    {
        // Default test environment: webroot defaults to '/', so the
        // controller's trim('/', '/') yields '' and URLs are absolute
        // from root.
        $this->get('/manifest.webmanifest');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame('/qsos/quick', $body['start_url']);
        $this->assertSame('/', $body['scope']);
        $this->assertSame('/img/icon-192.png', $body['icons'][0]['src']);
        $this->assertSame('/img/icon-512.png', $body['icons'][1]['src']);
    }

    public function testSubfolderDeployPrefixesAllUrls(): void
    {
        // Simulate a /qsl subfolder deploy by injecting the webroot
        // attribute the way RoutingMiddleware would in that scenario.
        // The integration test harness lets us configure request
        // attributes via configRequest().
        $this->configRequest([
            'environment' => [
                'SCRIPT_NAME' => '/qsl/webroot/index.php',
                'PHP_SELF' => '/qsl/webroot/index.php',
            ],
        ]);
        // CakePHP's request-building from environment derives the webroot
        // from SCRIPT_NAME — but the integration test bootstrap may not
        // honour that. Easier: explicitly construct the request with the
        // attribute we need.
        $this->session([]);
        \Cake\Core\Configure::write('App.base', '/qsl');
        try {
            $this->get('/manifest.webmanifest');
            $body = json_decode((string)$this->_response->getBody(), true);
            $this->assertSame('/qsl/qsos/quick', $body['start_url']);
            $this->assertSame('/qsl/', $body['scope']);
            $this->assertSame('/qsl/img/icon-192.png', $body['icons'][0]['src']);
            $this->assertSame('/qsl/img/icon-512.png', $body['icons'][1]['src']);
        } finally {
            \Cake\Core\Configure::write('App.base', false);
        }
    }
}
