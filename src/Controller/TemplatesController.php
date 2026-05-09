<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Templates Controller (M3-T2 scaffold).
 *
 * Surfaces the template designer (`add` / `edit`) and a placeholder `view`
 * action. Save / clone / publish land in subsequent M3 tasks (T4 / T8 / T9);
 * the gallery `index` is M3-T7. We scaffold those routes + actions now so the
 * designer view shell has somewhere stable to POST to once T4 wires save.
 *
 * Authorization model mirrors `CardsController`: every owned-template query
 * is scoped by `user_id = current identity`. `view()` additionally allows
 * `is_system` rows and `is_public AND is_approved` rows so users can preview
 * curated/shared templates before cloning (clone-and-edit lands in M3-T8).
 *
 * `add()` deliberately calls `render('edit')` so the designer view shell is
 * a single template — `mode` (`'new'` vs `'edit'`) tells the Alpine factory
 * whether to POST to `/templates/new` or `/templates/{id}/edit` (M3-T4).
 */
class TemplatesController extends AppController
{
    /**
     * Initialize hook.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    /**
     * Stub for M3-T7 (gallery: My / Public / System tabs).
     *
     * Today this just renders a placeholder; M3-T7 wires the three-tab grid
     * with thumbnail rendering. Keeping the action present (and the route
     * already connected) means the gallery task is purely view + query work.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('title', 'Templates');
    }

    /**
     * New-template designer (M3-T2 scaffold).
     *
     * Builds an in-memory `Template` entity with sane defaults (1500x1000
     * canvas, empty fields array) and renders the shared designer view.
     * No row is persisted until the user clicks Save (M3-T4 wires that).
     *
     * @return null
     */
    public function add()
    {
        $templates = $this->fetchTable('Templates');
        $entity = $templates->newEmptyEntity();
        $entity->canvas_width = 1500;
        $entity->canvas_height = 1000;
        $entity->layout_json = json_encode(['fields' => []]);

        $this->set([
            'template' => $entity,
            'mode' => 'new',
            'title' => 'New template',
        ]);
        // Render the shared designer view; `mode = 'new'` tells the Alpine
        // factory to POST to `/templates/new` rather than `/templates/{id}/edit`.
        $this->render('edit');

        return null;
    }

    /**
     * Edit an existing user-owned template (M3-T2 scaffold).
     *
     * Scoped strictly to the current user's own rows: system templates and
     * public-approved templates are read-only via this action and must be
     * cloned first (M3-T8). Cross-user attempts surface as 404 via
     * `firstOrFail` so we don't leak row existence.
     *
     * @param int $id Template id (route-bound).
     * @return null
     */
    public function edit(int $id)
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $templates = $this->fetchTable('Templates');
        $template = $templates->find()
            ->where(['Templates.id' => $id])
            ->where(['OR' => [
                ['Templates.user_id' => $userId],
                // (System and public-approved are read-only via this action;
                // clone-and-edit lands in M3-T8.)
            ]])
            ->firstOrFail();

        $this->set([
            'template' => $template,
            'mode' => 'edit',
            'title' => 'Edit template — ' . $template->name,
        ]);

        return null;
    }

    /**
     * Read-only template preview (M3-T2 placeholder).
     *
     * Allows the current user to view their own templates plus system rows
     * (`is_system`) and curated public rows (`is_public AND is_approved`).
     * This is the surface T7's gallery cards link into for "preview before
     * clone". A richer preview (with rendered thumbnail) ships alongside T6.
     *
     * @param int $id Template id (route-bound).
     * @return void
     */
    public function view(int $id): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $templates = $this->fetchTable('Templates');
        $template = $templates->find()
            ->where(['Templates.id' => $id])
            ->where(['OR' => [
                ['Templates.user_id' => $userId],
                ['Templates.is_system' => true],
                ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
            ]])
            ->firstOrFail();

        $this->set(['template' => $template, 'title' => $template->name]);
    }
}
