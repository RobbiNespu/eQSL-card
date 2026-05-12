<h1><?= h($title) ?></h1>
<p>User-submitted public templates that need moderation. Approving makes them visible in the public gallery; rejecting hides them with an optional reason.</p>

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
            <p class="form-text mb-0"><?= h($t->description) ?></p>
          </td>
          <td>
            <span class="callsign"><?= h($t->user->callsign ?? '?') ?></span>
            <span class="form-text d-block"><?= h($t->user->email ?? '') ?></span>
          </td>
          <td><?= h($t->created_at?->format('Y-m-d H:i')) ?></td>
          <td>
            <?php if ($t->thumbnail_path): ?>
              <img src="/<?= h($t->thumbnail_path) ?>" class="rounded" style="max-width: 200px" alt="" loading="lazy">
            <?php else: ?>
              <span class="form-text">No thumbnail</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $this->Form->postLink('Approve', '/admin/templates/' . $t->id . '/approve', [
                'class' => 'btn btn-sm btn-outline-success',
                'confirm' => 'Approve this template for the public gallery?',
            ]) ?>
            <?= $this->Form->create(null, ['url' => '/admin/templates/' . $t->id . '/reject', 'class' => 'd-flex gap-1 mt-2', 'style' => 'max-width: 280px;']) ?>
              <input type="text" name="reason" class="form-control form-control-sm" placeholder="Rejection reason (optional)">
              <button class="btn btn-sm btn-outline-danger">Reject</button>
            <?= $this->Form->end() ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
