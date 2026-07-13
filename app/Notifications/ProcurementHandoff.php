<?php

namespace App\Notifications;

use App\Support\SigningLink;
use Illuminate\Database\Eloquent\Model;

/**
 * A stage of the procurement chain finished and the recipient is the next
 * role that has work to do (or the requestor whose order completed).
 */
class ProcurementHandoff extends DocumentNotification
{
    public function __construct(
        Model $document,
        private string $stage, // ready_for_po | released | rr_verified | fully_received
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
     * @return array{title: string, line: string}
     */
    private function copy(): array
    {
        $label = SigningLink::label($this->document);

        return match ($this->stage) {
            'ready_for_po' => [
                'title' => 'Ready for ordering',
                'line' => "{$label} is ready for purchase order creation.",
            ],
            'released' => [
                'title' => 'Open for receiving',
                'line' => "{$label} has been ordered and is open for receiving deliveries.",
            ],
            'rr_verified' => [
                'title' => 'Delivery verified',
                'line' => "{$label} has been verified and is ready for payment processing.",
            ],
            default => [
                'title' => 'Fully received',
                'line' => "All items on {$label} have been received and verified.",
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        $copy = $this->copy();

        return $this->payload($copy['title'], $copy['line']);
    }
}
