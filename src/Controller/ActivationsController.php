<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * M5 T14 — Activations CRUD.
 *
 * Owner-scoped at every action: requests for another user's activation
 * 404 before any work happens. Two primary user actions:
 *
 *   start  → POST /activations    (writes started_at = now, ended_at = null)
 *   end    → POST /activations/{id}/end   (writes ended_at = now)
 *
 * Plus index/edit/delete for the housekeeping surface.
 *
 *   index  → GET  /activations             list (active first, then recent)
 *   edit   → GET/POST /activations/{id}/edit  rename, fix grid, notes
 *   delete → POST /activations/{id}/delete    hard-delete the activation row
 *
 * Delete is hard, but qsos.activation_id has ON DELETE SET NULL, so
 * deleting an activation never destroys the contacts logged under it —
 * they revert to "not part of an activation" but stay in the logbook.
 */
class ActivationsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function index(): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('Activations');

        $active = $tbl->findActiveForUser($userId);
        $recent = $tbl->findRecentForUser($userId, 50)->all();

        $this->set([
            'active' => $active,
            'recent' => $recent,
            'newActivation' => $tbl->newEmptyEntity(),
            'title' => 'Activations',
        ]);
    }

    /**
     * Start a new activation. Server stamps started_at; ended_at stays null.
     * If the user already has an active activation, the new one becomes
     * "the" active one — implicit: the prior active row is left running
     * (operator can end it manually). We don't auto-end because two
     * activations in flight is a legitimate edge case (operator running
     * a net WHILE on a SOTA summit).
     */
    public function start(): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('Activations');

        $entity = $tbl->patchEntity($tbl->newEmptyEntity(), $this->request->getData());
        $entity->set('user_id', $userId, ['guard' => false]);
        $entity->set('started_at', \Cake\I18n\DateTime::now(), ['guard' => false]);

        if ($tbl->save($entity)) {
            $this->Flash->success('Started activation: ' . $entity->name . '.');
            return $this->redirect('/activations');
        }
        $this->Flash->error('Could not start activation. Check fields.');
        // Re-render the index page with the errored entity bound to the
        // form so the user sees their input + the error messages.
        $active = $tbl->findActiveForUser($userId);
        $recent = $tbl->findRecentForUser($userId, 50)->all();
        $this->set([
            'active' => $active,
            'recent' => $recent,
            'newActivation' => $entity,
            'title' => 'Activations',
        ]);
        $this->render('index');
        return $this->response;
    }

    /**
     * Mark an activation ended. Idempotent — already-ended rows stamp a
     * fresh ended_at; the operator's intent is "this is done" either way.
     */
    public function end(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('Activations');

        $activation = $tbl->find()
            ->where(['id' => $id, 'user_id' => $userId])
            ->firstOrFail();

        $activation->set('ended_at', \Cake\I18n\DateTime::now(), ['guard' => false]);
        $tbl->saveOrFail($activation);

        $this->Flash->success('Ended activation: ' . $activation->name . '.');
        return $this->redirect('/activations');
    }

    public function edit(int $id): ?\Cake\Http\Response
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('Activations');

        $activation = $tbl->find()
            ->where(['id' => $id, 'user_id' => $userId])
            ->firstOrFail();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $tbl->patchEntity($activation, $this->request->getData());
            if ($tbl->save($activation)) {
                $this->Flash->success('Activation updated.');
                return $this->redirect('/activations');
            }
            $this->Flash->error('Could not save. Check fields.');
        }

        $this->set(['activation' => $activation, 'title' => 'Edit activation']);
        return null;
    }

    /**
     * M5 T17 — ADIF export per activation.
     *
     * `/activations/{id}/export.adi` returns an ADIF 3.1.4 text/plain
     * document with every QSO tagged to this activation, ready to upload
     * to POTA / SOTA / IOTA portals. MY_GRIDSQUARE comes from the
     * activation row; MY_POTA_REF / MY_SOTA_REF / MY_IOTA are inferred
     * from the activation.code prefix (POTA-* / SOTA-* / IOTA-*).
     *
     * Owner-scoped: trying to export another user's activation 404s.
     * Empty activations (zero QSOs tagged) export a header-only file —
     * still valid ADIF, useful as a sanity check.
     */
    public function export(int $id): \Cake\Http\Response
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('Activations');

        $activation = $tbl->find()
            ->where(['id' => $id, 'user_id' => $userId])
            ->firstOrFail();

        // QSOs scoped to (this activation, this user) — defensive double
        // check; activation ownership already ensures the user_id match
        // via the FK, but explicit beats implicit when generating an
        // upload that goes to a public portal.
        $qsos = $this->fetchTable('Qsos')->find()
            ->where(['user_id' => $userId, 'activation_id' => $id])
            ->orderBy(['qso_datetime_utc' => 'ASC', 'id' => 'ASC'])
            ->all();

        $callsign = (string)($this->Authentication->getIdentity()->getOriginalData()->callsign ?? '');

        $adif = (new \App\Service\AdifExporter())->export($activation, $qsos, $callsign);

        // Slugify the activation name for the filename. Awards portals
        // accept any filename but operators appreciate something they
        // can find later.
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$activation->code);
        $slug = trim((string)$slug, '-');
        $filename = ($slug !== '' ? $slug : 'activation-' . $id) . '.adi';

        return $this->response
            ->withType('text/plain')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody($adif);
    }

    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('Activations');

        $activation = $tbl->find()
            ->where(['id' => $id, 'user_id' => $userId])
            ->firstOrFail();

        $tbl->deleteOrFail($activation);

        $this->Flash->success('Activation deleted. QSOs logged under it are still in your logbook.');
        return $this->redirect('/activations');
    }
}
