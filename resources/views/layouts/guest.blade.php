<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', 'Sign in') — {{ config('app.name', 'Job Feed') }}</title>
</head>
<body class="bg-paper text-ink font-sans min-h-screen antialiased">
    <div class="min-h-screen grid lg:grid-cols-2">

        {{-- Brand panel --}}
        <aside class="hidden lg:flex relative overflow-hidden bg-forest-dark text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 15%, #2F7A68 0, transparent 45%), radial-gradient(circle at 85% 85%, #B8862E 0, transparent 40%);"></div>
            <a href="{{ route('home') }}" class="relative flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-lg bg-white/10 grid place-items-center"><x-icon name="briefcase" class="w-5 h-5" /></span>
                <span class="font-serif text-xl font-semibold tracking-tight">{{ config('app.name', 'Job Feed') }}</span>
            </a>

            <div class="relative max-w-md">
                <h2 class="font-serif text-4xl font-semibold leading-tight">Spend your hours on interviews, not application forms.</h2>
                <ul class="mt-8 space-y-4 text-white/85 text-sm">
                    <li class="flex gap-3"><x-icon name="target" class="w-5 h-5 shrink-0 text-gold" /> Jobs ranked against your real skills, with the reason shown.</li>
                    <li class="flex gap-3"><x-icon name="doc" class="w-5 h-5 shrink-0 text-gold" /> A tailored resume and cover letter drafted for each role.</li>
                    <li class="flex gap-3"><x-icon name="shield" class="w-5 h-5 shrink-0 text-gold" /> You set the limits. It never guesses answers that matter.</li>
                </ul>
            </div>

            <p class="relative text-xs text-white/50">Your data stays on this install. No ads, no selling your CV.</p>
        </aside>

        {{-- Form panel --}}
        <div class="flex items-center justify-center px-5 py-10">
            <div class="w-full max-w-sm">
                <a href="{{ route('home') }}" class="lg:hidden flex items-center justify-center gap-2.5 mb-8">
                    <span class="w-9 h-9 rounded-lg bg-forest text-white grid place-items-center"><x-icon name="briefcase" class="w-5 h-5" /></span>
                    <span class="font-serif text-xl font-semibold tracking-tight">{{ config('app.name', 'Job Feed') }}</span>
                </a>

                @if (session('status'))
                    <div class="mb-5 flex items-start gap-2.5 rounded-xl bg-forest-50 border border-forest-200 text-forest-dark px-4 py-3 text-sm">
                        <x-icon name="check" class="w-4 h-4 mt-0.5 shrink-0" /> <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-5 flex items-start gap-2.5 rounded-xl bg-rust-light border border-rust/20 text-rust px-4 py-3 text-sm">
                        <x-icon name="alert" class="w-4 h-4 mt-0.5 shrink-0" />
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
