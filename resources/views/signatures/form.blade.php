@extends('layouts.app')
@section('title', $signature->exists ? 'Edit signature' : 'New signature')
@section('content')
<h1 class="mb-6 text-2xl font-semibold">{{ $signature->exists ? 'Edit signature' : 'New signature' }}</h1>
<form method="POST" action="{{ $signature->exists ? route('signatures.update', $signature) : route('signatures.store') }}" class="card space-y-4 p-5">
    @csrf
    @if ($signature->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="name">Name</label>
            <input id="name" name="name" class="input" required value="{{ old('name', $signature->name) }}">
        </div>
        <div>
            <label class="label" for="channel">Channel</label>
            <select id="channel" name="channel" class="input">
                @foreach (['email' => 'Email', 'whatsapp' => 'WhatsApp', 'form' => 'Contact form', 'any' => 'Any'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('channel', $signature->channel) === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="label" for="body">Signature text</label>
        <textarea id="body" name="body" rows="5" required class="input font-mono">{{ old('body', $signature->body) }}</textarea>
        <p class="mt-1 text-xs text-slate-500">Plain text: your name, title, portfolio URL. Only include details you want sent.</p>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $signature->is_default))> Default for this channel</label>
    <div class="flex gap-2">
        <button class="btn-primary">Save signature</button>
        <a href="{{ route('signatures.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
