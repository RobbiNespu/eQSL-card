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
        gridVisible: false,
        gridSize: 50,
        snapToGrid: false,
        previewTab: 'design',
        previewFabric: null,
        // Background is now persisted on the template itself (cards inherit
        // it at render time). Initial values come from the bound entity for
        // edits, both blank for new templates.
        backgroundUploadId: initial.backgroundUploadId || null,

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
            this.fabricCanvas.on('object:moving', (e) => {
                if (this.snapToGrid && this.gridSize > 0) {
                    const s = this.gridSize;
                    e.target.set({
                        left: Math.round(e.target.left / s) * s,
                        top:  Math.round(e.target.top  / s) * s,
                    });
                }
                this.syncCanvasToField(e.target);
            });
            this.fabricCanvas.on('object:modified', (e) => this.syncCanvasToField(e.target));

            // Grid overlay — redrawn after every Fabric render pass so it
            // scales correctly with the viewport zoom.
            this.fabricCanvas.on('after:render', ({ ctx }) => this.drawGrid(ctx));

            // Alt+click cycles through overlapping objects at the same point,
            // bottom-to-top, so the user can reach fields buried under others.
            // We hook mouse:up (after Fabric's own selection logic has already
            // run) so our override wins without fighting the internal handler.
            this.fabricCanvas.on('mouse:up', (e) => {
                if (!e.e.altKey) return;
                const pointer = this.fabricCanvas.getPointer(e.e);
                const hits = this.fabricCanvas.getObjects().filter(o => o.containsPoint(pointer));
                if (hits.length < 2) return;
                const active = this.fabricCanvas.getActiveObject();
                const pos = hits.indexOf(active);
                // Cycle downward through the stack (wraps from bottom back to top).
                const next = hits[pos <= 0 ? hits.length - 1 : pos - 1];
                this.fabricCanvas.setActiveObject(next);
                this.fabricCanvas.requestRenderAll();
                this.handleSelection(next);
            });
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
            // Round to integer pixels — sub-pixel canvas sizes blur text on
            // some GPU paths and let `overflow:hidden` clip the last 0.x px.
            const w = Math.floor(this.canvasWidth * ratio);
            const h = Math.floor(this.canvasHeight * ratio);
            this.fabricCanvas.setZoom(ratio);
            // Fabric v6 prefers setDimensions over the legacy setWidth/setHeight
            // pair: it resizes the lower-canvas, upper-canvas, AND the wrapper
            // .canvas-container element in one atomic call. Without this the
            // canvas-container can keep its old (900x600) inline size while
            // the canvases themselves shrink, leaving a clipped-looking
            // viewport. Both v5 and v6 expose setDimensions; the inline
            // fallback covers any pre-5.3 build we might ever pin to.
            if (typeof this.fabricCanvas.setDimensions === 'function') {
                this.fabricCanvas.setDimensions({ width: w, height: h });
            } else {
                this.fabricCanvas.setWidth(w);
                this.fabricCanvas.setHeight(h);
            }
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
                // Outline + shadow are optional; fabric falls back to no
                // stroke / no shadow when these are nullish.
                stroke: (field.outline_width || 0) > 0 ? (field.outline_color || '#000000') : null,
                strokeWidth: field.outline_width || 0,
                // Shadow needs the at-least-one-nonzero-offset guard so a
                // field without shadow doesn't get a halo at (0,0).
                shadow: this.buildFabricShadow(field),
                editable: false,
            });
            // Stash the back-reference so selection/move handlers can find
            // the matching JSON entry without an O(n) lookup per event.
            obj.fieldIndex = idx;
            this.fabricCanvas.add(obj);
            this.fieldObjects.set(idx, obj);
        },

        /**
         * Build a Fabric shadow string from the field's shadow_* props.
         * Returns null when no shadow is configured (both offsets zero) so
         * Fabric renders without one. The server-side renderer applies the
         * same "both offsets zero → no shadow" rule, so the WYSIWYG and the
         * baked-in card match.
         */
        buildFabricShadow(field) {
            const ox = field.shadow_offset_x || 0;
            const oy = field.shadow_offset_y || 0;
            if (ox === 0 && oy === 0) return null;
            const color = field.shadow_color || '#000000';
            // Fabric accepts a string of "color offsetX offsetY blur".
            // Blur stays 0 for now — the GD renderer can't anti-alias a
            // blur, so we keep the designer faithful to the rendered output.
            return `${color} ${ox}px ${oy}px 0px`;
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
                // Outline + shadow defaults — keys always present in the
                // JSON so the right-pane bindings have something to bind
                // to. Width 0 / offsets 0 = visually nothing, same as the
                // pre-feature defaults.
                outline_color: '#000000',
                outline_width: 0,
                shadow_color: '#000000',
                shadow_offset_x: 0,
                shadow_offset_y: 0,
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
                stroke: (this.selectedField.outline_width || 0) > 0
                    ? (this.selectedField.outline_color || '#000000')
                    : null,
                strokeWidth: this.selectedField.outline_width || 0,
                shadow: this.buildFabricShadow(this.selectedField),
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

        toggleGrid() {
            this.gridVisible = !this.gridVisible;
            this.fabricCanvas?.requestRenderAll();
        },

        // Draw a grid directly onto the canvas context in design-space
        // coordinates. Called from the after:render event so it sits on top
        // of all Fabric objects but doesn't interfere with hit-testing.
        drawGrid(ctx) {
            if (!this.gridVisible || !this.fabricCanvas) return;
            const vpt  = this.fabricCanvas.viewportTransform;
            const zoom = this.fabricCanvas.getZoom();
            const w    = this.canvasWidth;
            const h    = this.canvasHeight;
            const s    = Math.max(10, this.gridSize);

            ctx.save();
            // Apply the viewport transform so coordinates match design space.
            ctx.transform(vpt[0], vpt[1], vpt[2], vpt[3], vpt[4], vpt[5]);
            ctx.strokeStyle = 'rgba(99,102,241,0.25)';
            ctx.lineWidth   = 1 / zoom;
            ctx.beginPath();
            for (let x = 0; x <= w; x += s) {
                ctx.moveTo(x, 0);
                ctx.lineTo(x, h);
            }
            for (let y = 0; y <= h; y += s) {
                ctx.moveTo(0, y);
                ctx.lineTo(w, y);
            }
            ctx.stroke();
            ctx.restore();
        },

        // Select a field from the layers panel by its fields-array index.
        selectFieldByIndex(idx) {
            const obj = this.fieldObjects.get(idx);
            if (!obj || !this.fabricCanvas) return;
            this.fabricCanvas.setActiveObject(obj);
            this.fabricCanvas.requestRenderAll();
            this.selectedField = this.fields[idx] || null;
        },

        bringForward() {
            if (!this.selectedField) return;
            const obj = this.fieldObjects.get(this.fields.indexOf(this.selectedField));
            if (!obj) return;
            this.fabricCanvas.bringForward(obj);
            this.fabricCanvas.requestRenderAll();
        },

        sendBackward() {
            if (!this.selectedField) return;
            const obj = this.fieldObjects.get(this.fields.indexOf(this.selectedField));
            if (!obj) return;
            this.fabricCanvas.sendBackwards(obj);
            this.fabricCanvas.requestRenderAll();
        },

        // ── Preview tab ───────────────────────────────────────────────────

        switchTab(tab) {
            this.previewTab = tab;
            if (tab === 'preview') {
                this.$nextTick(() => this.renderPreview());
            } else {
                this.$nextTick(() => this.fabricCanvas?.requestRenderAll());
            }
        },

        // Substitute a placeholder string with sample values for display.
        // Mirrors the server-side PlaceholderResolver regex so the preview
        // text matches what the real card renderer would produce.
        resolveForPreview(text) {
            const dt = new Date('2025-07-27T14:30:00Z');
            const sample = {
                operator_callsign: '9M2NSP',
                callsign:          'W1AW',
                operator_name:     'Ahmad',
                qso_datetime_utc:  dt,
                qso_date_hijri:    '1 SAFAR 1447H',
                frequency_mhz:     '14.225',
                band:              '20m',
                mode:              'SSB',
                rst_sent:          '59',
                rst_received:      '57',
                ncs_callsign:      '9M2NCS',
                net_title:         'Malaysians On Air',
                net_organisation:  'MARTS',
                transport:         'RF (over the air)',
                transport_meta:    '',
                notes:             'Good signal, 73!',
            };
            return text.replace(/\{([a-z_][a-z0-9_]*)(?::([^}]+))?\}/gi, (match, key, fmt) => {
                if (!(key in sample)) return match;
                const val = sample[key];
                if (val instanceof Date) {
                    return fmt ? this.formatPhpDate(fmt, val)
                               : val.toISOString().slice(0, 16).replace('T', ' ');
                }
                return String(val);
            });
        },

        // Convert a PHP date format string to a formatted string from a JS Date.
        // Handles the subset of PHP format chars actually used in QSL templates.
        formatPhpDate(fmt, dt) {
            const pad = n => String(n).padStart(2, '0');
            return [...fmt].map(c => {
                switch (c) {
                    case 'Y': return dt.getUTCFullYear();
                    case 'y': return String(dt.getUTCFullYear()).slice(-2);
                    case 'm': return pad(dt.getUTCMonth() + 1);
                    case 'n': return dt.getUTCMonth() + 1;
                    case 'd': return pad(dt.getUTCDate());
                    case 'j': return dt.getUTCDate();
                    case 'H': return pad(dt.getUTCHours());
                    case 'G': return dt.getUTCHours();
                    case 'i': return pad(dt.getUTCMinutes());
                    case 's': return pad(dt.getUTCSeconds());
                    default:  return c;
                }
            }).join('');
        },

        renderPreview() {
            if (!this.$refs.previewCanvas) return;
            const FabricNS   = fabric.fabric || fabric;
            const wrap       = this.$refs.previewWrap;
            const targetW    = (wrap && wrap.clientWidth) || 900;
            const ratio      = Math.min(targetW / this.canvasWidth, 600 / this.canvasHeight);
            const w          = Math.floor(this.canvasWidth  * ratio);
            const h          = Math.floor(this.canvasHeight * ratio);

            if (!this.previewFabric) {
                this.previewFabric = new FabricNS.Canvas(this.$refs.previewCanvas, {
                    selection: false,
                });
            } else {
                this.previewFabric.clear();
            }
            this.previewFabric.setZoom(ratio);
            if (typeof this.previewFabric.setDimensions === 'function') {
                this.previewFabric.setDimensions({ width: w, height: h });
            } else {
                this.previewFabric.setWidth(w);
                this.previewFabric.setHeight(h);
            }
            // Reuse the background URL (not the Fabric image object — sharing
            // Fabric objects between two canvases causes double-free artefacts).
            this.applyBackground(this.previewFabric);

            const Textbox = FabricNS.Textbox || FabricNS.Text;
            this.fields.forEach((f) => {
                const obj = new Textbox(this.resolveForPreview(f.placeholder || ''), {
                    left:        f.x,
                    top:         f.y,
                    fontFamily:  this.fontFamilyFor(f.font),
                    fontSize:    f.size || 36,
                    fill:        f.color || '#000000',
                    angle:       f.rotation || 0,
                    stroke:      (f.outline_width || 0) > 0 ? (f.outline_color || '#000000') : null,
                    strokeWidth: f.outline_width || 0,
                    shadow:      this.buildFabricShadow(f),
                    selectable:  false,
                    evented:     false,
                    editable:    false,
                });
                this.previewFabric.add(obj);
            });
            this.previewFabric.requestRenderAll();
        },

        // Background URL is held in state for the live preview; the actual
        // persisted reference is `backgroundUploadId` above. When the user
        // uploads a file the server returns the `uploads.id` and the URL —
        // we keep both: id for save(), url for the preview.
        backgroundUrl: initial.backgroundUrl || null,

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
            this.backgroundUploadId = data.upload_id || null;
            this.applyBackground();
        },

        // Detach the bound background. The card render will then fall
        // through to the site-default image. State update only — actual
        // persistence happens when the user clicks Save.
        removeBackground() {
            this.backgroundUrl = null;
            this.backgroundUploadId = null;
            if (this.fabricCanvas && this.fabricCanvas.setBackgroundImage) {
                this.fabricCanvas.setBackgroundImage(null, () => this.fabricCanvas.requestRenderAll());
            } else if (this.fabricCanvas) {
                this.fabricCanvas.backgroundImage = null;
                this.fabricCanvas.requestRenderAll();
            }
        },

        applyBackground(targetCanvas) {
            const canvas = targetCanvas || this.fabricCanvas;
            if (!this.backgroundUrl || !canvas) return;
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
                if (canvas.setBackgroundImage) {
                    canvas.setBackgroundImage(img, () => canvas.requestRenderAll());
                } else if ('backgroundImage' in canvas) {
                    canvas.backgroundImage = img;
                    canvas.requestRenderAll();
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
            // Empty string explicitly clears the background on the template;
            // a numeric id binds the upload row as the template's background.
            body.append('background_upload_id', this.backgroundUploadId ? String(this.backgroundUploadId) : '');
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
            // 422 = controller hit validation errors (empty name, bad canvas
            // dims, malformed layout_json). Surface the server's messages
            // instead of silently navigating away.
            if (r.status === 422) {
                let msg = 'Validation failed';
                try {
                    const data = await r.json();
                    if (Array.isArray(data.errors) && data.errors.length) {
                        msg = data.errors.join('\n');
                    }
                } catch (_) { /* fall through to generic message */ }
                alert('Save failed:\n' + msg);
                return;
            }
            if (r.ok) {
                // Preferred path: controller returns JSON {redirect_url: "..."}
                // for AJAX callers so we can distinguish a real success from
                // a validation-error re-render (both used to come back as
                // a 200 HTML page).
                const ct = r.headers.get('Content-Type') || '';
                if (ct.includes('application/json')) {
                    try {
                        const data = await r.json();
                        if (data && data.redirect_url) {
                            window.location.href = data.redirect_url;
                            return;
                        }
                    } catch (_) { /* fall through */ }
                }
                // Legacy path: server did a 302 that fetch auto-followed.
                if (r.redirected) {
                    window.location.href = r.url;
                    return;
                }
                // 200 OK with no redirect and no JSON redirect_url means the
                // server re-rendered the form (typically a non-AJAX path).
                // Don't silently nav to /templates — that hides errors.
                alert('Save failed: server returned 200 without a redirect. Check the page for a flash error.');
                return;
            }
            alert('Save failed: ' + r.status);
        },
    };
}
window.designer = designer;
