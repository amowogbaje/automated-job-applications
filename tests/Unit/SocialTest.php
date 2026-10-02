<?php

namespace Tests\Unit;

use App\Services\Social\HiringIntent;
use App\Services\Social\SocialPostValidator as V;
use PHPUnit\Framework\TestCase;

class SocialTest extends TestCase
{
    public function test_hiring_posts_pass_and_job_seekers_do_not(): void
    {
        $this->assertTrue(HiringIntent::looksLikeJobPost("We're hiring a senior Laravel developer! Remote. Send your CV to jobs@acme.dev"));
        $this->assertTrue(HiringIntent::looksLikeJobPost('Acme is hiring: PHP backend engineer, apply here https://acme.dev/jobs #hiring'));
        $this->assertTrue(HiringIntent::looksLikeJobPost('Looking for a Vue.js developer to join our team. DM me.'));

        $this->assertFalse(HiringIntent::looksLikeJobPost("I'm a Laravel developer open to work, #opentowork, DM me your offers"));
        $this->assertFalse(HiringIntent::looksLikeJobPost("I'm a full stack developer looking for a remote role. Hire me!"));
        $this->assertFalse(HiringIntent::looksLikeJobPost('Just shipped a new Laravel package, it is great, read the docs'));
        $this->assertFalse(HiringIntent::looksLikeJobPost('short'));
    }

    public function test_email_must_appear_verbatim_in_the_post(): void
    {
        $text = 'We are hiring a PHP dev. Email your CV to careers@acme.dev with subject "PHP Dev".';
        $ok = V::validate(['is_job_post' => true, 'confidence' => 0.9, 'apply_email' => 'careers@acme.dev'], $text);
        $this->assertSame('careers@acme.dev', $ok['apply_email']);

        // hallucinated / injected address is dropped
        $bad = V::validate(['is_job_post' => true, 'confidence' => 0.9, 'apply_email' => 'attacker@evil.com'], $text);
        $this->assertSame(null, $bad['apply_email']);
    }

    public function test_obfuscated_email_in_post_is_recognised(): void
    {
        $text = 'Hiring Laravel dev. Apply: jobs [at] acme [dot] io';
        $r = V::validate(['is_job_post' => true, 'confidence' => 0.8, 'apply_email' => 'jobs@acme.io'], $text);
        $this->assertSame('jobs@acme.io', $r['apply_email']);
        $this->assertSame(['jobs@acme.io'], V::extractEmails($text));
    }

    public function test_apply_url_must_come_from_the_post(): void
    {
        $text = 'We are hiring! Details: https://acme.dev/careers/php';
        $ok = V::validate(['is_job_post' => true, 'confidence' => 0.9, 'apply_url' => 'https://acme.dev/careers/php'], $text);
        $this->assertSame('https://acme.dev/careers/php', $ok['apply_url']);

        $bad = V::validate(['is_job_post' => true, 'confidence' => 0.9, 'apply_url' => 'https://phish.example/login'], $text);
        $this->assertSame(null, $bad['apply_url']);

        // expanded t.co style: text has a short link, platform entity has the real one
        $viaEntity = V::validate(['is_job_post' => true, 'confidence' => 0.9, 'apply_url' => 'https://acme.dev/careers/php'], 'Hiring! https://t.co/abc', ['https://acme.dev/careers/php']);
        $this->assertSame('https://acme.dev/careers/php', $viaEntity['apply_url']);
    }

    public function test_subject_is_sanitised_against_header_injection(): void
    {
        $this->assertSame('PHP Dev Bcc: x@y.z', V::cleanSubject("PHP Dev\r\nBcc: x@y.z"));
        $this->assertSame(120, mb_strlen(V::cleanSubject(str_repeat('a', 300))));
        $this->assertSame(null, V::cleanSubject("  \n "));
    }

    public function test_extra_demands_force_manual_and_low_confidence_is_not_a_job(): void
    {
        $text = 'We are hiring! Email cv@acme.dev with your CV and a 2 min video and salary expectations';
        $r = V::validate(['is_job_post' => true, 'confidence' => 0.9, 'apply_email' => 'cv@acme.dev', 'required_materials' => ['CV', 'video', 'salary expectations']], $text);
        $this->assertTrue($r['needs_manual_input']);

        $fine = V::validate(['is_job_post' => true, 'confidence' => 0.9, 'apply_email' => 'cv@acme.dev', 'required_materials' => ['CV', 'cover letter', 'portfolio']], $text);
        $this->assertFalse($fine['needs_manual_input']);

        $low = V::validate(['is_job_post' => true, 'confidence' => 0.3, 'apply_email' => 'cv@acme.dev'], $text);
        $this->assertFalse($low['is_job']);
        $this->assertSame(null, $low['apply_email']);
    }

    public function test_dm_instruction_cancels_email_apply(): void
    {
        $text = 'Hiring PHP dev, DM me. (team@acme.dev for press)';
        $r = V::validate(['is_job_post' => true, 'confidence' => 0.9, 'apply_email' => 'team@acme.dev', 'application_instructions' => 'DM me on X'], $text);
        $this->assertSame(null, $r['apply_email']);
    }

    public function test_x_queries_fit_the_length_limit_and_skip_unsearchable_skills(): void
    {
        $skills = ['Laravel', 'PHP', 'Vue.js', 'Node.js', 'NestJS', 'GraphQL', 'PostgreSQL', 'TypeScript', 'React', 'Next.js', 'Docker', 'C++', 'Tailwind CSS', 'Redis', 'MySQL', 'MongoDB', 'Python', 'Django', 'FastAPI', 'Kubernetes', 'AWS', 'Terraform'];
        $queries = \App\Services\Social\XSource::buildQueries($skills, 'en');

        $this->assertTrue(count($queries) >= 1);
        foreach ($queries as $q) {
            $this->assertTrue(strlen($q) <= 512);
            $this->assertTrue(str_contains($q, '-is:retweet'));
            $this->assertFalse(str_contains($q, 'C++'));
        }
        $all = implode(' ', $queries);
        $this->assertTrue(str_contains($all, 'Laravel') && str_contains($all, '"Vue.js"') && str_contains($all, '"Tailwind CSS"'));
    }

    public function test_hashtags_from_skills(): void
    {
        $this->assertSame('nodejs', \App\Services\Social\SkillTags::hashtag('Node.js'));
        $this->assertSame('vuejs', \App\Services\Social\SkillTags::hashtag('Vue.js'));
        $this->assertSame('cpp', \App\Services\Social\SkillTags::hashtag('C++'));
        $this->assertSame('tailwindcss', \App\Services\Social\SkillTags::hashtag('Tailwind CSS'));
    }

    public function test_hn_html_is_flattened(): void
    {
        $html = 'Acme | Backend Eng | REMOTE<p>We use Laravel &amp; Vue.<p>Email <a href="mailto:jobs@acme.dev">jobs@acme.dev</a>';
        $text = \App\Services\Social\HackerNewsSource::htmlToText($html);
        $this->assertTrue(str_contains($text, "REMOTE\nWe use Laravel & Vue."));
        $this->assertTrue(str_contains($text, 'jobs@acme.dev'));
    }

    public function test_bluesky_urls_links_and_queries(): void
    {
        $this->assertSame('https://bsky.app/profile/alice.bsky.social/post/3kabc', \App\Services\Social\BlueskySource::postUrl('at://did:plc:xyz/app.bsky.feed.post/3kabc', 'alice.bsky.social'));
        $this->assertSame('https://bsky.app/profile/did:plc:xyz/post/3kabc', \App\Services\Social\BlueskySource::postUrl('at://did:plc:xyz/app.bsky.feed.post/3kabc', null));

        $record = ['facets' => [['features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => 'https://acme.dev/jobs/php']]], ['features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => 'hiring']]]]];
        $this->assertSame(['https://acme.dev/jobs/php'], \App\Services\Social\BlueskySource::facetLinks($record));

        $q = \App\Services\Social\BlueskySource::buildQueries(['Laravel', 'C++', 'Tailwind CSS', 'PHP'], 6);
        $this->assertSame(['hiring Laravel', 'hiring "Tailwind CSS"', 'hiring PHP'], $q);
        $this->assertSame(2, count(\App\Services\Social\BlueskySource::buildQueries(['a1', 'b2', 'c3'], 2)));
    }

    public function test_reddit_title_rules_and_link_extraction(): void
    {
        $this->assertTrue(\App\Services\Social\RedditSource::acceptTitle('[Hiring] Laravel developer, remote', 'forhire'));
        $this->assertFalse(\App\Services\Social\RedditSource::acceptTitle('[For Hire] Laravel developer available', 'forhire'));
        $this->assertFalse(\App\Services\Social\RedditSource::acceptTitle('Looking for advice on queues', 'forhire'));
        $this->assertFalse(\App\Services\Social\RedditSource::acceptTitle('[Task] fix my site', 'forhire'));
        $this->assertTrue(\App\Services\Social\RedditSource::acceptTitle('We are hiring a PHP dev', 'laravel'));

        $links = \App\Services\Social\RedditSource::links('Apply at https://acme.dev/jobs/1, or see (https://acme.dev/about).');
        $this->assertSame(['https://acme.dev/jobs/1', 'https://acme.dev/about'], $links);
    }
}

