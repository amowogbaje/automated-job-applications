<?php

namespace App\Services\Agent\Providers;

use App\Models\Setting;

/** Merges config (keys, defaults) with the editable overrides saved in the settings table. */
class ProviderRegistry
{
    public const SETTING_KEYS = ['ai' => 'ai_providers', 'search' => 'search_providers'];

    public function all(string $kind): array
    {
        $configured = config($kind === 'ai' ? 'ai.providers' : 'search.providers', []);
        $overrides = Setting::get(self::SETTING_KEYS[$kind], []) ?: [];

        $rows = [];
        foreach ($configured as $name => $c) {
            $o = $overrides[$name] ?? [];
            $rows[] = [
                'name' => $name,
                'label' => $c['label'] ?? $name,
                'driver' => $c['driver'] ?? $name,
                'key' => $c['key'] ?? null,
                'key_env' => $c['key_env'] ?? null,
                'signup_url' => $c['signup_url'] ?? null,
                'base_url' => $c['base_url'] ?? null,
                'configured' => filled($c['key'] ?? null),
                'enabled' => (bool) ($o['enabled'] ?? true),
                'priority' => (int) ($o['priority'] ?? $c['priority'] ?? 100),
                'model' => (($o['model'] ?? null) ?: ($c['model'] ?? null)),
                'default_model' => $c['model'] ?? null,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['priority'], $a['name']] <=> [$b['priority'], $b['name']]);

        return $rows;
    }

    public function find(string $kind, string $name): ?array
    {
        foreach ($this->all($kind) as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }

        return null;
    }

    public function limit(string $kind): int
    {
        $key = $kind === 'ai' ? 'ai_calls_per_day' : 'search_calls_per_day';
        $saved = Setting::get('provider_limits', []) ?: [];

        return (int) ($saved[$key] ?? config("ai.limits.{$key}", 0));
    }
}
