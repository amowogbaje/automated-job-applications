@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <h1 class="font-serif text-3xl font-semibold">Reset your password</h1>
    <p class="text-sm text-ink/60 mt-1.5 mb-7">Enter your account email and we'll send you a link to choose a new one.</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-ink/80 mb-1.5">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="input">
        </div>
        <button type="submit" class="w-full rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium py-3 transition-colors">Send reset link</button>
    </form>

    <p class="text-center text-sm text-ink/60 mt-6"><a href="{{ route('login') }}" class="text-forest hover:text-forest-dark font-medium">&larr; Back to log in</a></p>
@endsection
