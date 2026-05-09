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
        // `find('active')` filters rows where `deleted_at` is non-null so
        // soft-deleted cards (M2-T9) drop out of the user's library listing.
        $query = $this->fetchTable('Cards')->find('active')
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
        // Soft-deleted rows must 404 from the detail surface too — using the
        // `active` finder keeps that policy in one place (CardsTable).
        $card = $this->fetchTable('Cards')->find('active')
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

    /**
     * Soft-delete a card (M2-T9).
     *
     * POST-only. Sets `cards.deleted_at` on the row instead of removing it,
     * so the artefact (PNG/PDF on disk + the audit row) survives until the
     * M4 admin sweep tools garbage-collect storage. We deliberately keep the
     * scope tight to `user_id = current identity`, which means a guessed-id
     * attack against another user's card surfaces as 404 (firstOrFail) rather
     * than 403 — this matches the existing pattern in `view()` and avoids
     * leaking row existence.
     *
     * `deleted_at` is intentionally absent from `Card::_accessible`, so we
     * assign it directly on the entity and `saveOrFail` to bypass the mass-
     * assignment guard while still running validation/rules. Storage cleanup
     * is NOT triggered here; that's deferred to M4 (admin sweep tools).
     *
     * @param int $id Card id (route-bound).
     * @return \Cake\Http\Response
     */
    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');

        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $cards = $this->fetchTable('Cards');
        $card = $cards->find()
            ->where(['Cards.id' => $id, 'Cards.user_id' => $userId])
            ->firstOrFail();

        // Soft-delete: set deleted_at on the card row.
        // Storage cleanup (the actual PNG/PDF files) is deferred to M4 admin
        // sweep tools — this endpoint is the user-facing "remove from my
        // library" surface, not a hard purge.
        $card->deleted_at = \Cake\I18n\DateTime::now();
        $cards->saveOrFail($card);

        $this->Flash->success('Card deleted.');

        return $this->redirect('/cards');
    }
}
