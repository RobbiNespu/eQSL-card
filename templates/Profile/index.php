<h1><?= h($title) ?></h1>
<p>Your callsign + station details. These prefill the QSO form and appear on the eQSL cards you generate.</p>

<div class="row g-4">
  <div class="col-md-3">
    <h2 class="h5">Avatar</h2>
    <?php if ($user->avatar_path): ?>
      <img src="/<?= h($user->avatar_path) ?>" alt="avatar" class="img-fluid rounded mb-3" style="max-width: 200px" loading="lazy">
    <?php else: ?>
      <div class="bg-light text-center text-muted py-5 rounded mb-3">No avatar yet</div>
    <?php endif; ?>

    <?= $this->Form->create(null, ['url' => '/profile/avatar', 'type' => 'file']) ?>
      <div class="field">
        <label class="form-label" for="avatar">Upload an avatar</label>
        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control">
        <p class="form-text">JPEG, PNG, or WebP. Square images look best.</p>
      </div>
      <button class="btn btn-sm btn-primary">Upload</button>
    <?= $this->Form->end() ?>
  </div>

  <div class="col-md-9">
    <h2 class="h5">Station details</h2>
    <?= $this->Form->create($user) ?>

    <div class="field">
      <label class="form-label" for="name">Display name</label>
      <?= $this->Form->control('name', [
          'class' => 'form-control', 'label' => false, 'id' => 'name',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="field">
      <label class="form-label" for="callsign">Callsign</label>
      <?= $this->Form->control('callsign', [
          'class' => 'form-control', 'label' => false, 'id' => 'callsign',
          'autocapitalize' => 'characters', 'autocomplete' => 'off', 'spellcheck' => 'false',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>

    <div class="row g-3">
      <div class="col-md-7">
        <div class="field">
          <label class="form-label" for="qth">QTH (city, region)</label>
          <?= $this->Form->control('qth', [
              'class' => 'form-control', 'label' => false, 'id' => 'qth',
              'templates' => ['inputContainer' => '{{content}}'],
          ]) ?>
        </div>
      </div>
      <div class="col-md-5">
        <div class="field">
          <label class="form-label" for="grid_square">Grid square</label>
          <?= $this->Form->control('grid_square', [
              'class' => 'form-control', 'label' => false, 'id' => 'grid_square',
              'placeholder' => 'e.g. OJ02wx',
              'templates' => ['inputContainer' => '{{content}}'],
          ]) ?>
        </div>
      </div>
    </div>

    <div class="field">
      <label class="form-label" for="bio">Bio</label>
      <?= $this->Form->control('bio', [
          'type' => 'textarea', 'rows' => 3,
          'class' => 'form-control', 'label' => false, 'id' => 'bio',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
      <p class="form-text">A short note about your station or interests. Shown on your public profile page once that ships.</p>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-primary">Save profile</button>
    </div>
    <?= $this->Form->end() ?>
  </div>
</div>
