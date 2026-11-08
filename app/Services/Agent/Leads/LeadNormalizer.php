<?php

namespace App\Services\Agent\Leads;

use App\Services\Agent\Ai\AiManager;

/** Turns raw pasted text (notes, a WhatsApp list, a messy sheet) into upload-format rows with AI. */
class LeadNormalizer
{
    public function __construct(private AiManager $ai) {}

    /** @return array{rows: array<int,array<string,string>>, source: string} */
    public function normalize(string $raw): array
    {
        $raw = mb_substr(trim($raw), 0, (int) config('leads.max_ai_chars', 14000));
        $res = $this->ai->complete(
            $this->prompt($raw), true, 4000,
            fn (array $d) => isset($d['leads']) && is_array($d['leads']) ? true : 'Missing "leads" list.'
        );

        return ['rows' => self::clean($res->data['leads'], $raw), 'source' => $res->provider];
    }

    private function prompt(string $raw): array
    {
        $fields = implode(', ', LeadCsv::FIELDS);
        $system = <<<TXT
You organise a salesperson's raw notes into a list of sales leads. Work only from the text given.

Rules:
1. One lead per business or person. Never invent anything: if a field is not in the text, use an empty string. Never guess an email, phone number or website.
2. Copy emails, phone numbers and website/social links exactly as written.
3. "company" is required: the business name (or the person's name if only a person is given).
4. Keep any other useful detail in "notes", short.
5. Respond with one JSON object: {"leads":[{{$fields}}]}. All values are strings. No other text.
TXT;

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $raw]];
    }

    /** Keeps only known fields, and drops phones/emails the text never contained (anti-hallucination). */
    public static function clean(array $leads, string $raw): array
    {
        $digits = preg_replace('/\D+/', '', $raw);
        $lower = mb_strtolower($raw);
        $out = [];
        foreach ($leads as $l) {
            if (! is_array($l)) {
                continue;
            }
            $row = [];
            foreach (LeadCsv::FIELDS as $f) {
                $row[$f] = trim((string) ($l[$f] ?? ''));
            }
            if ($row['email'] !== '' && ! str_contains($lower, mb_strtolower($row['email']))) {
                $row['email'] = '';
            }
            if ($row['phone'] !== '') {
                $pd = preg_replace('/\D+/', '', $row['phone']);
                if ($pd === '' || ! str_contains($digits, ltrim($pd, '0'))) {
                    $row['phone'] = '';
                }
            }
            if ($row['company'] === '') {
                $row['company'] = $row['contact_name'];
            }
            if ($row['company'] !== '') {
                $out[] = $row;
            }
        }

        return $out;
    }
}
