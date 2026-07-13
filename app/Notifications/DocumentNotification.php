<?php

namespace App\Notifications;

use App\Support\SigningLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Base for every document notification: queued, committed-data only, and
 * carrying the standard in-app payload the notification bell renders
 * (title / message / action_url / doc_label).
 */
abstract class DocumentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Model $document)
    {
        // Never queue before the surrounding transaction commits — most
        // dispatch sites live inside DB::transaction blocks.
        $this->afterCommit();
    }

    /**
     * The in-app (database channel) payload.
     *
     * @return array<string, string>
     */
    protected function payload(string $title, string $message): array
    {
        return [
            'title' => $title,
            'message' => $message,
            'action_url' => SigningLink::url($this->document),
            'doc_label' => SigningLink::label($this->document),
        ];
    }
}
