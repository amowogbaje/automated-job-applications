<?php

namespace Tests\Unit;

use App\Services\Dedupe\JobFingerprint as F;
use PHPUnit\Framework\TestCase;

class JobFingerprintTest extends TestCase
{
    public function test_same_job_on_two_boards_gets_the_same_fingerprint(): void
    {
        $a = F::fingerprint('Acme GmbH', 'Senior Laravel Developer (m/w/d)');
        $b = F::fingerprint('ACME', 'Laravel Developer Senior - Remote');
        $this->assertTrue($a !== null && $a === $b);

        $this->assertTrue(F::fingerprint('Acme Inc.', 'Backend Engineer') === F::fingerprint('acme', 'Backend Engineer (Remote)'));
    }

    public function test_different_jobs_or_companies_differ(): void
    {
        $this->assertTrue(F::fingerprint('Acme', 'Laravel Developer') !== F::fingerprint('Acme', 'Vue Developer'));
        $this->assertTrue(F::fingerprint('Acme', 'Laravel Developer') !== F::fingerprint('Globex', 'Laravel Developer'));
        $this->assertSame(null, F::fingerprint(null, 'Laravel Developer'));
        $this->assertSame(null, F::fingerprint('Acme', 'Dev'));
    }

    public function test_canonical_url_ignores_tracking_and_www(): void
    {
        $this->assertSame(
            F::canonicalUrl('https://www.acme.dev/careers/123/?utm_source=twitter&gh_src=abc'),
            F::canonicalUrl('http://acme.dev/careers/123')
        );
        $this->assertTrue(F::canonicalUrl('https://acme.dev/jobs?id=1') !== F::canonicalUrl('https://acme.dev/jobs?id=2'));
        $this->assertSame(null, F::canonicalUrl('not a url'));
    }

    public function test_apply_key_prefers_ats_ids_and_ignores_social_links(): void
    {
        $this->assertSame('ats:greenhouse:acme:123', F::applyKey('greenhouse', 'Acme', '123', null));
        $this->assertSame('url:acme.dev/careers/php', F::applyKey(null, null, null, 'https://acme.dev/careers/php?utm_source=x'));
        $this->assertSame(null, F::applyKey(null, null, null, 'https://x.com/acme/status/1'));
        $this->assertSame(null, F::applyKey(null, null, null, 'https://news.ycombinator.com/item?id=5'));
        $this->assertSame(null, F::applyKey(null, null, null, null));
    }

    public function test_crossposted_text_is_a_near_duplicate_but_other_text_is_not(): void
    {
        $tweet = "We're hiring a senior Laravel developer at Acme! Remote, worldwide. You'll build APIs with PHP 8, Vue and PostgreSQL, work with a small friendly team and ship weekly. Send your CV to jobs@acme.dev #hiring #laravel https://t.co/abc";
        $mastodon = "We're hiring a senior Laravel developer at Acme! Remote, worldwide. You'll build APIs with PHP 8, Vue and PostgreSQL, work with a small friendly team and ship weekly. Send your CV to jobs@acme.dev #fedihire #laravel https://acme.dev/jobs";
        $edited = "We're hiring a senior Laravel developer at Acme! Remote, worldwide. You'll build APIs with PHP 8, Vue and PostgreSQL and ship weekly with a friendly small team. Email your CV to jobs@acme.dev";
        $other = 'Globex is looking for a Python data engineer to join our analytics group in Berlin. Strong SQL, Airflow and dbt required, hybrid working, relocation support available for the right person.';

        $h1 = F::simhash($tweet);
        $h2 = F::simhash($mastodon);
        $this->assertTrue($h1 !== null && F::distance($h1, $h2) <= 3);
        $this->assertTrue(F::distance($h1, F::simhash($edited)) < F::distance($h1, F::simhash($other)));
        $this->assertTrue(F::distance($h1, F::simhash($other)) > 10);
        $this->assertSame(null, F::simhash('too short to compare'));
        $this->assertSame(0, F::distance($h1, $h1));
    }
}
