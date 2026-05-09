<h1><?= h($title) ?></h1>

<?= $this->Form->create($user) ?>
<dl class="row">
  <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= h($user->name) ?></dd>
  <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= h($user->email) ?></dd>
  <dt class="col-sm-3">Callsign</dt><dd class="col-sm-9"><?= h($user->callsign) ?></dd>
  <dt class="col-sm-3">Joined</dt><dd class="col-sm-9"><?= h($user->created_at?->format('Y-m-d H:i')) ?></dd>
</dl>

<div class="mb-3">
  <label class="form-label">Role</label>
  <select name="role" class="form-select">
    <option value="user" <?= $user->role === 'user' ? 'selected' : '' ?>>user</option>
    <option value="admin" <?= $user->role === 'admin' ? 'selected' : '' ?>>admin</option>
  </select>
</div>

<button class="btn btn-primary">Save</button>
<a class="btn btn-link" href="/admin/users">Back</a>
<?= $this->Form->end() ?>
