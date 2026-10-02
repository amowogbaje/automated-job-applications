@extends('layouts.guest')

@section('title', 'Sign up')

@section('content')
    <h1 class="font-serif text-3xl font-semibold">Create your account</h1>
    <p class="text-sm text-ink/60 mt-1.5 mb-6">Free to start. Pick what brings you here; you can always tell us more later.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4"
        x-data="{
            role: @js($role),
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
        <input type="hidden" name="role" x-bind:value="role">

        {{-- Role chooser --}}
        <div class="grid grid-cols-2 gap-3" role="radiogroup" aria-label="I am">
            <button type="button" x-on:click="role = 'seeker'" role="radio" x-bind:aria-checked="role === 'seeker'"
                class="text-left rounded-xl border p-3.5 transition-colors"
                x-bind:class="role === 'seeker' ? 'border-forest bg-forest-50 ring-2 ring-forest/15' : 'border-line bg-white hover:border-forest/30'">
                <x-icon name="user" class="w-5 h-5 text-forest" />
                <span class="block mt-2 text-sm font-semibold">I'm looking for work</span>
                <span class="block text-xs text-ink/55 mt-0.5">Get matched and apply faster</span>
            </button>
            <button type="button" x-on:click="role = 'employer'" role="radio" x-bind:aria-checked="role === 'employer'"
                class="text-left rounded-xl border p-3.5 transition-colors"
                x-bind:class="role === 'employer' ? 'border-forest bg-forest-50 ring-2 ring-forest/15' : 'border-line bg-white hover:border-forest/30'">
                <x-icon name="building" class="w-5 h-5 text-forest" />
                <span class="block mt-2 text-sm font-semibold">I'm hiring</span>
                <span class="block text-xs text-ink/55 mt-0.5">Post jobs, review applicants</span>
            </button>
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-ink/80 mb-1.5">Your name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="input">
        </div>

        <div x-show="role === 'employer'" x-cloak x-transition>
            <label for="company_name" class="block text-sm font-medium text-ink/80 mb-1.5">Company name</label>
            <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" x-bind:required="role === 'employer'" autocomplete="organization" class="input">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-ink/80 mb-1.5" x-text="role === 'employer' ? 'Work email' : 'Email'"></label>
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
            <span x-show="!busy" x-text="role === 'employer' ? 'Create employer account' : 'Create account'"></span><span x-show="busy" x-cloak>Creating…</span>
        </button>

        <p class="text-xs text-ink/50 text-center" x-show="role === 'seeker'">Applications only ever use the skills and experience on your own CV.</p>
        <p class="text-xs text-ink/50 text-center" x-show="role === 'employer'" x-cloak>Post only real, current openings. Duplicates of jobs listed elsewhere are merged automatically.</p>
    </form>

    <p class="text-center text-sm text-ink/60 mt-6">Already have an account? <a href="{{ route('login') }}" class="text-forest hover:text-forest-dark font-medium">Log in</a></p>
@endsection
