<div class="flex flex-col gap-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-3">
                    <flux:heading size="xl">{{ $purchaseRequisition->number }}</flux:heading>
                    <flux:badge size="sm" inset="top bottom">{{ $purchaseRequisition->status->value }}</flux:badge>
                </div>
                <flux:heading size="lg" class="!font-medium !text-zinc-700 dark:!text-zinc-200">
                    {{ $purchaseRequisition->title }}
                </flux:heading>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($purchaseRequisition->status === \App\Enums\PurchaseRequisitionStatus::DRAFT && $purchaseRequisition->created_by === auth()->id())
                    <flux:button variant="primary" :href="route('purchase-requisitions.edit', $purchaseRequisition)" wire:navigate icon="pencil-square">
                        {{ __('Edit') }}
                    </flux:button>
                @endif
                <flux:button variant="ghost" :href="route('purchase-requisitions.index')" wire:navigate icon="arrow-left">
                    {{ __('Back to list') }}
                </flux:button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <flux:card class="space-y-4 lg:col-span-2">
                <flux:heading size="lg">{{ __('Details') }}</flux:heading>
                <flux:separator variant="subtle" />
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('Description') }}
                    </flux:text>
                    <flux:text class="mt-1 whitespace-pre-wrap">
                        {{ $purchaseRequisition->description ?: '—' }}
                    </flux:text>
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Meta') }}</flux:heading>
                <flux:separator variant="subtle" />
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Created by') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-white">{{ $purchaseRequisition->createdBy?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-white">
                            {{ $purchaseRequisition->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Last updated') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-white">
                            {{ $purchaseRequisition->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                        </dd>
                    </div>
                </dl>
            </flux:card>
        </div>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Requesting departments') }}</flux:heading>
            <flux:separator variant="subtle" />
            @if ($purchaseRequisition->requestingDepartments->isEmpty())
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('None selected.') }}</flux:text>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($purchaseRequisition->requestingDepartments as $dept)
                        <flux:badge size="sm" inset="top bottom">{{ $dept->name }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <flux:heading size="lg">{{ __('Line items') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Total') }}:
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ number_format($total, 2) }}</span>
                </flux:text>
            </div>
            <flux:separator variant="subtle" />

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column>{{ __('Unit') }}</flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Qty') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Unit price') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Extended') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($purchaseRequisition->lineItems as $li)
                        @php
                            $ext = (float) $li->quantity * (float) $li->price;
                        @endphp
                        <flux:table.row :key="$li->id">
                            <flux:table.cell variant="strong">{{ $li->code }}</flux:table.cell>
                            <flux:table.cell>{{ $li->lineItemUnit?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="max-w-[12rem] truncate">{{ $li->name }}</flux:table.cell>
                            <flux:table.cell class="max-w-[14rem] truncate text-zinc-500 dark:text-zinc-400">
                                {{ $li->description ?: '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $li->quantity }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format((float) $li->price, 2) }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">{{ number_format($ext, 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="py-6 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No line items.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
</div>
