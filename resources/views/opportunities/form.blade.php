@extends('layouts.app')
@section('title', $opportunity->exists ? 'Edit opportunity' : 'New opportunity')
@section('content')
<h1 class="mb-6 text-2xl font-semibold">{{ $opportunity->exists ? 'Edit opportunity' : 'New opportunity' }}</h1>

<form method="POST" action="{{ $opportunity->exists ? route('opportunities.update', $opportunity) : route('opportunities.store') }}" class="space-y-6">
    @csrf
    @if ($opportunity->exists) @method('PUT') @endif

    <div class="card space-y-4 p-5">
        <h2 class="font-medium">Company</h2>
        <p class="-mt-2 text-xs text-slate-500">Matched by website domain, then by name, so the same company is never created twice.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="company_name">Company name</label>
                <input id="company_name" name="company_name" required class="input" value="{{ old('company_name', $company->name) }}">
            </div>
            <div>
                <label class="label" for="website_url">Website</label>
                <input id="website_url" name="website_url" type="url" placeholder="https://" class="input" value="{{ old('website_url', $company->website_url) }}">
            </div>
            <div>
                <label class="label" for="industry">Industry</label>
                <input id="industry" name="industry" class="input" value="{{ old('industry', $company->industry) }}">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="label" for="country">Country</label>
                    <input id="country" name="country" maxlength="2" placeholder="GB" class="input uppercase" value="{{ old('country', $company->country) }}">
                </div>
                <div class="col-span-2">
                    <label class="label" for="city">City</label>
                    <input id="city" name="city" class="input" value="{{ old('city', $company->city) }}">
                </div>
            </div>
            <div>
                <label class="label" for="size_band">Company size</label>
                <select id="size_band" name="size_band" class="input">
                    <option value="">Unknown</option>
                    @foreach (['1-10', '11-50', '51-200', '201-1000', '1000+'] as $z)
                        <option value="{{ $z }}" @selected(old('size_band', $company->size_band) === $z)>{{ $z }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card space-y-4 p-5">
        <h2 class="font-medium">Opportunity</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label" for="title">Title</label>
                <input id="title" name="title" required class="input" placeholder="e.g. Laravel developer vacancy / Reporting automation" value="{{ old('title', $opportunity->title) }}">
            </div>
            <div>
                <label class="label" for="type">Type</label>
                <select id="type" name="type" class="input">
                    @foreach (config('opportunity.types') as $k => $l)<option value="{{ $k }}" @selected(old('type', $opportunity->type) === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="source_url">Source URL</label>
                <input id="source_url" name="source_url" type="url" placeholder="https://" class="input" value="{{ old('source_url', $opportunity->source_url) }}">
            </div>
        </div>
        <div>
            <label class="label" for="summary">Summary</label>
            <textarea id="summary" name="summary" rows="2" class="input">{{ old('summary', $opportunity->summary) }}</textarea>
        </div>
        <div>
            <label class="label" for="business_need">Business need <span class="font-normal text-slate-500">(what the sources actually show)</span></label>
            <textarea id="business_need" name="business_need" rows="3" class="input">{{ old('business_need', $opportunity->business_need) }}</textarea>
        </div>
        <div>
            <label class="label" for="inferred_opportunity">Inferred opportunity <span class="font-normal text-slate-500">(your inference, not verified fact)</span></label>
            <textarea id="inferred_opportunity" name="inferred_opportunity" rows="3" class="input">{{ old('inferred_opportunity', $opportunity->inferred_opportunity) }}</textarea>
        </div>
    </div>

    <div class="card space-y-4 p-5">
        <h2 class="font-medium">Engagement and budget</h2>
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <label class="label" for="engagement_type">Engagement</label>
                <select id="engagement_type" name="engagement_type" class="input">
                    <option value="">Unknown</option>
                    @foreach (config('opportunity.engagements') as $k => $l)<option value="{{ $k }}" @selected(old('engagement_type', $opportunity->engagement_type) === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="budget_min">Budget min</label>
                <input id="budget_min" name="budget_min" type="number" min="0" class="input" value="{{ old('budget_min', $opportunity->budget_min) }}">
            </div>
            <div>
                <label class="label" for="budget_max">Budget max</label>
                <input id="budget_max" name="budget_max" type="number" min="0" class="input" value="{{ old('budget_max', $opportunity->budget_max) }}">
            </div>
            <div>
                <label class="label" for="budget_currency">Currency</label>
                <input id="budget_currency" name="budget_currency" maxlength="3" class="input uppercase" value="{{ old('budget_currency', $opportunity->budget_currency) }}">
            </div>
            <label class="flex items-center gap-2 text-sm sm:col-span-4"><input type="checkbox" name="budget_is_verified" value="1" @checked(old('budget_is_verified', $opportunity->budget_is_verified))> Budget comes from a source (not my estimate)</label>
            <div>
                <label class="label" for="posted_at">Posted</label>
                <input id="posted_at" name="posted_at" type="date" class="input" value="{{ old('posted_at', $opportunity->posted_at?->format('Y-m-d')) }}">
            </div>
            <div>
                <label class="label" for="expires_at">Closes</label>
                <input id="expires_at" name="expires_at" type="date" class="input" value="{{ old('expires_at', $opportunity->expires_at?->format('Y-m-d')) }}">
            </div>
        </div>
    </div>

    <div class="card space-y-4 p-5">
        <h2 class="font-medium">Matched services</h2>
        <div>
            <label class="label" for="primary_service_id">Strongest match (primary)</label>
            <select id="primary_service_id" name="primary_service_id" class="input">
                <option value="">None yet</option>
                @foreach ($services as $s)<option value="{{ $s->id }}" @selected(old('primary_service_id', $opportunity->primary_service_id) == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <span class="label">Also relevant</span>
            <div class="grid gap-1 sm:grid-cols-2">
                @foreach ($services as $s)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="service_ids[]" value="{{ $s->id }}" @checked(in_array($s->id, (array) old('service_ids', $selectedServices)))> {{ $s->name }}</label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex gap-2">
        <button class="btn-primary">Save opportunity</button>
        <a href="{{ $opportunity->exists ? route('opportunities.show', $opportunity) : route('opportunities.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
