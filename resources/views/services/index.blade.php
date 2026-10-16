@extends('layouts.app')
@section('title', 'Services')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Services</h1>
        <p class="text-sm text-slate-500">What the agent searches for and matches against.</p>
    </div>
    <a href="{{ route('services.create') }}" class="btn-primary">Add service</a>
</div>

<div class="space-y-3">
    @forelse ($services as $service)
        <div class="card flex items-start justify-between gap-4 p-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('services.edit', $service) }}" class="font-medium hover:text-indigo-600">{{ $service->name }}</a>
                    <span class="badge bg-slate-100 text-slate-600">{{ $service->preferred_engagement }}</span>
                    @if ($service->is_employment)<span class="badge bg-sky-100 text-sky-700">employment</span>@endif
                    @unless ($service->is_active)<span class="badge bg-amber-100 text-amber-700">inactive</span>@endunless
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($service->description, 140) }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach (array_slice($service->search_keywords ?? [], 0, 6) as $kw)
                        <span class="badge bg-indigo-50 text-indigo-700">{{ $kw }}</span>
                    @endforeach
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <a href="{{ route('services.edit', $service) }}" class="btn-secondary">Edit</a>
                <form method="POST" action="{{ route('services.destroy', $service) }}" onsubmit="return confirm('Delete this service?')">
                    @csrf @method('DELETE')
                    <button class="btn-danger">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">No services yet. Run the ServiceSeeder or add one.</div>
    @endforelse
</div>
@endsection
