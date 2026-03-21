<?php

namespace App\Livewire\PurchaseRequisitions;

use App\Models\PurchaseRequisition;
use Livewire\Component;

class Show extends Component
{
    public PurchaseRequisition $purchaseRequisition;

    public function mount(PurchaseRequisition $purchaseRequisition): void
    {
        $this->purchaseRequisition = $purchaseRequisition->load([
            'lineItems.lineItemUnit',
            'createdBy',
            'requestingDepartments',
        ]);
    }

    public function render()
    {
        $total = $this->purchaseRequisition->lineItems->sum(
            fn ($li) => (float) $li->quantity * (float) $li->price
        );

        return view('livewire.purchase-requisitions.show', [
            'total' => $total,
        ])
            ->layout('layouts.app')
            ->title($this->purchaseRequisition->number.' — '.$this->purchaseRequisition->title);
    }
}
