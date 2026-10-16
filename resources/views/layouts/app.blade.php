<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Opportunity Agent</title>
    {{-- No build step: Tailwind + Alpine from CDN (cPanel friendly) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <style type="text/tailwindcss">
        @layer components {
            .input { @apply block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500; }
            .label { @apply mb-1 block text-sm font-medium text-slate-700; }
            .btn { @apply inline-flex items-center rounded-md px-3 py-2 text-sm font-medium; }
            .btn-primary { @apply btn bg-indigo-600 text-white hover:bg-indigo-700; }
            .btn-secondary { @apply btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50; }
            .btn-danger { @apply btn text-red-600 hover:bg-red-50; }
            .card { @apply rounded-lg border border-slate-200 bg-white shadow-sm; }
            .badge { @apply inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium; }
        }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="bg-slate-50 text-slate-800">
@php
    $nav = [
        ['Dashboard', 'dashboard', 'dashboard'],
        ['Opportunities', 'opportunities.index', 'opportunities.*'],
        ['Services', 'services.index', 'services.*'],
        ['Projects', 'projects.index', 'projects.*'],
        ['CVs', 'cvs.index', 'cvs.*'],
        ['Signatures', 'signatures.index', 'signatures.*'],
        ['Providers', 'providers.index', 'providers.*'],
    ];
@endphp
<div x-data="{ open: false }" class="min-h-screen lg:flex">
    {{-- Mobile bar --}}
    <div class="flex items-center justify-between bg-slate-900 px-4 py-3 text-white lg:hidden">
        <span class="font-semibold">Opportunity Agent</span>
        <button @click="open = !open" class="rounded p-1 hover:bg-slate-800" aria-label="Menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <aside :class="open ? 'block' : 'hidden'" class="w-full shrink-0 bg-slate-900 text-slate-200 lg:block lg:min-h-screen lg:w-60">
        <div class="hidden px-5 py-5 text-lg font-semibold text-white lg:block">Opportunity Agent</div>
        <nav class="space-y-1 px-3 py-2">
            @foreach ($nav as [$label, $route, $pattern])
                <a href="{{ route($route) }}"
                   class="block rounded-md px-3 py-2 text-sm {{ request()->routeIs($pattern) ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">{{ $label }}</a>
            @endforeach
            <div class="mt-4 px-3 pb-1 text-xs uppercase tracking-wide text-slate-500">Coming next</div>
            @foreach (['Research runs', 'Outreach Studio'] as $soon)
                <span class="block cursor-not-allowed rounded-md px-3 py-2 text-sm text-slate-500">{{ $soon }}</span>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="px-3 py-4">
            @csrf
            <button class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-800">Sign out</button>
        </form>
    </aside>

    <main class="flex-1 p-4 sm:p-8">
        <div class="mx-auto max-w-5xl">
            @if (session('status'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-cloak
                     class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-medium">Please fix the following:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
