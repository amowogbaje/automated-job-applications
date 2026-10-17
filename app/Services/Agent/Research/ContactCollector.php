<?php

namespace App\Services\Agent\Research;

/**
 * Builds the list of contact routes from pages we actually read. Only details the company published
 * are returned; each carries the page it came from. Nothing is guessed or constructed.
 */
class ContactCollector
{
    /**
     * @param  array<int, array>  $pages  HtmlExtractor output plus 'url'
     * @return array<int, array{type: string, value: string, source_url: string, is_public_business_channel: bool, label: ?string}>
     */
    public static function collect(array $pages, string $companyWebsite): array
    {
        $out = [];
        $counts = ['email' => 0, 'phone' => 0, 'whatsapp' => 0];
        $limits = ['email' => 5, 'phone' => 3, 'whatsapp' => 2];
        $seen = [];

        $add = function (string $type, string $value, string $source, bool $public, ?string $label = null) use (&$out, &$counts, $limits, &$seen) {
            $key = $type.'|'.$value;
            if (isset($seen[$key]) || (isset($limits[$type]) && $counts[$type] >= $limits[$type])) {
                return;
            }
            $seen[$key] = true;
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
            $out[] = ['type' => $type, 'value' => $value, 'source_url' => $source, 'is_public_business_channel' => $public, 'label' => $label];
        };

        foreach ($pages as $p) {
            $url = $p['url'];
            $own = UrlHelper::sameSite($url, $companyWebsite);

            foreach ($p['emails'] ?? [] as $e) {
                // An email on the company's own domain is a business address; others (e.g. a partner's) are not assumed to be.
                $domainMatch = UrlHelper::host('https://'.substr($e, strpos($e, '@') + 1)) === UrlHelper::host($companyWebsite);
                $add('email', $e, $url, $own && $domainMatch);
            }
            foreach ($p['whatsapp'] ?? [] as $w) {
                $add('whatsapp', $w, $url, $own, 'WhatsApp link on site');
            }
            foreach ($p['phones'] ?? [] as $ph) {
                $add('phone', $ph, $url, $own, 'Phone link on site');
            }
            if ($own && ! empty($p['has_form']) && preg_match('#contact|enquir|get-in-touch#i', (string) parse_url($url, PHP_URL_PATH))) {
                $add('contact_form', $url, $url, true, 'Contact form');
            }
            foreach ($p['links'] ?? [] as $l) {
                if (UrlHelper::sameSite($l['url'], $companyWebsite)
                    && preg_match('#/(careers?|jobs?|vacanc(?:y|ies)|join-us|work-with-us)(/|$)#i', (string) parse_url($l['url'], PHP_URL_PATH))) {
                    $add('careers_portal', $l['url'], $url, true, 'Careers page');
                }
            }
        }

        return $out;
    }
}
