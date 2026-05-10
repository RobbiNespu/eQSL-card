<h1><?= h($title) ?></h1>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-3"><input type="search" name="q" value="<?= h($filters['search']) ?>" placeholder="Callsign" class="form-control"></div>
  <div class="col-md-2">
    <select name="band" class="form-select">
      <option value="">All bands</option>
      <?php foreach (\App\Service\HamRadio::bandOptions($filters['band']) as $b => $lbl): ?>
        <option value="<?= h($b) ?>"<?= $filters['band'] === $b ? ' selected' : '' ?>><?= h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select name="mode" class="form-select">
      <option value="">All modes</option>
      <?php foreach (\App\Service\HamRadio::modeOptions($filters['mode']) as $m => $lbl): ?>
        <option value="<?= h($m) ?>"<?= $filters['mode'] === $m ? ' selected' : '' ?>><?= h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><input type="date" name="from" value="<?= h($filters['from']) ?>" class="form-control"></div>
  <div class="col-md-2"><input type="date" name="to" value="<?= h($filters['to']) ?>" class="form-control"></div>
  <div class="col-md-1"><button class="btn btn-primary w-100">Filter</button></div>
</form>

<?php if ($qsos->count() === 0): ?>
  <div class="alert alert-info">No QSOs match your filter. <a href="/qsos/new">Add one</a> or <a href="/qsos/import">import an ADIF/CSV log</a>.</div>
<?php else: ?>
<div x-data="bulkRenderForm()">
  <div class="d-flex gap-2 mb-2 align-items-center">
    <span x-text="`${selected.length} selected`"></span>
    <button type="button" class="btn btn-sm btn-outline-primary" @click="openBulkModal()" x-bind:disabled="selected.length === 0">Bulk render selected</button>
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
        <td><strong><?= h($qso->call_worked) ?></strong></td>
        <td><?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?></td>
        <td><?= h($qso->frequency_mhz) ?></td>
        <td><?= h($qso->band) ?></td>
        <td><?= h($qso->mode) ?></td>
        <td><?= h($qso->rst_sent) ?> / <?= h($qso->rst_received) ?></td>
        <td>
          <a class="btn btn-sm btn-outline-secondary" href="/qsos/<?= $qso->id ?>">View</a>
          <a class="btn btn-sm btn-outline-primary" href="/qsos/<?= $qso->id ?>/render">Render</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <nav><?= $this->Paginator->numbers() ?></nav>

  <!-- Bulk render modal -->
  <div class="modal fade show" tabindex="-1" style="display: block; background: rgba(0,0,0,.5);"
       x-show="modalOpen" x-cloak>
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Bulk render eQSL cards</h5>
          <button type="button" class="btn-close" @click="closeModal()"></button>
        </div>
        <div class="modal-body">
          <template x-if="!started">
            <div>
              <p>Render <span x-text="selected.length"></span> cards using the same template + background.</p>
              <div class="mb-3">
                <label>Template ID</label>
                <input type="number" class="form-control" x-model="templateId" placeholder="System template ID">
              </div>
              <div class="mb-3">
                <label>Background upload ID</label>
                <input type="number" class="form-control" x-model="uploadId" placeholder="Existing upload ID">
              </div>
              <p class="small text-muted">Tip: M3 will replace these IDs with proper pickers.</p>
            </div>
          </template>
          <template x-if="started">
            <div>
              <p>Rendering <span x-text="done"></span> of <span x-text="total"></span>...</p>
              <div class="progress">
                <div class="progress-bar"
                     :style="`width: ${total ? (done * 100 / total) : 0}%`"
                     x-text="total ? Math.round(done * 100 / total) + '%' : '0%'"></div>
              </div>
              <p x-show="finished" class="mt-3 text-success">Done!
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
