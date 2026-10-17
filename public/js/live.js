/*
 * live.js — no-build "stay on the page" layer (cPanel friendly, ~no dependencies).
 *
 * Without any markup changes it turns normal link clicks and form posts into fetch() calls and swaps
 * only <header> + <main>, so the shell, fonts, Tailwind and Alpine are NOT reloaded on every click.
 * Anything it can't handle safely falls back to a normal browser navigation, so it can't strand you.
 *
 * Opt-in / opt-out attributes
 *   data-no-live                 on a link/form: always do a normal navigation (downloads, PDFs…)
 *   data-live="#a, #b"           on a link/form: swap only these elements (scroll position is kept)
 *   data-live-scope="#a"         on a container: links inside it swap only #a (pagination, tabs)
 *   data-live-remove="article"   on a form: on success remove that closest ancestor, no re-render
 *   data-live-auto               on a GET form: re-run it on change / while typing (filters)
 */
(() => {
    if (window.Live) return;

    const SHELL = ['header', 'aside', 'main'];
    const SKIP_PATH = /^\/(login|register|logout|forgot-password|reset-password|webhooks)(\/|$)/;
    let seq = 0;

    // ---- progress bar -------------------------------------------------------------------------------
    const bar = document.createElement('div');
    bar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0;background:#4f46e5;z-index:99999;opacity:0;transition:width .25s,opacity .25s;pointer-events:none';
    const mountBar = () => document.body && !bar.isConnected && document.body.appendChild(bar);
    let barTimer;
    const busy = (on) => {
        mountBar();
        clearTimeout(barTimer);
        if (on) { bar.style.opacity = '1'; bar.style.width = '70%'; }
        else { bar.style.width = '100%'; barTimer = setTimeout(() => { bar.style.opacity = '0'; bar.style.width = '0'; }, 220); }
    };

    // ---- helpers ------------------------------------------------------------------------------------
    const abs = (u) => new URL(u, location.href);
    const internal = (u) => u.origin === location.origin && !SKIP_PATH.test(u.pathname);
    const hardGo = (url) => location.assign(url);

    async function request(url, init = {}) {
        const res = await fetch(url, {
            credentials: 'same-origin',
            redirect: 'follow',
            ...init,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html,application/xhtml+xml', ...(init.headers || {}) },
        });
        const type = res.headers.get('content-type') || '';
        if (!type.includes('html')) {
            try { res.body && res.body.cancel(); } catch (e) {}
            return { res, doc: null };
        }
        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        return { res, doc };
    }

    // A page is swappable when it has the shell and no <script> in the body (scripts parsed by
    // DOMParser never run, so such pages need a real load).
    // The mobile menu state lives on <body> (never swapped), so close it after every navigation.
    const closeMenu = () => { try { window.Alpine && (Alpine.$data(document.body).open = false); } catch (e) {} };
    const swappable = (doc) => doc && SHELL.every((s) => doc.querySelector(s)) && !doc.body.querySelector('script');

    function syncHead(doc) {
        const t = doc.querySelector('meta[name="csrf-token"]');
        const cur = document.querySelector('meta[name="csrf-token"]');
        if (t && cur) cur.content = t.content;
        if (doc.title) document.title = doc.title;
    }

    function replaceEl(oldEl, newEl) {
        oldEl.replaceWith(document.importNode(newEl, true));
    }

    function swapShell(doc) {
        syncHead(doc);
        SHELL.forEach((s) => replaceEl(document.querySelector(s), doc.querySelector(s)));
    }

    function swapParts(doc, selectors) {
        syncHead(doc);
        const list = [...new Set([...selectors.split(',').map((s) => s.trim()).filter(Boolean), '#flash'])];
        let missing = false;
        list.forEach((sel) => {
            const a = document.querySelector(sel), b = doc.querySelector(sel);
            if (a && b) replaceEl(a, b);
            else if (sel !== '#flash') missing = true;
        });
        return !missing;
    }

    // ---- navigation (swap the whole shell) -----------------------------------------------------------
    async function visit(url, { push = true, init } = {}) {
        const my = ++seq;
        busy(true);
        try {
            const { res, doc } = await request(url, init);
            if (my !== seq) return;           // a newer click won
            const final = new URL(res.url, location.href);
            if (!doc || !swappable(doc) || final.origin !== location.origin || SKIP_PATH.test(final.pathname)) {
                busy(false);
                return hardGo(final.href);
            }
            swapShell(doc);
            closeMenu();
            const moved = final.href !== location.href;
            if (push && moved) history.pushState({ live: 1 }, '', final.href);
            if (moved) window.scrollTo(0, 0); // same page after a form post: stay where you were
            document.dispatchEvent(new CustomEvent('live:load'));
            busy(false);
        } catch (e) {
            busy(false);
            hardGo(typeof url === 'string' ? url : location.href);
        }
    }

    // ---- partial swaps (links/forms with data-live, or inside data-live-scope) -------------------------
    async function partial(url, selectors, { init, push = false, remove } = {}) {
        const my = ++seq;
        const targets = selectors.split(',').map((s) => document.querySelector(s.trim())).filter(Boolean);
        targets.forEach((t) => { t.setAttribute('aria-busy', 'true'); t.style.opacity = '.55'; });
        busy(true);
        try {
            const { res, doc } = await request(url, init);
            if (my !== seq) return true;
            const final = new URL(res.url, location.href);
            if (!doc || final.origin !== location.origin) throw new Error('not html');
            if (remove) { remove.remove(); swapParts(doc, '#flash'); }
            else if (!swapParts(doc, selectors)) throw new Error('target missing');
            if (push && final.href !== location.href) history.pushState({ live: 1 }, '', final.href);
            document.dispatchEvent(new CustomEvent('live:load'));
            return true;
        } catch (e) {
            return false;
        } finally {
            targets.forEach((t) => { t.removeAttribute('aria-busy'); t.style.opacity = ''; });
            busy(false);
        }
    }

    // ---- link clicks ---------------------------------------------------------------------------------
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.hasAttribute('data-no-live') || a.hasAttribute('download')) return;
        if (a.target && a.target !== '_self') return;
        const raw = a.getAttribute('href');
        if (!raw || raw.startsWith('#') || /^(mailto:|tel:|javascript:)/i.test(raw)) return;
        const url = abs(raw);
        if (!internal(url)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return; // in-page anchor

        const scope = a.closest('[data-live-scope]');
        const sel = a.getAttribute('data-live') || (scope && scope.getAttribute('data-live-scope'));
        e.preventDefault();
        if (sel) {
            partial(url.href, sel, { push: true }).then((ok) => { if (!ok) hardGo(url.href); });
        } else {
            visit(url.href);
        }
    });

    // ---- form submits --------------------------------------------------------------------------------
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (e.defaultPrevented || !(form instanceof HTMLFormElement) || form.hasAttribute('data-no-live')) return;
        if (form.target && form.target !== '_self') return;
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const action = abs(form.getAttribute('action') || location.href);
        if (!internal(action)) return;

        const submitter = e.submitter || null;
        const data = new FormData(form, submitter);
        e.preventDefault();
        if (form.dataset.liveBusy) return;
        form.dataset.liveBusy = '1';
        if (submitter) submitter.disabled = true;

        let url = action.href, init = { method };
        if (method === 'GET') {
            const q = new URLSearchParams(data);
            url = action.origin + action.pathname + (q.toString() ? '?' + q : '');
        } else {
            init = { method: 'POST', body: data }; // _method spoofing travels inside the body
        }

        const sel = form.getAttribute('data-live');
        const removeSel = form.getAttribute('data-live-remove');
        const remove = removeSel ? form.closest(removeSel) : null;
        const done = () => { delete form.dataset.liveBusy; if (submitter) submitter.disabled = false; };

        if (remove) {
            const ok = await partial(url, '#flash', { init, remove });
            done();
            if (!ok) hardGo(location.href);
            return;
        }
        if (sel) {
            const ok = await partial(url, sel, { init, push: method === 'GET' });
            done();
            if (!ok) hardGo(location.href);
            return;
        }
        await visit(url, { init, push: true });
        done();
    });

    // ---- auto-submitting filter forms ----------------------------------------------------------------
    let typing;
    const autoSubmit = (form) => form.requestSubmit ? form.requestSubmit() : form.submit();
    document.addEventListener('change', (e) => {
        const f = e.target.closest && e.target.closest('form[data-live-auto]');
        if (f && !/^(text|search)$/.test(e.target.type || '')) autoSubmit(f);
    });
    document.addEventListener('input', (e) => {
        const f = e.target.closest && e.target.closest('form[data-live-auto]');
        if (f && /^(text|search)$/.test(e.target.type || '')) { clearTimeout(typing); typing = setTimeout(() => autoSubmit(f), 350); }
    });

    // ---- back / forward ------------------------------------------------------------------------------
    window.addEventListener('popstate', () => visit(location.href, { push: false }));

    window.Live = {
        visit: (u) => visit(abs(u).href),
        refresh: () => visit(location.href, { push: false }),
        // Re-fetch the current page and swap only these selectors (keeps typed text elsewhere on the page).
        refreshParts: (selectors) => partial(location.href, selectors).then((ok) => { if (!ok) visit(location.href, { push: false }); }),
    };
})();
