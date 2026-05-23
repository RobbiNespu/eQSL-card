<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Public Controller
 *
 * Hosts the guest-facing eQSL generator. Both `index` (form) and
 * `generate` (POST handler, T20) are reachable without authentication.
 */
class PublicController extends AppController
{
    /**
     * Initialize hook — allow unauthenticated access to the public form.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
        $this->Authentication->allowUnauthenticated(['index', 'generate', 'share', 'unlock', 'downloadSharePdf']);
    }

    /**
     * Render the QSL generator form.
     *
     * @return void
     */
    public function index(): void
    {
        $templates = $this->fetchTable('Templates')->find()
            ->where(['OR' => [
                ['Templates.is_system' => true],
                ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
            ]])
            ->orderBy(['Templates.is_system' => 'DESC', 'Templates.created_at' => 'DESC'])
            ->all();

        $this->set([
            'title' => 'Generate an eQSL',
            'templates' => $templates,
        ]);
    }

    /**
     * Public share landing (M2-T14).
     *
     * Anonymous-accessible page that resolves a 43-char share slug to a
     * non-deleted card and renders the embedded card image + downloads.
     *
     * Three branches:
     *  - 404 if the slug doesn't match any active (non-soft-deleted) card.
     *  - 410 Gone if the share was revoked — search engines will deindex.
     *  - Redirect to `/qsl/{slug}/unlock` if a password is set and the
     *    visitor hasn't unlocked this slug in their session yet.
     *
     * Otherwise renders `share.php` with the QSO data, operator callsign,
     * PNG/PDF download links, and OG meta tags.
     *
     * @param string $slug 43-char URL-safe base64 slug (constrained by route).
     * @return mixed
     */
    /**
     * GET /qsl/{slug}/download.pdf — stream a PDF of a shared card.
     *
     * Mirrors the share-state checks from `share()`: 404 on unknown slug,
     * 410 on revoked share, and a redirect to the unlock gate if the card
     * has a password we haven't authenticated against in this session.
     * Successful path streams the PDF (built on demand from the rendered
     * card image — see CardsController::downloadPdf for the symmetric
     * owner / guest-preview surface).
     */
    public function downloadSharePdf(string $slug): \Cake\Http\Response
    {
        $card = $this->fetchTable('Cards')->find('active')
            ->where(['Cards.share_slug' => $slug])
            ->first();
        if (!$card) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($card->share_revoked_at) {
            // Mirror the share() 410 — but as a hard error since there's no
            // landing page to render PDF-as-attachment into.
            throw new \Cake\Http\Exception\HttpException('Share was revoked.', 410);
        }
        if ($card->share_password_hash) {
            $unlocked = $this->request->getSession()->read("share.unlocked.{$slug}", false);
            if (!$unlocked) {
                return $this->redirect('/qsl/' . $slug . '/unlock');
            }
        }

        $imagePath = WWW_ROOT . $card->png_path;
        if (!is_file($imagePath)) {
            throw new \Cake\Http\Exception\NotFoundException('Card image missing on disk.');
        }
        $template = $this->fetchTable('Templates')->get((int)$card->template_id);

        $tmpPdf = tempnam(sys_get_temp_dir(), 'eqsl_pdf_') . '.pdf';
        try {
            $renderer = \App\Service\CardRenderer::fromSettings(WWW_ROOT . 'files/fonts/');
            $renderer->wrapPdf($imagePath, $tmpPdf, (int)$template->canvas_width, (int)$template->canvas_height);
            $bytes = (string)file_get_contents($tmpPdf);
        } finally {
            @unlink($tmpPdf);
        }

        return $this->response
            ->withType('application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="card-' . $card->id . '.pdf"')
            ->withHeader('Content-Length', (string)strlen($bytes))
            ->withStringBody($bytes);
    }

    public function share(string $slug)
    {
        $card = $this->fetchTable('Cards')->find('active')
            ->where(['Cards.share_slug' => $slug])
            ->contain(['Users'])
            ->first();

        if (!$card) {
            throw new \Cake\Http\Exception\NotFoundException('Share not found.');
        }

        if ($card->share_revoked_at) {
            $this->setResponse($this->getResponse()->withStatus(410));
            $this->set([
                'title' => 'Share revoked',
                'qsoData' => null,
                'card' => null,
                'reason' => 'This share was revoked on ' . $card->share_revoked_at->format('Y-m-d') . '.',
                'operatorCallsign' => null,
            ]);
            $this->render('share_gone');

            return null;
        }

        if ($card->share_password_hash) {
            // Per-slug session cookie unlocks — once unlocked, subsequent
            // visits within the same session skip the gate.
            $unlocked = $this->request->getSession()->read("share.unlocked.{$slug}", false);
            if (!$unlocked) {
                return $this->redirect('/qsl/' . $slug . '/unlock');
            }
        }

        $qsoData = json_decode((string)$card->qso_data_json, true) ?: [];
        $operatorCallsign = (string)($card->user->callsign ?? $qsoData['operator_callsign'] ?? '');

        $this->set([
            'title' => 'eQSL — ' . ($qsoData['callsign'] ?? '#' . $card->id) . ' confirmed by ' . $operatorCallsign,
            'card' => $card,
            'qsoData' => $qsoData,
            'operatorCallsign' => $operatorCallsign,
            'shareSlug' => $slug,
        ]);

        return null;
    }

    /**
     * Public share password gate (M2-T15).
     *
     * GET renders the password form. POST verifies the supplied password
     * against the card's Argon2id `share_password_hash`; on a match we
     * write a per-slug session flag (`share.unlocked.{slug}`) so the
     * subsequent redirect into `share()` skips the gate, and bounce the
     * visitor back to `/qsl/{slug}`. Wrong passwords flash an error and
     * re-render the form.
     *
     * Edge cases:
     *  - Unknown slug → 404 (consistent with `share()`).
     *  - Revoked share or no password set → redirect to `/qsl/{slug}` so
     *    the share action surfaces the right state (410 Gone or open
     *    landing page) instead of duplicating that logic here.
     *
     * @param string $slug 43-char URL-safe base64 slug (constrained by route).
     * @return mixed
     */
    public function unlock(string $slug)
    {
        $card = $this->fetchTable('Cards')->find('active')
            ->where(['Cards.share_slug' => $slug])
            ->first();

        if (!$card) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($card->share_revoked_at || !$card->share_password_hash) {
            // No password protection — redirect to share page (which handles gone/no-pw)
            return $this->redirect('/qsl/' . $slug);
        }

        if ($this->request->is('post')) {
            $password = (string)$this->request->getData('password', '');
            $hasher = new \Authentication\PasswordHasher\DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]);
            if ($hasher->check($password, $card->share_password_hash)) {
                $this->request->getSession()->write("share.unlocked.{$slug}", true);
                return $this->redirect('/qsl/' . $slug);
            }
            $this->Flash->error('Incorrect password.');
        }

        $this->set([
            'title' => 'Password required',
            'slug' => $slug,
        ]);
        return null;
    }

    /**
     * Handle the POST submission and render a generated card.
     *
     * Wires up GuestSession + ImageOptimizer + CardRenderer with full
     * persistence into uploads + cards tables.
     *
     * @return mixed
     */
    public function generate()
    {
        $this->request->allowMethod(['post']);

        $data = $this->request->getData();
        $visit = (new \App\Service\GuestSession())->ensure($this->request);

        // Persist cookie if newly created
        $this->response = $this->response->withCookie(
            new \Cake\Http\Cookie\Cookie(
                \App\Service\GuestSession::COOKIE,
                $visit->session_token,
                null, '/', null, true, true, 'Lax'
            )
        );

        // Load the chosen template first (with strict guest-allowed scope),
        // or fall back to the first system template. The template owns its
        // background now — guests no longer pick / upload backgrounds on
        // this flow; if they want a different look they pick a different
        // template.
        $templateId = (int)($data['template_id'] ?? 0);
        $template = null;
        if ($templateId > 0) {
            $template = $this->fetchTable('Templates')->find()
                ->where(['Templates.id' => $templateId])
                ->where(['OR' => [
                    ['Templates.is_system' => true],
                    ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
                ]])
                ->first();
        }
        if (empty($template)) {
            $template = $this->fetchTable('Templates')->find()
                ->where(['is_system' => true])
                ->firstOrFail();
        }
        $layout = json_decode($template->layout_json, true);

        // Resolve the background. Template-bound bg wins; otherwise fall
        // through to the site-default image (SHA-deduped against the
        // existing uploads library, owned by the guest visit on first
        // insert so /admin/uploads still attributes it correctly).
        [$upload, $authorName, $license, $finalPath] = $this->resolveTemplateBackgroundForGuest($template, $visit->id);

        // Build QSO data
        $qso = $this->buildQsoData($data);

        // Render the card to WebP. The column name `cards.png_path` is kept
        // for backwards compat with rows persisted before this commit (they
        // still point at .png files which keep working). New rows here ship
        // .webp at quality 82 — same canvas, ~40% smaller on disk than the
        // prior PNG-level-6 baseline.
        $renderer = \App\Service\CardRenderer::fromSettings(WWW_ROOT . 'files/fonts/');
        $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $cardPath = WWW_ROOT . 'files/cards/' . $uuid . '.webp';
        if (!is_dir(dirname($cardPath))) {
            mkdir(dirname($cardPath), 0o775, true);
        }
        // Attribution line uses the freshly-computed local variables, NOT the
        // upload row's stored values. Why: an upload may already exist (sha256
        // dedup) with stale or null attribution from before this feature
        // shipped or from an earlier first-uploader. The current request's
        // intent — admin-configured for default-bg, form-supplied for new
        // upload — is the correct source.
        $attributionLine = \App\Service\ImageLicense::formatLine(
            $authorName,
            $license,
            (string)($qso['operator_callsign'] ?? '')
        );
        $renderer->renderPng(
            ['canvas_width' => $template->canvas_width, 'canvas_height' => $template->canvas_height,
             'fields' => $layout['fields']],
            $finalPath, $qso, $cardPath,
            extraFooterLines: [$attributionLine]
        );
        // No pre-rendered PDF — the PDF is built on demand by the download
        // controller action when the user clicks "Download PDF". Saves ~50%
        // of per-card disk usage since the PDF was just an FPDF wrapper
        // around the same pixels.

        // Persist card
        $cards = $this->fetchTable('Cards');
        $card = $cards->saveOrFail($cards->newEntity([
            'guest_visit_id' => $visit->id,
            'template_id' => $template->id,
            'upload_id' => $upload->id,
            'qso_data_json' => json_encode($qso, JSON_UNESCAPED_SLASHES),
            'png_path' => 'files/cards/' . $uuid . '.webp',
            'pdf_path' => null,
        ]));

        // M4-T3: Audit guest card generation. Tracked by guest_visit_id since
        // anonymous users have no user identity. Failures must never abort
        // the user-facing render flow.
        try {
            (new \App\Service\AuditLogger())->log(
                event: 'card.generated',
                actorGuestVisitId: $visit->id,
                target: ['type' => 'Cards', 'id' => $card->id],
                metadata: ['source' => 'public_generate'],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        // pdfUrl points at the lazy-download controller action so each click
        // streams a freshly-built PDF instead of serving a stored file.
        $this->set(['cardId' => $card->id, 'pngUrl' => '/' . $card->png_path, 'pdfUrl' => '/cards/' . $card->id . '/download.pdf']);
        $this->render('preview');
        return null;
    }

    /**
     * Resolve the background to use for a guest render. Templates own their
     * backgrounds; this method returns the right upload + attribution + the
     * resolved on-disk path the renderer will hand to CardRenderer.
     *
     * Template-bound bg wins. When the template has no bound bg (or the
     * bound row was deleted from /uploads), we fall through to the site-
     * default image (`_default-bg.jpg` if uploaded by an admin, otherwise
     * the bundled `_demo-bg.jpg`), optimise it, SHA-dedup it against
     * existing uploads, and either reuse the existing row or insert a new
     * one owned by this guest visit.
     *
     * @return array{0:object,1:?string,2:string,3:string} [upload, author, license, on-disk path]
     */
    private function resolveTemplateBackgroundForGuest(object $template, int $guestVisitId): array
    {
        $uploadsTbl = $this->fetchTable('CardBackgrounds');

        // 1) Template-bound bg path (preferred).
        $boundId = (int)($template->background_upload_id ?? 0);
        if ($boundId > 0) {
            $bound = $uploadsTbl->find()
                ->where(['id' => $boundId, 'CardBackgrounds.deleted_at IS' => null])
                ->first();
            if ($bound !== null) {
                $abs = WWW_ROOT . $bound->storage_path;
                if (is_file($abs)) {
                    return [$bound, $bound->author_name, (string)$bound->license, $abs];
                }
            }
            // Bound row vanished — fall through to site default rather than
            // 500ing on the guest.
        }

        // 2) Site default fallback. Choose admin override then bundled demo.
        $candidates = [
            WWW_ROOT . 'files/templates/_default-bg.jpg',
            WWW_ROOT . 'files/templates/_demo-bg.jpg',
        ];
        $source = null;
        foreach ($candidates as $abs) {
            if (is_file($abs)) {
                $source = $abs;
                break;
            }
        }
        if ($source === null) {
            throw new \Cake\Http\Exception\BadRequestException(
                'No background image available — admin must set a default.'
            );
        }

        // Bomb-defence guard before GD touches it.
        $info = @getimagesize($source);
        if ($info === false) {
            throw new \Cake\Http\Exception\BadRequestException('Default background is not a valid image.');
        }
        if ($info[0] * $info[1] > 50_000_000) {
            throw new \Cake\Http\Exception\BadRequestException('Default background dimensions exceed allowed limit.');
        }

        // Optimise + SHA-dedup.
        $optimizer = new \App\Service\ImageOptimizer(maxWidth: 1600, maxHeight: 1200, quality: 78);
        $tmpDest = tempnam(sys_get_temp_dir(), 'eqsl_opt_');
        $opt = $optimizer->optimize($source, $tmpDest);
        $sha = $opt['sha256_hash'];
        $uploadsDir = WWW_ROOT . 'files/uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o775, true);
        }
        $finalPath = $uploadsDir . $sha . '.jpg';
        if (is_file($finalPath)) {
            @unlink($tmpDest);
        } else {
            rename($tmpDest, $finalPath);
        }

        $settings = new \App\Service\AppSettings();
        $authorName = trim((string)$settings->get('default_background_author', ''));
        $authorName = $authorName !== '' ? $authorName : null;
        $license = (string)$settings->get('default_background_license', 'unknown');

        $upload = $uploadsTbl->find()->where(['sha256_hash' => $sha])->first();
        if (!$upload) {
            $upload = $uploadsTbl->saveOrFail($uploadsTbl->newEntity([
                'guest_visit_id'    => $guestVisitId,
                'original_filename' => 'site-default.jpg',
                'storage_path'      => 'files/uploads/' . $sha . '.jpg',
                'mime_type'         => 'image/jpeg',
                'width_px'          => $opt['width_px'],
                'height_px'         => $opt['height_px'],
                'file_size_bytes'   => $opt['file_size_bytes'],
                'sha256_hash'       => $sha,
                'author_name'       => $authorName,
                'license'           => $license,
            ]));
        } elseif ($upload->deleted_at !== null) {
            $upload->set('deleted_at', null, ['guard' => false]);
            $upload->set('author_name', $authorName, ['guard' => false]);
            $upload->set('license', $license, ['guard' => false]);
            $uploadsTbl->saveOrFail($upload);
        }

        return [$upload, $authorName, $license, $finalPath];
    }

    /**
     * Normalise raw POST data into a QSO array consumed by CardRenderer.
     */
    private function buildQsoData(array $data): array
    {
        return [
            'callsign'           => trim((string)($data['callsign'] ?? '')),
            'operator_callsign'  => trim((string)($data['operator_callsign'] ?? '')),
            'qso_datetime_utc'   => (string)($data['qso_datetime_utc'] ?? ''),
            'qso_date_hijri'     => ($data['qso_datetime_utc'] ?? '') !== ''
                ? \App\Service\HijriDate::fromGregorian(new \DateTimeImmutable((string)$data['qso_datetime_utc']))
                : '',
            'frequency_mhz'      => (string)($data['frequency_mhz'] ?? ''),
            'band'               => (string)($data['band'] ?? ''),
            'mode'               => (string)($data['mode'] ?? ''),
            'rst_sent'           => (string)($data['rst_sent'] ?? ''),
            'rst_received'       => (string)($data['rst_received'] ?? ''),
            'operator_name'      => (string)($data['operator_name'] ?? ''),
            'notes'              => (string)($data['notes'] ?? ''),
            // QSO type + net fields are now exposed on the guest form via a
            // Contact / Net toggle. We only honour the submitted net values
            // when qso_type === 'net'; otherwise force them empty so a stale
            // value in a contact submission doesn't leak onto the rendered
            // card. Anything other than the literal string 'net' falls back
            // to 'contact', matching the logged-in flow.
            'qso_type'           => (($data['qso_type'] ?? '') === 'net') ? 'net' : 'contact',
            'ncs_callsign'       => (($data['qso_type'] ?? '') === 'net') ? trim((string)($data['ncs_callsign'] ?? '')) : '',
            'net_title'          => (($data['qso_type'] ?? '') === 'net') ? trim((string)($data['net_title'] ?? '')) : '',
            'net_organisation'   => (($data['qso_type'] ?? '') === 'net') ? trim((string)($data['net_organisation'] ?? '')) : '',
            // Transport defaults to RF for guest cards — the public form
            // doesn't expose a transport picker.
            'transport'          => \App\Service\Transport::label('rf'),
            'transport_meta'     => '',
        ];
    }
}
