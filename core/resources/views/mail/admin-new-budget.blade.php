@extends('mail.layout', ['title' => __('mail.new_budget.subject')])

@section('body')
    <p style="margin:0 0 16px;color:#EDF1F6;font-size:18px;font-weight:600;">{{ __('mail.new_budget.subject') }}</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;color:#AEB9C8;">
        <tr><td style="padding:4px 0;padding-inline-end:16px;color:#8492A6;">{{ __('mail.new_budget.buyer') }}</td><td>{{ $buyer }}</td></tr>
        <tr><td style="padding:4px 0;padding-inline-end:16px;color:#8492A6;">{{ __('mail.new_budget.cash') }}</td><td dir="ltr">{{ $cash }}</td></tr>
        <tr><td style="padding:4px 0;padding-inline-end:16px;color:#8492A6;">{{ __('mail.new_budget.installment') }}</td><td dir="ltr">{{ $installment }}</td></tr>
        <tr><td style="padding:4px 0;padding-inline-end:16px;color:#8492A6;">{{ __('mail.new_budget.verified') }}</td><td>{{ $verified }}</td></tr>
    </table>
@endsection
