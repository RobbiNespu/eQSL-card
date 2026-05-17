<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Profile Controller (M4-T15 + T16).
 *
 * `/profile` lets the authenticated user edit their own identity fields
 * (display name, callsign, QTH, grid square, bio) and upload a per-user
 * avatar that is rendered next to their name on cards / share pages.
 *
 * Two surfaces:
 *  - `index` (GET + POST): renders the form; POST patches a fixed allow-list
 *    of fields. Crucially we do NOT pass `$this->request->getData()` straight
 *    into `patchEntity` — `User::_accessible` lists `role` and `email` as
 *    settable for legitimate flows (admin user CRUD, registration), so a
 *    direct passthrough would let the user escalate themselves to admin.
 *    We keep the allow-list narrow and immediately drop everything else.
 *  - `uploadAvatar` (POST): receives the file, runs the same image-bomb +
 *    getimagesize guard the designer / public form use, then re-encodes via
 *    `ImageOptimizer` (bounding box 256×256 — avatars don't need 2000px).
 *    The final path is `files/avatars/{user_id}.jpg`, stored relative to
 *    WWW_ROOT so the existing static handler serves it. Subsequent uploads
 *    overwrite the same path — there's only ever one avatar per user.
 */
class ProfileController extends AppController
{
    /**
     * Both actions require an authenticated identity. The `Authentication`
     * component is loaded explicitly here (rather than relying on AppController)
     * to mirror the convention used by `AuthController` / `QsosController` —
     * the unauthenticated middleware redirect to `/login` is what gates anonymous
     * access to either endpoint.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    /**
     * GET renders the profile form (with avatar preview); POST patches the
     * narrow set of identity fields the user is allowed to self-edit.
     *
     * Hardening: the allow-list filter on `$data` BEFORE patchEntity is the
     * security boundary — `role`, `email`, `password_hash`, etc. are all
     * accessible at the entity level for other code paths, so we cannot rely
     * on `_accessible` alone to keep them out of a self-service surface.
     *
     * @return \Cake\Http\Response|null
     */
    public function index()
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $users = $this->fetchTable('Users');
        $user = $users->get($userId);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            // Narrow allow-list — anything not listed here is dropped before
            // it can reach patchEntity. This blocks role / email / hash
            // overrides regardless of how `_accessible` is configured.
            $allowed = [
                'name' => true,
                'callsign' => true,
                'qth' => true,
                'grid_square' => true,
                'bio' => true,
                // M5 T27 — opt-in quick-add safety toggle. The form has
                // a hidden 0-input so unchecked POSTs send '0' explicitly;
                // patchEntity coerces to the boolean column.
                'block_dupes_in_activation' => true,
                // M5 T29 — opt-in NATO-phonetic mic on /qsos/quick.
                // Same hidden-0 + checkbox pattern as block_dupes.
                'voice_input_callsign' => true,
            ];
            $patch = array_intersect_key($data, $allowed);
            $users->patchEntity($user, $patch);
            if ($users->save($user)) {
                $this->Flash->success('Profile updated.');

                return $this->redirect('/profile');
            }
            $this->Flash->error('Could not save profile.');
        }

        $this->set([
            'user' => $user,
            'title' => 'Profile',
        ]);

        return null;
    }

    /**
     * Avatar upload (M4-T16).
     *
     * POST-only. Uses the same defensive sequence as the designer background
     * uploader (M3-T5) and public form (M1) — getimagesize guard FIRST so
     * non-images bounce before ImageOptimizer touches GD, then the pixel-count
     * cap to defend against image-bomb decompression attacks, then re-encode
     * to JPEG to strip EXIF / embedded payloads. The optimizer is configured
     * with a 256×256 bounding box because avatars never need anything bigger;
     * the resulting file is well under 30 KB for typical photos.
     *
     * The final path is deterministic per user (`files/avatars/{user_id}.jpg`)
     * so re-uploading silently overwrites the previous avatar — we never
     * accumulate stale files. The avatar_path column is set with `guard:false`
     * because the entity does not list it in `_accessible` (the column is
     * intentionally not user-settable; only this action may write it).
     *
     * @return \Cake\Http\Response|null
     */
    public function uploadAvatar()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();

        $file = $this->request->getUploadedFile('avatar');
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $this->Flash->error('Please choose an image file.');

            return $this->redirect('/profile');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'eqsl_av_');
        $file->moveTo($tmp);

        // Image-bomb defense — reject non-images and absurd pixel counts
        // BEFORE ImageOptimizer hands the file to GD.
        $info = @getimagesize($tmp);
        if ($info === false) {
            @unlink($tmp);
            $this->Flash->error('Not a valid image.');

            return $this->redirect('/profile');
        }
        if ($info[0] * $info[1] > 50_000_000) {
            @unlink($tmp);
            $this->Flash->error('Image too large.');

            return $this->redirect('/profile');
        }

        // Avatars use a much tighter bounding box than card backgrounds.
        $optimizer = new \App\Service\ImageOptimizer(maxWidth: 256, maxHeight: 256, quality: 86);
        $avatarsDir = WWW_ROOT . 'files/avatars/';
        if (!is_dir($avatarsDir)) {
            mkdir($avatarsDir, 0o775, true);
        }
        $finalPath = $avatarsDir . $userId . '.jpg';
        try {
            $optimizer->optimize($tmp, $finalPath);
        } catch (\Throwable $e) {
            @unlink($tmp);
            $this->Flash->error('Could not process image: ' . $e->getMessage());

            return $this->redirect('/profile');
        }
        @unlink($tmp);

        $relPath = 'files/avatars/' . $userId . '.jpg';
        $users = $this->fetchTable('Users');
        $user = $users->get($userId);
        // `avatar_path` is intentionally not mass-assignable — only this
        // action may write it, and only after the optimizer has produced
        // the matching file on disk.
        $user->set('avatar_path', $relPath, ['guard' => false]);
        $users->saveOrFail($user);

        $this->Flash->success('Avatar updated.');

        return $this->redirect('/profile');
    }
}
