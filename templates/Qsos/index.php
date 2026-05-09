<h1><?= h($title) ?></h1>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-3">
    <input type="search" name="q" value="<?= h($filters['search']) ?>" placeholder="Callsign" class="form-control">
  </div>
  <div class="col-md-2">
    <input type="text" name="band" value="<?= h($filters['band']) ?>" placeholder="Band (e.g. 20m)" class="form-control">
  </div>
  <div class="col-md-2">
    <input type="text" name="mode" value="<?= h($filters['mode']) ?>" placeholder="Mode (e.g. SSB)" class="form-control">
  </div>
  <div class="col-md-2">
    <input type="date" name="from" value="<?= h($filters['from']) ?>" class="form-control">
  </div>
  <div class="col-md-2">
    <input type="date" name="to" value="<?= h($filters['to']) ?>" class="form-control">
  </div>
  <div class="col-md-1">
    <button class="btn btn-primary w-100">Filter</button>
  </div>
</form>

<?php if ($qsos->count() === 0): ?>
  <div class="alert alert-info">
    No QSOs match your filter.
    <a href="/qsos/new">Add one</a>
    or <a href="/qsos/import">import an ADIF/CSV log</a>.
  </div>
<?php else: ?>
  <table class="table table-striped">
    <thead>
      <tr>
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
        <td><strong><?= h($qso->call_worked) ?></strong></td>
        <td><?= h($qso->qso_datetime_utc?->format('Y-m-d H:i')) ?></td>
        <td><?= h($qso->frequency_mhz) ?></td>
        <td><?= h($qso->band) ?></td>
        <td><?= h($qso->mode) ?></td>
        <td><?= h($qso->rst_sent) ?> / <?= h($qso->rst_received) ?></td>
        <td>
          <a class="btn btn-sm btn-outline-secondary" href="/qsos/<?= $qso->id ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <nav><?= $this->Paginator->numbers() ?></nav>
<?php endif; ?>
