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

        $defaultBg = WWW_ROOT . 'files/templates/_default-bg.jpg';
        $bundledBg = WWW_ROOT . 'files/templates/_demo-bg.jpg';

        $this->set([
            'settings' => $settings->getAll(),
            'title' => 'Admin · Settings',
            'hasCustomBg' => is_file($defaultBg),
            'hasBundledBg' => is_file($bundledBg),
        ]);

        return null;
    }

    /**
     * POST /admin/settings/background — upload an admin-supplied default
     * background image. Saves directly to webroot/files/templates/_default-bg.jpg
     * (overwriting any prior upload). Falls back to the bundled _demo-bg.jpg
     * automatically when this file is absent.
     */
    public function background()
    {
        $this->request->allowMethod('post');

        $upload = $this->request->getUploadedFile('default_background');
        if (!$upload || $upload->getError() !== UPLOAD_ERR_OK) {
            $this->Flash->error('Please choose an image file.');
            return $this->redirect('/admin/settings');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'eqsl_dbg_');
        $upload->moveTo($tmp);

        $info = @getimagesize($tmp);
        if ($info === false) {
            @unlink($tmp);
            $this->Flash->error('Not a valid image.');
            return $this->redirect('/admin/settings');
        }
        if ($info[0] * $info[1] > 50_000_000) {
            @unlink($tmp);
            $this->Flash->error('Image too large.');
            return $this->redirect('/admin/settings');
        }

        $optimizer = new \App\Service\ImageOptimizer(
            maxWidth: 2000, maxHeight: 1500, quality: 86
        );
        $finalPath = WWW_ROOT . 'files/templates/_default-bg.jpg';
        try {
            $optimizer->optimize($tmp, $finalPath);
        } catch (\Throwable $e) {
            @unlink($tmp);
            $this->Flash->error('Could not process image: ' . $e->getMessage());
            return $this->redirect('/admin/settings');
        }
        @unlink($tmp);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'settings.default_background_changed',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Default background updated.');
        return $this->redirect('/admin/settings');
    }

    /**
     * POST /admin/settings/background/reset — delete the admin-supplied default
     * so the bundled _demo-bg.jpg becomes the active fallback again.
     */
    public function backgroundReset()
    {
        $this->request->allowMethod('post');
        $path = WWW_ROOT . 'files/templates/_default-bg.jpg';
        if (is_file($path)) {
            @unlink($path);

            try {
                (new \App\Service\AuditLogger())->log(
                    event: 'settings.default_background_reset',
                    actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }
        }

        $this->Flash->success('Default background reset to the bundled image.');
        return $this->redirect('/admin/settings');
    }
}
