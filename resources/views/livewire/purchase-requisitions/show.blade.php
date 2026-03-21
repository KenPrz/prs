<div class="flex flex-col gap-6">
    {{-- Header --}}
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <flux:heading size="xl">{{ $purchaseRequisition->number }}</flux:heading>
                    <flux:badge size="sm" :color="$purchaseRequisition->status->badgeColor()" inset="top bottom">
                        {{ str($purchaseRequisition->status->value)->title() }}
                    </flux:badge>
                </div>
                <flux:subheading class="mt-1 max-w-2xl">
                    {{ $purchaseRequisition->title }}
                </flux:subheading>
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
    </x-card>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-card>
                <div class="flex flex-col gap-6">
                    <flux:heading size="lg">{{ __('Details') }}</flux:heading>
                    <flux:separator variant="subtle" />
                    <div>
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            {{ __('Description') }}
                        </flux:text>
                        <flux:text class="mt-1 whitespace-pre-wrap text-foreground">
                            {{ $purchaseRequisition->description ?: '—' }}
                        </flux:text>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex flex-col gap-6">
                    <flux:heading size="lg">{{ __('Requesting departments') }}</flux:heading>
                    <flux:separator variant="subtle" />
                    @if ($purchaseRequisition->requestingDepartments->isEmpty())
                        <flux:text class="text-muted-foreground">{{ __('None selected.') }}</flux:text>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($purchaseRequisition->requestingDepartments as $dept)
                                <flux:badge size="sm" inset="top bottom">{{ $dept->name }}</flux:badge>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card>
                <div class="flex flex-col gap-6">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <flux:heading size="lg">{{ __('Line items') }}</flux:heading>
                        <flux:text class="text-sm text-muted-foreground">
                            {{ __('Total') }}:
                            <span class="font-semibold text-foreground">{{ number_format($total, 2) }}</span>
                        </flux:text>
                    </div>
                    <flux:separator variant="subtle" />

                    <div class="-mx-6 overflow-x-auto">
                        <table class="min-w-full border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr class="text-foreground">
                                    <th class="w-[1%] whitespace-nowrap border-b border-border py-3 pe-3 ps-6 text-start text-sm font-medium">
                                        {{ __('Code') }}
                                    </th>
                                    <th class="w-[1%] whitespace-nowrap border-b border-border py-3 pe-3 text-start text-sm font-medium">
                                        {{ __('Unit') }}
                                    </th>
                                    <th class="border-b border-border py-3 pe-3 text-start text-sm font-medium">
                                        {{ __('Name') }}
                                    </th>
                                    <th class="border-b border-border py-3 pe-3 text-start text-sm font-medium">
                                        {{ __('Description') }}
                                    </th>
                                    <th class="w-[1%] whitespace-nowrap border-b border-border py-3 pe-3 text-end text-sm font-medium">
                                        {{ __('Qty') }}
                                    </th>
                                    <th class="w-[1%] whitespace-nowrap border-b border-border py-3 pe-3 text-end text-sm font-medium">
                                        {{ __('Unit price') }}
                                    </th>
                                    <th class="w-[1%] whitespace-nowrap border-b border-border py-3 ps-3 pe-6 text-end text-sm font-medium">
                                        {{ __('Extended') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($purchaseRequisition->lineItems as $li)
                                    @php
                                        $ext = (float) $li->quantity * (float) $li->price;
                                    @endphp
                                    <tr wire:key="line-{{ $li->id }}" class="align-top">
                                        <td class="border-t border-border py-3 pe-3 ps-6 font-medium text-foreground whitespace-nowrap">
                                            {{ $li->code }}
                                        </td>
                                        <td class="border-t border-border py-3 pe-3 text-muted-foreground whitespace-nowrap">
                                            {{ $li->lineItemUnit?->name ?? '—' }}
                                        </td>
                                        <td class="max-w-[10rem] border-t border-border py-3 pe-3 text-foreground sm:max-w-[14rem]">
                                            <span class="line-clamp-2 break-words">{{ $li->name }}</span>
                                        </td>
                                        <td class="max-w-[12rem] border-t border-border py-3 pe-3 text-muted-foreground sm:max-w-[18rem]">
                                            <span class="line-clamp-2 break-words">{{ $li->description ?: '—' }}</span>
                                        </td>
                                        <td class="border-t border-border py-3 pe-3 text-end tabular-nums text-muted-foreground whitespace-nowrap">
                                            {{ $li->quantity }}
                                        </td>
                                        <td class="border-t border-border py-3 pe-3 text-end tabular-nums text-muted-foreground whitespace-nowrap">
                                            {{ number_format((float) $li->price, 2) }}
                                        </td>
                                        <td class="border-t border-border py-3 ps-3 pe-6 text-end tabular-nums font-medium text-foreground whitespace-nowrap">
                                            {{ number_format($ext, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="border-t border-border px-6 py-12 text-center text-muted-foreground">
                                            {{ __('No line items.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Sidebar (mock UI) --}}
        <div class="flex flex-col gap-6">
            <x-card>
                <div class="flex flex-col gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Meta') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-muted-foreground">{{ __('Record information') }}</flux:text>
                    </div>
                    <flux:separator variant="subtle" />
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">{{ __('Created by') }}</dt>
                            <dd class="font-medium text-foreground">{{ $purchaseRequisition->createdBy?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ __('Created') }}</dt>
                            <dd class="font-medium text-foreground">
                                {{ $purchaseRequisition->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ __('Last updated') }}</dt>
                            <dd class="font-medium text-foreground">
                                {{ $purchaseRequisition->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </x-card>

            <x-card>
                <div class="flex flex-col gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Workflow') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-muted-foreground">{{ __('Mock — approval stages') }}</flux:text>
                    </div>
                    <flux:separator variant="subtle" />
                    <div class="flex flex-col gap-3">
                        @foreach (['Submitted', 'Manager review', 'Finance', 'Final approval'] as $i => $label)
                            <div class="flex items-center gap-3">
                                @if ($i === 0)
                                    <flux:icon icon="check-circle" class="size-5 shrink-0 text-emerald-500" />
                                @elseif ($i === 1)
                                    <div class="flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-primary text-[10px] font-semibold text-primary">
                                        2
                                    </div>
                                @else
                                    <div class="size-5 shrink-0 rounded-full border border-border bg-muted/30"></div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <flux:text class="text-sm font-medium text-foreground">{{ $label }}</flux:text>
                                    <flux:text size="sm" class="text-muted-foreground">{{ __('Pending (sample)') }}</flux:text>
                                </div>
                            </div>
                            @if ($i < 3)
                                <div class="ms-2.5 h-4 w-px bg-border"></div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex flex-col gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('People') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-muted-foreground">{{ __('Mock — stakeholders') }}</flux:text>
                    </div>
                    <flux:separator variant="subtle" />
                    <ul class="space-y-3">
                        @foreach (['A. Reyes — Requestor', 'M. Santos — Dept. head', 'J. Cruz — Finance'] as $row)
                            <li class="flex items-center gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                    {{ str($row)->substr(0, 1) }}
                                </div>
                                <flux:text class="text-sm text-foreground">{{ $row }}</flux:text>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-card>

            <x-card>
                <div class="flex flex-col gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Activity') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-muted-foreground">{{ __('Mock — audit trail') }}</flux:text>
                    </div>
                    <flux:separator variant="subtle" />
                    <ul class="space-y-4 border-s border-border ps-4">
                        @foreach ([__('PR created'), __('Routed for review'), __('Comment added (sample)')] as $j => $evt)
                            <li class="relative -ms-px ps-4">
                                <span class="absolute -start-[5px] top-1.5 size-2 rounded-full bg-border ring-4 ring-card"></span>
                                <flux:text class="text-sm text-foreground">{{ $evt }}</flux:text>
                                <flux:text size="sm" class="text-muted-foreground">{{ now()->subHours(3 - $j)->format('M j, g:i A') }} · {{ __('Sample user') }}</flux:text>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-card>
        </div>
    </div>
</div>
