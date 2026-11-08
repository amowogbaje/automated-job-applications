<?php

namespace App\Services\Agent\Push;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Services\Agent\Leads\LeadMailer;
use Illuminate\Support\Facades\Http;

class WebPush
{
    /** @return array{private:string, public:string} created once, kept in settings */
    public function keys(): array
    {
        $k = Setting::get('push.vapid');
        if (! $k) {
            $k = Vapid::generate();
            Setting::put('push.vapid', $k);
        }

        return $k;
    }

    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /** Wakes every subscribed device. @return array{sent:int, removed:int, failed:int} */
    public function broadcast(): array
    {
        $out = ['sent' => 0, 'removed' => 0, 'failed' => 0];
        $keys = $this->keys();
        $subject = 'mailto:'.(LeadMailer::ownerEmail() ?: 'admin@example.com');

        foreach (PushSubscription::all() as $sub) {
            try {
                $res = Http::timeout(10)->withHeaders([
                    'Authorization' => 'vapid t='.Vapid::jwt($sub->endpoint, $subject, $keys['private']).', k='.$keys['public'],
                    'TTL' => '86400',
                    'Urgency' => 'high',
                    'Content-Length' => '0',
                ])->withBody('', 'application/octet-stream')->post($sub->endpoint);
            } catch (\Throwable $e) {
                $out['failed']++;
                continue;
            }
            if (in_array($res->status(), [404, 410], true)) {
                $sub->delete();
                $out['removed']++;
            } elseif ($res->successful()) {
                $out['sent']++;
            } else {
                $out['failed']++;
            }
        }

        return $out;
    }
}
