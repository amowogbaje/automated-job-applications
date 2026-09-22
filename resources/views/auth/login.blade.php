@extends('layouts.guest')

@section('title', 'Log in')

@section('content')
    <div class="bg-white border border-line rounded-lg p-6">
        <h1 class="font-serif text-lg font-semibold mb-5">Log in</h1>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm text-ink/70 mb-1.5">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2.5">
            </div>

            <div>
                <label for="password" class="block text-sm text-ink/70 mb-1.5">Password</label>
                <input id="password" type="password" name="password" required
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2.5">
            </div>

            <label class="flex items-center gap-2 text-sm text-ink/70">
                <input type="checkbox" name="remember" class="rounded border-line text-forest focus:ring-forest">
                Remember me
            </label>

            <button type="submit"
                class="w-full rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium py-2.5 transition-colors">
                Log in
            </button>
        </form>
    </div>
    <p class="text-center text-sm text-ink/60 mt-5">No account yet? <a href="{{ route('register') }}" class="text-forest hover:text-forest-dark font-medium">Sign up</a></p>
@endsection
