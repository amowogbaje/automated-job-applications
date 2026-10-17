<?php

namespace App\Services\Agent\Outreach;

use App\Models\ActivityLog;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\OutreachDraft;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Signature;
use App\Models\User;
use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Providers\AllProvidersFailed;
use Illuminate\Support\Facades\DB;

/** Builds the grounded context, asks the AI (with provider failover) for three drafts, validates and saves them. */
class OutreachGenerator
{
    public function __construct(private AiManager $ai) {}

    /** Why drafting is not allowed right now, or null. */
    public static function blockReason(Opportunity $o, ?Contact $contact, bool $ignoreRestrictions = false): ?string
    {
        if ($o->company->do_not_contact) {
            return 'This company is marked "do not contact"'.($o->company->do_not_contact_reason ? ': '.$o->company->do_not_contact_reason : '').'.';
        }
        if ($contact) {
            if ($contact->status !== 'active') {
                return "This contact is marked {$contact->status}; pick another route.";
            }
            if (! $ignoreRestrictions && $contact->restrictions
                && preg_match('/no\s+(unsolicited|solicitation|cold|agencies|vendors)|do not (contact|solicit)|not (accept|want)\w*\s+unsolicited/i', $contact->restrictions)) {
                return 'This contact has a restriction that looks like "no unsolicited approaches": “'.$contact->restrictions.'”. Tick the override only if you are sure it does not apply to you.';
            }
        }

        return null;
    }

    /**
     * @param array{channel: string, contact_id?: ?int, service_id?: ?int, project_ids?: array, cv_version_id?: ?int, notes?: ?string, user_id?: ?int} $params
     */
    public function run(AgentRun $run, array $params): void
    {
        $o = Opportunity::with(['company.contacts', 'primaryService'])->findOrFail($run->opportunity_id);
        $run->update(['status' => 'running', 'started_at' => now()]);
        $started = microtime(true);
        $step = AgentStep::create(['agent_run_id' => $run->id, 'step_number' => 1, 'tool' => 'DraftOutreach', 'input' => ['channel' => $params['channel']]]);

        try {
            $result = $this->generate($o, $params);
            $step->update(['output' => count($result['drafts']).' drafts via '.$result['model'], 'duration_ms' => (int) ((microtime(true) - $started) * 1000)]);
            $run->update([
                'status' => 'completed', 'finished_at' => now(), 'steps_used' => 1, 'ai_model' => $result['model'],
                'result_summary' => 'Three '.OutreachChannels::spec($params['channel'])['label'].' drafts (version '.$result['version'].').',
            ]);
        } catch (\Throwable $e) {
            $step->update(['succeeded' => false, 'error' => mb_substr($e->getMessage(), 0, 1000), 'duration_ms' => (int) ((microtime(true) - $started) * 1000)]);
            $run->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
        }
    }

    /** @return array{drafts: OutreachDraft[], model: string, version: int} */
    public function generate(Opportunity $o, array $params): array
    {
        $channel = $params['channel'];
        $contact = ! empty($params['contact_id']) ? $o->company->contacts->firstWhere('id', (int) $params['contact_id']) : null;

        if ($why = self::blockReason($o, $contact, (bool) ($params['ignore_restrictions'] ?? false))) {
            throw new \RuntimeException($why);
        }

        $service = ! empty($params['service_id']) ? Service::find($params['service_id']) : $o->primaryService;
        $projects = $this->pickProjects($o, $service, (array) ($params['project_ids'] ?? []));
        $evidence = $o->evidence()->where('is_stale', false)->orderByRaw("case confidence when 'high' then 0 when 'moderate' then 1 else 2 end")->limit(12)->get();
        $signature = self::pickSignature($channel);
        $sender = ($params['user_id'] ?? null) ? (User::find($params['user_id'])?->name) : null;

        $ctx = [
            'channel' => $channel,
            'evidence_ids' => $evidence->pluck('id')->map(fn ($i) => (int) $i)->all(),
            'has_evidence' => $evidence->isNotEmpty(),
            'projects' => $projects->pluck('name', 'id')->all(),
            'allowed_hosts' => $this->allowedHosts($o, $projects),
            'allowed_contacts' => $this->allowedContacts($o),
        ];

        $messages = OutreachPrompt::build([
            'channel' => $channel,
            'sender' => $sender ?: 'the sender (a freelance software engineer)',
            'company' => [
                'name' => $o->company->name, 'website' => $o->company->website_url, 'industry' => $o->company->industry,
                'country' => $o->company->country, 'description' => $o->company->description ? mb_substr($o->company->description, 0, 600) : null,
            ],
            'opportunity' => ['title' => $o->title, 'type' => $o->type, 'business_need' => $o->business_need ? mb_substr($o->business_need, 0, 800) : null],
            'service' => $service ? [
                'name' => $service->name, 'description' => mb_substr((string) $service->description, 0, 500),
                'skills' => (array) $service->skills_required, 'engagement' => $service->preferred_engagement,
            ] : null,
            'projects' => $projects->map(fn (PortfolioProject $p) => [
                'id' => $p->id, 'name' => $p->name, 'summary' => mb_substr((string) $p->summary, 0, 300),
                'proof' => $p->proof_points ? mb_substr($p->proof_points, 0, 400) : null, 'technologies' => array_slice((array) $p->technologies, 0, 8),
            ])->all(),
            'evidence' => $evidence->map(fn ($e) => [
                'id' => $e->id, 'kind' => $e->kind, 'confidence' => $e->confidence,
                'finding' => mb_substr($e->finding, 0, 400), 'establishes' => $e->establishes ? mb_substr($e->establishes, 0, 300) : null,
                'inference' => $e->inference ? mb_substr($e->inference, 0, 250) : null,
            ])->all(),
            'contact' => $contact ? [
                'label' => (config('opportunity.contact_types')[$contact->type] ?? $contact->type).($contact->label ? ' — '.$contact->label : ''),
                'person_name' => $contact->person_name, 'person_role' => $contact->person_role, 'restrictions' => $contact->restrictions,
            ] : null,
            'notes' => self::cleanNotes($params['notes'] ?? null),
        ]);

        $checked = null;
        $response = null;
        $lastError = null;
        // A second try is only worthwhile when the failure was unusable output, not rate limits or outages.
        for ($try = 0; $try < 2; $try++) {
            try {
                $response = $this->ai->complete($messages, true, 1800, function (array $data) use ($ctx, &$checked) {
                    $checked = DraftValidator::check($data, $ctx);

                    return $checked['ok'] ? null : $checked['error'];
                });
                break;
            } catch (AllProvidersFailed $e) {
                $lastError = $e;
                $invalidOnly = $e->attempts && collect($e->attempts)->every(fn ($a) => $a['outcome'] === 'invalid');
                if (! $invalidOnly) {
                    break;
                }
            }
        }
        if (! $response) {
            throw $lastError ?? new \RuntimeException('No drafts were produced.');
        }

        return DB::transaction(function () use ($o, $channel, $contact, $service, $signature, $params, $checked, $response, $projects) {
            $contactId = $contact?->id;
            $version = 1 + (int) OutreachDraft::where('opportunity_id', $o->id)->where('channel', $channel)
                ->where('contact_id', $contactId)->max('version');

            // Unreviewed drafts from earlier rounds are retired; anything selected, edited or sent is kept.
            OutreachDraft::where('opportunity_id', $o->id)->where('channel', $channel)->where('contact_id', $contactId)
                ->where('status', 'draft')->update(['status' => 'discarded']);

            $saved = [];
            foreach (OutreachChannels::APPROACHES as $a) {
                $d = $checked['drafts'][$a];
                $saved[] = OutreachDraft::create([
                    'opportunity_id' => $o->id, 'contact_id' => $contactId, 'service_id' => $service?->id,
                    'signature_id' => $signature?->id, 'channel' => $channel, 'approach' => $a,
                    'subject' => $d['subject'], 'alt_subjects' => $d['alt_subjects'] ?: null,
                    'body' => $d['body'], 'original_body' => $d['body'],
                    'evidence_ids' => $d['evidence_ids'] ?: null, 'claims' => $d['claims'] ?: null, 'warnings' => $d['warnings'] ?: null,
                    'portfolio_project_id' => $d['project_id'], 'cv_version_id' => $params['cv_version_id'] ?? $o->recommended_cv_version_id,
                    'version' => $version, 'status' => 'draft', 'is_selected' => false,
                    'ai_model' => $response->provider.'/'.$response->model,
                ]);
            }

            if (in_array($o->status, ['discovered', 'qualified'], true)) {
                $o->update(['status' => 'drafted']);
            }
            ActivityLog::record($o, 'outreach_drafted', 'Three '.OutreachChannels::spec($channel)['label']." drafts generated (v{$version}).", ['model' => $response->provider]);

            return ['drafts' => $saved, 'model' => $response->provider.'/'.$response->model, 'version' => $version];
        });
    }

    /** Explicit choice, else projects already linked to this opportunity, else the service's best projects. */
    private function pickProjects(Opportunity $o, ?Service $service, array $ids)
    {
        if ($ids) {
            return PortfolioProject::whereIn('id', array_slice(array_map('intval', $ids), 0, 3))->get();
        }
        $linked = $o->portfolioProjects()->orderBy('opportunity_portfolio_project.rank')->limit(3)->get();
        if ($linked->isNotEmpty()) {
            return $linked;
        }

        return $service ? $service->portfolioProjects()->orderByDesc('service_portfolio_project.relevance')->limit(3)->get() : collect();
    }

    public static function pickSignature(string $channel): ?Signature
    {
        $want = OutreachChannels::spec($channel)['signature'];
        if ($want === null) {
            return null;
        }

        return Signature::whereIn('channel', array_unique([$want, 'any']))->orderByDesc('is_default')->orderBy('id')->first()
            ?? ($want !== 'email' ? Signature::where('channel', 'email')->orderByDesc('is_default')->first() : null);
    }

    private function allowedHosts(Opportunity $o, $projects): array
    {
        $urls = array_filter([$o->company->website_url, $o->company->domain]);
        foreach ($projects as $p) {
            $urls[] = $p->url;
        }
        foreach (Signature::pluck('body') as $b) {
            preg_match_all('~(?:https?://|www\.)[^\s<>"\')\]]+~i', (string) $b, $m);
            array_push($urls, ...$m[0]);
        }
        $hosts = [];
        foreach ($urls as $u) {
            if (! $u) {
                continue;
            }
            $h = parse_url(preg_match('~^[a-z]+://~i', $u) ? $u : 'https://'.$u, PHP_URL_HOST);
            if ($h) {
                $hosts[] = preg_replace('/^www\./', '', strtolower($h));
            }
        }

        return array_values(array_unique($hosts));
    }

    private function allowedContacts(Opportunity $o): array
    {
        $vals = [];
        foreach ($o->company->contacts as $c) {
            $vals[] = strtolower($c->value);
        }
        foreach (Signature::pluck('body') as $b) {
            preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $b, $e);
            preg_match_all('/\+?\d[\d\s().\-]{6,}\d/', (string) $b, $p);
            array_push($vals, ...array_map('strtolower', $e[0]), ...$p[0]);
        }

        return array_values(array_unique($vals));
    }

    /** User notes are style guidance only: cap length and drop anything that looks like an instruction to the model. */
    public static function cleanNotes(?string $notes): ?string
    {
        $notes = trim(strip_tags((string) $notes));
        if ($notes === '') {
            return null;
        }
        $notes = preg_replace('/(<<<|>>>)/', '', $notes);

        return mb_substr($notes, 0, 500);
    }
}
