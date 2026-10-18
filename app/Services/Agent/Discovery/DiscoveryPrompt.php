<?php

namespace App\Services\Agent\Discovery;

use App\Models\Service;

class DiscoveryPrompt
{
    /**
     * @param  array<string, array{url: string, title: string, text: string}>  $sources
     * @return array<int, array{role: string, content: string}>
     */
    public static function build(string $strategy, ?Service $service, array $sources): array
    {
        $s = Strategies::get($strategy);

        $system = <<<'TXT'
You help a freelance software engineer find COMPANIES worth a closer look. You only work from the SEARCH RESULTS below.

Rules:
1. Never invent companies, websites, quotes or facts. If a result does not clearly name a company, skip it.
2. Pick only results that show a real company with a signal relevant to the engineer's service. Skip listicles, news roundups, job-board home pages, aggregators and generic advice.
3. Each candidate cites exactly one result by its id (for example "S2"), copied exactly.
4. "excerpt" MUST be copied word for word from that result's title or text (max 200 characters). A candidate without a real quote is discarded.
5. Keep fact and inference apart. "finding" says what the result says. "establishes" says what that actually proves. "inference" is your guess and may be wrong.
6. "website_url" is the company's OWN website, only if the result shows it. Otherwise leave it empty. Never guess a URL.
7. Be conservative with confidence. A single snippet is usually "low" or "moderate".
8. Respond with a single JSON object only, no other text.
TXT;

        $svc = $service
            ? "SERVICE\nName: {$service->name}\nSkills: ".implode(', ', array_slice((array) $service->skills_required, 0, 12))
              ."\nTarget businesses: ".implode('; ', array_slice((array) $service->target_businesses, 0, 6))
              ."\nSignals that suggest a need: ".implode('; ', array_slice((array) $service->opportunity_signals, 0, 8))
            : 'SERVICE: (none chosen)';

        $src = [];
        foreach ($sources as $id => $r) {
            $src[] = "[{$id}] {$r['url']}\nTitle: {$r['title']}\n{$r['text']}";
        }

        $user = "STRATEGY: {$s['label']}\nWhat counts: {$s['hint']}\n\n{$svc}\n\nSEARCH RESULTS\n".implode("\n\n---\n\n", $src)."\n\n"
            .<<<'TXT'
Return JSON with exactly this shape (an empty list is fine if nothing qualifies):
{
  "candidates": [
    {
      "source": "S1",
      "company_name": "",
      "website_url": "",
      "title": "short opportunity title",
      "type": "job_posting | rfp | growth_signal | inferred_need",
      "industry": "",
      "country": "2-letter code or empty",
      "finding": "what the result says",
      "establishes": "what that proves",
      "inference": "what it MIGHT mean (a guess)",
      "excerpt": "word-for-word quote",
      "confidence": "low | moderate | high"
    }
  ]
}
TXT;

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]];
    }
}
