<?php $this->start('script'); ?>
<script src="<?= $this->Url->build('/js/vendor/fabric.min.js') ?>"></script>
<script src="<?= $this->Url->build('/js/designer.js') ?>" defer></script>
<?php $this->end(); ?>

<h1><?= h($title) ?></h1>

<div x-data="designer(<?= h(json_encode([
    'mode' => $mode,
    'templateId' => $template->id ?? null,
    'name' => $template->name ?? '',
    'description' => $template->description ?? '',
    'canvasWidth' => $template->canvas_width ?? 1500,
    'canvasHeight' => $template->canvas_height ?? 1000,
    'layoutJson' => $template->layout_json ?? '{"fields":[]}',
])) ?>)" class="row">

  <div class="col-md-3">
    <h2>Template details</h2>
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" class="form-control" x-model="name" placeholder="My QSL design">
    </div>
    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea class="form-control" rows="2" x-model="description"></textarea>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-6"><label class="form-label small">Width (px)</label><input type="number" class="form-control" x-model.number="canvasWidth"></div>
      <div class="col-6"><label class="form-label small">Height (px)</label><input type="number" class="form-control" x-model.number="canvasHeight"></div>
    </div>

    <h3>Add a field</h3>
    <details class="mb-1" open>
      <summary class="small fw-bold">Operator &amp; QSO basics</summary>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{operator_callsign}')">My callsign</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{callsign}')">Their callsign</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{operator_name}')">Operator name</button>
    </details>
    <details class="mb-1">
      <summary class="small fw-bold">Time &amp; frequency</summary>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{qso_datetime_utc:Y-m-d H:i}')">Date/time UTC</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{frequency_mhz} MHz')">Frequency</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{band}')">Band</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{mode}')">Mode</button>
    </details>
    <details class="mb-1">
      <summary class="small fw-bold">Signal report</summary>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('RST sent: {rst_sent}')">RST sent</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('RST recv: {rst_received}')">RST received</button>
    </details>
    <details class="mb-1">
      <summary class="small fw-bold">Custom</summary>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('Custom text')">Plain text</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{notes}')">QSO notes</button>
    </details>
  </div>

  <div class="col-md-6">
    <div class="mb-2">
      <label class="form-label small">Preview background (optional)</label>
      <input type="file" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp"
             @change="uploadBackground($event.target.files[0])">
      <p class="form-text small">Used only for visual reference while designing. The actual background is chosen at render time.</p>
    </div>
    <canvas x-ref="canvas" width="900" height="600" style="border: 1px solid #ccc; background: #f8f9fa;"></canvas>
    <p class="text-muted small mt-2">Preview at fit-to-screen. Final render is at <span x-text="canvasWidth"></span> &times; <span x-text="canvasHeight"></span> px.</p>
  </div>

  <div class="col-md-3">
    <h2>Selected field</h2>
    <template x-if="selectedField">
      <div>
        <div class="mb-2"><label class="form-label small">Text / placeholder</label><input class="form-control" x-model="selectedField.placeholder" @input="syncFieldToCanvas()"></div>
        <div class="mb-2"><label class="form-label small">Font size</label><input type="number" class="form-control" x-model.number="selectedField.size" @input="syncFieldToCanvas()"></div>
        <div class="mb-2"><label class="form-label small">Color</label><input type="color" class="form-control form-control-color" x-model="selectedField.color" @input="syncFieldToCanvas()"></div>
        <div class="mb-2"><label class="form-label small">Font</label>
          <select class="form-select" x-model="selectedField.font" @change="syncFieldToCanvas()">
            <option value="Inter-Regular.ttf">Inter Regular</option>
            <option value="Inter-Bold.ttf">Inter Bold</option>
            <option value="RobotoSlab-Regular.ttf">Roboto Slab</option>
            <option value="JetBrainsMono-Regular.ttf">JetBrains Mono</option>
            <option value="Cinzel-Regular.ttf">Cinzel</option>
          </select>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm mt-2" @click="deleteSelectedField()">Delete field</button>
      </div>
    </template>
    <template x-if="!selectedField">
      <p class="text-muted">Click a field on the canvas to edit it.</p>
    </template>

    <hr>
        <div class="form-check mb-3">
          <input type="checkbox" class="form-check-input" id="makePublic" x-model="makePublic">
          <label class="form-check-label small" for="makePublic">
            Make this template public (will be reviewed by admin before appearing in the gallery)
          </label>
        </div>
    <button type="button" class="btn btn-primary" @click="save()">Save</button>
  </div>
</div>
