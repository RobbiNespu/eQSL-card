<h1><?= h($title) ?></h1>
<p>Every eQSL you've generated. Click one to view, share, or download.</p>

<?php if ($cards->count() === 0): ?>
  <div class="alert alert-info">You haven't generated any cards yet. <a href="/qsos">Render one from a QSO &rarr;</a></div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($cards as $card): ?>
      <div class="col-md-4">
        <div class="card h-100">
          <?php
          // Thumbnails are written alongside the full card as <uuid>.thumb.webp.
          // Old cards rendered before this commit don't have one — fall back
          // to the full image rather than a broken <img>.
          $thumbPath = \App\Service\CardRenderer::thumbPathFor($card->png_path);
          $thumbAbs = WWW_ROOT . $thumbPath;
          $previewSrc = is_file($thumbAbs) ? $thumbPath : $card->png_path;
          ?>
          <a href="/cards/<?= $card->id ?>">
            <img src="/<?= h($previewSrc) ?>" class="card-img-top" alt="eQSL card preview" loading="lazy">
          </a>
          <div class="card-body">
            <?php $qsoData = json_decode((string)$card->qso_data_json, true) ?: []; ?>
            <h5 class="card-title mb-1"><?= h($qsoData['callsign'] ?? '—') ?></h5>
            <p class="card-text mb-2">
              <?= h($qsoData['qso_datetime_utc'] ?? '') ?>
              · <?= h($qsoData['band'] ?? '') ?>
              · <?= h($qsoData['mode'] ?? '') ?>
            </p>
            <?php if ($card->share_revoked_at): ?>
              <span class="badge bg-secondary">Share revoked</span>
            <?php elseif ($card->share_slug): ?>
              <span class="badge bg-success">Shared</span>
            <?php else: ?>
              <span class="badge bg-light">Private</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <nav class="mt-4"><?= $this->Paginator->numbers() ?></nav>
<?php endif; ?>
