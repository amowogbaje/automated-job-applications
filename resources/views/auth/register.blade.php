<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign up — Job Feed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; background: #fafafa; }
        h1 { font-size: 1.4rem; }
        .card { background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 1.5rem; }
        label { display: block; font-size: .85rem; color: #444; margin: .75rem 0 .25rem; }
        input { width: 100%; padding: .5rem; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; margin-top: 1.25rem; padding: .6rem; background: #222; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .errors { background: #fee; border: 1px solid #fbb; color: #900; padding: .5rem .75rem; border-radius: 4px; font-size: .85rem; margin-bottom: 1rem; }
        .switch { text-align: center; margin-top: 1rem; font-size: .85rem; }
    </style>
</head>
<body>
    <h1>Create your account</h1>
    <div class="card">
        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>

            <button type="submit">Sign up</button>
        </form>
    </div>
    <p class="switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
</body>
</html>
