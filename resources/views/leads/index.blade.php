@extends('layouts.app')

@section('title', 'Leads')

@section('content')

    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <h1 class="font-serif text-2xl font-semibold text-ink">Leads</h1>
            <p class="text-sm text-ink/60 mt-1">Companies worth reaching out to — proactively, before they've even posted a job.</p>
        </div>
        <a href="{{ route('leads.discover') }}" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-4 py-2 transition-colors">
            Find leads
        </a>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        @foreach (['all' => 'All', 'new' => 'New', 'contacted' => 'Contacted', 'replied' => 'Replied', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label)
            <a href="{{ route('leads.index', $value === 'all' ? [] : ['status' => $value]) }}"
               class="rounded-full px-3 py-1.5 text-sm {{ $status === $value ? 'bg-ink text-white' : 'bg-white border border-line text-ink/70 hover:border-forest' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @php
        $railColor = ['new' => 'border-l-gold', 'contacted' => 'border-l-forest', 'replied' => 'border-l-forest', 'won' => 'border-l-forest-dark', 'lost' => 'border-l-rust'];
    @endphp

    @forelse ($leads as $lead)
        <div class="bg-white border border-line border-l-4 {{ $railColor[$lead->status] ?? 'border-l-line' }} rounded-lg px-5 py-4 mb-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="font-serif text-base font-semibold">
                        {{ $lead->company_name }}
                        @if ($lead->domain)
                            <a href="https://{{ $lead->domain }}" target="_blank" rel="noopener" class="text-sm font-sans font-normal text-ink/40 hover:text-forest">{{ $lead->domain }}</a>
                        @endif
                    </h2>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink/60 mt-1">
                        @if ($lead->country_code)<span>{{ $lead->country_code }}</span>@endif
                        @foreach ($lead->tech_stack ?? [] as $tech)
                            <span class="inline-flex items-center rounded-full bg-paper text-ink/60 text-xs px-2 py-0.5 border border-line">{{ $tech }}</span>
                        @endforeach
                    </div>
                </div>
                <span class="text-xs rounded-full px-2.5 py-1 {{ $lead->source === 'manual' ? 'bg-paper text-ink/50' : 'bg-forest-light text-forest-dark' }}">
                    {{ $lead->source === 'manual' ? 'Added manually' : 'Hunter Discover' }}
                </span>
            </div>

            @if ($lead->contact_email)
                <div class="mt-3 text-sm bg-forest-light border border-forest/20 rounded-lg px-3 py-2 text-forest-dark">
                    {{ $lead->contact_name ?: 'Contact' }}{{ $lead->contact_position ? ' — ' . $lead->contact_position : '' }} · <a href="mailto:{{ $lead->contact_email }}" class="font-medium underline">{{ $lead->contact_email }}</a>
                </div>
            @elseif ($lead->domain)
                <form method="POST" action="{{ route('leads.reveal', $lead) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-forest hover:text-forest-dark transition-colors">Reveal contact (uses a Hunter credit)</button>
                </form>
            @endif

            <form method="POST" action="{{ route('leads.update', $lead) }}" class="flex flex-wrap items-center gap-2 mt-4">
                @csrf
                @method('PATCH')
                <select name="status" onchange="this.form.submit()" class="rounded-md border-line focus:border-forest focus:ring-forest text-sm py-1.5">
                    @foreach (['new', 'contacted', 'replied', 'won', 'lost'] as $s)
                        <option value="{{ $s }}" @selected($lead->status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="text-sm px-3 py-1.5 border border-line rounded-md">Save</button></noscript>
            </form>

            <details class="mt-2">
                <summary class="text-sm text-ink/50 cursor-pointer hover:text-ink">Notes {{ $lead->notes ? '' : '(none yet)' }}</summary>
                <form method="POST" action="{{ route('leads.update', $lead) }}" class="mt-2 flex gap-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $lead->status }}">
                    <textarea name="notes" rows="2" class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm">{{ $lead->notes }}</textarea>
                    <button type="submit" class="text-sm px-3 py-1.5 bg-ink text-white rounded-md h-fit">Save</button>
                </form>
            </details>

            <form method="POST" action="{{ route('leads.destroy', $lead) }}" class="mt-2" onsubmit="return confirm('Remove this lead?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-ink/40 hover:text-rust">Remove</button>
            </form>
        </div>
    @empty
        <div class="bg-white border border-line rounded-lg px-6 py-10 text-center">
            <p class="text-ink/60 text-sm mb-3">No leads yet.</p>
            <a href="{{ route('leads.discover') }}" class="text-forest hover:text-forest-dark font-medium text-sm">Search for some &rarr;</a>
        </div>
    @endforelse

    <div class="mt-6">{{ $leads->links() }}</div>

    <details class="mt-8 bg-white border border-line rounded-lg px-5 py-4">
        <summary class="cursor-pointer text-sm font-medium text-ink/80">Add a lead manually</summary>
        <form method="POST" action="{{ route('leads.store') }}" class="mt-4 space-y-3">
            @csrf
            <div class="grid sm:grid-cols-2 gap-3">
                <input type="text" name="company_name" placeholder="Company name" required class="rounded-md border-line focus:border-forest focus:ring-forest text-sm">
                <input type="text" name="domain" placeholder="Domain (optional)" class="rounded-md border-line focus:border-forest focus:ring-forest text-sm">
                <input type="text" name="contact_name" placeholder="Contact name (optional)" class="rounded-md border-line focus:border-forest focus:ring-forest text-sm">
                <input type="email" name="contact_email" placeholder="Contact email (optional)" class="rounded-md border-line focus:border-forest focus:ring-forest text-sm">
            </div>
            <textarea name="notes" rows="2" placeholder="Notes (optional)" class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm"></textarea>
            <button type="submit" class="rounded-full bg-ink hover:bg-ink/80 text-white text-sm font-medium px-4 py-2 transition-colors">Add lead</button>
        </form>
    </details>

@endsection
