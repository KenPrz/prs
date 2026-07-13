<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequisition;
use App\Pdf\BuildPurchaseRequisitionPdf;
use Illuminate\Http\Response;

class RenderPurchaseRequisitionPdfController extends Controller
{
    public function __invoke(
        PurchaseRequisition $purchaseRequisition,
        BuildPurchaseRequisitionPdf $builder,
    ): Response {
        $this->authorize('view', $purchaseRequisition);

        // ?standalone=1 renders just the document, without attachments.
        $withAttachments = ! request()->boolean('standalone');

        $merged = $builder->build($purchaseRequisition, $withAttachments);

        abort_unless($merged !== null, 404);

        $filename = $purchaseRequisition->pr_number
            ? sprintf('purchase-requisition-%s.pdf', $purchaseRequisition->pr_number)
            : sprintf('purchase-requisition-%d.pdf', $purchaseRequisition->id);

        return response($merged, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
