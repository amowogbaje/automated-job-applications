<?php

namespace Tests\Feature;

use App\Mail\JobAlertMail;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\JobListingUserState;
use App\Models\User;
use App\Services\Matching\JobAlertService;
use App\Services\Matching\JobMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class JobFeedTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $title, string $body = '', array $extra = []): JobListing
    {
        return JobListing::create($extra + [
            'source' => 'test', 'kind' => 'job', 'title' => $title, 'company' => 'Acme',
            'description' => $body, 'url' => 'https://x.test/' . md5($title . $body), 'url_hash' => md5($title . $body),
            'posted_at' => now(), 'language' => 'en', 'translation_status' => 'none',
        ]);
    }

    private function profile(User $user, array $attrs = []): CareerProfile
    {
        $p = CareerProfile::forUser($user->id);
        $p->forceFill($attrs)->save();

        return $p->fresh();
    }

    public function test_new_accounts_start_with_no_skills_and_no_alerts(): void
    {
        $p = CareerProfile::forUser(User::factory()->create()->id);

        $this->assertFalse($p->hasInterests());
        $this->assertFalse($p->digest_enabled);
        $this->assertFalse($p->score('Laravel Developer', 'We use Laravel.')['matches']);
    }

    public function test_only_jobs_for_the_chosen_skill_are_matched(): void
    {
        $user = User::factory()->create();
        $p = $this->profile($user, ['required_skills' => ['Laravel'], 'keywords' => []]);

        $laravel = $this->job('Senior Laravel Developer', 'Build APIs.');
        $react = $this->job('React Developer', 'Build UIs with React.');

        $n = app(JobMatchService::class)->matchNew($p);

        $this->assertSame(1, $n);
        $this->assertNotNull(JobListingUserState::where('user_id', $user->id)->where('job_listing_id', $laravel->id)->value('matched_at'));
        $this->assertSame(0, JobListingUserState::where('job_listing_id', $react->id)->count());
        $this->assertSame('strong', JobListingUserState::where('job_listing_id', $laravel->id)->value('tier'));
    }

    public function test_watermark_means_a_job_is_scored_once(): void
    {
        $user = User::factory()->create();
        $p = $this->profile($user, ['required_skills' => ['Laravel']]);
        $this->job('Laravel Developer');

        $svc = app(JobMatchService::class);
        $this->assertSame(1, $svc->matchNew($p));
        $this->assertSame(0, $svc->matchNew($p->fresh()));

        $this->job('Another Laravel Engineer role');
        $this->assertSame(1, $svc->matchNew($p->fresh()));
    }

    public function test_changing_skills_rescores_and_keeps_applied_flags(): void
    {
        $user = User::factory()->create();
        $p = $this->profile($user, ['required_skills' => ['Laravel']]);
        $laravel = $this->job('Laravel Developer');
        $vue = $this->job('Vue Developer');
        $svc = app(JobMatchService::class);
        $svc->matchNew($p);
        JobListingUserState::where('job_listing_id', $laravel->id)->update(['is_applied' => true]);

        $p->forceFill(['required_skills' => ['Vue.js']])->save();
        $svc->backfill($p->fresh());

        $old = JobListingUserState::where('job_listing_id', $laravel->id)->first();
        $this->assertNull($old->matched_at);
        $this->assertTrue($old->is_applied);
        $this->assertNotNull(JobListingUserState::where('job_listing_id', $vue->id)->value('matched_at'));
    }

    public function test_social_posts_follow_the_social_switch(): void
    {
        $user = User::factory()->create();
        $p = $this->profile($user, ['required_skills' => ['Laravel'], 'social_jobs_enabled' => false]);
        $this->job('Laravel dev wanted', 'hiring', ['kind' => 'social', 'social_platform' => 'mastodon']);

        $this->assertSame(0, app(JobMatchService::class)->backfill($p->fresh()));

        $p->forceFill(['social_jobs_enabled' => true])->save();
        $this->assertSame(1, app(JobMatchService::class)->backfill($p->fresh()));
    }

    public function test_feed_shows_only_matches_for_the_viewer(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $p = $this->profile($me, ['required_skills' => ['Laravel']]);
        $this->profile($other, ['required_skills' => ['React']]);
        $this->job('Laravel Developer');
        $this->job('React Developer');
        app(JobMatchService::class)->matchAll();

        $this->actingAs($me)->get(route('jobs.index'))
            ->assertOk()->assertSee('Laravel Developer')->assertDontSee('React Developer');
    }

    public function test_saving_interests_normalises_skills_and_fills_the_feed(): void
    {
        $user = User::factory()->create();
        $this->job('NestJS Engineer');

        $this->actingAs($user)->put(route('jobs.interests.update'), [
            'required_skills' => 'nest js', 'keywords' => '', 'excluded_keywords' => 'Junior',
            'min_required_matches' => 1, 'alert_frequency' => 'daily', 'alert_min_tier' => 'good',
        ])->assertRedirect(route('jobs.index'));

        $p = CareerProfile::forUser($user->id);
        $this->assertSame(['NestJS'], $p->required_skills);
        $this->assertSame(['junior'], $p->excluded_keywords);
        $this->assertFalse($p->digest_enabled);
        $this->assertSame(1, JobListingUserState::where('user_id', $user->id)->whereNotNull('matched_at')->count());
    }

    public function test_alerts_are_opt_in_skill_filtered_and_sent_once(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $p = $this->profile($user, ['required_skills' => ['Laravel'], 'alert_frequency' => 'hourly', 'digest_enabled' => false]);
        $this->job('Laravel Developer');
        $this->job('Go Developer');
        app(JobMatchService::class)->matchAll();

        $alerts = app(JobAlertService::class);

        // off by default: nothing goes out
        $this->assertSame(0, $alerts->sendDue());
        Mail::assertNothingSent();

        $p->forceFill(['digest_enabled' => true])->save();
        $this->assertSame(1, $alerts->sendDue());
        Mail::assertSent(JobAlertMail::class, fn ($m) => $m->total === 1 && $m->states->first()->job->title === 'Laravel Developer');

        // the same job is never emailed twice
        $this->assertSame(0, $alerts->sendDue());
    }

    public function test_alert_threshold_skips_weak_matches(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->profile($user, ['required_skills' => ['Laravel'], 'alert_frequency' => 'hourly', 'digest_enabled' => true, 'alert_min_tier' => 'strong']);
        $this->job('Backend Developer', 'You will use Laravel.'); // skill only in the body => weak
        app(JobMatchService::class)->matchAll();

        $this->assertSame(0, app(JobAlertService::class)->sendDue());
        Mail::assertNothingSent();
    }

    public function test_dismissed_and_applied_jobs_are_not_emailed(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->profile($user, ['required_skills' => ['Laravel'], 'alert_frequency' => 'hourly', 'digest_enabled' => true]);
        $a = $this->job('Laravel Developer A');
        $b = $this->job('Laravel Developer B');
        app(JobMatchService::class)->matchAll();
        JobListingUserState::where('job_listing_id', $a->id)->update(['is_dismissed' => true]);
        JobListingUserState::where('job_listing_id', $b->id)->update(['is_applied' => true]);

        $this->assertSame(0, app(JobAlertService::class)->sendDue());
    }
}
