<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Backend and API Development',
                'description' => 'Laravel/PHP, REST and GraphQL APIs, NestJS/Node.js backends, database design, payment integrations.',
                'skills_required' => ['Laravel', 'PHP', 'REST', 'GraphQL', 'NestJS', 'Node.js', 'MySQL', 'PostgreSQL', 'Payments'],
                'search_keywords' => ['Laravel developer', 'API development', 'backend developer', 'payment integration'],
                'preferred_engagement' => 'any',
            ],
            [
                'name' => 'Full-Stack Web Development',
                'description' => 'Business websites and web apps, Laravel apps, Vue/Livewire, custom dashboards, data-driven platforms.',
                'skills_required' => ['Laravel', 'Vue', 'Livewire', 'React', 'TypeScript', 'Tailwind'],
                'search_keywords' => ['full-stack developer', 'web application development', 'custom dashboard'],
                'preferred_engagement' => 'project',
            ],
            [
                'name' => 'AI Integration and Automation',
                'description' => 'AI API integration, AI-powered business tools, workflow automation, AI content generation, AI-assisted research.',
                'skills_required' => ['LLM APIs', 'Prompt design', 'Workflow automation', 'Queues', 'Laravel'],
                'search_keywords' => ['AI integration', 'workflow automation', 'AI content generation'],
                'preferred_engagement' => 'consulting',
            ],
            [
                'name' => 'Custom Business Software',
                'description' => 'Internal tools, booking and management systems, process digitisation, replacing manual work.',
                'skills_required' => ['Laravel', 'Business process analysis', 'Dashboards'],
                'search_keywords' => ['internal tools', 'booking system', 'business process automation'],
                'preferred_engagement' => 'project',
            ],
            [
                'name' => 'Technical Problem-Solving and Integration',
                'description' => 'Third-party API integrations, connecting systems, backend troubleshooting, performance, payments.',
                'skills_required' => ['API integration', 'Debugging', 'Performance tuning'],
                'search_keywords' => ['API integration', 'backend troubleshooting', 'performance optimisation'],
                'preferred_engagement' => 'contract',
            ],
            [
                'name' => 'Software Engineering Employment',
                'description' => 'Remote backend, Laravel, full-stack and AI integration engineering roles.',
                'skills_required' => ['Laravel', 'PHP', 'APIs', 'Full-stack', 'AI integration'],
                'search_keywords' => ['remote Laravel developer', 'backend engineer remote', 'AI integration engineer'],
                'preferred_engagement' => 'employment',
                'is_employment' => true,
            ],
        ];

        foreach ($services as $i => $data) {
            Service::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                $data + ['sort_order' => $i]
            );
        }

        Setting::put('score_weights', [
            'need_evidence' => 25,
            'skills_match' => 25,
            'active_signal' => 15,
            'commercial_value' => 15,
            'contact_channel' => 10,
            'portfolio_strength' => 10,
        ]);
        Setting::put('priority_thresholds', ['A' => 75, 'B' => 55]);
        Setting::put('agent_limits', ['research_step_limit' => 12]);
    }
}
