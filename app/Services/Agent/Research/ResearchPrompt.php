<?php

namespace App\Services\Agent\Research;

class ResearchPrompt
{
    public static function build(array $company, array $opportunity, array $services, array $sources): array
    {
        $system = <<<'TXT'
You are a careful business-research analyst helping a freelance software engineer decide whether a company is worth contacting. You work ONLY from the SOURCES provided below.

Rules:
1. Never invent facts, companies, people, contact details, URLs or quotes. If the sources do not show something, say so.
2. Every evidence item cites exactly one source by its id (for example "S2"), copied exactly.
3. Keep fact and inference apart. "finding" says what the source says. "establishes" says what that actually proves. "inference" is your guess about what it might mean and may be wrong.
4. "excerpt" must be copied word for word from that source (max 200 characters), or left empty.
5. An old website, a missing feature or a vague growth signal is NOT proof of a need. Do not present it as one.
6. If evidence is thin, say so: give low scores and low confidence.
7. Choose the one service that is the strongest match for a first approach; others are secondary.
8. Respond with a single JSON object only, no other text.
TXT;

        $svc = [];
        foreach ($services as $s) {
            $svc[] = "- id {$s['id']}: {$s['name']} | skills: ".implode(', ', array_slice($s['skills'], 0, 12)).' | signals: '.implode('; ', array_slice($s['signals'], 0, 8));
        }

        $src = [];
        foreach ($sources as $id => $s) {
            $src[] = "[{$id}] {$s['url']}\nTitle: {$s['title']}\n{$s['text']}";
        }

        $user = "COMPANY\nName: {$company['name']}\nWebsite: {$company['website']}\nIndustry: {$company['industry']}\nCountry: {$company['country']}\n\n"
            ."OPPORTUNITY\nTitle: {$opportunity['title']}\nType: {$opportunity['type']}\nNotes: {$opportunity['notes']}\n\n"
            ."MY SERVICES (match against these)\n".implode("\n", $svc)."\n\n"
            ."SOURCES\n".implode("\n\n---\n\n", $src)."\n\n"
            .<<<'TXT'
Return JSON with exactly this shape:
{
  "evidence": [
    {"source": "S1", "kind": "website|job_listing|news|funding|directory|rfp|other", "finding": "", "establishes": "", "inference": "", "confidence": "low|moderate|high", "excerpt": ""}
  ],
  "business_need": "facts the sources show about a need, or empty string",
  "inferred_opportunity": "a plausible way my services could help, clearly an inference, or empty string",
  "primary_service_id": 0,
  "matched_services": [{"service_id": 0, "match_strength": 0, "rationale": ""}],
  "scores": {
    "need_evidence": {"score": 0, "reasoning": ""},
    "skills_match": {"score": 0, "reasoning": ""},
    "active_signal": {"score": 0, "reasoning": ""},
    "commercial_value": {"score": 0, "reasoning": ""}
  },
  "confidence": "low|moderate|high",
  "recommended_action": "",
  "should_reject": false,
  "reject_reason": ""
}
Scores are integers 0-100. At most 6 evidence items. Use null for primary_service_id if nothing fits.
TXT;

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]];
    }
}
