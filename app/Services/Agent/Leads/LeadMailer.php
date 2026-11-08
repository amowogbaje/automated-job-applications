<?php

namespace App\Services\Agent\Leads;

use App\Models\LeadSequence;
use App\Models\Setting;
use App\Models\SharedDocument;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Builds and sends one follow-up email through the app's normal MAIL_* settings. */
class LeadMailer
{
    public static function ownerEmail(): ?string
    {
        $o = config('agent.owner_emails', []);

        return $o[0] ?? User::query()->orderBy('id')->value('email');
    }

    public static function ownerName(): string
    {
        return (string) (User::query()->where('email', self::ownerEmail())->value('name') ?: config('app.name'));
    }

    /** @return array{subject:string, body:string, to:string, attachments:SharedDocument[]} */
    public function compose(LeadSequence $seq, int $stepNumber): array
    {
        $opp = $seq->opportunity->loadMissing('company', 'primaryService');
        $contact = $seq->contact;
        $tpl = Templates::all()[min($stepNumber, 3)] ?? Templates::all()[1];

        $docs = SharedDocument::where('attach_to_emails', true)->get();
        $docText = $docs->isEmpty() ? '' : "Details you may find useful:\n".$docs->map(fn ($d) => '- '.$d->title.': '.$d->publicUrl())->implode("\n");
        $first = trim(explode(' ', trim((string) $contact?->person_name))[0] ?? '');
        $vars = [
            'name' => $first !== '' ? $first : 'there',
            'company' => $opp->company->name,
            'service' => $opp->primaryService?->name ?: 'my services',
            'sender' => self::ownerName(),
            'documents' => $docText,
        ];

        $body = Templates::render($tpl['body'], $vars);
        $sig = Signature::where('is_default', true)->value('body');
        if ($sig) {
            $body .= "\n\n".trim($sig);
        }
        $body .= "\n\n--\nIf you'd rather not get emails from me, opt out here: ".URL::signedRoute('leads.unsubscribe', ['contact' => $contact?->id ?? 0]);

        $max = (int) config('leads.attach_max_mb', 5) * 1048576;

        return [
            'subject' => Templates::render($tpl['subject'], $vars),
            'body' => $body,
            'to' => (string) $contact?->value,
            'attachments' => $docs->filter(fn ($d) => $d->size <= $max && is_file($d->absolutePath()))->values()->all(),
        ];
    }

    /** @throws \Throwable */
    public function send(array $mail): void
    {
        $replyTo = Setting::get('leads.reply_to') ?: self::ownerEmail();
        Mail::raw($mail['body'], function ($m) use ($mail, $replyTo) {
            $m->to($mail['to'])->subject($mail['subject']);
            if ($replyTo) {
                $m->replyTo($replyTo);
            }
            foreach ($mail['attachments'] as $d) {
                $m->attach($d->absolutePath(), ['as' => $d->original_name, 'mime' => $d->mime ?: 'application/octet-stream']);
            }
        });
    }
}
