<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\OperationLog;

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
class TemplatesController extends AdminController
{

    /**
     * List templates awaiting approval (is_public=true, is_approved=false).
     *
     * @return void
     */
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

    /**
     * POST /admin/templates/{id}/approve — flip is_approved to true.
     *
     * Only acts on templates that are currently public+pending (prevents
     * double-approval and acting on private or already-approved templates).
     *
     * @param int $id Template PK.
     * @return \Cake\Http\Response
     */
    public function approve(int $id)
    {
        $this->request->allowMethod('post');
        $tpls = $this->fetchTable('Templates');
        $tpl = $tpls->find()
            ->where(['id' => $id, 'is_public' => true, 'is_approved' => false])
            ->firstOrFail();
        $tpl->set('is_approved', true, ['guard' => false]);
        $tpls->saveOrFail($tpl);

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

        OperationLog::event('admin.template.approved', [
            'actor_user_id' => $adminId,
            'template_id'   => (int)$tpl->id,
        ]);

        $this->Flash->success('Template approved.');

        return $this->redirect('/admin/templates/pending');
    }

    /**
     * POST /admin/templates/{id}/reject — clear is_public so the submitter can
     * revise and resubmit. is_approved is left false.
     *
     * @param int $id Template PK.
     * @return \Cake\Http\Response
     */
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

        OperationLog::event('admin.template.rejected', [
            'actor_user_id' => $adminId,
            'template_id'   => (int)$tpl->id,
        ]);

        $this->Flash->success('Template rejected.' . ($reason !== '' ? " Reason: {$reason}" : ''));

        return $this->redirect('/admin/templates/pending');
    }
}
