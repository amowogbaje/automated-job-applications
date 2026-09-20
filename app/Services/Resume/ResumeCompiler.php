<?php

namespace App\Services\Resume;

use App\Models\Resume;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ResumeCompiler
{
    /**
     * Compile a Resume (plus everything loaded on its relations) into a PDF
     * and save it to storage. Pass $snapshot (from ResumeTailor) to reorder/
     * trim skills and projects for a specific job without touching the DB —
     * the underlying resume record never changes per-job, only the render.
     *
     * @return string absolute path to the generated PDF
     */
    public function compile(Resume $resume, ?array $snapshot = null, ?string $filenameHint = null): string
    {
        $resume->loadMissing(['experiences', 'education', 'skills', 'projects', 'certifications']);

        $headline = $snapshot['tailored_headline'] ?? $resume->headline;

        $skillsByCategory = $resume->skillsByCategory();
        if (! empty($snapshot['lead_skills'])) {
            // Keep categories, but reorder within them so tailored picks surface first,
            // and drop nothing — the rest of the real skill set still prints below.
            $lead = collect($snapshot['lead_skills']);
            $skillsByCategory = $skillsByCategory->map(
                fn ($names) => $names->sortBy(fn ($n) => $lead->search($n) === false ? 999 : $lead->search($n))->values()
            );
        }

        $projects = $resume->projects;
        if (! empty($snapshot['lead_projects'])) {
            $lead = collect($snapshot['lead_projects']);
            $projects = $projects->sortBy(fn ($p) => $lead->search($p->name) === false ? 999 : $lead->search($p->name))->values();
        }

        $pdf = Pdf::loadView('resume.pdf', [
            'resume' => $resume,
            'headline' => $headline,
            'skillsByCategory' => $skillsByCategory,
            'projects' => $projects,
        ])->setPaper('a4');

        $dir = storage_path('app/resumes');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $slug = Str::slug($resume->full_name ?: 'resume');
        $suffix = $filenameHint ? '-' . Str::slug($filenameHint) : '';
        $path = "{$dir}/{$slug}{$suffix}-" . now()->format('Ymd-His') . '.pdf';

        $pdf->save($path);

        return $path;
    }
}
