<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Services\Agent\Outreach\OutreachGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateOutreachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public int $runId, public array $params) {}

    public function handle(OutreachGenerator $generator): void
    {
        $run = AgentRun::find($this->runId);
        if (! $run || $run->status !== 'pending') {
            return;
        }
        $generator->run($run, $this->params);
    }

    public function failed(\Throwable $e): void
    {
        AgentRun::where('id', $this->runId)->whereIn('status', ['pending', 'running'])
            ->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
    }
}
