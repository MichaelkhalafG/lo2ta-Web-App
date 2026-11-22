@extends('mail.layout', ['title' => __('mail.team_facts.'.$kind.'.subject', ['reference' => $facts['reference'] ?? ''])])

@section('body')
    <p style="margin:0 0 16px;color:#EDF1F6;font-size:18px;font-weight:600;">{{ __('mail.team_facts.'.$kind.'.subject', ['reference' => $facts['reference'] ?? '']) }}</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;color:#AEB9C8;">
        @foreach ($facts as $key => $value)
            @if ($value !== '')
                <tr>
                    <td style="padding:4px 0;padding-inline-end:16px;color:#8492A6;vertical-align:top;white-space:nowrap;">{{ __('mail.team_facts.fields.'.$key) }}</td>
                    <td style="white-space:pre-line;">{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>
@endsection
