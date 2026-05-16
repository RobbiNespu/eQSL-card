<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Every background image on the site, owned by users or guests. Edit attribution, see which templates each one is bound to, or soft-delete from here.',
]) ?>

<form method="get" class="row g-2 mb-4">
  <div class="col-md-2">
    <select name="kind" class="form-select">
      <option value="">All owners</option>
      <option value="user" <?= $filters['kind'] === 'user' ? 'selected' : '' ?>>Logged-in users</option>
      <option value="guest" <?= $filters['kind'] === 'guest' ? 'selected' : '' ?>>Guest visits</option>
    </select>
  </div>
  <div class="col-md-3 form-check ms-3 mt-2">
    <input class="form-check-input" type="checkbox" name="include_deleted" value="1" id="includeDeleted"
           <?= $filters['includeDeleted'] ? 'checked' : '' ?>>
    <label class="form-check-label" for="includeDeleted">Include soft-deleted</label>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
</form>

<?php if ($backgrounds->count() === 0): ?>
  <?= $this->element('ui/empty_state', ['message' => 'No backgrounds match your filter.']) ?>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Preview</th>
        <th>Owner</th>
        <th>Author / License</th>
        <th>Used by template</th>
        <th>Size</th>
        <th>Created</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($backgrounds as $bg): ?>
        <tr<?= $bg->deleted_at ? ' class="table-warning"' : '' ?>>
          <td><img src="/<?= h($bg->storage_path) ?>" alt="" loading="lazy" style="height: 60px; width: 90px; object-fit: cover"></td>
          <td>
            <?php if ($bg->user_id): ?>
              <strong><?= h($bg->user->callsign ?? '?') ?></strong>
              <span class="text-muted small d-block"><?= h($bg->user->email ?? '') ?></span>
            <?php elseif ($bg->guest_visit_id): ?>
              <span class="badge bg-secondary">Guest</span>
              <span class="text-muted small">visit #<?= $bg->guest_visit_id ?></span>
            <?php else: ?>
              <span class="text-muted">orphan</span>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= h($bg->author_name ?: 'unknown source') ?></strong>
            <span class="text-muted small d-block"><?= h(\App\Service\ImageLicense::label($bg->license)) ?></span>
            <?php if (!empty($defaultBgUploadId) && (int)$bg->id === $defaultBgUploadId): ?>
              <span class="badge bg-success mt-1"
                    title="This image is the current site-default background. Configure on /admin/settings.">
                Default background
              </span>
            <?php endif; ?>
          </td>
          <td class="small">
            <?php $usedByThis = $usedBy[(int)$bg->id] ?? []; ?>
            <?php if (!empty($usedByThis)): ?>
              <?php foreach ($usedByThis as $tpl): ?>
                <a class="d-block" href="/templates/<?= h($tpl->id) ?>/edit"><?= h($tpl->name) ?></a>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-muted"><em>none</em></span>
            <?php endif; ?>
          </td>
          <td class="small text-muted">
            <?= h($bg->width_px) ?>×<?= h($bg->height_px) ?><br>
            <?= h(round($bg->file_size_bytes / 1024)) ?> KB
          </td>
          <td class="small text-muted">
            <?= h($bg->created_at?->format('Y-m-d H:i')) ?>
            <?php if ($bg->deleted_at): ?>
              <br><span class="badge bg-danger">deleted <?= h($bg->deleted_at?->format('Y-m-d')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$bg->deleted_at): ?>
              <a class="btn btn-sm btn-outline-primary" href="/card-backgrounds/<?= $bg->id ?>/edit?return=/admin/card-backgrounds">Edit</a>
              <?= $this->Form->postLink('Delete', '/card-backgrounds/' . $bg->id . '/delete?return=/admin/card-backgrounds', [
                  'class' => 'btn btn-sm btn-outline-danger',
                  'confirm' => 'Soft-delete this background?',
              ]) ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <nav><?= $this->Paginator->numbers() ?></nav>
<?php endif; ?>
