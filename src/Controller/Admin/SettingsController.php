<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

/**
 * App settings UI (M4-T17/T18).
 *
 * GET  /admin/settings — render the form pre-populated from the AppSettings
 *                        runtime loader (in-memory cached).
 * POST /admin/settings — apply a fixed allow-list of keys, coerce numeric
 *                        fields, persist via AppSettings::setMany() (which
 *                        invalidates the cache), audit-log the keys touched,
 *                        and PRG-redirect back to the form.
 *
 * Access control mirrors the rest of `App\Controller\Admin\*`: anonymous hits
 * redirect to /login through AuthenticationMiddleware; authenticated non-admins
 * get a 403 from `beforeFilter()`.
 */
class SettingsController extends AppController
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
            return;
        }
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        if ($user->role !== 'admin') {
            throw new \Cake\Http\Exception\ForbiddenException('Admin only.');
        }
    }

    public function index()
    {
        $settings = new \App\Service\AppSettings();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $allowed = [
                'site_name', 'max_upload_mb', 'share_base_url',
                'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
            ];
            $update = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $data)) {
                    $value = $data[$key];
                    if ($key === 'max_upload_mb' || $key === 'smtp_port') {
                        $value = (int)$value;
                    }
                    $update[$key] = $value;
                }
            }
            $settings->setMany($update);

            try {
                (new \App\Service\AuditLogger())->log(
                    event: 'settings.updated',
                    actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                    metadata: ['keys' => array_keys($update)],
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }

            $this->Flash->success('Settings saved.');

            return $this->redirect('/admin/settings');
        }

        $this->set([
            'settings' => $settings->getAll(),
            'title' => 'Admin · Settings',
        ]);

        return null;
    }
}
