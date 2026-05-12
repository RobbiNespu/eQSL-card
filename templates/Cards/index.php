<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => "Every eQSL you've generated. Click one to view, share, or download.",
]) ?>

<?php if ($cards->count() === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message'   => "You haven't generated any cards yet.",
      'cta_url'   => '/qsos',
      'cta_label' => 'Render one from a QSO',
  ]) ?>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($cards as $card): ?>
      <div class="col-md-4">
        <div class="card h-100">
          <a href="/cards/<?= $card->id ?>">
            <?= $this->element('ui/card_thumb', ['card' => $card]) ?>
          </a>
          <div class="card-body">
            <?php $qsoData = json_decode((string)$card->qso_data_json, true) ?: []; ?>
            <h5 class="card-title mb-1"><?= h($qsoData['callsign'] ?? '—') ?></h5>
            <p class="card-text mb-2">
              <?= h($qsoData['qso_datetime_utc'] ?? '') ?>
              · <?= h($qsoData['band'] ?? '') ?>
              · <?= h($qsoData['mode'] ?? '') ?>
            </p>
            <?= $this->element('ui/badge_share_status', ['card' => $card]) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <nav class="mt-4"><?= $this->Paginator->numbers() ?></nav>
<?php endif; ?>
