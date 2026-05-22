<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Utility\Security;

/**
 * M6 — NCS dashboard owner/co-logger surface. Owner controls lifecycle;
 * owner + co-loggers may log check-ins.
 */
class NetSessionsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    private function ownedOrFail(int $id): \App\Model\Entity\NetSession
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $row = $this->fetchTable('NetSessions')->find()
            ->where(['id' => $id, 'owner_id' => $uid])->first();
        if ($row === null) {
            throw new NotFoundException('Net session not found.');
        }
        return $row;
    }

    public function index(): void
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('NetSessions');
        $this->set([
            'live'        => $tbl->findLiveForUser($uid)->all(),
            'upcoming'    => $tbl->findUpcomingForUser($uid)->all(),
            'recent'      => $tbl->findRecentForUser($uid, 50)->all(),
            'newSession'  => $tbl->newEmptyEntity(),
            'title'       => 'Net sessions',
        ]);
    }

    public function add(): ?\Cake\Http\Response
    {
        $tbl = $this->fetchTable('NetSessions');
        $session = $tbl->newEmptyEntity();
        if ($this->request->is('post')) {
            $session = $tbl->patchEntity($session, $this->request->getData());
            $uid = $this->Authentication->getIdentity()->getIdentifier();
            $session->set('owner_id', $uid, ['guard' => false]);
            $session->set('status', 'scheduled', ['guard' => false]);
            $session->set('public_slug', $this->uniqueSlug(), ['guard' => false]);
            $session->set('logger_token', strtolower(Security::randomString(20)), ['guard' => false]);
            if ($tbl->save($session)) {
                $this->Flash->success('Net session created.');
                return $this->redirect(['action' => 'view', $session->id]);
            }
            $this->Flash->error('Could not create the net session.');
        }
        $this->set(['session' => $session, 'title' => 'New net session']);
        return null;
    }

    public function edit(int $id): ?\Cake\Http\Response
    {
        $session = $this->ownedOrFail($id);
        if ($this->request->is(['post', 'put'])) {
            $session = $this->fetchTable('NetSessions')->patchEntity($session, $this->request->getData());
            if ($this->fetchTable('NetSessions')->save($session)) {
                $this->Flash->success('Net session updated.');
                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error('Could not update the net session.');
        }
        $this->set(['session' => $session, 'title' => 'Edit net session']);
        return null;
    }

    public function start(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $session->set('status', 'live', ['guard' => false]);
        $session->set('started_at', DateTime::now(), ['guard' => false]);
        $this->fetchTable('NetSessions')->saveOrFail($session);
        $this->Flash->success('Net is live.');
        return $this->redirect(['action' => 'cockpit', $id]);
    }

    public function end(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $session->set('status', 'ended', ['guard' => false]);
        $session->set('ended_at', DateTime::now(), ['guard' => false]);
        $this->fetchTable('NetSessions')->saveOrFail($session);
        $this->Flash->success('Net ended.');
        return $this->redirect(['action' => 'view', $id]);
    }

    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $this->fetchTable('NetSessions')->deleteOrFail($session);
        $this->Flash->success('Net session deleted.');
        return $this->redirect(['action' => 'index']);
    }

    public function view(int $id): void
    {
        $session = $this->ownedOrFail($id);
        $this->set(['session' => $session, 'title' => $session->net_title]);
    }

    private function uniqueSlug(): string
    {
        $tbl = $this->fetchTable('NetSessions');
        do {
            $slug = strtolower(Security::randomString(16));
        } while ($tbl->exists(['public_slug' => $slug]));
        return $slug;
    }
}
