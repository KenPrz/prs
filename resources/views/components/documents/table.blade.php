@props([
    'headers' => [],
])

<table class="mb-md">
    <thead>
        <tr>
            @foreach($headers as $header)
                <th style="{{ $header['style'] ?? '' }}">{{ $header['label'] ?? $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        {{ $slot }}
    </tbody>
    @if(isset($totals))
        <tbody>
            {{ $totals }}
        </tbody>
    @endif
</table>
