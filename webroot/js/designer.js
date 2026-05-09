/**
 * Designer Alpine factory (M3-T2 scaffold).
 *
 * Exposes the methods the designer view (`templates/Templates/edit.php`)
 * binds against. Today this is a thin shell — real Fabric.Text wiring lands
 * in M3-T3 and `save()` becomes a real POST in M3-T4. Keeping the surface
 * stable now means downstream tasks are pure additions, not reshapings.
 *
 * `initial` is the JSON blob the view passes via `x-data`; we never read
 * server state any other way so a logged-out / cached page can't desync.
 */
function designer(initial) {
    return {
        mode: initial.mode,
        templateId: initial.templateId,
        name: initial.name,
        description: initial.description,
        canvasWidth: initial.canvasWidth,
        canvasHeight: initial.canvasHeight,
        // Defensive parse: a malformed/legacy `layout_json` should not break
        // the designer — it should just open with an empty fields array so
        // the user can rebuild rather than seeing a console-stack page.
        fields: (() => {
            try { return JSON.parse(initial.layoutJson).fields || []; } catch (e) { return []; }
        })(),
        selectedField: null,
        fabricCanvas: null,
        init() {
            // Fabric canvas init lands in M3-T3; for now just expose the
            // empty hook so the view's `x-ref="canvas"` is still attached
            // to a live Fabric instance when the next task wires Text objects.
            if (typeof fabric !== 'undefined') {
                this.fabricCanvas = new fabric.Canvas(this.$refs.canvas);
            }
        },
        addField(text) {
            const field = {
                placeholder: text,
                x: 100, y: 100,
                font: 'Inter-Regular.ttf', size: 36,
                color: '#000000', rotation: 0,
            };
            this.fields.push(field);
            this.selectedField = field;
            // Visual placement on canvas — M3-T3 wires Fabric.Text objects.
        },
        syncFieldToCanvas() {
            // M3-T3 will mirror x-model changes back into the Fabric.Text instance.
        },
        deleteSelectedField() {
            if (!this.selectedField) return;
            this.fields = this.fields.filter(f => f !== this.selectedField);
            this.selectedField = null;
        },
        async save() {
            // M3-T4 wires this into a real POST. For now just log so a manual
            // smoke test of the designer surface confirms the payload shape
            // before T4 begins implementation.
            const payload = {
                name: this.name,
                description: this.description,
                canvas_width: this.canvasWidth,
                canvas_height: this.canvasHeight,
                layout_json: JSON.stringify({ fields: this.fields }),
            };
            console.log('Designer payload (M3-T4 will POST this):', payload);
            alert('Save will be wired in M3-T4. Payload logged to console.');
        },
    };
}
window.designer = designer;
