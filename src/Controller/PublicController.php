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
        $this->Authentication->allowUnauthenticated(['index', 'generate']);
    }

    /**
     * Render the QSL generator form.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('title', 'Generate an eQSL');
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
        $this->Authentication->allowUnauthenticated(['index', 'generate']);
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

        // Resolve background source
        $tmpUpload = $this->resolveBackground($data);

        // T7 hardening: image-bomb defense — reject ridiculous pixel counts BEFORE decode
        $bgInfo = @getimagesize($tmpUpload);
        if ($bgInfo === false) {
            @unlink($tmpUpload);
            throw new \Cake\Http\Exception\BadRequestException('Background is not a valid image.');
        }
        if ($bgInfo[0] * $bgInfo[1] > 50_000_000) {
            @unlink($tmpUpload);
            throw new \Cake\Http\Exception\BadRequestException('Image dimensions exceed allowed limit.');
        }

        // Optimize once into a scratch path, then dedup by the POST-optimize hash.
        $optimizer = new \App\Service\ImageOptimizer(maxWidth: 2000, maxHeight: 1500, quality: 82);
        $tmpDest = tempnam(sys_get_temp_dir(), 'eqsl_opt_');
        $info = $optimizer->optimize($tmpUpload, $tmpDest);
        @unlink($tmpUpload);

        $sha = $info['sha256_hash'];
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

        $uploads = $this->fetchTable('Uploads');
        $upload = $uploads->find()->where(['sha256_hash' => $sha])->first();
        if (!$upload) {
            $upload = $uploads->saveOrFail($uploads->newEntity([
                'guest_visit_id' => $visit->id,
                'original_filename' => 'guest-upload.jpg',
                'storage_path' => 'files/uploads/' . $sha . '.jpg',
                'mime_type' => 'image/jpeg',
                'width_px' => $info['width_px'],
                'height_px' => $info['height_px'],
                'file_size_bytes' => $info['file_size_bytes'],
                'sha256_hash' => $sha,
            ]));
        }

        // Load the system template
        $template = $this->fetchTable('Templates')->find()->where(['is_system' => true])->firstOrFail();
        $layout = json_decode($template->layout_json, true);

        // Build QSO data
        $qso = $this->buildQsoData($data);

        // Render
        $renderer = new \App\Service\CardRenderer(WWW_ROOT . 'files/fonts/');
        $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $pngPath = WWW_ROOT . 'files/cards/' . $uuid . '.png';
        $pdfPath = WWW_ROOT . 'files/cards/' . $uuid . '.pdf';
        if (!is_dir(dirname($pngPath))) {
            mkdir(dirname($pngPath), 0o775, true);
        }
        $renderer->renderPng(
            ['canvas_width' => $template->canvas_width, 'canvas_height' => $template->canvas_height,
             'fields' => $layout['fields']],
            $finalPath, $qso, $pngPath
        );
        $renderer->wrapPdf($pngPath, $pdfPath, $template->canvas_width, $template->canvas_height);

        // Persist card
        $cards = $this->fetchTable('Cards');
        $card = $cards->saveOrFail($cards->newEntity([
            'guest_visit_id' => $visit->id,
            'template_id' => $template->id,
            'upload_id' => $upload->id,
            'qso_data_json' => json_encode($qso, JSON_UNESCAPED_SLASHES),
            'png_path' => 'files/cards/' . $uuid . '.png',
            'pdf_path' => 'files/cards/' . $uuid . '.pdf',
        ]));

        $this->set(['cardId' => $card->id, 'pngUrl' => '/' . $card->png_path, 'pdfUrl' => '/' . $card->pdf_path]);
        $this->render('preview');
        return null;
    }

    /**
     * Move an uploaded file or decode a base64 capture into a temp file
     * and return its path.
     */
    private function resolveBackground(array $data): string
    {
        $upload = $this->request->getUploadedFile('background_upload');
        if ($upload && $upload->getError() === UPLOAD_ERR_OK) {
            $tmp = tempnam(sys_get_temp_dir(), 'eqsl_');
            $upload->moveTo($tmp);
            return $tmp;
        }
        $capture = (string)($data['background_capture'] ?? '');
        if (str_starts_with($capture, 'data:image/')) {
            $blob = base64_decode((string)preg_replace('#^data:image/[^;]+;base64,#', '', $capture));
            $tmp = tempnam(sys_get_temp_dir(), 'eqsl_');
            file_put_contents($tmp, $blob);
            return $tmp;
        }
        throw new \Cake\Http\Exception\BadRequestException('Background image required.');
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
            'frequency_mhz'      => (string)($data['frequency_mhz'] ?? ''),
            'band'               => (string)($data['band'] ?? ''),
            'mode'               => (string)($data['mode'] ?? ''),
            'rst_sent'           => (string)($data['rst_sent'] ?? ''),
            'rst_received'       => (string)($data['rst_received'] ?? ''),
            'operator_name'      => (string)($data['operator_name'] ?? ''),
            'notes'              => (string)($data['notes'] ?? ''),
        ];
    }
}
