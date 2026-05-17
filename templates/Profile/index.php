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

    <?php /* M5 T27 — opt-in safety toggle for the Quick-add dupe-check.
           When ON, /qsos/quick disables Save while the red dup-in-
           activation badge is showing. Default OFF so DXpedition /
           contest operators (where intentional same-band dupes are
           legitimate) aren't blocked. */ ?>
    <h2 class="h5 mt-4">Quick-add safety</h2>
    <div class="field">
      <label class="form-check form-switch d-flex align-items-center gap-2">
        <input type="hidden" name="block_dupes_in_activation" value="0">
        <input type="checkbox" name="block_dupes_in_activation" value="1"
               class="form-check-input"
               <?= $user->block_dupes_in_activation ? 'checked' : '' ?>>
        <span>Block save when a duplicate is detected in the active activation</span>
      </label>
      <p class="form-text">
        With this on, the Quick-add Save button is greyed out whenever the dupe-check badge shows
        <strong>red</strong> (you've already worked this callsign on this band during the current
        activation). Prevents accidental double-logging during a busy net or POTA activation.
        Leave off if you run contests / DXpeditions where same-band dupes are legitimate.
      </p>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-primary">Save profile</button>
    </div>
    <?= $this->Form->end() ?>
  </div>
</div>
