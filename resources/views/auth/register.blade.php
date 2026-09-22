@extends('layouts.guest')

@section('title', 'Sign up')

@section('content')
    <div class="bg-white border border-line rounded-lg p-6">
        <h1 class="font-serif text-lg font-semibold mb-5">Create your account</h1>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm text-ink/70 mb-1.5">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2.5">
            </div>

            <div>
                <label for="email" class="block text-sm text-ink/70 mb-1.5">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2.5">
            </div>

            <div>
                <label for="password" class="block text-sm text-ink/70 mb-1.5">Password</label>
                <input id="password" type="password" name="password" required
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2.5">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm text-ink/70 mb-1.5">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2.5">
            </div>

            <button type="submit"
                class="w-full rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium py-2.5 transition-colors">
                Sign up
            </button>
        </form>
    </div>
    <p class="text-center text-sm text-ink/60 mt-5">Already have an account? <a href="{{ route('login') }}" class="text-forest hover:text-forest-dark font-medium">Log in</a></p>
@endsection
