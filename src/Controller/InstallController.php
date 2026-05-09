<?php
declare(strict_types=1);

namespace App\Controller;

class InstallController extends AppController
{
    public function index(): void
    {
        $this->set('title', 'Welcome');
    }

    public function systemCheck(): void
    {
        $report = (new \App\Service\SystemCheck())->run();
        $allPass = !in_array(false, array_column($report, 'ok'), true);
        $this->set(compact('report', 'allPass'));
    }

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
                $this->Flash->error($e->getMessage());
            }
        }
        $this->set('data', $this->request->getData() ?: ['host' => 'localhost', 'port' => '3306']);
    }

    private function testConnection(array $data): void
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $data['host'], $data['port'], $data['database']);
        new \PDO($dsn, $data['username'], $data['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    public function migrate()
    {
        if ($this->request->is('post')) {
            try {
                (new \App\Service\Installer())->runMigrations();
                $this->Flash->success('Schema applied.');
                return $this->redirect('/install/admin');
            } catch (\Throwable $e) {
                $this->Flash->error('Migration failed: ' . $e->getMessage());
            }
        }
    }

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

                return $this->redirect('/install/complete');
            } catch (\Throwable $e) {
                $this->Flash->error($e->getMessage());
            }
        }
    }

    public function complete(): void
    {
        $this->set('loginUrl', '/login');
    }
}
