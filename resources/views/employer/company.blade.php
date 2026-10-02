@extends('layouts.app')

@section('title', 'Company profile')
@section('container', 'max-w-3xl')

@section('content')
    <h1 class="font-serif text-3xl font-semibold mb-1">Company profile</h1>
    <p class="text-sm text-ink/60 mb-6">Shown on every job you post, so candidates know who they'd be working for.</p>

    <form method="POST" action="{{ route('employer.company.update') }}" class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8 space-y-5">
        @csrf @method('PATCH')
        <div><label class="block text-sm font-medium mb-1.5">Company name</label><input name="company_name" value="{{ old('company_name', $user->company_name) }}" required class="input"></div>
        <div class="grid sm:grid-cols-2 gap-5">
            <div><label class="block text-sm font-medium mb-1.5">Website</label><input name="company_website" type="url" value="{{ old('company_website', $user->company_website) }}" placeholder="https://" class="input"></div>
            <div><label class="block text-sm font-medium mb-1.5">Company size</label>
                <select name="company_size" class="input"><option value="">Not specified</option>@foreach (['1-10', '11-50', '51-200', '201-1000', '1000+'] as $s)<option @selected(old('company_size', $user->company_size) === $s)>{{ $s }}</option>@endforeach</select>
            </div>
        </div>
        <div><label class="block text-sm font-medium mb-1.5">About the company</label><textarea name="company_description" rows="5" class="input" placeholder="What you do, who you serve, what working there is like.">{{ old('company_description', $user->company_description) }}</textarea></div>
        <button class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-2.5 transition-colors">Save company profile</button>
    </form>
@endsection
