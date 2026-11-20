<!doctype html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden">
        <tr>
            <td style="background:#2563eb;color:#ffffff;padding:18px 24px;font-size:16px;font-weight:bold">
                {{ $companyName ?: 'RRHH' }}
            </td>
        </tr>
        <tr>
            <td style="padding:24px;font-size:14px;line-height:1.6">
                <p style="margin:0 0 12px">Hola {{ $employeeName }},</p>
                <p style="margin:0 0 12px">
                    Te enviamos el resumen de tu asistencia de <strong>{{ $monthLabel }}</strong>: fichajes de cada día,
                    horas trabajadas frente a las teóricas, vacaciones, festivos y ausencias.
                </p>
                <p style="margin:0 0 12px">
                    Lo tienes adjunto en PDF y también en la aplicación, en <strong>Documentos &gt; Asistencia</strong>.
                </p>
                <p style="margin:0;color:#6b7280;font-size:12px">
                    Si ves algún error, solicita la corrección desde Reportes &gt; Ausencias o escribe a RRHH.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
