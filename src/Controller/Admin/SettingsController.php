<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\OperationLog;

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
class SettingsController extends AdminController
{

    /**
     * GET/POST /admin/settings — render form and handle saves.
     *
     * On POST, applies an explicit allow-list of keys (to prevent arbitrary DB
     * writes), coerces numeric fields, persists via AppSettings::setMany(), and
     * PRG-redirects back. Boolean toggles are handled separately because HTML
     * checkboxes only POST when checked — the view uses a sibling hidden field
     * with the unchecked value.
     *
     * @return \Cake\Http\Response|null Redirect on POST, null for GET.
     */
    public function index()
    {
        $settings = new \App\Service\AppSettings();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $allowed = [
                'site_name', 'max_upload_mb', 'share_base_url',
                'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
                'eqsl_credit_template',
                'card_retention_days',
                'callsign_lookup_providers',
            ];
            $update = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $data)) {
                    $value = $data[$key];
                    if (in_array($key, ['max_upload_mb', 'smtp_port', 'card_retention_days'], true)) {
                        $value = max(0, (int)$value);
                    }
                    $update[$key] = $value;
                }
            }
            // Boolean toggles handled separately: HTML checkboxes only POST
            // when checked, so we rely on a sibling hidden field with the
            // unchecked value (see settings/index.php) and coerce explicitly.
            if (array_key_exists('rate_limit_private_ip_bypass', $data)) {
                $update['rate_limit_private_ip_bypass'] = (bool)$data['rate_limit_private_ip_bypass'];
            }
            if (array_key_exists('callsign_lookup_enabled', $data)) {
                $update['callsign_lookup_enabled'] = (bool)$data['callsign_lookup_enabled'];
            }
            // Provider checkboxes arrive as `callsign_provider[radioid]=1` etc.
            // Reassemble into a CSV string in checkbox order.
            if (isset($data['callsign_provider']) && is_array($data['callsign_provider'])) {
                $enabledCodes = array_keys(array_filter($data['callsign_provider']));
                $update['callsign_lookup_providers'] = implode(',', $enabledCodes);
            }
            $settings->setMany($update);

            $actorId = $this->Authentication->getIdentity()->getIdentifier();
            try {
                (new \App\Service\AuditLogger())->log(
                    event: 'settings.updated',
                    actorUserId: $actorId,
                    metadata: ['keys' => array_keys($update)],
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }

            OperationLog::event('admin.settings.saved', [
                'actor_user_id' => $actorId,
                'keys'          => array_keys($update),
            ]);

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
     * POST /admin/settings/background — upload a new site-default background image.
     *
     * Saves to webroot/files/templates/_default-bg.jpg (overwriting any prior
     * upload). Also SHA-deduplicates into uploads/ and persists an uploads row so
     * the image appears in /admin/card-backgrounds. The bundled _demo-bg.jpg is
     * used automatically when this file is absent.
     *
     * @return \Cake\Http\Response
     * @throws \Cake\ORM\Exception\PersistenceFailedException On upload-row save failure.
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
            OperationLog::failure('admin.settings.background_upload', $e, [
                'actor_user_id' => $this->Authentication->getIdentity()->getIdentifier(),
            ]);
            $this->Flash->error('Could not process image: ' . $e->getMessage());
            return $this->redirect('/admin/settings');
        }
        @unlink($tmp);

        // Attribution for the default background — stored in app_settings
        // because the file isn't represented as an `uploads` row. Read by
        // the renderer-side controllers when they fall back to the default
        // bg (PublicController, QsosController) via AppSettings::get(...).
        $authorName = trim((string)$this->request->getData('default_background_author', ''));
        $licenseRaw = trim((string)$this->request->getData('default_background_license', ''));
        $license = ($licenseRaw !== '' && array_key_exists($licenseRaw, \App\Service\ImageLicense::LICENSES))
            ? $licenseRaw
            : 'unknown';
        // Also persist the bytes as a regular `uploads` row so the admin
        // can see/manage the default background on /admin/uploads alongside
        // every other library image. SHA-dedupe: if the same image was
        // previously uploaded (by anyone, or by a prior render via the chain)
        // we reuse the existing row and refresh its attribution; otherwise
        // we insert a fresh row that points at a copy in files/uploads/.
        $sha = hash_file('sha256', $finalPath);
        $uploadsTable = $this->fetchTable('CardBackgrounds');
        $existingRow = $uploadsTable->find()->where(['sha256_hash' => $sha])->first();
        if ($existingRow !== null) {
            // Resurrect a soft-deleted match and refresh attribution to
            // match the just-uploaded admin context.
            if ($existingRow->deleted_at !== null) {
                $existingRow->set('deleted_at', null, ['guard' => false]);
            }
            $existingRow->set('author_name', $authorName !== '' ? $authorName : null, ['guard' => false]);
            $existingRow->set('license', $license, ['guard' => false]);
            $uploadsTable->saveOrFail($existingRow);
            $defaultBgUploadId = (int)$existingRow->id;
        } else {
            $uploadsDir = WWW_ROOT . 'files/uploads/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0o775, true);
            }
            $storedPath = 'files/uploads/' . $sha . '.jpg';
            // Copy (not move) — keep the canonical file at _default-bg.jpg
            // for the existing renderer-side fallback chain that loads bytes
            // from there.
            if (!is_file(WWW_ROOT . $storedPath)) {
                copy($finalPath, WWW_ROOT . $storedPath);
            }
            $dims = @getimagesize($finalPath) ?: [0, 0];
            $newRow = $uploadsTable->newEntity([
                'user_id'           => $this->Authentication->getIdentity()->getIdentifier(),
                'original_filename' => $upload->getClientFilename() ?: 'default-background.jpg',
                'storage_path'      => $storedPath,
                'mime_type'         => 'image/jpeg',
                'width_px'          => (int)$dims[0],
                'height_px'         => (int)$dims[1],
                'file_size_bytes'   => (int)filesize($finalPath),
                'sha256_hash'       => $sha,
                'author_name'       => $authorName !== '' ? $authorName : null,
                'license'           => $license,
            ]);
            $uploadsTable->saveOrFail($newRow);
            $defaultBgUploadId = (int)$newRow->id;
        }

        $appSettings = new \App\Service\AppSettings();
        $appSettings->setMany([
            'default_background_author'     => $authorName,
            'default_background_license'    => $license,
            'default_background_upload_id'  => $defaultBgUploadId,
        ]);

        $actorId = $this->Authentication->getIdentity()->getIdentifier();
        try {
            (new \App\Service\AuditLogger())->log(
                event: 'settings.default_background_changed',
                actorUserId: $actorId,
                metadata: ['author' => $authorName ?: null, 'license' => $license, 'upload_id' => $defaultBgUploadId],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.settings.default_background_changed', [
            'actor_user_id' => $actorId,
            'upload_id'     => $defaultBgUploadId,
            'license'       => $license,
        ]);

        $this->Flash->success('Default background updated.');
        return $this->redirect('/admin/settings');
    }

    /**
     * POST /admin/settings/background/reset — revert to the bundled default background.
     *
     * Deletes the admin-supplied _default-bg.jpg so the renderer falls back to
     * the bundled _demo-bg.jpg. The uploads row is intentionally NOT deleted —
     * cards rendered with this background may still reference it.
     *
     * @return \Cake\Http\Response
     */
    public function backgroundReset()
    {
        $this->request->allowMethod('post');
        $actorId = $this->Authentication->getIdentity()->getIdentifier();
        $path = WWW_ROOT . 'files/templates/_default-bg.jpg';
        if (is_file($path)) {
            @unlink($path);

            try {
                (new \App\Service\AuditLogger())->log(
                    event: 'settings.default_background_reset',
                    actorUserId: $actorId,
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }

            OperationLog::event('admin.settings.default_background_reset', [
                'actor_user_id' => $actorId,
            ]);
        }

        // Clear the pointer to the uploads row that was acting as the default.
        // The row itself is intentionally NOT deleted — cards rendered with
        // this background may still reference it, and the admin can clean it
        // up from /admin/uploads if they want it gone for good.
        (new \App\Service\AppSettings())->set('default_background_upload_id', '');

        $this->Flash->success('Default background reset to the bundled image.');
        return $this->redirect('/admin/settings');
    }
}
