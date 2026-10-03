// Run with:  node tests/js/taskboard.test.js
// Pulls the taskBoard() component straight out of the Blade view and exercises it against a fake server,
// so the optimistic updates / rollbacks / reorder / undo logic is tested without a browser.
const fs = require('fs');
const path = require('path');
const view = fs.readFileSync(path.join(__dirname, '../../resources/views/tasks/index.blade.php'), 'utf8');
const js = view.match(/<script>\n(function taskBoard[\s\S]*?)<\/script>/)[1];
eval(js + '\nglobalThis.taskBoard = taskBoard;');

let pass = 0, fail = 0;
const ok = (c, m) => { if (c) pass++; else { fail++; console.log('FAIL:', m); } };
const eq = (a, b, m) => ok(JSON.stringify(a) === JSON.stringify(b), `${m}: expected ${JSON.stringify(b)} got ${JSON.stringify(a)}`);

// ---- environment stubs
globalThis.document = { querySelector: () => ({ content: 'tok' }) };
const mem = {}; globalThis.localStorage = { getItem: k => mem[k] ?? null, setItem: (k, v) => { mem[k] = v; } };
globalThis.location = { href: 'http://x/tasks' }; globalThis.history = { replaceState() {} };
globalThis.setInterval = () => 0; globalThis.window = globalThis;

// ---- fake server mirroring TaskController
let nextId = 100, calls = [], failNext = null;
const server = { tasks: {} };
const mk = (o) => ({ completed: false, completed_at: null, created_at: 'x', updated_at: new Date().toISOString(), project_id: null, ...o });
globalThis.fetch = async (url, opts) => {
    const method = opts.method, body = opts.body ? JSON.parse(opts.body) : {};
    calls.push({ method, url, body });
    if (failNext) { const f = failNext; failNext = null; return { ok: false, status: f, json: async () => ({ message: 'boom' }) }; }
    const json = (data, status = 200) => ({ ok: true, status, json: async () => data });
    if (method === 'POST' && url === '/tasks') return json({ task: mk({ id: nextId++, name: body.name, project_id: body.project_id ?? null }) }, 201);
    if (method === 'PATCH' && /^\/tasks\/\d+$/.test(url)) {
        const id = +url.split('/').pop(); const t = mk({ id, ...body });
        if ('completed' in body) { t.completed_at = body.completed ? new Date().toISOString() : null; }
        return json({ task: t });
    }
    if (method === 'DELETE' && /^\/tasks\/\d+$/.test(url)) return json({ id: +url.split('/').pop() });
    if (method === 'POST' && /\/restore$/.test(url)) return json({ task: mk({ id: +url.split('/')[2], name: 'restored' }) });
    if (method === 'POST' && url === '/tasks/reorder') return json({ status: 'ok' });
    if (method === 'DELETE' && url.startsWith('/tasks/completed')) return json({ ids: [] });
    if (method === 'POST' && url === '/projects') return json({ project: { id: nextId++, name: body.name } }, 201);
    if (method === 'PATCH' && url.startsWith('/projects/')) return json({ project: { id: +url.split('/').pop(), name: body.name } });
    if (method === 'DELETE' && url.startsWith('/projects/')) return json({ id: +url.split('/').pop() });
    return { ok: false, status: 404, json: async () => ({}) };
};

const urls = { store: '/tasks', reorder: '/tasks/reorder', clear: '/tasks/completed', task: '/tasks', projects: '/projects', project: '/projects' };
function board(tasks, projects = [{ id: 1, name: 'Alpha' }, { id: 2, name: 'Beta' }], selected = null) {
    const b = taskBoard({ tasks: tasks.map(t => mk(t)), projects, selected }, urls);
    b.$refs = { newInput: { focus() {} }, search: { focus() {} }, list: null, projectInput: { focus() {} }, renameInput: { focus() {} } };
    b.$nextTick = f => f(); b.$watch = () => {};
    b.boot(); calls = [];
    return b;
}
const sleep = ms => new Promise(r => setTimeout(r, ms));

(async () => {
    // 1) derived state
    let b = board([{ id: 1, name: 'a', project_id: 1 }, { id: 2, name: 'b', project_id: 2 }, { id: 3, name: 'c', project_id: 1, completed: true, completed_at: '2026-01-02T00:00:00Z' }, { id: 4, name: 'd' }]);
    eq(b.active.map(t => t.id), [1, 2, 4], 'active ids (all)');
    eq(b.done.map(t => t.id), [3], 'done ids');
    eq(b.progress, { done: 1, total: 4, pct: 25 }, 'progress');
    eq([b.openCount('all'), b.openCount(1), b.openCount('none')], [3, 1, 1], 'chip counts');
    ok(b.hasUnassigned, 'has unassigned');
    b.filter = 1; eq(b.active.map(t => t.id), [1], 'project filter');
    eq(b.progress, { done: 1, total: 2, pct: 50 }, 'progress follows filter');
    b.filter = 'all'; b.search = 'BE'; eq(b.active.map(t => t.id), [2], 'search matches project name');
    b.search = ''; 
    eq(b.summary, '3 to do · 1 done', 'summary');

    // 2) optimistic add, then server id swaps in
    b = board([{ id: 1, name: 'a' }]);
    b.newName = '  New thing '; b.newProject = 2;
    const p = b.addTask();
    ok(b.tasks.length === 2 && b.isTemp(b.tasks[1]) && b.tasks[1].name === 'New thing' && b.newName === '', 'appears instantly as temp');
    await p;
    ok(!b.isTemp(b.tasks[1]) && b.tasks[1].id === 100 && b.tasks[1].project_id === 2, 'temp replaced by server task');
    eq(calls[0].body, { name: 'New thing', project_id: 2 }, 'posted payload');

    // 3) add failure rolls back
    b = board([{ id: 1, name: 'a' }]); b.newName = 'x'; failNext = 500;
    await b.addTask();
    ok(b.tasks.length === 1 && b.newName === 'x' && b.toasts.some(t => t.type === 'error'), 'failed add rolled back and restores text');

    // 4) toggle + rollback
    b = board([{ id: 1, name: 'a' }]);
    await b.toggle(b.tasks[0]);
    ok(b.tasks[0].completed && b.done.length === 1 && b.active.length === 0, 'completed moves to done');
    ok(b.toasts.some(t => t.action && t.action.label === 'Undo'), 'undo toast offered');
    failNext = 500; await b.toggle(b.tasks[0]);
    ok(b.tasks[0].completed === true, 'failed un-complete rolls back');

    // 5) reorder inside a filtered view keeps other projects' slots
    b = board([{ id: 1, name: 'a1', project_id: 1 }, { id: 2, name: 'b1', project_id: 2 }, { id: 3, name: 'a2', project_id: 1 }, { id: 4, name: 'b2', project_id: 2 }]);
    b.filter = 1;
    await b.applyOrder([3, 1]);
    eq(b.tasks.map(t => t.id), [3, 2, 1, 4], 'only project-1 slots reordered');
    eq(calls[0].body.task_ids, [3, 2, 1, 4], 'full global order sent');
    // no-op reorder sends nothing
    calls = []; await b.applyOrder([3, 1]); eq(calls.length, 0, 'no-op reorder makes no request');
    // failure reverts
    failNext = 500; await b.applyOrder([1, 3]);
    eq(b.tasks.map(t => t.id), [3, 2, 1, 4], 'failed reorder reverts');

    // 6) move to project goes to the bottom, rolls back on error
    b = board([{ id: 1, name: 'a' }, { id: 2, name: 'b' }, { id: 3, name: 'c' }]);
    await b.move(b.tasks[0], 2);
    eq(b.tasks.map(t => t.id), [2, 3, 1], 'moved task is last'); eq(b.tasks[2].project_id, 2, 'project set');
    failNext = 500; await b.move(b.tasks[0], 1);
    eq(b.tasks.map(t => t.id), [2, 3, 1], 'order restored after failed move'); 

    // 7) delete + undo puts it back where it was and re-saves order
    b = board([{ id: 1, name: 'a' }, { id: 2, name: 'b' }, { id: 3, name: 'c' }]);
    const victim = b.tasks[1];
    await b.remove(victim);
    eq(b.tasks.map(t => t.id), [1, 3], 'deleted instantly');
    const undo = b.toasts.find(t => t.action).action;
    calls = []; await undo.run(); await sleep(5);
    eq(b.tasks.map(t => t.id), [1, 2, 3], 'undo restores original position');
    ok(calls.some(c => c.url === '/tasks/reorder'), 'undo re-saves order');
    failNext = 500; const t3 = b.tasks[2]; await b.remove(t3);
    eq(b.tasks.map(t => t.id), [1, 2, 3], 'failed delete puts the task back');

    // 8) inline edit
    b = board([{ id: 1, name: 'old' }]);
    b.startEdit(b.tasks[0]); b.draft = '  newer  '; await b.saveEdit(b.tasks[0]);
    eq(b.tasks[0].name, 'newer', 'edit saved'); 
    b.startEdit(b.tasks[0]); b.draft = ''; await b.saveEdit(b.tasks[0]); eq(b.tasks[0].name, 'newer', 'empty edit ignored');

    // 9) projects
    b = board([{ id: 1, name: 'a', project_id: 1 }, { id: 2, name: 'b', project_id: 2 }]);
    b.projectDraft = 'Zed'; await b.addProject();
    ok(b.projects.some(p => p.name === 'Zed') && typeof b.filter === 'number', 'project created and selected');
    b.filter = 1; await b.deleteProject();
    ok(b.tasks[0].project_id === null && !b.projects.some(p => p.id === 1) && b.filter === 'all', 'delete project unassigns its tasks');
    b.filter = 2; b.renameDraft = 'Bee'; await b.renameProject(); eq(b.projectName(2), 'Bee', 'rename');

    // 10) relative time
    ok(b.ago(new Date(Date.now() - 5 * 60000).toISOString()).length > 0 && b.ago(null) === '', 'ago');
    eq(b.ago(new Date().toISOString()), 'just now', 'just now');

    // 11) pending (temp) tasks can't be completed or deleted
    b = board([]); b.tasks.push({ id: 'tmp-9', name: 't', completed: false }); calls = [];
    await b.toggle(b.tasks[0]); await b.remove(b.tasks[0]);
    eq(calls.length, 0, 'no requests for unsaved tasks');

    console.log(`pass=${pass} fail=${fail}`);
    process.exit(fail ? 1 : 0);
})();
