<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🗂️</text></svg>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

{{-- Tailwind CDN + Alpine keep this project dependency-free (no npm build step required). --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    paper: '#F6F5F0',
                    ink: '#1A2220',
                    forest: { DEFAULT: '#1F5D50', dark: '#154138', light: '#E7F0EC', 50: '#F2F8F5', 100: '#E7F0EC', 200: '#C9DFD6', 500: '#2F7A68', 600: '#1F5D50', 700: '#154138' },
                    gold: { DEFAULT: '#B8862E', light: '#FBF2DF' },
                    rust: { DEFAULT: '#B23A2E', light: '#FBEAE7' },
                    line: '#E2DED3',
                },
                fontFamily: {
                    serif: ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                },
                boxShadow: {
                    card: '0 1px 2px rgba(26,34,32,.04), 0 4px 16px rgba(26,34,32,.05)',
                    pop: '0 12px 40px rgba(26,34,32,.14)',
                },
            },
        },
    }
</script>
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    .font-serif { font-optical-sizing: auto; }
    :focus-visible { outline: 2px solid #1F5D50; outline-offset: 2px; }
    .bg-grid {
        background-image: linear-gradient(to right, rgba(31,93,80,.07) 1px, transparent 1px), linear-gradient(to bottom, rgba(31,93,80,.07) 1px, transparent 1px);
        background-size: 44px 44px;
        -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 40%, transparent 100%);
        mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 40%, transparent 100%);
    }
    /* Tailwind CDN has no forms plugin; give inputs a consistent base. */
    .input { width: 100%; border: 1px solid #E2DED3; border-radius: .5rem; background: #fff; padding: .6rem .8rem; font-size: .875rem; transition: box-shadow .15s, border-color .15s; }
    .input:focus { outline: none; border-color: #1F5D50; box-shadow: 0 0 0 3px rgba(31,93,80,.15); }
    select.input { padding-right: 2rem; }
    textarea.input { line-height: 1.5; }
</style>
