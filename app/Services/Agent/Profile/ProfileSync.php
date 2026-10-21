<?php

namespace App\Services\Agent\Profile;

use App\Models\PortfolioProject;
use App\Models\Resume;
use App\Models\ResumeProject;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * One source of truth: the owner's Profile (resume). Its projects are mirrored into the agent's
 * portfolio_projects (linked by resume_project_id), so nothing is typed twice. Agent-only fields
 * (industries, proof points, public flag, service links) stay editable on the agent side.
 */
class ProfileSync
{
    public static function ownerId(): ?int
    {
        $owners = (array) config('agent.owner_emails', []);
        if ($owners) {
            return User::query()->whereIn('email', $owners)->orderBy('id')->value('id');
        }

        return User::query()->min('id') ?: null;
    }

    public function ownerResume(): ?Resume
    {
        $id = self::ownerId();

        return $id ? Resume::current($id) : null;
    }

    public function isOwnersResume(Resume $resume): bool
    {
        return $resume->user_id !== null && (int) $resume->user_id === (int) self::ownerId();
    }

    /** Mirror every project of the owner's active resume. Safe to call repeatedly. */
    public function syncProjects(): int
    {
        $resume = $this->ownerResume();
        if (! $resume) {
            return 0;
        }
        $n = 0;
        foreach ($resume->projects as $p) {
            $this->syncProject($p);
            $n++;
        }

        return $n;
    }

    public function syncProject(ResumeProject $p): PortfolioProject
    {
        $fields = [
            'name' => $p->name, 'url' => $p->url, 'summary' => $p->description,
            'technologies' => array_values((array) $p->tech_stack),
        ];

        $linked = PortfolioProject::where('resume_project_id', $p->id)->first();
        if (! $linked) {
            // Adopt a hand-made project with the same name instead of creating a duplicate.
            $linked = PortfolioProject::whereNull('resume_project_id')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($p->name)])->first();
        }
        if ($linked) {
            $linked->update($fields + ['resume_project_id' => $p->id]);

            return $linked;
        }

        return PortfolioProject::create($fields + ['resume_project_id' => $p->id, 'slug' => $this->uniqueSlug($p->name), 'is_public' => true]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        for ($i = 2; PortfolioProject::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
