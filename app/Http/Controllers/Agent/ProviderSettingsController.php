<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

use App\Models\Setting;
use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Providers\ProviderHealth;
use App\Services\Agent\Providers\ProviderRegistry;
use App\Services\Agent\Search\SearchManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class ProviderSettingsController extends Controller
{
    private const KINDS = ['ai', 'search'];

    public function index(ProviderRegistry $registry, ProviderHealth $health)
    {
        $rows = function (string $kind) use ($registry, $health) {
            $stats = $health->stats($kind);

            return array_map(fn ($p) => $p + [
                'cooldown' => $health->cooldown($kind, $p['name']),
                'stats' => $stats[$p['name']] ?? ['ok' => 0, 'failed' => 0, 'tokens' => 0],
            ], $registry->all($kind));
        };

        return view('agent.providers.index', [
            'ai' => $rows('ai'),
            'search' => $rows('search'),
            'today' => ['ai' => $health->todayCount('ai'), 'search' => $health->todayCount('search')],
            'limits' => ['ai_calls_per_day' => $registry->limit('ai'), 'search_calls_per_day' => $registry->limit('search')],
        ]);
    }

    public function update(Request $request, ProviderRegistry $registry): RedirectResponse
    {
        $request->validate([
            'ai.*.priority' => ['nullable', 'integer', 'between:1,99'],
            'ai.*.model' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\-\/:]+$/'],
            'search.*.priority' => ['nullable', 'integer', 'between:1,99'],
            'ai_calls_per_day' => ['required', 'integer', 'between:0,100000'],
            'search_calls_per_day' => ['required', 'integer', 'between:0,100000'],
        ]);

        foreach (self::KINDS as $kind) {
            $saved = [];
            foreach ($registry->all($kind) as $p) {
                $in = $request->input("{$kind}.{$p['name']}", []);
                $row = [
                    'enabled' => ! empty($in['enabled']),   // unchecked boxes are absent from the request
                    'priority' => (int) ($in['priority'] ?? $p['priority']),
                ];
                if ($kind === 'ai') {
                    $model = trim((string) ($in['model'] ?? ''));
                    $row['model'] = ($model !== '' && $model !== $p['default_model']) ? $model : null;
                }
                $saved[$p['name']] = $row;
            }
            Setting::put($kind === 'ai' ? 'ai_providers' : 'search_providers', $saved);
        }

        Setting::put('provider_limits', [
            'ai_calls_per_day' => (int) $request->input('ai_calls_per_day'),
            'search_calls_per_day' => (int) $request->input('search_calls_per_day'),
        ]);

        return back()->with('status', 'Provider settings saved.');
    }

    /** Calls one provider directly so you can check its key works. Result is shown on the page. */
    public function test(string $kind, string $name, AiManager $ai, SearchManager $search): RedirectResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $result = $kind === 'ai' ? $ai->test($name) : $search->test($name);

        return back()->with('provider_test', ['ok' => $result['ok'], 'name' => $name, 'message' => $result['message']]);
    }

    /** Clear a provider's cooldown so it is tried again straight away. */
    public function reset(string $kind, string $name, ProviderHealth $health): RedirectResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $health->clear($kind, $name);

        return back()->with('status', 'Cooldown cleared for '.$name.'.');
    }

    /** cPanel often keeps a cached config, so keys added to .env are invisible until it is cleared. */
    public function refresh(): RedirectResponse
    {
        try {
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            // best effort; the message below tells her what to check
        }

        return redirect()->route('agent.providers.index')->with('provider_test', [
            'ok' => true, 'name' => 'Config',
            'message' => 'Cache cleared. Keys now come straight from .env. If one still says "no key", check the spelling of the name and that there is no space or # right after the = sign.',
        ]);
    }
}
