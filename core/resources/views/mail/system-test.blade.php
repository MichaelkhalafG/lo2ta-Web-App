@php($dir = config('locales.supported.'.app()->getLocale().'.dir', 'rtl'))
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('mail.system_test.subject') }}</title>
</head>
<body>
    <p>{{ __('mail.system_test.body', ['time' => $sentAt]) }}</p>
</body>
</html>
