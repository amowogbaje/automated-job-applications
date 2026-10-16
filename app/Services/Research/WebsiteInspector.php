<?php

namespace App\Services\Research;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reads a company's public website. Safe by construction:
 *  - http/https only, standard ports, and every host (including redirect targets) must resolve to a public IP
 *  - honours robots.txt
 *  - contacts are extracted from the page itself (mailto:, tel:, wa.me links, visible emails), never guessed
 */
class WebsiteInspector
{
    private const MAX_BYTES = 1_000_000;
    private const PAGE_TEXT_CHARS = 6000;
    private const ATS_HOSTS = ['greenhouse.io', 'lever.co', 'workable.com', 'ashbyhq.com', 'bamboohr.com', 'smartrecruiters.com', 'recruitee.com', 'teamtailor.com', 'breezy.hr', 'jobs.personio.de', 'myworkdayjobs.com'];
    private const FREE_JUNK_EMAIL_DOMAINS = ['example.com', 'domain.com', 'email.com', 'sentry.io', 'sentry-next.wixpress.com', 'wixpress.com'];

    /** @return array{pages:array, contacts:array, errors:array} */
    public function inspect(string $url, ?int $maxPages = null): array
    {
        $maxPages ??= (int) config('research.pages_per_site', 4);
        $out = ['pages' => [], 'contacts' => [], 'errors' => []];

        $home = $this->fetchPage($url);
        if (! $home['ok']) {
            $out['errors'][] = $home['error'];

            return $out;
        }

        $pages = [$home['page']];
        $links = $home['links'];
        $homeUrl = $home['page']['url'];

        foreach ($this->pickPages($links, $homeUrl, $maxPages) as $extra) {
            $p = $this->fetchPage($extra);
            if ($p['ok']) {
                $pages[] = $p['page'];
                $links = array_merge($links, $p['links']);
            } else {
                $out['errors'][] = $p['error'];
            }
        }

        $out['contacts'] = $this->extractContacts($pages, $links, $this->host($homeUrl));
        // Signals are only needed for contact extraction.
        $out['pages'] = array_map(function ($p) {
            unset($p['signals'], $p['has_form']);

            return $p;
        }, $pages);

        return $out;
    }

    /** Fetch and parse one page. @return array{ok:bool, error?:string, page?:array, links?:array} */
    public function fetchPage(string $url): array
    {
        try {
            $url = $this->normalize($url);
            $this->assertSafe($url);
            if (! $this->robotsAllows($url)) {
                return ['ok' => false, 'error' => "robots.txt asks bots not to read {$url}"];
            }

            $response = Http::withHeaders(['User-Agent' => config('research.user_agent'), 'Accept' => 'text/html,application/xhtml+xml'])
                ->connectTimeout(8)->timeout(15)
                ->withOptions(['allow_redirects' => [
                    'max' => 3, 'strict' => true, 'referer' => false, 'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri) {
                        $this->assertSafe((string) $uri);
                    },
                ]])
                ->get($url);

            if ($response->failed()) {
                return ['ok' => false, 'error' => "HTTP {$response->status()} for {$url}"];
            }
            $type = strtolower((string) $response->header('Content-Type'));
            if ($type !== '' && ! str_contains($type, 'html') && ! str_contains($type, 'text')) {
                return ['ok' => false, 'error' => "Not a web page ({$type}): {$url}"];
            }

            $final = $url;
            if (method_exists($response, 'effectiveUri') && $response->effectiveUri()) {
                $final = (string) $response->effectiveUri();
            }

            return $this->parse(substr($response->body(), 0, self::MAX_BYTES), $final);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
    }

    // -- safety ---------------------------------------------------------

    public function assertSafe(string $url): void
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        if (! $p || empty($p['host']) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Only http and https addresses can be read.');
        }
        if (isset($p['port']) && ! in_array((int) $p['port'], [80, 443, 8080, 8443], true)) {
            throw new \RuntimeException("Refusing non-standard port {$p['port']}.");
        }

        $host = trim($p['host'], '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host) ?: [];
            foreach ((@dns_get_record($host, DNS_AAAA) ?: []) as $r) {
                $ips[] = $r['ipv6'];
            }
        }
        if ($ips === []) {
            throw new \RuntimeException("Could not resolve {$host}.");
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException("Refusing {$host}: it points at a private or reserved address.");
            }
        }
    }

    private function robotsAllows(string $url): bool
    {
        $p = parse_url($url);
        $host = $p['host'];
        $path = ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');

        $rules = Cache::remember("robots:{$host}", 86400, function () use ($p, $host) {
            try {
                $robots = $p['scheme'].'://'.$host.'/robots.txt';
                $this->assertSafe($robots);
                $r = Http::withHeaders(['User-Agent' => config('research.user_agent')])->connectTimeout(5)->timeout(8)->get($robots);

                return $r->successful() ? $this->parseRobots(substr($r->body(), 0, 200000)) : [];
            } catch (\Throwable) {
                return [];
            }
        });

        return $this->robotsDecision($rules, $path);
    }

    /** Rules that apply to "*" or to our bot name. @return array<int, array{0:string,1:string}> */
    public function parseRobots(string $body): array
    {
        $rules = [];
        $agents = [];
        $lastWasAgent = false;
        foreach (preg_split('/\R/', $body) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $lastWasAgent) {
                    $agents = [];
                }
                $agents[] = strtolower($value);
                $lastWasAgent = true;

                continue;
            }
            $lastWasAgent = false;

            $applies = in_array('*', $agents, true) || in_array('opportunityagentbot', $agents, true);
            if ($applies && in_array($field, ['allow', 'disallow'], true)) {
                $rules[] = [$field, $value];
            }
        }

        return $rules;
    }

    public function robotsDecision(array $rules, string $path): bool
    {
        $best = null;
        foreach ($rules as [$type, $rulePath]) {
            $rulePath = rtrim(explode('*', $rulePath)[0], '$');
            if ($rulePath === '' || ! str_starts_with($path, $rulePath)) {
                continue;
            }
            if ($best === null || strlen($rulePath) > strlen($best[1]) || (strlen($rulePath) === strlen($best[1]) && $type === 'allow')) {
                $best = [$type, $rulePath];
            }
        }

        return $best === null || $best[0] === 'allow';
    }

    // -- parsing --------------------------------------------------------

    public function parse(string $html, string $baseUrl): array
    {
        if (trim($html) === '') {
            return ['ok' => false, 'error' => "Empty page: {$baseUrl}"];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);

        $title = trim($xp->evaluate('string(//title)'));
        $description = trim($xp->evaluate('string(//meta[translate(@name,"DESCRIPTION","description")="description"]/@content)'));

        $links = [];
        $mailto = [];
        $tel = [];
        $wa = [];
        foreach ($xp->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            $text = trim(preg_replace('/\s+/u', ' ', $a->textContent));
            if (stripos($href, 'mailto:') === 0) {
                $mailto[] = strtolower(trim(explode('?', substr($href, 7))[0]));

                continue;
            }
            if (stripos($href, 'tel:') === 0) {
                $tel[] = preg_replace('/[^\d+]/', '', substr($href, 4));

                continue;
            }
            if (preg_match('~(?:wa\.me/|whatsapp\.com/send\?(?:[^"\']*&)?phone=)\+?(\d{6,15})~i', $href, $m)) {
                $wa[] = '+'.$m[1];

                continue;
            }
            if ($href === '' || preg_match('~^(javascript:|#|data:)~i', $href)) {
                continue;
            }
            if ($abs = $this->resolve($baseUrl, $href)) {
                $links[] = ['url' => $abs, 'text' => mb_substr($text, 0, 100)];
            }
        }

        $hasForm = $xp->query('//form[.//textarea]')->length > 0;

        foreach ($xp->query('//script|//style|//noscript|//svg|//iframe|//template') as $n) {
            $n->parentNode?->removeChild($n);
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $dom->textContent));

        preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $em);

        return [
            'ok' => true,
            'links' => $links,
            'page' => [
                'url' => $baseUrl,
                'title' => mb_substr($title, 0, 200),
                'description' => mb_substr($description, 0, 400),
                'text' => mb_substr($text, 0, self::PAGE_TEXT_CHARS),
                'has_form' => $hasForm,
                'signals' => [
                    'mailto' => array_values(array_unique($mailto)),
                    'tel' => array_values(array_unique(array_filter($tel))),
                    'wa' => array_values(array_unique($wa)),
                    'text_emails' => array_values(array_unique(array_map('strtolower', $em[0] ?? []))),
                ],
            ],
        ];
    }

    /** Which internal pages are worth reading, best first. */
    public function pickPages(array $links, string $homeUrl, int $max): array
    {
        $host = $this->host($homeUrl);
        $patterns = [
            100 => '~contact|get-in-touch|reach-us|enquir~i',
            90 => '~career|jobs?\b|vacanc|join-us|work-with-us|hiring~i',
            70 => '~service|solution|what-we-do|offer|capabilit|expertise|product~i',
            60 => '~about|who-we-are|company|our-team|team~i',
        ];

        $scored = [];
        foreach ($links as $l) {
            if ($this->host($l['url']) !== $host || $l['url'] === $homeUrl) {
                continue;
            }
            $path = parse_url($l['url'], PHP_URL_PATH) ?: '/';
            if (preg_match('~\.(pdf|jpe?g|png|gif|svg|webp|zip|docx?|xlsx?|mp4|mp3)$~i', $path) || $path === '/') {
                continue;
            }
            foreach ($patterns as $score => $re) {
                if (preg_match($re, $path.' '.$l['text'])) {
                    $key = strtolower(strtok($l['url'], '?'));
                    $scored[$key] = max($scored[$key] ?? 0, $score);

                    break;
                }
            }
        }
        arsort($scored);

        return array_slice(array_keys($scored), 0, $max);
    }

    /** Deterministic contact extraction. Every contact carries the page it was found on. */
    public function extractContacts(array $pages, array $links, string $companyHost): array
    {
        $contacts = [];
        $seen = [];
        $add = function (array $c) use (&$contacts, &$seen) {
            $k = $c['type'].'|'.strtolower($c['value']);
            if (isset($seen[$k]) || mb_strlen($c['value']) > 500) {
                return;
            }
            $seen[$k] = true;
            $contacts[] = $c;
        };

        foreach ($pages as $page) {
            $onContactPage = (bool) preg_match('~contact|get-in-touch|reach-us|enquir~i', parse_url($page['url'], PHP_URL_PATH) ?: '');

            $emails = array_merge(
                array_map(fn ($e) => ['email' => $e, 'mailto' => true], $page['signals']['mailto'] ?? []),
                array_map(fn ($e) => ['email' => $e, 'mailto' => false], $page['signals']['text_emails'] ?? []),
            );
            foreach ($emails as $e) {
                $addr = strtolower($e['email']);
                $domain = substr(strrchr($addr, '@') ?: '', 1);
                if (! filter_var($addr, FILTER_VALIDATE_EMAIL)
                    || preg_match('~\.(png|jpe?g|gif|webp|svg|css|js)$~i', $addr)
                    || in_array($domain, self::FREE_JUNK_EMAIL_DOMAINS, true)) {
                    continue;
                }
                $sameDomain = $domain === $companyHost || str_ends_with($domain, '.'.$companyHost);
                $add([
                    'type' => 'email',
                    'value' => $addr,
                    'label' => $this->emailLabel($addr),
                    'source_url' => $page['url'],
                    // Confirmed only when it belongs to the company's own domain, or is a published mailto: on the contact page.
                    'is_public_business_channel' => $sameDomain || ($e['mailto'] && $onContactPage),
                ]);
            }

            foreach ($page['signals']['tel'] ?? [] as $num) {
                if (strlen(preg_replace('/\D/', '', $num)) >= 7) {
                    $add(['type' => 'phone', 'value' => $num, 'label' => 'Phone (published on site)', 'source_url' => $page['url'], 'is_public_business_channel' => true]);
                }
            }
            foreach ($page['signals']['wa'] ?? [] as $num) {
                $add(['type' => 'whatsapp', 'value' => $num, 'label' => 'WhatsApp link (published on site)', 'source_url' => $page['url'], 'is_public_business_channel' => true]);
            }
            if (! empty($page['has_form']) && ($onContactPage || $page['url'] === $pages[0]['url'])) {
                $add(['type' => 'contact_form', 'value' => $page['url'], 'label' => 'Contact form', 'source_url' => $page['url'], 'is_public_business_channel' => true]);
            }
        }

        $careers = 0;
        $linkedin = 0;
        foreach ($links as $l) {
            $host = $this->host($l['url']);
            $path = parse_url($l['url'], PHP_URL_PATH) ?: '';
            $isAts = (bool) array_filter(self::ATS_HOSTS, fn ($a) => $host === $a || str_ends_with($host, '.'.$a));
            $looksCareers = $host === $companyHost && preg_match('~career|jobs?\b|vacanc|join-us|work-with-us~i', $path.' '.$l['text']);
            if (($isAts || $looksCareers) && $careers < 2) {
                $add(['type' => 'careers_portal', 'value' => $l['url'], 'label' => 'Careers / applications', 'source_url' => $pages[0]['url'], 'is_public_business_channel' => true]);
                $careers++;
            }
            if ($linkedin < 1 && preg_match('~linkedin\.com/company/~i', $l['url'])) {
                $add(['type' => 'professional_profile', 'value' => strtok($l['url'], '?'), 'label' => 'LinkedIn company page', 'source_url' => $pages[0]['url'], 'is_public_business_channel' => true]);
                $linkedin++;
            }
        }

        // Keep the list useful, not exhaustive.
        $limits = ['email' => 5, 'phone' => 3, 'whatsapp' => 2, 'contact_form' => 2, 'careers_portal' => 2, 'professional_profile' => 1];
        $count = [];

        return array_values(array_filter($contacts, function ($c) use (&$count, $limits) {
            $count[$c['type']] = ($count[$c['type']] ?? 0) + 1;

            return $count[$c['type']] <= ($limits[$c['type']] ?? 1);
        }));
    }

    private function emailLabel(string $email): string
    {
        $local = strtolower(strstr($email, '@', true));

        return match (true) {
            (bool) preg_match('~^(careers?|jobs?|hr|recruit|talent|people)~', $local) => 'Hiring',
            (bool) preg_match('~^(sales|business|partnerships?)~', $local) => 'Sales / business',
            (bool) preg_match('~^(support|help)~', $local) => 'Support',
            (bool) preg_match('~^(info|hello|hi|contact|enquir|office|admin|team)~', $local) => 'General enquiries',
            default => 'Email (check who this reaches)',
        };
    }

    // -- url helpers ----------------------------------------------------

    public function normalize(string $url): string
    {
        $url = trim($url);

        return preg_match('~^https?://~i', $url) ? $url : 'https://'.ltrim($url, '/');
    }

    public function host(string $url): string
    {
        return preg_replace('/^www\./i', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    public function resolve(string $base, string $href): ?string
    {
        if (preg_match('~^https?://~i', $href)) {
            return strtok($href, '#');
        }
        $b = parse_url($base);
        if (! $b || empty($b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($href, '//')) {
            return strtok($b['scheme'].':'.$href, '#');
        }
        if (str_starts_with($href, '/')) {
            $path = $href;
        } else {
            $basePath = $b['path'] ?? '/';
            $path = substr($basePath, 0, (int) strrpos($basePath, '/') + 1).$href;
        }
        // collapse ./ and ../
        $parts = [];
        $query = '';
        if (str_contains($path, '?')) {
            [$path, $query] = explode('?', $path, 2);
            $query = '?'.strtok($query, '#');
        }
        $path = strtok($path, '#') ?: '/';
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                array_pop($parts);
            } elseif ($seg !== '.') {
                $parts[] = $seg;
            }
        }
        $clean = implode('/', $parts);

        return $origin.($clean === '' ? '/' : (str_starts_with($clean, '/') ? $clean : '/'.$clean)).$query;
    }
}
