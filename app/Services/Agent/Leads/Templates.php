<?php

namespace App\Services\Agent\Leads;

use App\Models\Setting;

/** The follow-up wording. Editable on the Leads page; falls back to config defaults. */
class Templates
{
    public static function all(): array
    {
        $saved = Setting::get('leads.templates', []);
        $out = [];
        foreach (config('leads.default_templates') as $n => $def) {
            $out[$n] = [
                'subject' => trim((string) ($saved[$n]['subject'] ?? '')) ?: $def['subject'],
                'body' => trim((string) ($saved[$n]['body'] ?? '')) ?: $def['body'],
            ];
        }

        return $out;
    }

    /** @param array<string,string> $vars name, company, service, sender, documents */
    public static function render(string $text, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{'.$k.'}'] = (string) $v;
        }
        $out = strtr($text, $map);
        // an empty {documents} or {name} leaves stray blank lines / double spaces
        $out = preg_replace("/\n{3,}/", "\n\n", $out);

        return trim(str_replace('Hi ,', 'Hi there,', $out));
    }
}
