<?php

namespace App\Notifications;

use App\Mail\ApprovalActionRequiredMail;
use App\Support\SigningLink;
use Illuminate\Database\Eloquent\Model;

/**
 * It is the recipient's turn to act on a document: the workflow reached their
 * step, their step went overdue, or a pending decision was reassigned to them.
 */
class ApprovalActionRequired extends DocumentNotification
{
    public function __construct(
        Model $document,
        private ?string $stepName = null,
        private string $context = 'your_turn', // your_turn | overdue | reassigned
        private bool $sendMail = true,
    ) {
        parent::__construct($document);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $this->sendMail) {
            return ['database'];
        }

        if (in_array($this->context, ['your_turn', 'reassigned'])) {
            return ['mail', 'database'];
        }

        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        $label = SigningLink::label($this->document);
        $step = $this->stepName ? " ({$this->stepName})" : '';

        return $this->payload(...match ($this->context) {
            'overdue' => ['Approval overdue', "{$label} is overdue for your decision{$step}."],
            'reassigned' => ['Reassigned to you', "{$label} was reassigned to you for approval{$step}."],
            default => ['Awaiting your approval', "{$label} needs your decision{$step}."],
        });
    }

    public function toMail(object $notifiable): ApprovalActionRequiredMail
    {
        return new ApprovalActionRequiredMail(
            $this->document,
            $this->context,
            $this->stepName,
            SigningLink::label($this->document),
            SigningLink::url($this->document),
            $notifiable->name,
            $notifiable->email,
        );
    }
}
