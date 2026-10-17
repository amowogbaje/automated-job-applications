{{-- Dashboard tabs: Overview (owner only) | Jobs | Job interests. $active = overview|jobs|interests --}}
@php
    $tabUser = auth()->user();
    $newMatches = \App\Models\JobListingUserState::query()
        ->where('user_id', $tabUser->id)->whereNotNull('matched_at')
        ->where('is_dismissed', false)->where('is_applied', false)
        ->where('matched_at', '>=', now()->subDay())->count();
    $tabs = [];
    if ($tabUser->canUseAgent()) {
        $tabs[] = ['overview', 'Overview', route('agent.dashboard')];
    }
    $tabs[] = ['jobs', 'Jobs', route('jobs.index')];
    $tabs[] = ['interests', 'Job interests', route('jobs.interests')];
@endphp
<nav class="mb-6 flex gap-1 border-b border-slate-200 text-sm" aria-label="Dashboard">
    @foreach ($tabs as [$key, $label, $url])
        <a href="{{ $url }}" class="-mb-px border-b-2 px-4 py-2.5 font-medium {{ $active === $key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            {{ $label }}
            @if ($key === 'jobs' && $newMatches > 0)
                <span class="ml-1 rounded-full bg-indigo-600 px-1.5 py-0.5 text-[11px] font-semibold text-white">{{ $newMatches > 99 ? '99+' : $newMatches }}</span>
            @endif
        </a>
    @endforeach
</nav>
