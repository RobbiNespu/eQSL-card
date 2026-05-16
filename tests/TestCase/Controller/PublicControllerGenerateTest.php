<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

final class PublicControllerGenerateTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Templates', 'app.GuestVisits', 'app.CardBackgrounds', 'app.Cards'];

    public function testGuestCanGenerateAndCardIsPersisted(): void
    {
        // Seed system template
        $templates = $this->getTableLocator()->get('Templates');
        $templates->saveOrFail($templates->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]), 'is_system' => true,
            'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        // Build a small JPEG fixture
        $img = imagecreatetruecolor(800, 600);
        $tmp = tempnam(sys_get_temp_dir(), 'fix_');
        imagejpeg($img, $tmp);
        imagedestroy($img);

        $upload = new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'bg.jpg', 'image/jpeg');

        $this->configRequest(['files' => ['background_upload' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/generate', [
            'callsign' => 'W1AW', 'operator_callsign' => 'AA1AA',
            'qso_datetime_utc' => '2026-05-09T14:32',
            'frequency_mhz' => '14.205', 'band' => '20m', 'mode' => 'SSB',
            'rst_sent' => '59', 'rst_received' => '59', 'operator_name' => 'Hiram',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Your eQSL is ready');
        $this->assertSame(1, $this->getTableLocator()->get('Cards')->find()->count());
    }

    public function testGuestCanPickPublicApprovedTemplate(): void
    {
        // Seed two templates: system + public-approved (different layouts)
        $templates = $this->getTableLocator()->get('Templates');
        $sys = $templates->saveOrFail($templates->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'Designer', 'email' => 'd@x.com', 'role' => 'user',
            'callsign' => 'DD1DD', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));

        $pub = $templates->saveOrFail($templates->newEntity([
            'user_id' => $u->id, 'name' => 'public approved',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_public' => true, 'is_approved' => true]]));

        // GET shows both
        $this->get('/');
        $this->assertResponseOk();
        $this->assertResponseContains('public approved');

        // POST with the public template's id should render with that template
        $img = imagecreatetruecolor(800, 600);
        $tmp = tempnam(sys_get_temp_dir(), 'fix_');
        imagejpeg($img, $tmp);
        imagedestroy($img);
        $upload = new \Laminas\Diactoros\UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'bg.jpg', 'image/jpeg');

        $this->configRequest(['files' => ['background_upload' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/generate', [
            'template_id' => $pub->id,
            'callsign' => 'W1AW', 'operator_callsign' => 'AA1AA',
            'qso_datetime_utc' => '2026-05-09T14:32',
            'frequency_mhz' => '14.205', 'band' => '20m', 'mode' => 'SSB',
            'rst_sent' => '59', 'rst_received' => '59', 'operator_name' => 'H',
        ]);
        $this->assertResponseOk();

        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->find()->orderBy(['id' => 'DESC'])->first();
        $this->assertSame($pub->id, $card->template_id, 'Guest can use a public-approved template by id');
    }

    public function testGuestCannotUsePrivateTemplate(): void
    {
        $templates = $this->getTableLocator()->get('Templates');
        $sys = $templates->saveOrFail($templates->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'Owner', 'email' => 'o@x.com', 'role' => 'user',
            'callsign' => 'OO1OO', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));

        // Private template (is_public=false)
        $priv = $templates->saveOrFail($templates->newEntity([
            'user_id' => $u->id, 'name' => 'private',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]));

        $img = imagecreatetruecolor(800, 600);
        $tmp = tempnam(sys_get_temp_dir(), 'fix_');
        imagejpeg($img, $tmp);
        imagedestroy($img);
        $upload = new \Laminas\Diactoros\UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'bg.jpg', 'image/jpeg');

        $this->configRequest(['files' => ['background_upload' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/generate', [
            'template_id' => $priv->id, // attacker-supplied
            'callsign' => 'W1AW', 'operator_callsign' => 'AA1AA',
            'qso_datetime_utc' => '2026-05-09T14:32',
            'frequency_mhz' => '14.205', 'band' => '20m', 'mode' => 'SSB',
            'rst_sent' => '59', 'rst_received' => '59',
        ]);
        $this->assertResponseOk();

        $cards = $this->getTableLocator()->get('Cards');
        $card = $cards->find()->orderBy(['id' => 'DESC'])->first();
        $this->assertSame($sys->id, $card->template_id, 'Private template id should silently fall back to system');
    }
}
