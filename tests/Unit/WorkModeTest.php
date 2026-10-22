<?php

namespace Tests\Unit;

use App\Services\Matching\JobMatcher;
use App\Services\Matching\WorkMode;
use App\Services\Skills\SkillNormalizer;
use PHPUnit\Framework\TestCase;

class WorkModeTest extends TestCase
{
    public function test_detects_the_three_modes(): void
    {
        $this->assertSame('remote', WorkMode::detect('Laravel Developer (Remote)', 'Build things', null, false));
        $this->assertSame('remote', WorkMode::detect('Laravel Developer', 'Build things', null, true));
        $this->assertSame('hybrid', WorkMode::detect('Laravel Developer', 'Hybrid role, 3 days a week in the office.', 'Lagos', false));
        $this->assertSame('onsite', WorkMode::detect('Laravel Developer', 'Join our team in Lagos.', 'Lagos', false));
    }

    public function test_hybrid_wins_over_remote_wording(): void
    {
        $this->assertSame('hybrid', WorkMode::detect('Developer', 'Hybrid: partially remote.', null, true));
    }

    public function test_unmarked_jobs_count_as_onsite(): void
    {
        $this->assertSame('work type (onsite)', WorkMode::rejects('onsite', ['remote'], [], '', ''));
        $this->assertNull(WorkMode::rejects('remote', ['remote'], [], '', ''));
        $this->assertNull(WorkMode::rejects('onsite', [], [], '', ''));
        $this->assertNull(WorkMode::rejects('onsite', ['remote', 'hybrid', 'onsite'], [], '', ''));
    }

    public function test_locations_only_apply_to_onsite_and_hybrid(): void
    {
        $this->assertSame('location', WorkMode::rejects('onsite', ['onsite'], ['Lagos'], 'Berlin', 'Office job in Berlin'));
        $this->assertNull(WorkMode::rejects('onsite', ['onsite'], ['Lagos'], 'Lagos, Nigeria', ''));
        $this->assertNull(WorkMode::rejects('remote', [], ['Lagos'], 'Berlin', ''));
    }

    public function test_matcher_filters_by_work_type(): void
    {
        $m = new JobMatcher(new SkillNormalizer(require dirname(__DIR__, 2).'/config/skills.php'));
        $office = $m->score('Laravel Developer', 'Laravel work in our Lagos office.', ['Laravel'], [], workTypes: ['remote'], location: 'Lagos');
        $remote = $m->score('Laravel Developer', 'Laravel work, fully remote.', ['Laravel'], [], workTypes: ['remote'], isRemote: true);
        $none = $m->score('Laravel Developer', 'Laravel work in our Lagos office.', ['Laravel'], [], location: 'Lagos');

        $this->assertFalse($office['matches']);
        $this->assertTrue($remote['matches']);
        $this->assertTrue($none['matches']);
    }
}
