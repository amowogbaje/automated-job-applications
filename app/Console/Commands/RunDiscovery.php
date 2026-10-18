<?php

namespace App\Console\Commands;

use App\Models\DiscoveryRun;
use App\Models\Service;
use App\Services\Agent\Discovery\DiscoveryEngine;
use App\Services\Agent\Discovery\Strategies;
use Illuminate\Console\Command;

class RunDiscovery extends Command
{
    protected $signature = 'discovery:run {--strategy=* : technical_need|hiring|external_assistance|funded_growth|portfolio_fit} {--service= : service id (default: every active service)}';

    protected $description = 'Find candidate companies for your services (saved as "discovered" opportunities; nothing is contacted)';

    public function handle(DiscoveryEngine $engine): int
    {
        $strategies = array_filter((array) $this->option('strategy')) ?: (array) config('discovery.schedule_strategies');
        $strategies = array_values(array_filter($strategies, fn ($s) => Strategies::exists($s)));
        if (! $strategies) {
            $this->error('No valid strategy given.');

            return self::FAILURE;
        }

        $services = Service::active()->when($this->option('service'), fn ($q, $id) => $q->where('id', $id))->get();
        $cap = (int) config('discovery.runs_per_day');

        foreach ($services as $service) {
            foreach ($strategies as $strategy) {
                if (DiscoveryRun::where('created_at', '>=', now()->startOfDay())->count() >= $cap) {
                    $this->warn("Daily limit of {$cap} runs reached.");

                    return self::SUCCESS;
                }
                $run = DiscoveryRun::create(['strategy' => $strategy, 'service_id' => $service->id, 'status' => 'pending', 'options' => []]);
                $engine->run($run);
                $run->refresh();
                $this->line("{$service->name} / {$strategy}: {$run->status}, {$run->candidates_new} new".($run->error ? " ({$run->error})" : ''));
            }
        }

        return self::SUCCESS;
    }
}
