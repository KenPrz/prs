<?php

namespace App\Http\Controllers;

use App\Enums\PaymentRequestFormStatus;
use App\Events\Procurement\DocumentCancelled;
use App\Models\PaymentRequestForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelPaymentRequestFormController extends Controller
{
    /**
     * Cancel an approved payment request form that has not yet been paid out.
     */
    public function __invoke(Request $request, PaymentRequestForm $paymentRequestForm): RedirectResponse
    {
        $this->authorize('cancel', $paymentRequestForm);

        // Hard state guard (Super Admin bypasses the policy's status checks).
        if ($paymentRequestForm->status !== PaymentRequestFormStatus::APPROVED) {
            return back()->with('error', 'Only an approved payment request can be cancelled here.');
        }

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'A cancellation reason is required.'],
        );

        $paymentRequestForm->forceFill(['status' => PaymentRequestFormStatus::CANCELLED])->save();

        activity()
            ->performedOn($paymentRequestForm)
            ->withProperties(['reason' => $validated['reason']])
            ->log('cancelled');

        DocumentCancelled::dispatch($paymentRequestForm, $validated['reason']);

        return to_route('payment-request-forms.show', $paymentRequestForm);
    }
}
