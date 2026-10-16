<?php

namespace App\Services\Research;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Reads a company's public pages politely: public hosts only, robots.txt honoured, a few pages at most. */
class SiteInspector
{
    private array $robots = [];

    private string $userAgent;

    private int $timeout;

    public function __construct()
    {
        $this->userAgent = (string) config('research.user_agent');
        $this->timeout = (int) config('research.fetch_timeout', 12);
    }

    /** @return array{ok: bool, url: string, html?: string, error?: string} */
    public function fetch(string $url): array
    {
        $current = $url;
        for ($i = 0; $i <= 3; $i++) {
            if (! UrlGuard::isPublicHttpUrl($current)) {
                return ['ok' => false, 'url' => $current, 'error' => 'not a public web address'];
            }
            if (! $this->robotsAllow($current)) {
                return ['ok' => false, 'url' => $current, 'error' => 'blocked by robots.txt'];
            }
            try {
                $r = Http::withUserAgent($this->userAgent)->timeout($this->timeout)
                    ->withOptions(['allow_redirects' => false])->accept('text/html,application/xhtml+xml')->get($current);
            } catch (ConnectionException $e) {
                return ['ok' => false, 'url' => $current, 'error' => 'connection failed'];
            }

            if ($r->redirect()) {
                $next = UrlHelper::resolve($current, (string) $r->header('Location'));
                if (! $next) {
                    return ['ok' => false, 'url' => $current, 'error' => 'bad redirect'];
                }
                $current = $next;

                continue;
            }
            if (! $r->successful()) {
                return ['ok' => false, 'url' => $current, 'error' => 'HTTP '.$r->status()];
            }
            $ct = strtolower((string) $r->header('Content-Type'));
            if ($ct !== '' && ! str_contains($ct, 'html')) {
                return ['ok' => false, 'url' => $current, 'error' => 'not an HTML page'];
            }

            return ['ok' => true, 'url' => $current, 'html' => substr($r->body(), 0, 1500000)];
        }

        return ['ok' => false, 'url' => $current, 'error' => 'too many redirects'];
    }

    /** @return array{pages: array, notes: string[]} */
    public function inspect(string $website, int $maxPages): array
    {
        $pages = [];
        $notes = [];

        $home = $this->fetch($website);
        if (! $home['ok']) {
            return ['pages' => [], 'notes' => ["Homepage not read ({$home['error']})."]];
        }
        $homeData = HtmlExtractor::extract($home['html'], $home['url']) + ['url' => $home['url']];
        $pages[] = $homeData;

        foreach (self::pickSubpages($homeData['links'], $home['url'], max(0, $maxPages - 1)) as $url) {
            if ($url === $home['url']) {
                continue;
            }
            $res = $this->fetch($url);
            if (! $res['ok']) {
                $notes[] = "Skipped {$url} ({$res['error']}).";

                continue;
            }
            $pages[] = HtmlExtractor::extract($res['html'], $res['url']) + ['url' => $res['url']];
        }

        return ['pages' => $pages, 'notes' => $notes];
    }

    /** Choose the most useful same-site pages: contact, careers, services, then about. */
    public static function pickSubpages(array $links, string $baseUrl, int $limit): array
    {
        $scored = [];
        foreach ($links as $l) {
            if (! UrlHelper::sameSite($l['url'], $baseUrl) || preg_match('#\.(pdf|jpe?g|png|gif|zip|docx?|xlsx?)(\?|$)#i', $l['url'])) {
                continue;
            }
            $hay = strtolower((string) parse_url($l['url'], PHP_URL_PATH).' '.$l['text']);
            $score = 0;
            foreach ([['/contact/', 100], ['/career|job|vacanc|join.us|work.with.us/', 80], ['/service|solution|what.we.do|product|platform/', 60], ['/about|team|company/', 40]] as [$re, $s]) {
                if (preg_match($re, $hay)) {
                    $score = max($score, $s);
                }
            }
            if ($score > 0) {
                $scored[$l['url']] = max($scored[$l['url']] ?? 0, $score);
            }
        }
        arsort($scored);

        return array_slice(array_keys($scored), 0, $limit);
    }

    private function robotsAllow(string $url): bool
    {
        $p = parse_url($url);
        $host = $p['host'] ?? '';
        if (! array_key_exists($host, $this->robots)) {
            $this->robots[$host] = null;
            $robotsUrl = ($p['scheme'] ?? 'https').'://'.$host.'/robots.txt';
            if (UrlGuard::isPublicHttpUrl($robotsUrl)) {
                try {
                    $r = Http::withUserAgent($this->userAgent)->timeout(8)->withOptions(['allow_redirects' => false])->get($robotsUrl);
                    if ($r->successful()) {
                        $this->robots[$host] = substr($r->body(), 0, 200000);
                    }
                } catch (\Throwable $e) {
                    // unreachable robots.txt: treated as no restrictions
                }
            }
        }
        if ($this->robots[$host] === null) {
            return true;
        }

        return RobotsTxt::allows($this->robots[$host], ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : ''));
    }
}
