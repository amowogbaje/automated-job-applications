<?php

namespace Tests\Unit;

use App\Services\Matching\JobMatcher;
use App\Services\Skills\SkillNormalizer;
use PHPUnit\Framework\TestCase;

class JobMatcherTest extends TestCase
{
    private function m(): JobMatcher
    {
        return new JobMatcher(new SkillNormalizer(require dirname(__DIR__, 2) . '/config/skills.php'));
    }

    private function stack(): array
    {
        return ['required' => ['Laravel', 'PHP', 'Vue.js'], 'keywords' => ['Laravel', 'PHP', 'Vue.js', 'REST', 'Git', 'SQL', 'Agile']];
    }

    public function test_generic_skills_alone_never_qualify_a_job(): void
    {
        $r = $this->m()->score('Senior Java Engineer', 'Spring Boot, REST APIs, Git, SQL, Agile team.', ['Laravel', 'PHP', 'REST', 'Git'], ['REST', 'Git']);
        $this->assertFalse($r['matches']);
    }

    public function test_title_hits_outrank_description_only_hits(): void
    {
        $inTitle = $this->m()->score('Laravel Developer', 'Build features.', ...$this->args());
        $inBody = $this->m()->score('Backend Developer', 'We use Laravel daily.', ...$this->args());
        $this->assertTrue($inTitle['matches'] && $inBody['matches']);
        $this->assertTrue($inTitle['score'] > $inBody['score']);
    }

    private function args(): array
    {
        return ['required' => ['Laravel'], 'keywords' => ['PHP']];
    }

    public function test_tiers(): void
    {
        $strong = $this->m()->score('Senior Laravel / PHP Developer', 'Vue.js frontend too.', ...$this->stack());
        $this->assertSame('strong', $strong['tier']);

        $weak = $this->m()->score('Backend Engineer', 'Our legacy system is written in PHP.', ...$this->stack());
        $this->assertSame('weak', $weak['tier']);
    }

    public function test_excluded_words_are_boundary_aware(): void
    {
        $ok = $this->m()->score('Laravel Developer', 'Join our international team.', ['Laravel'], [], ['intern']);
        $this->assertTrue($ok['matches']);

        $out = $this->m()->score('Laravel Intern', 'Learn on the job.', ['Laravel'], [], ['intern']);
        $this->assertFalse($out['matches']);
        $this->assertSame('intern', $out['excluded_by']);
    }

    public function test_alias_and_boundary_matching_in_scoring(): void
    {
        $r = $this->m()->score('Full Stack Engineer', 'NestJS and Postgres, remote.', ['Nest.js'], []);
        $this->assertTrue($r['matches']);
        $this->assertSame(['NestJS'], $r['matched']);

        $no = $this->m()->score('JavaScript Developer', 'Frontend work.', ['Java'], []);
        $this->assertFalse($no['matches']);
    }

    public function test_generic_hits_add_at_most_three_points(): void
    {
        $r = $this->m()->score('Developer', 'Laravel. Also REST, Git, SQL, Agile, HTML, CSS.', ['Laravel'], ['REST', 'Git', 'SQL', 'Agile', 'HTML', 'CSS']);
        $this->assertSame(3 + 3, $r['score']); // Laravel in body (3) + generic cap (3)
    }

    public function test_region_restriction_is_flagged_and_can_hide(): void
    {
        $text = 'Remote role. Must be located in the United States to apply. Laravel.';
        $flag = $this->m()->score('Laravel Developer', $text, ['Laravel'], []);
        $this->assertTrue($flag['matches']);
        $this->assertTrue(count($flag['warnings']) === 1);

        $hide = $this->m()->score('Laravel Developer', $text, ['Laravel'], [], [], 1, true);
        $this->assertFalse($hide['matches']);

        $global = $this->m()->score('Laravel Developer', 'Work from anywhere in the world. Laravel.', ['Laravel'], [], [], 1, true);
        $this->assertTrue($global['matches']);
        $this->assertSame([], $global['warnings']);
    }

    public function test_min_required_matches_is_respected_for_core_skills(): void
    {
        $r = $this->m()->score('PHP Developer', 'PHP only here.', ['Laravel', 'PHP'], [], [], 2);
        $this->assertFalse($r['matches']);
        $ok = $this->m()->score('Laravel PHP Developer', 'x', ['Laravel', 'PHP'], [], [], 2);
        $this->assertTrue($ok['matches']);
    }
}
