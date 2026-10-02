<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class JobDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $jobs, public ?Collection $social = null)
    {
        $this->social ??= collect();
    }

    public function envelope(): Envelope
    {
        $count = $this->jobs->count();
        $social = $this->social->count();

        $subject = $social > 0
            ? "{$social} fresh social post" . ($social === 1 ? '' : 's') . ' to apply to' . ($count ? " + {$count} job" . ($count === 1 ? '' : 's') : '')
            : "{$count} new job" . ($count === 1 ? '' : 's') . ' to apply for manually';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.digest');
    }
}
