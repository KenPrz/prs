<?php

namespace App\Livewire\PurchaseRequisitions;

use App\Enums\PurchaseRequisitionStatus;
use App\Models\PurchaseRequisition;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $status = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = PurchaseRequisition::query()
            ->with(['createdBy', 'requestingDepartments', 'lineItems'])
            ->withSum('lineItems as total_amount', DB::raw('quantity * price'))
            ->latest('updated_at');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhere('title', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        $statuses = PurchaseRequisitionStatus::list(false);
        return view('livewire.purchase-requisitions.index', [
            'requisitions' => $query->paginate(15),
            'statuses' => $statuses,
        ]);
    }
}
