<?php

namespace App\Livewire\PurchaseRequisitions;

use App\Enums\PurchaseRequisitionStatus;
use App\Models\Department;
use App\Models\LineItemUnit;
use App\Models\PurchaseRequisition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Form extends Component
{
    use WithPagination;

    public ?int $prId = null;

    public ?string $prNumber = null;

    public string $title = '';

    public string $description = '';

    /** @var array<int, string|int> */
    public array $departmentIds = [];

    public string $lineSearch = '';

    public int $linePerPage = 75;

    /** @var array<int, int> */
    public array $deletedLineIds = [];

    /** @var array<int, array{unit_id: string, name: string, description: string, quantity: string, price: string}> */
    public array $lineDrafts = [];

    /** @var list<array{unit_id: string, name: string, description: string, quantity: string, price: string}> */
    public array $newLines = [];

    public function mount(?PurchaseRequisition $purchaseRequisition = null): void
    {
        if ($purchaseRequisition !== null) {
            $currentUserId = Auth::id();
            abort_unless($currentUserId !== null, 403);

            abort_unless(
                $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT
                    && $purchaseRequisition->created_by === $currentUserId,
                403
            );

            $this->prId = $purchaseRequisition->id;
            $this->prNumber = $purchaseRequisition->number;
            $purchaseRequisition->load(['requestingDepartments']);

            $this->title = $purchaseRequisition->title;
            $this->description = (string) $purchaseRequisition->description;
            $this->departmentIds = $purchaseRequisition->requestingDepartments->pluck('id')->all();

            if (! $purchaseRequisition->lineItems()->exists()) {
                $this->addLine();
            }
        } else {
            $this->addLine();
        }
    }

    public function addLine(): void
    {
        $this->newLines[] = [
            'unit_id' => '',
            'name' => '',
            'description' => '',
            'quantity' => '1',
            'price' => '',
        ];
    }

    public function removeNewLine(int $index): void
    {
        unset($this->newLines[$index]);
        $this->newLines = array_values($this->newLines);
    }

    public function removeExistingLine(int $lineId): void
    {
        if (! in_array($lineId, $this->deletedLineIds, true)) {
            $this->deletedLineIds[] = $lineId;
        }

        unset($this->lineDrafts[$lineId]);

        $this->ensureValidLinePage();
    }

    public function updatingLineSearch(): void
    {
        $this->resetPage();
    }

    private function ensureValidLinePage(): void
    {
        if ($this->prId === null) {
            return;
        }

        $remaining = PurchaseRequisition::query()
            ->whereKey($this->prId)
            ->firstOrFail()
            ->lineItems()
            ->whereNotIn('id', $this->deletedLineIds)
            ->when($this->lineSearch !== '', function ($query): void {
                $search = '%'.$this->lineSearch.'%';
                $query->where(function ($q) use ($search): void {
                    $q->where('code', 'like', $search)
                        ->orWhere('name', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            })
            ->count();

        $lastPage = max(1, (int) ceil($remaining / $this->linePerPage));

        if ($this->getPage() > $lastPage) {
            $this->setPage($lastPage);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'departmentIds' => ['array'],
            'departmentIds.*' => ['integer', 'exists:departments,id'],
            'newLines' => ['array'],
            'newLines.*.unit_id' => ['nullable', 'integer', 'exists:line_item_units,id'],
            'newLines.*.name' => ['required', 'string', 'max:255'],
            'newLines.*.description' => ['nullable', 'string'],
            'newLines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'newLines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lineDrafts' => ['array'],
            'lineDrafts.*.unit_id' => ['nullable', 'integer', 'exists:line_item_units,id'],
            'lineDrafts.*.name' => ['required', 'string', 'max:255'],
            'lineDrafts.*.description' => ['nullable', 'string'],
            'lineDrafts.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lineDrafts.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            $currentUserId = Auth::id();
            abort_unless($currentUserId !== null, 403);

            if ($this->prId !== null) {
                $pr = PurchaseRequisition::query()->whereKey($this->prId)->lockForUpdate()->firstOrFail();
                abort_unless(
                    $pr->status === PurchaseRequisitionStatus::DRAFT && $pr->created_by === $currentUserId,
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
                    'created_by' => $currentUserId,
                ]);
                $this->prId = $pr->id;
            }

            $pr->requestingDepartments()->sync($validated['departmentIds']);

            $existingLineIds = $pr->lineItems()->pluck('id')->all();
            $deletedIds = array_values(array_intersect(
                $existingLineIds,
                array_map('intval', array_unique($this->deletedLineIds))
            ));

            $remainingExistingCount = count($existingLineIds) - count($deletedIds);
            $newLinesCount = count($validated['newLines'] ?? []);

            if (($remainingExistingCount + $newLinesCount) < 1) {
                throw ValidationException::withMessages([
                    'newLines' => __('At least one line item is required.'),
                ]);
            }

            if ($deletedIds !== []) {
                $pr->lineItems()->whereKey($deletedIds)->delete();
            }

            $deletedLookup = array_flip($deletedIds);

            foreach (($validated['lineDrafts'] ?? []) as $lineId => $line) {
                $lineId = (int) $lineId;

                if (! in_array($lineId, $existingLineIds, true) || isset($deletedLookup[$lineId])) {
                    continue;
                }

                $pr->lineItems()->whereKey($lineId)->update([
                    'unit_id' => $line['unit_id'] !== '' && $line['unit_id'] !== null ? $line['unit_id'] : null,
                    'name' => $line['name'],
                    'description' => $line['description'] ?: null,
                    'quantity' => $line['quantity'],
                    'price' => $line['price'] !== '' && $line['price'] !== null ? $line['price'] : null,
                ]);
            }

            foreach (($validated['newLines'] ?? []) as $line) {
                $pr->lineItems()->create([
                    'unit_id' => $line['unit_id'] !== '' && $line['unit_id'] !== null ? $line['unit_id'] : null,
                    'name' => $line['name'],
                    'description' => $line['description'] ?: null,
                    'quantity' => $line['quantity'],
                    'price' => $line['price'] !== '' && $line['price'] !== null ? $line['price'] : null,
                ]);
            }
        });

        $this->redirect(route('purchase-requisitions.show', $this->prId), navigate: true);
    }

    public function render()
    {
        $paginatedLines = null;

        if ($this->prId !== null) {
            $query = PurchaseRequisition::query()
                ->whereKey($this->prId)
                ->firstOrFail()
                ->lineItems()
                ->orderBy('id')
                ->whereNotIn('id', $this->deletedLineIds)
                ->when($this->lineSearch !== '', function ($lineItems): void {
                    $search = '%'.$this->lineSearch.'%';
                    $lineItems->where(function ($query) use ($search): void {
                        $query->where('code', 'like', $search)
                            ->orWhere('name', 'like', $search)
                            ->orWhere('description', 'like', $search);
                    });
                });

            $paginatedLines = $query->paginate($this->linePerPage);

            foreach ($paginatedLines as $lineItem) {
                if (! isset($this->lineDrafts[$lineItem->id])) {
                    $this->lineDrafts[$lineItem->id] = [
                        'unit_id' => $lineItem->unit_id ? (string) $lineItem->unit_id : '',
                        'name' => $lineItem->name,
                        'description' => (string) $lineItem->description,
                        'quantity' => (string) $lineItem->quantity,
                        'price' => $lineItem->price !== null ? (string) $lineItem->price : '',
                    ];
                }
            }
        }

        return view('livewire.purchase-requisitions.form', [
            'departments' => Department::query()->orderBy('name')->get(),
            'units' => LineItemUnit::query()->orderBy('name')->get(),
            'paginatedLines' => $paginatedLines,
        ]);
    }
}
