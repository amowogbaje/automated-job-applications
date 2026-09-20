<?php

namespace Database\Seeders;

use App\Models\Resume;
use Illuminate\Database\Seeder;

class ResumeSeeder extends Seeder
{
    /**
     * Seeds the resume you already gave us directly into the database, as
     * structured rows — no AI call, no API key needed, and you won't have
     * to upload the PDF again. It's created with user_id = null — nothing
     * attaches it to an account automatically. After you register your own
     * account at /register, run `php artisan resume:claim your@email.com`
     * to attach it (see app/Console/Commands/ClaimResume.php).
     */
    public function run(): void
    {
        if (Resume::where('full_name', 'Gideon Amowogbaje')->exists()) {
            return; // idempotent — don't duplicate on repeated db:seed runs
        }

        $resume = Resume::create([
            'user_id' => null,
            'source' => 'pdf_upload',
            'is_active' => true,
            'full_name' => 'Gideon Amowogbaje',
            'headline' => 'Full Stack / Backend Developer — Laravel, Node.js, Go, React/Next.js',
            'email' => 'amowogbajegideon@gmail.com',
            'phone' => '+234 702 630 5945',
            'location' => 'Ile-Ife, Osun State, Nigeria (open to fully remote)',
            'website_url' => 'amowogbaje.com',
            'linkedin_url' => 'linkedin.com/in/amowogbaje',
            'github_url' => 'github.com/amowogbaje',
            'summary' => 'Full stack developer with 8+ years of professional experience across '
                . 'Laravel/PHP, Node.js/NestJS, and — currently — Go, plus React, Next.js, and Vue.js '
                . 'on the frontend. Track record spans production integration work (webhooks, OAuth '
                . 'reconciliation, multi-gateway payments), applied AI integration (Gemini, Hugging '
                . 'Face, TogetherAI, Vertex AI), and end-to-end ownership of features from Figma to '
                . 'deployment. Comfortable in fully remote, cross-timezone teams.',
        ]);

        $this->seedExperiences($resume);
        $this->seedEducation($resume);
        $this->seedSkills($resume);
        $this->seedProjects($resume);
        $this->seedCertifications($resume);
    }

    private function seedExperiences(Resume $resume): void
    {
        $rows = [
            [
                'job_title' => 'Backend Developer (Laravel → Go)',
                'company' => 'Dayout',
                'location' => 'United States',
                'start_date' => '2024-06-01',
                'end_date' => null,
                'is_current' => true,
                'bullets' => [
                    "Currently rewriting core service endpoints — activities, notifications, emails, and comments — from Laravel to Go as part of the company's backend migration.",
                    'Built a Push Notification Service from scratch, including three related endpoints, as part of a 3-person backend team.',
                    'Led a research study comparing Laravel Eloquent against raw SQL that resulted in company-wide adoption of Eloquent; built all resulting database migrations and refactored the codebase around Dependency Injection and Eloquent Models.',
                    'Contributed to the OpenAPI documentation used by the engineering team.',
                ],
            ],
            [
                'job_title' => 'Full Stack Developer',
                'company' => 'KOC',
                'location' => 'Nigeria',
                'start_date' => '2025-09-01',
                'end_date' => '2026-05-01',
                'is_current' => false,
                'bullets' => [
                    'Rescued a stalled project, turning a Figma design into a working, production-ready product under a tight deadline.',
                    'Integrated transactional email and WooCommerce syncing, and built role-based permissions using Laravel Policies and Gates.',
                    'Built a Notion-style, block-based blogging interface with Editor.js.',
                ],
            ],
            [
                'job_title' => 'Backend Developer',
                'company' => 'Africred',
                'location' => 'Nigeria',
                'start_date' => '2025-02-01',
                'end_date' => '2025-09-01',
                'is_current' => false,
                'bullets' => [
                    'Built webhook ingestion for Facebook Instant Forms into a production CRM, syncing prospective-client data for sales follow-up.',
                    'Automated OAuth access-token tracking and refresh via a scheduled Laravel command.',
                ],
            ],
            [
                'job_title' => 'Backend Developer (NestJS, GraphQL)',
                'company' => 'Televerse',
                'location' => 'Nigeria',
                'start_date' => '2023-04-01',
                'end_date' => '2024-06-01',
                'is_current' => false,
                'bullets' => [
                    'Developed optimized search APIs for the ReachMe app using NestJS, GraphQL, MongoDB, Redis, and RabbitMQ.',
                    'Designed and implemented a simulated payment API with GraphQL, including secure coin-gifting endpoints.',
                    'Collaborated closely with Flutter developers to integrate backend features into a mobile app.',
                ],
            ],
            [
                'job_title' => 'Full Stack Developer',
                'company' => 'Grace Filled Academy',
                'location' => 'Nigeria',
                'start_date' => '2023-04-01',
                'end_date' => '2024-06-01',
                'is_current' => false,
                'bullets' => [
                    'Architected the full MySQL database structure from scratch for a school-management system.',
                    'Used Laravel factories and seeders for full integration test coverage under real data volumes.',
                ],
            ],
            [
                'job_title' => 'Full Stack Developer',
                'company' => 'Thinkshifts',
                'location' => 'United Kingdom',
                'start_date' => '2021-11-01',
                'end_date' => '2024-06-01',
                'is_current' => false,
                'bullets' => [
                    'Built a real-estate web application on a Laravel + Vue.js full stack, handling property data at scale.',
                    'Worked as a Vue.js developer on the Carelly app for a client engagement.',
                ],
            ],
            [
                'job_title' => 'Team Lead / Software Developer',
                'company' => 'Spokesman Communication Ministries',
                'location' => 'Nigeria',
                'start_date' => '2017-05-01',
                'end_date' => '2021-08-01',
                'is_current' => false,
                'bullets' => [
                    'Built the ministry website and International Leadership Conference site end-to-end (Next.js frontend, Laravel backend, Filament admin panel).',
                    'Migrated a legacy WordPress site to a custom Laravel CMS with zero data loss.',
                ],
            ],
            [
                'job_title' => 'Software Developer',
                'company' => 'iQube Labs',
                'location' => 'Nigeria',
                'start_date' => '2015-01-01',
                'end_date' => '2018-11-01',
                'is_current' => false,
                'bullets' => [
                    'Advanced TradeWorkflow, a cargo-shipping application, from 40% to 80% complete using CakePHP.',
                    'Built and managed RedBank, a Laravel application connecting blood donors with people in need.',
                ],
            ],
        ];

        foreach ($rows as $i => $row) {
            $resume->experiences()->create($row + ['sort_order' => $i]);
        }
    }

    private function seedEducation(Resume $resume): void
    {
        $resume->education()->create([
            'institution' => 'Obafemi Awolowo University, Ile-Ife',
            'degree' => 'B.Sc.',
            'field' => 'Computer Engineering',
            'sort_order' => 0,
        ]);
    }

    private function seedSkills(Resume $resume): void
    {
        $categories = [
            'backend' => ['Laravel', 'PHP 8 (OOP, MVC)', 'Node.js (NestJS, Express.js)', 'Go (production)', 'Python/FastAPI (personal-project level)'],
            'frontend' => ['React', 'Next.js', 'TypeScript', 'JavaScript', 'Vue.js', 'Tailwind CSS'],
            'database' => ['MySQL', 'PostgreSQL', 'MongoDB', 'Redis'],
            'apis_integrations' => ['REST', 'GraphQL', 'webhooks', 'OAuth', 'RabbitMQ', 'Stripe', 'Paystack', 'Flutterwave', 'WooCommerce'],
            'applied_ai' => ['Gemini API', 'Hugging Face', 'TogetherAI', 'Vertex AI', 'Cloudflare Agents'],
            'testing_devops' => ['PHPUnit', 'Pest', 'Docker', 'Git', 'GitHub', 'GitLab', 'CI/CD', 'DigitalOcean', 'Nginx', 'Linux'],
        ];

        $order = 0;
        foreach ($categories as $category => $names) {
            foreach ($names as $name) {
                $resume->skills()->create([
                    'category' => $category,
                    'name' => $name,
                    'sort_order' => $order++,
                ]);
            }
        }
    }

    private function seedProjects(Resume $resume): void
    {
        $rows = [
            [
                'name' => 'Storyverse',
                'description' => 'Built a subscription platform integrating Stripe, Paystack, and Flutterwave with per-country pricing.',
                'tech_stack' => ['Laravel', 'React', 'MySQL'],
                'url' => 'storyverse.amowogbaje.com',
            ],
            [
                'name' => 'CraftProfessor Story Engine',
                'description' => "Built a fully automated AI content-generation and publishing pipeline; grew a Pinterest account's monthly views from 100 to 30.6K in 30 days with zero manual posting.",
                'tech_stack' => ['Laravel', 'Vertex AI', 'Gemini', 'Cloudflare Agents', 'TogetherAI'],
                'url' => 'craftprofessor.amowogbaje.com',
            ],
            [
                'name' => 'database-repository (Packagist package)',
                'description' => "Published a Composer package automating database backups tied to Laravel's migration lifecycle.",
                'tech_stack' => ['PHP', 'Laravel'],
                'url' => 'packagist.org/packages/amowogbaje/database-repository',
            ],
        ];

        foreach ($rows as $i => $row) {
            $resume->projects()->create($row + ['sort_order' => $i]);
        }
    }

    private function seedCertifications(Resume $resume): void
    {
        $names = [
            'PHP Certificate',
            'SQL Advanced',
            'Web Mobile Development',
            'HNG Finalist Certificate',
            'JavaScript (Intermediate)',
        ];

        foreach ($names as $i => $name) {
            $resume->certifications()->create(['name' => $name, 'sort_order' => $i]);
        }
    }
}
