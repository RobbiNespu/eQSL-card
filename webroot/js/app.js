function cameraForm() {
    return {
        mode: 'default',
        captured: '',
        async startCamera() {
            this.mode = 'camera';
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                this.$refs.video.srcObject = stream;
            } catch (e) {
                alert('Camera unavailable: ' + e.message);
                this.mode = 'upload';
            }
        },
        capture() {
            const v = this.$refs.video, c = this.$refs.canvas;
            c.width = v.videoWidth; c.height = v.videoHeight;
            c.getContext('2d').drawImage(v, 0, 0);
            this.captured = c.toDataURL('image/jpeg', 0.9);
            v.srcObject?.getTracks().forEach(t => t.stop());
        },
    };
}
window.cameraForm = cameraForm;

function bulkRenderForm() {
    return {
        selected: [],
        modalOpen: false,
        started: false,
        finished: false,
        templateId: '',
        uploadId: '',
        done: 0,
        total: 0,
        jobToken: null,
        toggleOne(id, on) {
            id = parseInt(id, 10);
            if (on) {
                if (!this.selected.includes(id)) this.selected.push(id);
            } else {
                this.selected = this.selected.filter(x => x !== id);
            }
        },
        toggleAll(on) {
            const cbs = document.querySelectorAll('tbody input[type="checkbox"]');
            cbs.forEach(cb => {
                cb.checked = on;
                this.toggleOne(parseInt(cb.value, 10), on);
            });
        },
        openBulkModal() { this.modalOpen = true; this.started = false; this.finished = false; },
        closeModal() { this.modalOpen = false; },
        async startBulk() {
            this.started = true;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || document.cookie.match(/csrfToken=([^;]+)/)?.[1] || '';
            const body = new URLSearchParams();
            body.append('template_id', this.templateId);
            body.append('upload_id', this.uploadId);
            this.selected.forEach(id => body.append('qso_ids[]', id));
            const r = await fetch('/qsos/bulk-render', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body,
                credentials: 'same-origin',
            });
            const data = await r.json();
            this.jobToken = data.job_token;
            this.done = data.done;
            this.total = data.total;
            this.finished = data.finished;
            if (!this.finished) {
                await this.pollNext(csrf);
            }
        },
        async pollNext(csrf) {
            while (!this.finished) {
                const r = await fetch(`/qsos/bulk-render/${this.jobToken}/next`, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await r.json();
                this.done = data.done;
                this.total = data.total;
                this.finished = data.finished;
            }
        },
    };
}
window.bulkRenderForm = bulkRenderForm;
