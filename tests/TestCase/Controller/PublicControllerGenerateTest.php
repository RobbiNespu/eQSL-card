<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

final class PublicControllerGenerateTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Templates', 'app.GuestVisits', 'app.Uploads', 'app.Cards'];

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
}
