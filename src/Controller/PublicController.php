<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Public Controller
 *
 * Hosts the guest-facing eQSL generator. Both `index` (form) and
 * `generate` (POST handler, T20) are reachable without authentication.
 */
class PublicController extends AppController
{
    /**
     * Initialize hook — allow unauthenticated access to the public form.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
        $this->Authentication->allowUnauthenticated(['index', 'generate']);
    }

    /**
     * Render the QSL generator form.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('title', 'Generate an eQSL');
    }

    /**
     * Handle the POST submission and stream a rendered card.
     *
     * Implementation arrives in Task 20.
     *
     * @return void
     */
    public function generate()
    {
        // Implemented in Task 20
    }
}
