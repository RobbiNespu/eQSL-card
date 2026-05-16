<?php
/*
 * M5 T14 — Activations list + inline "start new" form.
 *
 * Layout:
 *   1. "Active right now" — either the open activation with an End button,
 *      or a friendly empty state ("No active activation").
 *   2. "Start a new activation" — inline form (no separate /new page).
 *   3. "Recent activations" — list with edit/delete per row.
 */
?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Group consecutive QSOs into named portable sessions — POTA, SOTA, IOTA, field day, kampung activation. Every QSO you log on /qsos/quick while an activation is active gets tagged with it automatically.',
]) ?>

<!-- ============ Active activation banner ============ -->
<section class="activations__active mb-4" aria-label="Currently active activation">
  <?php if ($active !== null): ?>
    <div class="card border-success">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <span class="badge bg-success mb-2">Active</span>
            <h2 class="h5 mb-1"><?= h($active->name) ?></h2>
            <p class="mb-1 small">
              <code><?= h($active->code) ?></code>
              <?php if ($active->grid_square): ?>
                · <span class="text-muted">Grid:</span> <code><?= h($active->grid_square) ?></code>
              <?php endif; ?>
            </p>
            <p class="small text-muted mb-0">
              Started <?= h($active->started_at?->format('Y-m-d H:i')) ?> UTC
            </p>
            <?php if ($active->notes): ?>
              <p class="small mb-0 mt-1"><?= nl2br(h($active->notes)) ?></p>
            <?php endif; ?>
          </div>
          <div class="d-flex flex-column gap-2">
            <?= $this->Form->postLink('End now', '/activations/' . $active->id . '/end', [
                'class' => 'btn btn-outline-danger btn-sm',
                'confirm' => 'End "' . $active->name . '"? New QSOs after this won\'t auto-tag with this activation.',
            ]) ?>
            <a class="btn btn-outline-secondary btn-sm" href="/activations/<?= $active->id ?>/edit">Edit</a>
            <a class="btn btn-outline-primary btn-sm" href="/activations/<?= $active->id ?>/export.adi">Export ADIF</a>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <p class="form-text">
      <strong>No active activation right now.</strong>
      New QSOs you log via <a href="/qsos/quick">Quick add</a> won't be grouped until you start one below.
    </p>
  <?php endif; ?>
</section>

<!-- ============ Start new activation form ============ -->
<section class="activations__new mb-4" aria-label="Start a new activation"
         x-data="activationGpsHelper()">
  <h2 class="h5">Start a new activation</h2>
  <?= $this->Form->create($newActivation, ['url' => '/activations']) ?>
    <div class="row g-2">
      <div class="col-md-4">
        <div class="field">
          <label class="form-label" for="act-code">Code <span class="req">*</span></label>
          <input type="text" id="act-code" name="code" class="form-control"
                 value="<?= h($newActivation->code ?? '') ?>"
                 placeholder="e.g. POTA-K-1234, SOTA-9M2/PR-001"
                 autocomplete="off" required>
        </div>
      </div>
      <div class="col-md-5">
        <div class="field">
          <label class="form-label" for="act-name">Name <span class="req">*</span></label>
          <input type="text" id="act-name" name="name" class="form-control"
                 value="<?= h($newActivation->name ?? '') ?>"
                 placeholder="e.g. Bukit Larut SOTA"
                 autocomplete="off" required>
        </div>
      </div>
      <div class="col-md-3">
        <div class="field">
          <label class="form-label" for="act-grid">Grid <span class="form-label small">(optional)</span></label>
          <div class="input-group">
            <input type="text" id="act-grid" name="grid_square" class="form-control"
                   x-ref="gridInput"
                   value="<?= h($newActivation->grid_square ?? '') ?>"
                   placeholder="OJ02wx" autocomplete="off"
                   maxlength="8" pattern="[A-Ra-r]{2}[0-9]{2}([A-Xa-x]{2})?">
            <?php /* T15 — Use my location button. Browser Geolocation API → Maidenhead. */ ?>
            <button type="button" class="btn btn-outline-secondary"
                    @click="fillGridFromGps()"
                    :disabled="gpsState === 'asking'"
                    title="Use my current GPS location">
              <span x-show="gpsState !== 'asking'" aria-hidden="true">📍</span>
              <span x-show="gpsState === 'asking'" aria-hidden="true">⏳</span>
              <span class="visually-hidden">Use my location</span>
            </button>
          </div>
          <p class="form-text small mb-0" x-show="!gpsMessage">Maidenhead 4 or 6 char.</p>
          <p class="form-text small mb-0"
             x-show="gpsMessage" x-cloak
             :class="{ 'text-success': gpsState === 'ok', 'text-danger': gpsState === 'denied' || gpsState === 'error', 'text-muted': gpsState === 'asking' }"
             x-text="gpsMessage" role="status" aria-live="polite"></p>
        </div>
      </div>
      <div class="col-12">
        <div class="field">
          <label class="form-label" for="act-notes">Notes <span class="form-label small">(optional)</span></label>
          <input type="text" id="act-notes" name="notes" class="form-control"
                 value="<?= h($newActivation->notes ?? '') ?>"
                 placeholder="Anything memorable about this session" autocomplete="off">
        </div>
      </div>
    </div>
    <?php if ($newActivation->getErrors()): ?>
      <div class="alert alert-danger mt-2">
        <ul class="mb-0">
          <?php foreach ($newActivation->getErrors() as $field => $fieldErrors): ?>
            <?php foreach ($fieldErrors as $msg): ?>
              <li><strong><?= h($field) ?>:</strong> <?= h($msg) ?></li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <div class="form-actions-mobile mt-3">
      <button class="btn btn-primary">Start activation</button>
    </div>
  <?= $this->Form->end() ?>
</section>

<!-- ============ Recent activations list ============ -->
<section class="activations__recent" aria-label="Recent activations">
  <h2 class="h5">Recent activations</h2>
  <?php if ($recent->count() === 0): ?>
    <p class="form-text">No activations yet. Start one above to begin grouping QSOs.</p>
  <?php else: ?>
    <table class="table table-responsive-stack">
      <thead>
        <tr>
          <th>Name</th>
          <th>Code</th>
          <th>Grid</th>
          <th>Started</th>
          <th>Ended</th>
          <th>QSOs</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $a): ?>
          <tr>
            <td data-label="Name">
              <strong><?= h($a->name) ?></strong>
              <?php if ($a->isActive()): ?>
                <span class="badge bg-success ms-1">Active</span>
              <?php endif; ?>
            </td>
            <td data-label="Code"><code><?= h($a->code) ?></code></td>
            <td data-label="Grid"><?= h($a->grid_square ?: '—') ?></td>
            <td data-label="Started">
              <span class="small text-muted"><?= h($a->started_at?->format('Y-m-d H:i')) ?></span>
            </td>
            <td data-label="Ended">
              <span class="small text-muted">
                <?= $a->ended_at ? h($a->ended_at->format('Y-m-d H:i')) : '—' ?>
              </span>
            </td>
            <td data-label="QSOs">
              <a class="btn btn-sm btn-outline-secondary" href="/activations/<?= $a->id ?>/edit">View / Edit</a>
            </td>
            <td data-label="Actions" class="table-responsive-stack__actions">
              <a class="btn btn-sm btn-outline-primary" href="/activations/<?= $a->id ?>/export.adi" title="Download ADIF for upload to POTA / SOTA / LoTW">ADIF</a>
              <?php if ($a->isActive()): ?>
                <?= $this->Form->postLink('End', '/activations/' . $a->id . '/end', [
                    'class' => 'btn btn-sm btn-outline-warning',
                    'confirm' => 'End "' . $a->name . '"?',
                ]) ?>
              <?php endif; ?>
              <?= $this->Form->postLink('Delete', '/activations/' . $a->id . '/delete', [
                  'class' => 'btn btn-sm btn-outline-danger',
                  'confirm' => 'Delete "' . $a->name . '"? QSOs logged under it stay in your logbook (just untagged).',
              ]) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
