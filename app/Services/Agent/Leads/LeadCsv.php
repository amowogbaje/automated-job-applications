<?php

namespace App\Services\Agent\Leads;

/** The lead upload format, a template for it, and a forgiving CSV reader. */
class LeadCsv
{
    public const FIELDS = ['company', 'contact_name', 'email', 'phone', 'website', 'service', 'city', 'country', 'industry', 'notes', 'source'];

    private const ALIASES = [
        'company' => ['company', 'company name', 'business', 'business name', 'organisation', 'organization', 'name of business', 'firm'],
        'contact_name' => ['contact name', 'contact', 'contact person', 'person', 'full name', 'name', 'owner'],
        'email' => ['email', 'e-mail', 'email address', 'e mail', 'mail'],
        'phone' => ['phone', 'phone number', 'mobile', 'whatsapp', 'tel', 'telephone', 'number', 'phone no'],
        'website' => ['website', 'url', 'site', 'web', 'link', 'instagram', 'social'],
        'service' => ['service', 'service interested', 'interest', 'product'],
        'city' => ['city', 'town', 'location'],
        'country' => ['country'],
        'industry' => ['industry', 'sector', 'type', 'category'],
        'notes' => ['notes', 'note', 'comment', 'comments', 'description', 'details'],
        'source' => ['source', 'found at', 'where found', 'origin'],
    ];

    public static function template(): string
    {
        $rows = [
            self::FIELDS,
            ['Bright Hair Studio', 'Ada Obi', 'ada@brighthair.example', '08031234567', 'https://instagram.com/brighthair', 'Website design', 'Lagos', 'NG', 'Hair salon', 'Met at the market; wants online booking', 'Referral'],
            ['Okafor Motors', '', '', '+234 802 000 0000', '', '', 'Enugu', 'NG', 'Auto mechanic', 'Phone only', 'Walk-in'],
        ];

        return implode("\n", array_map(fn ($r) => implode(',', array_map(fn ($c) => '"'.str_replace('"', '""', $c).'"', $r)), $rows))."\n";
    }

    /** @return array<int, array<string,string>> rows keyed by FIELDS */
    public static function parse(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $firstLine = strtok($text, "\n") ?: '';
        $delim = ',';
        foreach ([';', "\t"] as $d) {
            if (substr_count($firstLine, $d) > substr_count($firstLine, $delim)) {
                $delim = $d;
            }
        }

        $lines = [];
        $h = fopen('php://memory', 'r+');
        fwrite($h, $text);
        rewind($h);
        while (($row = fgetcsv($h, 0, $delim, '"', '')) !== false) {
            if ($row === [null] || ! array_filter($row, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }
            $lines[] = array_map(fn ($c) => trim((string) $c), $row);
        }
        fclose($h);
        if (count($lines) < 2) {
            return [];
        }

        $map = self::mapHeaders($lines[0]);
        if (! in_array('company', $map, true)) {
            // A sheet with only a "name" column: treat that as the business/person name.
            $i = array_search('contact_name', $map, true);
            if ($i === false || in_array('contact_name', array_diff_key($map, [$i => 1]), true)) {
                return [];
            }
            $map[$i] = 'company';
        }
        $out = [];
        foreach (array_slice($lines, 1) as $line) {
            $row = array_fill_keys(self::FIELDS, '');
            foreach ($map as $i => $field) {
                if ($field && isset($line[$i]) && $row[$field] === '') {
                    $row[$field] = $line[$i];
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @return array<int, ?string> column index => field */
    public static function mapHeaders(array $header): array
    {
        $map = [];
        $used = [];
        foreach ($header as $i => $h) {
            $h = strtolower(trim(preg_replace('/[_\-]+/', ' ', (string) $h)));
            $map[$i] = null;
            foreach (self::ALIASES as $field => $names) {
                if (! isset($used[$field]) && in_array($h, $names, true)) {
                    $map[$i] = $field;
                    $used[$field] = true;
                    break;
                }
            }
        }

        return $map;
    }
}
