<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    @include('partials.head')
    <title>{{ config('app.name', 'Job Feed') }} — Find the right jobs. Apply in minutes.</title>
    <meta name="description" content="Jobs ranked against your real skills, a tailored resume and cover letter for each role, and auto-apply with guardrails you control.">
</head>
<body class="bg-paper text-ink font-sans antialiased">

{{-- ───────── Nav ───────── --}}
<header class="sticky top-0 z-40 bg-paper/85 backdrop-blur border-b border-line/70" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-forest text-white grid place-items-center"><x-icon name="briefcase" class="w-4 h-4" /></span>
            <span class="font-serif text-lg font-semibold tracking-tight">{{ config('app.name', 'Job Feed') }}</span>
        </a>
        <nav class="hidden md:flex items-center gap-7 text-sm text-ink/70">
            <a href="#how" class="hover:text-ink">How it works</a>
            <a href="#features" class="hover:text-ink">Features</a>
            <a href="#latest" class="hover:text-ink">Latest roles</a>
            <a href="#faq" class="hover:text-ink">FAQ</a>
        </nav>
        <div class="hidden md:flex items-center gap-2 text-sm">
            <a href="{{ route('login') }}" class="px-3 py-2 text-ink/70 hover:text-ink">Log in</a>
            <a href="{{ route('register') }}" class="rounded-full bg-forest hover:bg-forest-dark text-white font-medium px-5 py-2.5 transition-colors">Get started free</a>
        </div>
        <button type="button" class="md:hidden p-2 rounded-lg hover:bg-black/[.05]" x-on:click="open = !open" aria-label="Menu">
            <x-icon name="menu" x-show="!open" /><x-icon name="x" x-show="open" x-cloak />
        </button>
    </div>
    <div x-show="open" x-cloak x-transition class="md:hidden border-t border-line bg-paper px-5 py-4 space-y-1 text-sm">
        <a href="#how" x-on:click="open = false" class="block py-2">How it works</a>
        <a href="#features" x-on:click="open = false" class="block py-2">Features</a>
        <a href="#latest" x-on:click="open = false" class="block py-2">Latest roles</a>
        <a href="#faq" x-on:click="open = false" class="block py-2">FAQ</a>
        <div class="pt-3 flex gap-2">
            <a href="{{ route('login') }}" class="flex-1 text-center rounded-full border border-line py-2.5">Log in</a>
            <a href="{{ route('register') }}" class="flex-1 text-center rounded-full bg-forest text-white font-medium py-2.5">Get started</a>
        </div>
    </div>
</header>

{{-- ───────── Hero ───────── --}}
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-grid pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-5 pt-16 pb-20 lg:pt-24 lg:pb-28 grid lg:grid-cols-[1.05fr_.95fr] gap-14 items-center">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-forest-200 bg-white px-3.5 py-1.5 text-xs font-medium text-forest">
                <x-icon name="sparkle" class="w-3.5 h-3.5" /> AI-assisted job hunting, you stay in control
            </span>
            <h1 class="mt-6 font-serif text-4xl sm:text-5xl lg:text-[3.5rem] font-semibold leading-[1.08] tracking-tight">
                Find the right jobs.<br><span class="text-forest">Apply in minutes,</span> not hours.
            </h1>
            <p class="mt-6 text-lg text-ink/65 max-w-xl leading-relaxed">
                Upload your CV once. Every hour we pull fresh roles, rank them against your real skills, and draft a tailored resume and cover letter for the good ones. Review them yourself, or let auto-apply send them within limits you set.
            </p>
            <div class="mt-9 flex flex-wrap items-center gap-3">
                <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-full bg-forest hover:bg-forest-dark text-white font-medium px-7 py-3.5 transition-colors shadow-card">
                    Create free account <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
                <a href="#how" class="inline-flex items-center gap-2 rounded-full border border-line bg-white hover:border-forest/40 font-medium px-7 py-3.5 transition-colors">See how it works</a>
            </div>
            <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-ink/60">
                <li class="flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4 text-forest" /> No credit card</li>
                <li class="flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4 text-forest" /> Your CV stays yours</li>
                <li class="flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4 text-forest" /> Never invents experience</li>
            </ul>
        </div>

        {{-- Product mock: what a match actually looks like --}}
        <div class="relative" x-data="{ step: 0 }" x-init="setInterval(() => step = (step + 1) % 4, 2200)">
            <div class="absolute -inset-6 bg-forest/10 blur-3xl rounded-[3rem]"></div>
            <div class="relative bg-white border border-line rounded-2xl shadow-pop p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-serif text-lg font-semibold">Senior Laravel Developer</p>
                        <p class="text-sm text-ink/55 mt-0.5 flex items-center gap-1.5"><x-icon name="building" class="w-3.5 h-3.5" /> Northwind Labs · Remote (worldwide)</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-forest text-white text-xs font-medium px-3 py-1">Strong match</span>
                </div>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    @foreach (['Laravel', 'PHP', 'Vue.js', 'PostgreSQL', 'REST'] as $tag)
                        <span class="rounded-full bg-gold-light text-gold text-xs px-2.5 py-1">{{ $tag }}</span>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-ink/50 flex items-center gap-1.5"><x-icon name="target" class="w-3.5 h-3.5" /> Laravel and PHP are in the job title; 5 of your skills appear in the description.</p>

                <div class="mt-5 rounded-xl bg-paper border border-line/80 p-4 space-y-3">
                    @foreach ([['Tailored resume ready', 'doc'], ['Cover letter drafted', 'mail'], ['Screening answers filled from your profile', 'shield'], ['Application submitted', 'bolt']] as $i => [$label, $ic])
                        <div class="flex items-center gap-3 text-sm transition-opacity duration-500" x-bind:class="step >= {{ $i }} ? 'opacity-100' : 'opacity-35'">
                            <span class="w-6 h-6 rounded-full grid place-items-center transition-colors duration-500" x-bind:class="step >= {{ $i }} ? 'bg-forest text-white' : 'bg-line text-ink/40'">
                                <x-icon name="check" class="w-3.5 h-3.5" />
                            </span>
                            <span class="flex-1">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <p class="relative mt-3 text-center text-xs text-ink/40">Illustration. Real matches come from your own CV.</p>
        </div>
    </div>
</section>

{{-- ───────── Live numbers (real, from the database) ───────── --}}
@if (($stats['tracked'] ?? 0) > 0)
    <section class="border-y border-line bg-white">
        <div class="max-w-6xl mx-auto px-5 py-8 grid grid-cols-3 gap-6 text-center">
            <div><p class="font-serif text-3xl sm:text-4xl font-semibold text-forest">{{ number_format($stats['tracked']) }}</p><p class="text-sm text-ink/55 mt-1">roles tracked</p></div>
            <div><p class="font-serif text-3xl sm:text-4xl font-semibold text-forest">{{ number_format($stats['last_day']) }}</p><p class="text-sm text-ink/55 mt-1">added in the last 24h</p></div>
            <div><p class="font-serif text-3xl sm:text-4xl font-semibold text-forest">{{ number_format($stats['sources']) }}</p><p class="text-sm text-ink/55 mt-1">job sources</p></div>
        </div>
    </section>
@endif

{{-- ───────── How it works ───────── --}}
<section id="how" class="max-w-6xl mx-auto px-5 py-20 lg:py-28">
    <div class="max-w-2xl">
        <p class="text-sm font-medium text-forest">How it works</p>
        <h2 class="mt-2 font-serif text-3xl sm:text-4xl font-semibold tracking-tight">Three steps to a calmer job search</h2>
        <p class="mt-4 text-ink/65">Most of the work in applying is repeating yourself. We do the repeating; you make the decisions.</p>
    </div>

    <div class="mt-12 grid md:grid-cols-3 gap-5">
        @foreach ([
            ['01', 'Build your profile', 'Upload your CV or point us at your site. We read it into a clean profile with standardised skills: NestJS, GraphQL and Microservices are three skills, not one messy line.', 'user'],
            ['02', 'Get matched, with reasons', 'Every role is scored against your core stack. A skill in the job title counts for more than one buried in the text, and broad terms like "Git" or "REST" never qualify a role on their own.', 'target'],
            ['03', 'Apply with guardrails', 'Review drafts yourself, or switch on auto-apply with a daily cap. Questions about visas, salary or relocation are answered only from what you wrote. Anything else waits for you.', 'bolt'],
        ] as [$n, $title, $copy, $ic])
            <div class="group bg-white border border-line rounded-2xl p-7 hover:shadow-card hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <span class="w-11 h-11 rounded-xl bg-forest-50 text-forest grid place-items-center group-hover:bg-forest group-hover:text-white transition-colors"><x-icon :name="$ic" class="w-5 h-5" /></span>
                    <span class="font-serif text-3xl text-ink/15 font-semibold">{{ $n }}</span>
                </div>
                <h3 class="mt-5 font-serif text-xl font-semibold">{{ $title }}</h3>
                <p class="mt-2.5 text-sm text-ink/65 leading-relaxed">{{ $copy }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ───────── Features ───────── --}}
<section id="features" class="bg-white border-y border-line">
    <div class="max-w-6xl mx-auto px-5 py-20 lg:py-28">
        <div class="max-w-2xl">
            <p class="text-sm font-medium text-forest">Features</p>
            <h2 class="mt-2 font-serif text-3xl sm:text-4xl font-semibold tracking-tight">Built to be honest and useful</h2>
        </div>
        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-10">
            @foreach ([
                ['search', 'Fresh roles every hour', 'Remote boards plus company career pages on Greenhouse, Lever and Ashby, merged into one feed.'],
                ['target', 'Matching you can inspect', 'See which skills matched, whether they were in the title, and a strong, good or weak rating. No black box.'],
                ['doc', 'Tailored resume + cover letter', 'For each role we reorder your real skills and projects and draft a letter from facts on your CV only.'],
                ['bolt', 'Auto-apply, rate-limited', 'Off, review-first or automatic. Daily caps, one attempt per job, and a master switch you control.'],
                ['shield', 'No guessing on the big questions', 'Work authorisation, salary and relocation come from your own answers, or the application pauses and asks you.'],
                ['globe', 'Region-aware', 'Spots "US only" and similar wording in remote roles so you don\'t waste applications, and can hide them entirely.'],
            ] as [$ic, $t, $c])
                <div class="flex gap-4">
                    <span class="shrink-0 w-10 h-10 rounded-lg bg-forest-50 text-forest grid place-items-center"><x-icon :name="$ic" class="w-5 h-5" /></span>
                    <div>
                        <h3 class="font-semibold">{{ $t }}</h3>
                        <p class="mt-1.5 text-sm text-ink/62 leading-relaxed">{{ $c }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ───────── Latest roles (live) ───────── --}}
@if ($latest->isNotEmpty())
    <section id="latest" class="max-w-6xl mx-auto px-5 py-20 lg:py-28">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-xl">
                <p class="text-sm font-medium text-forest">Fresh from the feed</p>
                <h2 class="mt-2 font-serif text-3xl sm:text-4xl font-semibold tracking-tight">The latest roles we found</h2>
            </div>
            <a href="{{ route('register') }}" class="text-sm font-medium text-forest hover:text-forest-dark inline-flex items-center gap-1.5">See how they match you <x-icon name="arrow-right" class="w-4 h-4" /></a>
        </div>
        <div class="mt-8 bg-white border border-line rounded-2xl divide-y divide-line overflow-hidden">
            @foreach ($latest as $job)
                <a href="{{ route('register') }}" class="flex flex-wrap items-center gap-x-6 gap-y-1 px-6 py-4 hover:bg-forest-50/60 transition-colors">
                    <div class="flex-1 min-w-[220px]">
                        <p class="font-medium">{{ $job->title }}</p>
                        <p class="text-sm text-ink/55">{{ $job->company ?? 'Company' }} · {{ $job->location ?? ($job->is_remote ? 'Remote' : 'Location not listed') }}</p>
                    </div>
                    <span class="rounded-full bg-forest-light text-forest-dark text-xs px-2.5 py-1">{{ $job->source }}</span>
                    <span class="text-xs text-ink/45 w-24 text-right">{{ $job->posted_at?->diffForHumans(short: true) }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- ───────── FAQ ───────── --}}
<section id="faq" class="bg-white border-y border-line">
    <div class="max-w-3xl mx-auto px-5 py-20 lg:py-24" x-data="{ open: 0 }">
        <h2 class="font-serif text-3xl sm:text-4xl font-semibold tracking-tight text-center">Questions, answered straight</h2>
        <div class="mt-10 divide-y divide-line border-y border-line">
            @foreach ([
                ['Does it apply on LinkedIn or Indeed?', 'No. Those platforms prohibit automated applications and ban accounts that attempt them, so we don\'t automate them. You still see those roles in your feed and apply the normal way.'],
                ['So where can auto-apply actually submit?', 'Through channels that allow it: email-apply roles, company boards where an employer has issued an API key, and a third-party apply service for many other career sites. Roles with no safe channel stay in your feed for a manual apply.'],
                ['Will it make things up on my applications?', 'No. Letters and tailored resumes only use skills, projects and experience that are on your CV. Questions it can\'t answer truthfully from your profile pause the application and ask you instead.'],
                ['Can I stop it from sending anything?', 'Yes. The default is drafts only. Auto-apply needs two switches on (your account mode and the install\'s live flag), plus a daily cap you choose.'],
                ['What happens to my CV?', 'It is stored on this install to build your profile and tailor your applications. It is never sold or used for ads.'],
            ] as $i => [$q, $a])
                <div>
                    <button type="button" class="w-full flex items-center justify-between gap-4 py-5 text-left" x-on:click="open = open === {{ $i }} ? null : {{ $i }}" x-bind:aria-expanded="open === {{ $i }}">
                        <span class="font-medium">{{ $q }}</span>
                        <x-icon name="chevron-down" class="w-5 h-5 shrink-0 text-ink/40 transition-transform" x-bind:class="open === {{ $i }} ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse x-cloak class="pb-5 -mt-1 text-sm text-ink/65 leading-relaxed pr-8">{{ $a }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ───────── Final CTA ───────── --}}
<section class="max-w-6xl mx-auto px-5 py-20 lg:py-24">
    <div class="relative overflow-hidden rounded-3xl bg-forest-dark text-white px-8 py-14 sm:px-14 text-center">
        <div class="absolute inset-0 opacity-40" style="background-image: radial-gradient(circle at 15% 20%, #2F7A68 0, transparent 45%), radial-gradient(circle at 90% 90%, #B8862E 0, transparent 40%);"></div>
        <div class="relative">
            <h2 class="font-serif text-3xl sm:text-4xl font-semibold tracking-tight max-w-2xl mx-auto">Ready to hand off the repetitive part?</h2>
            <p class="mt-4 text-white/75 max-w-xl mx-auto">Create an account, upload your CV, and see your first ranked matches today.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="rounded-full bg-white text-forest-dark font-medium px-7 py-3.5 hover:bg-forest-50 transition-colors">Get started free</a>
                <a href="{{ route('login') }}" class="rounded-full border border-white/30 hover:border-white/60 font-medium px-7 py-3.5 transition-colors">Log in</a>
            </div>
        </div>
    </div>
</section>

<footer class="border-t border-line">
    <div class="max-w-6xl mx-auto px-5 py-8 flex flex-wrap items-center justify-between gap-3 text-sm text-ink/50">
        <span>© {{ date('Y') }} {{ config('app.name', 'Job Feed') }}</span>
        <span class="flex gap-5"><a href="{{ route('login') }}" class="hover:text-ink">Log in</a><a href="{{ route('register') }}" class="hover:text-ink">Sign up</a><a href="#faq" class="hover:text-ink">FAQ</a></span>
    </div>
</footer>

</body>
</html>
