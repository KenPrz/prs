<?php

namespace App\Notifications;

use App\Support\SigningLink;
use Illuminate\Database\Eloquent\Model;

/**
 * One of the requestor's items was short-closed (omitted) on a requisition or
 * purchase order. In-app only — informational, no action required.
 */
class ItemShortClosedNotification extends DocumentNotification
{
    public function __construct(
        Model $document,
        private string $itemName,
        private ?string $reason = null,
    ) {
        parent::__construct($document);
    }

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

        return $this->payload(
            'Item short-closed',
            "'{$this->itemName}' on {$label} was omitted".($this->reason ? ": \"{$this->reason}\"" : '.'),
        );
    }
}
