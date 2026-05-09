<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Cards Controller (M2-T7).
 *
 * Logged-in user's library of generated eQSL cards. T7 ships the paginated
 * thumbnail grid (`index`); T8 will add the per-card detail view, T9 will
 * add soft-delete, and T13–T16 will layer the share-link surface on top.
 *
 * Authorization model mirrors `QsosController`: every query is scoped by
 * `user_id = current identity`, so a user can only ever see (or modify)
 * their own cards. The `Card` entity also enforces the user-vs-guest split
 * via the `ownerExclusive` rule in `CardsTable`, so a card with a `user_id`
 * cannot also masquerade as a guest card.
 */
class CardsController extends AppController
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
     * Paginated thumbnail grid of the current user's cards.
     *
     * Sort: newest first. We `contain('Templates')` so the view can later
     * surface the template name without an extra query per row (T8 will use
     * this; T7 keeps the grid minimal).
     *
     * @return void
     */
    public function index(): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $query = $this->fetchTable('Cards')->find()
            ->where(['Cards.user_id' => $userId])
            ->contain(['Templates'])
            ->orderBy(['Cards.created_at' => 'DESC']);

        $cards = $this->paginate($query, ['limit' => 20]);
        $this->set(['cards' => $cards, 'title' => 'My eQSL cards']);
    }
}
