<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

/**
 * QsosController::import integration tests (M2-T6).
 *
 * Covers:
 *  - GET /qsos/import renders the upload form.
 *  - POST /qsos/import with a CSV file shows the summary stage with a
 *    confirm token.
 *  - POST /qsos/import with a confirm token batch-inserts the records.
 *  - A second import of the same row is detected as a duplicate at the
 *    pre-flight stage AND is skipped at insert time (qsos_dedup_idx).
 *
 * Note on session bridging: CakePHP's IntegrationTestTrait builds a fresh
 * Session per request from `$this->_session`, so any session writes the
 * controller does during request N do not survive into request N+1. The
 * `bridgeSession()` helper reads `$this->_requestSession` (the post-request
 * session set inside `_sendRequest`) and re-injects everything into
 * `$this->_session` before the next request.
 *
 * Note on file uploads: `configRequest()` merges with previous request state,
 * so chaining two uploads in the same test would corrupt the `files` array.
 * `replaceRequest()` resets it.
 */
final class QsosControllerImportTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos'];

    private function seedUserAndLogin(): int
    {
        $users = $this->getTableLocator()->get('Users');
        $user = $users->saveOrFail($users->newEntity([
            'name' => 'OP',
            'email' => 'op@x.com',
            'role' => 'user',
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $user->id, 'email' => 'op@x.com']]);

        return $user->id;
    }

    private function uploadCsv(string $csv): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csvimp_');
        file_put_contents($tmp, $csv);

        return new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'log.csv', 'text/csv');
    }

    /**
     * Bring the session written by the previous request forward into the
     * `_session` bag the trait uses to seed the next request.
     *
     * Reads `$_SESSION` directly because the request-bound Session object
     * gets `close()`d after dispatch (sets `_started=false`), and calling
     * `->read()` on it re-runs `start()` which in CLI mode wipes
     * `$_SESSION`. The global is the actual store in CLI; reading it
     * directly is the only side-effect-free way to peek.
     */
    private function bridgeSession(): void
    {
        if (isset($_SESSION) && is_array($_SESSION) && $_SESSION !== []) {
            $this->_session = $_SESSION + $this->_session;
        }
    }

    public function testGetImportShowsUploadForm(): void
    {
        $this->seedUserAndLogin();
        $this->get('/qsos/import');
        $this->assertResponseOk();
        $this->assertResponseContains('Upload an ADIF');
    }

    public function testPostFileShowsSummary(): void
    {
        $this->seedUserAndLogin();
        $upload = $this->uploadCsv("call,qso_datetime_utc,band,mode\nW1AW,2026-05-09 14:32:00,20m,SSB\nK2DST,2026-05-08 14:32:00,40m,CW\n");
        $this->configRequest(['files' => ['adif_csv' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/import', []);
        $this->assertResponseOk();
        $this->assertResponseContains('Summary');
        // The "2" is wrapped in <strong>, so match around the markup.
        $this->assertResponseContains('>2</strong> new QSOs');
    }

    public function testConfirmInsertsRecords(): void
    {
        $userId = $this->seedUserAndLogin();
        $upload = $this->uploadCsv("call,qso_datetime_utc,band,mode\nW1AW,2026-05-09 14:32:00,20m,SSB\nK2DST,2026-05-08 14:32:00,40m,CW\n");
        $this->configRequest(['files' => ['adif_csv' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/import', []);
        // Pull token out of the response body
        preg_match('/name="confirm_token" value="([0-9a-f]+)"/', (string)$this->_response->getBody(), $m);
        $this->assertNotEmpty($m[1] ?? '');
        $token = $m[1];

        // The session-stash needs to survive into the next request.
        $this->bridgeSession();
        $this->replaceRequest([]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/import', ['confirm_token' => $token]);
        $this->assertRedirect('/qsos');

        $count = $this->getTableLocator()->get('Qsos')->find()->where(['user_id' => $userId])->count();
        $this->assertSame(2, $count);
    }

    public function testDuplicatesSkippedOnSecondImport(): void
    {
        $userId = $this->seedUserAndLogin();

        // First import: parse stage.
        $upload = $this->uploadCsv("call,qso_datetime_utc,band,mode\nW1AW,2026-05-09 14:32:00,20m,SSB\n");
        $this->configRequest(['files' => ['adif_csv' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/import', []);
        preg_match('/name="confirm_token" value="([0-9a-f]+)"/', (string)$this->_response->getBody(), $m);
        $token1 = $m[1] ?? '';
        $this->assertNotEmpty($token1);

        // First import: confirm stage. Bridge the session forward and clear
        // the previous request's `files` state.
        $this->bridgeSession();
        $this->replaceRequest([]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/import', ['confirm_token' => $token1]);

        // Second import — same row, expecting the pre-flight scan to flag
        // the duplicate and the (skipped, not inserted) summary to surface.
        $upload2 = $this->uploadCsv("call,qso_datetime_utc,band,mode\nW1AW,2026-05-09 14:32:00,20m,SSB\n");
        $this->bridgeSession();
        $this->replaceRequest(['files' => ['adif_csv' => $upload2]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/import', []);
        $this->assertResponseContains('1 already in your logbook');

        $count = $this->getTableLocator()->get('Qsos')->find()->where(['user_id' => $userId])->count();
        $this->assertSame(1, $count);
    }
}
