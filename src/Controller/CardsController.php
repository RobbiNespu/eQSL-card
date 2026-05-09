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

        // M4-T3: Audit the soft-delete. Failures must NOT abort the user's
        // action — log to error_log and move on so audit infra issues never
        // break a user-facing flow.
        try {
            (new \App\Service\AuditLogger())->log(
                event: 'card.deleted',
                actorUserId: $userId,
                target: ['type' => 'Cards', 'id' => $card->id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Card deleted.');

        return $this->redirect('/cards');
    }

    /**
     * Mint a public share slug for a private card (M2-T13).
     *
     * POST-only. Generates a 43-character URL-safe base64 slug from
     * `random_bytes(32)` (256 bits of entropy → enumeration is computationally
     * infeasible) and stores it on the row. If the operator supplies a non-empty
     * `password` field we hash it with Argon2id via CakePHP's `DefaultPasswordHasher`
     * and stash the hash in `share_password_hash`; the plaintext is never
     * persisted. We also clear `share_revoked_at` so an operator who previously
     * revoked a share can re-share without a separate "un-revoke" surface — the
     * row gets a brand-new slug, which is the safer default (the old `/qsl/{slug}`
     * URL stays 410 Gone).
     *
     * If a card is already actively shared (slug present, not revoked) we no-op
     * with a flash and bounce back to the detail page rather than minting a new
     * slug — re-running `share` should be idempotent for the user, and silently
     * rotating the slug would invalidate any link they already handed out.
     *
     * Authorization mirrors `view`/`delete`: scope the query to `user_id =
     * current identity` and `firstOrFail` so cross-user attempts surface as 404
     * instead of leaking row existence. We use `set(..., ['guard' => false])`
     * to assign the share fields directly — this is the documented CakePHP
     * pattern for trusted controller-side writes that bypass the entity's
     * `_accessible` mass-assignment guard.
     *
     * @param int $id Card id (route-bound).
     * @return \Cake\Http\Response
     */
    public function share(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');

        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $cards = $this->fetchTable('Cards');
        $card = $cards->find('active')
            ->where(['Cards.id' => $id, 'Cards.user_id' => $userId])
            ->firstOrFail();

        // Idempotency guard: if the card is already actively shared, don't
        // rotate the slug. Re-sharing after a revoke (slug present BUT
        // revoked_at non-null) is allowed and falls through to mint a new
        // slug — this is the only way an operator can re-share post-revoke.
        if (!empty($card->share_slug) && empty($card->share_revoked_at)) {
            $this->Flash->info('This card is already shared.');

            return $this->redirect('/cards/' . $card->id);
        }

        // 256 bits of entropy. base64-encoding 32 raw bytes yields 44 chars
        // including a single '=' pad; URL-safe transform + rtrim('=') gives
        // a stable 43-char slug suitable for `/qsl/{slug}` URLs.
        $slug = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $passwordHash = null;
        $password = (string)$this->request->getData('password', '');
        if ($password !== '') {
            // Pin Argon2id explicitly — relying on PHP's PASSWORD_DEFAULT
            // would silently change algorithm with PHP-version upgrades and
            // we want share-link passwords to be stored under a known hasher.
            $passwordHash = (new \Authentication\PasswordHasher\DefaultPasswordHasher(
                ['hashType' => PASSWORD_ARGON2ID]
            ))->hash($password);
        }

        // `set(..., ['guard' => false])` bypasses `_accessible` so a future
        // tightening of the entity's mass-assignment surface (e.g. removing
        // share_* from accessible to prevent guest-form leakage) won't silently
        // break this trusted controller-side write.
        $card->set('share_slug', $slug, ['guard' => false]);
        $card->set('share_password_hash', $passwordHash, ['guard' => false]);
        $card->set('share_revoked_at', null, ['guard' => false]);
        $cards->saveOrFail($card);

        // M4-T3: Audit the share. Metadata records whether a password gate
        // was set, but never the password (or hash) itself.
        try {
            (new \App\Service\AuditLogger())->log(
                event: 'card.shared',
                actorUserId: $userId,
                target: ['type' => 'Cards', 'id' => $card->id],
                metadata: ['has_password' => $passwordHash !== null],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Card shared. Public link: /qsl/' . $slug);

        return $this->redirect('/cards/' . $card->id);
    }

    /**
     * Revoke a previously-minted share link (M2-T16).
     *
     * POST-only. Stamps `share_revoked_at` with the current timestamp; the
     * `share_slug` itself is left intact so the public `/qsl/{slug}` route
     * (M2-T14) continues to recognise the slug and respond with 410 Gone
     * rather than 404 Not Found — preserving the "this link existed and was
     * intentionally revoked" signal for anyone holding the URL.
     *
     * Idempotency / no-op surfaces:
     *  - Card never had a slug → flash info, redirect (no row change).
     *  - Card already has `share_revoked_at` → flash info, redirect (no
     *    row change). Stamping a fresh timestamp on every re-POST would
     *    rewrite history; the original revoke moment is the truthful one.
     *
     * Authorization mirrors `view`/`delete`/`share`: scope by `user_id` and
     * `firstOrFail` so cross-user attempts surface as 404 instead of leaking
     * row existence. Re-sharing post-revoke is handled by `share()` (which
     * mints a brand-new slug), so this endpoint deliberately does NOT clear
     * `share_revoked_at` — the only path back to a working public URL is to
     * mint a new slug, never to silently un-revoke the old one.
     *
     * @param int $id Card id (route-bound).
     * @return \Cake\Http\Response
     */
    public function revoke(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');

        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $cards = $this->fetchTable('Cards');
        $card = $cards->find('active')
            ->where(['Cards.id' => $id, 'Cards.user_id' => $userId])
            ->firstOrFail();

        if (empty($card->share_slug)) {
            $this->Flash->info('This card was never shared.');

            return $this->redirect('/cards/' . $card->id);
        }

        if ($card->share_revoked_at) {
            $this->Flash->info('Share already revoked.');

            return $this->redirect('/cards/' . $card->id);
        }

        // `set(..., ['guard' => false])` bypasses `_accessible` for the same
        // reason as `share()` — this is a trusted controller-side write that
        // should not depend on the entity's mass-assignment surface.
        $card->set('share_revoked_at', \Cake\I18n\DateTime::now(), ['guard' => false]);
        $cards->saveOrFail($card);

        // M4-T3: Audit the revoke. The slug stays in the row but is now 410.
        try {
            (new \App\Service\AuditLogger())->log(
                event: 'card.revoked',
                actorUserId: $userId,
                target: ['type' => 'Cards', 'id' => $card->id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Share link revoked.');

        return $this->redirect('/cards/' . $card->id);
    }
}
