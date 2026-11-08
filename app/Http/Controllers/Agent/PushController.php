<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentNotification;
use App\Models\PushSubscription;
use App\Services\Agent\Push\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function key(WebPush $push): JsonResponse
    {
        return response()->json(['key' => $push->publicKey()]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:2000']]);
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            ['endpoint' => $data['endpoint'], 'user_agent' => mb_substr((string) $request->userAgent(), 0, 250)]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);
        PushSubscription::where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }

    public function test(WebPush $push): JsonResponse
    {
        AgentNotification::create(['kind' => 'info', 'title' => 'Test notification', 'body' => 'Push notifications are working on this device.', 'dedupe_key' => 'test:'.now()->timestamp, 'read_at' => null]);

        return response()->json($push->broadcast());
    }

    /** What the service worker shows after a wake-up. Needs your login cookie. */
    public function latest(): JsonResponse
    {
        $unread = AgentNotification::whereNull('read_at')->count();
        $n = AgentNotification::whereNull('read_at')->latest('id')->first();

        return response()->json([
            'title' => $n?->title ?? 'Lead follow-ups',
            'body' => $unread > 1 ? "{$unread} things need you" : ($n ? \Illuminate\Support\Str::limit(strtok((string) $n->body, "\n") ?: '', 120) : ''),
            'url' => $n?->opportunity_id && $unread === 1 ? route('agent.opportunities.show', $n->opportunity_id) : route('agent.notifications.index'),
        ]);
    }
}
