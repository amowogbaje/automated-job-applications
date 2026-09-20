<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Log in — Job Feed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; background: #fafafa; }
        h1 { font-size: 1.4rem; }
        .card { background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 1.5rem; }
        label { display: block; font-size: .85rem; color: #444; margin: .75rem 0 .25rem; }
        input[type=email], input[type=password] { width: 100%; padding: .5rem; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .remember { display: flex; align-items: center; gap: .4rem; margin-top: .75rem; font-size: .85rem; }
        .remember input { width: auto; }
        button { width: 100%; margin-top: 1.25rem; padding: .6rem; background: #222; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .errors { background: #fee; border: 1px solid #fbb; color: #900; padding: .5rem .75rem; border-radius: 4px; font-size: .85rem; margin-bottom: 1rem; }
        .status { background: #efe; border: 1px solid #bdb; color: #262; padding: .5rem .75rem; border-radius: 4px; font-size: .85rem; margin-bottom: 1rem; }
        .switch { text-align: center; margin-top: 1rem; font-size: .85rem; }
    </style>
</head>
<body>
    <h1>Log in</h1>
    <div class="card">
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <label class="remember"><input type="checkbox" name="remember"> Remember me</label>

            <button type="submit">Log in</button>
        </form>
    </div>
    <p class="switch">No account yet? <a href="{{ route('register') }}">Sign up</a></p>
</body>
</html>
