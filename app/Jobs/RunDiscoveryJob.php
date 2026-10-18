<?php

namespace App\Jobs;

use App\Models\DiscoveryRun;
use App\Services\Agent\Discovery\DiscoveryEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 280;

    public function __construct(public int $runId) {}

    public function handle(DiscoveryEngine $engine): void
    {
        $run = DiscoveryRun::find($this->runId);
        if (! $run || $run->status !== 'pending') {
            return;
        }
        $engine->run($run);
    }

    public function failed(\Throwable $e): void
    {
        DiscoveryRun::where('id', $this->runId)->whereIn('status', ['pending', 'running'])
            ->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
    }
}
