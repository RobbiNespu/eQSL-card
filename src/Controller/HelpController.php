<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\HelpCatalog;
use Cake\Http\Exception\NotFoundException;

/**
 * Public docs portal.
 *
 *   GET /help                      → index landing page.
 *   GET /help/{category}/{slug}    → individual article (validated against
 *                                    HelpCatalog::TREE; 404 otherwise).
 *
 * No auth — both routes serve logged-out visitors so search engines
 * can index and operators can share article links externally.
 */
final class HelpController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        // Authentication component is loaded per-controller (see
        // PublicController for the same pattern); the AppController base
        // doesn't auto-attach it. Once loaded, mark both actions as
        // public so the auth middleware skips them.
        $this->loadComponent('Authentication.Authentication');
        $this->Authentication->allowUnauthenticated(['index', 'view']);
    }

    public function index(): void
    {
        $this->set('tree', HelpCatalog::TREE);
        $this->set('title', 'Help');
    }

    public function view(string $category, string $slug): void
    {
        if (!HelpCatalog::exists($category, $slug)) {
            throw new NotFoundException('Documentation page not found.');
        }
        $this->set(compact('category', 'slug'));
        $this->set('title', HelpCatalog::pageLabel($category, $slug));
        $this->render("/Help/{$category}/{$slug}");
    }
}
