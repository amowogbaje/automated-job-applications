<?php

namespace App\Services\JobSources;

class AtsDetector
{
    /**
     * Identify the ATS behind a job/apply URL.
     *
     * @return array{ats:string, board:string, job_id:string}|null
     */
    public static function fromUrl(?string $url): ?array
    {
        if (! $url) {
            return null;
        }

        $patterns = [
            'greenhouse' => '#(?:boards|job-boards)(?:\.eu)?\.greenhouse\.io/([^/?\#]+)/jobs/(\d+)#i',
            'lever' => '#jobs(?:\.eu)?\.lever\.co/([^/?\#]+)/([0-9a-f-]{36})#i',
            'ashby' => '#jobs\.ashbyhq\.com/([^/?\#]+)/([0-9a-f-]{36})#i',
        ];

        foreach ($patterns as $ats => $re) {
            if (preg_match($re, $url, $m)) {
                return ['ats' => $ats, 'board' => strtolower($m[1]), 'job_id' => $m[2]];
            }
        }

        // Greenhouse embeds: ...?gh_jid=123&for=board
        if (preg_match('#[?&]gh_jid=(\d+)#', $url, $j) && preg_match('#[?&]for=([^&]+)#', $url, $b)) {
            return ['ats' => 'greenhouse', 'board' => strtolower($b[1]), 'job_id' => $j[1]];
        }

        return null;
    }
}
