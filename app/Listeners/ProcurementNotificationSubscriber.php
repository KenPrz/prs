<?php

namespace App\Listeners;

use App\Events\Procurement\DocumentCancelled;
use App\Events\Procurement\ItemShortClosed;
use App\Events\Procurement\PurchaseOrderFullyReceived;
use App\Events\Procurement\PurchaseOrderReleased;
use App\Events\Procurement\PurchaseRequisitionReadyForPo;
use App\Events\Procurement\ReceivingReportVerified;
use App\Models\PaymentRequestForm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Notifications\DocumentOutcome;
use App\Notifications\ItemShortClosedNotification;
use App\Notifications\ProcurementHandoff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Maps procurement-chain handoffs to notifications: each stage that finishes
 * tells the next role in the chain (or the requestor whose order completed).
 * The acting user is always excluded — you don't notify yourself.
 */
class ProcurementNotificationSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            PurchaseRequisitionReadyForPo::class => 'onReadyForPo',
            PurchaseOrderReleased::class => 'onReleased',
            ReceivingReportVerified::class => 'onVerified',
            PurchaseOrderFullyReceived::class => 'onFullyReceived',
            ItemShortClosed::class => 'onItemShortClosed',
            DocumentCancelled::class => 'onDocumentCancelled',
        ];
    }

    /** Buyers can now create purchase orders from this requisition. */
    public function onReadyForPo(PurchaseRequisitionReadyForPo $event): void
    {
        $this->notify(
            $this->usersWithPermission('po.prepare'),
            new ProcurementHandoff($event->purchaseRequisition, 'ready_for_po'),
        );
    }

    /** Receivers can now record deliveries against this order. */
    public function onReleased(PurchaseOrderReleased $event): void
    {
        $this->notify(
            $this->usersWithPermission('rr.prepare'),
            new ProcurementHandoff($event->purchaseOrder, 'released'),
        );
    }

    /** Payables can process payment for the verified delivery. */
    public function onVerified(ReceivingReportVerified $event): void
    {
        $this->notify(
            $this->usersWithPermission('prf.prepare'),
            new ProcurementHandoff($event->receivingReport, 'rr_verified'),
        );
    }

    /** The requestor's order arrived in full. */
    public function onFullyReceived(PurchaseOrderFullyReceived $event): void
    {
        $pr = $event->purchaseOrder->purchaseRequisition;
        if ($pr === null) {
            return;
        }

        $this->notify(
            $this->requisitionStakeholders($pr),
            new ProcurementHandoff($event->purchaseOrder, 'fully_received'),
        );
    }

    /** An item was short-closed out of the requestor's order. */
    public function onItemShortClosed(ItemShortClosed $event): void
    {
        $pr = $event->document instanceof PurchaseOrder
            ? $event->document->purchaseRequisition
            : $event->document;

        if (! $pr instanceof PurchaseRequisition || $pr->requestor_id === null) {
            return;
        }

        $this->notify(
            User::query()->whereKey($pr->requestor_id)->get(),
            new ItemShortClosedNotification($event->document, $event->itemName, $event->reason),
        );
    }

    /** A post-approval cancellation: the document's stakeholders should know. */
    public function onDocumentCancelled(DocumentCancelled $event): void
    {
        $userIds = $this->stakeholderIds($event->document);

        $this->notify(
            User::query()->whereIn('id', $userIds)->get(),
            new DocumentOutcome($event->document, 'cancelled', $event->reason, important: true),
        );
    }

    /**
     * @return array<int, int>
     */
    private function stakeholderIds(Model $document): array
    {
        $ids = match (true) {
            $document instanceof PurchaseRequisition => [$document->requestor_id, $document->to_be_ordered_by_id],
            $document instanceof PurchaseOrder => $document->purchaseRequisition
                ? [$document->purchaseRequisition->requestor_id, $document->purchaseRequisition->to_be_ordered_by_id]
                : [],
            $document instanceof PaymentRequestForm => [$document->requestor_id],
            default => [],
        };

        return collect($ids)->filter()->unique()->values()->all();
    }

    /**
     * @return Collection<int, User>
     */
    private function requisitionStakeholders(PurchaseRequisition $pr): Collection
    {
        return User::query()
            ->whereIn('id', collect([$pr->requestor_id, $pr->to_be_ordered_by_id])->filter()->unique())
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithPermission(string $permission): Collection
    {
        try {
            return User::permission($permission)->get();
        } catch (PermissionDoesNotExist) {
            // The permission isn't seeded (yet) — nobody holds it.
            return new Collection;
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function notify(Collection $users, mixed $notification): void
    {
        $recipients = $users->reject(fn (User $user) => $user->id === auth()->id());

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }
}
