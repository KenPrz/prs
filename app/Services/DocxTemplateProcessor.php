<?php

namespace App\Services;

use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\ReceivingReportStatus;
use App\Enums\WorkflowStepType;
use App\Models\Document;
use App\Models\PaymentRequestForm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DocxTemplateProcessor
{
    public function __construct(
        private CarboneClient $carbone,
        private SignatureImageComposer $signatures,
        private DocxSignatureStamper $stamper,
    ) {}

    // -------------------------------------------------------------------------
    // Public render methods — load the template, assemble data, render via Carbone
    // -------------------------------------------------------------------------

    public function renderPurchaseRequisition(Document $document, PurchaseRequisition $pr): string
    {
        return $this->carbone->render(
            $this->stamper->stamp($this->load($document), $this->signatureImagesForPurchaseRequisition($pr)),
            $this->dataForPurchaseRequisition($pr),
        );
    }

    public function renderPurchaseOrder(Document $document, PurchaseOrder $po): string
    {
        return $this->carbone->render(
            $this->stamper->stamp($this->load($document), [
                'prepared_by' => $this->signatures->forUser($po->purchaseRequisition?->requestor),
                'approvers' => $this->signatures->composePng($this->approveActors($po->workflowInstance)),
            ]),
            $this->dataForPurchaseOrder($po),
        );
    }

    public function renderReceivingReport(Document $document, ReceivingReport $rr): string
    {
        return $this->carbone->render(
            $this->stamper->stamp($this->load($document), [
                'received_by' => $rr->status !== ReceivingReportStatus::DRAFT
                    ? $this->signatures->forUser($rr->receivedBy)
                    : null,
                'approved_by' => $this->signatures->composePng($this->approveActors($rr->workflowInstance)),
            ]),
            $this->dataForReceivingReport($rr),
        );
    }

    public function renderPaymentRequestForm(Document $document, PaymentRequestForm $prf): string
    {
        $approvals = ($prf->workflowInstance?->actions ?? collect())
            ->filter(fn ($d) => $d->action->value === 'APPROVE')->values();

        $images = ['prepared_by' => $this->signatures->forUser($prf->requestor)];
        for ($i = 1; $i <= 4; $i++) {
            $images["approver_{$i}"] = $this->signatures->forUser($approvals->get($i - 1)?->actor);
        }

        return $this->carbone->render(
            $this->stamper->stamp($this->load($document), $images),
            $this->dataForPaymentRequestForm($prf),
        );
    }

    // -------------------------------------------------------------------------
    // Private data assemblers — same business logic as before, now returning
    // plain arrays for Carbone instead of mutating a TemplateProcessor
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function dataForPurchaseRequisition(PurchaseRequisition $pr): array
    {
        $pr->loadMissing([
            'departments',
            'requestor',
            'toBeOrderedBy',
            'lineItems.unit',
            'workflowInstance.steps',
            'workflowInstance.actions.actor',
            'workflowInstance.actions.stepInstance',
        ]);

        $departments = $pr->departments->map(fn ($d) => $d->name)->join(', ');
        $requestor = $pr->requestor?->name ?? '—';

        $instance = $pr->workflowInstance;
        $approvals = ($instance?->actions ?? collect())
            ->filter(fn ($d) => $d->action->value === 'APPROVE')
            ->values();

        // "Checked by" is the injected department-head step; everything after it
        // is the regular approval chain ("Approved by"). When no department-head
        // step exists, all approvals fall under "Approved by".
        $deptHeadStepOrder = $instance?->steps
            ->firstWhere('step_type', WorkflowStepType::DepartmentHead)?->step_order;

        $checkedByActors = $approvals
            ->filter(fn ($d) => $deptHeadStepOrder !== null && $d->stepInstance?->step_order === $deptHeadStepOrder)
            ->map(fn ($d) => $d->actor)
            ->filter()->unique(fn ($actor) => $actor->id)->values();

        $approverActors = $approvals
            ->filter(fn ($d) => $deptHeadStepOrder === null || $d->stepInstance?->step_order !== $deptHeadStepOrder)
            ->map(fn ($d) => $d->actor)
            ->filter()->unique(fn ($actor) => $actor->id)->values();

        $checkedByNames = $checkedByActors->map(fn ($actor) => $actor->name)->filter()->values();
        $approverNames = $approverActors->map(fn ($actor) => $actor->name)->filter()->values();

        $items = $pr->lineItems
            ->reject(fn ($item) => $item->is_omitted)
            ->map(fn ($item) => [
                'qty' => $item->quantity,
                'unit' => $item->unit?->code ?? '—',
                'description' => strtoupper($item->name ?? '—'),
                'descriptionDetail' => '',
                'unitPrice' => number_format((float) $item->price, 2, '.', ','),
                'amount' => number_format($item->quantity * (float) $item->price, 2, '.', ','),
                'remarks' => '',
            ])->values()->toArray();

        return [
            'pr_number' => $pr->pr_number ?? '—',
            'pr_date' => $pr->created_at?->format('n/j/Y') ?? '—',
            'department' => $departments ?: '—',
            'date_needed' => $pr->delivery_date ? Carbon::parse($pr->delivery_date)->format('n/j/Y') : '—',
            'requested_by' => strtoupper($requestor),
            // Joined with newlines; the template applies :convCRLF so each
            // department head / approver renders on its own line.
            'checked_by' => $checkedByNames->map(fn ($n) => strtoupper($n))->join("\n") ?: '—',
            'purpose_type' => $pr->purpose_type?->value ?? '',
            'expected_useful_life' => $pr->expected_useful_life ?? '—',
            'accounting_details' => $pr->price_type?->value ?? '',
            'received_by' => strtoupper($pr->toBeOrderedBy?->name ?? '—'),
            'received_date' => '—',
            'approved_by' => $approverNames->map(fn ($n) => strtoupper($n))->join("\n") ?: '—',
            'items' => $items ?: [[
                'qty' => '—', 'unit' => '—', 'description' => '—',
                'descriptionDetail' => '', 'unitPrice' => '—', 'amount' => '—', 'remarks' => '',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function dataForPurchaseOrder(PurchaseOrder $po): array
    {
        $pr = $po->purchaseRequisition;
        $supplier = $po->supplier;
        $billTo = $po->billToAddress;
        $shipTo = $po->shipToAddress;

        $preparer = $pr?->requestor?->name ?? '—';
        $items = ($po->items ?? collect())->reject(fn ($item) => $item->is_omitted)->values();

        $approvals = $po->workflowInstance?->actions
            ->filter(fn ($d) => $d->action->value === 'APPROVE')
            ->values() ?? collect();

        $approversList = $approvals->map(function ($a) {
            $parts = explode(' ', trim($a->actor->name));
            $first = array_shift($parts);

            return strtoupper(substr($first, 0, 1).'.'.implode(' ', $parts));
        })->join(' / ');

        $billToText = $billTo
            ? implode("\n", array_filter([
                $billTo->recipient_name,
                $billTo->street,
                trim($billTo->city.', '.$billTo->province),
            ]))
            : "OpenPRS Trading Corp.\n100 Commerce Avenue\nMakati City";

        $shipToText = $shipTo
            ? implode("\n", array_filter([
                $shipTo->recipient_name,
                $shipTo->street,
                trim($shipTo->city.', '.$shipTo->province),
            ]))
            : "OpenPRS Trading Corp.\n100 Commerce Avenue\nMakati City";

        $deliveryDate = $po->expected_delivery_date
            ? Carbon::parse($po->expected_delivery_date)->format('n/j/Y')
            : '—';

        $itemRows = $items->map(fn ($item, $idx) => [
            'no' => $idx + 1,
            'description' => strtoupper($item->lineItem?->name ?? '—'),
            'delivery_date' => $deliveryDate,
            'unit' => $item->lineItem?->unit?->code ?? '—',
            'quantity' => $item->quantity,
            'unit_price' => number_format((float) $item->price, 2, '.', ','),
            'total' => number_format($item->quantity * (float) $item->price, 2, '.', ','),
        ])->values()->toArray();

        return [
            'po_number' => $po->po_number ?? '—',
            'po_date' => $po->created_at ? $po->created_at->format('n/j/Y') : '—',
            'supplier_name' => strtoupper($supplier?->name ?? '—'),
            'supplier_address' => $supplier?->address ?? '—',
            'supplier_contact' => strtoupper($supplier?->contact_person_1 ?? '—'),
            'bill_to_address' => $billToText,
            'ship_to_address' => $shipToText,
            'payment_terms' => $po->payment_terms ?? '—',
            'currency' => $po->currency ?? '—',
            'pr_number' => $pr?->pr_number ?? '—',
            'remarks' => $po->remarks ?? '',
            'prepared_by' => strtoupper($preparer),
            'price_type_label' => $po->price_type->label(),
            'net_amount' => number_format($po->net_total, 2, '.', ','),
            'vat_rate' => match ($po->price_type) {
                PriceType::VAT_INCLUSIVE, PriceType::VAT_EXCLUSIVE => '12%',
                PriceType::NON_VAT, PriceType::ZERO_VAT => '0%',
            },
            'vat_amount' => number_format($po->vat_total, 2, '.', ','),
            'grand_total' => number_format($po->gross_total, 2, '.', ','),
            'approvers_list' => $approversList ?: '—',
            'items' => $itemRows,
        ];
    }

    /** @return array<string, mixed> */
    private function dataForReceivingReport(ReceivingReport $rr): array
    {
        $po = $rr->purchaseOrder;
        $supplier = $po?->supplier;
        $receiver = $rr->receivedBy;

        $approvals = $rr->workflowInstance?->actions
            ->filter(fn ($d) => $d->action->value === 'APPROVE')
            ->values() ?? collect();

        $approvedBy = $approvals->map(fn ($a) => strtoupper($a->actor->name ?? '—'))->join(' / ');

        $itemRows = $rr->items->map(fn ($item, $idx) => [
            'no' => $idx + 1,
            'description' => strtoupper($item->purchaseOrderItem?->lineItem?->name ?? '—'),
            'unit' => strtolower($item->purchaseOrderItem?->lineItem?->unit?->code ?? '—'),
            'ordered_qty' => $item->purchaseOrderItem?->quantity ?? '—',
            'received_qty' => $item->quantity_received,
            'rejected_qty' => $item->quantity_rejected ?? 0,
            'remarks' => $item->remarks ?? '',
        ])->values()->toArray();

        return [
            'rr_number' => $rr->rr_number ?? '—',
            'rr_date' => $rr->received_date ? Carbon::parse($rr->received_date)->format('n/j/Y') : '—',
            'po_number' => $po?->po_number ?? '—',
            'supplier_name' => strtoupper($supplier?->name ?? '—'),
            'received_by' => strtoupper($receiver?->name ?? '—'),
            'approved_by' => $approvedBy ?: '—',
            'general_remarks' => $rr->remarks ?? '',
            'items' => $itemRows,
        ];
    }

    /** @return array<string, mixed> */
    private function dataForPaymentRequestForm(PaymentRequestForm $prf): array
    {
        $decisions = $prf->workflowInstance?->actions ?? collect();
        $approvals = $decisions->filter(fn ($d) => $d->action->value === 'APPROVE')->values();

        $data = [
            'prf_number' => $prf->prf_number ?? '—',
            'prf_date' => $prf->created_at ? $prf->created_at->format('m-d-Y') : '—',
            'department' => strtoupper($prf->departments->first()?->name ?? 'GENERAL ADMINISTRATION'),
            'payee_name' => $prf->supplier?->name ?? '—',
            'amount' => $prf->amount ? number_format((float) $prf->amount, 2, '.', ',') : '—',
            'invoice_number' => $prf->invoice_number ?? '—',
            'purpose' => $prf->description ?? '—',
            'prepared_by' => strtoupper($prf->requestor?->name ?? '—'),
            'due_date' => $prf->due_date ? Carbon::parse($prf->due_date)->format('m-d-Y') : '—',
        ];

        // Fixed 4-slot approvers
        for ($i = 1; $i <= 4; $i++) {
            $data["approver_{$i}_name"] = strtoupper($approvals->get($i - 1)?->actor?->name ?? '');
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * The PR template's signature slots (see word/media/sig_*.png).
     *
     * @return array<string, string|null>
     */
    private function signatureImagesForPurchaseRequisition(PurchaseRequisition $pr): array
    {
        $pr->loadMissing(['requestor', 'toBeOrderedBy', 'workflowInstance.actions.actor', 'workflowInstance.actions.stepInstance', 'workflowInstance.steps']);

        $instance = $pr->workflowInstance;
        $approvals = ($instance?->actions ?? collect())
            ->filter(fn ($d) => $d->action->value === 'APPROVE')->values();

        $deptHeadStepOrder = $instance?->steps
            ->firstWhere('step_type', WorkflowStepType::DepartmentHead)?->step_order;

        $checkedByActors = $approvals
            ->filter(fn ($d) => $deptHeadStepOrder !== null && $d->stepInstance?->step_order === $deptHeadStepOrder)
            ->map(fn ($d) => $d->actor)->filter()->unique(fn ($actor) => $actor->id)->values();

        $approverActors = $approvals
            ->filter(fn ($d) => $deptHeadStepOrder === null || $d->stepInstance?->step_order !== $deptHeadStepOrder)
            ->map(fn ($d) => $d->actor)->filter()->unique(fn ($actor) => $actor->id)->values();

        $orderedStates = [
            PurchaseRequisitionStatus::READY_FOR_PO,
            PurchaseRequisitionStatus::PARTIALLY_ORDERED,
            PurchaseRequisitionStatus::FULLY_ALLOCATED,
            PurchaseRequisitionStatus::FULLY_ORDERED,
            PurchaseRequisitionStatus::CLOSED,
        ];

        return [
            'requested_by' => $this->signatures->forUser($pr->requestor),
            'checked_by' => $this->signatures->composePng($checkedByActors),
            'approved_by' => $this->signatures->composePng($approverActors),
            // The orderer acknowledges at the ready-for-PO seal.
            'received_by' => \in_array($pr->status, $orderedStates, true)
                ? $this->signatures->forUser($pr->toBeOrderedBy)
                : null,
        ];
    }

    /**
     * Unique actors who approved on the given workflow instance.
     *
     * @return Collection<int, User>
     */
    private function approveActors($instance): Collection
    {
        return ($instance?->actions ?? collect())
            ->filter(fn ($d) => $d->action->value === 'APPROVE')
            ->map(fn ($d) => $d->actor)
            ->filter()
            ->unique(fn ($actor) => $actor->id)
            ->values();
    }

    private function load(Document $document): string
    {
        $media = $document->getFirstMedia('docx_template');

        if ($media === null) {
            abort(404, 'No .docx template is attached to this document.');
        }

        return (string) file_get_contents($media->getPath());
    }
}
