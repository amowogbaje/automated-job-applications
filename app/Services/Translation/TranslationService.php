<?php

namespace App\Services\Translation;

use App\Models\JobListing;
use Illuminate\Support\Str;

/**
 * Translates one listing (title + the first N chars of the description) using the
 * configured drivers in order. Each piece of text goes through the first driver
 * that is configured, has budget left and succeeds; MyMemory gets small chunks.
 */
class TranslationService
{
    /** @var array<string, TranslationDriver> */
    private array $drivers = [];

    public function __construct(
        DeepLDriver $deepl,
        AzureTranslatorDriver $azure,
        LibreTranslateDriver $libre,
        MyMemoryDriver $myMemory,
        AiTranslationDriver $ai,
    ) {
        $all = [$deepl->name() => $deepl, $azure->name() => $azure, $libre->name() => $libre, $myMemory->name() => $myMemory, $ai->name() => $ai];

        foreach (config('translation.drivers', []) as $name) {
            if (isset($all[$name]) && $all[$name]->available()) {
                $this->drivers[$name] = $all[$name];
            }
        }
    }

    public function hasDrivers(): bool
    {
        return $this->drivers !== [];
    }

    /** @return array<string, array{remaining:int|string, paused_until:?string}> in the order they're tried */
    public function status(): array
    {
        $out = [];
        foreach ($this->drivers as $name => $driver) {
            $remaining = $driver->remainingBudget();
            $out[$name] = [
                'remaining' => $remaining === PHP_INT_MAX ? 'unlimited' : $remaining,
                'paused_until' => BudgetTracker::pausedUntil($name),
            ];
        }

        return $out;
    }

    /**
     * @return array{title:string, description:?string, provider:string}|null  null = nothing could translate it
     */
    public function translateListing(JobListing $job): ?array
    {
        $to = (string) config('translation.target', 'en');
        $provider = null;

        $title = $this->translateText((string) $job->title, $to, $provider);
        if ($title === null) {
            return null;
        }

        $description = null;
        $source = trim((string) $job->description);
        if ($source !== '') {
            $source = Str::limit($source, (int) config('translation.max_chars_per_job', 3500), '');
            $description = $this->translateText($source, $to, $provider);
            if ($description === null) {
                return null; // partial translations would be misleading; retry later
            }
        }

        return ['title' => trim($title), 'description' => $description ? trim($description) : null, 'provider' => (string) $provider];
    }

    private function translateText(string $text, string $to, ?string &$provider): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        foreach ($this->drivers as $name => $driver) {
            $chunks = $name === 'mymemory'
                ? TextChunker::chunk($text, MyMemoryDriver::MAX_BYTES)
                : TextChunker::chunk($text, $name === 'ai' ? 3000 : 20000);

            if ($driver->remainingBudget() < mb_strlen($text)) {
                continue; // would blow this driver's free quota; try the next one
            }

            $parts = [];
            foreach ($chunks as $chunk) {
                $out = $driver->translate($chunk, $to);
                if ($out === null) {
                    continue 2; // this driver failed mid-way; redo the whole text with the next one
                }
                $parts[] = $out;
            }

            $provider = $name;

            return implode($name === 'mymemory' ? ' ' : "\n", $parts);
        }

        return null;
    }
}
