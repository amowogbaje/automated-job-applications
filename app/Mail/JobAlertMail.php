<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class JobAlertMail extends Mailable
{
    /**
     * @param  Collection<int, \App\Models\JobListingUserState>  $states
     * @param  string[]  $skills
     */
    public function __construct(public Collection $states, public int $total, public array $skills = []) {}

    public function envelope(): Envelope
    {
        $what = $this->skills ? ' for ' . implode(', ', array_slice($this->skills, 0, 3)) : '';

        return new Envelope(subject: $this->total . ' new job' . ($this->total === 1 ? '' : 's') . $what);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.job-alert');
    }
}
