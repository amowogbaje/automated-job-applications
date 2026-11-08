<?php

namespace App\Http\Controllers;

use App\Models\AgentNotification;
use App\Models\Contact;
use App\Models\SharedDocument;
use Illuminate\Http\Request;

/** Signed, login-free links that go out in emails: opt-out and document view. */
class PublicLeadController extends Controller
{
    public function unsubscribe(Request $request, int $contact)
    {
        $c = Contact::find($contact);
        if ($c) {
            $c->update(['status' => 'opted_out']);
            $c->company?->contacts()->where('type', 'email')->where('value', $c->value)->update(['status' => 'opted_out']);
            foreach ($c->company?->opportunities ?? [] as $o) {
                $o->sequence?->update(['state' => 'stopped', 'stop_reason' => 'Opted out']);
                AgentNotification::raise('info', ($c->company->name ?? 'A lead').' opted out of emails', 'They used the opt-out link. No more emails will be sent to '.$c->value.'.', $o->id, "optout:{$c->id}");
            }
        }

        return response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem"><h1>You are unsubscribed</h1><p>You will not receive any more emails from me. Sorry to have bothered you.</p>');
    }

    public function document(SharedDocument $document)
    {
        abort_unless(is_file($document->absolutePath()), 404);

        return response()->file($document->absolutePath(), [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($document->original_name).'"',
        ]);
    }
}
