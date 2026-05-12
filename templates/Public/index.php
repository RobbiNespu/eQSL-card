<div x-data="cameraForm()" class="card p-4">
<h1>Generate an eQSL</h1>
<p class="text-muted">Fill in the QSO, attach a background, and download.</p>

<?= $this->Form->create(null, ['url' => '/generate', 'type' => 'file']) ?>
<div class="row g-3">
  <div class="col-md-6"><?= $this->Form->control('callsign', ['label' => 'Their callsign', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('operator_callsign', ['label' => 'My callsign', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('qso_datetime_utc', ['label' => 'Date/Time UTC', 'type' => 'datetime-local', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('frequency_mhz', ['label' => 'Frequency (MHz)', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('band', [
      'label' => 'Band', 'type' => 'select', 'class' => 'form-select',
      'options' => \App\Service\HamRadio::bandOptions(),
      'empty' => '— pick a band —',
  ]) ?></div>
  <div class="col-md-3"><?= $this->Form->control('mode', [
      'label' => 'Mode', 'type' => 'select', 'class' => 'form-select',
      'options' => \App\Service\HamRadio::modeOptions(),
      'empty' => '— pick a mode —',
  ]) ?></div>
  <div class="col-md-3"><?= $this->Form->control('rst_sent', ['label' => 'RST sent', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('rst_received', ['label' => 'RST received', 'class' => 'form-control']) ?></div>
  <div class="col-md-6"><?= $this->Form->control('operator_name', ['label' => 'Their name', 'class' => 'form-control']) ?></div>
  <div class="col-md-12"><?= $this->Form->control('notes', ['label' => 'Notes', 'class' => 'form-control', 'type' => 'textarea', 'rows' => 2]) ?></div>
</div>

<hr>
<h2>Template</h2>
<?php if (!empty($templates) && $templates->count() > 0): ?>
  <div class="row g-2 mb-3">
    <?php $first = true; ?>
    <?php foreach ($templates as $t): ?>
      <div class="col-md-3">
        <label class="card p-2 d-block" style="cursor: pointer;">
          <input type="radio" name="template_id" value="<?= $t->id ?>" <?= $first ? 'checked' : '' ?>>
          <?php if ($t->thumbnail_path): ?>
            <img src="/<?= h($t->thumbnail_path) ?>" alt="<?= h($t->name) ?>" class="img-fluid" loading="lazy">
          <?php endif; ?>
          <span class="d-block small mt-1"><strong><?= h($t->name) ?></strong></span>
        </label>
      </div>
      <?php $first = false; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="text-muted">No templates available — please contact the operator.</p>
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
  <p class="text-muted small">No image needed — your card will be generated with the site's default background.</p>
</div>

<div class="mt-3" x-show="mode==='upload'">
  <input type="file" name="background_upload" accept="image/jpeg,image/png,image/webp" class="form-control">
</div>
<div class="mt-3" x-show="mode==='camera'" x-cloak>
  <video x-ref="video" autoplay playsinline style="max-width:100%"></video>
  <canvas x-ref="canvas" hidden></canvas>
  <button type="button" class="btn btn-secondary mt-2" @click="capture()">Capture</button>
  <input type="hidden" name="background_capture" x-model="captured">
  <img class="card-preview mt-2" x-show="captured" :src="captured">
</div>

<!-- Attribution — only relevant when actually uploading or capturing. -->
<div class="row g-2 mt-3" x-show="mode==='upload' || mode==='camera'" x-cloak>
  <div class="col-md-6">
    <label class="form-label small">Background — author / photographer</label>
    <input type="text" name="background_author" class="form-control" placeholder="Leave blank if unknown">
  </div>
  <div class="col-md-6">
    <label class="form-label small">Background — license</label>
    <select name="background_license" class="form-select">
      <?php foreach (\App\Service\ImageLicense::options() as $code => $label): ?>
        <option value="<?= h($code) ?>"<?= $code === 'unknown' ? ' selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <p class="form-text small col-12">
    The credit footer on your card will show: <em>Background: &lt;author&gt; (&lt;license&gt;) — used by &lt;your callsign&gt;</em>.
    If you leave the author blank, it'll say "unknown source".
  </p>
</div>

<button class="btn btn-primary mt-4">Generate</button>
<?= $this->Form->end() ?>
</div>
