<?php

namespace App\Services\Agent\Profile;

use App\Models\Resume;
use App\Models\Service;
use App\Services\Agent\Ai\AiManager;
use Illuminate\Support\Str;

/**
 * Proposes services from the Profile. Nothing is saved here: the owner ticks the ones to keep.
 * Every skill and project a proposal mentions must already exist on the resume.
 */
class ServiceSuggester
{
    public function __construct(private AiManager $ai) {}

    /** @return array{services: array<int, array>, source: string, note: ?string} */
    public function suggest(Resume $resume): array
    {
        $skills = $resume->skills->pluck('name')->all();
        $projects = $resume->projects->pluck('name')->all();
        $note = null;

        if ($this->ai->activeNames()) {
            try {
                $res = $this->ai->complete(
                    $this->prompt($resume), true, 2500,
                    fn (array $d) => isset($d['services']) && is_array($d['services']) ? true : 'Missing "services" list.'
                );
                $clean = self::clean($res->data['services'], $skills, $projects);
                if ($clean) {
                    return ['services' => $this->dropExisting($clean), 'source' => $res->provider, 'note' => null];
                }
                $note = 'The AI proposed nothing usable, so these come from your skill groups.';
            } catch (\Throwable $e) {
                $note = 'AI was unavailable ('.Str::limit($e->getMessage(), 120).'), so these come from your skill groups.';
            }
        } else {
            $note = 'No AI provider is configured, so these come from your skill groups.';
        }

        return ['services' => $this->dropExisting(self::fromSkillGroups($resume)), 'source' => 'rules', 'note' => $note];
    }

    private function prompt(Resume $resume): array
    {
        $system = <<<'TXT'
You turn a developer's resume into 3 to 6 SERVICES they could offer to clients or employers. Work only from the resume.

Rules:
1. Never invent skills, projects or experience. "skills_required" may only contain skills copied exactly from available_skills. "project_names" only names copied exactly from available_projects.
2. Each service is a distinct offer (for example "Laravel backend and API development"), not a single technology.
3. "search_keywords": 4-8 phrases a company with this need would use. "target_businesses": 3-5 kinds of businesses. "opportunity_signals": 3-6 observable things that suggest the need (hiring for X, migrating Y, launching Z).
4. "preferred_engagement" is one of employment, contract, project, consulting, any. "is_employment" is true only for a full-time job offer.
5. Respond with one JSON object: {"services":[{"name","description","skills_required","search_keywords","target_businesses","opportunity_signals","preferred_engagement","is_employment","project_names"}]}. No other text.
TXT;
        $payload = [
            'headline' => $resume->headline, 'summary' => $resume->summary,
            'available_skills' => $resume->skills->pluck('name')->all(),
            'available_projects' => $resume->projects->pluck('name')->all(),
            'experience' => $resume->experiences->take(5)->map(fn ($e) => ['title' => $e->job_title, 'company' => $e->company, 'highlights' => array_slice((array) $e->bullets, 0, 3)])->all(),
            'projects' => $resume->projects->take(8)->map(fn ($p) => ['name' => $p->name, 'about' => Str::limit((string) $p->description, 200)])->all(),
        ];

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]];
    }

    /** Pure validation of the AI's answer against the real resume. */
    public static function clean(array $raw, array $resumeSkills, array $resumeProjects): array
    {
        $skillMap = [];
        foreach ($resumeSkills as $s) {
            $skillMap[mb_strtolower($s)] = $s;
        }
        $projMap = [];
        foreach ($resumeProjects as $p) {
            $projMap[mb_strtolower($p)] = $p;
        }
        $list = fn ($v, int $max, int $len) => array_values(array_slice(array_unique(array_filter(array_map(
            fn ($x) => is_string($x) ? mb_substr(trim($x), 0, $len) : '', (array) $v))), 0, $max));

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            $skills = [];
            foreach ((array) ($item['skills_required'] ?? []) as $s) {
                if (is_string($s) && isset($skillMap[mb_strtolower(trim($s))])) {
                    $skills[] = $skillMap[mb_strtolower(trim($s))];
                }
            }
            $skills = array_values(array_unique($skills));
            if ($name === '' || mb_strlen($name) > 120 || ! $skills) {
                continue;
            }
            $projects = [];
            foreach ((array) ($item['project_names'] ?? []) as $p) {
                if (is_string($p) && isset($projMap[mb_strtolower(trim($p))])) {
                    $projects[] = $projMap[mb_strtolower(trim($p))];
                }
            }
            $engagement = in_array($item['preferred_engagement'] ?? '', ['any', 'employment', 'contract', 'project', 'consulting'], true) ? $item['preferred_engagement'] : 'any';
            $out[] = [
                'name' => $name, 'description' => mb_substr(trim((string) ($item['description'] ?? '')), 0, 600),
                'skills_required' => $skills, 'search_keywords' => $list($item['search_keywords'] ?? [], 8, 100),
                'target_businesses' => $list($item['target_businesses'] ?? [], 6, 120), 'opportunity_signals' => $list($item['opportunity_signals'] ?? [], 6, 160),
                'preferred_engagement' => $engagement, 'is_employment' => $engagement === 'employment' && ! empty($item['is_employment']),
                'project_names' => array_values(array_unique($projects)),
            ];
        }

        return array_slice($out, 0, 6);
    }

    /** No-AI fallback: one service per skill group that has at least two skills. */
    public static function fromSkillGroups(Resume $resume): array
    {
        $labels = ['backend' => 'Backend development', 'frontend' => 'Frontend development', 'database' => 'Database design and optimisation',
            'apis_integrations' => 'API design and integrations', 'applied_ai' => 'Applied AI features', 'testing_devops' => 'Testing and deployment (DevOps)',
            'architecture' => 'Software architecture'];
        $out = [];
        foreach ($resume->skills->groupBy('category') as $cat => $group) {
            if (! isset($labels[$cat]) || $group->count() < 2) {
                continue;
            }
            $names = $group->pluck('name')->take(8)->all();
            $out[] = [
                'name' => $labels[$cat], 'description' => $labels[$cat].' using '.implode(', ', array_slice($names, 0, 5)).'.',
                'skills_required' => $names, 'search_keywords' => array_map(fn ($n) => $n.' developer', array_slice($names, 0, 4)),
                'target_businesses' => [], 'opportunity_signals' => array_map(fn ($n) => "hiring {$n} developer", array_slice($names, 0, 3)),
                'preferred_engagement' => 'any', 'is_employment' => false, 'project_names' => [],
            ];
        }

        return array_slice($out, 0, 6);
    }

    private function dropExisting(array $items): array
    {
        $have = Service::pluck('name')->map(fn ($n) => Str::slug($n))->all();

        return array_values(array_filter($items, fn ($i) => ! in_array(Str::slug($i['name']), $have, true)));
    }
}
