<?php

namespace App\Pdf;

use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Services\DocxTemplateProcessor;
use Illuminate\Support\Facades\Storage;

class BuildPurchaseOrderPdf
{
    public function __construct(
        private readonly DocxTemplateProcessor $processor,
        private readonly MergeMediaIntoPdf $mergeService,
    ) {}

    /**
     * Render the PO's document template and merge its attachments into a single PDF binary.
     * Once a final document has been bound (marked as ordered), that snapshot is served
     * instead of re-rendering. Pass $withAttachments=false for a standalone live render
     * of just the document.
     *
     * Returns null when the resolved document has no docx template.
     */
    public function build(PurchaseOrder $purchaseOrder, bool $withAttachments = true): ?string
    {
        $bound = $purchaseOrder->getFirstMedia('final_document');
        if ($withAttachments && $bound !== null) {
            return Storage::disk($bound->disk)->get($bound->getPathRelativeToRoot());
        }

        $purchaseOrder->loadMissing([
            'supplier',
            'items.lineItem.unit',
            'billToAddress',
            'shipToAddress',
            'purchaseRequisition.requestor',
            'purchaseRequisition.document',
            'workflowInstance.actions.actor',
            'workflowInstance.steps',
        ]);

        $document = $purchaseOrder->purchaseRequisition?->document
            ?? $purchaseOrder->document
            ?? Document::defaultPurchaseOrderTemplate();

        if (! $document?->hasMedia('docx_template')) {
            return null;
        }

        $pdfBinary = $this->processor->renderPurchaseOrder($document, $purchaseOrder);

        if (! $withAttachments) {
            return $pdfBinary;
        }

        return $this->mergeService->merge($pdfBinary, $purchaseOrder->getMedia('attachments'));
    }
}
