@extends('layouts.app')
@section('container', 'max-w-6xl')
@section('title', trim($__env->yieldContent('agent_title', 'Opportunity Agent')))

@section('content')
@php
    $agentNav = [
        ['Overview', 'agent.dashboard', 'agent.dashboard'],
        ['Opportunities', 'agent.opportunities.index', ['agent.opportunities.*', 'agent.outreach.*']],
        ['Services', 'agent.services.index', 'agent.services.*'],
        ['Projects', 'agent.projects.index', 'agent.projects.*'],
        ['CVs', 'agent.cvs.index', 'agent.cvs.*'],
        ['Signatures', 'agent.signatures.index', 'agent.signatures.*'],
        ['Providers', 'agent.providers.index', 'agent.providers.*'],
    ];
@endphp
<div class="mb-6 flex flex-wrap items-center gap-1 border-b border-line pb-3 text-sm">
    <span class="mr-3 font-serif text-base font-semibold">Opportunity Agent</span>
    @foreach ($agentNav as [$label, $route, $pattern])
        <a href="{{ route($route) }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs(...(array) $pattern) ? 'bg-forest-50 font-medium text-forest' : 'text-ink/65 hover:bg-black/[.04] hover:text-ink' }}">{{ $label }}</a>
    @endforeach
</div>
<div class="agent">
    @yield('agent')
</div>
@endsection
