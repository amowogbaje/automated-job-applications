@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('content')
    <h1 class="font-serif text-3xl font-semibold">Choose a new password</h1>
    <p class="text-sm text-ink/60 mt-1.5 mb-7">Pick something you haven't used elsewhere.</p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" x-data="{ show: false }">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm font-medium text-ink/80 mb-1.5">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required class="input">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-ink/80 mb-1.5">New password</label>
            <input id="password" x-bind:type="show ? 'text' : 'password'" name="password" required autocomplete="new-password" class="input">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-ink/80 mb-1.5">Confirm new password</label>
            <input id="password_confirmation" x-bind:type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" class="input">
        </div>
        <label class="flex items-center gap-2 text-sm text-ink/70"><input type="checkbox" x-model="show" class="rounded border-line text-forest focus:ring-forest"> Show passwords</label>

        <button type="submit" class="w-full rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium py-3 transition-colors">Update password</button>
    </form>
@endsection
