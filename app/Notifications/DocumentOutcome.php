<?php

namespace App\Notifications;

use App\Mail\DocumentOutcomeMail;
use App\Support\SigningLink;
use Illuminate\Database\Eloquent\Model;

/**
 * A document's terminal outcome: approved, rejected, or cancelled. Approved
 * and rejected are emailed as well; a cancellation is in-app only unless
 * flagged important (post-approval cancels that affect downstream work).
 */
class DocumentOutcome extends DocumentNotification
{
    public function __construct(
        Model $document,
        private string $outcome, // approved | rejected | cancelled
        private ?string $comment = null,
        private bool $important = false,
    ) {
        parent::__construct($document);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($this->outcome === 'cancelled' && ! $this->important) {
            return ['database'];
        }

        return ['mail', 'database'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        $label = SigningLink::label($this->document);

        return $this->payload(...match ($this->outcome) {
            'approved' => ['Approved', "{$label} has been fully approved."],
            'rejected' => ['Rejected', "{$label} was rejected".($this->comment ? ": \"{$this->comment}\"" : '.')],
            default => ['Cancelled', "{$label} was cancelled".($this->comment ? ": \"{$this->comment}\"" : '.')],
        });
    }

    public function toMail(object $notifiable): DocumentOutcomeMail
    {
        return new DocumentOutcomeMail(
            $this->document,
            $this->outcome,
            $this->comment,
            SigningLink::label($this->document),
            SigningLink::url($this->document),
            $notifiable->name,
            $notifiable->email,
        );
    }
}
