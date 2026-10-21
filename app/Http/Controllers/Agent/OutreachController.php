<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateOutreachJob;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\CvVersion;
use App\Models\Opportunity;
use App\Models\OutreachDraft;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Signature;
use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Outreach\DraftValidator;
use App\Services\Agent\Outreach\OutreachChannels;
use App\Services\Agent\Outreach\OutreachGenerator;
use App\Services\Agent\Research\ContactRouting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Outreach Studio. Nothing here sends anything: drafts are copied and sent by hand, then logged. */
class OutreachController extends Controller
{
    public function show(Request $request, Opportunity $opportunity)
    {
        $opportunity->load(['company.contacts', 'primaryService', 'portfolioProjects', 'recommendedCv', 'evidence']);
        $company = $opportunity->company;

        $contacts = $company->contacts->where('status', 'active')->values();
        $rec = ContactRouting::recommend(
            $contacts->map->only(['type'])->all(),
            $opportunity->primaryService?->preferred_engagement,
            $opportunity->type
        );
        $channel = $request->query('channel');
        if (! OutreachChannels::exists((string) $channel)) {
            $channel = $rec['channel'] ?? 'email';
        }

        $drafts = $opportunity->outreachDrafts()->where('status', '!=', 'discarded')
            ->with(['contact', 'signature', 'portfolioProject', 'cvVersion'])
            ->orderByDesc('version')->orderBy('id')->get();

        $channels = [];
        foreach (OutreachChannels::keys() as $key) {
            $spec = OutreachChannels::spec($key);
            $channels[$key] = $spec + [
                'key' => $key,
                'contacts' => $contacts->filter(fn (Contact $c) => OutreachChannels::forContactType($c->type) === $key)->values(),
                'drafts' => $drafts->where('channel', $key)->values(),
            ];
        }

        $service = $opportunity->primaryService;
        $defaultProjects = $opportunity->portfolioProjects->pluck('id')->all()
            ?: ($service ? $service->portfolioProjects()->orderByDesc('service_portfolio_project.relevance')->limit(3)->pluck('portfolio_projects.id')->all() : []);

        return view('agent.outreach.show', [
            'opportunity' => $opportunity,
            'company' => $company,
            'channels' => $channels,
            'channel' => $channel,
            'recommendation' => $rec,
            'evidenceById' => $opportunity->evidence->keyBy('id'),
            'services' => Service::active()->get(),
            'projects' => PortfolioProject::orderBy('name')->get(),
            'defaultProjects' => $defaultProjects,
            'cvs' => CvVersion::where(fn ($q) => $q->whereNull('opportunity_id')->orWhere('opportunity_id', $opportunity->id))->orderByDesc('is_default')->orderBy('name')->get(),
            'defaultCvId' => $opportunity->recommended_cv_version_id ?? CvVersion::where('is_default', true)->value('id'),
            'signatures' => Signature::orderBy('channel')->orderBy('name')->get(),
            'aiReady' => (bool) app(AiManager::class)->activeNames(),
            'limits' => collect(OutreachChannels::keys())->mapWithKeys(fn ($k) => [$k => OutreachChannels::spec($k)['max_words']])->all(),
        ]);
    }

    public function generate(Request $request, Opportunity $opportunity, AiManager $ai): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(OutreachChannels::keys())],
            'contact_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'project_ids' => ['nullable', 'array', 'max:3'],
            'project_ids.*' => ['integer', 'exists:portfolio_projects,id'],
            'cv_version_id' => ['nullable', 'integer', 'exists:cv_versions,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'ignore_restrictions' => ['nullable', 'boolean'],
        ]);

        $contact = null;
        if (! empty($data['contact_id'])) {
            $contact = Contact::where('company_id', $opportunity->company_id)->find($data['contact_id']);
            if (! $contact) {
                return response()->json(['message' => 'That contact does not belong to this company.'], 422);
            }
        }
        $opportunity->loadMissing('company');
        if ($why = OutreachGenerator::blockReason($opportunity, $contact, (bool) ($data['ignore_restrictions'] ?? false))) {
            return response()->json(['message' => $why], 422);
        }
        if (! $ai->activeNames()) {
            return response()->json(['message' => 'No AI provider is enabled with an API key. Open Providers to add one.'], 422);
        }
        $busy = $opportunity->agentRuns()->where('kind', 'outreach')->whereIn('status', ['pending', 'running'])
            ->where('created_at', '>', now()->subMinutes(5))->exists();
        if ($busy) {
            return response()->json(['message' => 'Drafts are already being written for this opportunity.'], 409);
        }

        $run = $opportunity->agentRuns()->create([
            'company_id' => $opportunity->company_id, 'kind' => 'outreach', 'status' => 'pending', 'step_limit' => 1,
            'objective' => 'Draft '.$data['channel'].' outreach for '.$opportunity->company->name,
        ]);
        $params = $data + ['user_id' => $request->user()->id];

        if (config('research.mode') === 'sync') {
            GenerateOutreachJob::dispatchSync($run->id, $params);
        } else {
            GenerateOutreachJob::dispatch($run->id, $params);
        }

        return response()->json(['run' => $run->fresh()->load('steps')->payload()], 202);
    }

    /** Autosave while editing. Editing a sent draft is blocked so the log stays truthful. */
    public function update(Request $request, OutreachDraft $draft): JsonResponse
    {
        if ($draft->status === 'sent') {
            return response()->json(['message' => 'This draft was already sent; it can no longer be edited.'], 409);
        }
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:6000'],
            'signature_id' => ['nullable', 'integer', 'exists:signatures,id'],
        ]);

        $changed = trim($data['body']) !== trim($draft->body) || ($data['subject'] ?? null) !== $draft->subject;
        $draft->fill([
            'subject' => OutreachChannels::spec($draft->channel)['subject'] ? ($data['subject'] ?? $draft->subject) : null,
            'body' => trim($data['body']),
            'signature_id' => $data['signature_id'] ?? null,
        ]);
        if ($changed) {
            $draft->edited_at = now();
            if ($draft->status === 'draft') {
                $draft->status = 'edited';
            }
        }
        $draft->save();

        return response()->json($this->payload($draft->load('signature')));
    }

    public function reset(OutreachDraft $draft): JsonResponse
    {
        if ($draft->status === 'sent' || ! $draft->original_body) {
            return response()->json(['message' => 'Nothing to reset.'], 409);
        }
        $draft->update(['body' => $draft->original_body, 'edited_at' => null, 'status' => $draft->is_selected ? 'selected' : 'draft']);

        return response()->json($this->payload($draft->load('signature')));
    }

    public function select(OutreachDraft $draft): JsonResponse
    {
        if (in_array($draft->status, ['sent', 'discarded'], true)) {
            return response()->json(['message' => 'This draft can no longer be selected.'], 409);
        }
        OutreachDraft::where('opportunity_id', $draft->opportunity_id)->where('channel', $draft->channel)
            ->where('contact_id', $draft->contact_id)->where('is_selected', true)->where('status', '!=', 'sent')
            ->update(['is_selected' => false]);
        $draft->update(['is_selected' => true, 'status' => $draft->edited_at ? 'edited' : 'selected']);

        $o = $draft->opportunity;
        if (in_array($o->status, ['discovered', 'qualified', 'drafted'], true)) {
            $o->update(['status' => 'approved']);
        }
        ActivityLog::record($o, 'outreach_selected', 'Selected the '.(OutreachChannels::APPROACH_LABELS[$draft->approach] ?? $draft->approach).' '.$draft->channel.' draft.');

        return response()->json($this->payload($draft->load('signature')));
    }

    public function copied(OutreachDraft $draft): JsonResponse
    {
        $draft->update(['copied_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function discard(OutreachDraft $draft): JsonResponse
    {
        if ($draft->status === 'sent') {
            return response()->json(['message' => 'A sent draft cannot be discarded.'], 409);
        }
        $draft->update(['status' => 'discarded', 'is_selected' => false]);

        return response()->json(['ok' => true]);
    }

    /** You sent it yourself: log it, move the pipeline on, and (optionally) schedule a follow-up. */
    public function sent(Request $request, OutreachDraft $draft): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'follow_up_days' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);
        if ($draft->status === 'sent') {
            return response()->json(['message' => 'Already marked as sent.'], 409);
        }

        $o = $draft->opportunity;
        $now = now();
        $draft->update(['status' => 'sent', 'is_selected' => true, 'sent_at' => $now]);
        $o->outreachEvents()->create([
            'outreach_draft_id' => $draft->id, 'contact_id' => $draft->contact_id, 'event_type' => 'sent',
            'channel' => $draft->channel, 'notes' => $data['notes'] ?? null, 'occurred_at' => $now,
        ]);
        if (in_array($o->status, ['discovered', 'researching', 'qualified', 'drafted', 'approved'], true)) {
            $o->update(['status' => 'contacted', 'contacted_at' => $o->contacted_at ?? $now]);
        }

        $days = $data['follow_up_days'] ?? 5;
        if ($days > 0) {
            $o->followUps()->create([
                'title' => 'Follow up: '.(config('opportunity.channels')[$draft->channel] ?? $draft->channel).' to '.$o->company->name,
                'notes' => 'Sent '.$now->format('M j').'. If there is still no reply, send one short, polite follow-up or close it.',
                'due_at' => $now->copy()->addDays($days), 'is_auto' => true,
            ]);
        }
        ActivityLog::record($o, 'outreach_sent', 'Marked a '.$draft->channel.' message as sent.');

        return response()->json($this->payload($draft->load('signature')) + ['follow_up_days' => $days]);
    }

    private function payload(OutreachDraft $d): array
    {
        return [
            'id' => $d->id, 'status' => $d->status, 'is_selected' => $d->is_selected, 'subject' => $d->subject, 'body' => $d->body,
            'words' => DraftValidator::wordCount($d->body), 'edited' => (bool) $d->edited_at, 'full' => $d->fullText(),
            'signature_id' => $d->signature_id, 'sent_at' => $d->sent_at?->toIso8601String(),
        ];
    }
}
