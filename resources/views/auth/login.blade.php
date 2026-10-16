<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Opportunity Agent</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
<form method="POST" action="{{ url('/login') }}" class="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    <h1 class="mb-1 text-xl font-semibold text-slate-900">Opportunity Agent</h1>
    <p class="mb-5 text-sm text-slate-500">Sign in to continue.</p>

    <label class="mb-1 block text-sm font-medium text-slate-700" for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
           class="mb-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
    @error('email')<p class="mb-2 text-xs text-red-600">{{ $message }}</p>@enderror

    <label class="mb-1 mt-3 block text-sm font-medium text-slate-700" for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password"
           class="mb-4 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">

    <label class="mb-4 flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Keep me signed in
    </label>
    <button class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Sign in</button>
</form>
</body>
</html>
