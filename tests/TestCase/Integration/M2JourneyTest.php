<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

/**
 * End-to-end journey test exercising the full M2 user flow:
 *  register → login → import ADIF → render card → share w/ password →
 *  wrong-password attempt → correct-password unlock → revoke → confirm 410.
 *
 * Covers cross-cutting interactions that are individually tested in unit + per-controller
 * tests but that need a single integration sanity check before tagging v0.2.0.
 */
final class M2JourneyTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos', 'app.Templates', 'app.Uploads', 'app.Cards', 'app.PasswordResets'];

    /** Bridge $_SESSION → IntegrationTestTrait's _session between requests (T6 helper). */
    private function bridgeSession(): void
    {
        if (isset($_SESSION) && is_array($_SESSION)) {
            $current = (array)($this->_session ?? []);
            $this->session(array_replace_recursive($current, $_SESSION));
        }
    }

    private static function adifTag(string $name, string $value): string
    {
        return sprintf('<%s:%d>%s', strtoupper($name), strlen($value), $value);
    }

    public function testFullJourney(): void
    {
        // ---------------------------------------------------------------
        // Step 1: Register a new user
        // ---------------------------------------------------------------
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/register', [
            'name' => 'Alice', 'callsign' => 'AA1AA',
            'email' => 'alice@example.com',
            'password' => 'CorrectHorseBatteryStaple1',
            'password_confirm' => 'CorrectHorseBatteryStaple1',
        ]);
        $this->assertRedirect('/login');
        $users = $this->getTableLocator()->get('Users');
        $alice = $users->find()->where(['email' => 'alice@example.com'])->firstOrFail();
        $this->assertSame('user', $alice->role);

        // ---------------------------------------------------------------
        // Step 2: Log in (bypassing actual auth flow — just session-set the identity)
        // ---------------------------------------------------------------
        $this->session(['Auth' => ['id' => $alice->id, 'email' => 'alice@example.com']]);

        // ---------------------------------------------------------------
        // Step 3: Import an ADIF file with 2 QSOs
        // ---------------------------------------------------------------
        $adif = "Test\n<ADIF_VER:5>3.1.4\n<EOH>\n"
            . self::adifTag('CALL', 'W1AW') . ' '
            . self::adifTag('QSO_DATE', '20260509') . ' '
            . self::adifTag('TIME_ON', '143200') . ' '
            . self::adifTag('BAND', '20m') . ' '
            . self::adifTag('MODE', 'SSB') . ' <EOR>'
            . self::adifTag('CALL', 'K2DST') . ' '
            . self::adifTag('QSO_DATE', '20260508') . ' '
            . self::adifTag('TIME_ON', '143200') . ' '
            . self::adifTag('BAND', '40m') . ' '
            . self::adifTag('MODE', 'CW') . ' <EOR>';

        $tmp = tempnam(sys_get_temp_dir(), 'j_');
        file_put_contents($tmp, $adif);
        $upload = new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'log.adi', 'text/plain');
        $this->configRequest(['files' => ['adif_csv' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/import', []);
        $this->assertResponseOk();
        $this->assertResponseContains('>2</strong> new QSOs');

        // Pull token from rendered HTML
        preg_match('/name="confirm_token" value="([0-9a-f]+)"/', (string)$this->_response->getBody(), $m);
        $this->assertNotEmpty($m[1] ?? '');
        $this->bridgeSession();

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/import', ['confirm_token' => $m[1]]);
        $this->assertRedirect('/qsos');

        $qsoCount = $this->getTableLocator()->get('Qsos')->find()->where(['user_id' => $alice->id])->count();
        $this->assertSame(2, $qsoCount);

        // ---------------------------------------------------------------
        // Step 4: Render a card from the first QSO
        // ---------------------------------------------------------------
        $qsoId = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $alice->id, 'call_worked' => 'W1AW'])
            ->firstOrFail()->id;

        // Seed system template
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $img = imagecreatetruecolor(800, 600);
        $bgTmp = tempnam(sys_get_temp_dir(), 'bg_');
        imagejpeg($img, $bgTmp);
        imagedestroy($img);
        $bgUpload = new UploadedFile($bgTmp, filesize($bgTmp), UPLOAD_ERR_OK, 'bg.jpg', 'image/jpeg');
        $this->configRequest(['files' => ['background_upload' => $bgUpload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/' . $qsoId . '/render', ['template_id' => $tpl->id, 'upload_id' => 0]);
        $this->assertRedirectContains('/cards/');

        $card = $this->getTableLocator()->get('Cards')->find()->where(['user_id' => $alice->id])->firstOrFail();

        // ---------------------------------------------------------------
        // Step 5: Share with a password
        // ---------------------------------------------------------------
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $card->id . '/share', ['password' => 'sharepass']);
        $this->assertRedirect('/cards/' . $card->id);

        $card = $this->getTableLocator()->get('Cards')->get($card->id);
        $this->assertSame(43, strlen((string)$card->share_slug));
        $this->assertNotNull($card->share_password_hash);

        // ---------------------------------------------------------------
        // Step 6: Anonymous user hits the share page → redirected to unlock
        // ---------------------------------------------------------------
        // Reset session, cookies, and accumulated request state — anon visitor.
        $this->session([]);
        $this->_cookie = [];
        $this->replaceRequest([]);
        $this->get('/qsl/' . $card->share_slug);
        $this->assertRedirectContains('/qsl/' . $card->share_slug . '/unlock');

        // Wrong password
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsl/' . $card->share_slug . '/unlock', ['password' => 'wrong']);
        $this->assertResponseOk();
        $this->assertResponseContains('Incorrect password');

        // Correct password → redirect to share
        $this->bridgeSession();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsl/' . $card->share_slug . '/unlock', ['password' => 'sharepass']);
        $this->assertRedirect('/qsl/' . $card->share_slug);

        // Follow the redirect (via session) — public share page should now render
        $this->bridgeSession();
        $this->get('/qsl/' . $card->share_slug);
        $this->assertResponseOk();
        $this->assertResponseContains('AA1AA');

        // ---------------------------------------------------------------
        // Step 7: Owner revokes the share
        // ---------------------------------------------------------------
        $this->session(['Auth' => ['id' => $alice->id, 'email' => 'alice@example.com']]);
        $this->_cookie = [];
        $this->replaceRequest([]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/cards/' . $card->id . '/revoke');
        $this->assertRedirect('/cards/' . $card->id);

        // ---------------------------------------------------------------
        // Step 8: Anonymous re-visit returns 410 Gone
        // ---------------------------------------------------------------
        $this->session([]);
        $this->_cookie = [];
        $this->replaceRequest([]);
        $this->get('/qsl/' . $card->share_slug);
        $this->assertResponseCode(410);
        $this->assertResponseContains('Share revoked');
    }
}
