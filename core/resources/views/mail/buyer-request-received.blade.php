@extends('mail.layout', ['title' => __('mail.request_received.subject', ['reference' => $reference])])

@section('body')
    <p style="margin:0 0 16px;color:#EDF1F6;font-size:18px;font-weight:600;">{{ __('mail.request_received.heading') }}</p>
    <p style="margin:0 0 12px;color:#AEB9C8;font-size:15px;line-height:1.85;">{{ $title }} <span dir="ltr">({{ $code }})</span></p>
    <p style="margin:0 0 12px;color:#AEB9C8;font-size:15px;line-height:1.85;">{{ __('mail.request_received.reference', ['reference' => $reference]) }}</p>
    <p style="margin:0 0 12px;color:#AEB9C8;font-size:15px;line-height:1.85;">{{ __('mail.request_received.fee', ['fee' => $fee]) }}</p>
    <p style="margin:0;color:#AEB9C8;font-size:15px;line-height:1.85;">{{ __('mail.request_received.next') }}</p>
@endsection
