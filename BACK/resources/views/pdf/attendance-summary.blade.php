@php
    use App\Services\AttendanceSummaryService as S;
    use App\Support\WorkTime;
    $t = $totals;
    $hhmm = fn ($m) => WorkTime::formatMinutesAsHHMM((int) $m);
    $statusLabels = [
        'worked' => 'Trabajado',
        'remote' => 'Teletrabajo',
        'vacation' => 'Vacaciones',
        'holiday' => 'Festivo',
        'justified' => 'Ausencia justificada',
        'absence' => 'Ausencia',
        'future' => 'Pendiente',
        'not_employed' => '—',
        'rest' => 'Descanso',
    ];
    $dayLetters = ['L' => 'Lun', 'M' => 'Mar', 'X' => 'Mié', 'J' => 'Jue', 'V' => 'Vie', 'S' => 'Sáb', 'D' => 'Dom'];
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Resumen de asistencia {{ $period['label'] }} - {{ $employee['name'] }}</title>
<style>
    @page { margin: 28px 30px 46px 30px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9px; color: #1f2937; margin: 0; }
    h1 { font-size: 16px; margin: 0; color: #111827; }
    .muted { color: #6b7280; }
    .header { border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 12px; }
    .header td { vertical-align: bottom; }
    .company { font-size: 11px; font-weight: bold; color: #2563eb; text-transform: uppercase; letter-spacing: .5px; }
    table { border-collapse: collapse; width: 100%; }
    .info td { padding: 3px 6px; border: 1px solid #e5e7eb; }
    .info .k { background: #f3f4f6; color: #4b5563; width: 16%; }
    .kpis { margin: 12px 0; }
    .kpis td { width: 16.66%; border: 1px solid #e5e7eb; padding: 6px; text-align: center; }
    .kpis .v { font-size: 13px; font-weight: bold; color: #111827; }
    .kpis .l { font-size: 7.5px; color: #6b7280; text-transform: uppercase; }
    .pos { color: #047857; }
    .neg { color: #b91c1c; }
    .days th { background: #1e3a8a; color: #fff; font-size: 8px; font-weight: bold; padding: 4px 3px; text-align: left; }
    .days td { border-bottom: 1px solid #e5e7eb; padding: 3px; }
    .days tr:nth-child(even) td { background: #f9fafb; }
    .num { text-align: right; white-space: nowrap; }
    .tag { padding: 1px 4px; border-radius: 3px; font-size: 7.5px; }
    .s-worked { background: #dbeafe; color: #1e40af; }
    .s-remote { background: #e0f2fe; color: #0369a1; }
    .s-vacation { background: #ccfbf1; color: #0f766e; }
    .s-holiday { background: #ede9fe; color: #6d28d9; }
    .s-justified { background: #fef3c7; color: #92400e; }
    .s-absence { background: #fee2e2; color: #b91c1c; }
    .s-future, .s-rest, .s-not_employed { background: #f3f4f6; color: #6b7280; }
    .totals td { font-weight: bold; border-top: 2px solid #1e3a8a; padding: 4px 3px; }
    .sign { margin-top: 26px; }
    .sign td { width: 50%; padding: 0 12px; vertical-align: top; }
    .sign .box { border-top: 1px solid #9ca3af; padding-top: 4px; margin-top: 40px; text-align: center; color: #4b5563; }
    .legal { margin-top: 10px; font-size: 7.5px; color: #6b7280; }
    .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7.5px; color: #9ca3af; }
</style>
</head>
<body>
<div class="footer">
    <table><tr>
        <td>{{ $company['name'] }} · Resumen de asistencia {{ $period['label'] }} · {{ $employee['name'] }}</td>
        <td style="text-align:right">Generado el {{ $generated_at }}</td>
    </tr></table>
</div>

<div class="header">
    <table><tr>
        <td>
            <div class="company">{{ $company['name'] }}</div>
            <h1>Resumen mensual de asistencia</h1>
        </td>
        <td style="text-align:right">
            <div style="font-size:13px;font-weight:bold">{{ $period['label'] }}</div>
            <div class="muted">{{ $period['from'] }} – {{ $period['to'] }}@if($period['partial']) (parcial)@endif</div>
        </td>
    </tr></table>
</div>

<table class="info">
    <tr>
        <td class="k">Empleado</td><td>{{ $employee['name'] }}</td>
        <td class="k">DNI</td><td>{{ $employee['dni'] ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Nº Seg. Social</td><td>{{ $employee['nss'] ?: '—' }}</td>
        <td class="k">Equipo</td><td>{{ $employee['team'] ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Jornada</td>
        <td>
            @if($schedule['start'] && $schedule['end'])
                {{ $schedule['start'] }} – {{ $schedule['end'] }}
                ({{ implode(', ', array_map(fn ($d) => $dayLetters[$d] ?? $d, $schedule['days'])) }})
                · {{ $hhmm($schedule['daily_minutes']) }} h/día
            @else
                Sin jornada asignada
            @endif
        </td>
        <td class="k">Fecha de alta</td><td>{{ $employee['hire_date'] ?: '—' }}</td>
    </tr>
</table>

<table class="kpis">
    <tr>
        <td><div class="v">{{ $hhmm($t['worked']) }}</div><div class="l">Horas trabajadas</div></td>
        <td><div class="v">{{ $hhmm($t['expected']) }}</div><div class="l">Horas teóricas</div></td>
        <td><div class="v {{ $t['balance'] < 0 ? 'neg' : 'pos' }}">{{ S::signedHHMM($t['balance']) }}</div><div class="l">Saldo</div></td>
        <td><div class="v">{{ $t['days_worked'] }}</div><div class="l">Días trabajados</div></td>
        <td><div class="v">{{ $t['remote_days'] }}</div><div class="l">Días teletrabajo</div></td>
        <td><div class="v">{{ $t['vacation_days'] }}</div><div class="l">Días vacaciones</div></td>
    </tr>
    <tr>
        <td><div class="v">{{ $t['holidays'] }}</div><div class="l">Festivos</div></td>
        <td><div class="v">{{ $t['justified_absences'] }}</div><div class="l">Ausencias justificadas</div></td>
        <td><div class="v {{ $t['absences'] ? 'neg' : '' }}">{{ $t['absences'] }}</div><div class="l">Ausencias sin fichaje</div></td>
        <td colspan="3" style="text-align:left" class="muted">
            Horas teóricas: días laborables de la jornada hasta la fecha, sin contar festivos, vacaciones ni ausencias justificadas.
        </td>
    </tr>
</table>

<table class="days">
    <thead>
        <tr>
            <th style="width:9%">Fecha</th>
            <th style="width:6%">Día</th>
            <th style="width:13%">Estado</th>
            <th class="num" style="width:7%">Entrada</th>
            <th class="num" style="width:7%">Pausa</th>
            <th class="num" style="width:7%">Reanuda</th>
            <th class="num" style="width:7%">Salida</th>
            <th class="num" style="width:8%">Trabajado</th>
            <th class="num" style="width:7%">Teórico</th>
            <th class="num" style="width:7%">Saldo</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($days as $d)
            <tr>
                <td>{{ $d['label'] }}</td>
                <td>{{ $d['weekday'] }}</td>
                <td><span class="tag s-{{ $d['status'] }}">{{ $statusLabels[$d['status']] ?? $d['status'] }}</span></td>
                <td class="num">{{ $d['start'] ?? '—' }}</td>
                <td class="num">{{ $d['pause'] ?? '' }}</td>
                <td class="num">{{ $d['resume'] ?? '' }}</td>
                <td class="num">{{ $d['end'] ?? '—' }}</td>
                <td class="num">{{ $d['worked'] ? $hhmm($d['worked']) : '—' }}</td>
                <td class="num">{{ $d['expected'] ? $hhmm($d['expected']) : '—' }}</td>
                <td class="num {{ $d['balance'] < 0 ? 'neg' : ($d['balance'] > 0 ? 'pos' : '') }}">{{ ($d['worked'] || $d['expected']) ? S::signedHHMM($d['balance']) : '' }}</td>
                <td>{{ $d['notes'] }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="muted" style="text-align:center;padding:12px">Sin registros en el período.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="totals">
            <td colspan="7">Total del mes</td>
            <td class="num">{{ $hhmm($t['worked']) }}</td>
            <td class="num">{{ $hhmm($t['expected']) }}</td>
            <td class="num {{ $t['balance'] < 0 ? 'neg' : 'pos' }}">{{ S::signedHHMM($t['balance']) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<table class="sign">
    <tr>
        <td><div class="box">Firma de la empresa</div></td>
        <td><div class="box">Firma del trabajador/a</div></td>
    </tr>
</table>

<p class="legal">
    @if(($company['country_code'] ?? null) === 'ES')
        Registro diario de jornada conforme al artículo 34.9 del Estatuto de los Trabajadores. La empresa conservará estos registros durante cuatro años.
    @else
        Registro mensual de jornada generado a partir de los fichajes del empleado.
    @endif
    Si detectas un error, solicita la corrección desde Reportes &gt; Ausencias o contacta con RRHH.
</p>
</body>
</html>
