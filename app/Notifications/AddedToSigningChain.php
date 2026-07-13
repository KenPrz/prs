<?php

namespace App\Notifications;

use App\Support\SigningLink;

/**
 * Tells a person they are part of a newly submitted document's signing chain.
 * Database-only (UI bell) — no email. Separate action-required email comes when it's their turn.
 */
class AddedToSigningChain extends DocumentNotification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        $label = SigningLink::label($this->document);
        $type = SigningLink::type($this->document);

        return $this->payload(
            "You're a signee on {$label}",
            "A new {$type} ({$label}) has been submitted for approval."
        );
    }
}
