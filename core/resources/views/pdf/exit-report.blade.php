@php
    /** @var \App\Models\Unit $unit */
    $n = fn (int|float|null $v) => $v === null ? '—' : number_format((float) $v);
    $c = $r['cancel'];
    $t = $r['transfer'];
    $dir = $locale === 'en' ? 'ltr' : 'rtl';
    $egp = $locale === 'en' ? 'EGP' : 'ج.م';
@endphp
<html dir="{{ $dir }}">
<head>
<style>
    body { font-family: alexandria; color: #141820; font-size: 10.5pt; line-height: 1.6; }
    h1 { font-size: 18pt; margin: 0 0 2mm; }
    h2 { font-size: 12pt; margin: 7mm 0 2mm; border-bottom: 0.3mm solid #B9B2A3; padding-bottom: 1mm; }
    .meta { color: #4A5262; font-size: 9.5pt; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1.6mm 0; border-bottom: 0.2mm solid #E6E1D6; vertical-align: top; }
    td.v { text-align: {{ $dir === 'rtl' ? 'left' : 'right' }}; white-space: nowrap; direction: ltr; }
    .net td { font-weight: bold; border-bottom: 0.4mm solid #141820; }
    .gold { color: #7A5818; }
    .small { font-size: 8.5pt; color: #4A5262; }
</style>
</head>
<body>
    <h1>{{ $label('title') }}</h1>
    <div class="meta">
        {{ $label('reference') }}: {{ $reference }}
        &nbsp;·&nbsp; {{ $label('date') }}: {{ $date }}
        &nbsp;·&nbsp; {{ $label('version.'.$kind) }}
    </div>

    <h2>{{ $label('summary') }}</h2>
    <table>
        <tr><td>{{ $unit->sub_type?->label($locale) }}</td><td class="v">{{ $unit->compound?->nameFor($locale) ?? $unit->compound_other }}</td></tr>
        <tr><td>{{ $unit->developer?->nameFor($locale) ?? $unit->developer_other }}</td><td class="v">{{ $unit->area?->nameFor($locale) ?? $unit->area_other }}</td></tr>
    </table>

    <h2>{{ $label('figures') }} <span class="small">({{ $label('version.'.$kind) }})</span></h2>
    <table>
        <tr><td>{{ $label('total_price') }}</td><td class="v">{{ $egp }} {{ $n($r['inputs']['total_price']) }}</td></tr>
        <tr><td>{{ $label('paid') }}</td><td class="v">{{ $egp }} {{ $n($r['inputs']['paid_amount']) }}</td></tr>
        <tr><td>{{ $label('remaining') }}</td><td class="v">{{ $egp }} {{ $n($r['inputs']['total_price'] - $r['inputs']['paid_amount']) }}</td></tr>
        <tr><td>{{ $label('years_paid') }}</td><td class="v">{{ $r['inputs']['years_paid'] }}</td></tr>
    </table>

    <h2>{{ $label('cancel') }}</h2>
    <table>
        <tr><td>{{ $label('penalty') }}</td><td class="v">{{ $egp }} {{ $n($c['penalty_min']) }} – {{ $n($c['penalty_max']) }}</td></tr>
        <tr><td>{{ $label('refund') }}</td><td class="v">{{ $egp }} {{ $n($c['refund_min']) }} – {{ $n($c['refund_max']) }}</td></tr>
        <tr><td>{{ $label('refund_years') }}</td><td class="v">{{ $c['refund_years'] }}</td></tr>
        @if ($c['refund_pv_min'] !== null)
            <tr><td>{{ $label('refund_pv') }}</td><td class="v">{{ $egp }} {{ $n($c['refund_pv_min']) }}</td></tr>
        @endif
    </table>

    <h2>{{ $label('transfer') }}</h2>
    <table>
        <tr><td>{{ $label('receive') }}</td><td class="v">{{ $egp }} {{ $n($t['receive']) }}</td></tr>
        <tr><td>{{ $label('transfer_fee') }}</td><td class="v">{{ $egp }} −{{ $n($t['transfer_fee']) }}</td></tr>
        @if ($t['arrears_to_developer'] > 0)
            <tr><td>{{ $label('arrears') }}</td><td class="v">{{ $egp }} −{{ $n($t['arrears_to_developer']) }}</td></tr>
        @endif
        @if ($t['arrears_self'] > 0)
            <tr><td>{{ $label('arrears_self') }}</td><td class="v">{{ $egp }} {{ $n($t['arrears_self']) }}</td></tr>
        @endif
        <tr><td>{{ $label('lokta_fee') }}</td><td class="v">0</td></tr>
        <tr class="net"><td>{{ $label('net') }}</td><td class="v gold">{{ $egp }} {{ $n($t['net']) }}</td></tr>
    </table>

    <h2>{{ $label('difference') }}</h2>
    <table>
        <tr class="net"><td>{{ $label('difference') }}</td><td class="v gold">{{ $egp }} {{ $n($r['advantage_min']) }} – {{ $n($r['advantage_max']) }}</td></tr>
    </table>

    @php
        $pct = fn (int $bp) => rtrim(rtrim(number_format($bp / 100, 2), '0'), '.').'%';
        $p = $r['provisional'];
        $assumptions = [
            ['assume.penalty', $pct($settings['penalty_min_bp']).' – '.$pct($settings['penalty_max_bp']).' · '.$label('assume.basis_'.$settings['penalty_basis']), array_intersect(['penalty_min_bp', 'penalty_max_bp', 'penalty_basis'], $p) !== []],
            ['assume.refund', $settings['refund_rule'] === 'fixed' ? (string) $settings['refund_years_fixed'] : $label('assume.refund_mirror'), array_intersect(['refund_rule', 'refund_years_fixed'], $p) !== []],
            ['assume.transfer_fee', $t['transfer_fee_bp'] !== null ? $pct($t['transfer_fee_bp']) : $n($t['transfer_fee']).' '.$egp, $t['transfer_fee_source'] === 'setting' && array_intersect(['transfer_fee_mode', 'transfer_fee_bp', 'transfer_fee_fixed'], $p) !== []],
            ['assume.late_fee', $pct($settings['late_fee_bp']), in_array('late_fee_bp', $p, true)],
        ];
    @endphp
    <h2>{{ $label('assumptions') }}</h2>
    <table class="small">
        @foreach ($assumptions as [$key, $value, $provisional])
            <tr>
                <td>{{ $label($key) }}@if ($provisional) <span class="gold"> · {{ $label('assumption_provisional') }}</span>@endif</td>
                <td class="v">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <h2>{{ $label('disclaimer') }}</h2>
    <p class="small">{{ $label('disclaimer_text') }}</p>
</body>
</html>
