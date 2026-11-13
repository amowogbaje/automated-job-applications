<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\SharedDocument;
use App\Models\Setting;
use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Leads\LeadCsv;
use App\Services\Agent\Leads\LeadImporter;
use App\Services\Agent\Leads\LeadNormalizer;
use App\Services\Agent\Leads\LeadSequenceRunner;
use App\Services\Agent\Leads\Phone;
use App\Services\Agent\Leads\SequenceStarter;
use App\Services\Agent\Leads\Templates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $leads = Opportunity::query()->where('source', 'uploaded')->with(['company.contacts', 'sequence'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('batch'), fn ($q) => $q->where('lead_batch', $request->input('batch')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('company', fn ($c) => $c->where('name', 'like', '%'.$request->input('q').'%')))
            ->latest()->paginate(25)->withQueryString();

        return view('agent.leads.index', [
            'leads' => $leads,
            'batches' => Opportunity::where('source', 'uploaded')->whereNotNull('lead_batch')->distinct()->orderByDesc('lead_batch')->pluck('lead_batch'),
            'autoOn' => LeadSequenceRunner::autoOn(),
            'dailyCap' => (int) Setting::get('leads.daily_cap', config('leads.daily_cap')),
            'emailOwner' => (bool) Setting::get('leads.email_owner', true),
            'replyTo' => Setting::get('leads.reply_to'),
            'sendNowEnabled' => \App\Services\Agent\Mail\SendNow::enabled(),
            'smtpUserEmail' => \App\Services\Agent\Mail\SendNow::smtpUserEmail(),
            'google' => $request->user()->googleAccount,
            'googleConfigured' => \App\Services\Agent\Mail\GoogleMail::configured(),
            'templates' => Templates::all(),
            'counts' => Opportunity::where('source', 'uploaded')->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'daily_cap' => ['required', 'integer', 'min:1', 'max:100'],
            'reply_to' => ['nullable', 'email'],
            'smtp_user_email' => ['nullable', 'email'],
            'templates' => ['array'],
            'templates.*.subject' => ['nullable', 'string', 'max:200'],
            'templates.*.body' => ['nullable', 'string', 'max:3000'],
        ]);
        Setting::put('leads.auto_send', $request->boolean('auto_send'));
        Setting::put('leads.email_owner', $request->boolean('email_owner'));
        Setting::put('leads.daily_cap', (int) $data['daily_cap']);
        Setting::put('leads.reply_to', $data['reply_to'] ?? null);
        Setting::put('leads.send_now_enabled', $request->boolean('send_now_enabled'));
        Setting::put('leads.smtp_user_email', strtolower($data['smtp_user_email'] ?? '') ?: null);
        Setting::put('leads.templates', collect($data['templates'] ?? [])->only([1, 2, 3])->all());

        return back()->with('status', $request->boolean('auto_send')
            ? 'Saved. Automatic follow-up emails are ON (within your daily limit and send hours).'
            : 'Saved. Automatic follow-up emails are OFF; you will be notified when an email is due.');
    }

    public function import()
    {
        return view('agent.leads.import', ['aiReady' => (bool) app(AiManager::class)->activeNames(), 'fields' => LeadCsv::FIELDS]);
    }

    public function template(): Response
    {
        return response(LeadCsv::template(), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="leads-template.csv"']);
    }

    /** Parse a CSV, or AI-organise raw text, then show an editable preview. Nothing is saved yet. */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:2048', 'mimes:csv,txt'],
            'raw' => ['nullable', 'string', 'max:60000'],
        ]);
        $text = $request->file('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : trim((string) $request->input('raw'));
        if ($text === '') {
            return back()->withErrors(['raw' => 'Upload a CSV file or paste some text.']);
        }

        $note = null;
        $rows = LeadCsv::parse($text);
        if ($rows) {
            $note = 'Read '.count($rows).' rows from your CSV.';
        } else {
            try {
                $r = app(LeadNormalizer::class)->normalize($text);
                $rows = $r['rows'];
                $note = 'AI ('.$r['source'].') organised your text into '.count($rows).' leads. Check them before saving; it never invents emails or phone numbers.';
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors(['raw' => 'This is not in the CSV format and the AI could not organise it: '.mb_substr($e->getMessage(), 0, 200)]);
            }
        }
        if (! $rows) {
            return back()->withInput()->withErrors(['raw' => 'No leads found. Use the template (company column is required).']);
        }
        $max = (int) config('leads.max_import_rows', 200);
        if (count($rows) > $max) {
            $note .= " Only the first {$max} are shown.";
            $rows = array_slice($rows, 0, $max);
        }

        return view('agent.leads.preview', [
            'rows' => $rows, 'note' => $note, 'fields' => LeadCsv::FIELDS,
            'problems' => array_map(fn ($r) => LeadImporter::problems($r), $rows),
            'batch' => 'Upload '.now()->format('j M Y H:i'),
        ]);
    }

    public function store(Request $request, LeadImporter $importer): RedirectResponse
    {
        // The preview page only exists as a POST result, so a failed save must not go "back" to it (that was the
        // "GET method is not supported for agent/leads/preview" error). Show the reason on the upload page instead.
        if (! is_array($request->input('rows')) || ! $request->input('rows')) {
            return redirect()->route('agent.leads.import')->withErrors(['rows' => 'The list of leads did not arrive. If you uploaded a long list, your host may be cutting off big forms; try about 50 leads at a time.']);
        }
        try {
            $this->validateRows($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('agent.leads.import')->withErrors($e->errors());
        }

        return $this->saveRows($request, $importer);
    }

    private function validateRows(Request $request): void
    {
        $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:'.config('leads.max_import_rows', 200)],
            'rows.*.company' => ['nullable', 'string', 'max:255'],
            'rows.*.contact_name' => ['nullable', 'string', 'max:255'],
            'rows.*.email' => ['nullable', 'string', 'max:255'],
            'rows.*.phone' => ['nullable', 'string', 'max:40'],
            'rows.*.website' => ['nullable', 'string', 'max:500'],
            'rows.*.service' => ['nullable', 'string', 'max:255'],
            'rows.*.city' => ['nullable', 'string', 'max:120'],
            'rows.*.country' => ['nullable', 'string', 'max:60'],
            'rows.*.industry' => ['nullable', 'string', 'max:255'],
            'rows.*.notes' => ['nullable', 'string', 'max:3000'],
            'rows.*.source' => ['nullable', 'string', 'max:255'],
            'batch' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function saveRows(Request $request, LeadImporter $importer): RedirectResponse
    {
        $keep = array_keys((array) $request->input('keep', []));
        $rows = collect($request->input('rows'))->filter(fn ($r, $i) => in_array((string) $i, array_map('strval', $keep), true))
            ->map(fn ($r) => array_merge(array_fill_keys(LeadCsv::FIELDS, ''), array_map(fn ($v) => (string) $v, $r)))->values()->all();
        if (! $rows) {
            return redirect()->route('agent.leads.import')->withErrors(['rows' => 'Nothing was ticked, so nothing was saved. Preview again and tick at least one lead.']);
        }

        $res = $importer->import($rows, [
            'batch' => $request->input('batch'),
            'start_sequence' => $request->boolean('start_sequence'),
            'auto_send' => $request->boolean('auto_send', true),
        ]);

        $msg = "Saved {$res['created']} lead(s)".($res['existing'] ? ", {$res['existing']} already in your pipeline" : '').($res['skipped'] ? ", {$res['skipped']} skipped" : '').'.';
        if ($res['no_contact']) {
            $msg .= " {$res['no_contact']} have no email or phone, so they won't be followed up.";
        }

        return redirect()->route('agent.leads.index')->with('status', $msg)->with('import_messages', array_slice($res['messages'], 0, 20));
    }

    // ---- per-lead sequence controls ----

    public function startSequence(Request $request, Opportunity $opportunity, SequenceStarter $starter): RedirectResponse
    {
        $seq = $starter->start($opportunity, null, $request->boolean('auto_send', true));

        return back()->with('status', $seq ? 'Follow-up sequence started.' : 'Add an email or phone number first.');
    }

    public function sent(Request $request, Opportunity $opportunity, LeadSequenceRunner $runner): RedirectResponse
    {
        abort_unless($opportunity->sequence, 404);
        $runner->markSent($opportunity->sequence, (string) $request->input('channel', 'whatsapp'));

        return back()->with('status', 'Logged. The next step is scheduled.');
    }

    public function replied(Opportunity $opportunity, LeadSequenceRunner $runner): RedirectResponse
    {
        abort_unless($opportunity->sequence, 404);
        $runner->markReplied($opportunity->sequence);

        return back()->with('status', 'Marked as replied. Follow-ups stopped.');
    }

    public function pause(Opportunity $opportunity): RedirectResponse
    {
        $opportunity->sequence?->update(['state' => 'paused', 'stop_reason' => 'Paused by you']);

        return back()->with('status', 'Follow-ups paused.');
    }

    public function resume(Opportunity $opportunity): RedirectResponse
    {
        $opportunity->sequence?->update(['state' => 'active', 'stop_reason' => null, 'next_action_at' => now()]);

        return back()->with('status', 'Follow-ups resumed.');
    }

    public function stop(Opportunity $opportunity): RedirectResponse
    {
        $opportunity->sequence?->update(['state' => 'stopped', 'stop_reason' => 'Stopped by you']);

        return back()->with('status', 'Follow-ups stopped.');
    }

    public function convert(Opportunity $opportunity): RedirectResponse
    {
        $opportunity->update(['status' => 'won']);
        $opportunity->company->update(['is_customer' => true, 'customer_since' => $opportunity->company->customer_since ?? now()]);
        $opportunity->sequence?->update(['state' => 'done', 'stop_reason' => 'Became a customer']);
        \App\Models\ActivityLog::record($opportunity, 'converted', 'Converted to customer.');

        return redirect()->route('agent.customers.show', $opportunity->company)->with('status', 'Converted to a customer. Record transactions below.');
    }
}
