@extends('mail.layout', ['title' => $heading])

@section('body')
    <p style="margin:0 0 12px;color:#EDF1F6;font-size:18px;font-weight:600;">{{ $heading }}</p>
    <p style="margin:0 0 20px;">{{ __('mail.greeting', ['name' => $name]) }}</p>
    <p style="margin:0 0 24px;">{{ $body }}</p>
    <p style="margin:0 0 24px;">
        <a href="{{ $url }}" style="display:inline-block;padding:14px 22px;background:#D6AC55;color:#0D1118;text-decoration:none;font-weight:600;border-radius:2px;">{{ $button }}</a>
    </p>
    <p style="margin:0;font-size:13px;color:#8492A6;">{{ $note }}</p>
    <p style="margin:12px 0 0;font-size:12px;color:#8492A6;word-break:break-all;" dir="ltr">{{ $url }}</p>
@endsection
