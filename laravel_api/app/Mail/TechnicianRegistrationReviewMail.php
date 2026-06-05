<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TechnicianRegistrationReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $decision,
        public readonly string $note = '',
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->decision === 'approved'
            ? 'Your Nearest Technician registration was approved'
            : 'Your Nearest Technician registration was rejected';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.technician-registration-review',
            with: [
                'name' => $this->name,
                'decision' => $this->decision,
                'note' => $this->note,
            ],
        );
    }
}
