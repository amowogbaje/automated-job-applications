@extends('agent.layout')
@section('agent_title', 'Check leads')
@section('container', 'max-w-7xl')
@section('agent')
<div class="mb-4"><a href="{{ route('agent.leads.import') }}" class="text-sm text-slate-500 hover:underline">← Back</a>
    <h1 class="text-2xl font-semibold">Check your leads</h1>
    <p class="text-sm text-slate-500">{{ $note }} Edit anything, untick what you don't want, then save. Nothing is sent by saving.</p></div>

<form method="POST" action="{{ route('agent.leads.store') }}" class="space-y-4" id="lead-form">
    @csrf
    <input type="hidden" name="rows_json" id="rows_json">
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500"><tr><th class="p-2">Keep</th>@foreach ($fields as $f)<th class="p-2">{{ str_replace('_', ' ', $f) }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($rows as $i => $r)
                <tr class="border-t border-slate-100 align-top {{ $problems[$i] ? 'bg-amber-50' : '' }}">
                    <td class="p-2"><input type="checkbox" data-keep="{{ $i }}" @checked(! in_array('No company name', $problems[$i], true))></td>
                    @foreach ($fields as $f)
                        <td class="p-1"><input data-row="{{ $i }}" data-field="{{ $f }}" value="{{ $r[$f] }}" class="input min-w-[9rem] py-1 text-xs"></td>
                    @endforeach
                </tr>
                @if ($problems[$i])<tr class="bg-amber-50"><td></td><td colspan="{{ count($fields) }}" class="px-2 pb-2 text-xs text-amber-700">Row {{ $i + 1 }}: {{ implode('; ', $problems[$i]) }}</td></tr>@endif
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card space-y-3 p-5">
        <div><label class="label">Upload name (to find this group later)</label><input name="batch" value="{{ $batch }}" class="input"></div>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="start_sequence" value="1" checked class="mt-1"><span>Start follow-up for these leads: emails for leads that have an email, phone/WhatsApp reminders for the rest.</span></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="auto_send" value="1" checked class="mt-1"><span>Let the emails go out automatically (only if you switched automatic sending on in Leads → Follow-up settings; otherwise you are notified).</span></label>
        <button class="btn-primary">Save leads</button>
    </div>
</form>
<script>
// Rows are sent as ONE field: many hosts silently drop form fields beyond 1000 (max_input_vars), which would lose leads.
document.getElementById('lead-form').addEventListener('submit', function () {
    var rows = [];
    document.querySelectorAll('[data-keep]').forEach(function (box) {
        if (!box.checked) return;
        var i = box.getAttribute('data-keep'), row = {};
        document.querySelectorAll('[data-row="' + i + '"]').forEach(function (inp) { row[inp.getAttribute('data-field')] = inp.value; });
        rows.push(row);
    });
    document.getElementById('rows_json').value = JSON.stringify(rows);
});
</script>
@endsection
