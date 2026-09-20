<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your resume — Job Feed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 2rem auto; padding: 0 1rem; background: #fafafa; }
        h1 { font-size: 1.4rem; }
        nav { font-size: .9rem; margin-bottom: 1.5rem; }
        .card { background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; }
        label { display: block; font-size: .85rem; color: #444; margin: .75rem 0 .25rem; }
        input[type=file], input[type=text] { width: 100%; padding: .5rem; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { margin-top: 1.25rem; padding: .6rem 1.2rem; background: #222; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .errors { background: #fee; border: 1px solid #fbb; color: #900; padding: .5rem .75rem; border-radius: 4px; font-size: .85rem; margin-bottom: 1rem; }
        .status { background: #efe; border: 1px solid #bdb; color: #262; padding: .5rem .75rem; border-radius: 4px; font-size: .85rem; margin-bottom: 1rem; }
        .current { font-size: .85rem; color: #555; }
        .hint { font-size: .8rem; color: #777; }
    </style>
</head>
<body>
    <nav><a href="{{ route('jobs.index') }}">&larr; Job feed</a></nav>
    <h1>Your resume</h1>

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

    @if ($resume)
        <div class="card">
            <strong>Active resume:</strong> {{ $resume->full_name }} — {{ $resume->headline }}
            <div class="current">
                {{ $resume->experiences->count() }} roles &middot;
                {{ $resume->skills->count() }} skills &middot;
                {{ $resume->projects->count() }} projects
                &middot; <a href="{{ route('resume.download') }}">Download PDF</a>
            </div>
        </div>
    @endif

    <div class="card">
        <p class="hint">Upload a PDF and/or your personal site — the PDF is the only source of facts; the site is only used for tone/summary phrasing, never to add a skill or job that isn't in the PDF.</p>
        <form method="POST" action="{{ route('resume.upload') }}" enctype="multipart/form-data">
            @csrf
            <label for="resume_pdf">Resume PDF</label>
            <input id="resume_pdf" type="file" name="resume_pdf" accept="application/pdf">

            <label for="website_url">Personal website (optional)</label>
            <input id="website_url" type="text" name="website_url" placeholder="amowogbaje.com" value="{{ old('website_url') }}">

            <button type="submit">{{ $resume ? 'Replace resume' : 'Import resume' }}</button>
        </form>
    </div>
</body>
</html>
