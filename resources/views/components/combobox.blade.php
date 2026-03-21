@props([
    'items' => [],
    'multiple' => false,
    'placeholder' => 'Select...',
    'searchPlaceholder' => 'Search...',
    'name' => null,
    'size' => null,
])

@php
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
@endphp

<div
    x-data="{
        open: false,
        search: '',
        multiple: @js($multiple),
        items: @js($items),
        selected: @entangle($wireModel),
        highlightedIndex: -1,
        dropdownTop: 0,
        dropdownLeft: 0,
        dropdownWidth: 0,
        /** Dynamic list max-height (px) — like Radix Popover collision padding */
        listMaxHeightPx: 192,
        /** True when panel is placed above the trigger (shadcn / Radix flip) */
        placeAbove: false,
        _outside: null,
        _scrollHandler: null,

        init() {
            this.$watch('open', (open) => {
                if (open) {
                    this.$nextTick(() => {
                        this.updatePosition();
                        this._scrollHandler = () => this.updatePosition();
                        window.addEventListener('scroll', this._scrollHandler, true);
                        window.addEventListener('resize', this._scrollHandler);
                        setTimeout(() => {
                            this._outside = (e) => {
                                if (this.$refs.triggerButton?.contains(e.target)) return;
                                if (this.$refs.dropdown?.contains(e.target)) return;
                                this.open = false;
                                this.search = '';
                            };
                            document.addEventListener('click', this._outside, true);
                        }, 0);
                        this.$nextTick(() => {
                            setTimeout(() => this.$refs.searchInput?.focus(), 0);
                        });
                    });
                } else {
                    if (this._outside) {
                        document.removeEventListener('click', this._outside, true);
                        this._outside = null;
                    }
                    if (this._scrollHandler) {
                        window.removeEventListener('scroll', this._scrollHandler, true);
                        window.removeEventListener('resize', this._scrollHandler);
                        this._scrollHandler = null;
                    }
                }
            });
        },

        /**
         * Viewport positioning modeled on Radix / shadcn PopoverContent:
         * collision padding, flip to top when space below is insufficient,
         * clamp horizontal overflow, dynamic max-height for the list.
         */
        updatePosition() {
            const btn = this.$refs.triggerButton;
            if (!btn) return;

            const r = btn.getBoundingClientRect();
            const GUTTER = 4;
            const VIEWPORT_PADDING = 8;
            const SEARCH_BLOCK_H = 56;
            const LIST_CAP = 192;
            const MIN_LIST = 96;

            const vh = window.innerHeight;
            const vw = window.innerWidth;

            let left = r.left;
            let width = r.width;
            if (left + width > vw - VIEWPORT_PADDING) {
                left = vw - width - VIEWPORT_PADDING;
            }
            if (left < VIEWPORT_PADDING) {
                left = VIEWPORT_PADDING;
            }
            this.dropdownLeft = left;
            this.dropdownWidth = width;

            const spaceBelow = vh - r.bottom - GUTTER - VIEWPORT_PADDING;
            const spaceAbove = r.top - VIEWPORT_PADDING;

            const needBelow = SEARCH_BLOCK_H + MIN_LIST;
            let placeAbove = false;

            if (spaceBelow < needBelow && spaceAbove > spaceBelow) {
                placeAbove = true;
            } else if (spaceBelow < LIST_CAP + SEARCH_BLOCK_H && spaceAbove > spaceBelow) {
                placeAbove = true;
            }

            let listMax;
            if (placeAbove) {
                listMax = Math.min(LIST_CAP, Math.max(MIN_LIST, spaceAbove - SEARCH_BLOCK_H - GUTTER));
            } else {
                listMax = Math.min(LIST_CAP, Math.max(MIN_LIST, spaceBelow - SEARCH_BLOCK_H));
            }
            this.listMaxHeightPx = listMax;
            this.placeAbove = placeAbove;

            const estPanelH = SEARCH_BLOCK_H + listMax + 12;
            if (placeAbove) {
                this.dropdownTop = Math.max(VIEWPORT_PADDING, r.top - GUTTER - estPanelH);
            } else {
                this.dropdownTop = r.bottom + GUTTER;
            }

            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    const panel = this.$refs.dropdown;
                    if (!panel || !this.open) return;

                    const ph = panel.getBoundingClientRect().height;
                    let top;

                    if (placeAbove) {
                        top = r.top - GUTTER - ph;
                        if (top < VIEWPORT_PADDING) {
                            top = VIEWPORT_PADDING;
                        }
                    } else {
                        top = r.bottom + GUTTER;
                        if (top + ph > vh - VIEWPORT_PADDING) {
                            const spaceAbove2 = r.top - VIEWPORT_PADDING;
                            if (spaceAbove2 >= ph + GUTTER || spaceAbove2 > vh - r.bottom - GUTTER) {
                                top = Math.max(VIEWPORT_PADDING, r.top - GUTTER - ph);
                                this.placeAbove = true;
                            } else {
                                top = Math.max(VIEWPORT_PADDING, vh - VIEWPORT_PADDING - ph);
                            }
                        }
                    }

                    this.dropdownTop = top;
                });
            });
        },

        get filteredItems() {
            if (!this.search) return this.items;
            const s = this.search.toLowerCase();
            return this.items.filter(i =>
                i.label.toLowerCase().includes(s) ||
                (i.description && i.description.toLowerCase().includes(s))
            );
        },

        get selectedLabels() {
            if (!this.selected) return '';
            if (this.multiple) {
                const ids = Array.isArray(this.selected) ? this.selected : [];
                return this.items
                    .filter(i => ids.map(String).includes(String(i.value)))
                    .map(i => i.label);
            }
            const found = this.items.find(i => String(i.value) === String(this.selected));
            return found ? found.label : '';
        },

        get displayText() {
            if (this.multiple) {
                const labels = this.selectedLabels;
                if (!labels.length) return '';
                return labels.join(', ');
            }
            return this.selectedLabels;
        },

        toggle(value) {
            value = String(value);
            if (this.multiple) {
                let arr = Array.isArray(this.selected) ? [...this.selected.map(String)] : [];
                const idx = arr.indexOf(value);
                if (idx > -1) {
                    arr.splice(idx, 1);
                } else {
                    arr.push(value);
                }
                this.selected = arr.map(v => isNaN(v) ? v : Number(v));
            } else {
                this.selected = isNaN(value) ? value : Number(value);
                this.open = false;
                this.search = '';
            }
        },

        isSelected(value) {
            value = String(value);
            if (this.multiple) {
                const arr = Array.isArray(this.selected) ? this.selected.map(String) : [];
                return arr.includes(value);
            }
            return String(this.selected) === value;
        },

        onKeydown(e) {
            if (!this.open) return;
            if (e.key === 'Escape') {
                this.open = false;
                this.search = '';
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.highlightedIndex = Math.min(this.highlightedIndex + 1, this.filteredItems.length - 1);
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.highlightedIndex = Math.max(this.highlightedIndex - 1, 0);
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                if (this.highlightedIndex >= 0 && this.filteredItems[this.highlightedIndex]) {
                    this.toggle(this.filteredItems[this.highlightedIndex].value);
                }
            }
        },

        removeTag(value) {
            this.toggle(String(value));
        }
    }"
    x-on:keydown="onKeydown"
    class="relative"
    {{ $attributes->except(['wire:model', 'wire:model.defer', 'wire:model.live', 'wire:model.lazy', 'items', 'multiple', 'placeholder', 'searchPlaceholder', 'name', 'size']) }}
>
    {{-- Trigger --}}
    <button
        type="button"
        x-ref="triggerButton"
        x-on:click="open = !open"
        @class([
            'flex w-full items-center justify-between border border-border bg-white shadow-xs transition',
            'hover:border-primary/30',
            'dark:bg-white/10 dark:border-white/10 dark:hover:border-white/20',
            'focus:outline-none focus:ring-2 focus:ring-ring/20',
            'h-10 px-3 py-2 text-sm rounded-lg' => $size === null,
            'h-8 px-2.5 py-1.5 text-sm rounded-md' => $size === 'sm',
            'h-6 px-2 text-xs rounded-md' => $size === 'xs',
        ])
    >
        <div @class([
            'flex flex-wrap items-center gap-1 overflow-hidden text-start',
            'min-h-6' => $size === null,
            'min-h-5' => $size === 'sm',
            'min-h-4' => $size === 'xs',
        ])>
            <template x-if="multiple && Array.isArray(selected) && selected.length > 0">
                <template x-for="(val, idx) in selected" :key="idx">
                    <span class="inline-flex items-center gap-1 rounded-md bg-primary/10 px-1.5 py-0.5 text-xs font-medium text-primary dark:bg-primary/20">
                        <span x-text="items.find(i => String(i.value) === String(val))?.label || val"></span>
                        <button type="button" x-on:click.stop="removeTag(val)" class="ml-0.5 hover:text-destructive transition">
                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                </template>
            </template>
            <template x-if="!multiple && displayText">
                <span class="text-foreground truncate" x-text="displayText"></span>
            </template>
            <template x-if="(!multiple && !displayText) || (multiple && (!Array.isArray(selected) || selected.length === 0))">
                <span class="text-muted-foreground">{{ $placeholder }}</span>
            </template>
        </div>
        <svg class="size-4 shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    {{-- Teleported panel: fixed + collision handling (shadcn uses Radix Popover collision + flip) --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-ref="dropdown"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            :style="`top: ${dropdownTop}px; left: ${dropdownLeft}px; width: ${dropdownWidth}px`"
            :class="placeAbove ? 'origin-bottom' : 'origin-top'"
            class="fixed z-[9999] rounded-lg border border-border bg-white shadow-lg dark:bg-zinc-800"
            x-cloak
        >
            <div class="border-b border-border p-2">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        x-ref="searchInput"
                        x-model="search"
                        x-on:input="highlightedIndex = 0; $nextTick(() => updatePosition())"
                        type="text"
                        placeholder="{{ $searchPlaceholder }}"
                        class="w-full rounded-md border border-border bg-transparent py-1.5 pl-8 pr-3 text-sm text-foreground placeholder:text-muted-foreground
                               focus:outline-none focus:ring-1 focus:ring-ring/30"
                    />
                </div>
            </div>

            <div
                class="overflow-y-auto p-1"
                :style="`max-height: ${listMaxHeightPx}px`"
            >
                <template x-for="(item, index) in filteredItems" :key="item.value">
                    <button
                        type="button"
                        x-on:click="toggle(item.value)"
                        x-on:mouseenter="highlightedIndex = index"
                        :class="{
                            'bg-primary/10 dark:bg-primary/20': highlightedIndex === index,
                            'bg-primary/5': isSelected(item.value) && highlightedIndex !== index,
                        }"
                        class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-sm text-foreground transition hover:bg-primary/10 dark:hover:bg-primary/20"
                    >
                        <div class="flex flex-col items-start">
                            <span x-text="item.label"></span>
                            <template x-if="item.description">
                                <span class="text-xs text-muted-foreground" x-text="item.description"></span>
                            </template>
                        </div>
                        <template x-if="isSelected(item.value)">
                            <svg class="size-4 text-primary" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                    </button>
                </template>
                <div x-show="filteredItems.length === 0" class="px-2.5 py-4 text-center text-sm text-muted-foreground">
                    {{ __('No results found.') }}
                </div>
            </div>
        </div>
    </template>
</div>
