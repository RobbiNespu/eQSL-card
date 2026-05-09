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

    public function database(): void
    {
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
