<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OperationLog;

/**
 * Guided installation wizard (only reachable while `config/installed.lock` is absent).
 *
 * Steps:
 *  1. index       — welcome screen.
 *  2. systemCheck — PHP extension / write-permission report.
 *  3. database    — DB + SMTP connection form; writes `config/app_local.php`.
 *  4. migrate     — runs Phinx migrations.
 *  5. admin       — creates the first admin user, seeds the default template,
 *                   writes the lock file, and renders the completion page inline.
 *  6. complete    — direct-link sanity target (ordinarily reached via admin inline render).
 *
 * InstallationCheckMiddleware 404s every `/install/*` route once the lock file
 * exists, so these actions are inert on a running install.
 */
class InstallController extends AppController
{
    /**
     * Welcome screen — step 1 of the install wizard.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('title', 'Welcome');
    }

    /**
     * System-requirements check — step 2.
     *
     * Runs `SystemCheck::run()` and sets `$report` (array of checks) and
     * `$allPass` (bool, true when every check returned `ok => true`).
     *
     * @return void
     */
    public function systemCheck(): void
    {
        $report = (new \App\Service\SystemCheck())->run();
        $allPass = !in_array(false, array_column($report, 'ok'), true);
        $this->set(compact('report', 'allPass'));
    }

    /**
     * Database + SMTP configuration — step 3.
     *
     * GET: render the connection form with defaults (host=localhost, port=3306).
     * POST: test the connection, then write `config/app_local.php` via
     * `AppLocalWriter`; redirect to migrate on success or re-render with a
     * flash error on failure.
     *
     * @return \Cake\Http\Response|null Redirect on success, null to re-render.
     */
    public function database()
    {
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            try {
                $this->testConnection($data);
                $writer = new \App\Service\AppLocalWriter(CONFIG . 'app_local.php.example');
                $writer->write(CONFIG . 'app_local.php', [
                    'DB_HOST'        => (string)$data['host'],
                    'DB_PORT'        => (string)$data['port'],
                    'DB_USER'        => (string)$data['username'],
                    'DB_PASS'        => (string)$data['password'],
                    'DB_NAME'        => (string)$data['database'],
                    'SECURITY_SALT'  => bin2hex(random_bytes(32)),
                    'SMTP_HOST'      => (string)($data['smtp_host'] ?? ''),
                    'SMTP_USER'      => (string)($data['smtp_user'] ?? ''),
                    'SMTP_PASS'      => (string)($data['smtp_pass'] ?? ''),
                    'SMTP_FROM'      => (string)($data['smtp_from'] ?? ''),
                ]);
                $this->Flash->success('Database connection saved.');
                return $this->redirect('/install/migrate');
            } catch (\Throwable $e) {
                OperationLog::failure('install.database_config', $e, []);
                $this->Flash->error($e->getMessage());
            }
        }
        $this->set('data', $this->request->getData() ?: ['host' => 'localhost', 'port' => '3306']);
    }

    /**
     * Attempt a PDO connection with the supplied DB credentials.
     *
     * @param array<string, mixed> $data Form data with keys host, port, database, username, password.
     * @return void
     * @throws \PDOException On connection failure.
     */
    private function testConnection(array $data): void
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $data['host'], $data['port'], $data['database']);
        new \PDO($dsn, $data['username'], $data['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * Run database migrations — step 4.
     *
     * POST triggers `Installer::runMigrations()` and redirects to the admin
     * setup step on success. Failures flash the exception message and re-render.
     *
     * @return \Cake\Http\Response|null Redirect on success, null to re-render.
     */
    public function migrate()
    {
        if ($this->request->is('post')) {
            try {
                (new \App\Service\Installer())->runMigrations();
                $this->Flash->success('Schema applied.');
                return $this->redirect('/install/admin');
            } catch (\Throwable $e) {
                OperationLog::failure('install.migrate', $e, []);
                $this->Flash->error('Migration failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create the first admin user — step 5.
     *
     * POST creates the admin, seeds the default template, writes the lock
     * file, audits the install completion, then renders `complete` inline
     * (redirect would hit the lock and 404). Failures flash the error and
     * re-render.
     *
     * @return \Cake\Http\Response|null Null after inline render, or null to re-render on failure.
     */
    public function admin()
    {
        if ($this->request->is('post')) {
            try {
                $installer = new \App\Service\Installer();
                $entity = $installer->createAdmin($this->request->getData());
                $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');
                $installer->lock(CONFIG . 'installed.lock');

                // M4-T3: Audit the install completion. The freshly-created
                // admin is the actor — this is the first row in the install's
                // audit_logs table and anchors who provisioned the system.
                // Audit failures must never break the install handoff.
                try {
                    (new \App\Service\AuditLogger())->log(
                        event: 'installer.completed',
                        actorUserId: isset($entity->id) ? (int)$entity->id : null,
                        metadata: ['admin_email' => (string)($entity->email ?? '')],
                    );
                } catch (\Throwable $e) {
                    error_log('audit: ' . $e->getMessage());
                }

                // Render the completion page directly instead of redirecting.
                // We can't redirect to /install/complete here because the lock
                // file we just wrote causes InstallationCheckMiddleware to 404
                // any /install/* path on the next request — so the redirect
                // target would be unreachable. Rendering inline keeps the lock
                // semantics correct while still giving the user the success page.
                $this->set('loginUrl', '/login');
                $this->render('complete');
                return null;
            } catch (\Throwable $e) {
                OperationLog::failure('install.admin', $e, []);
                $this->Flash->error($e->getMessage());
            }
        }
        return null;
    }

    /**
     * Installation complete page.
     *
     * Reachable only on a fresh, un-installed instance. The primary entry
     * point is now via `admin()` rendering this view inline; this action
     * exists for direct-link sanity.
     *
     * @return void
     */
    public function complete(): void
    {
        // Reachable only on a fresh, un-installed instance (i.e. before the
        // admin POST creates the lock). Kept for direct-link sanity but the
        // primary entry is now via admin() rendering this view inline.
        $this->set('loginUrl', '/login');
    }
}
