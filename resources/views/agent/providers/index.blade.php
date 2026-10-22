@extends('agent.layout')
@section('agent_title', 'Providers')
@section('agent')
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Providers</h1>
    <p class="text-sm text-slate-500">Requests try providers in order. If one is rate limited, down or returns unusable output, the next one takes over automatically, and the failed provider rests on a cooldown. API keys live only in your server's <code>.env</code>.</p>
</div>

@if (session('provider_test'))
    @php $t = session('provider_test'); @endphp
    <div class="mb-4 rounded-md border px-4 py-2 text-sm {{ $t['ok'] ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' }}">
        <span class="font-medium">{{ $t['name'] }}:</span> {{ $t['message'] }}
    </div>
@endif

<div class="mb-4 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm">
    <form method="POST" action="{{ route('agent.providers.refresh') }}" class="flex flex-wrap items-center gap-3">
        @csrf
        <span class="text-slate-600">Added a key to <code>.env</code> but it still says "no key"? The server may be holding an old copy of your settings{{ app()->configurationIsCached() ? ' (it is, right now)' : '' }}.</span>
        <button class="btn-secondary">Reload .env keys</button>
    </form>
</div>

<form method="POST" action="{{ route('agent.providers.update') }}" class="space-y-6">
    @csrf @method('PUT')

    @foreach ([['ai', 'AI models', $ai], ['search', 'Web search', $search]] as [$kind, $title, $rows])
        <div class="card p-5">
            <h2 class="mb-1 font-medium">{{ $title }}</h2>
            <p class="mb-4 text-xs text-slate-500">Lower number = tried first.</p>
            <div class="divide-y divide-slate-100">
                @foreach ($rows as $p)
                    <div class="py-4">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-medium">{{ $p['label'] }}</span>
                            @unless ($p['enabled'])<span class="badge bg-slate-300 text-slate-700">switched off</span>@endunless
                            @if ($p['configured'])
                                <span class="badge bg-green-100 text-green-700">key found</span>
                            @else
                                <span class="badge bg-slate-200 text-slate-600">no key</span>
                            @endif
                            @if ($p['cooldown'])
                                <span class="badge bg-amber-100 text-amber-800">resting until {{ date('H:i:s', $p['cooldown']['until']) }} ({{ str_replace('_', ' ', $p['cooldown']['reason']) }})</span>
                            @endif
                            <span class="text-xs text-slate-500">24h: {{ $p['stats']['ok'] }} ok · {{ $p['stats']['failed'] }} failed @if ($kind === 'ai' && $p['stats']['tokens']) · {{ number_format($p['stats']['tokens']) }} tokens @endif</span>
                        </div>
                        @unless ($p['configured'])
                            <p class="mt-1 text-xs text-slate-500">Add <code>{{ $p['key_env'] }}=your-key</code> to .env @if ($p['signup_url']) (get one at <a href="{{ $p['signup_url'] }}" target="_blank" rel="noopener" class="text-forest-600 hover:underline">{{ parse_url($p['signup_url'], PHP_URL_HOST) }}</a>)@endif.</p>
                        @endunless

                        <div class="mt-3 grid items-end gap-3 sm:grid-cols-12">
                            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                                <input type="checkbox" name="{{ $kind }}[{{ $p['name'] }}][enabled]" value="1" @checked($p['enabled'])> Enabled
                            </label>
                            <div class="sm:col-span-2">
                                <label class="label">Order</label>
                                <input type="number" min="1" max="99" name="{{ $kind }}[{{ $p['name'] }}][priority]" value="{{ $p['priority'] }}" class="input">
                            </div>
                            @if ($kind === 'ai')
                                <div class="sm:col-span-5">
                                    <label class="label">Model</label>
                                    <input name="ai[{{ $p['name'] }}][model]" value="{{ $p['model'] }}" placeholder="{{ $p['default_model'] }}" class="input font-mono text-xs">
                                </div>
                            @else
                                <div class="sm:col-span-5"></div>
                            @endif
                            <div class="flex gap-2 sm:col-span-3 sm:justify-end">
                                <button type="submit" form="toggle-{{ $kind }}-{{ $p['name'] }}" class="{{ $p['enabled'] ? 'btn-secondary' : 'btn-primary' }}">{{ $p['enabled'] ? 'Switch off' : 'Switch on' }}</button>
                                <button type="submit" form="test-{{ $kind }}-{{ $p['name'] }}" class="btn-secondary">Test</button>
                                @if ($p['cooldown'])
                                    <button type="submit" form="reset-{{ $kind }}-{{ $p['name'] }}" class="btn-secondary">Clear rest</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="card p-5">
        <h2 class="mb-1 font-medium">Daily safety limits</h2>
        <p class="mb-4 text-xs text-slate-500">Stops runaway usage. Today: {{ $today['ai'] }} AI calls, {{ $today['search'] }} searches. Set 0 for no limit.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="ai_calls_per_day">AI calls per day</label>
                <input id="ai_calls_per_day" name="ai_calls_per_day" type="number" min="0" value="{{ old('ai_calls_per_day', $limits['ai_calls_per_day']) }}" class="input">
            </div>
            <div>
                <label class="label" for="search_calls_per_day">Searches per day</label>
                <input id="search_calls_per_day" name="search_calls_per_day" type="number" min="0" value="{{ old('search_calls_per_day', $limits['search_calls_per_day']) }}" class="input">
            </div>
        </div>
    </div>

    <button class="btn-primary">Save settings</button>
</form>

{{-- Standalone forms for the Test / Clear buttons (a form can't nest inside another form) --}}
@foreach ([['ai', $ai], ['search', $search]] as [$kind, $rows])
    @foreach ($rows as $p)
        <form id="test-{{ $kind }}-{{ $p['name'] }}" method="POST" action="{{ route('agent.providers.test', [$kind, $p['name']]) }}">@csrf</form>
        <form id="toggle-{{ $kind }}-{{ $p['name'] }}" method="POST" action="{{ route('agent.providers.toggle', [$kind, $p['name']]) }}">@csrf</form>
        <form id="reset-{{ $kind }}-{{ $p['name'] }}" method="POST" action="{{ route('agent.providers.reset', [$kind, $p['name']]) }}">@csrf</form>
    @endforeach
@endforeach
@endsection
