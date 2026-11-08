<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Agent">
<meta name="color-scheme" content="light dark">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#f2f2f7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
<link rel="apple-touch-icon" href="/icon.svg">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🗂️</text></svg>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> {{-- fallback font for non-Apple devices --}}

{{-- Tailwind CDN + Alpine keep this project dependency-free (no npm build step required).
     The whole app uses the Opportunity Agent look (slate + indigo). The older class names used by the
     job-board views (paper / ink / forest / gold / rust / line) are mapped onto that palette here so one
     theme drives every page. --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    paper: '#f2f2f7',
                    ink: '#1c1c1e',
                    forest: { DEFAULT: '#007aff', dark: '#0062cc', light: '#e5f1ff', 50: '#eaf3ff', 100: '#d6e9ff', 200: '#cfe4ff', 500: '#2b8cff', 600: '#007aff', 700: '#0062cc' },
                    gold: { DEFAULT: '#d97706', light: '#fffbeb' },
                    rust: { DEFAULT: '#dc2626', light: '#fef2f2' },
                    line: '#e5e5ea',
                },
                fontFamily: {
                    serif: ['-apple-system', 'BlinkMacSystemFont', 'SF Pro Text', 'Inter', 'system-ui', 'sans-serif'],
                    sans: ['-apple-system', 'BlinkMacSystemFont', 'SF Pro Text', 'Inter', 'system-ui', 'sans-serif'],
                },
                boxShadow: {
                    card: '0 1px 2px rgba(15,23,42,.05)',
                    pop: '0 12px 40px rgba(15,23,42,.16)',
                },
            },
        },
    }
</script>
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style type="text/tailwindcss">
    @layer components {
        .label { @apply mb-1 block text-sm font-medium text-slate-700; }
        .btn { @apply inline-flex items-center rounded-md px-3 py-2 text-sm font-medium transition-colors; }
        .btn-primary { @apply btn bg-forest text-white hover:bg-forest-dark; }
        .btn-secondary { @apply btn border border-line bg-white text-ink/80 hover:bg-black/[.04]; }
        .btn-danger { @apply btn text-rust hover:bg-rust-light; }
        .card { @apply rounded-lg border border-line bg-white shadow-card; }
        .badge { @apply inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium; }
    }
</style>
<script defer src="{{ asset('js/live.js') }}?v=2"></script>
<script defer src="{{ asset('js/agent.js') }}?v=2"></script>
<style>
    [x-cloak] { display: none !important; }
    .bg-grid {
        background-image: linear-gradient(to right, rgba(79,70,229,.07) 1px, transparent 1px), linear-gradient(to bottom, rgba(79,70,229,.07) 1px, transparent 1px);
        background-size: 44px 44px;
        -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 40%, transparent 100%);
        mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 40%, transparent 100%);
    }
    /* ---- iOS look: one set of tokens, light + dark (follows the phone's setting) ---- */
    :root { --bg:#f2f2f7; --card:#ffffff; --fill:#f2f2f7; --line:#e5e5ea; --text:#1c1c1e; --sub:#6c6c70; --blue:#007aff; --glass:rgba(249,249,251,.82); }
    @media (prefers-color-scheme: dark) { :root { --bg:#000000; --card:#1c1c1e; --fill:#2c2c2e; --line:#38383a; --text:#f5f5f7; --sub:#98989f; --blue:#0a84ff; --glass:rgba(28,28,30,.82); } }
    html { -webkit-text-size-adjust:100%; -webkit-tap-highlight-color:transparent; }
    html body { background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text",Inter,system-ui,sans-serif; -webkit-font-smoothing:antialiased; }
    :focus-visible { outline:2px solid var(--blue); outline-offset:2px; }
    .glass { background:var(--glass); -webkit-backdrop-filter:saturate(180%) blur(20px); backdrop-filter:saturate(180%) blur(20px); }
    .input { width:100%; border:1px solid transparent; border-radius:10px; background:var(--fill); color:var(--text); padding:.6rem .8rem; font-size:.9375rem; transition:box-shadow .15s,border-color .15s; }
    .input:focus { outline:none; border-color:var(--blue); box-shadow:0 0 0 3px rgba(0,122,255,.22); }
    .input::placeholder { color:var(--sub); opacity:.7; }
    select.input { padding-right:2rem; }
    textarea.input { line-height:1.5; }
    html .card { border:0; border-radius:14px; background:var(--card); box-shadow:0 1px 2px rgba(0,0,0,.04),0 0 0 .5px rgba(0,0,0,.05); }
    html a.card { transition:transform .12s ease, box-shadow .15s; }
    html a.card:active, html .btn:active { transform:scale(.97); }
    html .btn { border-radius:10px; min-height:36px; justify-content:center; font-weight:600; }
    html .btn-primary { background:var(--blue); color:#fff; }
    html .btn-secondary { background:var(--fill); border:0; color:var(--blue); }
    html .btn-danger { color:#ff3b30; }
    html .badge { font-weight:600; }
    html main h1 { font-size:1.75rem; font-weight:700; letter-spacing:-.02em; }
    html .divide-y > :not([hidden]) ~ :not([hidden]) { border-color:var(--line); }
    code { background:var(--fill); color:var(--text); }
    @media (max-width:1023px) {
        .input { font-size:16px; }          /* stops iOS zooming into fields */
        html .btn { min-height:44px; padding-left:1rem; padding-right:1rem; }
    }
    .safe-b { padding-bottom:env(safe-area-inset-bottom); }
    .safe-t { padding-top:env(safe-area-inset-top); }
    .sheet-enter { animation:sheet .25s cubic-bezier(.2,.8,.2,1); }
    @keyframes sheet { from { transform:translateY(100%); } to { transform:none; } }

    @media (prefers-color-scheme: dark) {
        html .bg-white, html .bg-slate-50 { background-color:var(--card); }
        html .bg-slate-100, html .bg-slate-200 { background-color:var(--fill); }
        html .text-slate-900, html .text-slate-800, html .text-slate-700, html .text-slate-600, html .text-ink, html .text-black { color:var(--text); }
        html .text-slate-500, html .text-slate-400 { color:var(--sub); }
        html .border-slate-100, html .border-slate-200, html .border-slate-300, html .border-line { border-color:var(--line); }
        html .hover\:bg-slate-50:hover, html .hover\:bg-black\/\[\.04\]:hover { background-color:var(--fill); }
        html .bg-green-100, html .bg-green-50 { background-color:rgba(48,209,88,.18); } html .text-green-700, html .text-green-800 { color:#30d158; }
        html .bg-red-100, html .bg-red-50 { background-color:rgba(255,69,58,.18); } html .text-red-700, html .text-red-600, html .text-red-800 { color:#ff6961; }
        html .bg-amber-50, html .bg-amber-100, html .bg-yellow-50 { background-color:rgba(255,159,10,.18); } html .text-amber-700, html .text-amber-800 { color:#ff9f0a; }
        html .bg-blue-100, html .bg-sky-100, html .bg-teal-100, html .bg-violet-100, html .bg-forest-50, html .bg-forest-200, html .bg-forest-light { background-color:rgba(10,132,255,.2); }
        html .text-blue-700, html .text-sky-700, html .text-teal-700, html .text-violet-700, html .text-forest-700, html .text-forest-600 { color:#64a8ff; }
        html .border-green-200, html .border-red-200, html .border-amber-300 { border-color:var(--line); }
    }
    @media (prefers-reduced-motion: reduce) { *, ::before, ::after { animation-duration:.01ms !important; transition-duration:.01ms !important; } }
</style>
