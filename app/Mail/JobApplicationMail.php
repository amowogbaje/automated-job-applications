<?php

namespace App\Mail;

use App\Models\JobListing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public JobListing $job,
        public string $coverLetter,
        public ?string $resumePath = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Application: {$this->job->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application',
            with: ['coverLetter' => $this->coverLetter],
        );
    }

    public function attachments(): array
    {
        if ($this->resumePath && file_exists($this->resumePath)) {
            return [\Illuminate\Mail\Mailables\Attachment::fromPath($this->resumePath)];
        }

        return [];
    }
}
