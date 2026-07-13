<?php

namespace App\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DocumentOutcomeMail extends Mailable
{
    public function __construct(
        private Model $document,
        private string $outcome,
        private ?string $comment,
        private string $documentLabel,
        private string $documentUrl,
        private string $recipientName,
        private string $recipientEmail,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->outcome) {
            'approved' => "Approved: {$this->documentLabel}",
            'rejected' => "Rejected: {$this->documentLabel}",
            default => "Cancelled: {$this->documentLabel}",
        };

        return new Envelope(
            to: $this->recipientEmail,
            from: config('mail.from.address'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-outcome',
            with: [
                'outcome' => $this->outcome,
                'comment' => $this->comment,
                'documentLabel' => $this->documentLabel,
                'documentUrl' => $this->documentUrl,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}
