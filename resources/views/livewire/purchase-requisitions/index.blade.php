<div class="flex flex-col gap-6">
    <x-card>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Purchase Requisitions') }}</flux:heading>
                <flux:subheading class="mt-1">
                    {{ __('Manage and track all purchase requisition requests') }}
                </flux:subheading>
            </div>
            <flux:button variant="primary" :href="route('purchase-requisitions.create')" wire:navigate icon="plus">
                {{ __('New Requisition') }}
            </flux:button>
        </div>
    </x-card>

    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-muted-foreground">{{ __('Filter by status') }}</span>
                <div class="flex items-center gap-1 p-1 bg-muted dark:bg-muted/20 rounded-lg border border-border">
                    <flux:button wire:click="$set('status', '')" size="sm" :variant="$status === '' ? 'primary' : 'ghost'"
                        class="cursor-pointer {{ $status === '' ? '' : 'text-muted-foreground' }}">
                        {{ __('All') }}
                    </flux:button>
                    @foreach ($statuses as $statusValue => $statusTitle)
                        <flux:button wire:click="$set('status', '{{ $statusValue }}')" size="sm"
                            :variant="$status === $statusValue ? 'primary' : 'ghost'"
                            class="cursor-pointer {{ $status === $statusValue ? '' : 'text-muted-foreground' }}">
                            {{ $statusValue }}
                        </flux:button>
                    @endforeach
                </div>
            </div>

            <div class="w-full sm:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="{{ __('Search requisitions...') }}" />
            </div>
        </div>
    </x-card>
    <x-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('PR Number') }}</flux:table.column>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Department') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Workflow') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($requisitions as $pr)
                    <flux:table.row :key="$pr->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20">
                                    <flux:icon icon="document-text" class="size-5 text-primary" />
                                </div>
                                <div>
                                    <flux:link :href="route('purchase-requisitions.show', $pr)" wire:navigate
                                        class="font-semibold text-foreground">
                                        {{ $pr->number }}
                                    </flux:link>
                                    <flux:text size="sm" variant="subtle">{{ $pr->created_at->format('m/d/Y') }}
                                    </flux:text>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="max-w-xs">
                                <flux:text class="font-medium text-foreground truncate" title="{{ $pr->title }}">
                                    {{ $pr->title }}
                                </flux:text>
                                <flux:text size="sm" variant="subtle">by {{ $pr->createdBy?->name ?? 'User' }}
                                </flux:text>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text size="sm">
                                {{ $pr->requestingDepartments->pluck('name')->join(', ') ?: '—' }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="font-semibold text-foreground">
                                ₱{{ number_format($pr->total_amount ?? 0, 2) }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$pr->status->badgeColor()" inset="top bottom">
                                {{ str($pr->status->value)->title() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1">
                                <flux:icon icon="check-circle" class="size-4 text-emerald-500" />
                                <div class="h-px w-2 bg-border"></div>
                                @if (in_array($pr->status, [
                                        \App\Enums\PurchaseRequisitionStatus::PENDING,
                                        \App\Enums\PurchaseRequisitionStatus::APPROVED,
                                        \App\Enums\PurchaseRequisitionStatus::REJECTED,
                                    ], true))
                                    <flux:icon icon="check-circle" class="size-4 text-emerald-500" />
                                @else
                                    <div class="size-4 rounded-full border border-border"></div>
                                @endif
                                <div class="h-px w-2 bg-border"></div>
                                @if ($pr->status === \App\Enums\PurchaseRequisitionStatus::APPROVED)
                                    <flux:icon icon="check-circle" class="size-4 text-emerald-500" />
                                @elseif ($pr->status === \App\Enums\PurchaseRequisitionStatus::REJECTED)
                                    <flux:icon icon="x-circle" class="size-4 text-red-500" />
                                @else
                                    <div class="size-4 rounded-full border border-border"></div>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button :href="route('purchase-requisitions.show', $pr)" wire:navigate size="sm" square
                                    icon="eye" variant="ghost" />
                                <flux:dropdown>
                                    <flux:button size="sm" square icon="ellipsis-horizontal" variant="ghost" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item variant="danger" icon="trash">{{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center text-muted-foreground">
                            {{ __('No purchase requisitions yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-card>

    <div>
        {{ $requisitions->links() }}
    </div>
</div>