<?php

namespace App\Pdf;

use App\Models\Document;
use App\Models\PaymentRequestForm;
use App\Services\DocxTemplateProcessor;
use Illuminate\Support\Facades\Storage;

class BuildPaymentRequestFormPdf
{
    public function __construct(
        private readonly DocxTemplateProcessor $processor,
        private readonly MergeMediaIntoPdf $mergeService,
    ) {}

    /**
     * Render the PRF's document template and merge its attachments into a single PDF binary.
     * Once a final document has been bound (on approval), that snapshot is served instead
     * of re-rendering. Pass $withAttachments=false for a standalone live render of just
     * the document.
     *
     * Returns null when the resolved document has no docx template.
     */
    public function build(PaymentRequestForm $paymentRequestForm, bool $withAttachments = true): ?string
    {
        $bound = $paymentRequestForm->getFirstMedia('final_document');
        if ($withAttachments && $bound !== null) {
            return Storage::disk($bound->disk)->get($bound->getPathRelativeToRoot());
        }

        $paymentRequestForm->loadMissing([
            'document',
            'departments',
            'supplier',
            'requestor',
            'workflowInstance.actions.actor',
            'workflowInstance.steps',
        ]);

        $document = $paymentRequestForm->document
            ?? Document::defaultPaymentRequestFormTemplate();

        if (! $document?->hasMedia('docx_template')) {
            return null;
        }

        $pdfBinary = $this->processor->renderPaymentRequestForm($document, $paymentRequestForm);

        if (! $withAttachments) {
            return $pdfBinary;
        }

        return $this->mergeService->merge($pdfBinary, $paymentRequestForm->getMedia('attachments'));
    }
}
