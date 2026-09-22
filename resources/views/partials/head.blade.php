<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🗂️</text></svg>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

{{-- Tailwind CDN keeps this project dependency-free (no npm build step required). --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    paper: '#F6F4EE',
                    ink: '#1C2321',
                    forest: { DEFAULT: '#1F5D50', dark: '#163F37', light: '#E9F0EC' },
                    gold: { DEFAULT: '#B8862E', light: '#FBF2DF' },
                    rust: { DEFAULT: '#B23A2E', light: '#FBEAE7' },
                    line: '#DDD8CC',
                },
                fontFamily: {
                    serif: ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                },
            },
        },
    }
</script>
<style>
    /* Fraunces at optical size 9 looks thin at display sizes; nudge it toward its intended range. */
    .font-serif { font-optical-sizing: auto; }
    :focus-visible { outline: 2px solid #1F5D50; outline-offset: 2px; }
</style>
