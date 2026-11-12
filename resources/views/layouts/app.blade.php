<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', config('app.name', 'Opportunity Agent')) · {{ config('app.name', 'Opportunity Agent') }}</title>
</head>
<body class="font-sans antialiased" x-data="{ more: false, quick: false }" x-on:keydown.escape.window="more = false; quick = false">
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
        ['Discovery', 'agent.discovery.index', 'agent.discovery.*'],
        ['Opportunities', 'agent.opportunities.index', ['agent.opportunities.*', 'agent.outreach.*']],
        ['Leads', 'agent.leads.index', 'agent.leads.*'],
        ['Customers', 'agent.customers.index', 'agent.customers.*'],
        ['Documents', 'agent.documents.index', 'agent.documents.*'],
        ['Services', 'agent.services.index', 'agent.services.*'],
        ['Projects', 'agent.projects.index', 'agent.projects.*'],
        ['CVs', 'agent.cvs.index', 'agent.cvs.*'],
        ['Signatures', 'agent.signatures.index', 'agent.signatures.*'],
        ['Providers', 'agent.providers.index', 'agent.providers.*'],
    ];
    $unread = $isOwner ? \App\Models\AgentNotification::unreadCount() : 0;

    // Sidebar (desktop): soft, light, blue when selected.
    $link = fn ($pattern) => request()->routeIs(...(array) $pattern)
        ? 'bg-forest-100 text-forest-700 font-semibold' : 'text-slate-600 hover:bg-black/5';

    // Bottom tab bar (phone). The last tab opens a sheet with everything else.
    $icons = [
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'leads' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'brief' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'bell' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        'spark' => 'M5 3v4M3 5h4M6 17v4M4 19h4M13 3l2.5 5.5L21 11l-5.5 2.5L13 19l-2.5-5.5L5 11l5.5-2.5L13 3z',
        'doc' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'user' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'dots' => 'M5 12h.01M12 12h.01M19 12h.01',
    ];
    $tabs = $isOwner
        ? [['Home', 'agent.dashboard', 'agent.dashboard', 'home'], ['Leads', 'agent.leads.index', 'agent.leads.*', 'leads'],
           ['Pipeline', 'agent.opportunities.index', ['agent.opportunities.*', 'agent.outreach.*'], 'brief'], ['Alerts', 'agent.notifications.index', 'agent.notifications.*', 'bell']]
        : [['Jobs', 'jobs.index', 'jobs.index', 'brief'], ['Interests', 'jobs.interests', 'jobs.interests*', 'spark'],
           ['Resume', 'resume.upload', 'resume.*', 'doc'], ['Profile', 'profile.edit', 'profile.*', 'user']];
    $tabOn = fn ($pattern) => request()->routeIs(...(array) $pattern);
    // Pages reached from the tab bar are "roots"; anything deeper gets a Back button.
    $isRoot = request()->routeIs('agent.dashboard', 'agent.leads.index', 'agent.opportunities.index', 'agent.notifications.index', 'jobs.index', 'jobs.interests', 'resume.upload', 'profile.edit');
    $pageTitle = trim($__env->yieldContent('title', $appName));
@endphp
<div class="min-h-screen lg:flex">
    {{-- Phone: translucent top bar with Back, page title and bell --}}
    <header class="glass safe-t sticky top-0 z-30 border-b border-black/5 lg:hidden">
        <div class="relative flex h-11 items-center px-2">
            <div class="z-10 w-20">
                @if (! $isRoot)
                    <button type="button" onclick="history.length > 1 ? history.back() : location.href='{{ $user ? route($user->homeRoute()) : url('/') }}'" class="flex items-center gap-0.5 rounded-lg px-1 py-2 text-[15px] font-medium text-forest" aria-label="Back">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>Back</button>
                @endif
            </div>
            <div class="pointer-events-none absolute inset-x-20 truncate text-center text-[15px] font-semibold">{{ $isRoot ? $appName : $pageTitle }}</div>
            <div class="z-10 ml-auto flex w-20 justify-end">
                @if ($isOwner)
                    <a href="{{ route('agent.notifications.index') }}" class="relative rounded-lg p-2 text-slate-600" aria-label="Alerts">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icons['bell'] }}"/></svg>
                        @if ($unread)<span class="absolute right-0.5 top-0.5 rounded-full bg-[#ff3b30] px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $unread }}</span>@endif
                    </a>
                @endif
            </div>
        </div>
    </header>

    @auth
        {{-- Desktop sidebar --}}
        <aside class="hidden w-60 shrink-0 border-r border-black/5 bg-white/60 lg:block lg:min-h-screen">
            <a href="{{ route($user->homeRoute()) }}" class="block px-5 py-5 text-lg font-bold tracking-tight">{{ $appName }}</a>
            @if ($isOwner)
                <div class="flex gap-2 px-3 pb-2">
                    <a href="{{ route('agent.leads.import') }}" class="btn btn-primary flex-1 text-sm">+ Leads</a>
                    <a href="{{ route('agent.opportunities.create') }}" class="btn btn-secondary flex-1 text-sm">+ Opportunity</a>
                </div>
            @endif
            <nav class="space-y-0.5 px-3 py-2">
                @if ($isOwner)
                    @foreach ($agentNav as [$label, $route, $pattern])
                        <a href="{{ route($route) }}" class="block rounded-lg px-3 py-2 text-sm {{ $link($pattern) }}">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('agent.notifications.index') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ $link('agent.notifications.*') }}"><span>Notifications</span>@if ($unread)<span class="rounded-full bg-[#ff3b30] px-2 text-xs font-semibold text-white">{{ $unread }}</span>@endif</a>
                    <div class="px-3 pb-1 pt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Job feed</div>
                @endif
                @foreach ($jobsNav as [$label, $route, $pattern])
                    <a href="{{ route($route) }}" class="block rounded-lg px-3 py-2 text-sm {{ $link($pattern) }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="px-3 py-4">
                <p class="truncate px-3 pb-2 text-xs text-slate-400">{{ $user->email }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-600 hover:bg-black/5">Sign out</button>
                </form>
            </div>
        </aside>
    @else
        <aside class="hidden"></aside>
    @endauth

    <main class="min-w-0 flex-1 p-4 pb-[calc(6rem+env(safe-area-inset-bottom))] sm:p-8 lg:pb-8">
        <div class="mx-auto @yield('container', 'max-w-5xl')">
            @if ($isOwner)<div id="wa-banner" class="hidden sticky top-14 z-20 mb-4 rounded-2xl bg-[#25d366] p-3 text-white shadow-lg lg:top-4"></div>@endif
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

@auth
{{-- Phone: iOS-style tab bar + "More" sheet --}}
<nav class="glass safe-b fixed inset-x-0 bottom-0 z-40 border-t border-black/10 lg:hidden" aria-label="Main">
    <div class="mx-auto grid max-w-xl grid-cols-5">
        @foreach ($tabs as [$label, $route, $pattern, $icon])
            @php $on = $tabOn($pattern); @endphp
            <a href="{{ route($route) }}" class="relative flex flex-col items-center gap-0.5 pb-1.5 pt-2 text-[10px] font-medium {{ $on ? 'text-forest' : 'text-slate-500' }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="{{ $on ? 2.2 : 1.7 }}" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icons[$icon] }}"/></svg>
                {{ $label }}
                @if ($icon === 'bell' && $unread)<span class="absolute right-[26%] top-1 rounded-full bg-[#ff3b30] px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $unread }}</span>@endif
            </a>
        @endforeach
        <button type="button" x-on:click="more = true" class="flex flex-col items-center gap-0.5 pb-1.5 pt-2 text-[10px] font-medium text-slate-500">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24"><path d="{{ $icons['dots'] }}"/></svg>
            More
        </button>
    </div>
</nav>

@if ($isOwner && $isRoot)
    <button type="button" class="fab lg:hidden" x-on:click="quick = true" aria-label="Quick actions">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
    </button>
    <div x-cloak x-show="quick" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/40" x-on:click="quick = false" x-transition.opacity></div>
        <div class="sheet-enter safe-b absolute inset-x-0 bottom-0 rounded-t-3xl bg-[var(--bg)] p-4 shadow-2xl">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-black/20"></div>
            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    ['Upload leads', 'agent.leads.import', '#007aff', 'M12 16V4m0 0l-4 4m4-4l4 4M4 20h16'],
                    ['Add opportunity', 'agent.opportunities.create', '#5856d6', 'M12 5v14M5 12h14'],
                    ['Find opportunities', 'agent.discovery.index', '#ff9500', 'M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z'],
                    ['Alerts & schedule', 'agent.notifications.index', '#ff3b30', $icons['bell']],
                    ['Documents', 'agent.documents.index', '#34c759', $icons['doc']],
                    ['Customers', 'agent.customers.index', '#af52de', $icons['leads']],
                ] as [$label, $route, $color, $path])
                    <a href="{{ route($route) }}" class="card flex flex-col items-start gap-3 p-4 active:scale-[.97]">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white" style="background: {{ $color }}"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $path }}"/></svg></span>
                        <span class="text-[15px] font-semibold">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif

<script>window.AGENT_ALERTS = @json($isOwner ? ['url' => route('agent.whatsapp.due')] : null);</script>
<script defer src="{{ asset('js/alerts.js') }}?v=1"></script>

<div x-cloak x-show="more" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/40" x-on:click="more = false" x-transition.opacity></div>
    <div class="sheet-enter safe-b absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-[var(--bg)] p-4 shadow-2xl">
        <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-black/20"></div>
        @if ($isOwner)
            <div class="card mb-4 divide-y divide-slate-100">
                @foreach ($agentNav as [$label, $route, $pattern])
                    <a href="{{ route($route) }}" class="flex items-center justify-between px-4 py-3 text-[15px] {{ $tabOn($pattern) ? 'font-semibold text-forest' : '' }}">{{ $label }}<span class="text-slate-300">›</span></a>
                @endforeach
            </div>
            <div class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Job feed</div>
            <div class="card mb-4 divide-y divide-slate-100">
                @foreach ($jobsNav as [$label, $route, $pattern])
                    <a href="{{ route($route) }}" class="flex items-center justify-between px-4 py-3 text-[15px] {{ $tabOn($pattern) ? 'font-semibold text-forest' : '' }}">{{ $label }}<span class="text-slate-300">›</span></a>
                @endforeach
            </div>
        @endif
        <p class="mb-2 truncate px-2 text-xs text-slate-400">{{ $user->email }}</p>
        <form method="POST" action="{{ route('logout') }}" class="card">
            @csrf
            <button class="w-full px-4 py-3 text-center text-[15px] font-medium text-[#ff3b30]">Sign out</button>
        </form>
    </div>
</div>
@endauth
@stack('scripts')
</body>
</html>
