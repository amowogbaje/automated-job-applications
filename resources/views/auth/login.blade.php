@extends('layouts.guest')

@section('title', 'Log in')

@section('content')
    <h1 class="font-serif text-3xl font-semibold">Welcome back</h1>
    <p class="text-sm text-ink/60 mt-1.5 mb-7">Log in to see today's matches and your drafts.</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ show: false, busy: false }" x-on:submit="busy = true">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-ink/80 mb-1.5">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input">
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-medium text-ink/80">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs text-forest hover:text-forest-dark font-medium">Forgot password?</a>
            </div>
            <div class="relative">
                <input id="password" x-bind:type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" class="input pr-11">
                <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 px-3 text-ink/40 hover:text-ink" x-bind:aria-label="show ? 'Hide password' : 'Show password'">
                    <x-icon name="eye" class="w-[18px] h-[18px]" />
                </button>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-ink/70">
            <input type="checkbox" name="remember" class="rounded border-line text-forest focus:ring-forest">
            Keep me signed in
        </label>

        <button type="submit" x-bind:disabled="busy"
            class="w-full rounded-full bg-forest hover:bg-forest-dark disabled:opacity-60 text-white text-sm font-medium py-3 transition-colors">
            <span x-show="!busy">Log in</span><span x-show="busy" x-cloak>Signing you in…</span>
        </button>
    </form>

    <p class="text-center text-sm text-ink/60 mt-6">New here? <a href="{{ route('register') }}" class="text-forest hover:text-forest-dark font-medium">Create a free account</a></p>
@endsection
