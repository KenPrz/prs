@props([
    'signatures' => [],
])

<table class="no-border mb-md" style="width: 100%;">
    <tr>
        @foreach($signatures as $sig)
            <td style="vertical-align: top; padding-right: 10px; width: {{ 100 / max(1, count($signatures)) }}%;">
                <div class="label" style="font-size: 10px; margin-bottom: 5px;">{{ $sig['label'] ?? '' }}</div>
                
                @if(!empty($sig['user']))
                    <div style="margin-bottom: -15px;">
                        <x-signature :user="$sig['user']" width="100px" height="40px" />
                    </div>
                @else
                    <div class="signature-space"><br /><br /></div>
                @endif
                
                <div style="border-top: 1px solid #000; margin-top: 5px; font-size: 10px;">
                    @if(!empty($sig['user']))
                        {{ $sig['user']->name }}
                    @else
                        {!! $sig['placeholder'] ?? '<span class="placeholder-line">&nbsp;</span>' !!}
                    @endif
                </div>
                
                <div style="font-size: 9px; color: #666; min-height: 12px;">
                    @if(!empty($sig['date']))
                        {{ $sig['date'] }}
                    @elseif(!empty($sig['user']) && !empty($sig['date_label']))
                        {{ $sig['date_label'] }}
                    @else
                        &nbsp;
                    @endif
                </div>
                
                @if(!empty($sig['subtext']))
                    <div style="font-size: 10px; margin-top: 5px;">{{ $sig['subtext'] }}</div>
                @endif
            </td>
            @if($loop->iteration % 4 == 0 && !$loop->last)
                </tr><tr>
            @endif
        @endforeach
    </tr>
</table>
