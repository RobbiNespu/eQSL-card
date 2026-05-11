<?php
// Bust browser caches when these files change on disk. Without `?v=mtime`
// a returning user keeps running the old designer.js until they manually
// shift-reload — which masked the M3-T17 canvas fix during testing.
$jsVersion = static fn (string $rel): string => (string)(@filemtime(WWW_ROOT . ltrim($rel, '/')) ?: time());
?>
<?php $this->start('script'); ?>
<script src="<?= $this->Url->build('/js/vendor/fabric.min.js') ?>?v=<?= $jsVersion('/js/vendor/fabric.min.js') ?>"></script>
<script src="<?= $this->Url->build('/js/designer-helpers.js') ?>?v=<?= $jsVersion('/js/designer-helpers.js') ?>"></script>
<script src="<?= $this->Url->build('/js/designer.js') ?>?v=<?= $jsVersion('/js/designer.js') ?>" defer></script>
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
      <summary class="small fw-bold">Net details</summary>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('NCS: {ncs_callsign}')">NCS callsign</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{net_title}')">Net title</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{net_organisation}')">Net organisation</button>
    </details>
    <details class="mb-1">
      <summary class="small fw-bold">Connection</summary>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('via {transport}')">Transport</button>
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start" @click="addField('{transport_meta}')">Channel / node / server</button>
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
    <div x-ref="canvasWrap" style="width: 100%; line-height: 0; overflow: hidden; border: 1px solid #ccc; background: #f8f9fa;">
      <canvas x-ref="canvas" style="max-width: 100%; display: block;"></canvas>
    </div>
    <p class="text-muted small mt-2">Preview at fit-to-column. Final render is at <span x-text="canvasWidth"></span> &times; <span x-text="canvasHeight"></span> px.</p>
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

        <!-- Outline. Width 0 (default) renders no stroke — same rule on
             both the Fabric canvas and the server-side GD renderer so the
             designer is WYSIWYG. -->
        <details class="mb-2" :open="(selectedField.outline_width || 0) > 0">
          <summary class="small fw-bold">Outline</summary>
          <div class="row g-2 mt-1">
            <div class="col-7">
              <label class="form-label small">Color</label>
              <input type="color" class="form-control form-control-color form-control-sm"
                     x-model="selectedField.outline_color" @input="syncFieldToCanvas()">
            </div>
            <div class="col-5">
              <label class="form-label small">Width (px)</label>
              <input type="number" class="form-control form-control-sm" min="0" max="20"
                     x-model.number="selectedField.outline_width" @input="syncFieldToCanvas()">
            </div>
          </div>
        </details>

        <!-- Shadow. Both offsets at 0 = no shadow. Sub-pixel blur isn't
             supported by GD without manual anti-aliasing, so the designer
             stays faithful to a hard-edged shadow. -->
        <details class="mb-2"
                 :open="(selectedField.shadow_offset_x || 0) !== 0 || (selectedField.shadow_offset_y || 0) !== 0">
          <summary class="small fw-bold">Shadow</summary>
          <div class="row g-2 mt-1">
            <div class="col-12">
              <label class="form-label small">Color</label>
              <input type="color" class="form-control form-control-color form-control-sm"
                     x-model="selectedField.shadow_color" @input="syncFieldToCanvas()">
            </div>
            <div class="col-6">
              <label class="form-label small">Offset X (px)</label>
              <input type="number" class="form-control form-control-sm" min="-30" max="30"
                     x-model.number="selectedField.shadow_offset_x" @input="syncFieldToCanvas()">
            </div>
            <div class="col-6">
              <label class="form-label small">Offset Y (px)</label>
              <input type="number" class="form-control form-control-sm" min="-30" max="30"
                     x-model.number="selectedField.shadow_offset_y" @input="syncFieldToCanvas()">
            </div>
          </div>
        </details>

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
