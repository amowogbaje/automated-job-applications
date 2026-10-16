@props(['status'])
@php
    $colors = [
        'discovered' => 'bg-slate-100 text-slate-700', 'researching' => 'bg-sky-100 text-sky-700',
        'qualified' => 'bg-indigo-100 text-indigo-700', 'drafted' => 'bg-violet-100 text-violet-700',
        'approved' => 'bg-violet-100 text-violet-700', 'contacted' => 'bg-blue-100 text-blue-700',
        'replied' => 'bg-teal-100 text-teal-700', 'won' => 'bg-green-100 text-green-800',
        'lost' => 'bg-red-100 text-red-700', 'rejected' => 'bg-red-50 text-red-600',
        'archived' => 'bg-slate-100 text-slate-500',
    ];
@endphp
<span class="badge {{ $colors[$status] ?? 'bg-slate-100 text-slate-600' }}">{{ config('opportunity.statuses')[$status] ?? $status }}</span>
