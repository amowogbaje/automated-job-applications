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
        $prospecting = $strategy === 'prospecting';

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

        if ($prospecting) {
            $system .= "\n\nThis is PROSPECTING. The organisations do not need to say they want software or help; you are finding real, non-technical organisations (not software companies, agencies, job boards or directories) that the engineer could approach with a proposal. Rule 2 still applies: the result must clearly name the organisation. In \"inference\" give one plausible way the service could help them, labelled as a guess. Results may be public Instagram, Facebook, LinkedIn or X pages of small businesses or independent professionals (barbers, mechanics, stylists, engineers and the like); for those, \"company_name\" is the trading or professional name shown, and \"website_url\" stays empty unless the result shows a website. Do not judge website quality from a snippet. Set \"is_technology_company\" to true for any software, IT or digital-agency business.\n"
                .'"contacts" lists ways to reach the organisation ONLY if they are written in that result: type "email", "phone", "whatsapp" or "contact_form" (a URL on the same site), with the value copied exactly. If the result shows none, return an empty list. Never guess or construct an address or number.';
        }

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
      "confidence": "low | moderate | high",
      "work_mode": "remote | hybrid | onsite, only if the result says so, else empty",
      "is_technology_company": false,
      "contacts": [{"type": "email | phone | whatsapp | contact_form", "value": "", "label": ""}]
    }
  ]
}
TXT;

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]];
    }
}
