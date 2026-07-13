<?php

namespace App\Pdf;

use App\Models\Document;
use App\Models\ReceivingReport;
use App\Services\DocxTemplateProcessor;
use Illuminate\Support\Facades\Storage;

class BuildReceivingReportPdf
{
    public function __construct(
        private readonly DocxTemplateProcessor $processor,
        private readonly MergeMediaIntoPdf $mergeService,
    ) {}

    /**
     * Render the RR's document template and merge its attachments into a single PDF binary.
     * Once a final document has been bound (verified), that snapshot is served instead
     * of re-rendering. Pass $withAttachments=false for a standalone live render of just
     * the document.
     *
     * Returns null when the default receiving report document has no docx template.
     */
    public function build(ReceivingReport $receivingReport, bool $withAttachments = true): ?string
    {
        $bound = $receivingReport->getFirstMedia('final_document');
        if ($withAttachments && $bound !== null) {
            return Storage::disk($bound->disk)->get($bound->getPathRelativeToRoot());
        }

        $receivingReport->loadMissing([
            'purchaseOrder.supplier',
            'receivedBy',
            'items.purchaseOrderItem.lineItem.unit',
            'workflowInstance.actions.actor',
            'workflowInstance.steps',
        ]);

        $document = Document::defaultReceivingReportTemplate();

        if (! $document?->hasMedia('docx_template')) {
            return null;
        }

        $pdfBinary = $this->processor->renderReceivingReport($document, $receivingReport);

        if (! $withAttachments) {
            return $pdfBinary;
        }

        return $this->mergeService->merge($pdfBinary, $receivingReport->getMedia('attachments'));
    }
}
