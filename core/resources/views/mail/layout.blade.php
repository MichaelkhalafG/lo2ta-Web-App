@php
    $locale = app()->getLocale();
    $dir = config('locales.supported.'.$locale.'.dir', 'rtl');
    $site = config('locales.supported.'.$locale.'.site_name', 'Lokta');
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#090C11;color:#EDF1F6;font-family:'Segoe UI',Tahoma,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#090C11;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#131922;border:1px solid #263142;" dir="{{ $dir }}">
                    <tr>
                        <td style="padding:24px 28px;border-bottom:1px solid #263142;font-size:22px;font-weight:800;text-align:start;">
                            {{ $site }} <span style="font-size:11px;font-weight:600;letter-spacing:.24em;color:#D6AC55;">LOKTA</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.85;color:#AEB9C8;text-align:start;">
                            @yield('body')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid #263142;font-size:12px;color:#8492A6;text-align:start;">
                            {{ __('mail.footer') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
