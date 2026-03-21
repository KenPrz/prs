<form wire:submit="save" class="flex flex-col gap-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">
                    {{ $prId ? __('Edit purchase requisition') : __('New purchase requisition') }}
                </flux:heading>
                <flux:subheading class="mt-1">
                    {{ __('Header, requesting departments, and line items match your database schema.') }}
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

        <flux:card class="grid gap-6 md:grid-cols-2">
            @if ($prId && $prNumber)
                <div class="md:col-span-2">
                    <flux:input :value="$prNumber" :label="__('Number')" readonly disabled />
                </div>
            @endif

            <div class="md:col-span-2">
                <flux:input wire:model="title" :label="__('Title')" type="text" required autocomplete="off" />
            </div>

            <div class="md:col-span-2">
                <flux:textarea wire:model="description" :label="__('Description')" rows="4" />
            </div>

            <div class="md:col-span-2">
                <flux:label class="mb-2">{{ __('Requesting departments') }}</flux:label>
                <flux:select wire:model="departmentIds" multiple class="min-h-32" :placeholder="__('Select departments')">
                    @foreach ($departments as $dept)
                        <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="departmentIds" />
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Line items') }}</flux:heading>
                <flux:button type="button" variant="ghost" size="sm" wire:click="addLine" icon="plus">
                    {{ __('Add line') }}
                </flux:button>
            </div>
            <flux:separator variant="subtle" />

            @error('lines')
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            <div class="-mx-4 overflow-x-auto sm:mx-0">
                <table class="min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr class="text-start text-sm font-medium text-zinc-800 dark:text-white">
                            <th class="border-b border-zinc-800/10 py-3 pe-3 ps-0 dark:border-white/20">{{ __('Code') }}</th>
                            <th class="border-b border-zinc-800/10 py-3 pe-3 dark:border-white/20">{{ __('Unit') }}</th>
                            <th class="border-b border-zinc-800/10 py-3 pe-3 dark:border-white/20">{{ __('Name') }}</th>
                            <th class="border-b border-zinc-800/10 py-3 pe-3 dark:border-white/20">{{ __('Description') }}</th>
                            <th class="border-b border-zinc-800/10 py-3 pe-3 text-end dark:border-white/20">{{ __('Qty') }}</th>
                            <th class="border-b border-zinc-800/10 py-3 pe-3 text-end dark:border-white/20">{{ __('Price') }}</th>
                            <th class="w-10 border-b border-zinc-800/10 py-3 pe-0 dark:border-white/20"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $index => $line)
                            <tr wire:key="line-{{ $index }}" class="align-top">
                                <td class="border-t border-zinc-800/10 py-2 pe-2 ps-0 dark:border-white/20">
                                    <flux:input wire:model="lines.{{ $index }}.code" size="sm" />
                                </td>
                                <td class="border-t border-zinc-800/10 py-2 pe-2 dark:border-white/20">
                                    <flux:select wire:model="lines.{{ $index }}.unit_id" size="sm">
                                        <flux:select.option value="">{{ __('—') }}</flux:select.option>
                                        @foreach ($units as $u)
                                            <flux:select.option :value="$u->id">{{ $u->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </td>
                                <td class="border-t border-zinc-800/10 py-2 pe-2 dark:border-white/20">
                                    <flux:input wire:model="lines.{{ $index }}.name" size="sm" />
                                </td>
                                <td class="border-t border-zinc-800/10 py-2 pe-2 dark:border-white/20">
                                    <flux:input wire:model="lines.{{ $index }}.description" size="sm" />
                                </td>
                                <td class="border-t border-zinc-800/10 py-2 pe-2 dark:border-white/20">
                                    <flux:input wire:model="lines.{{ $index }}.quantity" size="sm" type="number" step="0.01" min="0.01" />
                                </td>
                                <td class="border-t border-zinc-800/10 py-2 pe-2 dark:border-white/20">
                                    <flux:input wire:model="lines.{{ $index }}.price" size="sm" type="number" step="0.01" min="0" />
                                </td>
                                <td class="border-t border-zinc-800/10 py-2 pe-0 dark:border-white/20">
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        class="shrink-0"
                                        wire:click="removeLine({{ $index }})"
                                    />
                                </td>
                            </tr>
                            @if ($errors->has('lines.'.$index.'.code') || $errors->has('lines.'.$index.'.name'))
                                <tr wire:key="line-err-{{ $index }}">
                                    <td colspan="7" class="pb-2 pt-0">
                                        <flux:error name="lines.{{ $index }}.code" />
                                        <flux:error name="lines.{{ $index }}.unit_id" />
                                        <flux:error name="lines.{{ $index }}.name" />
                                        <flux:error name="lines.{{ $index }}.description" />
                                        <flux:error name="lines.{{ $index }}.quantity" />
                                        <flux:error name="lines.{{ $index }}.price" />
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
</form>
