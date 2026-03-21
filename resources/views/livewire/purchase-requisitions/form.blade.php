<form wire:submit="save" class="flex flex-col gap-6">
    {{-- Header Card --}}
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">
                    {{ $prId ? __('Edit Purchase Requisition') : __('New Purchase Requisition') }}
                </flux:heading>
                <flux:subheading class="mt-1">
                    {{ __('Fill in the details below to create or update a purchase requisition.') }}
                </flux:subheading>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button variant="ghost" type="button" :href="route('purchase-requisitions.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </x-card>

    {{-- PR Details Card --}}
    <x-card>
        <div class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Requisition Details') }}</flux:heading>
            <flux:separator variant="subtle" />

            <div class="grid gap-6 md:grid-cols-2">
                @if ($prId && $prNumber)
                    <div class="md:col-span-2">
                        <flux:input :value="$prNumber" :label="__('PR Number')" readonly disabled />
                    </div>
                @endif

                <div class="md:col-span-2">
                    <flux:input wire:model="title" :label="__('Title')" type="text" required autocomplete="off" />
                </div>

                <div class="md:col-span-2">
                    <flux:textarea wire:model="description" :label="__('Description')" rows="4" />
                </div>

                <div class="md:col-span-2">
                    <flux:label class="mb-2">{{ __('Requesting Departments') }}</flux:label>
                    <x-combobox wire:model="departmentIds" :items="$departments->map(fn($d) => ['value' => $d->id, 'label' => $d->name, 'description' => $d->code ?? ''])->toArray()" :multiple="true"
                        :placeholder="__('Select departments...')" :search-placeholder="__('Search departments...')" />
                    <flux:error name="departmentIds" />
                </div>
            </div>
        </div>
    </x-card>

    {{-- Line Items Card --}}
    <x-card :padding="false">
        <div class="p-6 pb-0">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Line Items') }}</flux:heading>
                <div class="flex items-center gap-3">
                    @if ($prId)
                        <div class="w-64 max-w-full">
                            <flux:input wire:model.live.debounce.300ms="lineSearch" icon="magnifying-glass"
                                placeholder="{{ __('Search line items...') }}" />
                        </div>
                    @endif
                    <flux:button type="button" variant="ghost" size="sm" wire:click="addLine" icon="plus">
                        {{ __('Add Line') }}
                    </flux:button>
                </div>
            </div>
        </div>

        @error('newLines')
            <div class="px-6 pt-4">
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            </div>
        @enderror
        @error('lineDrafts')
            <div class="px-6 pt-4">
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            </div>
        @enderror

        {{-- Grid keeps header and field columns on identical tracks (table + % widths misaligned combobox/inputs). --}}
        <div class="overflow-x-auto">
            <div
                class="min-w-[36rem] text-sm md:min-w-[44rem]"
                style="--line-cols: minmax(10rem, 1.1fr) minmax(12rem, 1.4fr) 5.25rem 6.5rem 2.75rem"
            >
                <div
                    class="grid items-end gap-x-3 border-b border-border px-6 py-3 font-medium text-foreground [grid-template-columns:var(--line-cols)]"
                >
                    <div class="min-w-0">{{ __('Unit') }}</div>
                    <div class="min-w-0">{{ __('Name') }}</div>
                    <div class="text-end">{{ __('Qty') }}</div>
                    <div class="text-end">{{ __('Price') }}</div>
                    <div class="flex justify-end" aria-hidden="true">
                        <span class="sr-only">{{ __('Row actions') }}</span>
                    </div>
                </div>

                @if ($prId && $paginatedLines !== null)
                    @foreach ($paginatedLines as $lineItem)
                        <div wire:key="line-existing-{{ $lineItem->id }}">
                            <div
                                class="grid items-start gap-x-3 border-t border-border px-6 py-4 [grid-template-columns:var(--line-cols)]"
                            >
                                <div class="min-w-0">
                                    <x-combobox wire:model="lineDrafts.{{ $lineItem->id }}.unit_id" :items="$units->map(fn($u) => ['value' => $u->id, 'label' => $u->name, 'description' => $u->code])->toArray()"
                                        :placeholder="__('Unit')" :search-placeholder="__('Search by name or code...')" />
                                </div>
                                <div class="min-w-0">
                                    <flux:input wire:model="lineDrafts.{{ $lineItem->id }}.name"
                                        placeholder="{{ __('Item name') }}" />
                                </div>
                                <div class="min-w-0">
                                    <flux:input wire:model="lineDrafts.{{ $lineItem->id }}.quantity" type="number" step="any"
                                        class="tabular-nums" />
                                </div>
                                <div class="min-w-0">
                                    <flux:input wire:model="lineDrafts.{{ $lineItem->id }}.price" type="number" step="0.01"
                                        placeholder="{{ __('Optional') }}" class="tabular-nums" />
                                </div>
                                <div class="flex justify-end pt-0.5">
                                    <flux:button type="button" size="sm" variant="ghost" icon="trash"
                                        class="shrink-0 text-destructive hover:text-destructive/80"
                                        wire:click="removeExistingLine({{ $lineItem->id }})" />
                                </div>
                            </div>
                            @if (
                                $errors->has('lineDrafts.' . $lineItem->id . '.name') ||
                                    $errors->has('lineDrafts.' . $lineItem->id . '.unit_id') ||
                                    $errors->has('lineDrafts.' . $lineItem->id . '.quantity') ||
                                    $errors->has('lineDrafts.' . $lineItem->id . '.price'))
                                <div wire:key="line-existing-err-{{ $lineItem->id }}" class="border-t border-border px-6 pb-3 pt-0">
                                    <flux:error name="lineDrafts.{{ $lineItem->id }}.unit_id" />
                                    <flux:error name="lineDrafts.{{ $lineItem->id }}.name" />
                                    <flux:error name="lineDrafts.{{ $lineItem->id }}.quantity" />
                                    <flux:error name="lineDrafts.{{ $lineItem->id }}.price" />
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif

                @foreach ($newLines as $index => $line)
                    <div wire:key="line-new-{{ $index }}">
                        <div
                            class="grid items-start gap-x-3 border-t border-border px-6 py-4 [grid-template-columns:var(--line-cols)]"
                        >
                            <div class="min-w-0">
                                <x-combobox wire:model="newLines.{{ $index }}.unit_id" :items="$units->map(fn($u) => ['value' => $u->id, 'label' => $u->name, 'description' => $u->code])->toArray()"
                                    :placeholder="__('Unit')" :search-placeholder="__('Search by name or code...')" />
                            </div>
                            <div class="min-w-0">
                                <flux:input wire:model="newLines.{{ $index }}.name"
                                    placeholder="{{ __('Item name') }}" />
                            </div>
                            <div class="min-w-0">
                                <flux:input wire:model="newLines.{{ $index }}.quantity" type="number" step="any"
                                    class="tabular-nums" />
                            </div>
                            <div class="min-w-0">
                                <flux:input wire:model="newLines.{{ $index }}.price" type="number" step="0.01"
                                    placeholder="{{ __('Optional') }}" class="tabular-nums" />
                            </div>
                            <div class="flex justify-end pt-0.5">
                                <flux:button type="button" size="sm" variant="ghost" icon="trash"
                                    class="shrink-0 text-destructive hover:text-destructive/80"
                                    wire:click="removeNewLine({{ $index }})" />
                            </div>
                        </div>
                        @if ($errors->has('newLines.' . $index . '.name') || $errors->has('newLines.' . $index . '.unit_id') || $errors->has('newLines.' . $index . '.quantity') || $errors->has('newLines.' . $index . '.price'))
                            <div wire:key="line-new-err-{{ $index }}" class="border-t border-border px-6 pb-3 pt-0">
                                <flux:error name="newLines.{{ $index }}.unit_id" />
                                <flux:error name="newLines.{{ $index }}.name" />
                                <flux:error name="newLines.{{ $index }}.quantity" />
                                <flux:error name="newLines.{{ $index }}.price" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        @if ($prId && $paginatedLines !== null)
            <div class="border-t border-border px-6 py-3">
                {{ $paginatedLines->links() }}
            </div>
        @endif

        {{-- Add line footer --}}
        <div class="flex items-center justify-center border-t border-border px-6 py-3">
            <flux:button type="button" variant="ghost" size="sm" wire:click="addLine" icon="plus"
                class="text-muted-foreground">
                {{ __('Add another line item') }}
            </flux:button>
        </div>
    </x-card>
</form>