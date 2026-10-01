<?php

/*
|--------------------------------------------------------------------------
| Canonical skill catalog
|--------------------------------------------------------------------------
|
| One entry per skill, written the way it should appear on a resume:
|
|   'Display Name' => [category, [aliases...], optional flags]
|
| Everything that enters the system (resume import, the seeder, the
| /profile form) is run through App\Services\Skills\SkillNormalizer, which:
|
|   - splits compound CV entries ("Node.js (NestJS, Express.js)") into
|     separate skills (Node.js, NestJS, Express.js)
|   - maps spellings to ONE canonical name ("nest.js", "NestJs" -> NestJS)
|   - drops level notes ("(production)", "(personal-project level)") into
|     a separate `qualifier` instead of leaving them in the skill name
|
| Aliases are also what job-description matching searches for, so add
| every spelling a job ad might use. Unknown skills are still kept
| (as typed) — the catalog improves matching, it never blocks a skill.
|
| An alias starting with "~" (e.g. '~next') is INPUT-ONLY: it normalizes
| what a person types ("Next" -> Next.js) but is never searched for in job
| descriptions, because the bare word is too common ("next steps", "express
| interest", "spring").
|
| 'generic' => true marks broad skills that appear in a huge share of job ads
| (Git, REST, SQL, Agile, HTML...). They still show up as matched tags and add
| a little to the score, but they can NEVER qualify a job on their own, so
| "REST + Git" alone doesn't make a Java role look like a Laravel match.
|
| Flags: 'case_sensitive' => true for names that collide with ordinary
| English words (Go), so "Go" matches but "go" does not.
|
| Categories (free text in the DB; keep to these for a consistent CV):
|   backend, frontend, database, apis_integrations, applied_ai,
|   testing_devops, architecture, other
*/

return [

    // ---- backend: languages & frameworks ----
    'PHP' => ['backend', ['php8', 'php 8', 'php7']],
    'Laravel' => ['backend', ['laravel framework']],
    'Symfony' => ['backend', []],
    'Node.js' => ['backend', ['~node', 'nodejs', 'node js']],
    'NestJS' => ['backend', ['nest.js', 'nest js', '~nest']],
    'Express.js' => ['backend', ['~express', 'expressjs', 'express js']],
    'Go' => ['backend', ['golang'], ['case_sensitive' => true]],
    'Python' => ['backend', ['python3']],
    'FastAPI' => ['backend', ['fast api']],
    'Django' => ['backend', []],
    'Flask' => ['backend', []],
    'Java' => ['backend', []],
    'Spring Boot' => ['backend', ['~spring', 'springboot']],
    'C#' => ['backend', ['csharp', 'c sharp']],
    '.NET' => ['backend', ['dotnet', 'dot net', 'asp.net']],
    'Ruby' => ['backend', []],
    'Ruby on Rails' => ['backend', ['~rails', '~ror']],
    'C++' => ['backend', ['cpp']],
    'Rust' => ['backend', []],
    'Elixir' => ['backend', []],

    // ---- frontend ----
    'JavaScript' => ['frontend', ['~js', 'ecmascript', '~es6'], ['generic' => true]],
    'TypeScript' => ['frontend', ['~ts']],
    'React' => ['frontend', ['react.js', 'reactjs', 'react js']],
    'Next.js' => ['frontend', ['nextjs', 'next js', '~next']],
    'Vue.js' => ['frontend', ['vue', 'vuejs', 'vue 3', 'vue3']],
    'Nuxt' => ['frontend', ['nuxt.js', 'nuxtjs']],
    'Angular' => ['frontend', ['angularjs', 'angular.js']],
    'Svelte' => ['frontend', ['sveltekit']],
    'Tailwind CSS' => ['frontend', ['tailwind', 'tailwindcss']],
    'HTML' => ['frontend', ['html5'], ['generic' => true]],
    'CSS' => ['frontend', ['css3'], ['generic' => true]],
    'Inertia.js' => ['frontend', ['inertia', 'inertiajs']],
    'Livewire' => ['frontend', []],
    'Redux' => ['frontend', []],

    // ---- databases ----
    'MySQL' => ['database', ['mariadb']],
    'PostgreSQL' => ['database', ['postgres', 'postgresql', 'psql']],
    'MongoDB' => ['database', ['mongo']],
    'Redis' => ['database', []],
    'SQLite' => ['database', []],
    'Elasticsearch' => ['database', ['elastic search', 'opensearch']],
    'DynamoDB' => ['database', []],
    'SQL' => ['database', [], ['generic' => true]],

    // ---- APIs & integrations ----
    'REST' => ['apis_integrations', ['rest api', 'restful', 'restful api', 'rest apis', 'restful apis'], ['generic' => true]],
    'GraphQL' => ['apis_integrations', ['graph ql', 'gql']],
    'gRPC' => ['apis_integrations', ['grpc']],
    'WebSockets' => ['apis_integrations', ['websocket', 'web sockets']],
    'Webhooks' => ['apis_integrations', ['webhook'], ['generic' => true]],
    'OAuth' => ['apis_integrations', ['oauth2', 'oauth 2.0', 'oauth2.0'], ['generic' => true]],
    'OpenAPI' => ['apis_integrations', ['swagger', 'openapi spec']],
    'RabbitMQ' => ['apis_integrations', ['rabbit mq', 'amqp']],
    'Kafka' => ['apis_integrations', ['apache kafka']],
    'Stripe' => ['apis_integrations', []],
    'Paystack' => ['apis_integrations', []],
    'Flutterwave' => ['apis_integrations', []],
    'WooCommerce' => ['apis_integrations', ['woo commerce']],

    // ---- applied AI ----
    'Gemini API' => ['applied_ai', ['gemini']],
    'Hugging Face' => ['applied_ai', ['huggingface']],
    'TogetherAI' => ['applied_ai', ['together ai', 'together.ai']],
    'Vertex AI' => ['applied_ai', ['vertexai']],
    'Cloudflare Agents' => ['applied_ai', []],
    'OpenAI API' => ['applied_ai', ['openai', 'chatgpt api']],
    'LLM Integration' => ['applied_ai', ['llm', 'llms', 'large language models']],
    'RAG' => ['applied_ai', ['retrieval augmented generation', 'retrieval-augmented generation']],
    'LangChain' => ['applied_ai', []],

    // ---- testing, tooling & devops ----
    'PHPUnit' => ['testing_devops', []],
    'Pest' => ['testing_devops', ['pestphp']],
    'Jest' => ['testing_devops', []],
    'Docker' => ['testing_devops', ['~containers', '~containerization'], ['generic' => true]],
    'Kubernetes' => ['testing_devops', ['k8s']],
    'Git' => ['testing_devops', [], ['generic' => true]],
    'GitHub' => ['testing_devops', [], ['generic' => true]],
    'GitLab' => ['testing_devops', [], ['generic' => true]],
    'CI/CD' => ['testing_devops', ['ci cd', 'cicd', 'ci/cd pipelines', 'continuous integration'], ['generic' => true]],
    'DigitalOcean' => ['testing_devops', ['digital ocean']],
    'AWS' => ['testing_devops', ['amazon web services']],
    'GCP' => ['testing_devops', ['google cloud', 'google cloud platform']],
    'Azure' => ['testing_devops', ['microsoft azure']],
    'Nginx' => ['testing_devops', [], ['generic' => true]],
    'Linux' => ['testing_devops', [], ['generic' => true]],
    'Terraform' => ['testing_devops', []],

    // ---- architecture & practices ----
    'Microservices' => ['architecture', ['microservice', 'micro-services', 'micro services'], ['generic' => true]],
    'OOP' => ['architecture', ['object-oriented programming', 'object oriented programming', 'object-oriented', 'object oriented'], ['generic' => true]],
    'MVC' => ['architecture', [], ['generic' => true]],
    'Dependency Injection' => ['architecture', ['~di', '~ioc'], ['generic' => true]],
    'Event-Driven Architecture' => ['architecture', ['event driven', 'event-driven', '~eda']],
    'Domain-Driven Design' => ['architecture', ['ddd']],
    'Message Queues' => ['architecture', ['message queue', '~queues'], ['generic' => true]],
    'System Design' => ['architecture', [], ['generic' => true]],
    'Agile' => ['architecture', ['scrum'], ['generic' => true]],
];
