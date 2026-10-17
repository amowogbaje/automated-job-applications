<?php

namespace App\Services\Agent\Research;

/**
 * Turns a page into readable text plus the contact details the company itself published
 * (mailto:/tel:/WhatsApp links and visible emails). It never guesses or constructs addresses.
 */
class HtmlExtractor
{
    public static function extract(string $html, string $baseUrl): array
    {
        $result = ['title' => '', 'description' => '', 'text' => '', 'links' => [], 'emails' => [], 'phones' => [], 'whatsapp' => [], 'has_form' => false];
        if (trim($html) === '') {
            return $result;
        }

        // Keep words from neighbouring block elements apart in the extracted text.
        $html = preg_replace('#</(p|div|li|ul|ol|h[1-6]|tr|td|th|section|article|header|footer|nav|address)\s*>|<br\s*/?>#i', '$0 ', $html) ?? $html;

        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($dom);

        $title = $xp->query('//title')->item(0);
        $result['title'] = $title ? self::clean($title->textContent) : '';

        foreach ($xp->query('//meta[@name]') as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $result['description'] = self::clean($meta->getAttribute('content'));
                break;
            }
        }

        $result['has_form'] = $xp->query('//form')->length > 0;

        // Cloudflare-obfuscated emails
        foreach ($xp->query('//*[@data-cfemail]') as $node) {
            self::addEmail($result, self::decodeCf($node->getAttribute('data-cfemail')));
        }

        foreach ($xp->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            if (stripos($href, 'mailto:') === 0) {
                self::addEmail($result, rawurldecode(explode('?', substr($href, 7))[0]));
            } elseif (stripos($href, 'tel:') === 0) {
                $num = preg_replace('/[^\d+]/', '', rawurldecode(substr($href, 4)));
                if (strlen(preg_replace('/\D/', '', $num)) >= 7) {
                    $result['phones'][$num] = $num;
                }
            } elseif (preg_match('#(?:wa\.me/|(?:api\.)?whatsapp\.com/send/?\?(?:[^"\']*&)?phone=)\+?(\d{7,15})#i', $href, $m)) {
                $result['whatsapp']['+'.$m[1]] = '+'.$m[1];
            } elseif (stripos($href, 'email-protection#') !== false) {
                self::addEmail($result, self::decodeCf(substr($href, strpos($href, '#') + 1)));
            } else {
                $url = UrlHelper::resolve($baseUrl, $href);
                if ($url) {
                    $result['links'][$url] = ['url' => $url, 'text' => self::clean($a->textContent)];
                }
            }
        }

        foreach (['script', 'style', 'noscript', 'svg', 'template', 'iframe'] as $tag) {
            $nodes = [];
            foreach ($dom->getElementsByTagName($tag) as $n) {
                $nodes[] = $n;
            }
            foreach ($nodes as $n) {
                $n->parentNode?->removeChild($n);
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0) ?? $dom->documentElement;
        $result['text'] = $body ? self::clean($body->textContent) : '';

        if (preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $result['text'], $m)) {
            foreach ($m[0] as $email) {
                self::addEmail($result, $email);
            }
        }

        $result['links'] = array_values($result['links']);
        $result['emails'] = array_values($result['emails']);
        $result['phones'] = array_values($result['phones']);
        $result['whatsapp'] = array_values($result['whatsapp']);

        return $result;
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private static function addEmail(array &$result, string $email): void
    {
        $email = strtolower(trim($email, " \t\n\r\0\x0B.,;:<>()[]\"'"));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $domain = substr($email, strpos($email, '@') + 1);
        if (preg_match('/\.(png|jpe?g|gif|svg|webp|css|js|ico|woff2?)$/', $domain)
            || preg_match('/(^|\.)(example|sentry|wixpress|domain)\./', $domain)
            || str_starts_with($email, 'you@') || str_starts_with($email, 'your@') || str_starts_with($email, 'name@')) {
            return;
        }
        $result['emails'][$email] = $email;
    }

    private static function decodeCf(string $hex): string
    {
        if (strlen($hex) < 4 || ! ctype_xdigit($hex) || strlen($hex) % 2) {
            return '';
        }
        $key = hexdec(substr($hex, 0, 2));
        $out = '';
        for ($i = 2; $i < strlen($hex); $i += 2) {
            $out .= chr(hexdec(substr($hex, $i, 2)) ^ $key);
        }

        return $out;
    }
}
