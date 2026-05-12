<div x-data="cameraForm()">
<h1>Generate an eQSL</h1>
<p>Fill in the QSO, attach a background, and download. No account needed — sign up later to keep your card library.</p>

<?= $this->Form->create(null, ['url' => '/generate', 'type' => 'file']) ?>
<div class="row g-3">
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="callsign">Their callsign <span class="req">*</span></label>
      <?= $this->Form->control('callsign', [
          'class' => 'form-control', 'label' => false, 'id' => 'callsign',
          'required' => true, 'autocapitalize' => 'characters', 'autocomplete' => 'off',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="operator_callsign">My callsign <span class="req">*</span></label>
      <?= $this->Form->control('operator_callsign', [
          'class' => 'form-control', 'label' => false, 'id' => 'operator_callsign',
          'required' => true, 'autocapitalize' => 'characters', 'autocomplete' => 'off',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="qso_datetime_utc">Date / Time UTC <span class="req">*</span></label>
      <?= $this->Form->control('qso_datetime_utc', [
          'type' => 'datetime-local', 'class' => 'form-control', 'label' => false,
          'id' => 'qso_datetime_utc', 'required' => true,
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="frequency_mhz">Frequency (MHz)</label>
      <?= $this->Form->control('frequency_mhz', [
          'class' => 'form-control', 'label' => false, 'id' => 'frequency_mhz',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="band">Band</label>
      <?= $this->Form->control('band', [
          'type' => 'select', 'class' => 'form-select', 'label' => false, 'id' => 'band',
          'options' => \App\Service\HamRadio::bandOptions(),
          'empty' => '— pick a band —',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="mode">Mode</label>
      <?= $this->Form->control('mode', [
          'type' => 'select', 'class' => 'form-select', 'label' => false, 'id' => 'mode',
          'options' => \App\Service\HamRadio::modeOptions(),
          'empty' => '— pick a mode —',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="rst_sent">RST sent</label>
      <?= $this->Form->control('rst_sent', [
          'class' => 'form-control', 'label' => false, 'id' => 'rst_sent',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="rst_received">RST received</label>
      <?= $this->Form->control('rst_received', [
          'class' => 'form-control', 'label' => false, 'id' => 'rst_received',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="operator_name">Their name</label>
      <?= $this->Form->control('operator_name', [
          'class' => 'form-control', 'label' => false, 'id' => 'operator_name',
          'placeholder' => 'Optional',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-12">
    <div class="field">
      <label class="form-label" for="notes">Notes</label>
      <?= $this->Form->control('notes', [
          'type' => 'textarea', 'rows' => 2,
          'class' => 'form-control', 'label' => false, 'id' => 'notes',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
</div>

<hr>
<h2>Template</h2>
<?php if (!empty($templates) && $templates->count() > 0): ?>
  <div class="row g-3 mb-3">
    <?php $first = true; ?>
    <?php foreach ($templates as $t): ?>
      <div class="col-md-3">
        <label class="radio-card">
          <input type="radio" name="template_id" value="<?= $t->id ?>" <?= $first ? 'checked' : '' ?>>
          <?php if ($t->thumbnail_path): ?>
            <img src="/<?= h($t->thumbnail_path) ?>" alt="<?= h($t->name) ?>"
                 class="img-fluid rounded mb-2" loading="lazy">
          <?php endif; ?>
          <span class="d-block small fw-semibold"><?= h($t->name) ?></span>
        </label>
      </div>
      <?php $first = false; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="alert alert-info">No templates available — please contact the operator.</div>
<?php endif; ?>

<hr>
<h2>Background</h2>
<div class="btn-group" role="group" aria-label="Background source">
  <button type="button" class="btn btn-outline-primary"
          :class="mode==='default' && 'btn-active'"
          @click="mode='default'">Use site default</button>
  <button type="button" class="btn btn-outline-primary"
          :class="mode==='upload' && 'btn-active'"
          @click="mode='upload'">Upload</button>
  <button type="button" class="btn btn-outline-primary"
          :class="mode==='camera' && 'btn-active'"
          @click="startCamera()">Use camera</button>
</div>

<div class="mt-3" x-show="mode==='default'">
  <p class="form-text">No image needed — your card will be generated with the site's default background.</p>
</div>

<div class="mt-3" x-show="mode==='upload'">
  <input type="file" name="background_upload" accept="image/jpeg,image/png,image/webp" class="form-control">
</div>
<div class="mt-3" x-show="mode==='camera'" x-cloak>
  <video x-ref="video" autoplay playsinline style="max-width:100%; border-radius: var(--r-md);"></video>
  <canvas x-ref="canvas" hidden></canvas>
  <button type="button" class="btn btn-secondary mt-2" @click="capture()">Capture</button>
  <input type="hidden" name="background_capture" x-model="captured">
  <img class="card-preview mt-2" x-show="captured" :src="captured" loading="lazy">
</div>

<!-- Attribution — only relevant when actually uploading or capturing. -->
<div class="row g-3 mt-2" x-show="mode==='upload' || mode==='camera'" x-cloak>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="background_author">Background — author / photographer</label>
      <input type="text" id="background_author" name="background_author" class="form-control"
             placeholder="Leave blank if unknown">
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="background_license">Background — license</label>
      <select id="background_license" name="background_license" class="form-select">
        <?php foreach (\App\Service\ImageLicense::options() as $code => $label): ?>
          <option value="<?= h($code) ?>"<?= $code === 'unknown' ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="col-12">
    <p class="form-text">
      The credit footer on your card will show: <em>Background: &lt;author&gt; (&lt;license&gt;) — used by &lt;your callsign&gt;</em>.
      If you leave the author blank, it'll say "unknown source".
    </p>
  </div>
</div>

<button class="btn btn-primary mt-4">Generate eQSL</button>
<?= $this->Form->end() ?>
</div>
