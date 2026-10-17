<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', config('app.name', 'Job Feed')) — {{ config('app.name', 'Job Feed') }}</title>
</head>
<body class="bg-paper text-ink font-sans min-h-screen antialiased">
@php
    $isEmployer = auth()->check() && auth()->user()->isEmployer();
    // Two different products in one shell: the nav, home link and primary action depend on the account type.
    $nav = $isEmployer
        ? [
            ['Dashboard', 'employer.dashboard', 'employer.dashboard', 'briefcase'],
            ['My jobs', 'employer.jobs.index', 'employer.jobs.*', 'doc'],
            ['Applicants', 'employer.applicants', 'employer.applicants', 'user'],
            ['Company', 'employer.company', 'employer.company*', 'building'],
        ]
        : [
            ['Jobs', 'jobs.index', 'jobs.index', 'briefcase'],
            ['Social', 'social.index', 'social.*', 'bolt'],
            ['Applications', 'applications.index', 'applications.*', 'check'],
            ['Drafts', 'drafts.index', 'drafts.*', 'doc'],
            ['Profile', 'profile.edit', 'profile.*', 'user'],
            ['Resume', 'resume.upload', 'resume.*', 'award'],
        ];
    if (auth()->check() && auth()->user()->canUseAgent()) {
        $nav[] = ['Agent', 'agent.dashboard', 'agent.*', 'target'];
    }
    $homeUrl = auth()->check() ? route(auth()->user()->homeRoute()) : route('home');
    $initials = auth()->check() ? collect(explode(' ', trim(auth()->user()->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') : '';
@endphp

    <header class="sticky top-0 z-40 bg-white/85 backdrop-blur border-b border-line" x-data="{ open: false, menu: false }" x-on:keydown.escape.window="open = false; menu = false">
        <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between gap-4">
            <a href="{{ $homeUrl }}" class="flex items-center gap-2.5 shrink-0">
                <span class="w-8 h-8 rounded-lg bg-forest text-white grid place-items-center"><x-icon name="briefcase" class="w-[18px] h-[18px]" /></span>
                <span class="font-serif text-lg font-semibold tracking-tight">{{ config('app.name', 'Job Feed') }}</span>
            </a>

            @auth
                <nav class="hidden md:flex items-center gap-1 text-sm">
                    @foreach ($nav as [$label, $route, $pattern])
                        <a href="{{ route($route) }}"
                           class="px-3 py-2 rounded-lg transition-colors {{ request()->routeIs($pattern) ? 'bg-forest-50 text-forest font-medium' : 'text-ink/65 hover:text-ink hover:bg-black/[.04]' }}">{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-2">
                    @if ($isEmployer)
                        <a href="{{ route('employer.jobs.create') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-4 py-2 transition-colors"><x-icon name="plus" class="w-4 h-4" /> Post a job</a>
                    @endif
                    <div class="relative" x-on:click.outside="menu = false">
                        <button type="button" x-on:click="menu = !menu" class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-black/[.04] transition-colors" aria-haspopup="true" x-bind:aria-expanded="menu">
                            <span class="w-8 h-8 rounded-full bg-forest text-white grid place-items-center text-xs font-semibold">{{ $initials }}</span>
                            <x-icon name="chevron-down" class="w-4 h-4 text-ink/40" />
                        </button>
                        <div x-show="menu" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-60 bg-white border border-line rounded-xl shadow-pop py-2 text-sm">
                            <div class="px-4 py-2 border-b border-line mb-1">
                                <p class="font-medium truncate">{{ auth()->user()->name }}</p>
                                <p class="text-ink/50 truncate text-xs">{{ auth()->user()->email }}</p>
                            </div>
                            @if ($isEmployer)
                                <p class="px-4 pb-1 text-[11px] uppercase tracking-wide text-ink/40">Employer account</p>
                                <a href="{{ route('employer.company') }}" class="flex items-center gap-2 px-4 py-2 hover:bg-black/[.04]"><x-icon name="building" class="w-4 h-4 text-ink/50" /> Company profile</a>
                                <a href="{{ route('employer.jobs.create') }}" class="flex items-center gap-2 px-4 py-2 hover:bg-black/[.04]"><x-icon name="plus" class="w-4 h-4 text-ink/50" /> Post a job</a>
                            @else
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 hover:bg-black/[.04]"><x-icon name="user" class="w-4 h-4 text-ink/50" /> My profile</a>
                                <a href="{{ route('resume.upload') }}" class="flex items-center gap-2 px-4 py-2 hover:bg-black/[.04]"><x-icon name="doc" class="w-4 h-4 text-ink/50" /> Resume</a>
                            @endif
                            <a href="{{ route('tasks.index') }}" class="flex items-center gap-2 px-4 py-2 hover:bg-black/[.04]"><x-icon name="check" class="w-4 h-4 text-ink/50" /> Tasks</a>
                            <a href="{{ route('leads.index') }}" class="flex items-center gap-2 px-4 py-2 hover:bg-black/[.04]"><x-icon name="mail" class="w-4 h-4 text-ink/50" /> Leads</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left flex items-center gap-2 px-4 py-2 hover:bg-rust-light text-rust"><x-icon name="logout" class="w-4 h-4" /> Log out</button>
                            </form>
                        </div>
                    </div>

                    <button type="button" class="md:hidden p-2 rounded-lg hover:bg-black/[.04]" x-on:click="open = !open" aria-label="Menu">
                        <x-icon name="menu" x-show="!open" />
                        <x-icon name="x" x-show="open" x-cloak />
                    </button>
                </div>
            @else
                <div class="flex items-center gap-2 text-sm">
                    <a href="{{ route('register', ['role' => 'employer']) }}" class="hidden sm:inline px-3 py-2 text-ink/70 hover:text-ink">Post a job</a>
                    <a href="{{ route('login') }}" class="px-3 py-2 text-ink/70 hover:text-ink">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-forest hover:bg-forest-dark text-white font-medium px-4 py-2 transition-colors">Sign up</a>
                </div>
            @endauth
        </div>

        @auth
            <nav x-show="open" x-cloak x-transition class="md:hidden border-t border-line bg-white px-5 py-3 grid grid-cols-2 gap-1 text-sm">
                @foreach ($nav as [$label, $route, $pattern, $icon])
                    <a href="{{ route($route) }}" class="flex items-center gap-2 px-3 py-2.5 rounded-lg {{ request()->routeIs($pattern) ? 'bg-forest-50 text-forest font-medium' : 'text-ink/70' }}">
                        <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
                    </a>
                @endforeach
            </nav>
        @endauth
    </header>

    <main class="@yield('container', 'max-w-5xl') mx-auto px-5 py-8 sm:py-10">

        <div id="flash">
        @if (session('status'))
            <div x-data="{ show: true }" x-show="show" x-transition class="mb-6 flex items-start gap-3 rounded-xl bg-forest-50 border border-forest-200 text-forest-dark px-4 py-3 text-sm">
                <x-icon name="check" class="w-4 h-4 mt-0.5 shrink-0" />
                <span class="flex-1">{{ session('status') }}</span>
                <button type="button" x-on:click="show = false" class="text-forest/60 hover:text-forest" aria-label="Dismiss"><x-icon name="x" class="w-4 h-4" /></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 flex items-start gap-3 rounded-xl bg-rust-light border border-rust/20 text-rust px-4 py-3 text-sm">
                <x-icon name="alert" class="w-4 h-4 mt-0.5 shrink-0" />
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        </div>

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
