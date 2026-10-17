<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', config('app.name', 'Opportunity Agent')) · {{ config('app.name', 'Opportunity Agent') }}</title>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
@php
    $user = auth()->user();
    $isOwner = $user && $user->canUseAgent();
    $appName = config('app.name', 'Opportunity Agent');

    // Everyone who signs in gets the job feed; the outreach tools are the owner's.
    $jobsNav = [
        ['Jobs', 'jobs.index', 'jobs.index'],
        ['Job interests', 'jobs.interests', 'jobs.interests*'],
        ['Resume', 'resume.upload', 'resume.*'],
        ['Profile', 'profile.edit', 'profile.*'],
    ];
    $agentNav = [
        ['Dashboard', 'agent.dashboard', 'agent.dashboard'],
        ['Opportunities', 'agent.opportunities.index', ['agent.opportunities.*', 'agent.outreach.*']],
        ['Services', 'agent.services.index', 'agent.services.*'],
        ['Projects', 'agent.projects.index', 'agent.projects.*'],
        ['CVs', 'agent.cvs.index', 'agent.cvs.*'],
        ['Signatures', 'agent.signatures.index', 'agent.signatures.*'],
        ['Providers', 'agent.providers.index', 'agent.providers.*'],
    ];
    $link = fn ($pattern) => request()->routeIs(...(array) $pattern)
        ? 'bg-slate-700 text-white' : 'hover:bg-slate-800';
@endphp
<div class="min-h-screen lg:flex">
    {{-- Mobile bar --}}
    <header class="flex items-center justify-between bg-slate-900 px-4 py-3 text-white lg:hidden">
        <a href="{{ $user ? route($user->homeRoute()) : route('home') }}" class="font-semibold">{{ $appName }}</a>
        @auth
            <button type="button" x-on:click="open = !open" class="rounded p-1 hover:bg-slate-800" aria-label="Menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        @endauth
    </header>

    @auth
        <aside x-bind:class="open ? 'block' : 'hidden'" class="hidden w-full shrink-0 bg-slate-900 text-slate-200 lg:block lg:min-h-screen lg:w-60">
            <a href="{{ route($user->homeRoute()) }}" class="hidden px-5 py-5 text-lg font-semibold text-white lg:block">{{ $appName }}</a>
            <nav class="space-y-1 px-3 py-2">
                @if ($isOwner)
                    @foreach ($agentNav as [$label, $route, $pattern])
                        <a href="{{ route($route) }}" class="block rounded-md px-3 py-2 text-sm {{ $link($pattern) }}">{{ $label }}</a>
                    @endforeach
                    <div class="mt-4 px-3 pb-1 text-xs uppercase tracking-wide text-slate-500">Job feed</div>
                @endif
                @foreach ($jobsNav as [$label, $route, $pattern])
                    <a href="{{ route($route) }}" class="block rounded-md px-3 py-2 text-sm {{ $link($pattern) }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="px-3 py-4">
                <p class="truncate px-3 pb-2 text-xs text-slate-500">{{ $user->email }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-800">Sign out</button>
                </form>
            </div>
        </aside>
    @else
        <aside class="hidden"></aside>
    @endauth

    <main class="min-w-0 flex-1 p-4 sm:p-8">
        <div class="mx-auto @yield('container', 'max-w-5xl')">
            <div id="flash">
                @if (session('status'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-cloak
                         class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p class="font-medium">Please fix the following:</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif
            </div>
            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
