<div x-data="cameraForm()" class="card p-4">
<h1>Generate an eQSL</h1>
<p class="text-muted">Fill in the QSO, attach a background, and download.</p>

<?= $this->Form->create(null, ['url' => '/generate', 'type' => 'file']) ?>
<div class="row g-3">
  <div class="col-md-6"><?= $this->Form->control('callsign', ['label' => 'Their callsign', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('operator_callsign', ['label' => 'My callsign', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('qso_datetime_utc', ['label' => 'Date/Time UTC', 'type' => 'datetime-local', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('frequency_mhz', ['label' => 'Frequency (MHz)', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('band', ['label' => 'Band', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('mode', ['label' => 'Mode', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('rst_sent', ['label' => 'RST sent', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('rst_received', ['label' => 'RST received', 'class' => 'form-control']) ?></div>
  <div class="col-md-6"><?= $this->Form->control('operator_name', ['label' => 'Their name', 'class' => 'form-control']) ?></div>
  <div class="col-md-12"><?= $this->Form->control('notes', ['label' => 'Notes', 'class' => 'form-control', 'type' => 'textarea', 'rows' => 2]) ?></div>
</div>

<hr>
<h2>Background</h2>
<div class="btn-group" role="group">
  <button type="button" class="btn btn-outline-primary" @click="mode='upload'" :class="mode==='upload' && 'active'">Upload</button>
  <button type="button" class="btn btn-outline-primary" @click="startCamera()" :class="mode==='camera' && 'active'">Use camera</button>
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

<button class="btn btn-primary mt-4">Generate</button>
<?= $this->Form->end() ?>
</div>
