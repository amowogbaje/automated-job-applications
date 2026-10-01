@props(['name', 'title', 'wide' => false])
{{-- Lives inside an element whose x-data has a `modal` property (see profile/edit). --}}
<div x-show="modal === '{{ $name }}'" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-label="{{ $title }}"
     x-on:keydown.escape.window="if (modal === '{{ $name }}') modal = null">
    <div class="absolute inset-0 bg-ink/50" x-show="modal === '{{ $name }}'" x-transition.opacity x-on:click="modal = null"></div>
    <div class="relative w-full {{ $wide ? 'max-w-2xl' : 'max-w-lg' }} max-h-[90vh] overflow-y-auto bg-white rounded-2xl shadow-pop"
         x-show="modal === '{{ $name }}'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-line">
            <h3 class="font-serif text-lg font-semibold">{{ $title }}</h3>
            <button type="button" x-on:click="modal = null" class="p-1 rounded-lg hover:bg-black/[.05]" aria-label="Close"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <div class="px-6 py-5">{{ $slot }}</div>
    </div>
</div>
