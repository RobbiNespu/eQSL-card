/**
 * Designer Alpine factory (M3-T3 — Fabric.js wiring).
 *
 * Renders each entry of `fields` as a draggable Fabric.Textbox on the canvas,
 * keeps the Alpine `fields` array authoritative for the JSON payload, and
 * mirrors selection / drag / size / colour edits in both directions.
 *
 * The canvas element is a fixed 900×600 viewport, but the design space is
 * `canvasWidth × canvasHeight` (default 1500×1000). We apply a uniform
 * `setZoom` so coordinates stored in JSON are always in design space — that
 * keeps the server-side renderer (M3-T7+) trivial: no display scaling fudge.
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
        fields: (() => {
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
            this.$nextTick(() => this.initFabric());
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
            const ratioW = 900 / this.canvasWidth;
            const ratioH = 600 / this.canvasHeight;
            const ratio = Math.min(ratioW, ratioH);
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
            // Designer-side preview only. Server-side renderer (M3-T7+)
            // loads the actual TTF; here we just pick a CSS family that
            // looks roughly right so the operator can judge proportions.
            const map = {
                'Inter-Regular.ttf': 'Inter, sans-serif',
                'Inter-Bold.ttf': 'Inter, sans-serif',
                'RobotoSlab-Regular.ttf': '"Roboto Slab", serif',
                'JetBrainsMono-Regular.ttf': '"JetBrains Mono", monospace',
                'Cinzel-Regular.ttf': 'Cinzel, serif',
            };
            return map[filename] || 'sans-serif';
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

        async save() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const url = this.mode === 'new' ? '/templates/new' : `/templates/${this.templateId}/edit`;
            const body = new URLSearchParams();
            body.append('name', this.name);
            body.append('description', this.description || '');
            body.append('canvas_width', String(this.canvasWidth));
            body.append('canvas_height', String(this.canvasHeight));
            body.append('layout_json', JSON.stringify({ fields: this.fields }));
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
