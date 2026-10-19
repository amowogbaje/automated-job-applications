<?php

namespace App\Http\Controllers\AmowogbajeWebsite;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/** Receives the project brief from www.amowogbaje.com and emails it to the owner. */
class BriefController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        // Honeypot: pretend success so bots learn nothing.
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }

        // Single-line fields: drop line breaks (blocks header injection).
        $oneLine = fn ($v) => trim((string) preg_replace('/[\r\n\0]+/', ' ', is_scalar($v) ? (string) $v : ''));
        $in = [
            'name' => $oneLine($request->input('name')),
            'email' => $oneLine($request->input('email')),
            'need' => is_scalar($request->input('need')) ? trim((string) $request->input('need')) : '',
            'timeline' => $oneLine($request->input('timeline')),
            'budget' => $oneLine($request->input('budget')),
            'link' => $oneLine($request->input('link')),
        ];

        $v = Validator::make($in, [
            'name' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:150',
            'need' => 'required|string|max:4000',
            'timeline' => 'nullable|string|max:80',
            'budget' => 'nullable|string|max:80',
            'link' => 'nullable|string|max:300',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }
        $d = $v->validated();

        $body = "Name: {$d['name']}\nEmail: {$d['email']}\nTimeline: ".($d['timeline'] ?? '')
            ."\nBudget: ".($d['budget'] ?? '')."\nLink: ".($d['link'] ?? '')."\n\n{$d['need']}\n";

        try {
            Mail::raw($body, function ($m) use ($d) {
                $m->to(config('brief.to'));
                if (config('brief.bcc')) {
                    $m->bcc(config('brief.bcc'));
                }
                $m->replyTo($d['email'], $d['name'])->subject('Project brief from '.$d['name']);
            });
        } catch (\Throwable $e) {
            Log::error('send-brief mail failed: '.$e->getMessage());

            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }
}
