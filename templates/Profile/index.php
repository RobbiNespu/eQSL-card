<h1><?= h($title) ?></h1>

<div class="row">
  <div class="col-md-3">
    <h2>Avatar</h2>
    <?php if ($user->avatar_path): ?>
      <img src="/<?= h($user->avatar_path) ?>" alt="avatar" class="img-thumbnail mb-3" style="max-width: 200px">
    <?php else: ?>
      <div class="bg-light text-center py-5 mb-3">No avatar</div>
    <?php endif; ?>

    <?= $this->Form->create(null, ['url' => '/profile/avatar', 'type' => 'file']) ?>
    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control mb-2">
    <button class="btn btn-sm btn-primary">Upload avatar</button>
    <?= $this->Form->end() ?>
  </div>

  <div class="col-md-9">
    <h2>Details</h2>
    <?= $this->Form->create($user) ?>
    <div class="mb-3"><label>Display name</label><?= $this->Form->control('name', ['class' => 'form-control', 'label' => false]) ?></div>
    <div class="mb-3"><label>Callsign</label><?= $this->Form->control('callsign', ['class' => 'form-control', 'label' => false]) ?></div>
    <div class="mb-3"><label>QTH (city, region)</label><?= $this->Form->control('qth', ['class' => 'form-control', 'label' => false]) ?></div>
    <div class="mb-3"><label>Grid square</label><?= $this->Form->control('grid_square', ['class' => 'form-control', 'label' => false]) ?></div>
    <div class="mb-3"><label>Bio</label><?= $this->Form->control('bio', ['type' => 'textarea', 'rows' => 3, 'class' => 'form-control', 'label' => false]) ?></div>
    <button class="btn btn-primary">Save profile</button>
    <?= $this->Form->end() ?>
  </div>
</div>
