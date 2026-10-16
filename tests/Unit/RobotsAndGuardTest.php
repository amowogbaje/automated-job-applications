<?php

namespace Tests\Unit;

use App\Services\Research\RobotsTxt;
use App\Services\Research\UrlGuard;
use PHPUnit\Framework\TestCase;

class RobotsAndGuardTest extends TestCase
{
    public function testDisallowAll(): void
    {
        $this->assertFalse(RobotsTxt::allows("User-agent: *\nDisallow: /", '/contact'));
    }

    public function testEmptyDisallowAllowsAll(): void
    {
        $this->assertTrue(RobotsTxt::allows("User-agent: *\nDisallow:", '/contact'));
    }

    public function testSpecificPathAndAllowOverride(): void
    {
        $robots = "User-agent: *\nDisallow: /private\nAllow: /private/public\n";
        $this->assertFalse(RobotsTxt::allows($robots, '/private/x'));
        $this->assertTrue(RobotsTxt::allows($robots, '/private/public/page'));
        $this->assertTrue(RobotsTxt::allows($robots, '/contact'));
    }

    public function testOurAgentGroupBeatsWildcard(): void
    {
        $robots = "User-agent: *\nDisallow: /\n\nUser-agent: OpportunityAgentBot\nDisallow: /admin\n";
        $this->assertTrue(RobotsTxt::allows($robots, '/contact'));
        $this->assertFalse(RobotsTxt::allows($robots, '/admin/x'));
    }

    public function testBlockedBotNamedExplicitly(): void
    {
        $this->assertFalse(RobotsTxt::allows("User-agent: opportunityagentbot\nDisallow: /\n", '/'));
    }

    public function testWildcardsAndComments(): void
    {
        $robots = "# hi\nUser-agent: *\nDisallow: /*.pdf$ # no pdfs\n";
        $this->assertFalse(RobotsTxt::allows($robots, '/files/cv.pdf'));
        $this->assertTrue(RobotsTxt::allows($robots, '/files/cv.pdfx'));
    }

    public function testNoRulesAllows(): void
    {
        $this->assertTrue(RobotsTxt::allows('', '/x'));
        $this->assertTrue(RobotsTxt::allows("Sitemap: https://a.com/s.xml\n", '/x'));
    }

    public function testGuardBlocksPrivateAndOddTargets(): void
    {
        foreach (['http://localhost/', 'http://127.0.0.1/', 'http://10.0.0.5/', 'http://192.168.1.1/', 'http://169.254.169.254/latest', 'http://[::1]/', 'ftp://example.com/', 'https://example.com:8080/', 'https://user:pw@8.8.8.8/', 'not a url', 'http://box.internal/'] as $url) {
            $this->assertFalse(UrlGuard::isPublicHttpUrl($url), $url);
        }
        $this->assertTrue(UrlGuard::isPublicHttpUrl('https://8.8.8.8/'));
    }
}
