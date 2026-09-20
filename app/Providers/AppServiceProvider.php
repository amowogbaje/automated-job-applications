<?php

namespace App\Providers;

use App\Services\AI\AgnesClient;
use App\Services\AI\AiClientInterface;
use App\Services\AI\AnthropicClient;
use App\Services\AI\GroqClient;
use App\Services\Resume\ResumeParser;
use App\Services\Resume\ResumeTailor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Every command/service asks for AiClientInterface, not a concrete class,
        // so switching providers is a one-line .env change (AI_PROVIDER=groq|
        // anthropic|agnes) rather than touching code. Default is "groq" — free,
        // no card required.
        $this->app->bind(AiClientInterface::class, fn () => $this->resolveProvider(
            config('services.ai.provider', 'groq')
        ));

        // The resume pipeline (parsing your PDF/site, and picking which real
        // skills/projects to lead with per job) uses its own provider setting,
        // defaulting to Agnes AI — also free, no card required — independent
        // of whatever AI_PROVIDER is set to for cover letters/emails.
        $this->app->when([ResumeParser::class, ResumeTailor::class])
            ->needs(AiClientInterface::class)
            ->give(fn () => $this->resolveProvider(
                config('services.ai.resume_provider', 'agnes')
            ));
    }

    protected function resolveProvider(string $name): AiClientInterface
    {
        return match ($name) {
            'anthropic' => new AnthropicClient(),
            'agnes' => new AgnesClient(),
            default => new GroqClient(),
        };
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
