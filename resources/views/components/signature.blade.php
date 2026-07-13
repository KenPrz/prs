@props([
    'user' => null,
    'width' => '150px',
    'height' => 'auto',
])

@php
    $signature = $user?->activeSignature()->with('media')->first();
    $signaturePath = $signature?->getFirstMediaPath();
    $signatureData = null;
    
    if ($signaturePath && file_exists($signaturePath)) {
        $imageData = base64_encode(file_get_contents($signaturePath));
        $mimeType = mime_content_type($signaturePath);
        $signatureData = 'data:' . $mimeType . ';base64,' . $imageData;
    }
@endphp

<div {{ $attributes->merge(['style' => "width: {$width}; height: {$height}; position: relative;"]) }}>
    @if($signatureData)
        <img 
            src="{{ $signatureData }}"
            style="max-width: 100%; max-height: 100%; object-fit: contain; display: block;"
        >
    @else
        <div style="font-style: italic; color: #999; font-size: 0.8rem; border-bottom: 1px solid #ccc; padding-bottom: 5px;">
            {{ $user?->name ?? 'N/A' }}
        </div>
    @endif
</div>
