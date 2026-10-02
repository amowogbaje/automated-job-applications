<?php

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells an employer someone applied to a job they posted here, with the resume attached. */
class NewApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public JobApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New applicant: {$this->application->applicant->name} for {$this->application->job->title}",
            replyTo: [$this->application->applicant->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new-application');
    }

    public function attachments(): array
    {
        $path = $this->application->resume_pdf_path;

        return $path && file_exists($path)
            ? [Attachment::fromPath($path)->as('resume-' . str($this->application->applicant->name)->slug() . '.pdf')->withMime('application/pdf')]
            : [];
    }
}
