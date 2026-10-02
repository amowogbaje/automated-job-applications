@extends('layouts.app')

@section('title', $job->exists ? 'Edit job' : 'Post a job')
@section('container', 'max-w-6xl')

@section('content')
@php
    $isEdit = $job->exists;
    $currencies = ['USD', 'EUR', 'GBP', 'NGN', 'CAD', 'AUD', 'INR', 'ZAR', 'KES', 'GHS'];
    $firstError = $errors->keys()[0] ?? null;
    $startStep = match (true) {
        in_array($firstError, ['title', 'location', 'employment_type', 'salary_min', 'salary_max', 'salary_currency'], true) => 1,
        in_array($firstError, ['description', 'skills'], true) => 2,
        $firstError !== null => 3,
        default => 1,
    };
    $state = [
        'step' => $startStep,
        'title' => old('title', $job->title),
        'type' => old('employment_type', $job->employment_type ?: 'full_time'),
        'location' => old('location', $job->location),
        'remote' => (bool) old('is_remote', $job->exists ? $job->is_remote : true),
        'showSalary' => (bool) (old('salary_min', $job->salary_min) || old('salary_max', $job->salary_max)),
        'salaryMin' => old('salary_min', $job->salary_min),
        'salaryMax' => old('salary_max', $job->salary_max),
        'currency' => old('salary_currency', $job->salary_currency ?: 'USD'),
        'description' => old('description', $job->description),
        'skills' => array_values(array_filter(array_map('trim', explode(',', old('skills', implode(', ', (array) $job->skills)))))),
        'method' => old('apply_method', $job->apply_method ?: 'native'),
        'email' => old('apply_email', $job->apply_email ?: $user->email),
        'url' => old('apply_url', $job->apply_method === 'web' ? $job->apply_url : ''),
        'duration' => (int) old('duration', $duration),
    ];
    $starter = "About the role\nWhat will this person do, and why does it matter to your team?\n\nWhat you'll do\n- \n- \n- \n\nWhat we're looking for\n- \n- \n\nNice to have\n- \n\nWhat we offer\n- ";
@endphp

<div x-data="postJob(@js($state), @js($starter), '{{ route('skills.preview') }}')" class="grid lg:grid-cols-[1fr_360px] gap-8 items-start">

    <form method="POST" action="{{ $isEdit ? route('employer.jobs.update', $job) : route('employer.jobs.store') }}" class="min-w-0" x-on:submit="busy = true">
        @csrf
        @if ($isEdit) @method('PATCH') @endif

        <div class="mb-6">
            <h1 class="font-serif text-3xl font-semibold">{{ $isEdit ? 'Edit job' : 'Post a job' }}</h1>
            <p class="text-sm text-ink/60 mt-1">Three quick steps. Matching candidates see it as soon as you publish.</p>
        </div>

        {{-- Stepper --}}
        <ol class="flex items-center gap-2 mb-6 text-sm">
            @foreach ([1 => 'The role', 2 => 'Details', 3 => 'Apply & publish'] as $n => $label)
                <li class="flex items-center gap-2 {{ $n < 3 ? 'flex-1' : '' }}">
                    <button type="button" x-on:click="go({{ $n }})" class="flex items-center gap-2 group">
                        <span class="w-7 h-7 rounded-full grid place-items-center text-xs font-semibold transition-colors"
                              x-bind:class="step > {{ $n }} ? 'bg-forest text-white' : (step === {{ $n }} ? 'bg-forest text-white ring-4 ring-forest/15' : 'bg-line text-ink/50')">
                            <span x-show="step <= {{ $n }}">{{ $n }}</span><x-icon name="check" class="w-3.5 h-3.5" x-show="step > {{ $n }}" x-cloak />
                        </span>
                        <span class="hidden sm:inline" x-bind:class="step === {{ $n }} ? 'font-medium text-ink' : 'text-ink/50'">{{ $label }}</span>
                    </button>
                    @if ($n < 3)<span class="flex-1 h-px bg-line"></span>@endif
                </li>
            @endforeach
        </ol>

        @if ($errors->any())
            <div class="mb-5 rounded-xl bg-rust-light border border-rust/20 text-rust px-4 py-3 text-sm">
                <p class="font-medium">A few things need a look:</p>
                <ul class="list-disc ml-5 mt-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- ───── Step 1: the role ───── --}}
        <section x-show="step === 1" class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8 space-y-6">
            <div>
                <label for="title" class="block text-sm font-medium mb-1.5">Job title</label>
                <input id="title" name="title" type="text" x-model="title" maxlength="140" placeholder="e.g. Senior Laravel Developer" class="input" autofocus>
                <p class="text-xs text-ink/50 mt-1.5">Use the title people would search for. "Senior Laravel Developer" beats "Rockstar Ninja".</p>
            </div>

            <div>
                <p class="block text-sm font-medium mb-2">Type of work</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($types as $val => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="employment_type" value="{{ $val }}" x-model="type" class="sr-only">
                            <span class="inline-block rounded-full border px-4 py-2 text-sm transition-colors" x-bind:class="type === '{{ $val }}' ? 'border-forest bg-forest text-white' : 'border-line bg-white hover:border-forest/40'">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid sm:grid-cols-[1fr_auto] gap-4 items-end">
                <div>
                    <label for="location" class="block text-sm font-medium mb-1.5">Location</label>
                    <input id="location" name="location" type="text" x-model="location" placeholder="City, country (or the countries you can hire in)" class="input">
                </div>
                <label class="flex items-center gap-2.5 pb-2.5 cursor-pointer">
                    <input type="checkbox" name="is_remote" value="1" x-model="remote" class="rounded border-line text-forest focus:ring-forest">
                    <span class="text-sm font-medium">Remote friendly</span>
                </label>
            </div>
            <p class="text-xs text-ink/50 -mt-3" x-show="remote">If you can only hire in certain countries, say so in the location, so candidates elsewhere don't waste your time.</p>

            <div>
                <label class="inline-flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" x-model="showSalary" class="rounded border-line text-forest focus:ring-forest">
                    <span class="text-sm font-medium">Show a salary range</span>
                    <span class="text-xs text-ink/50">(jobs with pay get noticeably more applicants)</span>
                </label>
                <div x-show="showSalary" x-cloak x-transition class="mt-3 grid grid-cols-[auto_1fr_1fr] gap-3 items-center">
                    <select name="salary_currency" x-model="currency" class="input w-24">@foreach ($currencies as $c)<option>{{ $c }}</option>@endforeach</select>
                    <input name="salary_min" type="number" min="0" x-model="salaryMin" placeholder="Min per year" class="input">
                    <input name="salary_max" type="number" min="0" x-model="salaryMax" placeholder="Max per year" class="input">
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="button" x-on:click="next(1)" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-2.5 transition-colors">Continue</button>
            </div>
        </section>

        {{-- ───── Step 2: details ───── --}}
        <section x-show="step === 2" x-cloak class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8 space-y-6">
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="description" class="block text-sm font-medium">Description</label>
                    <button type="button" x-on:click="useStarter()" x-show="!description.trim()" class="text-xs font-medium text-forest hover:text-forest-dark">Start from an outline</button>
                </div>
                <textarea id="description" name="description" rows="14" x-model="description" class="input" placeholder="What will they do, what do you need, what do you offer?"></textarea>
                <div class="flex items-center justify-between text-xs mt-1.5">
                    <span class="text-ink/50">Plain text. Line breaks are kept.</span>
                    <span x-bind:class="description.trim().length >= 80 ? 'text-forest' : 'text-ink/50'" x-text="description.trim().length < 80 ? (80 - description.trim().length) + ' more characters to publish' : 'Good length'"></span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5">Skills needed</label>
                <input type="hidden" name="skills" x-bind:value="skills.join(', ')">
                <div class="flex flex-wrap gap-2 rounded-lg border border-line bg-white p-2 focus-within:border-forest focus-within:ring-2 focus-within:ring-forest/15">
                    <template x-for="(s, i) in skills" :key="s">
                        <span class="inline-flex items-center gap-1 rounded-full bg-forest-light text-forest-dark text-sm pl-3 pr-1.5 py-1">
                            <span x-text="s"></span>
                            <button type="button" x-on:click="skills.splice(i, 1)" class="w-[18px] h-[18px] grid place-items-center rounded-full hover:bg-black/10" aria-label="Remove"><x-icon name="x" class="w-3 h-3" /></button>
                        </span>
                    </template>
                    <input type="text" x-model="skillDraft" x-on:keydown.enter.prevent="addSkills()" x-on:keydown.comma.prevent="addSkills()" x-on:blur="addSkills()"
                           x-on:keydown.backspace="if (!skillDraft && skills.length) skills.pop()" placeholder="Type a skill, press Enter" class="flex-1 min-w-[160px] border-0 focus:ring-0 focus:outline-none text-sm py-1 px-1 bg-transparent">
                </div>
                <p class="text-xs text-ink/50 mt-1.5">Skills are standardised when you add them: "nest js" becomes <strong>NestJS</strong>, and "NestJS (GraphQL, microservices)" becomes three separate skills. This is what candidates are matched on.</p>
            </div>

            <div class="flex justify-between pt-2">
                <button type="button" x-on:click="step = 1" class="rounded-full border border-line px-5 py-2.5 text-sm">Back</button>
                <button type="button" x-on:click="next(2)" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-2.5 transition-colors">Continue</button>
            </div>
        </section>

        {{-- ───── Step 3: apply & publish ───── --}}
        <section x-show="step === 3" x-cloak class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8 space-y-6">
            <div>
                <p class="text-sm font-medium mb-2.5">How should people apply?</p>
                <input type="hidden" name="apply_method" x-bind:value="method">
                <div class="grid sm:grid-cols-3 gap-3">
                    @foreach ([
                        'native' => ['Apply here', 'Applicants send their resume and cover letter through this site. You review and shortlist them in one place.', 'Recommended'],
                        'email' => ['By email', 'Applications are emailed to you. Simple, but nothing is tracked here.', null],
                        'web' => ['On your site', 'Send people to your own careers page or form.', null],
                    ] as $val => [$label, $desc, $badge])
                        <button type="button" x-on:click="method = '{{ $val }}'" class="text-left rounded-xl border p-4 transition-colors" x-bind:class="method === '{{ $val }}' ? 'border-forest bg-forest-50 ring-2 ring-forest/15' : 'border-line hover:border-forest/30'">
                            <span class="flex items-center gap-2 text-sm font-semibold">{{ $label }} @if ($badge)<span class="rounded-full bg-forest text-white text-[10px] font-medium px-2 py-0.5">{{ $badge }}</span>@endif</span>
                            <span class="block text-xs text-ink/55 mt-1.5 leading-relaxed">{{ $desc }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div x-show="method === 'email'" x-cloak x-transition>
                <label for="apply_email" class="block text-sm font-medium mb-1.5">Send applications to</label>
                <input id="apply_email" name="apply_email" type="email" x-model="email" class="input">
            </div>
            <div x-show="method === 'web'" x-cloak x-transition>
                <label for="apply_url" class="block text-sm font-medium mb-1.5">Application link</label>
                <input id="apply_url" name="apply_url" type="url" x-model="url" placeholder="https://yourcompany.com/careers/..." class="input">
            </div>
            <p x-show="method === 'native'" x-cloak class="text-sm text-ink/60 rounded-lg bg-paper border border-line px-4 py-3">You'll get an email for each new applicant (resume attached), and everyone appears on your Applicants page with a simple pipeline: New, Reviewing, Shortlisted, Rejected, Hired.</p>

            <div>
                <label for="duration" class="block text-sm font-medium mb-1.5">Keep it open for</label>
                <select id="duration" name="duration" x-model.number="duration" class="input w-48">
                    @foreach ($durations as $d)<option value="{{ $d }}">{{ $d }} days</option>@endforeach
                </select>
            </div>

            <div class="rounded-xl bg-forest-50 border border-forest-200 px-4 py-3 text-sm text-ink/70">
                <span class="font-medium text-forest-dark">Already posted this elsewhere?</span> If the same job is on a board we track or has been shared on social media, we merge those copies under this listing, so seekers see it once, here.
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <button type="button" x-on:click="step = 2" class="rounded-full border border-line px-5 py-2.5 text-sm">Back</button>
                <div class="flex items-center gap-3">
                    <button type="submit" name="intent" value="draft" x-bind:disabled="busy" class="rounded-full border border-line bg-white hover:border-forest/40 text-sm font-medium px-5 py-2.5 transition-colors">Save as draft</button>
                    <button type="submit" name="intent" value="publish" x-bind:disabled="busy || !ready" class="rounded-full bg-forest hover:bg-forest-dark disabled:opacity-50 text-white text-sm font-medium px-6 py-2.5 transition-colors">{{ $isEdit && $job->status === 'published' ? 'Save changes' : 'Publish job' }}</button>
                </div>
            </div>
            <p x-show="!ready" x-cloak class="text-xs text-rust text-right">To publish: <span x-text="missing.join(', ')"></span>.</p>
        </section>
    </form>

    {{-- Live preview: exactly what a seeker sees in their feed --}}
    <aside class="lg:sticky lg:top-24">
        <p class="text-xs font-medium uppercase tracking-wide text-ink/45 mb-2">Live preview</p>
        <div class="bg-white border border-line rounded-2xl shadow-card p-5">
            <h2 class="font-serif text-lg font-semibold leading-snug" x-text="title || 'Your job title'" x-bind:class="title ? '' : 'text-ink/30'"></h2>
            <p class="text-sm text-ink/60 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="inline-flex items-center gap-1"><x-icon name="building" class="w-3.5 h-3.5 text-ink/35" /> {{ $user->company_name }}</span>
                <span class="inline-flex items-center gap-1" x-show="location || remote"><x-icon name="pin" class="w-3.5 h-3.5 text-ink/35" /> <span x-text="[location, remote ? 'Remote' : ''].filter(Boolean).join(' · ')"></span></span>
            </p>
            <div class="mt-3 flex flex-wrap gap-1.5 text-xs">
                <span class="rounded-full bg-forest-light text-forest-dark px-2.5 py-1" x-text="({full_time:'Full-time',part_time:'Part-time',contract:'Contract',internship:'Internship'})[type]"></span>
                <span class="rounded-full bg-gold-light text-gold px-2.5 py-1" x-show="salaryLabel" x-text="salaryLabel"></span>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5" x-show="skills.length">
                <template x-for="s in skills.slice(0, 8)" :key="s"><span class="rounded-full bg-gold-light text-gold text-xs px-2.5 py-1" x-text="s"></span></template>
            </div>
            <p class="mt-3 text-sm text-ink/65 leading-relaxed line-clamp-5 whitespace-pre-line" x-text="description || 'Your description appears here as you type.'" x-bind:class="description ? '' : 'text-ink/30'"></p>
            <div class="mt-4 pt-4 border-t border-line text-sm">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-forest text-white font-medium px-4 py-1.5" x-text="method === 'native' ? 'Apply here' : (method === 'email' ? 'Apply by email' : 'Apply on company site')"></span>
            </div>
        </div>
        <p class="text-xs text-ink/45 mt-3 leading-relaxed">Candidates whose skills match yours see this in their feed, ranked by how well it fits.</p>
    </aside>
</div>

<script>
    function postJob(state, starter, previewUrl) {
        return {
            ...state, starter, busy: false, skillDraft: '',
            get salaryLabel() {
                if (!this.showSalary || (!this.salaryMin && !this.salaryMax)) return '';
                const f = n => Number(n).toLocaleString();
                return this.salaryMin && this.salaryMax ? `${this.currency} ${f(this.salaryMin)} – ${f(this.salaryMax)}` : `${this.currency} ${f(this.salaryMin || this.salaryMax)}${this.salaryMin ? '+' : ' max'}`;
            },
            get missing() {
                const m = [];
                if (!this.title.trim()) m.push('a title');
                if (this.description.trim().length < 80) m.push('a longer description');
                if (this.method === 'email' && !/^\S+@\S+\.\S+$/.test(this.email)) m.push('an application email');
                if (this.method === 'web' && !/^https?:\/\//.test(this.url)) m.push('an application link');
                return m;
            },
            get ready() { return this.missing.length === 0; },
            go(n) { if (n <= this.step || this.title.trim()) this.step = n; },
            next(from) {
                if (from === 1 && !this.title.trim()) { document.getElementById('title').focus(); return; }
                this.step = from + 1; window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            useStarter() { this.description = this.starter; },
            async addSkills() {
                const raw = this.skillDraft.trim();
                this.skillDraft = '';
                if (!raw) return;
                try {
                    const r = await fetch(previewUrl + '?q=' + encodeURIComponent(raw), { headers: { 'Accept': 'application/json' } });
                    const found = r.ok ? await r.json() : raw.split(',').map(name => ({ name: name.trim() }));
                    found.forEach(s => { if (s.name && !this.skills.some(x => x.toLowerCase() === s.name.toLowerCase())) this.skills.push(s.name); });
                } catch (e) {
                    raw.split(',').map(s => s.trim()).filter(Boolean).forEach(s => { if (!this.skills.includes(s)) this.skills.push(s); });
                }
            },
        };
    }
</script>
@endsection
