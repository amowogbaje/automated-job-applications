@extends('layouts.guest')

@section('title', 'Sign up')

@section('content')
    <h1 class="font-serif text-3xl font-semibold">Create your account</h1>
    <p class="text-sm text-ink/60 mt-1.5 mb-6">Choose the skills you want jobs for and get a feed (and optional emails) for just those.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4"
        x-data="{
            show: false, busy: false, pw: '', confirm: '',
            get checks() { return [
                { ok: this.pw.length >= 8, label: 'At least 8 characters' },
                { ok: /[A-Za-z]/.test(this.pw) && /\d/.test(this.pw), label: 'A letter and a number' },
                { ok: this.confirm.length > 0 && this.pw === this.confirm, label: 'Passwords match' },
            ]; },
            get score() { return this.checks.filter(c => c.ok).length; }
        }"
        x-on:submit="busy = true">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-ink/80 mb-1.5">Your name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="input">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-ink/80 mb-1.5">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="input">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-ink/80 mb-1.5">Password</label>
            <div class="relative">
                <input id="password" x-model="pw" x-bind:type="show ? 'text' : 'password'" name="password" required autocomplete="new-password" class="input pr-11">
                <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 px-3 text-ink/40 hover:text-ink" x-bind:aria-label="show ? 'Hide password' : 'Show password'"><x-icon name="eye" class="w-[18px] h-[18px]" /></button>
            </div>
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-ink/80 mb-1.5">Confirm password</label>
            <input id="password_confirmation" x-model="confirm" x-bind:type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" class="input">
        </div>

        <div x-show="pw.length > 0" x-cloak x-transition>
            <div class="flex gap-1 mb-2">
                <template x-for="i in 3" :key="i"><span class="h-1 flex-1 rounded-full transition-colors" x-bind:class="i <= score ? (score === 3 ? 'bg-forest' : 'bg-gold') : 'bg-line'"></span></template>
            </div>
            <ul class="text-xs space-y-1">
                <template x-for="c in checks" :key="c.label">
                    <li class="flex items-center gap-1.5" x-bind:class="c.ok ? 'text-forest' : 'text-ink/45'">
                        <span class="w-3.5 h-3.5 rounded-full grid place-items-center text-[9px]" x-bind:class="c.ok ? 'bg-forest text-white' : 'border border-line'" x-text="c.ok ? '✓' : ''"></span>
                        <span x-text="c.label"></span>
                    </li>
                </template>
            </ul>
        </div>

        <button type="submit" x-bind:disabled="busy" class="w-full rounded-full bg-forest hover:bg-forest-dark disabled:opacity-60 text-white text-sm font-medium py-3 transition-colors">
            <span x-show="!busy">Create account</span><span x-show="busy" x-cloak>Creating…</span>
        </button>

    </form>

    <p class="text-center text-sm text-ink/60 mt-6">Already have an account? <a href="{{ route('login') }}" class="text-forest hover:text-forest-dark font-medium">Log in</a></p>
@endsection
