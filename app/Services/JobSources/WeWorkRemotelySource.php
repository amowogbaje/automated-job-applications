<?php

namespace App\Services\JobSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeWorkRemotelySource implements JobSourceInterface
{
    // Add/remove category feeds as needed. Full list at weworkremotely.com/categories
    protected array $feeds = [
        'programming' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
        'full-stack' => 'https://weworkremotely.com/categories/remote-full-stack-programming-jobs.rss',
        'back-end' => 'https://weworkremotely.com/categories/remote-back-end-programming-jobs.rss',
    ];

    public function name(): string
    {
        return 'weworkremotely';
    }

    public function fetch(): array
    {
        $jobs = [];

        foreach ($this->feeds as $category => $url) {
            try {
                $response = Http::timeout(15)->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $xml = @simplexml_load_string($response->body());
                if ($xml === false || ! isset($xml->channel->item)) {
                    continue;
                }

                foreach ($xml->channel->item as $item) {
                    $title = (string) $item->title;
                    // WWR titles are usually "Company: Job Title"
                    $company = null;
                    if (str_contains($title, ':')) {
                        [$company, $titleOnly] = array_map('trim', explode(':', $title, 2));
                    } else {
                        $titleOnly = $title;
                    }

                    $jobs[] = [
                        'source' => $this->name(),
                        'external_id' => (string) $item->guid,
                        'title' => $titleOnly,
                        'company' => $company,
                        'location' => 'Remote',
                        'is_remote' => true,
                        'description' => strip_tags((string) $item->description),
                        'url' => (string) $item->link,
                        'posted_at' => isset($item->pubDate) ? Carbon::parse((string) $item->pubDate) : null,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('WeWorkRemotely fetch exception', ['category' => $category, 'error' => $e->getMessage()]);
            }
        }

        return $jobs;
    }
}
