/**
 * Designer Alpine factory (M3-T3 — Fabric.js wiring).
 *
 * Renders each entry of `fields` as a draggable Fabric.Textbox on the canvas,
 * keeps the Alpine `fields` array authoritative for the JSON payload, and
 * mirrors selection / drag / size / colour edits in both directions.
 *
 * The on-screen preview is fit-to-column — we read the wrapper's clientWidth
 * (capped at 600px tall) and uniformly `setZoom` to scale the design space
 * (`canvasWidth × canvasHeight`, default 1500×1000) into that rectangle.
 * Coordinates stored in JSON are always in design space so the server-side
 * renderer (M3-T7+) stays trivial: no display scaling fudge.
 *
 * `initial.layoutJson` is a string (CakePHP hands us the raw column). A
 * malformed legacy value should not blow up the page; we degrade to an
 * empty fields array so the user can rebuild rather than seeing a
 * white-screen stack trace.
 */
function designer(initial) {
    return {
        mode: initial.mode,
        templateId: initial.templateId,
        name: initial.name || '',
        description: initial.description || '',
        canvasWidth: initial.canvasWidth || 1500,
        canvasHeight: initial.canvasHeight || 1000,
        fields: (window.designerHelpers || {}).parseLayoutJson
            ? window.designerHelpers.parseLayoutJson(initial.layoutJson).fields
            : (() => {
                try { return JSON.parse(initial.layoutJson).fields || []; } catch (e) { return []; }
            })(),
        selectedField: null,
        fabricCanvas: null,
        // Index → fabric.Textbox. We rely on indices (not object identity)
        // because Alpine reactivity rewraps array entries; the integer index
        // is the stable bridge between the two worlds.
        fieldObjects: new Map(),

        init() {
            // Defer until the <canvas x-ref="canvas"> element is mounted.
            this.$nextTick(() => {
                this.initFabric();
                // Re-fit the canvas to its column on viewport changes. 120ms
                // debounce is fast enough to feel live during a drag-resize
                // without flooding Fabric with re-zoom work mid-drag.
                let resizeTimer;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => {
                        if (this.fabricCanvas) this.applyViewportScale();
                    }, 120);
                });
            });
        },

        initFabric() {
            if (typeof fabric === 'undefined') {
                console.error('Fabric.js not loaded');
                return;
            }
            // Fabric v5 exposes everything on `fabric.*`; the v6 UMD build we
            // vendored wraps the same API but some builds re-nest under
            // `fabric.fabric`. Tolerating both keeps the upgrade path open.
            const FabricNS = fabric.fabric || fabric;
            const Canvas = FabricNS.Canvas;
            this.fabricCanvas = new Canvas(this.$refs.canvas, {
                preserveObjectStacking: true,
            });

            this.applyViewportScale();

            // Render existing fields (edit mode).
            this.fields.forEach((f, idx) => this.renderFieldOnCanvas(f, idx));

            // Click → select; mirror into `selectedField` so the right pane
            // bindings light up.
            this.fabricCanvas.on('selection:created', (e) => this.handleSelection(e.selected?.[0]));
            this.fabricCanvas.on('selection:updated', (e) => this.handleSelection(e.selected?.[0]));
            this.fabricCanvas.on('selection:cleared', () => { this.selectedField = null; });

            // Drag/move → write x/y back into the JSON model. `object:moving`
            // gives a live stream while dragging; `object:modified` covers
            // the final commit (also triggered by handles for resize).
            this.fabricCanvas.on('object:moving', (e) => this.syncCanvasToField(e.target));
            this.fabricCanvas.on('object:modified', (e) => this.syncCanvasToField(e.target));
        },

        applyViewportScale() {
            // Fit the on-screen canvas to its column. The wrapper ref is set
            // by the edit.php template; if it's missing (legacy markup) we
            // fall back to 900 so the designer still renders rather than
            // crashing on a null clientWidth read.
            const wrap = this.$refs.canvasWrap;
            const targetW = (wrap && wrap.clientWidth) || 900;
            // Cap on-screen height so a very wide viewport doesn't make the
            // canvas taller than the side panels next to it.
            const targetH = 600;
            const ratio = Math.min(targetW / this.canvasWidth, targetH / this.canvasHeight);
            this.fabricCanvas.setZoom(ratio);
            this.fabricCanvas.setWidth(this.canvasWidth * ratio);
            this.fabricCanvas.setHeight(this.canvasHeight * ratio);
        },

        renderFieldOnCanvas(field, idx) {
            const FabricNS = fabric.fabric || fabric;
            // Textbox supports word-wrap & resize handles; fall back to Text
            // for very old fabric builds where Textbox isn't bundled.
            const Textbox = FabricNS.Textbox || FabricNS.Text;
            const obj = new Textbox(field.placeholder || '', {
                left: field.x,
                top: field.y,
                fontFamily: this.fontFamilyFor(field.font),
                fontSize: field.size || 36,
                fill: field.color || '#000000',
                angle: field.rotation || 0,
                editable: false,
            });
            // Stash the back-reference so selection/move handlers can find
            // the matching JSON entry without an O(n) lookup per event.
            obj.fieldIndex = idx;
            this.fabricCanvas.add(obj);
            this.fieldObjects.set(idx, obj);
        },

        fontFamilyFor(filename) {
            // Delegates to the pure helper module (M3-T16) so the same
            // mapping is unit-testable under Node without a DOM. Falls back
            // to a hard 'sans-serif' if the helpers script failed to load.
            const helpers = window.designerHelpers;
            return helpers ? helpers.fontFamilyFor(filename) : 'sans-serif';
        },

        handleSelection(obj) {
            if (obj && typeof obj.fieldIndex !== 'undefined') {
                this.selectedField = this.fields[obj.fieldIndex] || null;
            }
        },

        syncCanvasToField(obj) {
            if (!obj || typeof obj.fieldIndex === 'undefined') return;
            const f = this.fields[obj.fieldIndex];
            if (!f) return;
            f.x = Math.round(obj.left);
            f.y = Math.round(obj.top);
            f.size = Math.round(obj.fontSize);
            f.color = obj.fill;
            f.rotation = Math.round(obj.angle || 0);
        },

        addField(text) {
            const field = {
                placeholder: text,
                x: 100,
                // Stagger so successively-added fields don't pile on top of
                // each other and become impossible to select.
                y: 100 + (this.fields.length * 60),
                font: 'Inter-Regular.ttf',
                size: 36,
                color: '#000000',
                rotation: 0,
            };
            this.fields.push(field);
            this.selectedField = field;
            this.renderFieldOnCanvas(field, this.fields.length - 1);
            this.fabricCanvas?.requestRenderAll();
        },

        syncFieldToCanvas() {
            // Right-pane edits (`x-model` on `selectedField`) → push into
            // the matching Fabric object so the canvas updates live.
            if (!this.selectedField) return;
            const idx = this.fields.indexOf(this.selectedField);
            const obj = this.fieldObjects.get(idx);
            if (!obj) return;
            obj.set({
                text: this.selectedField.placeholder,
                fontSize: this.selectedField.size,
                fill: this.selectedField.color,
                fontFamily: this.fontFamilyFor(this.selectedField.font),
                angle: this.selectedField.rotation,
            });
            this.fabricCanvas?.requestRenderAll();
        },

        deleteSelectedField() {
            if (!this.selectedField) return;
            const idx = this.fields.indexOf(this.selectedField);
            const obj = this.fieldObjects.get(idx);
            if (obj) {
                this.fabricCanvas?.remove(obj);
            }
            this.fields.splice(idx, 1);
            this.selectedField = null;
            // Splicing shifts indices ≥ idx down by one, so our index→object
            // map is now stale. Easiest correct fix: rebuild it from the
            // canvas object list, matching by the back-reference we already
            // stamped at render time.
            this.fieldObjects.clear();
            const objs = this.fabricCanvas?.getObjects() || [];
            objs.forEach((o) => {
                if (typeof o.fieldIndex === 'number' && o.fieldIndex > idx) {
                    o.fieldIndex -= 1;
                }
                if (typeof o.fieldIndex === 'number') {
                    this.fieldObjects.set(o.fieldIndex, o);
                }
            });
            this.fabricCanvas?.requestRenderAll();
        },

        // M3-T5 — designer preview background. The URL is preview-only:
        // templates stay background-agnostic per spec §6.4, so we don't
        // serialise `backgroundUrl` into `layout_json`. The upload itself
        // does land in the user's `uploads` library and can be reused at
        // render time.
        backgroundUrl: null,

        async uploadBackground(file) {
            if (!file) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const fd = new FormData();
            fd.append('background_upload', file);
            const r = await fetch('/templates/upload-background', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body: fd,
                credentials: 'same-origin',
            });
            if (!r.ok) {
                const t = await r.text();
                alert('Upload failed: ' + t);
                return;
            }
            const data = await r.json();
            this.backgroundUrl = data.url;
            this.applyBackground();
        },

        applyBackground() {
            if (!this.backgroundUrl || !this.fabricCanvas) return;
            // Fabric v5 exposes `fabric.Image.fromURL(url, cb)`; the v6 UMD
            // build we vendored returns a Promise. Tolerating both keeps the
            // upgrade path open (same shape as initFabric's namespace probe).
            const FabricNS = fabric.fabric || fabric;
            const Image = FabricNS.Image || FabricNS.FabricImage;
            const promise = Image.fromURL ? Image.fromURL(this.backgroundUrl) : null;
            const set = (img) => {
                // Scale background to fit the design space (canvasWidth ×
                // canvasHeight). The viewport zoom applied in
                // applyViewportScale() handles the on-screen shrink, so we
                // do NOT compose pre-zoom and post-zoom scaling here.
                img.scaleX = this.canvasWidth / img.width;
                img.scaleY = this.canvasHeight / img.height;
                if (this.fabricCanvas.setBackgroundImage) {
                    this.fabricCanvas.setBackgroundImage(img, () => this.fabricCanvas.requestRenderAll());
                } else if ('backgroundImage' in this.fabricCanvas) {
                    this.fabricCanvas.backgroundImage = img;
                    this.fabricCanvas.requestRenderAll();
                }
            };
            if (promise && typeof promise.then === 'function') {
                promise.then(set);
            } else {
                Image.fromURL(this.backgroundUrl, set);
            }
        },

        // M3-T9 — opt-in "Make public" toggle. When checked, save() POSTs
        // `make_public=1` and the controller flips `is_public=true,
        // is_approved=false` so the template enters the admin moderation queue.
        // We never auto-approve — that requires explicit admin action (M3-T11).
        makePublic: false,
        async save() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const url = this.mode === 'new' ? '/templates/new' : `/templates/${this.templateId}/edit`;
            const body = new URLSearchParams();
            body.append('name', this.name);
            body.append('description', this.description || '');
            body.append('canvas_width', String(this.canvasWidth));
            body.append('canvas_height', String(this.canvasHeight));
            body.append('layout_json', (window.designerHelpers && window.designerHelpers.serializeLayout)
                ? window.designerHelpers.serializeLayout(this.fields)
                : JSON.stringify({ fields: this.fields }));
            if (this.makePublic) {
                body.append('make_public', '1');
            }
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrf,
                    'Accept': 'application/json',
                },
                body,
                credentials: 'same-origin',
            });
            if (r.redirected) {
                window.location.href = r.url;
            } else if (r.ok) {
                window.location.href = '/templates';
            } else {
                alert('Save failed: ' + r.status);
            }
        },
    };
}
window.designer = designer;
