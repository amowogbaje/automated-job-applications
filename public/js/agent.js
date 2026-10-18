// Alpine helpers for the Opportunity Agent pages (loaded globally so in-place page swaps keep working).
window.researchPanel = function (initial) {
    return {
        run: initial, timer: null,
        active() { return this.run && ['pending', 'running'].includes(this.run.status); },
        init() { if (this.active()) this.poll(); },
        destroy() { clearInterval(this.timer); },
        poll() {
            this.timer = setInterval(async () => {
                try {
                    const r = await fetch(this.run.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!r.ok) return;
                    this.run = await r.json();
                    if (!this.active()) {
                        clearInterval(this.timer);
                        window.Live ? window.Live.refresh() : location.reload();
                    }
                } catch (e) {}
            }, 4000);
        },
    };
};

// Discovery run page: poll until the run finishes, then refresh in place.
window.discoveryPanel = function (initial) {
    return {
        run: initial, timer: null,
        active() { return this.run && ['pending', 'running'].includes(this.run.status); },
        init() { if (this.active()) this.poll(); },
        destroy() { clearInterval(this.timer); },
        poll() {
            this.timer = setInterval(async () => {
                try {
                    const r = await fetch(this.run.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!r.ok) return;
                    this.run = await r.json();
                    if (!this.active()) {
                        clearInterval(this.timer);
                        window.Live ? window.Live.refresh() : location.reload();
                    }
                } catch (e) {}
            }, 4000);
        },
    };
};

// ---- Outreach Studio ---------------------------------------------------------------------------------
(() => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const call = async (method, url, body) => {
        const res = await fetch(url, {
            method, credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const first = data.errors ? Object.values(data.errors)[0][0] : null;
            const err = new Error(first || data.message || `Request failed (${res.status})`);
            err.status = res.status;
            throw err;
        }
        return data;
    };

    window.copyText = async (text) => {
        try { await navigator.clipboard.writeText(text); return true; } catch (e) {}
        const t = document.createElement('textarea');
        t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
        document.body.appendChild(t); t.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) {}
        t.remove();
        return ok;
    };

    const refresh = (channel) => (window.Live ? window.Live.refreshParts(`#tabs, #grid-${channel}`) : location.reload());

    // The whole studio page: options per channel, generating, polling the run.
    window.studio = (init) => ({
        channel: init.channel, opts: init.opts, busy: false, working: null, error: null, timer: null,
        destroy() { clearInterval(this.timer); },
        async generate(channel) {
            this.busy = true; this.working = channel; this.error = null;
            const o = this.opts[channel];
            try {
                const { run } = await call('POST', init.generateUrl, {
                    channel, contact_id: o.contact_id || null, service_id: o.service_id || null,
                    project_ids: o.project_ids, cv_version_id: o.cv_version_id || null,
                    notes: o.notes || null, ignore_restrictions: !!o.ignore_restrictions,
                });
                await this.poll(run);
                await refresh(channel);
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
            }
        },
        poll(run) {
            return new Promise((resolve, reject) => {
                const check = (r) => {
                    if (r.status === 'completed') { resolve(r); return true; }
                    if (r.status === 'failed') { reject(new Error(r.error || 'The drafts could not be written.')); return true; }
                    return false;
                };
                if (check(run)) return;
                let tries = 0;
                this.timer = setInterval(async () => {
                    try {
                        const r = await call('GET', run.url);
                        if (check(r)) clearInterval(this.timer);
                        else if (++tries > 90) { clearInterval(this.timer); reject(new Error('Still running after 3 minutes. Check that the queue cron is running, or set RESEARCH_RUN_MODE=sync.')); }
                    } catch (e) { /* transient, keep polling */ }
                }, 2000);
            });
        },
    });

    // One draft card: autosave, copy, select, mark sent, discard.
    window.draftCard = (d) => ({
        ...d, saving: false, savedAt: false, message: '', copied: false, copiedSubject: false,
        askSent: false, followDays: 5, sentNotes: '', timer: null, pending: null,
        get words() { return (this.body.trim().match(/\S+/g) || []).length; },
        full() {
            const sig = (this.sigBodies && this.sigBodies[this.signature_id]) || '';
            return this.body.trim() + (sig ? '\n\n' + sig.trim() : '');
        },
        queueSave(now = false) {
            clearTimeout(this.timer);
            this.savedAt = false;
            this.pending = new Promise((resolve) => {
                this.timer = setTimeout(async () => { await this.save(); resolve(); }, now ? 0 : 800);
            });
            return this.pending;
        },
        async flush() {
            if (this.timer) { clearTimeout(this.timer); this.timer = null; await this.save(); }
        },
        async save() {
            this.timer = null; this.saving = true; this.message = '';
            try {
                const r = await call('PATCH', this.urls.update, { subject: this.hasSubject ? this.subject : null, body: this.body, signature_id: this.signature_id || null });
                this.status = r.status; this.edited = r.edited; this.savedAt = true;
            } catch (e) { this.message = e.message; } finally { this.saving = false; }
        },
        async copy() {
            await this.flush();
            if (await window.copyText(this.full())) {
                this.copied = true; setTimeout(() => (this.copied = false), 1800);
                call('POST', this.urls.copied).catch(() => {});
            } else { this.message = 'Could not copy automatically. Select the text and copy it by hand.'; }
        },
        async copySubject() {
            await this.flush();
            if (await window.copyText(this.subject || '')) { this.copiedSubject = true; setTimeout(() => (this.copiedSubject = false), 1800); }
        },
        async act(kind) {
            this.message = '';
            try {
                await this.flush();
                if (kind === 'sent') await call('POST', this.urls.sent, { notes: this.sentNotes || null, follow_up_days: this.followDays });
                else await call('POST', this.urls[kind]);
                await refresh(this.channel);
            } catch (e) { this.message = e.message; }
        },
    });
})();
