@extends('mail.layout', ['title' => __('mail.deal.'.$kind.'.subject', ['code' => $unit['code']])])

@section('body')
    <p style="margin:0 0 16px;color:#EDF1F6;font-size:18px;font-weight:600;">{{ __('mail.deal.'.$kind.'.subject', ['code' => $unit['code']]) }}</p>
    <p style="margin:0 0 12px;color:#AEB9C8;font-size:15px;line-height:1.85;">{{ __('mail.deal.'.$kind.'.'.$role, ['code' => $unit['code']]) }}</p>
    @if ($unit['code'] !== '')
        <p style="margin:0 0 12px;color:#AEB9C8;font-size:15px;line-height:1.85;">
            <span style="color:#EDF1F6;" dir="ltr">{{ $unit['code'] }}</span> · {{ $unit['title'] }}@if ($unit['place'] !== '')<br>{{ $unit['place'] }}@endif
        </p>
    @endif
    @if ($kind !== 'unit_sold')
        <p style="margin:0 0 12px;color:#7E8A9C;font-size:13px;" dir="ltr">{{ $reference }}</p>
    @endif
    @if ($link !== null)
        <p style="margin:20px 0 0;">
            <a href="{{ $link }}" style="display:inline-block;padding:14px 22px;background:#D6AC55;color:#0D1118;text-decoration:none;font-weight:600;border-radius:2px;">{{ __('mail.deal.button.'.($kind === 'unit_sold' ? 'browse' : $role)) }}</a>
        </p>
    @endif
@endsection
