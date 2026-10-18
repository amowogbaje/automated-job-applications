<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🗂️</text></svg>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
                    paper: '#f8fafc',
                    ink: '#1e293b',
                    forest: { DEFAULT: '#4f46e5', dark: '#4338ca', light: '#eef2ff', 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' },
                    gold: { DEFAULT: '#d97706', light: '#fffbeb' },
                    rust: { DEFAULT: '#dc2626', light: '#fef2f2' },
                    line: '#e2e8f0',
                },
                fontFamily: {
                    serif: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
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
    :focus-visible { outline: 2px solid #4f46e5; outline-offset: 2px; }
    .bg-grid {
        background-image: linear-gradient(to right, rgba(79,70,229,.07) 1px, transparent 1px), linear-gradient(to bottom, rgba(79,70,229,.07) 1px, transparent 1px);
        background-size: 44px 44px;
        -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 40%, transparent 100%);
        mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 40%, transparent 100%);
    }
    /* Tailwind CDN has no forms plugin; give inputs a consistent base. */
    .input { width: 100%; border: 1px solid #cbd5e1; border-radius: .375rem; background: #fff; padding: .5rem .75rem; font-size: .875rem; transition: box-shadow .15s, border-color .15s; }
    .input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 1px #6366f1; }
    select.input { padding-right: 2rem; }
    textarea.input { line-height: 1.5; }
</style>
