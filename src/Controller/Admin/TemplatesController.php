<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

/**
 * Admin template moderation queue (M3-T10/T11/T12).
 *
 * Owners flag a template `is_public = true` (M3-T9) which puts it in front
 * of an admin for review; approval flips `is_approved = true` so the gallery
 * surfaces it, while rejection clears `is_public` so the submitter can edit
 * and resubmit. Access control mirrors UpgradeController: anonymous users
 * are bounced to /login by the auth middleware, authenticated non-admins
 * receive a 403.
 */
class TemplatesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return; // let auth middleware handle redirect
        }
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        if ($user->role !== 'admin') {
            throw new \Cake\Http\Exception\ForbiddenException('Admin only.');
        }
    }

    public function pending(): void
    {
        $pending = $this->fetchTable('Templates')->find()
            ->where(['Templates.is_public' => true, 'Templates.is_approved' => false])
            ->contain(['Users'])
            ->orderBy(['Templates.created_at' => 'DESC'])
            ->all();

        $this->set([
            'pending' => $pending,
            'title' => 'Admin · Pending templates',
        ]);
    }

    public function approve(int $id)
    {
        $this->request->allowMethod('post');
        $tpls = $this->fetchTable('Templates');
        $tpl = $tpls->find()
            ->where(['id' => $id, 'is_public' => true, 'is_approved' => false])
            ->firstOrFail();
        $tpl->set('is_approved', true, ['guard' => false]);
        $tpls->saveOrFail($tpl);

        // M4-T3: replace the placeholder error_log() shim with a real
        // audit_logs row. Audit failures must never break the moderation
        // flow, so we wrap and swallow.
        $adminId = $this->Authentication->getIdentity()->getIdentifier();
        try {
            (new \App\Service\AuditLogger())->log(
                event: 'template.approved',
                actorUserId: $adminId,
                target: ['type' => 'Templates', 'id' => (int)$tpl->id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Template approved.');

        return $this->redirect('/admin/templates/pending');
    }

    public function reject(int $id)
    {
        $this->request->allowMethod('post');
        $tpls = $this->fetchTable('Templates');
        $tpl = $tpls->find()
            ->where(['id' => $id, 'is_public' => true, 'is_approved' => false])
            ->firstOrFail();

        $reason = trim((string)$this->request->getData('reason', ''));

        $tpl->set('is_public', false, ['guard' => false]);
        // is_approved stays false; submitter can re-edit and re-submit
        $tpls->saveOrFail($tpl);

        // M4-T3: persist the rejection as an audit row. The reason text
        // (admin's free-form comment) goes into metadata so the audit
        // viewer can surface it to future reviewers without a join.
        $adminId = $this->Authentication->getIdentity()->getIdentifier();
        try {
            (new \App\Service\AuditLogger())->log(
                event: 'template.rejected',
                actorUserId: $adminId,
                target: ['type' => 'Templates', 'id' => (int)$tpl->id],
                metadata: ['reason' => $reason],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Template rejected.' . ($reason !== '' ? " Reason: {$reason}" : ''));

        return $this->redirect('/admin/templates/pending');
    }
}
