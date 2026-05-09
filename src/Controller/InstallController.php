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

    public function migrate(): void
    {
    }

    public function admin(): void
    {
    }

    public function complete(): void
    {
    }
}
