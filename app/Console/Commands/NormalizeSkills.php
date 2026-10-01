<?php

namespace App\Console\Commands;

use App\Models\CareerProfile;
use App\Models\Resume;
use App\Services\Skills\SkillNormalizer;
use Illuminate\Console\Command;

class NormalizeSkills extends Command
{
    protected $signature = 'skills:normalize {--dry-run : Show what would change without saving}';

    protected $description = 'One-off cleanup: split compound skills ("Node.js (NestJS, Express.js)") into atomic canonical rows on every stored resume, and canonicalize every career profile\'s keywords/required skills';

    public function handle(SkillNormalizer $normalizer): int
    {
        $dry = $this->option('dry-run');

        foreach (Resume::with('skills')->get() as $resume) {
            $before = $resume->skills->pluck('name')->all();
            $grouped = $resume->skills->groupBy('category')->map(fn ($g) => $g->pluck('name')->all())->all();

            $after = collect($normalizer->normalize($before))->pluck('name')->all();

            if ($before === $after) {
                continue;
            }

            $this->line("Resume #{$resume->id} ({$resume->full_name}): " . count($before) . ' -> ' . count($after) . ' skills');
            foreach (array_diff($before, $after) as $old) {
                $this->line("   - {$old}");
            }
            foreach (array_diff($after, $before) as $new) {
                $this->line("   + {$new}");
            }

            if (! $dry) {
                $resume->syncSkills($grouped);
            }
        }

        foreach (CareerProfile::all() as $profile) {
            $keywords = $normalizer->names($profile->keywords ?? []);
            $required = $normalizer->names($profile->required_skills ?? []);

            if ($keywords === ($profile->keywords ?? []) && $required === ($profile->required_skills ?? [])) {
                continue;
            }

            $this->line("Profile for user #{$profile->user_id}: keywords/required skills canonicalized");

            if (! $dry) {
                $profile->update(['keywords' => $keywords, 'required_skills' => $required]);
            }
        }

        $this->info($dry ? 'Dry run — nothing saved.' : 'Done.');

        return self::SUCCESS;
    }
}
