@extends('layouts.app')

@section('title', 'Tasks')
@section('container', 'max-w-4xl')

@section('content')
<div x-data="taskBoard(@js(['tasks' => $tasks, 'projects' => $projects, 'selected' => $selectedProjectId]), @js([
        'store' => route('tasks.store'), 'reorder' => route('tasks.reorder'), 'clear' => route('tasks.clearCompleted'),
        'task' => url('/tasks'), 'projects' => route('projects.store'), 'project' => url('/projects'),
    ]))"
    x-init="boot()" x-cloak
    x-on:keydown.window="shortcuts($event)">

    {{-- ───── Header: title, live counts, progress ring ───── --}}
    <header class="flex items-center justify-between gap-6 mb-6">
        <div class="min-w-0">
            <h1 class="font-serif text-3xl font-semibold">Tasks</h1>
            <p class="text-sm text-ink/60 mt-1" x-text="summary"></p>
        </div>
        <div class="relative w-16 h-16 shrink-0" x-bind:title="progress.done + ' of ' + progress.total + ' done'">
            <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90">
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#E2DED3" stroke-width="3" />
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#1F5D50" stroke-width="3" stroke-linecap="round" class="transition-all duration-700 ease-out"
                    x-bind:stroke-dasharray="(progress.pct * 0.9743) + ' 97.43'" />
            </svg>
            <span class="absolute inset-0 grid place-items-center text-sm font-semibold" x-text="progress.pct + '%'"></span>
        </div>
    </header>

    {{-- ───── Quick add ───── --}}
    <section class="bg-white border border-line rounded-2xl shadow-card p-3 mb-5 flex flex-wrap items-center gap-2">
        <span class="w-9 h-9 rounded-full bg-forest-50 text-forest grid place-items-center shrink-0"><x-icon name="plus" class="w-[18px] h-[18px]" /></span>
        <input type="text" x-ref="newInput" x-model="newName" maxlength="255" autocomplete="off"
            x-on:keydown.enter.prevent="addTask()"
            placeholder="Add a task, then press Enter"
            class="flex-1 min-w-[180px] bg-transparent border-0 focus:ring-0 focus:outline-none text-[15px] py-2">

        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
            <button type="button" x-on:click="open = !open" class="inline-flex items-center gap-2 rounded-full border border-line hover:border-forest/40 px-3.5 py-2 text-sm transition-colors">
                <span class="w-2.5 h-2.5 rounded-full" x-bind:style="'background:' + dot(newProject)"></span>
                <span class="max-w-[140px] truncate" x-text="projectName(newProject) || 'No project'"></span>
                <x-icon name="chevron-down" class="w-4 h-4 text-ink/40" />
            </button>
            <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-56 bg-white border border-line rounded-xl shadow-pop py-1.5 z-30 max-h-72 overflow-y-auto">
                <button type="button" x-on:click="newProject = null; open = false; $refs.newInput.focus()" class="w-full text-left flex items-center gap-2 px-3.5 py-2 text-sm hover:bg-black/[.04]"><span class="w-2.5 h-2.5 rounded-full bg-line"></span> No project</button>
                <template x-for="p in projects" :key="p.id">
                    <button type="button" x-on:click="newProject = p.id; open = false; $refs.newInput.focus()" class="w-full text-left flex items-center gap-2 px-3.5 py-2 text-sm hover:bg-black/[.04]">
                        <span class="w-2.5 h-2.5 rounded-full" x-bind:style="'background:' + dot(p.id)"></span><span class="truncate" x-text="p.name"></span>
                    </button>
                </template>
            </div>
        </div>

        <button type="button" x-on:click="addTask()" x-bind:disabled="!newName.trim()" class="rounded-full bg-forest hover:bg-forest-dark disabled:opacity-40 text-white text-sm font-medium px-5 py-2.5 transition-colors">Add</button>
    </section>

    {{-- ───── Project chips + search ───── --}}
    <div class="flex flex-wrap items-center gap-2 mb-5">
        <button type="button" x-on:click="setFilter('all')" class="chip" x-bind:class="filter === 'all' ? 'chip-on' : ''">All <span class="chip-n" x-text="openCount('all')"></span></button>

        <template x-for="p in projects" :key="p.id">
            <button type="button" x-on:click="setFilter(p.id)" class="chip" x-bind:class="filter === p.id ? 'chip-on' : ''">
                <span class="w-2 h-2 rounded-full" x-bind:style="'background:' + dot(p.id)"></span>
                <span class="max-w-[120px] truncate" x-text="p.name"></span>
                <span class="chip-n" x-text="openCount(p.id)"></span>
            </button>
        </template>

        <button type="button" x-show="hasUnassigned" x-on:click="setFilter('none')" class="chip" x-bind:class="filter === 'none' ? 'chip-on' : ''">No project <span class="chip-n" x-text="openCount('none')"></span></button>

        <template x-if="!addingProject">
            <button type="button" x-on:click="addingProject = true; $nextTick(() => $refs.projectInput.focus())" class="chip border-dashed text-forest"><x-icon name="plus" class="w-3.5 h-3.5" /> New project</button>
        </template>
        <form x-show="addingProject" x-cloak x-on:submit.prevent="addProject()" class="inline-flex items-center gap-1.5">
            <input x-ref="projectInput" x-model="projectDraft" maxlength="60" placeholder="Project name" x-on:keydown.escape="addingProject = false; projectDraft = ''" class="rounded-full border border-forest bg-white px-3.5 py-1.5 text-sm w-40 focus:outline-none focus:ring-2 focus:ring-forest/20">
            <button class="rounded-full bg-forest text-white text-xs font-medium px-3 py-1.5">Create</button>
            <button type="button" x-on:click="addingProject = false; projectDraft = ''" class="text-xs text-ink/50 hover:text-ink px-1">Cancel</button>
        </form>

        <div class="relative ml-auto">
            <x-icon name="search" class="w-4 h-4 text-ink/35 absolute left-3 top-1/2 -translate-y-1/2" />
            <input type="search" x-ref="search" x-model="search" placeholder="Search  ( / )" class="rounded-full border border-line bg-white pl-9 pr-4 py-2 text-sm w-44 focus:w-60 transition-all focus:outline-none focus:border-forest focus:ring-2 focus:ring-forest/15">
        </div>
    </div>

    {{-- Selected-project header (rename / delete) --}}
    <div x-show="typeof filter === 'number'" x-cloak x-transition class="flex flex-wrap items-center gap-3 mb-4 px-1">
        <template x-if="!renaming">
            <h2 class="font-serif text-xl font-semibold flex items-center gap-2"><span class="w-3 h-3 rounded-full" x-bind:style="'background:' + dot(filter)"></span><span x-text="projectName(filter)"></span></h2>
        </template>
        <form x-show="renaming" x-cloak x-on:submit.prevent="renameProject()" class="flex items-center gap-2">
            <input x-ref="renameInput" x-model="renameDraft" maxlength="60" x-on:keydown.escape="renaming = false" class="input w-56">
            <button class="rounded-full bg-forest text-white text-xs font-medium px-3 py-1.5">Save</button>
            <button type="button" x-on:click="renaming = false" class="text-xs text-ink/50">Cancel</button>
        </form>
        <div class="flex items-center gap-1 text-sm" x-show="!renaming">
            <button type="button" x-on:click="renaming = true; renameDraft = projectName(filter); $nextTick(() => $refs.renameInput.focus())" class="p-1.5 rounded-lg text-ink/45 hover:text-forest hover:bg-forest-50" title="Rename project"><x-icon name="pencil" class="w-4 h-4" /></button>
            <template x-if="!confirmDeleteProject"><button type="button" x-on:click="confirmDeleteProject = true" class="p-1.5 rounded-lg text-ink/45 hover:text-rust hover:bg-rust-light" title="Delete project"><x-icon name="trash" class="w-4 h-4" /></button></template>
            <span x-show="confirmDeleteProject" x-cloak class="inline-flex items-center gap-2 rounded-full bg-rust-light text-rust px-3 py-1 text-xs">
                Delete project? Its tasks are kept.
                <button type="button" x-on:click="deleteProject()" class="font-semibold underline">Delete</button>
                <button type="button" x-on:click="confirmDeleteProject = false" class="text-ink/60">Cancel</button>
            </span>
        </div>
    </div>

    {{-- ───── Active tasks (drag to reorder) ───── --}}
    <ul x-ref="list" class="space-y-2.5">
        <template x-for="(t, i) in active" :key="t.id">
            <li x-bind:data-id="t.id" x-bind:data-task="isTemp(t) ? null : ''"
                class="task-card group bg-white border border-line rounded-2xl shadow-card px-3 py-3 flex items-center gap-3 transition-all"
                x-bind:class="isTemp(t) ? 'opacity-60' : 'hover:border-forest/30'"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                x-effect="if (editing === t.id) $nextTick(() => $refs.editInput && $refs.editInput.focus())">

                <span data-handle class="cursor-grab active:cursor-grabbing text-ink/20 group-hover:text-ink/45 select-none touch-none shrink-0" title="Drag to reorder" x-show="!search">
                    <svg viewBox="0 0 16 16" class="w-4 h-5" fill="currentColor"><circle cx="5" cy="3" r="1.3"/><circle cx="11" cy="3" r="1.3"/><circle cx="5" cy="8" r="1.3"/><circle cx="11" cy="8" r="1.3"/><circle cx="5" cy="13" r="1.3"/><circle cx="11" cy="13" r="1.3"/></svg>
                </span>

                <button type="button" x-on:click="toggle(t)" x-bind:disabled="isTemp(t)" class="check shrink-0" aria-label="Mark done">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-white opacity-0" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                </button>

                <div class="flex-1 min-w-0">
                    <template x-if="editing !== t.id">
                        <div>
                            <p class="text-[15px] font-medium leading-snug break-words" x-text="t.name" x-on:dblclick="startEdit(t)"></p>
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink/45">
                                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                                    <button type="button" x-on:click="open = !open" x-bind:disabled="isTemp(t)" class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 transition-colors"
                                        x-bind:style="t.project_id ? 'background:' + pal(t.project_id).bg + ';color:' + pal(t.project_id).fg : ''" x-bind:class="t.project_id ? '' : 'bg-paper text-ink/45 hover:text-ink'">
                                        <span class="w-1.5 h-1.5 rounded-full" x-bind:style="'background:' + dot(t.project_id)"></span>
                                        <span x-text="projectName(t.project_id) || 'No project'"></span>
                                    </button>
                                    <div x-show="open" x-cloak x-transition.origin.top.left class="absolute left-0 mt-1.5 w-52 bg-white border border-line rounded-xl shadow-pop py-1.5 z-30 max-h-64 overflow-y-auto text-sm text-ink">
                                        <button type="button" x-on:click="move(t, null); open = false" class="w-full text-left px-3.5 py-2 hover:bg-black/[.04]">No project</button>
                                        <template x-for="p in projects" :key="p.id"><button type="button" x-on:click="move(t, p.id); open = false" class="w-full text-left flex items-center gap-2 px-3.5 py-2 hover:bg-black/[.04]"><span class="w-2 h-2 rounded-full" x-bind:style="'background:' + dot(p.id)"></span><span class="truncate" x-text="p.name"></span></button></template>
                                    </div>
                                </div>
                                <span x-text="isTemp(t) ? 'saving…' : ago(t.updated_at)"></span>
                            </div>
                        </div>
                    </template>
                    <div x-show="editing === t.id" x-cloak>
                        <input x-ref="editInput" x-model="draft" maxlength="255" class="input" x-on:keydown.enter.prevent="saveEdit(t)" x-on:keydown.escape="editing = null" x-on:blur="saveEdit(t)">
                        <p class="text-[11px] text-ink/40 mt-1">Enter to save · Esc to cancel</p>
                    </div>
                </div>

                <span class="text-xs font-mono text-ink/25 w-5 text-right shrink-0 group-hover:hidden" x-show="!search" x-text="i + 1"></span>
                <div class="shrink-0 hidden group-hover:flex items-center gap-0.5" x-bind:class="isTemp(t) ? '!hidden' : ''">
                    <button type="button" x-on:click="startEdit(t)" class="p-1.5 rounded-lg text-ink/40 hover:text-forest hover:bg-forest-50" title="Edit"><x-icon name="pencil" class="w-4 h-4" /></button>
                    <button type="button" x-on:click="remove(t)" class="p-1.5 rounded-lg text-ink/40 hover:text-rust hover:bg-rust-light" title="Delete"><x-icon name="trash" class="w-4 h-4" /></button>
                </div>
            </li>
        </template>
    </ul>

    {{-- Empty states --}}
    <div x-show="!active.length && !done.length" x-cloak class="bg-white border border-line rounded-2xl px-6 py-14 text-center">
        <span class="mx-auto w-14 h-14 rounded-full bg-forest-50 text-forest grid place-items-center"><x-icon :name="'check'" class="w-7 h-7" /></span>
        <p class="mt-4 font-serif text-xl font-semibold" x-text="search ? 'Nothing matches “' + search + '”' : (tasks.length ? 'Nothing here yet' : 'A clean slate')"></p>
        <p class="text-sm text-ink/55 mt-1" x-text="search ? 'Try a different word.' : 'Type a task above and press Enter. It appears instantly.'"></p>
    </div>
    <div x-show="!active.length && done.length" x-cloak class="rounded-2xl border border-forest-200 bg-forest-50 px-6 py-6 text-center text-forest-dark">
        <p class="font-medium">All caught up. Nice work.</p>
    </div>

    {{-- ───── Completed ───── --}}
    <section x-show="done.length" x-cloak class="mt-8">
        <div class="flex items-center justify-between mb-2.5 px-1">
            <button type="button" x-on:click="showDone = !showDone; pref('showDone', showDone)" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink/60 hover:text-ink">
                <x-icon name="chevron-down" class="w-4 h-4 transition-transform" x-bind:class="showDone ? '' : '-rotate-90'" /> Completed <span class="chip-n" x-text="done.length"></span>
            </button>
            <button type="button" x-show="showDone" x-on:click="clearDone()" class="text-xs text-ink/45 hover:text-rust">Clear completed</button>
        </div>
        <ul class="space-y-2" x-show="showDone" x-collapse>
            <template x-for="t in done" :key="t.id">
                <li class="group bg-white/70 border border-line rounded-2xl px-3 py-2.5 flex items-center gap-3" x-transition>
                    <button type="button" x-on:click="toggle(t)" class="check check-on shrink-0 ml-7" aria-label="Mark not done">
                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                    </button>
                    <p class="flex-1 min-w-0 text-sm text-ink/45 line-through decoration-ink/25 truncate" x-text="t.name"></p>
                    <span class="text-xs text-ink/35 hidden sm:inline" x-text="projectName(t.project_id)"></span>
                    <span class="text-xs text-ink/30" x-text="ago(t.completed_at)"></span>
                    <button type="button" x-on:click="remove(t)" class="p-1.5 rounded-lg text-ink/30 hover:text-rust hover:bg-rust-light opacity-0 group-hover:opacity-100" title="Delete"><x-icon name="trash" class="w-4 h-4" /></button>
                </li>
            </template>
        </ul>
    </section>

    <p class="mt-10 text-center text-xs text-ink/35">Drag to reorder · double-click a task to edit · <kbd class="kbd">n</kbd> new task · <kbd class="kbd">/</kbd> search · changes save instantly</p>

    {{-- ───── Toasts ───── --}}
    <div class="fixed bottom-5 left-1/2 -translate-x-1/2 z-50 flex flex-col items-center gap-2 pointer-events-none px-4 w-full max-w-md" aria-live="polite">
        <template x-for="m in toasts" :key="m.id">
            <div class="pointer-events-auto w-full flex items-center gap-3 rounded-xl px-4 py-3 text-sm shadow-pop"
                x-bind:class="m.type === 'error' ? 'bg-rust text-white' : 'bg-ink text-white'"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <span class="flex-1" x-text="m.text"></span>
                <button type="button" x-show="m.action" x-on:click="m.action.run(); dismiss(m.id)" class="font-semibold underline underline-offset-2" x-text="m.action ? m.action.label : ''"></button>
                <button type="button" x-on:click="dismiss(m.id)" class="text-white/60 hover:text-white" aria-label="Dismiss"><x-icon name="x" class="w-4 h-4" /></button>
            </div>
        </template>
    </div>
</div>

<style>
    .chip { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid #E2DED3; background: #fff; border-radius: 9999px; padding: .4rem .85rem; font-size: .8125rem; color: rgba(26,34,32,.7); transition: all .15s; }
    .chip:hover { border-color: rgba(31,93,80,.4); color: #1A2220; }
    .chip-on { background: #1F5D50; border-color: #1F5D50; color: #fff !important; }
    .chip-n { font-size: .6875rem; opacity: .6; font-variant-numeric: tabular-nums; }
    .check { width: 1.5rem; height: 1.5rem; border-radius: 9999px; border: 2px solid #CFCABB; display: grid; place-items: center; background: #fff; transition: all .2s; }
    .check:hover { border-color: #1F5D50; background: #E7F0EC; }
    .check:hover svg { opacity: .35; color: #1F5D50; }
    .check-on, .check-on:hover { background: #1F5D50; border-color: #1F5D50; }
    .kbd { border: 1px solid #E2DED3; border-bottom-width: 2px; border-radius: .3rem; padding: 0 .35rem; font-size: .6875rem; background: #fff; }
    .sortable-ghost { opacity: .35; background: #E7F0EC; }
    .sortable-chosen { box-shadow: 0 12px 40px rgba(26,34,32,.18); }
</style>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15/Sortable.min.js"></script>
<script>
function taskBoard(init, urls) {
    const PALETTE = [
        { dot: '#1F5D50', bg: '#E7F0EC', fg: '#154138' }, { dot: '#B8862E', bg: '#FBF2DF', fg: '#7A5A1C' },
        { dot: '#3B6FD9', bg: '#E8EFFC', fg: '#244A9A' }, { dot: '#8B5CF6', bg: '#F0EAFE', fg: '#5B38B0' },
        { dot: '#D6457A', bg: '#FCE8F0', fg: '#962C53' }, { dot: '#0E9AA7', bg: '#E1F5F7', fg: '#0A6A73' },
        { dot: '#E0742B', bg: '#FDEEE2', fg: '#9A4E1C' }, { dot: '#64748B', bg: '#EEF1F5', fg: '#475569' },
    ];
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const store = (k, v) => { try { localStorage.setItem('tasks.' + k, JSON.stringify(v)); } catch (e) {} };
    const load = (k, d) => { try { const v = localStorage.getItem('tasks.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } };

    return {
        tasks: init.tasks, projects: init.projects,
        filter: 'all', search: '', showDone: true,
        newName: '', newProject: null, addingProject: false, projectDraft: '',
        renaming: false, renameDraft: '', confirmDeleteProject: false,
        editing: null, draft: '', toasts: [], now: Date.now(), seq: 0, sortable: null,

        boot() {
            this.showDone = load('showDone', true);
            const saved = load('filter', 'all');
            const want = init.selected ?? saved;
            this.filter = (want === 'all' || want === 'none' || this.projects.some(p => p.id === want)) ? want : 'all';
            this.newProject = typeof this.filter === 'number' ? this.filter : null;
            this.$watch('filter', v => { this.newProject = typeof v === 'number' ? v : null; this.renaming = false; this.confirmDeleteProject = false; store('filter', v); });
            setInterval(() => { this.now = Date.now(); }, 60000);
            this.$nextTick(() => this.initSortable());
        },

        // ───── derived state ─────
        inScope(t) { return this.filter === 'all' || (this.filter === 'none' ? !t.project_id : t.project_id === this.filter); },
        matches(t) {
            const q = this.search.trim().toLowerCase();
            return !q || t.name.toLowerCase().includes(q) || (this.projectName(t.project_id) || '').toLowerCase().includes(q);
        },
        get active() { return this.tasks.filter(t => !t.completed && this.inScope(t) && this.matches(t)); },
        get done() {
            return this.tasks.filter(t => t.completed && this.inScope(t) && this.matches(t))
                .sort((a, b) => (b.completed_at || '').localeCompare(a.completed_at || ''));
        },
        get progress() {
            const scope = this.tasks.filter(t => this.inScope(t));
            const done = scope.filter(t => t.completed).length;
            return { done, total: scope.length, pct: scope.length ? Math.round(done / scope.length * 100) : 0 };
        },
        get summary() {
            const open = this.tasks.filter(t => !t.completed && this.inScope(t)).length;
            if (!this.tasks.length) return 'Nothing to do yet';
            return open === 0 ? 'Everything here is done' : `${open} to do · ${this.progress.done} done`;
        },
        get hasUnassigned() { return this.tasks.some(t => !t.project_id && !t.completed); },
        openCount(f) { return this.tasks.filter(t => !t.completed && (f === 'all' || (f === 'none' ? !t.project_id : t.project_id === f))).length; },
        projectName(id) { const p = this.projects.find(p => p.id === id); return p ? p.name : null; },
        pal(id) { const i = this.projects.findIndex(p => p.id === id); return PALETTE[(i < 0 ? 7 : i) % PALETTE.length]; },
        dot(id) { return id ? this.pal(id).dot : '#CFCABB'; },
        isTemp(t) { return typeof t.id === 'string'; },
        ago(iso) {
            void this.now;
            if (!iso) return '';
            const s = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
            const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
            for (const [unit, sec] of [['day', 86400], ['hour', 3600], ['minute', 60]]) {
                if (Math.abs(s) >= sec) return rtf.format(Math.round(s / sec), unit);
            }
            return 'just now';
        },
        setFilter(f) {
            this.filter = f;
            const url = new URL(location.href);
            typeof f === 'number' ? url.searchParams.set('project_id', f) : url.searchParams.delete('project_id');
            history.replaceState(null, '', url);
        },
        pref(k, v) { store(k, v); },

        // ───── network + feedback ─────
        async api(method, url, body) {
            let res;
            try {
                res = await fetch(url, {
                    method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    body: body === undefined ? undefined : JSON.stringify(body),
                });
            } catch (e) { throw new Error('You appear to be offline. Nothing was saved.'); }
            if (res.status === 419) throw new Error('Your session expired. Refresh the page and try again.');
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Something went wrong.'));
            return data;
        },
        toast(text, opts = {}) {
            const id = ++this.seq;
            this.toasts.push({ id, text, type: opts.type || 'info', action: opts.action || null });
            setTimeout(() => this.dismiss(id), opts.ms || (opts.action ? 7000 : 4000));
        },
        dismiss(id) { this.toasts = this.toasts.filter(m => m.id !== id); },
        shortcuts(e) {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable;
            if (typing || e.metaKey || e.ctrlKey || e.altKey) return;
            if (e.key === 'n') { e.preventDefault(); this.$refs.newInput.focus(); }
            if (e.key === '/') { e.preventDefault(); this.$refs.search.focus(); }
        },
        replace(oldId, task) { const i = this.tasks.findIndex(t => t.id === oldId); if (i >= 0) this.tasks.splice(i, 1, task); },

        // ───── tasks ─────
        async addTask() {
            const name = this.newName.trim();
            if (!name) return;
            const tmpId = 'tmp-' + (++this.seq);
            const nowIso = new Date().toISOString();
            // Appears instantly; the server fills in the real id a moment later.
            this.tasks.push({ id: tmpId, name, project_id: this.newProject, completed: false, completed_at: null, created_at: nowIso, updated_at: nowIso });
            this.newName = '';
            this.$refs.newInput.focus();
            try {
                const { task } = await this.api('POST', urls.store, { name, project_id: this.newProject });
                this.replace(tmpId, task);
            } catch (e) {
                this.tasks = this.tasks.filter(t => t.id !== tmpId);
                this.newName = name;
                this.toast(e.message, { type: 'error' });
            }
        },
        async toggle(t) {
            if (this.isTemp(t)) return;
            const prev = { completed: t.completed, completed_at: t.completed_at };
            t.completed = !t.completed;
            t.completed_at = t.completed ? new Date().toISOString() : null;
            try {
                const { task } = await this.api('PATCH', `${urls.task}/${t.id}`, { completed: t.completed });
                Object.assign(t, task);
                if (t.completed) this.toast('Nice. Marked done.', { action: { label: 'Undo', run: () => this.toggle(t) } });
            } catch (e) { Object.assign(t, prev); this.toast(e.message, { type: 'error' }); }
        },
        startEdit(t) { if (this.isTemp(t)) return; this.editing = t.id; this.draft = t.name; },
        async saveEdit(t) {
            if (this.editing !== t.id) return;
            const name = this.draft.trim();
            this.editing = null;
            if (!name || name === t.name) return;
            const prev = t.name;
            t.name = name;
            try { Object.assign(t, (await this.api('PATCH', `${urls.task}/${t.id}`, { name })).task); }
            catch (e) { t.name = prev; this.toast(e.message, { type: 'error' }); }
        },
        async move(t, projectId) {
            if (t.project_id === projectId) return;
            const prev = { project_id: t.project_id }, order = this.tasks.slice();
            t.project_id = projectId;
            // Moving to another project sends the task to the bottom of the list, matching the server.
            this.tasks = this.tasks.filter(x => x !== t).concat(t);
            try { Object.assign(t, (await this.api('PATCH', `${urls.task}/${t.id}`, { project_id: projectId })).task); }
            catch (e) { t.project_id = prev.project_id; this.tasks = order; this.toast(e.message, { type: 'error' }); }
        },
        async remove(t) {
            if (this.isTemp(t)) return;
            const index = this.tasks.indexOf(t);
            this.tasks.splice(index, 1);
            try {
                await this.api('DELETE', `${urls.task}/${t.id}`);
                this.toast('Task deleted.', { action: { label: 'Undo', run: () => this.restore(t, index) } });
            } catch (e) { this.tasks.splice(index, 0, t); this.toast(e.message, { type: 'error' }); }
        },
        async restore(t, index) {
            try {
                const { task } = await this.api('POST', `${urls.task}/${t.id}/restore`);
                this.tasks.splice(Math.min(index, this.tasks.length), 0, task);
                await this.persistOrder(this.tasks.slice());   // put it back where it was
            } catch (e) { this.toast(e.message, { type: 'error' }); }
        },
        async clearDone() {
            const ids = this.done.map(t => t.id);
            if (!ids.length) return;
            const before = this.tasks.slice();
            this.tasks = this.tasks.filter(t => !ids.includes(t.id));
            const q = typeof this.filter === 'number' ? `?project_id=${this.filter}` : (this.filter === 'none' ? '?scope=none' : '');
            try { await this.api('DELETE', urls.clear + q); this.toast(`Cleared ${ids.length} completed.`); }
            catch (e) { this.tasks = before; this.toast(e.message, { type: 'error' }); }
        },

        // ───── drag to reorder ─────
        initSortable() {
            if (!window.Sortable || !this.$refs.list) return;
            this.sortable = Sortable.create(this.$refs.list, {
                handle: '[data-handle]', draggable: '[data-task]', animation: 180, ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen', forceFallback: false,
                onEnd: () => {
                    if (this.search) return;
                    const ids = [...this.$refs.list.querySelectorAll('[data-task]')].map(el => el.dataset.id);
                    this.applyOrder(ids.map(Number));
                },
            });
        },
        async applyOrder(visibleIds) {
            const before = this.tasks.slice();
            const set = new Set(visibleIds);
            const slots = [];
            before.forEach((t, i) => { if (set.has(t.id)) slots.push(i); });
            const byId = Object.fromEntries(before.map(t => [t.id, t]));
            const next = before.slice();
            slots.forEach((slot, k) => { next[slot] = byId[visibleIds[k]]; });
            if (next.every((t, i) => t === before[i])) return;
            this.tasks = next;
            try { await this.persistOrder(next); }
            catch (e) { this.tasks = before; this.toast("Couldn't save the new order. Put it back.", { type: 'error' }); }
        },
        // One global order per user: send every saved task id in its on-screen position.
        persistOrder(list) {
            return this.api('POST', urls.reorder, { task_ids: list.filter(t => !this.isTemp(t)).map(t => t.id) });
        },

        // ───── projects ─────
        async addProject() {
            const name = this.projectDraft.trim();
            if (!name) return;
            try {
                const { project } = await this.api('POST', urls.projects, { name });
                this.projects.push(project);
                this.projects.sort((a, b) => a.name.localeCompare(b.name));
                this.projectDraft = ''; this.addingProject = false;
                this.setFilter(project.id);
                this.$nextTick(() => this.$refs.newInput.focus());
            } catch (e) { this.toast(e.message, { type: 'error' }); }
        },
        async renameProject() {
            const name = this.renameDraft.trim();
            const p = this.projects.find(p => p.id === this.filter);
            this.renaming = false;
            if (!p || !name || name === p.name) return;
            const prev = p.name;
            p.name = name;
            try { await this.api('PATCH', `${urls.project}/${p.id}`, { name }); }
            catch (e) { p.name = prev; this.toast(e.message, { type: 'error' }); }
        },
        async deleteProject() {
            const id = this.filter;
            const beforeP = this.projects.slice(), beforeT = this.tasks.map(t => ({ t, project_id: t.project_id }));
            this.confirmDeleteProject = false;
            this.tasks.forEach(t => { if (t.project_id === id) t.project_id = null; });
            this.projects = this.projects.filter(p => p.id !== id);
            this.setFilter('all');
            try { await this.api('DELETE', `${urls.project}/${id}`); this.toast('Project deleted. Its tasks were kept.'); }
            catch (e) { this.projects = beforeP; beforeT.forEach(x => { x.t.project_id = x.project_id; }); this.toast(e.message, { type: 'error' }); }
        },
    };
}
</script>
@endpush
