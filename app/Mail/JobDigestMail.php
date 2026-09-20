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

    public function __construct(public Collection $jobs) {}

    public function envelope(): Envelope
    {
        $count = $this->jobs->count();

        return new Envelope(
            subject: "{$count} new job" . ($count === 1 ? '' : 's') . " to apply for manually",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.digest');
    }
}
