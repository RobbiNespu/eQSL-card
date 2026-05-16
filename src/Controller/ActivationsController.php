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
