<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Agent\Mail\GoogleMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleConnectController extends Controller
{
    public function connect(Request $request, GoogleMail $google): RedirectResponse
    {
        if (! GoogleMail::configured()) {
            return back()->withErrors(['google' => 'Google sign-in is not set up. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env.']);
        }
        $request->session()->put('google_oauth_state', $state = Str::random(40));
        $request->session()->put('google_oauth_back', url()->previous());

        return redirect()->away($google->authUrl($state));
    }

    public function callback(Request $request, GoogleMail $google): RedirectResponse
    {
        $back = $request->session()->pull('google_oauth_back', route('agent.leads.index'));
        $state = $request->session()->pull('google_oauth_state');
        if ($request->filled('error')) {
            return redirect($back)->withErrors(['google' => 'Google sign-in was cancelled ('.$request->input('error').').']);
        }
        if (! $state || ! hash_equals($state, (string) $request->input('state')) || ! $request->filled('code')) {
            return redirect($back)->withErrors(['google' => 'That sign-in link expired. Try connecting again.']);
        }
        try {
            $acct = $google->connect($request->user(), (string) $request->input('code'));
        } catch (\Throwable $e) {
            return redirect($back)->withErrors(['google' => mb_substr($e->getMessage(), 0, 300)]);
        }

        return redirect($back)->with('status', 'Google connected: '.$acct->email.'. Send now will use this mailbox.');
    }

    public function disconnect(Request $request, GoogleMail $google): RedirectResponse
    {
        if ($acct = $request->user()->googleAccount) {
            $google->disconnect($acct);
        }

        return back()->with('status', 'Google disconnected.');
    }
}
