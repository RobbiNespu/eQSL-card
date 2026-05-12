<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Append-only log of significant actions across the site. Filter by event type or by actor.',
]) ?>

<form method="get" class="row g-2 mb-4">
  <div class="col-md-3">
    <select name="event" class="form-select">
      <option value="">All events</option>
      <?php foreach ($eventTypes as $e): ?>
        <option value="<?= h($e) ?>" <?= $filters['event'] === $e ? 'selected' : '' ?>><?= h($e) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <input type="number" name="actor_id" value="<?= h($filters['actorId'] ?: '') ?>" placeholder="Actor user id" class="form-control">
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
</form>

<table class="table table-sm">
  <thead><tr><th>When</th><th>Event</th><th>Actor</th><th>Target</th><th>Metadata</th></tr></thead>
  <tbody>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td><span title="<?= h($log->created_at?->format('Y-m-d H:i:s')) ?>"><?= h($log->created_at?->format('m-d H:i')) ?></span></td>
        <td><code><?= h($log->event) ?></code></td>
        <td>
          <?php if ($log->user): ?>
            <?= h($log->user->callsign) ?> (#<?= $log->actor_user_id ?>)
          <?php elseif ($log->actor_user_id): ?>
            #<?= $log->actor_user_id ?>
          <?php elseif ($log->actor_guest_visit_id): ?>
            guest visit #<?= $log->actor_guest_visit_id ?>
          <?php else: ?>
            <span class="text-muted">system</span>
          <?php endif; ?>
        </td>
        <td><?= h($log->target_type) ?> <?= $log->target_id ? '#' . h($log->target_id) : '' ?></td>
        <td><small class="text-muted"><?= h($log->metadata_json) ?></small></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<nav><?= $this->Paginator->numbers() ?></nav>
