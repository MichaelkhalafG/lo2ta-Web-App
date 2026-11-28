@extends('mail.layout', ['title' => __('mail.unit_notice.'.$kind.'.subject', ['reference' => $reference])])

@section('body')
    <p style="margin:0 0 16px;color:#EDF1F6;font-size:18px;font-weight:600;">{{ __('mail.unit_notice.'.$kind.'.subject', ['reference' => $reference]) }}</p>
    <p style="margin:0 0 12px;color:#AEB9C8;font-size:15px;line-height:1.85;">{{ __('mail.unit_notice.'.$kind.'.body', ['reference' => $reference]) }}</p>
    @if (($reason ?? null) !== null)
        <p style="margin:0 0 6px;color:#EDF1F6;font-size:15px;font-weight:600;">{{ __('mail.unit_notice.reason') }}</p>
        <p style="margin:0 0 12px;color:#AEB9C8;font-size:15px;line-height:1.85;white-space:pre-line;">{{ $reason }}</p>
    @endif
    @if (($fields ?? []) !== [])
        <p style="margin:0 0 6px;color:#EDF1F6;font-size:15px;font-weight:600;">{{ __($kind === 'change_rejected' ? 'mail.unit_notice.changes' : 'mail.unit_notice.fields') }}</p>
        <ul style="margin:0 0 12px;padding-inline-start:20px;color:#AEB9C8;font-size:15px;line-height:1.85;">
            @foreach ($fields as $row)
                <li>{{ $row['label'] }}: {{ $row['note'] }}</li>
            @endforeach
        </ul>
    @endif
    @if (($documents ?? []) !== [])
        <p style="margin:0 0 6px;color:#EDF1F6;font-size:15px;font-weight:600;">{{ __('mail.unit_notice.documents') }}</p>
        <ul style="margin:0 0 12px;padding-inline-start:20px;color:#AEB9C8;font-size:15px;line-height:1.85;">
            @foreach ($documents as $row)
                <li>{{ $row['label'] }}@if ($row['note'] !== ''): {{ $row['note'] }}@endif</li>
            @endforeach
        </ul>
    @endif
    @if (in_array($kind, ['published', 'published_verified'], true))
        <p style="margin:0 0 12px;color:#AEB9C8;font-size:15px;line-height:1.85;">{{ __('mail.unit_notice.report') }}</p>
    @endif
    @php($link = in_array($kind, ['published', 'published_verified', 'verified'], true) ? ($unitUrl ?? null) : ($accountUrl ?? null))
    @if ($link !== null && $kind !== 'withdrawn')
        <p style="margin:20px 0 0;">
            <a href="{{ $link }}" style="display:inline-block;padding:14px 22px;background:#D6AC55;color:#0D1118;text-decoration:none;font-weight:600;border-radius:2px;">{{ __('mail.unit_notice.'.(in_array($kind, ['published', 'published_verified', 'verified'], true) ? 'open_unit' : 'open_account')) }}</a>
        </p>
    @endif
@endsection
