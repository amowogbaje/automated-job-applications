@extends('layouts.app')

@section('title', 'Profile')
@section('container', 'max-w-6xl')

@section('content')
@php
    $name = $resume?->full_name ?: auth()->user()->name;
    $initials = collect(explode(' ', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $skillGroups = $resume ? $resume->skills->groupBy('category') : collect();
@endphp

<div x-data="{ modal: null, bio: false }" class="space-y-6">

    {{-- ═════════ Header card ═════════ --}}
    <section class="bg-white border border-line rounded-2xl shadow-card overflow-hidden">
        <div class="h-32 sm:h-40 relative" style="background: linear-gradient(120deg, #312e81 0%, #4f46e5 55%, #6366f1 100%);">
            <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 85% 20%, #fbbf24 0, transparent 40%);"></div>
        </div>
        <div class="px-6 sm:px-8 pb-6">
            <div class="flex flex-wrap items-end justify-between gap-4 -mt-14 sm:-mt-16">
                <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-full border-4 border-white bg-forest text-white grid place-items-center font-serif text-4xl font-semibold shadow-card">{{ $initials }}</div>
                <div class="flex flex-wrap gap-2 pb-1">
                    @if ($resume)
                        <button type="button" x-on:click="modal = 'intro'" class="inline-flex items-center gap-1.5 rounded-full border border-line hover:border-forest/40 bg-white text-sm font-medium px-4 py-2 transition-colors"><x-icon name="pencil" class="w-4 h-4" /> Edit profile</button>
                        <a data-no-live href="{{ route('resume.download') }}" class="inline-flex items-center gap-1.5 rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-4 py-2 transition-colors"><x-icon name="doc" class="w-4 h-4" /> Download CV</a>
                    @endif
                    <a href="{{ route('resume.upload') }}" class="inline-flex items-center gap-1.5 rounded-full border border-line hover:border-forest/40 bg-white text-sm font-medium px-4 py-2 transition-colors">{{ $resume ? 'Re-import CV' : 'Import your CV' }}</a>
                </div>
            </div>

            <div class="mt-4 grid lg:grid-cols-[1fr_auto] gap-6 items-start">
                <div class="min-w-0">
                    <h1 class="font-serif text-2xl sm:text-3xl font-semibold">{{ $name }}</h1>
                    <p class="mt-1 text-ink/75">{{ $resume?->headline ?: 'Add a headline so employers see what you do at a glance' }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-ink/55">
                        @if ($resume?->location)<span class="inline-flex items-center gap-1.5"><x-icon name="pin" class="w-4 h-4" /> {{ $resume->location }}</span>@endif
                        @if ($resume?->email)<span class="inline-flex items-center gap-1.5"><x-icon name="mail" class="w-4 h-4" /> {{ $resume->email }}</span>@endif
                        @foreach (['website_url' => 'Website', 'github_url' => 'GitHub', 'linkedin_url' => 'LinkedIn'] as $field => $label)
                            @if ($resume?->$field)<a href="{{ $resume->$field }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-forest hover:text-forest-dark font-medium"><x-icon name="link" class="w-4 h-4" /> {{ $label }}</a>@endif
                        @endforeach
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.openToWork') }}" class="lg:text-right">
                    @csrf @method('PATCH')
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm text-left transition-colors {{ $profile->open_to_work ? 'border-forest/40 bg-forest-50' : 'border-line bg-white' }}">
                        <span class="w-9 h-5 rounded-full relative transition-colors {{ $profile->open_to_work ? 'bg-forest' : 'bg-line' }}"><span class="absolute top-0.5 w-4 h-4 rounded-full bg-white transition-all {{ $profile->open_to_work ? 'left-[1.15rem]' : 'left-0.5' }}"></span></span>
                        <span><span class="block font-medium leading-tight">Open to work</span><span class="block text-xs text-ink/50">{{ $profile->open_to_work ? 'Your feed is on' : 'Paused' }}</span></span>
                    </button>
                </form>
            </div>
        </div>

    </section>

    {{-- ═════════════════ TAB: PROFILE ═════════════════ --}}
    <div class="grid lg:grid-cols-[1fr_320px] gap-6 items-start">

        <div class="space-y-6 min-w-0">
        @if (! $resume)
            <section class="bg-white border border-line rounded-2xl shadow-card px-8 py-14 text-center">
                <span class="mx-auto w-14 h-14 rounded-full bg-forest-50 text-forest grid place-items-center"><x-icon name="doc" class="w-7 h-7" /></span>
                <h2 class="mt-4 font-serif text-xl font-semibold">Start with your CV</h2>
                <p class="mt-2 text-sm text-ink/60 max-w-md mx-auto">Upload it once and we'll build your profile (experience, education, projects and a clean, standardised skills list) which you can then fine-tune here.</p>
                <a href="{{ route('resume.upload') }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-forest hover:bg-forest-dark text-white font-medium px-6 py-3 transition-colors">Import your CV <x-icon name="arrow-right" class="w-4 h-4" /></a>
            </section>
        @else
            {{-- About --}}
            <section id="about" class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-xl font-semibold">About</h2>
                    <button type="button" x-on:click="modal = 'intro'" class="p-2 rounded-lg text-ink/50 hover:text-forest hover:bg-forest-50" aria-label="Edit about"><x-icon name="pencil" class="w-[18px] h-[18px]" /></button>
                </div>
                @if ($resume->summary)
                    <div x-data="{ long: {{ strlen($resume->summary) > 360 ? 'true' : 'false' }} }">
                        <p class="mt-3 text-ink/75 leading-relaxed whitespace-pre-line" x-bind:class="long && !bio ? 'line-clamp-4' : ''">{{ $resume->summary }}</p>
                        <button type="button" x-show="long" x-on:click="bio = !bio" class="mt-2 text-sm font-medium text-forest hover:text-forest-dark" x-text="bio ? 'Show less' : '…see more'"></button>
                    </div>
                @else
                    <button type="button" x-on:click="modal = 'intro'" class="mt-3 text-sm text-forest font-medium">+ Write a short summary</button>
                @endif
            </section>

            {{-- Experience --}}
            <section id="experience" class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-xl font-semibold">Experience</h2>
                    <button type="button" x-on:click="modal = 'exp:new'" class="p-2 rounded-lg text-ink/50 hover:text-forest hover:bg-forest-50" aria-label="Add experience"><x-icon name="plus" class="w-5 h-5" /></button>
                </div>
                <div class="mt-5 space-y-7">
                    @forelse ($resume->experiences as $exp)
                        <div class="flex gap-4" x-data="{ all: false }">
                            <span class="shrink-0 w-11 h-11 rounded-lg bg-forest-50 text-forest grid place-items-center"><x-icon name="building" class="w-5 h-5" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold leading-snug">{{ $exp->job_title }}</h3>
                                        <p class="text-sm text-ink/70">{{ $exp->company }}@if ($exp->location) · {{ $exp->location }}@endif</p>
                                        <p class="text-xs text-ink/45 mt-0.5">{{ $exp->dateRangeLabel() }}</p>
                                    </div>
                                    <button type="button" x-on:click="modal = 'exp:{{ $exp->id }}'" class="p-1.5 rounded-lg text-ink/40 hover:text-forest hover:bg-forest-50" aria-label="Edit"><x-icon name="pencil" class="w-4 h-4" /></button>
                                </div>
                                @php $bullets = $exp->bullets ?? []; @endphp
                                @if ($bullets)
                                    <ul class="mt-3 space-y-1.5 text-sm text-ink/75 list-disc ml-5 marker:text-forest/50">
                                        @foreach ($bullets as $i => $b)
                                            <li @if ($i >= 2) x-show="all" x-cloak @endif>{{ $b }}</li>
                                        @endforeach
                                    </ul>
                                    @if (count($bullets) > 2)
                                        <button type="button" x-on:click="all = !all" class="mt-2 text-sm font-medium text-forest hover:text-forest-dark" x-text="all ? 'Show less' : 'Show {{ count($bullets) - 2 }} more'"></button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-ink/55">No roles yet. Add your most recent job to get better tailored resumes.</p>
                    @endforelse
                </div>
            </section>

            {{-- Projects --}}
            <section id="project" class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-xl font-semibold">Projects</h2>
                    <button type="button" x-on:click="modal = 'project:new'" class="p-2 rounded-lg text-ink/50 hover:text-forest hover:bg-forest-50" aria-label="Add project"><x-icon name="plus" class="w-5 h-5" /></button>
                </div>
                <div class="mt-5 grid sm:grid-cols-2 gap-4">
                    @forelse ($resume->projects as $p)
                        <div class="rounded-xl border border-line p-4 hover:border-forest/30 transition-colors flex flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-semibold leading-snug">
                                    @if ($p->url)<a href="{{ $p->url }}" target="_blank" rel="noopener" class="hover:text-forest">{{ $p->name }}</a>@else{{ $p->name }}@endif
                                </h3>
                                <button type="button" x-on:click="modal = 'project:{{ $p->id }}'" class="p-1 rounded text-ink/40 hover:text-forest" aria-label="Edit"><x-icon name="pencil" class="w-4 h-4" /></button>
                            </div>
                            @if ($p->description)<p class="mt-1.5 text-sm text-ink/65 leading-relaxed line-clamp-4">{{ $p->description }}</p>@endif
                            <div class="mt-3 flex flex-wrap gap-1.5 pt-1">
                                @foreach ($p->tech_stack ?? [] as $t)<span class="rounded-full bg-forest-light text-forest-dark text-xs px-2 py-0.5">{{ $t }}</span>@endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-ink/55 sm:col-span-2">Projects are what recruiters click first. Add one with a link.</p>
                    @endforelse
                </div>
            </section>

            {{-- Education --}}
            <section id="education" class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-xl font-semibold">Education</h2>
                    <button type="button" x-on:click="modal = 'edu:new'" class="p-2 rounded-lg text-ink/50 hover:text-forest hover:bg-forest-50" aria-label="Add education"><x-icon name="plus" class="w-5 h-5" /></button>
                </div>
                <div class="mt-5 space-y-5">
                    @forelse ($resume->education as $edu)
                        <div class="flex gap-4">
                            <span class="shrink-0 w-11 h-11 rounded-lg bg-gold-light text-gold grid place-items-center"><x-icon name="graduation" class="w-5 h-5" /></span>
                            <div class="flex-1 min-w-0 flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold leading-snug">{{ $edu->institution }}</h3>
                                    <p class="text-sm text-ink/70">{{ collect([$edu->degree, $edu->field])->filter()->implode(', ') }}</p>
                                    <p class="text-xs text-ink/45 mt-0.5">{{ collect([$edu->start_date?->format('Y'), $edu->end_date?->format('Y')])->filter()->implode(' – ') }}</p>
                                </div>
                                <button type="button" x-on:click="modal = 'edu:{{ $edu->id }}'" class="p-1.5 rounded-lg text-ink/40 hover:text-forest hover:bg-forest-50" aria-label="Edit"><x-icon name="pencil" class="w-4 h-4" /></button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-ink/55">No education listed.</p>
                    @endforelse
                </div>
            </section>

            {{-- Skills --}}
            <section id="skills" class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8"
                x-data="{
                    q: '', preview: [], timer: null,
                    lookup() {
                        clearTimeout(this.timer);
                        if (!this.q.trim()) { this.preview = []; return; }
                        this.timer = setTimeout(async () => {
                            const r = await fetch('{{ route('profile.skills.preview') }}?q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } });
                            this.preview = r.ok ? await r.json() : [];
                        }, 250);
                    }
                }">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-xl font-semibold">Skills <span class="text-sm font-sans font-normal text-ink/45">{{ $resume->skills->count() }}</span></h2>
                </div>

                <form method="POST" action="{{ route('profile.skills.add') }}" class="mt-4">
                    @csrf
                    <div class="flex gap-2">
                        <input type="text" name="names" x-model="q" x-on:input="lookup()" placeholder="Add skills, e.g. nest js, graphql, microservices" class="input" autocomplete="off">
                        <button type="submit" class="shrink-0 rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-5 transition-colors">Add</button>
                    </div>
                    <div x-show="preview.length" x-cloak x-transition class="mt-2.5 flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-ink/50">Will be saved as:</span>
                        <template x-for="s in preview" :key="s.name">
                            <span class="rounded-full px-2.5 py-1" x-bind:class="s.known ? 'bg-forest-light text-forest-dark' : 'bg-gold-light text-gold'" x-text="s.name + (s.known ? '' : ' (custom)')"></span>
                        </template>
                    </div>
                </form>

                <div class="mt-6 space-y-6">
                    @forelse ($skillGroups as $cat => $group)
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink/45">{{ $categoryLabels[$cat] ?? ucfirst(str_replace('_', ' ', $cat)) }}</h3>
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                @foreach ($group as $skill)
                                    <form method="POST" action="{{ route('profile.skills.remove', $skill->id) }}" class="group inline-flex items-center rounded-full border border-line bg-white pl-3.5 pr-1.5 py-1 text-sm hover:border-forest/40 transition-colors" @if ($skill->qualifier) title="{{ $skill->qualifier }}" @endif>
                                        @csrf @method('DELETE')
                                        <span>{{ $skill->name }}</span>
                                        @if ($skill->qualifier)<span class="ml-1.5 text-[10px] text-ink/40">· {{ $skill->qualifier }}</span>@endif
                                        <button type="submit" class="ml-1.5 w-5 h-5 grid place-items-center rounded-full text-ink/30 hover:bg-rust-light hover:text-rust" aria-label="Remove {{ $skill->name }}"><x-icon name="x" class="w-3 h-3" /></button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-ink/55">No skills yet. Add a few above.</p>
                    @endforelse
                </div>
            </section>

            {{-- Certifications --}}
            <section id="certification" class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-xl font-semibold">Certifications</h2>
                    <button type="button" x-on:click="modal = 'cert:new'" class="p-2 rounded-lg text-ink/50 hover:text-forest hover:bg-forest-50" aria-label="Add certification"><x-icon name="plus" class="w-5 h-5" /></button>
                </div>
                <ul class="mt-4 space-y-3">
                    @forelse ($resume->certifications as $c)
                        <li class="flex items-center gap-3 text-sm">
                            <span class="w-9 h-9 rounded-lg bg-forest-50 text-forest grid place-items-center shrink-0"><x-icon name="award" class="w-[18px] h-[18px]" /></span>
                            <span class="flex-1">{{ $c->name }}</span>
                            <button type="button" x-on:click="modal = 'cert:{{ $c->id }}'" class="p-1.5 rounded-lg text-ink/40 hover:text-forest hover:bg-forest-50" aria-label="Edit"><x-icon name="pencil" class="w-4 h-4" /></button>
                        </li>
                    @empty
                        <li class="text-sm text-ink/55">None listed.</li>
                    @endforelse
                </ul>
            </section>
        @endif
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-6 lg:sticky lg:top-24">
            <section class="bg-white border border-line rounded-2xl shadow-card p-6" x-data="{ list: false }">
                <div class="flex items-center gap-4">
                    <div class="relative w-16 h-16 shrink-0">
                        <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90"><circle cx="18" cy="18" r="15.5" fill="none" stroke="#e2e8f0" stroke-width="3"/><circle cx="18" cy="18" r="15.5" fill="none" stroke="#4f46e5" stroke-width="3" stroke-linecap="round" stroke-dasharray="{{ round($strength['percent'] * 0.9743, 1) }} 97.43"/></svg>
                        <span class="absolute inset-0 grid place-items-center text-sm font-semibold">{{ $strength['percent'] }}%</span>
                    </div>
                    <div>
                        <h2 class="font-semibold leading-tight">Profile strength</h2>
                        <p class="text-xs text-ink/55 mt-1">{{ $strength['next'] ? 'Next: ' . $strength['next'] : 'Looking complete. Nice.' }}</p>
                    </div>
                </div>
                <button type="button" x-on:click="list = !list" class="mt-4 text-xs font-medium text-forest hover:text-forest-dark" x-text="list ? 'Hide checklist' : 'View checklist'"></button>
                <ul x-show="list" x-cloak x-collapse class="mt-3 space-y-2 text-sm">
                    @foreach ($strength['items'] as $item)
                        <li class="flex items-center gap-2 {{ $item['done'] ? 'text-ink/50' : 'text-ink' }}">
                            <span class="w-4 h-4 rounded-full grid place-items-center {{ $item['done'] ? 'bg-forest text-white' : 'border border-line' }}">@if ($item['done'])<x-icon name="check" class="w-3 h-3" />@endif</span>
                            <span class="{{ $item['done'] ? 'line-through' : '' }}">{{ $item['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="bg-white border border-line rounded-2xl shadow-card p-6">
                <h2 class="font-semibold">Your job feed</h2>
                <p class="mt-2 text-sm text-ink/60 leading-relaxed">You get jobs for <strong>{{ count($profile->required_skills ?? []) + count($profile->keywords ?? []) }}</strong> skill(s) you chose. They're separate from this page, so editing your resume never changes which jobs you see. Each job's tailored resume and cover letter are built from what's here.</p>
                <a href="{{ route('jobs.interests') }}" class="mt-3 text-sm font-medium text-forest hover:text-forest-dark inline-flex items-center gap-1">Change my job skills <x-icon name="arrow-right" class="w-4 h-4" /></a>
            </section>
        </aside>
    </div>

    {{-- ═════════════════ MODALS ═════════════════ --}}
    @if ($resume)
        <x-modal name="intro" title="Edit profile" wide>
            <form method="POST" action="{{ route('profile.intro') }}" class="space-y-4">
                @csrf @method('PATCH')
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2"><label class="block text-xs font-medium text-ink/60 mb-1">Full name *</label><input type="text" name="full_name" value="{{ old('full_name', $resume->full_name) }}" required class="input"></div>
                    <div class="sm:col-span-2"><label class="block text-xs font-medium text-ink/60 mb-1">Headline</label><input type="text" name="headline" value="{{ old('headline', $resume->headline) }}" placeholder="Full-stack developer · Laravel, Node.js, Vue" class="input"></div>
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Location</label><input type="text" name="location" value="{{ old('location', $resume->location) }}" class="input"></div>
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Phone</label><input type="text" name="phone" value="{{ old('phone', $resume->phone) }}" class="input"></div>
                    <div class="sm:col-span-2"><label class="block text-xs font-medium text-ink/60 mb-1">Email</label><input type="email" name="email" value="{{ old('email', $resume->email) }}" class="input"></div>
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Website</label><input type="url" name="website_url" value="{{ old('website_url', $resume->website_url) }}" placeholder="https://" class="input"></div>
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">GitHub</label><input type="url" name="github_url" value="{{ old('github_url', $resume->github_url) }}" placeholder="https://github.com/" class="input"></div>
                    <div class="sm:col-span-2"><label class="block text-xs font-medium text-ink/60 mb-1">LinkedIn</label><input type="url" name="linkedin_url" value="{{ old('linkedin_url', $resume->linkedin_url) }}" placeholder="https://linkedin.com/in/" class="input"></div>
                    <div class="sm:col-span-2"><label class="block text-xs font-medium text-ink/60 mb-1">About</label><textarea name="summary" rows="5" class="input">{{ old('summary', $resume->summary) }}</textarea></div>
                </div>
                <div class="flex justify-end gap-2 pt-2"><button type="button" x-on:click="modal = null" class="rounded-full border border-line px-5 py-2 text-sm">Cancel</button><button class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-2 transition-colors">Save</button></div>
            </form>
        </x-modal>

        {{-- Experience: new + one per existing role --}}
        @foreach (array_merge([null], $resume->experiences->all()) as $exp)
            <x-modal :name="'exp:' . ($exp->id ?? 'new')" :title="$exp ? 'Edit experience' : 'Add experience'" wide>
                <form method="POST" action="{{ $exp ? route('profile.section.update', ['section' => 'experience', 'id' => $exp->id]) : route('profile.section.store', 'experience') }}" class="space-y-4" x-data="{ current: {{ $exp?->is_current ? 'true' : 'false' }} }">
                    @csrf @if ($exp) @method('PATCH') @endif
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div><label class="block text-xs font-medium text-ink/60 mb-1">Title *</label><input type="text" name="job_title" required value="{{ $exp?->job_title }}" class="input"></div>
                        <div><label class="block text-xs font-medium text-ink/60 mb-1">Company</label><input type="text" name="company" value="{{ $exp?->company }}" class="input"></div>
                        <div class="sm:col-span-2"><label class="block text-xs font-medium text-ink/60 mb-1">Location</label><input type="text" name="location" value="{{ $exp?->location }}" class="input"></div>
                        <div><label class="block text-xs font-medium text-ink/60 mb-1">Start</label><input type="month" name="start_date" value="{{ $exp?->start_date?->format('Y-m') }}" class="input"></div>
                        <div x-show="!current"><label class="block text-xs font-medium text-ink/60 mb-1">End</label><input type="month" name="end_date" value="{{ $exp?->end_date?->format('Y-m') }}" class="input"></div>
                        <label class="sm:col-span-2 flex items-center gap-2 text-sm"><input type="checkbox" name="is_current" value="1" x-model="current" class="rounded border-line text-forest focus:ring-forest"> I currently work here</label>
                        <div class="sm:col-span-2"><label class="block text-xs font-medium text-ink/60 mb-1">Achievements (one per line)</label><textarea name="bullets" rows="6" class="input" placeholder="Cut report generation from 40s to 3s by caching…">{{ implode("\n", $exp?->bullets ?? []) }}</textarea></div>
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        @if ($exp)
                            <button type="submit" form="del-exp-{{ $exp->id }}" class="text-sm text-rust hover:underline" onclick="return confirm('Delete this role?')">Delete</button>
                        @else <span></span> @endif
                        <div class="flex gap-2"><button type="button" x-on:click="modal = null" class="rounded-full border border-line px-5 py-2 text-sm">Cancel</button><button class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-2 transition-colors">Save</button></div>
                    </div>
                </form>
                @if ($exp)<form id="del-exp-{{ $exp->id }}" method="POST" action="{{ route('profile.section.destroy', ['section' => 'experience', 'id' => $exp->id]) }}" class="hidden">@csrf @method('DELETE')</form>@endif
            </x-modal>
        @endforeach

        {{-- Education --}}
        @foreach (array_merge([null], $resume->education->all()) as $edu)
            <x-modal :name="'edu:' . ($edu->id ?? 'new')" :title="$edu ? 'Edit education' : 'Add education'">
                <form method="POST" action="{{ $edu ? route('profile.section.update', ['section' => 'education', 'id' => $edu->id]) : route('profile.section.store', 'education') }}" class="space-y-4">
                    @csrf @if ($edu) @method('PATCH') @endif
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Institution *</label><input type="text" name="institution" required value="{{ $edu?->institution }}" class="input"></div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div><label class="block text-xs font-medium text-ink/60 mb-1">Degree</label><input type="text" name="degree" value="{{ $edu?->degree }}" class="input"></div>
                        <div><label class="block text-xs font-medium text-ink/60 mb-1">Field</label><input type="text" name="field" value="{{ $edu?->field }}" class="input"></div>
                        <div><label class="block text-xs font-medium text-ink/60 mb-1">Start</label><input type="month" name="start_date" value="{{ $edu?->start_date?->format('Y-m') }}" class="input"></div>
                        <div><label class="block text-xs font-medium text-ink/60 mb-1">End</label><input type="month" name="end_date" value="{{ $edu?->end_date?->format('Y-m') }}" class="input"></div>
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        @if ($edu)<button type="submit" form="del-edu-{{ $edu->id }}" class="text-sm text-rust hover:underline" onclick="return confirm('Delete this entry?')">Delete</button>@else <span></span> @endif
                        <div class="flex gap-2"><button type="button" x-on:click="modal = null" class="rounded-full border border-line px-5 py-2 text-sm">Cancel</button><button class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-2 transition-colors">Save</button></div>
                    </div>
                </form>
                @if ($edu)<form id="del-edu-{{ $edu->id }}" method="POST" action="{{ route('profile.section.destroy', ['section' => 'education', 'id' => $edu->id]) }}" class="hidden">@csrf @method('DELETE')</form>@endif
            </x-modal>
        @endforeach

        {{-- Projects --}}
        @foreach (array_merge([null], $resume->projects->all()) as $proj)
            <x-modal :name="'project:' . ($proj->id ?? 'new')" :title="$proj ? 'Edit project' : 'Add project'">
                <form method="POST" action="{{ $proj ? route('profile.section.update', ['section' => 'project', 'id' => $proj->id]) : route('profile.section.store', 'project') }}" class="space-y-4">
                    @csrf @if ($proj) @method('PATCH') @endif
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Name *</label><input type="text" name="name" required value="{{ $proj?->name }}" class="input"></div>
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Link</label><input type="url" name="url" value="{{ $proj?->url }}" placeholder="https://" class="input"></div>
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Tech stack (comma-separated)</label><input type="text" name="tech_stack" value="{{ implode(', ', $proj?->tech_stack ?? []) }}" placeholder="Laravel, Vue.js, PostgreSQL" class="input"></div>
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Description</label><textarea name="description" rows="5" class="input">{{ $proj?->description }}</textarea></div>
                    <div class="flex items-center justify-between pt-2">
                        @if ($proj)<button type="submit" form="del-project-{{ $proj->id }}" class="text-sm text-rust hover:underline" onclick="return confirm('Delete this project?')">Delete</button>@else <span></span> @endif
                        <div class="flex gap-2"><button type="button" x-on:click="modal = null" class="rounded-full border border-line px-5 py-2 text-sm">Cancel</button><button class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-2 transition-colors">Save</button></div>
                    </div>
                </form>
                @if ($proj)<form id="del-project-{{ $proj->id }}" method="POST" action="{{ route('profile.section.destroy', ['section' => 'project', 'id' => $proj->id]) }}" class="hidden">@csrf @method('DELETE')</form>@endif
            </x-modal>
        @endforeach

        {{-- Certifications --}}
        @foreach (array_merge([null], $resume->certifications->all()) as $cert)
            <x-modal :name="'cert:' . ($cert->id ?? 'new')" :title="$cert ? 'Edit certification' : 'Add certification'">
                <form method="POST" action="{{ $cert ? route('profile.section.update', ['section' => 'certification', 'id' => $cert->id]) : route('profile.section.store', 'certification') }}" class="space-y-4">
                    @csrf @if ($cert) @method('PATCH') @endif
                    <div><label class="block text-xs font-medium text-ink/60 mb-1">Name *</label><input type="text" name="name" required value="{{ $cert?->name }}" class="input"></div>
                    <div class="flex items-center justify-between pt-2">
                        @if ($cert)<button type="submit" form="del-certification-{{ $cert->id }}" class="text-sm text-rust hover:underline" onclick="return confirm('Delete this certification?')">Delete</button>@else <span></span> @endif
                        <div class="flex gap-2"><button type="button" x-on:click="modal = null" class="rounded-full border border-line px-5 py-2 text-sm">Cancel</button><button class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-2 transition-colors">Save</button></div>
                    </div>
                </form>
                @if ($cert)<form id="del-certification-{{ $cert->id }}" method="POST" action="{{ route('profile.section.destroy', ['section' => 'certification', 'id' => $cert->id]) }}" class="hidden">@csrf @method('DELETE')</form>@endif
            </x-modal>
        @endforeach
    @endif
</div>
@endsection
