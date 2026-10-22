@extends('layouts.app')

@section('title', 'Job interests')
@section('container', 'max-w-6xl')

@section('content')
@include('partials.dash-tabs', ['active' => 'interests'])

<div class="mb-6">
    <h1 class="text-2xl font-semibold">Job interests</h1>
    <p class="mt-1 text-sm text-slate-500">Choose the skills you want jobs for. Only those show up in your feed and in your emails. Want only Laravel? Add just <strong>Laravel</strong>.</p>
</div>

<div class="grid items-start gap-6 lg:grid-cols-[1fr_380px]">
    <div class="space-y-6">
        <form method="POST" action="{{ route('jobs.interests.update') }}" class="space-y-6" id="interests-form">
            @csrf @method('PUT')

            <section class="card space-y-6 p-6">
                <div>
                    <h2 class="font-semibold">What you want</h2>
                    <p class="mt-1 text-xs text-slate-500">Type skills in plain words; they're saved in standard form (<em>nest js</em> becomes <strong>NestJS</strong>). Currently {{ $matchCount }} open {{ Str::plural('match', $matchCount) }} in your feed.</p>
                </div>

                <x-tag-input name="required_skills" label="Skills I want jobs for" :values="$profile->required_skills ?? []" :suggest="$fromResume"
                    help="A job must mention at least the number below to appear. Broad skills (Git, REST, SQL, Agile…) never qualify a job on their own." />

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="min_required_matches" class="label">Jobs must mention at least</label>
                        <div class="flex items-center gap-2">
                            <input type="number" id="min_required_matches" name="min_required_matches" min="1" max="20" value="{{ old('min_required_matches', $profile->min_required_matches) }}" class="input w-24">
                            <span class="text-sm text-slate-500">of these skills</span>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">1 means “any one of them”.</p>
                    </div>
                    <label class="flex items-start gap-3 sm:pt-6">
                        <input type="checkbox" name="hide_region_restricted" value="1" @checked(old('hide_region_restricted', $profile->hide_region_restricted)) class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span><span class="block text-sm font-medium">Hide region-restricted roles</span><span class="block text-xs text-slate-500">Drops “remote” jobs that say US-only, EU-only or must live in a specific country. Otherwise they're flagged, not hidden.</span></span>
                    </label>
                </div>

                @php $wt = old('work_types', $profile->work_types ?: ['remote', 'hybrid', 'onsite']); @endphp
                <div>
                    <label class="label">Work type</label>
                    <div class="flex flex-wrap gap-4">
                        @foreach (['remote' => 'Remote', 'hybrid' => 'Hybrid', 'onsite' => 'On-site'] as $k => $l)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="work_types[]" value="{{ $k }}" @checked(in_array($k, (array) $wt, true)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"> {{ $l }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">Only jobs of the types you tick are shown and emailed. A posting that doesn't say remote or hybrid counts as on-site.</p>
                    <label for="work_locations" class="label mt-3">Where, for on-site or hybrid work <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" id="work_locations" name="work_locations" class="input" placeholder="Lagos, Abuja, Nigeria" value="{{ old('work_locations', implode(', ', (array) $profile->work_locations)) }}">
                    <p class="mt-1.5 text-xs text-slate-500">Leave empty for anywhere. Remote jobs ignore this.</p>
                </div>

                <details class="rounded-md border border-slate-200 p-4" @if (! empty($profile->keywords) || ! empty($profile->excluded_keywords) || ! empty($profile->ats_boards)) open @endif>
                    <summary class="cursor-pointer text-sm font-medium text-slate-700">More ways to tune it</summary>
                    <div class="mt-5 space-y-6">
                        <x-tag-input name="keywords" label="Nice-to-have skills" :values="$profile->keywords ?? []"
                            help="Not required. Each one found just raises the match score (and tier)." />
                        <x-tag-input name="excluded_keywords" label="Never show me jobs that mention" tone="red" :values="$profile->excluded_keywords ?? []"
                            help="A whole-word hit anywhere in the posting drops the job: e.g. wordpress, junior, unpaid." />
                        <div>
                            <label for="ats_boards" class="label">Company job boards to watch</label>
                            <input type="text" id="ats_boards" name="ats_boards" class="input" placeholder="greenhouse:stripe, lever:spotify, ashby:ramp"
                                   value="{{ old('ats_boards', implode(', ', (array) $profile->ats_boards)) }}">
                            <p class="mt-1.5 text-xs text-slate-500">Optional. Adds those companies' own boards (Greenhouse, Lever, Ashby) to the fetch; their jobs are still filtered by your skills.</p>
                        </div>
                    </div>
                </details>

                <div class="space-y-3 border-t border-slate-100 pt-5">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="social_jobs_enabled" value="1" @checked(old('social_jobs_enabled', $profile->social_jobs_enabled)) class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span><span class="block text-sm font-medium">Include social posts</span><span class="block text-xs text-slate-500">Hiring posts from Hacker News, Mastodon, Bluesky, Reddit, GitHub and more, in the same feed as job boards. Matched on the same skills.</span></span>
                    </label>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="open_to_work" value="1" @checked(old('open_to_work', $profile->open_to_work)) class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span><span class="block text-sm font-medium">Open to work</span><span class="block text-xs text-slate-500">Untick to pause matching and emails without losing your settings.</span></span>
                    </label>
                </div>
            </section>

            <section class="card space-y-5 p-6">
                <div>
                    <h2 class="font-semibold">Email alerts</h2>
                    <p class="mt-1 text-xs text-slate-500">Off by default. When on, you only get jobs that match the skills above, each one once.</p>
                </div>
                <label class="flex items-start gap-3" x-data="{ on: {{ old('digest_enabled', $profile->digest_enabled) ? 'true' : 'false' }} }">
                    <input type="checkbox" name="digest_enabled" value="1" x-model="on" @checked(old('digest_enabled', $profile->digest_enabled)) class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="block text-sm font-medium">Email me new matching jobs</span>
                </label>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="alert_frequency" class="label">How often</label>
                        <select id="alert_frequency" name="alert_frequency" class="input">
                            <option value="daily" @selected(old('alert_frequency', $profile->alert_frequency) === 'daily')>Once a day (morning)</option>
                            <option value="hourly" @selected(old('alert_frequency', $profile->alert_frequency) === 'hourly')>As they arrive (hourly)</option>
                        </select>
                    </div>
                    <div>
                        <label for="alert_min_tier" class="label">Only if the match is</label>
                        <select id="alert_min_tier" name="alert_min_tier" class="input">
                            <option value="strong" @selected(old('alert_min_tier', $profile->alert_min_tier) === 'strong')>Strong</option>
                            <option value="good" @selected(old('alert_min_tier', $profile->alert_min_tier) === 'good')>Good or better</option>
                            <option value="weak" @selected(old('alert_min_tier', $profile->alert_min_tier) === 'weak')>Any match</option>
                        </select>
                    </div>
                    <div>
                        <label for="notification_email" class="label">Send to</label>
                        <input type="email" id="notification_email" name="notification_email" value="{{ old('notification_email', $profile->notification_email) }}" placeholder="{{ auth()->user()->email }}" class="input">
                    </div>
                </div>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary">Save and update my feed</button>
                <span class="text-xs text-slate-500">Re-checks the last {{ $windowDays }} days of jobs against your skills.</span>
            </div>
        </form>

        <form method="POST" action="{{ route('jobs.interests.testEmail') }}" class="text-sm text-slate-600" data-no-live>
            @csrf
            <button type="submit" class="font-medium text-indigo-600 hover:text-indigo-800">Send me a test email</button>
            <span class="text-xs text-slate-500">with your top current matches (uses your saved settings).</span>
        </form>
    </div>

    {{-- Live matcher test --}}
    <section class="card p-6 lg:sticky lg:top-6"
        x-data="{
            title: '', description: '', result: null, busy: false, err: '',
            async run() {
                this.busy = true; this.err = ''; this.result = null;
                try {
                    const r = await fetch('{{ route('jobs.interests.test') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ title: this.title, description: this.description })
                    });
                    if (!r.ok) throw new Error('Could not score that (check the title).');
                    this.result = await r.json();
                } catch (e) { this.err = e.message; }
                this.busy = false;
            }
        }">
        <h2 class="font-semibold">Test your skills</h2>
        <p class="mt-1 text-xs text-slate-500">Paste any job to see whether it would reach your feed, and why. Uses your <em>saved</em> skills.</p>
        <div class="mt-4 space-y-3">
            <input type="text" x-model="title" placeholder="Job title" class="input">
            <textarea x-model="description" rows="6" placeholder="Paste the job description" class="input"></textarea>
            <button type="button" x-on:click="run()" x-bind:disabled="busy || !title.trim()" class="btn bg-slate-800 text-white hover:bg-slate-900 disabled:opacity-50">
                <span x-show="!busy">Score this job</span><span x-show="busy" x-cloak>Scoring…</span>
            </button>
            <p x-show="err" x-cloak class="text-sm text-red-600" x-text="err"></p>
        </div>
        <div x-show="result" x-cloak class="mt-4 rounded-md border p-4" x-bind:class="result && result.matches ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-slate-50'">
            <template x-if="result && result.matches">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="badge" x-bind:class="result.tier === 'strong' ? 'bg-indigo-600 text-white' : (result.tier === 'good' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-600')" x-text="result.tier + ' match'"></span>
                        <span class="text-sm text-slate-500">score <strong x-text="result.score"></strong></span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <template x-for="m in result.matched" :key="m">
                            <span class="badge" x-bind:class="result.title_hits.includes(m) ? 'bg-amber-500 text-white' : (result.generic_hits.includes(m) ? 'bg-slate-200 text-slate-600' : 'bg-amber-50 text-amber-700')" x-text="m"></span>
                        </template>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Dark = in the title · light = in the text · grey = broad skill (adds little).</p>
                    <template x-for="w in result.warnings" :key="w"><p class="mt-2 text-xs text-red-600" x-text="w"></p></template>
                </div>
            </template>
            <template x-if="result && !result.matches">
                <p class="text-sm text-slate-700"><strong>Not a match.</strong> <span x-text="result.reason"></span></p>
            </template>
        </div>
    </section>
</div>
@endsection
