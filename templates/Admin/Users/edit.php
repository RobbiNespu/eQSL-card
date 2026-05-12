<h1><?= h($title) ?></h1>
<p>Promote, demote, or update this user. Self-demotion is blocked at the controller level.</p>

<dl class="row dl-stack mb-4">
  <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= h($user->name) ?></dd>
  <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= h($user->email) ?></dd>
  <dt class="col-sm-3">Callsign</dt><dd class="col-sm-9"><span class="callsign"><?= h($user->callsign) ?></span></dd>
  <dt class="col-sm-3">Joined</dt><dd class="col-sm-9"><?= h($user->created_at?->format('Y-m-d H:i')) ?></dd>
</dl>

<?= $this->Form->create($user) ?>
<div class="field" style="max-width: 320px;">
  <label class="form-label" for="role">Role</label>
  <select id="role" name="role" class="form-select">
    <option value="user" <?= $user->role === 'user' ? 'selected' : '' ?>>user</option>
    <option value="admin" <?= $user->role === 'admin' ? 'selected' : '' ?>>admin</option>
  </select>
</div>

<div class="d-flex gap-2 mt-3">
  <button class="btn btn-primary">Save</button>
  <a class="btn btn-secondary" href="/admin/users">Back</a>
</div>
<?= $this->Form->end() ?>
