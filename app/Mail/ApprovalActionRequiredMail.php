<?php

namespace App\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApprovalActionRequiredMail extends Mailable
{
    public function __construct(
        private Model $document,
        private string $context,
        private ?string $stepName,
        private string $documentLabel,
        private string $documentUrl,
        private string $recipientName,
        private string $recipientEmail,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->context) {
            'overdue' => "Overdue: {$this->documentLabel}",
            'reassigned' => "Reassigned: {$this->documentLabel}",
            default => "Your turn: {$this->documentLabel}",
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
            view: 'emails.approval-action-required',
            with: [
                'context' => $this->context,
                'stepName' => $this->stepName,
                'documentLabel' => $this->documentLabel,
                'documentUrl' => $this->documentUrl,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}
