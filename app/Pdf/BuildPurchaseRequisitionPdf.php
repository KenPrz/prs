<?php

namespace App\Pdf;

use App\Models\Document;
use App\Models\PurchaseRequisition;
use App\Services\DocxTemplateProcessor;
use Illuminate\Support\Facades\Storage;

class BuildPurchaseRequisitionPdf
{
    public function __construct(
        private readonly DocxTemplateProcessor $processor,
        private readonly MergeMediaIntoPdf $mergeService,
    ) {}

    /**
     * Render the PR's document template and merge its attachments into a single PDF binary.
     * Once a final document has been bound (marked ready for PO), that snapshot is
     * served instead of re-rendering. Pass $withAttachments=false for a standalone
     * live render of just the document.
     *
     * Returns null when the resolved document has no docx template.
     */
    public function build(PurchaseRequisition $purchaseRequisition, bool $withAttachments = true): ?string
    {
        $bound = $purchaseRequisition->getFirstMedia('final_document');
        if ($withAttachments && $bound !== null) {
            return Storage::disk($bound->disk)->get($bound->getPathRelativeToRoot());
        }

        $purchaseRequisition->loadMissing([
            'requisitionDocument',
            'departments',
            'lineItems.unit',
            'notes.user',
            'requestor',
            'workflowInstance.actions.actor',
            'workflowInstance.actions.stepInstance',
            'workflowInstance.steps',
        ]);

        $document = $purchaseRequisition->requisitionDocument
            ?? Document::defaultPurchaseRequisitionTemplate();

        if (! $document?->hasMedia('docx_template')) {
            return null;
        }

        $pdfBinary = $this->processor->renderPurchaseRequisition($document, $purchaseRequisition);

        if (! $withAttachments) {
            return $pdfBinary;
        }

        return $this->mergeService->merge($pdfBinary, $purchaseRequisition->getMedia('attachments'));
    }
}
