<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentNotification;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function index()
    {
        return view('agent.notifications.index', ['items' => AgentNotification::with('opportunity.company')->latest()->limit(100)->get()]);
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
