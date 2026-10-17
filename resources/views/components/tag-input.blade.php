@props(['name', 'label', 'help' => null, 'values' => [], 'suggest' => [], 'tone' => 'indigo', 'placeholder' => 'Type and press Enter'])
@php
    $chip = $tone === 'red' ? 'bg-red-50 text-red-700' : 'bg-indigo-50 text-indigo-700';
@endphp
<div x-data="{
        items: @js(array_values($values)), draft: '', suggest: @js(array_values($suggest)),
        has(s) { return this.items.some(i => i.toLowerCase() === s.toLowerCase()) },
        push(s) { s = s.trim(); if (s && !this.has(s)) this.items.push(s) },
        add() { this.draft.split(',').forEach(s => this.push(s)); this.draft = '' },
        remove(i) { this.items.splice(i, 1) }
    }">
    <label class="label">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" x-bind:value="items.join(', ')">
    <div class="flex flex-wrap gap-2 rounded-md border border-slate-300 bg-white p-2 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
        <template x-for="(item, i) in items" :key="item">
            <span class="inline-flex items-center gap-1 rounded-full {{ $chip }} py-1 pl-3 pr-1.5 text-sm">
                <span x-text="item"></span>
                <button type="button" x-on:click="remove(i)" class="grid h-[18px] w-[18px] place-items-center rounded-full hover:bg-black/10" aria-label="Remove">×</button>
            </span>
        </template>
        <input type="text" x-model="draft" x-on:keydown.enter.prevent="add()" x-on:keydown.comma.prevent="add()" x-on:blur="add()"
               x-on:keydown.backspace="if (!draft && items.length) items.pop()"
               placeholder="{{ $placeholder }}" class="min-w-[140px] flex-1 border-0 bg-transparent px-1 py-1 text-sm focus:outline-none focus:ring-0">
    </div>
    @if ($suggest)
        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs" x-show="suggest.some(s => !has(s))" x-cloak>
            <span class="text-slate-500">From your resume, click to add:</span>
            <template x-for="s in suggest.filter(s => !has(s))" :key="s">
                <button type="button" x-on:click="push(s)" class="badge border border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-700" x-text="'+ ' + s"></button>
            </template>
        </div>
    @endif
    @if ($help)<p class="mt-1.5 text-xs text-slate-500">{{ $help }}</p>@endif
</div>
