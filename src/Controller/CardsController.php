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

    /**
     * Single-card detail view (M2-T8).
     *
     * Scoped by `user_id` so a user cannot peek at another operator's card via
     * a guessed id — the query simply 404s. We `contain` Templates and Uploads
     * because the view surfaces template name and the original background-image
     * filename. `qso_data_json` is decoded once here so the template stays free
     * of decoding logic; if the column is malformed JSON we fall back to an
     * empty array rather than 500ing.
     *
     * Share state is derived locally: an active share has a `share_slug` and
     * no `share_revoked_at`. Once revoked, the slug stays in the row (so the
     * public `/qsl/{slug}` route can return 410 Gone) but we no longer surface
     * a "Public link" — instead we show a historic revoke notice.
     *
     * @param int $id Card id (route-bound).
     * @return void
     */
    public function view(int $id): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $card = $this->fetchTable('Cards')->find()
            ->where(['Cards.id' => $id, 'Cards.user_id' => $userId])
            ->contain(['Templates', 'Uploads'])
            ->firstOrFail();

        $qsoData = json_decode((string)$card->qso_data_json, true) ?: [];

        $shareUrl = null;
        if ($card->share_slug && !$card->share_revoked_at) {
            $shareUrl = '/qsl/' . $card->share_slug;
        }

        $this->set([
            'card' => $card,
            'qso' => $qsoData,
            'shareUrl' => $shareUrl,
            'title' => 'eQSL — ' . ($qsoData['callsign'] ?? '#' . $card->id),
        ]);
    }
}
