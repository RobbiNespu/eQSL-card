<h1><?= h($title) ?></h1>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-6">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search by name, email, or callsign" class="form-control">
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
</form>

<table class="table">
  <thead><tr><th>Name</th><th>Email</th><th>Callsign</th><th>Role</th><th>Joined</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= h($u->name) ?></td>
        <td><?= h($u->email) ?></td>
        <td><?= h($u->callsign) ?></td>
        <td><span class="badge bg-<?= $u->role === 'admin' ? 'danger' : 'secondary' ?>"><?= h($u->role) ?></span></td>
        <td><?= h($u->created_at?->format('Y-m-d')) ?></td>
        <td>
          <a class="btn btn-sm btn-outline-primary" href="/admin/users/<?= $u->id ?>/edit">Edit</a>
          <?= $this->Form->postLink('Delete', '/admin/users/' . $u->id . '/delete', [
              'class' => 'btn btn-sm btn-outline-danger',
              'confirm' => 'Soft-delete this user? Their cards stay; their session ends.',
          ]) ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<nav><?= $this->Paginator->numbers() ?></nav>
