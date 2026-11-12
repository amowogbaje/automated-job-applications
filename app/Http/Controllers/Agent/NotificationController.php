<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentNotification;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function index()
    {
        return view('agent.notifications.index', [
            'items' => AgentNotification::with('opportunity.company')->latest()->limit(100)->get(),
            'due' => \App\Models\WhatsappReminder::with('opportunity.company')->where('status', 'due')->orderBy('remind_at')->get(),
            'upcoming' => \App\Models\WhatsappReminder::with('opportunity.company')->where('status', 'pending')->orderBy('remind_at')->limit(30)->get(),
            'emails' => \App\Models\ScheduledEmail::with('opportunity.company', 'contact')->where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($q) => $q->whereIn('status', ['failed', 'skipped'])->where('updated_at', '>=', now()->subDays(3))))->orderBy('send_at')->limit(30)->get(),
        ]);
    }

    public function read(AgentNotification $notification): RedirectResponse
    {
        $notification->update(['read_at' => now()]);

        return back();
    }

    public function readAll(): RedirectResponse
    {
        AgentNotification::whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All marked as read.');
    }
}
