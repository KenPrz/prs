<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequestForm;
use App\Pdf\BuildPaymentRequestFormPdf;
use Illuminate\Http\Response;

class RenderPaymentRequestFormPdfController extends Controller
{
    public function __invoke(
        PaymentRequestForm $paymentRequestForm,
        BuildPaymentRequestFormPdf $builder,
    ): Response {
        $this->authorize('view', $paymentRequestForm);

        // ?standalone=1 renders just the document, without attachments.
        $withAttachments = ! request()->boolean('standalone');

        $merged = $builder->build($paymentRequestForm, $withAttachments);

        abort_unless($merged !== null, 404);

        $filename = $paymentRequestForm->prf_number
            ? sprintf('payment-request-%s.pdf', $paymentRequestForm->prf_number)
            : sprintf('payment-request-%d.pdf', $paymentRequestForm->id);

        return response($merged, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
