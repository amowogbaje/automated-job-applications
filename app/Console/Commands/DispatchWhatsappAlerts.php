<?php

namespace App\Console\Commands;

use App\Models\AgentNotification;
use App\Models\WhatsappReminder;
use Illuminate\Console\Command;

class DispatchWhatsappAlerts extends Command
{
    protected $signature = 'alerts:dispatch';

    protected $description = 'Turn due WhatsApp reminders into notifications (and push), and nag again if still not handled';

    public function handle(): int
    {
        $raised = 0;

        foreach (WhatsappReminder::with('opportunity.company')->where('status', 'pending')->where('remind_at', '<=', now())->get() as $r) {
            if (WhatsappReminder::where('id', $r->id)->where('status', 'pending')->update(['status' => 'due', 'notified_at' => now()]) !== 1) {
                continue;
            }
            $raised += self::notify($r, "Time to message {$r->opportunity->company->name} on WhatsApp", "wa:{$r->id}:0") ? 1 : 0;
        }

        // Still not handled: remind again after 30 and 90 minutes (twice), so a busy moment doesn't lose it.
        foreach (WhatsappReminder::with('opportunity.company')->where('status', 'due')->where('nags', '<', 2)->get() as $r) {
            $wait = $r->nags === 0 ? 30 : 90;
            if ($r->notified_at && $r->notified_at->copy()->addMinutes($wait)->lte(now())) {
                $r->update(['nags' => $r->nags + 1, 'notified_at' => now()]);
                $raised += self::notify($r, "Still waiting: message {$r->opportunity->company->name} on WhatsApp", "wa:{$r->id}:{$r->nags}") ? 1 : 0;
            }
        }

        $this->info("WhatsApp alerts raised: {$raised}");
        if ($raised) {
            AgentNotification::pushAll();
        }

        return self::SUCCESS;
    }

    public static function notify(WhatsappReminder $r, string $title, string $key): bool
    {
        return (bool) AgentNotification::raise('whatsapp_due', $title, $r->message, $r->opportunity_id, $key, route('agent.whatsapp.show', $r));
    }
}
