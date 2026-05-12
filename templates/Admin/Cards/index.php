<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Every card ever rendered — by users and by guests. Filter by owner type or date.',
]) ?>

<form method="get" class="row g-2 mb-4">
  <div class="col-md-2">
    <select name="kind" class="form-select">
      <option value="">All</option>
      <option value="user" <?= $filters['kind'] === 'user' ? 'selected' : '' ?>>Logged-in users</option>
      <option value="guest" <?= $filters['kind'] === 'guest' ? 'selected' : '' ?>>Guests</option>
    </select>
  </div>
  <div class="col-md-2"><input type="date" name="from" value="<?= h($filters['from']) ?>" class="form-control"></div>
  <div class="col-md-2"><input type="date" name="to" value="<?= h($filters['to']) ?>" class="form-control"></div>
  <div class="col-md-3 form-check ms-3 mt-2">
    <input class="form-check-input" type="checkbox" name="include_deleted" value="1" id="includeDeleted" <?= $filters['includeDeleted'] ? 'checked' : '' ?>>
    <label class="form-check-label" for="includeDeleted">Include soft-deleted</label>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
</form>

<table class="table">
  <thead><tr><th>ID</th><th>Owner</th><th>Template</th><th>Created</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($cards as $c): ?>
      <tr>
        <td>#<?= $c->id ?></td>
        <td>
          <?php if ($c->user_id): ?>
            <?= h($c->user->callsign ?? '?') ?> <span class="text-muted small">(<?= h($c->user->email ?? '') ?>)</span>
          <?php elseif ($c->guest_visit_id): ?>
            <span class="badge bg-secondary">Guest</span>
            <span class="text-muted small">visit #<?= $c->guest_visit_id ?></span>
          <?php endif; ?>
        </td>
        <td><?= h($c->template->name ?? '?') ?></td>
        <td><?= h($c->created_at?->format('Y-m-d H:i')) ?></td>
        <td>
          <?php if ($c->deleted_at): ?>
            <span class="badge bg-danger">deleted</span>
          <?php else: ?>
            <?= $this->element('ui/badge_share_status', ['card' => $c]) ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<nav><?= $this->Paginator->numbers() ?></nav>
