<?php

namespace App\Services\Resume;

use Illuminate\Support\Facades\Http;

class WebsiteResumeScraper
{
    /**
     * Fetch a URL and return cleaned, whitespace-collapsed plain text.
     * Supplementary context only — see the "never invent" instructions in
     * ImportResume's AI prompt. This never becomes a source of facts on its
     * own, only tone/positioning that must already be backed by the resume.
     *
     * @throws \RuntimeException on network failure or empty response
     */
    public function scrape(string $url, int $charLimit = 8000): string
    {
        if (! str_starts_with($url, 'http')) {
            $url = "https://{$url}";
        }

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; JobFeedAggregator/1.0; +resume-import)',
        ])->timeout(15)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Could not fetch {$url} (HTTP {$response->status()}).");
        }

        $html = $response->body();

        // Strip script/style blocks entirely, then all remaining tags.
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n+/', "\n", $text);
        $text = trim($text);

        if ($text === '') {
            throw new \RuntimeException("Fetched {$url} but found no readable text on the page.");
        }

        return mb_substr($text, 0, $charLimit);
    }
}
