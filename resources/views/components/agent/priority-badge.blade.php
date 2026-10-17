@props(['priority' => null])
@if ($priority)
    <span class="badge {{ ['A' => 'bg-green-100 text-green-800', 'B' => 'bg-amber-100 text-amber-800', 'C' => 'bg-slate-200 text-slate-700'][$priority] ?? 'bg-slate-100 text-slate-600' }}">Priority {{ $priority }}</span>
@endif
