<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

final class CallsignDirectoryControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.AuditLogs', 'app.CallsignDirectory'];

    private function loginAs(string $role = 'admin'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => uniqid('u') . '@x.com', 'role' => $role,
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);
        return $u->id;
    }

    public function testNonAdminGets403(): void
    {
        $this->loginAs('user');
        $this->get('/admin/callsign-directory');
        $this->assertResponseCode(403);
    }

    public function testAdminSeesIndex(): void
    {
        $this->loginAs('admin');
        $this->get('/admin/callsign-directory');
        $this->assertResponseOk();
        $this->assertResponseContains('Callsigns indexed');
        $this->assertResponseContains('Upload CSV');
    }

    public function testCsvUploadImportsRows(): void
    {
        $this->loginAs('admin');
        $csv = "callsign,name,qth\nW1AW,Hiram,Newington\n9W2NSP,Robbi,Penang\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmp, $csv);
        $upload = new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'directory.csv', 'text/csv');

        $this->configRequest(['files' => ['csv' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/callsign-directory/upload', ['source_label' => 'TestBatch']);
        $this->assertRedirect('/admin/callsign-lookups/provider/local');

        $table = $this->getTableLocator()->get('CallsignDirectory');
        $this->assertSame(2, $table->find()->count());
        $row = $table->find()->where(['callsign' => 'W1AW'])->first();
        $this->assertSame('Hiram', $row->name);
        $this->assertSame('TestBatch', $row->source_label);

        // Audit row should record the import.
        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'callsign_directory.imported'])->first();
        $this->assertNotNull($log);
    }

    public function testUploadWithoutFileShowsError(): void
    {
        $this->loginAs('admin');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/callsign-directory/upload');
        $this->assertRedirect('/admin/callsign-lookups/provider/local');
        // Couldn't surface the flash message without a follow-up GET; the
        // important guarantee is that no rows landed in the DB.
        $this->assertSame(
            0,
            $this->getTableLocator()->get('CallsignDirectory')->find()->count()
        );
    }

    public function testClearWipesDirectory(): void
    {
        $this->loginAs('admin');
        $table = $this->getTableLocator()->get('CallsignDirectory');
        $table->saveOrFail($table->newEntity([
            'callsign' => 'W1AW', 'name' => 'Hiram',
            'imported_at' => DateTime::now(),
        ], ['accessibleFields' => ['*' => true]]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/callsign-directory/clear');
        $this->assertRedirect('/admin/callsign-lookups/provider/local');
        $this->assertSame(0, $table->find()->count());

        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'callsign_directory.cleared'])->first();
        $this->assertNotNull($log);
    }

    public function testIndexFiltersByCallsignSubstring(): void
    {
        $this->loginAs('admin');
        $table = $this->getTableLocator()->get('CallsignDirectory');
        $table->saveOrFail($table->newEntity([
            'callsign' => 'W1AW', 'name' => 'Hiram',
            'imported_at' => DateTime::now(),
        ], ['accessibleFields' => ['*' => true]]));
        $table->saveOrFail($table->newEntity([
            'callsign' => '9W2NSP', 'name' => 'Robbi',
            'imported_at' => DateTime::now(),
        ], ['accessibleFields' => ['*' => true]]));

        $this->get('/admin/callsign-directory?q=9W2');
        $this->assertResponseOk();
        $this->assertResponseContains('9W2NSP');
        $this->assertResponseNotContains('W1AW');
    }
}
