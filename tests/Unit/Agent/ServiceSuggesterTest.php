<?php

namespace Tests\Unit\Agent;

use App\Services\Agent\Profile\ServiceSuggester;
use PHPUnit\Framework\TestCase;

class ServiceSuggesterTest extends TestCase
{
    public function test_skills_and_projects_not_on_the_resume_are_dropped(): void
    {
        $out = ServiceSuggester::clean([
            ['name' => 'Laravel APIs', 'description' => 'x', 'skills_required' => ['laravel', 'Kubernetes', 'PHP'], 'project_names' => ['Storyverse', 'Ghost'], 'preferred_engagement' => 'contract'],
            ['name' => 'Rust embedded', 'skills_required' => ['Rust']],
            ['name' => '', 'skills_required' => ['PHP']],
        ], ['Laravel', 'PHP'], ['Storyverse']);

        $this->assertCount(1, $out);
        $this->assertSame(['Laravel', 'PHP'], $out[0]['skills_required']);
        $this->assertSame(['Storyverse'], $out[0]['project_names']);
        $this->assertSame('contract', $out[0]['preferred_engagement']);
    }

    public function test_unknown_engagement_becomes_any_and_employment_needs_the_flag(): void
    {
        $out = ServiceSuggester::clean([['name' => 'A', 'skills_required' => ['PHP'], 'preferred_engagement' => 'bogus', 'is_employment' => true]], ['PHP'], []);
        $this->assertSame('any', $out[0]['preferred_engagement']);
        $this->assertFalse($out[0]['is_employment']);
    }
}
