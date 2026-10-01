<?php

namespace Tests\Unit;

use App\Services\Skills\SkillNormalizer;
use PHPUnit\Framework\TestCase;

class SkillNormalizerTest extends TestCase
{
    private function n(): SkillNormalizer
    {
        return new SkillNormalizer(require dirname(__DIR__, 2) . '/config/skills.php');
    }

    public function test_compound_cv_entry_is_split_into_atomic_skills(): void
    {
        $this->assertSame(
            ['Node.js', 'NestJS', 'Express.js'],
            $this->n()->names('Node.js (NestJS, Express.js)')
        );
    }

    public function test_nestjs_graphql_microservices_are_three_separate_skills(): void
    {
        $this->assertSame(
            ['NestJS', 'GraphQL', 'Microservices'],
            $this->n()->names('NestJS (GraphQL, microservices)')
        );
        $this->assertSame(
            ['NestJS', 'GraphQL', 'Microservices'],
            $this->n()->names('NestJs, graphql, microservices')
        );
    }

    public function test_version_suffixes_and_level_notes_are_not_part_of_the_name(): void
    {
        $this->assertSame(['PHP', 'OOP', 'MVC'], $this->n()->names('PHP 8 (OOP, MVC)'));

        $go = $this->n()->normalize('Go (production)');
        $this->assertSame('Go', $go[0]['name']);
        $this->assertSame('production', $go[0]['qualifier']);

        $py = $this->n()->normalize('Python/FastAPI (personal-project level)');
        $this->assertSame(['Python', 'FastAPI'], array_column($py, 'name'));
        $this->assertSame('personal-project level', $py[0]['qualifier']);
    }

    public function test_spelling_variants_collapse_and_duplicates_are_removed(): void
    {
        $this->assertSame(['NestJS'], $this->n()->names(['nest.js', 'Nest JS', 'NESTJS', 'NestJs']));
        $this->assertSame(['Vue.js'], $this->n()->names(['Vue', 'vue 3', 'VueJS']));
        $this->assertSame(['CI/CD'], $this->n()->names(['CI/CD', 'ci cd']));
    }

    public function test_unknown_skills_are_kept_as_typed(): void
    {
        $this->assertSame(['Zig', 'Laravel'], $this->n()->names('Zig, laravel'));
        $this->assertSame(['C++'], $this->n()->names('C++'));
    }

    public function test_job_text_matching_is_boundary_aware(): void
    {
        $n = $this->n();
        $this->assertTrue($n->mentionedIn('We use Nest.js and Postgres', 'NestJS'));
        $this->assertTrue($n->mentionedIn('Experience with node js', 'Node.js'));
        $this->assertFalse($n->mentionedIn('We love Google and going places', 'Go'));
        $this->assertTrue($n->mentionedIn('Backend in Go or Rust', 'Go'));
        $this->assertTrue($n->mentionedIn('Backend in golang', 'Go'));
        $this->assertFalse($n->mentionedIn('We use JavaScript daily', 'Java'));
        $this->assertFalse($n->mentionedIn('Express interest by email. Next steps follow.', 'Express.js'));
        $this->assertFalse($n->mentionedIn('Next steps: apply', 'Next.js'));
        $this->assertTrue($n->mentionedIn('React or Vue', 'Vue.js'));
        $this->assertFalse($n->mentionedIn('C++ and C# required', 'C'));
    }
}
