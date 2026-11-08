<?php

namespace App\Services\Agent\Leads;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Service;

/** Saves uploaded leads as opportunities (type "lead", source "uploaded"). Nothing is sent here. */
class LeadImporter
{
    public function __construct(private SequenceStarter $starter) {}

    /** Per-row problems, used by the preview. @return string[] */
    public static function problems(array $r): array
    {
        $p = [];
        if (trim($r['company'] ?? '') === '') {
            $p[] = 'No company name';
        }
        if (trim($r['email'] ?? '') !== '' && ! filter_var(trim($r['email']), FILTER_VALIDATE_EMAIL)) {
            $p[] = 'Email looks invalid';
        }
        if (trim($r['phone'] ?? '') !== '' && ! Phone::normalize($r['phone'], config('leads.default_country_code', '234'))) {
            $p[] = 'Phone looks invalid';
        }
        if (trim($r['email'] ?? '') === '' && trim($r['phone'] ?? '') === '') {
            $p[] = 'No email or phone (can be saved, but not followed up)';
        }

        return $p;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @param array{batch?:string, start_sequence?:bool, auto_send?:bool} $opts
     * @return array{created:int, existing:int, skipped:int, no_contact:int, messages:string[]}
     */
    public function import(array $rows, array $opts = []): array
    {
        $batch = trim($opts['batch'] ?? '') ?: 'Upload '.now()->format('j M Y H:i');
        $res = ['created' => 0, 'existing' => 0, 'skipped' => 0, 'no_contact' => 0, 'messages' => []];
        $cc = config('leads.default_country_code', '234');
        $services = Service::pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower($n) => $id]);

        foreach (array_slice($rows, 0, (int) config('leads.max_import_rows', 200)) as $i => $r) {
            $n = $i + 1;
            $name = trim($r['company'] ?? '');
            $email = strtolower(trim($r['email'] ?? ''));
            $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
            $phone = Phone::normalize($r['phone'] ?? '', $cc);
            if ($name === '') {
                $res['skipped']++;
                $res['messages'][] = "Row {$n}: skipped, no company name.";
                continue;
            }

            $company = $this->company($name, trim($r['website'] ?? ''), $r);
            $hadOpportunity = $company->opportunities()->exists();
            $contacts = [];
            if ($email) {
                $contacts['email'] = $this->contact($company, 'email', $email, $r, $batch);
            }
            if ($phone) {
                $contacts['phone'] = $this->contact($company, 'phone', $phone, $r, $batch);
            }

            if ($hadOpportunity) {
                $res['existing']++;
                $res['messages'][] = "Row {$n}: {$name} is already in your pipeline; contact details were added to it, no new lead created.";
                continue;
            }

            $opp = Opportunity::create([
                'company_id' => $company->id,
                'title' => 'Lead: '.$name,
                'type' => 'lead',
                'source' => 'uploaded',
                'lead_batch' => $batch,
                'source_hash' => sha1('lead|'.$company->id),
                'summary' => trim(($r['notes'] ?? '').(trim($r['source'] ?? '') !== '' ? "\nSource: ".trim($r['source']) : '')) ?: null,
                'status' => 'qualified',
                'qualified_at' => now(),
                'primary_service_id' => ($sid = $services[mb_strtolower(trim($r['service'] ?? ''))] ?? null),
                'recommended_channel' => $email ? 'email' : ($phone ? 'whatsapp' : null),
                'budget_currency' => null,
            ]);
            if ($sid) {
                $opp->services()->syncWithoutDetaching([$sid => ['match_strength' => 100, 'is_primary' => true]]);
            }
            ActivityLog::record($opp, 'lead_uploaded', "Uploaded lead ({$batch}).");
            $res['created']++;

            if (! $email && ! $phone) {
                $res['no_contact']++;
                continue;
            }
            if (! empty($opts['start_sequence'])) {
                $this->starter->start($opp, $contacts['email'] ?? null, ! empty($opts['auto_send']));
            }
        }

        return $res;
    }

    private function company(string $name, string $website, array $r): Company
    {
        $isSocial = $website !== '' && preg_match('~(instagram|facebook|tiktok|x|twitter|linkedin|wa)\.(com|me)~i', $website);
        $company = null;
        if ($website !== '' && ! $isSocial) {
            $company = Company::where('domain', Company::normalizeDomain($website))->first();
        }
        $company ??= Company::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($company) {
            return $company;
        }

        return Company::create([
            'name' => $name,
            'website_url' => ($website !== '' && ! $isSocial) ? $website : null,
            'industry' => trim($r['industry'] ?? '') ?: null,
            'city' => trim($r['city'] ?? '') ?: null,
            'country' => strlen(trim($r['country'] ?? '')) === 2 ? strtoupper(trim($r['country'])) : null,
            'description' => $isSocial ? 'Social page: '.$website : null,
        ]);
    }

    private function contact(Company $company, string $type, string $value, array $r, string $batch): Contact
    {
        return Contact::firstOrCreate(
            ['company_id' => $company->id, 'type' => $type, 'value' => $value],
            [
                'person_name' => trim($r['contact_name'] ?? '') ?: null,
                'label' => 'Uploaded lead',
                'source_url' => 'uploaded: '.$batch,
                'verified_at' => now(),
                'is_public_business_channel' => false,
                'status' => 'active',
                'created_by' => 'manual',
            ]
        );
    }
}
