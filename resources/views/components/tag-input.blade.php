@props(['name', 'label', 'values' => [], 'hint' => null, 'placeholder' => 'Type and press Enter'])
{{-- Alpine tag editor. Submits as name[] hidden inputs. Enter or comma adds a tag. --}}
<div x-data="{
        tags: @js(array_values((array) old($name, $values))),
        input: '',
        add() {
            const parts = this.input.split(',').map(s => s.trim()).filter(Boolean);
            parts.forEach(p => { if (!this.tags.includes(p)) this.tags.push(p); });
            this.input = '';
        }
     }">
    <label class="label">{{ $label }}</label>
    <div class="rounded-md border border-slate-300 bg-white p-2 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
        <div class="flex flex-wrap gap-1.5">
            <template x-for="(tag, i) in tags" :key="tag">
                <span class="badge gap-1 bg-indigo-50 text-indigo-700">
                    <span x-text="tag"></span>
                    <button type="button" @click="tags.splice(i, 1)" class="hover:text-indigo-900" aria-label="Remove">&times;</button>
                    <input type="hidden" name="{{ $name }}[]" :value="tag">
                </span>
            </template>
            <input type="text" x-model="input" placeholder="{{ $placeholder }}"
                   @keydown.enter.prevent="add()" @keydown.comma.prevent="add()" @blur="add()"
                   class="min-w-[10rem] flex-1 border-0 p-1 text-sm focus:outline-none focus:ring-0">
        </div>
    </div>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
</div>
