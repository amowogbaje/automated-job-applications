<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', 'Sign in') — {{ config('app.name', 'Job Feed') }}</title>
</head>
<body class="bg-paper text-ink font-sans min-h-screen antialiased flex items-center justify-center px-5">

    <div class="w-full max-w-sm py-10">
        <div class="mb-8 text-center">
            <span class="font-serif text-2xl font-semibold text-ink tracking-tight">{{ config('app.name', 'Job Feed') }}</span>
        </div>

        @if (session('status'))
            <div class="mb-5 rounded-lg bg-forest-light border border-forest/20 text-forest-dark px-4 py-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-lg bg-rust-light border border-rust/20 text-rust px-4 py-3 text-sm">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

</body>
</html>
