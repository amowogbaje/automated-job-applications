@extends('agent.layout')
@section('agent_title', 'Projects')
@section('agent')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Portfolio projects</h1>
        <p class="text-sm text-slate-500">Evidence the agent can cite when recommending what to show.</p>
    </div>
    <a href="{{ route('agent.projects.create') }}" class="btn-primary">Add project</a>
</div>
<div class="space-y-3">
    @forelse ($projects as $project)
        <div class="card flex items-start justify-between gap-4 p-4">
            <div class="min-w-0">
                <a href="{{ route('agent.projects.edit', $project) }}" class="font-medium hover:text-forest-600">{{ $project->name }}</a>
                @if ($project->url)<a href="{{ $project->url }}" target="_blank" rel="noopener" class="ml-2 text-xs text-forest-600 hover:underline">{{ parse_url($project->url, PHP_URL_HOST) }}</a>@endif
                @unless ($project->is_public)<span class="badge ml-2 bg-amber-100 text-amber-700">private</span>@endunless
                <p class="mt-1 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($project->summary, 160) }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($project->technologies ?? [] as $t)<span class="badge bg-slate-100 text-slate-600">{{ $t }}</span>@endforeach
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <a href="{{ route('agent.projects.edit', $project) }}" class="btn-secondary">Edit</a>
                <form method="POST" action="{{ route('agent.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?')">
                    @csrf @method('DELETE')<button class="btn-danger">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">No projects yet. Add CraftProfessor, Storyverse and the rest.</div>
    @endforelse
</div>
@endsection
