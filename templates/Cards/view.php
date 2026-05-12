<h1><?= h($title) ?></h1>
<p>Preview, share, or download this eQSL card.</p>

<div class="row g-4">
  <div class="col-md-8">
    <img src="/<?= h($card->png_path) ?>" alt="eQSL card" class="card-preview">

    <div class="d-flex gap-2 mt-3">
      <a class="btn btn-primary" href="/<?= h($card->png_path) ?>" download>Download image</a>
      <a class="btn btn-secondary" href="/cards/<?= h($card->id) ?>/download.pdf">Download PDF</a>
      <a class="btn btn-link" href="/cards">Back to library</a>
    </div>
  </div>

  <div class="col-md-4">
    <h2 class="h5">QSO details</h2>
    <dl class="row dl-stack">
      <dt class="col-sm-5">Callsign</dt><dd class="col-sm-7"><?= h($qso['callsign'] ?? '—') ?></dd>
      <dt class="col-sm-5">Date / Time UTC</dt><dd class="col-sm-7"><?= h($qso['qso_datetime_utc'] ?? '') ?></dd>
      <dt class="col-sm-5">Frequency</dt><dd class="col-sm-7"><?= h($qso['frequency_mhz'] ?? '') ?> MHz</dd>
      <dt class="col-sm-5">Band</dt><dd class="col-sm-7"><?= h($qso['band'] ?? '') ?></dd>
      <dt class="col-sm-5">Mode</dt><dd class="col-sm-7"><?= h($qso['mode'] ?? '') ?></dd>
      <dt class="col-sm-5">RST sent / received</dt><dd class="col-sm-7"><?= h($qso['rst_sent'] ?? '') ?> / <?= h($qso['rst_received'] ?? '') ?></dd>
      <?php if (!empty($qso['operator_name'])): ?>
        <dt class="col-sm-5">Operator name</dt><dd class="col-sm-7"><?= h($qso['operator_name']) ?></dd>
      <?php endif; ?>
      <?php if (!empty($qso['notes'])): ?>
        <dt class="col-sm-5">Notes</dt><dd class="col-sm-7"><?= nl2br(h($qso['notes'])) ?></dd>
      <?php endif; ?>
    </dl>

    <hr>
    <h2 class="h5">Sharing</h2>
    <?php if ($shareUrl): ?>
      <p class="form-text mb-2">Public link:</p>
      <p><code style="word-break: break-all"><?= h($shareUrl) ?></code></p>
      <?= $this->Form->postLink('Revoke share', '/cards/' . $card->id . '/revoke', [
          'class' => 'btn btn-outline-danger btn-sm',
          'confirm' => 'Are you sure? The public link will return 410 Gone.',
      ]) ?>
    <?php elseif ($card->share_revoked_at): ?>
      <p class="form-text">Share was revoked on <?= h($card->share_revoked_at?->format('Y-m-d H:i')) ?>.</p>
      <p class="form-text">A future "re-share" action lands in M3 polish.</p>
    <?php else: ?>
      <p class="form-text mb-2">This card is private. Generate a public link below.</p>
      <?= $this->Form->create(null, ['url' => '/cards/' . $card->id . '/share']) ?>
        <div class="field">
          <label class="form-label" for="share_password">Optional password</label>
          <input type="password" id="share_password" name="password" class="form-control"
                 autocomplete="new-password" placeholder="Leave blank for unprotected">
        </div>
        <button class="btn btn-primary btn-sm">Share</button>
      <?= $this->Form->end() ?>
    <?php endif; ?>

    <hr>
    <?= $this->Form->postLink('Delete card', '/cards/' . $card->id . '/delete', [
        'class' => 'btn btn-outline-danger btn-sm',
        'confirm' => 'Permanently delete this card? This cannot be undone.',
    ]) ?>
  </div>
</div>
