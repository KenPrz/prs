<?php

namespace App\Support;

use App\Models\PaymentRequestForm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Resolves human-readable labels and signing URLs for procurement documents.
 *
 * A "signing" link points at the document's show page, where an assigned
 * approver can review the document and record their decision. The show
 * routes are auth-protected, so a plain route URL is correct here (a signed
 * URL would only make sense for a guest-accessible route).
 */
class SigningLink
{
    /**
     * @return array{type: string, number: string, route: string}
     */
    private static function meta(Model $document): array
    {
        return match (true) {
            $document instanceof PurchaseRequisition => [
                'type' => 'Purchase Requisition',
                'number' => (string) $document->pr_number,
                'route' => 'purchase-requisitions.show',
            ],
            $document instanceof PurchaseOrder => [
                'type' => 'Purchase Order',
                'number' => (string) $document->po_number,
                'route' => 'purchase-orders.show',
            ],
            $document instanceof ReceivingReport => [
                'type' => 'Receiving Report',
                'number' => (string) $document->rr_number,
                'route' => 'receiving-reports.show',
            ],
            $document instanceof PaymentRequestForm => [
                'type' => 'Payment Request Form',
                'number' => (string) $document->prf_number,
                'route' => 'payment-request-forms.show',
            ],
            default => throw new InvalidArgumentException(
                'Unsupported signable document: '.$document::class
            ),
        };
    }

    /**
     * The document type label, e.g. "Purchase Requisition".
     */
    public static function type(Model $document): string
    {
        return self::meta($document)['type'];
    }

    /**
     * A display label including the document number, e.g. "Purchase Requisition #2026-001".
     */
    public static function label(Model $document): string
    {
        $meta = self::meta($document);

        return $meta['type'].' #'.$meta['number'];
    }

    /**
     * Absolute URL to the document's show page for review and signing.
     */
    public static function url(Model $document): string
    {
        return route(self::meta($document)['route'], $document);
    }
}
