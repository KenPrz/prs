@props(['header' => null, 'footer' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'bg-card text-card-foreground rounded-xl border border-border shadow-sm']) }}>
    @if ($header)
        <div class="px-6 py-4 border-b border-border">
            {{ $header }}
        </div>
    @endif

    <div @class(['p-6' => $padding])>
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="px-6 py-4 border-t border-border">
            {{ $footer }}
        </div>
    @endif
</div>
