<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Every background image on the site, owned by users or guests. Edit attribution or soft-delete from here.',
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

<?php if ($uploads->count() === 0): ?>
  <?= $this->element('ui/empty_state', ['message' => 'No uploads match your filter.']) ?>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Preview</th>
        <th>Owner</th>
        <th>Author / License</th>
        <th>Size</th>
        <th>Created</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($uploads as $u): ?>
        <tr<?= $u->deleted_at ? ' class="table-warning"' : '' ?>>
          <td><img src="/<?= h($u->storage_path) ?>" alt="" loading="lazy" style="height: 60px; width: 90px; object-fit: cover"></td>
          <td>
            <?php if ($u->user_id): ?>
              <strong><?= h($u->user->callsign ?? '?') ?></strong>
              <span class="text-muted small d-block"><?= h($u->user->email ?? '') ?></span>
            <?php elseif ($u->guest_visit_id): ?>
              <span class="badge bg-secondary">Guest</span>
              <span class="text-muted small">visit #<?= $u->guest_visit_id ?></span>
            <?php else: ?>
              <span class="text-muted">orphan</span>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= h($u->author_name ?: 'unknown source') ?></strong>
            <span class="text-muted small d-block"><?= h(\App\Service\ImageLicense::label($u->license)) ?></span>
          </td>
          <td class="small text-muted">
            <?= h($u->width_px) ?>×<?= h($u->height_px) ?><br>
            <?= h(round($u->file_size_bytes / 1024)) ?> KB
          </td>
          <td class="small text-muted">
            <?= h($u->created_at?->format('Y-m-d H:i')) ?>
            <?php if ($u->deleted_at): ?>
              <br><span class="badge bg-danger">deleted <?= h($u->deleted_at?->format('Y-m-d')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$u->deleted_at): ?>
              <a class="btn btn-sm btn-outline-primary" href="/uploads/<?= $u->id ?>/edit?return=/admin/uploads">Edit</a>
              <?= $this->Form->postLink('Delete', '/uploads/' . $u->id . '/delete?return=/admin/uploads', [
                  'class' => 'btn btn-sm btn-outline-danger',
                  'confirm' => 'Soft-delete this upload?',
              ]) ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <nav><?= $this->Paginator->numbers() ?></nav>
<?php endif; ?>
