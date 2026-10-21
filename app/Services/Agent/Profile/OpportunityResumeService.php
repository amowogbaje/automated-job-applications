<?php

namespace App\Services\Agent\Profile;

use App\Models\CvVersion;
use App\Models\Opportunity;
use App\Models\Resume;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds a resume for one opportunity from the owner's Profile: it only reorders and leads with the
 * REAL skills and projects that fit (the tailor drops anything not on the resume), then renders a PDF
 * and saves it as that opportunity's recommended CV. Nothing is sent.
 */
class OpportunityResumeService
{
    public function __construct(private ProfileSync $profile, private ResumeTailor $tailor, private ResumeCompiler $compiler) {}

    public function available(): bool
    {
        return $this->profile->ownerResume() !== null;
    }

    public function generate(Opportunity $o, bool $force = false): ?CvVersion
    {
        $resume = $this->profile->ownerResume();
        if (! $resume) {
            return null;
        }
        $o->loadMissing(['company', 'services', 'primaryService', 'evidence']);

        $existing = CvVersion::where('opportunity_id', $o->id)->latest('id')->first();
        if ($existing && ! $force && (int) $existing->resume_id === (int) $resume->id
            && $existing->updated_at->greaterThanOrEqualTo($resume->updated_at ?? $existing->updated_at)) {
            return $existing;
        }

        $company = $o->company?->name;
        $text = $this->describe($o);
        $snapshot = null;
        try {
            $snapshot = $this->tailor->tailorForText($resume, (string) $o->title, $company, $text);
        } catch (\Throwable $e) {
            report($e);
        }
        $snapshot ??= $this->fallbackSnapshot($resume, $text);
        $snapshot['source'] = $snapshot['source'] ?? 'ai';

        $abs = $this->compiler->compile($resume, $snapshot, ($company ?: 'opportunity').'-'.$o->id);
        $rel = 'cvs/tailored/'.basename($abs);
        Storage::disk('local')->put($rel, file_get_contents($abs));
        @unlink($abs);

        if ($existing?->file_path) {
            Storage::disk('local')->delete($existing->file_path);
        }
        $cv = $existing ?? new CvVersion;
        $cv->fill([
            'opportunity_id' => $o->id, 'resume_id' => $resume->id,
            'name' => 'Tailored: '.($company ?: 'Opportunity').' · '.Str::limit((string) $o->title, 60),
            'description' => $snapshot['gap_notes'] ?? null, 'file_path' => $rel, 'snapshot' => $snapshot, 'is_default' => false,
        ])->save();
        $cv->touch();

        $o->update([
            'recommended_cv_version_id' => $cv->id,
            'cv_rationale' => 'Generated from your Profile for this opportunity: leads with '.implode(', ', array_slice($snapshot['lead_skills'] ?? [], 0, 5)).'.',
        ]);

        return $cv;
    }

    /** Everything we know about the opportunity, as plain text for the tailoring step. */
    private function describe(Opportunity $o): string
    {
        $parts = [$o->title, $o->summary, $o->business_need, $o->inferred_opportunity];
        foreach ($o->services as $s) {
            $parts[] = $s->name.': '.implode(', ', (array) $s->skills_required);
        }
        foreach ($o->evidence->take(8) as $e) {
            $parts[] = $e->finding;
        }

        return trim(implode("\n", array_filter(array_map(fn ($x) => is_string($x) ? trim($x) : '', $parts))));
    }

    /** No-AI fallback: lead with the resume skills/projects the opportunity text actually mentions. */
    private function fallbackSnapshot(Resume $resume, string $text): array
    {
        $hay = mb_strtolower($text);
        $skills = $resume->skills->pluck('name')->all();
        usort($skills, fn ($a, $b) => (int) str_contains($hay, mb_strtolower($b)) <=> (int) str_contains($hay, mb_strtolower($a)));
        $projects = $resume->projects->sortByDesc(function ($p) use ($hay) {
            $n = str_contains($hay, mb_strtolower($p->name)) ? 3 : 0;
            foreach ((array) $p->tech_stack as $t) {
                $n += str_contains($hay, mb_strtolower($t)) ? 1 : 0;
            }

            return $n;
        })->pluck('name')->take(3)->values()->all();

        return ['tailored_headline' => $resume->headline, 'lead_skills' => array_slice($skills, 0, 10), 'lead_projects' => $projects,
            'gap_notes' => '', 'source' => 'rules'];
    }
}
