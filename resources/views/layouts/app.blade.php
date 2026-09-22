<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', config('app.name', 'Job Feed')) — {{ config('app.name', 'Job Feed') }}</title>
</head>
<body class="bg-paper text-ink font-sans min-h-screen antialiased">

    <header class="border-b border-line">
        <div class="max-w-4xl mx-auto px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('jobs.index') }}" class="font-serif text-xl font-semibold text-ink tracking-tight">
                {{ config('app.name', 'Job Feed') }}
            </a>

            @auth
                <nav class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <a href="{{ route('jobs.index') }}" class="text-ink/70 hover:text-forest transition-colors {{ request()->routeIs('jobs.index') ? 'text-forest font-medium' : '' }}">Jobs</a>
                    <a href="{{ route('drafts.index') }}" class="text-ink/70 hover:text-forest transition-colors {{ request()->routeIs('drafts.*') ? 'text-forest font-medium' : '' }}">Drafts</a>
                    <a href="{{ route('resume.upload') }}" class="text-ink/70 hover:text-forest transition-colors {{ request()->routeIs('resume.*') ? 'text-forest font-medium' : '' }}">Resume</a>
                    <a href="{{ route('leads.index') }}" class="text-ink/70 hover:text-forest transition-colors {{ request()->routeIs('leads.*') ? 'text-forest font-medium' : '' }}">Leads</a>
                    <a href="{{ route('tasks.index') }}" class="text-ink/70 hover:text-forest transition-colors {{ request()->routeIs('tasks.index') ? 'text-forest font-medium' : '' }}">Tasks</a>
                    <span class="w-px h-4 bg-line hidden sm:block"></span>
                    <span class="text-ink/50 hidden sm:inline">{{ auth()->user()->email }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-ink/70 hover:text-rust transition-colors">Log out</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-5 py-10">

        @if (session('status'))
            <div class="mb-6 rounded-lg bg-forest-light border border-forest/20 text-forest-dark px-4 py-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-rust-light border border-rust/20 text-rust px-4 py-3 text-sm">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
