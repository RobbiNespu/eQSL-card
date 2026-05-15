<h1><?= h($title) ?></h1>
<p class="form-text">
  <a href="/admin/callsign-lookups">&larr; Back to callsign auto-complete</a>
</p>

<p class="text-muted">
  Combined view across the admin-curated local directory and the auto-fetched
  external cache (<code>UNION ALL</code> across both tables). A callsign that
  exists in both stores will appear twice, with different source badges, so
  duplicates are visible at a glance instead of being silently merged.
</p>

<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="card p-2 text-center">
      <p class="display-6 mb-0"><?= h($directoryCount) ?></p>
      <p class="text-muted small mb-0">From local directory</p>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card p-2 text-center">
      <p class="display-6 mb-0"><?= h($cacheCount) ?></p>
      <p class="text-muted small mb-2">From external cache</p>
      <?php if ($cacheCount > 0): ?>
        <?= $this->Form->postLink('Clear external cache', '/admin/callsign-lookups/clear', [
            'class'   => 'btn btn-outline-danger btn-sm',
            'confirm' => 'Delete every cached row? The chain will re-fetch on demand. QSO history is untouched.',
        ]) ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card p-2 text-center">
      <p class="display-6 mb-0"><?= h($totalCount) ?></p>
      <p class="text-muted small mb-0">Total rows (with duplicates)</p>
    </div>
  </div>
</div>

<form method="get" class="mb-3" action="/admin/callsign-lookups/all">
  <div class="input-group" style="max-width: 360px;">
    <input type="search" name="q" value="<?= h($q) ?>" class="form-control form-control-sm"
           placeholder="Search callsign…" autocapitalize="characters">
    <button class="btn btn-secondary btn-sm">Search</button>
    <?php if ($q !== ''): ?>
      <a href="/admin/callsign-lookups/all" class="btn btn-outline-secondary btn-sm">Clear</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($totalCount === 0): ?>
  <?= $this->element('ui/empty_state', [
      'message' => $q !== ''
          ? 'No callsigns match that search across either store.'
          : 'Nothing here yet. Upload a CSV via the local directory, or let the QSO form populate the external cache as users look callsigns up.',
  ]) ?>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Callsign</th>
          <th>Source</th>
          <th>Name</th>
          <th>QTH</th>
          <th>Country</th>
          <th>Grid</th>
          <th>Class</th>
          <th>Updated</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($callsigns as $row): ?>
          <tr>
            <td><strong><?= h($row['callsign']) ?></strong></td>
            <td>
              <?php if ($row['source_type'] === 'directory'): ?>
                <span class="badge bg-info" title="Admin-curated CSV entry">Directory</span>
                <?php if (!empty($row['source_detail'])): ?>
                  <span class="text-muted small">· <?= h($row['source_detail']) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge bg-secondary" title="Auto-fetched from external provider">Cache</span>
                <code class="small">· <?= h($row['source_detail']) ?></code>
              <?php endif; ?>
            </td>
            <td><?= h($row['name'] ?? '—') ?></td>
            <td><?= h($row['qth'] ?? '—') ?></td>
            <td><?= h($row['country'] ?? '—') ?></td>
            <td><code><?= h($row['grid_square'] ?? '') ?></code></td>
            <td><?= h($row['license_class'] ?? '—') ?></td>
            <td class="small text-muted">
              <?php if ($row['updated_at'] instanceof \DateTimeInterface): ?>
                <?= h($row['updated_at']->format('Y-m-d')) ?>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($row['source_type'] === 'directory'): ?>
                <a class="btn btn-outline-primary btn-sm"
                   href="/admin/callsign-lookups/provider/local?q=<?= h($row['callsign']) ?>">Manage</a>
              <?php else: ?>
                <a class="btn btn-outline-primary btn-sm"
                   href="/admin/callsign-lookups/<?= h($row['id']) ?>/edit">Edit cache</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="d-flex gap-2 align-items-center">
      <?php if ($page > 1): ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="/admin/callsign-lookups/all?<?= http_build_query(array_filter(['q' => $q, 'page' => $page - 1])) ?>">&larr; Prev</a>
      <?php endif; ?>
      <span class="text-muted small">Page <?= h($page) ?> of <?= h($totalPages) ?></span>
      <?php if ($page < $totalPages): ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="/admin/callsign-lookups/all?<?= http_build_query(array_filter(['q' => $q, 'page' => $page + 1])) ?>">Next &rarr;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
