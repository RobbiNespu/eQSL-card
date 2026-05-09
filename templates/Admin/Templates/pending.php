<h1><?= h($title) ?></h1>

<?php if ($pending->count() === 0): ?>
  <div class="alert alert-info">No templates awaiting review.</div>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Submitted by</th>
        <th>Submitted at</th>
        <th>Preview</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pending as $t): ?>
        <tr>
          <td>
            <strong><?= h($t->name) ?></strong>
            <p class="small text-muted mb-0"><?= h($t->description) ?></p>
          </td>
          <td><?= h($t->user->callsign ?? '?') ?> <span class="text-muted small">(<?= h($t->user->email ?? '') ?>)</span></td>
          <td><?= h($t->created_at?->format('Y-m-d H:i')) ?></td>
          <td>
            <?php if ($t->thumbnail_path): ?>
              <img src="/<?= h($t->thumbnail_path) ?>" style="max-width: 200px" alt="" loading="lazy">
            <?php else: ?>
              <span class="text-muted small">No thumbnail</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $this->Form->postLink('Approve', '/admin/templates/' . $t->id . '/approve', [
                'class' => 'btn btn-sm btn-success',
                'confirm' => 'Approve this template for the public gallery?',
            ]) ?>
            <?= $this->Form->create(null, ['url' => '/admin/templates/' . $t->id . '/reject', 'class' => 'd-inline-block mt-1']) ?>
              <input type="text" name="reason" class="form-control form-control-sm" placeholder="Rejection reason (optional)" style="width: 220px; display: inline-block">
              <button class="btn btn-sm btn-outline-danger">Reject</button>
            <?= $this->Form->end() ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
