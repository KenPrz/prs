@props([
    'companyName' => 'OpenPRS Trading Corp.',
    'companyAddress' => "100 Commerce Avenue, Metro Business Park,\nMakati City 1200, Philippines",
    'companyContact' => 'Mobile No.: (+63917) 000-0000',
    'title' => 'Document',
])

<table class="mb-md no-border">
    <tr>
        <td style="width: 60%; vertical-align: top;">
            <div class="label text-large">{{ $title }}</div>
            <div class="uppercase">{{ $companyName }}</div>
            <div>{!! nl2br(e($companyAddress)) !!}</div>
            @if($companyContact)
                <div>{{ $companyContact }}</div>
            @endif
        </td>
        <td style="width: 40%; vertical-align: top; text-align: right;">
            <div class="label text-xlarge">OpenPRS</div>
            @if(isset($meta))
                <div style="margin-top: 10px;">
                    {{ $meta }}
                </div>
            @endif
        </td>
    </tr>
</table>
