<?php

namespace App\Livewire\PurchaseRequisitions;

use App\Enums\PurchaseRequisitionStatus;
use App\Models\Department;
use App\Models\LineItemUnit;
use App\Models\PurchaseRequisition;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Form extends Component
{
    public ?int $prId = null;

    public ?string $prNumber = null;

    public string $title = '';

    public string $description = '';

    /** @var array<int, string|int> */
    public array $departmentIds = [];

    /** @var list<array{id: int|null, code: string, unit_id: string, name: string, description: string, quantity: string, price: string}> */
    public array $lines = [];

    public function mount(?PurchaseRequisition $purchaseRequisition = null): void
    {
        if ($purchaseRequisition !== null) {
            abort_unless(
                $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT
                    && $purchaseRequisition->created_by === auth()->id(),
                403
            );

            $this->prId = $purchaseRequisition->id;
            $this->prNumber = $purchaseRequisition->number;
            $purchaseRequisition->load(['lineItems', 'requestingDepartments']);

            $this->title = $purchaseRequisition->title;
            $this->description = (string) $purchaseRequisition->description;
            $this->departmentIds = $purchaseRequisition->requestingDepartments->pluck('id')->all();

            $this->lines = $purchaseRequisition->lineItems->map(fn ($li) => [
                'id' => $li->id,
                'code' => $li->code,
                'unit_id' => $li->unit_id ? (string) $li->unit_id : '',
                'name' => $li->name,
                'description' => (string) $li->description,
                'quantity' => (string) $li->quantity,
                'price' => (string) $li->price,
            ])->all();

            if ($this->lines === []) {
                $this->addLine();
            }
        } else {
            $this->addLine();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'id' => null,
            'code' => '',
            'unit_id' => '',
            'name' => '',
            'description' => '',
            'quantity' => '1',
            'price' => '0',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'departmentIds' => ['array'],
            'departmentIds.*' => ['integer', 'exists:departments,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.code' => ['required', 'string', 'max:255'],
            'lines.*.unit_id' => ['nullable', 'string', 'exists:line_item_units,id'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $codes = collect($validated['lines'])->pluck('code')->map(fn ($c) => mb_strtolower($c));
        if ($codes->count() !== $codes->unique()->count()) {
            $this->addError('lines', __('Each line item code must be unique within this requisition.'));

            return;
        }

        DB::transaction(function () use ($validated): void {
            if ($this->prId !== null) {
                $pr = PurchaseRequisition::query()->whereKey($this->prId)->lockForUpdate()->firstOrFail();
                abort_unless(
                    $pr->status === PurchaseRequisitionStatus::DRAFT && $pr->created_by === auth()->id(),
                    403
                );

                $pr->update([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?: null,
                ]);
            } else {
                $pr = PurchaseRequisition::create([
                    'number' => PurchaseRequisition::nextNumber(),
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?: null,
                    'status' => PurchaseRequisitionStatus::DRAFT,
                    'created_by' => auth()->id(),
                ]);
                $this->prId = $pr->id;
            }

            $pr->requestingDepartments()->sync($validated['departmentIds']);

            $pr->lineItems()->delete();

            foreach ($validated['lines'] as $line) {
                $pr->lineItems()->create([
                    'code' => $line['code'],
                    'unit_id' => $line['unit_id'] !== '' ? (int) $line['unit_id'] : null,
                    'name' => $line['name'],
                    'description' => $line['description'] ?: null,
                    'quantity' => $line['quantity'],
                    'price' => $line['price'],
                ]);
            }
        });

        $this->redirect(route('purchase-requisitions.show', $this->prId), navigate: true);
    }

    public function render()
    {
        return view('livewire.purchase-requisitions.form', [
            'departments' => Department::query()->orderBy('name')->get(),
            'units' => LineItemUnit::query()->orderBy('name')->get(),
        ])
            ->layout('layouts.app')
            ->title(
                $this->prId && $this->prNumber
                    ? __('Edit').' — '.$this->prNumber
                    : __('New purchase requisition')
            );
    }
}
