<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use App\Services\Dedupe\JobDeduper;
use App\Services\Dedupe\JobFingerprint;
use App\Services\Skills\SkillNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployerJobController extends Controller
{
    private const TYPES = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'internship' => 'Internship'];

    // GET /employer/jobs
    public function index(Request $request)
    {
        $jobs = $request->user()->postedJobs()
            ->withCount([
                'applications',
                'applications as new_applications_count' => fn ($q) => $q->where('status', 'new'),
            ])
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->get();

        return view('employer.jobs.index', ['jobs' => $jobs]);
    }

    // GET /employer/jobs/create
    public function create(Request $request)
    {
        return view('employer.jobs.form', [
            'job' => new JobListing(['is_remote' => true, 'employment_type' => 'full_time', 'apply_method' => 'native', 'salary_currency' => 'USD']),
            'types' => self::TYPES,
            'durations' => config('employer.duration_options'),
            'duration' => config('employer.default_duration'),
            'user' => $request->user(),
        ]);
    }

    // GET /employer/jobs/{job}/edit
    public function edit(Request $request, JobListing $job)
    {
        $this->authorizeJob($request, $job);

        return view('employer.jobs.form', [
            'job' => $job,
            'types' => self::TYPES,
            'durations' => config('employer.duration_options'),
            'duration' => $job->closes_at ? max(1, (int) now()->diffInDays($job->closes_at, false)) : config('employer.default_duration'),
            'user' => $request->user(),
        ]);
    }

    // POST /employer/jobs
    public function store(Request $request, JobDeduper $deduper)
    {
        $data = $this->validated($request);
        $publish = $request->input('intent') === 'publish';

        if ($publish) {
            $this->guardPublishing($request, null, $data);
        }

        $job = JobListing::create($this->attributes($request, $data, new JobListing(), $publish) + [
            'source' => 'native',
            'kind' => 'job',
            'employer_id' => $request->user()->id,
            'external_id' => (string) Str::uuid(),
            'url' => 'pending',
            'url_hash' => sha1('native:' . Str::uuid()),
        ]);

        $job->update(['url' => route('jobs.public', $job)]);

        return $this->afterSave($job, $publish, $deduper);
    }

    // PATCH /employer/jobs/{job}
    public function update(Request $request, JobListing $job, JobDeduper $deduper)
    {
        $this->authorizeJob($request, $job);

        $data = $this->validated($request);
        $publish = $request->input('intent') === 'publish';

        if ($publish && $job->status !== 'published') {
            $this->guardPublishing($request, $job, $data);
        }

        $job->update($this->attributes($request, $data, $job, $publish));

        return $this->afterSave($job->fresh(), $publish, $deduper);
    }

    // POST /employer/jobs/{job}/close
    public function close(Request $request, JobListing $job)
    {
        $this->authorizeJob($request, $job);
        $job->update(['status' => 'closed']);

        return back()->with('status', "“{$job->title}” is closed. It no longer shows to job seekers, and existing applicants are kept.");
    }

    // POST /employer/jobs/{job}/reopen
    public function reopen(Request $request, JobListing $job)
    {
        $this->authorizeJob($request, $job);
        $this->guardPublishing($request, $job, ['title' => $job->title]);

        $job->update(['status' => 'published', 'closes_at' => now()->addDays(config('employer.default_duration')), 'posted_at' => now()]);

        return back()->with('status', "“{$job->title}” is live again for " . config('employer.default_duration') . ' days.');
    }

    // POST /employer/jobs/{job}/duplicate
    public function duplicate(Request $request, JobListing $job)
    {
        $this->authorizeJob($request, $job);

        $copy = $job->replicate(['fingerprint', 'apply_key', 'simhash', 'also_posted', 'canonical_id', 'posted_at', 'closes_at']);
        $copy->fill([
            'title' => $job->title . ' (copy)',
            'status' => 'draft',
            'external_id' => (string) Str::uuid(),
            'url_hash' => sha1('native:' . Str::uuid()),
            'url' => 'pending',
        ])->save();
        $copy->update(['url' => route('jobs.public', $copy)]);

        return redirect()->route('employer.jobs.edit', $copy)->with('status', 'Copied as a draft. Change what you need, then publish.');
    }

    // DELETE /employer/jobs/{job} — only drafts, or jobs nobody applied to
    public function destroy(Request $request, JobListing $job)
    {
        $this->authorizeJob($request, $job);

        if ($job->applications()->exists()) {
            return back()->withErrors(['job' => 'This job has applicants, so it can only be closed (their applications are kept).']);
        }
        $job->delete();

        return redirect()->route('employer.jobs.index')->with('status', 'Job deleted.');
    }

    // ------------------------------------------------------------------

    private function authorizeJob(Request $request, JobListing $job): void
    {
        abort_unless($job->employer_id === $request->user()->id && $job->source === 'native', 404);
    }

    private function validated(Request $request): array
    {
        $isPublish = $request->input('intent') === 'publish';
        $req = $isPublish ? 'required' : 'nullable';

        return $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'location' => ['nullable', 'string', 'max:120'],
            'is_remote' => ['nullable', 'boolean'],
            'employment_type' => ['required', 'in:' . implode(',', array_keys(self::TYPES))],
            'description' => [$req, 'string', 'min:' . ($isPublish ? 80 : 0), 'max:12000'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:100000000', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'alpha', 'size:3'],
            'apply_method' => ['required', 'in:native,email,web'],
            'apply_email' => ['required_if:apply_method,email', 'nullable', 'email', 'max:190'],
            'apply_url' => ['required_if:apply_method,web', 'nullable', 'url', 'max:500'],
            'duration' => ['nullable', 'integer', 'in:' . implode(',', config('employer.duration_options'))],
        ], [
            'description.min' => 'Add a bit more detail (at least a few sentences) so the right people apply.',
            'apply_email.required_if' => 'Enter the email applications should go to.',
            'apply_url.required_if' => 'Enter the link people should apply at.',
            'salary_max.gte' => 'The maximum salary can\'t be lower than the minimum.',
        ]);
    }

    private function attributes(Request $request, array $data, JobListing $job, bool $publish): array
    {
        $normalizer = app(SkillNormalizer::class);
        $skills = $normalizer->names($data['skills'] ?? '');

        $attrs = [
            'title' => $data['title'],
            'company' => $request->user()->company_name,
            'location' => $data['location'] ?? null,
            'is_remote' => $request->boolean('is_remote'),
            'employment_type' => $data['employment_type'],
            'description' => $data['description'] ?? '',
            'skills' => $skills,
            'salary_min' => $data['salary_min'] ?? null,
            'salary_max' => $data['salary_max'] ?? null,
            'salary_currency' => isset($data['salary_currency']) ? strtoupper($data['salary_currency']) : null,
            'apply_method' => $data['apply_method'],
            'apply_email' => $data['apply_method'] === 'email' ? mb_strtolower($data['apply_email']) : null,
            'apply_url' => $data['apply_method'] === 'web' ? $data['apply_url'] : ($job->exists ? $job->url : null),
            'matched_keywords' => [],
            'match_score' => 0,
        ];

        if ($publish) {
            $attrs['status'] = 'published';
            $attrs['posted_at'] = $job->status === 'published' && $job->posted_at ? $job->posted_at : now();
            $attrs['closes_at'] = $job->status === 'published' && $job->closes_at && ! $request->filled('duration')
                ? $job->closes_at
                : now()->addDays((int) ($data['duration'] ?? config('employer.default_duration')));
        } elseif (! $job->exists) {
            $attrs['status'] = 'draft';
        }

        return $attrs;
    }

    /** Active-job cap, daily publish cap, and "you already have this exact job live". */
    private function guardPublishing(Request $request, ?JobListing $job, array $data): void
    {
        $user = $request->user();

        $active = $user->postedJobs()->where('status', 'published')->when($job, fn ($q) => $q->where('id', '!=', $job->id))->count();
        if ($active >= (int) config('employer.max_active_jobs')) {
            throw ValidationException::withMessages(['title' => 'You have ' . $active . ' live jobs, the maximum right now. Close one first.']);
        }

        $today = $user->postedJobs()->where('posted_at', '>=', now()->startOfDay())->when($job, fn ($q) => $q->where('id', '!=', $job->id))->count();
        if ($today >= (int) config('employer.max_publishes_per_day')) {
            throw ValidationException::withMessages(['title' => 'Daily posting limit reached. Try again tomorrow.']);
        }

        $fp = JobFingerprint::fingerprint($user->company_name, $data['title'] ?? '');
        if ($fp) {
            $same = $user->postedJobs()->where('status', 'published')->where('fingerprint', $fp)->when($job, fn ($q) => $q->where('id', '!=', $job->id))->first();
            if ($same) {
                throw ValidationException::withMessages(['title' => "You already have “{$same->title}” live. Edit that one instead of posting it twice."]);
            }
        }
    }

    private function afterSave(JobListing $job, bool $publish, JobDeduper $deduper)
    {
        $job = $deduper->refreshKeys($job);

        if (! $publish) {
            return redirect()->route('employer.jobs.edit', $job)->with('status', 'Draft saved. Publish it whenever you\'re ready.');
        }

        // Same job already on a board / tweeted? Fold those copies under this one.
        $merged = $deduper->adopt($job);
        $note = $merged > 0
            ? " We found {$merged} " . ($merged === 1 ? 'copy' : 'copies') . ' of this job elsewhere and merged ' . ($merged === 1 ? 'it' : 'them') . ' under yours, so job seekers see one listing.'
            : '';

        return redirect()->route('employer.jobs.index')->with('status', "“{$job->title}” is live.{$note}");
    }
}
