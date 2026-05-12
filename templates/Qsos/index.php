<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Your station log. Filter by callsign, type, band, or date range, then render eQSL cards individually or in bulk.',
]) ?>

<div class="d-flex gap-2 mb-3 flex-wrap">
  <a class="btn btn-primary" href="/qsos/new">+ New QSO</a>
  <a class="btn btn-secondary" href="/qsos/import">Import ADIF / CSV</a>
</div>

<form method="get" class="row g-2 mb-4">
  <div class="col-md-3 col-sm-6">
    <input type="search" name="q" value="<?= h($filters['search']) ?>"
           placeholder="Search callsign" class="form-control">
  </div>
  <div class="col-md-2 col-sm-6">
    <select name="qso_type" class="form-select">
      <option value=""<?= $filters['qsoType'] === '' ? ' selected' : '' ?>>All types</option>
      <option value="contact"<?= $filters['qsoType'] === 'contact' ? ' selected' : '' ?>>Contact</option>
      <option value="net"<?= $filters['qsoType'] === 'net' ? ' selected' : '' ?>>Net</option>
    </select>
  </div>
  <div class="col-md-2 col-sm-6">
    <select name="transport" class="form-select">
      <option value=""<?= $filters['transport'] === '' ? ' selected' : '' ?>>All transports</option>
      <option value="rf"<?= $filters['transport'] === 'rf' ? ' selected' : '' ?>>RF only</option>
      <option value="internet"<?= $filters['transport'] === 'internet' ? ' selected' : '' ?>>Internet only</option>
      <?php foreach (\App\Service\Transport::TRANSPORTS as $code => $label): ?>
        <?php if ($code === 'rf') continue; ?>
        <option value="<?= h($code) ?>"<?= $filters['transport'] === $code ? ' selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2 col-sm-6">
    <select name="band" class="form-select">
      <option value="">All bands</option>
      <?php foreach (\App\Service\HamRadio::bandOptions($filters['band']) as $b => $lbl): ?>
        <option value="<?= h($b) ?>"<?= $filters['band'] === $b ? ' selected' : '' ?>><?= h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-1 col-sm-6">
    <select name="mode" class="form-select">
      <option value="">Modes</option>
      <?php foreach (\App\Service\HamRadio::modeOptions($filters['mode']) as $m => $lbl): ?>
        <option value="<?= h($m) ?>"<?= $filters['mode'] === $m ? ' selected' : '' ?>><?= h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2 col-sm-6">
    <input type="date" name="from" value="<?= h($filters['from']) ?>" class="form-control" title="From date">
  </div>
  <div class="col-md-2 col-sm-6">
    <input type="date" name="to" value="<?= h($filters['to']) ?>" class="form-control" title="To date">
  </div>
  <div class="col-md-2 col-sm-12">
    <button class="btn btn-primary w-100">Filter</button>
  </div>
</form>

<?php if ($qsos->count() === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message'   => 'No QSOs match your filter.',
      'cta_url'   => '/qsos/new',
      'cta_label' => 'Add one',
  ]) ?>
<?php else: ?>
<div x-data="bulkRenderForm()">
  <div class="d-flex gap-2 mb-3 align-items-center">
    <span class="form-text" x-text="`${selected.length} selected`"></span>
    <button type="button" class="btn btn-sm btn-outline-primary"
            @click="openBulkModal()" x-bind:disabled="selected.length === 0">Bulk render selected</button>
  </div>

  <table class="table table-striped">
    <thead>
      <tr>
        <th><input type="checkbox" @change="toggleAll($event.target.checked)"></th>
        <th>Callsign</th>
        <th>Date/Time UTC</th>
        <th>Freq</th>
        <th>Band</th>
        <th>Mode</th>
        <th>RST sent / recv</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($qsos as $qso): ?>
      <tr>
        <td><input type="checkbox" :value="<?= $qso->id ?>" @change="toggleOne(<?= $qso->id ?>, $event.target.checked)"></td>
        <td>
          <strong><?= h($qso->call_worked) ?></strong>
          <?= $this->element('ui/badge_qso_type', ['qso' => $qso]) ?>
          <?= $this->element('ui/badge_transport', ['qso' => $qso]) ?>
        </td>
        <td><?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?></td>
        <td><?= h($qso->frequency_mhz) ?></td>
        <td><?= h($qso->band) ?></td>
        <td><?= h($qso->mode) ?></td>
        <td><?= h($qso->rst_sent) ?> / <?= h($qso->rst_received) ?></td>
        <td>
          <a class="btn btn-sm btn-outline-secondary" href="/qsos/<?= $qso->id ?>">View</a>
          <?php if (isset($activeCardByQso[$qso->id])): ?>
            <a class="btn btn-sm btn-outline-success" href="/cards/<?= $activeCardByQso[$qso->id] ?>" title="A card has already been rendered for this QSO. Delete it first to render a new one.">View card</a>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-primary" href="/qsos/<?= $qso->id ?>/render">Render</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php
  // Carry filters across page links. CakePHP's PaginatorHelper picks up
  // current request query params by default but the URL builder needs to be
  // told explicitly when we want only-non-empty filters in the link so the
  // URL doesn't grow `?q=&band=&...` noise.
  $paginatorOptions = ['url' => ['?' => array_filter([
      'q' => $filters['search'] ?: null,
      'band' => $filters['band'] ?: null,
      'mode' => $filters['mode'] ?: null,
      'from' => $filters['from'] ?: null,
      'to' => $filters['to'] ?: null,
      'qso_type' => $filters['qsoType'] ?: null,
      'transport' => $filters['transport'] ?: null,
  ])]];
  ?>
  <nav><?= $this->Paginator->numbers($paginatorOptions) ?></nav>

  <!-- Bulk render modal: Alpine fully manages visibility (no Bootstrap .show class
       — that has display:block !important which would make the modal un-closable). -->
  <div x-show="modalOpen" x-cloak tabindex="-1"
       style="position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1050; overflow-y: auto;">
    <div class="modal-dialog" style="margin: 5rem auto;">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Bulk render eQSL cards</h5>
          <button type="button" class="btn-close" @click="closeModal()" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <template x-if="!started">
            <div>
              <p>Render <span x-text="selected.length"></span> cards using the same template + background.</p>
              <div class="mb-3">
                <label class="form-label">Template</label>
                <select class="form-select" x-model="templateId">
                  <option value="">— pick a template —</option>
                  <?php foreach ($availableTemplates as $t): ?>
                    <option value="<?= $t->id ?>"><?= h($t->name) ?><?= $t->is_system ? ' (system)' : '' ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Background</label>
                <select class="form-select" x-model="uploadId">
                  <?php if ($userUploads->count() > 0): ?>
                    <option value="">— pick an existing upload —</option>
                    <?php foreach ($userUploads as $u): ?>
                      <option value="<?= $u->id ?>"><?= h($u->original_filename ?: 'upload #' . $u->id) ?> · <?= h(round($u->file_size_bytes / 1024)) ?> KB</option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="">No uploads yet — upload one via the single-render flow first</option>
                  <?php endif; ?>
                </select>
                <p class="form-text small">Use <a href="/qsos/<?= $qsos->first()->id ?? '' ?>/render">single-render</a> to upload a new background — it'll then appear in this list.</p>
              </div>
            </div>
          </template>
          <template x-if="started">
            <div role="status" aria-live="polite">
              <p>Rendering <span x-text="done"></span> of <span x-text="total"></span>...</p>
              <div class="progress">
                <div class="progress-bar"
                     :style="`width: ${total ? (done * 100 / total) : 0}%`"
                     x-text="total ? Math.round(done * 100 / total) + '%' : '0%'"></div>
              </div>
              <p x-show="skipped > 0" class="mt-3 text-warning small" x-cloak>
                Skipped <span x-text="skipped"></span> QSO<span x-show="skipped !== 1">s</span> that already had a rendered card.
                Delete the existing card from <a href="/cards">your library</a> to re-render.
              </p>
              <p x-show="finished" class="mt-3 text-success" x-cloak>
                <span x-text="message || 'Done!'"></span>
                <a href="/cards" class="btn btn-sm btn-primary">View library</a>
              </p>
            </div>
          </template>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeModal()" x-text="finished ? 'Close' : 'Cancel'"></button>
          <button class="btn btn-primary" @click="startBulk()" x-show="!started" x-bind:disabled="!templateId || !uploadId">Start render</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
