@props([
    'title' => null,
])

<div class="mb-md">
    @if($title)
        <div class="section-title">{{ $title }}</div>
    @endif
    <div class="grid-2-col">
        <div class="grid-2-col-row">
            {{ $slot }}
        </div>
    </div>
</div>
