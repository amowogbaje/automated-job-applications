<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Services\Skills\SkillNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Inline editing for the LinkedIn-style profile page: the intro block,
 * experience / education / projects / certifications, and skills.
 * Everything is scoped to the signed-in user's ACTIVE resume.
 */
class ProfileSectionController extends Controller
{
    private const SECTIONS = ['experience' => 'experiences', 'education' => 'education', 'project' => 'projects', 'certification' => 'certifications'];

    private function resume(Request $request): Resume
    {
        $resume = Resume::current($request->user()->id);
        abort_unless($resume, 404, 'Import a resume first.');

        return $resume;
    }

    private function done(string $message, string $anchor = '')
    {
        return redirect()->to(route('profile.edit', ['tab' => 'profile']) . $anchor)->with('status', $message);
    }

    // PATCH /profile/intro
    public function intro(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'summary' => ['nullable', 'string', 'max:3000'],
        ]);

        $this->resume($request)->update($data);

        return $this->done('Profile updated.');
    }

    // POST /profile/{section}
    public function store(Request $request, string $section)
    {
        $resume = $this->resume($request);
        $payload = $this->payload($request, $section);
        $payload['sort_order'] = (int) $resume->{self::SECTIONS[$section]}()->max('sort_order') + 1;

        $resume->{self::SECTIONS[$section]}()->create($payload);

        return $this->done(ucfirst($section) . ' added.', '#' . $section);
    }

    // PATCH /profile/{section}/{id}
    public function update(Request $request, string $section, int $id)
    {
        $item = $this->resume($request)->{self::SECTIONS[$section]}()->findOrFail($id);
        $item->update($this->payload($request, $section));

        return $this->done(ucfirst($section) . ' updated.', '#' . $section);
    }

    // DELETE /profile/{section}/{id}
    public function destroy(Request $request, string $section, int $id)
    {
        $this->resume($request)->{self::SECTIONS[$section]}()->findOrFail($id)->delete();

        return $this->done(ucfirst($section) . ' removed.', '#' . $section);
    }

    // POST /profile/skills — "nest js, graphql" -> NestJS, GraphQL (atomic + canonical), deduped.
    public function addSkills(Request $request)
    {
        $data = $request->validate(['names' => ['required', 'string', 'max:1000']]);

        $added = $this->resume($request)->addSkills($data['names']);

        return $this->done($added ? 'Added ' . implode(', ', $added) . '.' : 'Those skills are already on your profile.', '#skills');
    }

    // DELETE /profile/skills/{skill}
    public function removeSkill(Request $request, int $skill)
    {
        $this->resume($request)->skills()->findOrFail($skill)->delete();

        return $this->done('Skill removed.', '#skills');
    }

    // GET /profile/skills/preview?q=... — live "how will this be saved?" for the add-skill box.
    public function previewSkills(Request $request, SkillNormalizer $normalizer)
    {
        $q = (string) $request->query('q', '');

        return response()->json(array_map(fn ($s) => [
            'name' => $s['name'],
            'category' => $s['category'],
            'known' => $s['known'],
            'qualifier' => $s['qualifier'],
        ], $normalizer->normalize($q)));
    }

    // ------------------------------------------------------------------

    private function payload(Request $request, string $section): array
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        return match ($section) {
            'experience' => $this->experience($request),
            'education' => $this->education($request),
            'project' => $this->project($request),
            'certification' => $request->validate(['name' => ['required', 'string', 'max:255']]),
        };
    }

    private function month(?string $value): ?Carbon
    {
        return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : null;
    }

    private function experience(Request $request): array
    {
        $d = $request->validate([
            'job_title' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m'],
            'end_date' => ['nullable', 'date_format:Y-m', 'after_or_equal:start_date'],
            'bullets' => ['nullable', 'string', 'max:5000'],
        ]);
        $current = $request->boolean('is_current');

        return [
            'job_title' => $d['job_title'],
            'company' => $d['company'] ?? null,
            'location' => $d['location'] ?? null,
            'start_date' => $this->month($d['start_date'] ?? null),
            'end_date' => $current ? null : $this->month($d['end_date'] ?? null),
            'is_current' => $current,
            // One achievement per line in the form -> JSON array.
            'bullets' => array_values(array_filter(array_map(fn ($l) => trim(ltrim(trim($l), "-•* \t")), preg_split('/\R/', $d['bullets'] ?? '')))),
        ];
    }

    private function education(Request $request): array
    {
        $d = $request->validate([
            'institution' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m'],
            'end_date' => ['nullable', 'date_format:Y-m', 'after_or_equal:start_date'],
        ]);

        return [
            'institution' => $d['institution'],
            'degree' => $d['degree'] ?? null,
            'field' => $d['field'] ?? null,
            'start_date' => $this->month($d['start_date'] ?? null),
            'end_date' => $this->month($d['end_date'] ?? null),
        ];
    }

    private function project(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'tech_stack' => ['nullable', 'string', 'max:1000'],
            'url' => ['nullable', 'url', 'max:255'],
        ]);

        return [
            'name' => $d['name'],
            'description' => $d['description'] ?? null,
            // Same canonical, atomic skills as everywhere else.
            'tech_stack' => app(SkillNormalizer::class)->names($d['tech_stack'] ?? ''),
            'url' => $d['url'] ?? null,
        ];
    }
}
