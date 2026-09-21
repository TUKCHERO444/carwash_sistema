<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('titulo')</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #0f172a; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin: 0 0 14px 0; }
        th { background-color: #e2e8f0; padding: 5px 7px; text-align: left; border: 1px solid #cbd5e1; font-size: 8pt; }
        td { padding: 4px 7px; border: 1px solid #d7dde8; vertical-align: top; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        h2 { font-size: 10.5pt; margin: 12px 0 6px 0; color: #1e293b; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; }
        .derecha { text-align: right; white-space: nowrap; }
        .centro { text-align: center; }
        .vacio { color: #64748b; font-style: italic; }
        .cabecera { border: none; margin-bottom: 12px; }
        .cabecera td { border: none; padding: 0; }
        .titulo-texto { font-size: 15pt; font-weight: bold; }
        .subtitulo-texto { font-size: 8.5pt; color: #475569; margin-top: 2px; }
        .generado-texto { text-align: right; font-size: 7.5pt; color: #64748b; white-space: nowrap; }
        .kpis { margin: 0 0 12px 0; }
        .kpis td { border: 1px solid #cbd5e1; background-color: #f8fafc; padding: 7px 9px; }
        .kpi-label { font-size: 7pt; color: #64748b; }
        .kpi-valor { font-size: 12.5pt; font-weight: bold; margin-top: 3px; }
        .resaltado { font-weight: bold; }
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td>
                <div class="titulo-texto">@yield('titulo')</div>
                <div class="subtitulo-texto">@yield('subtitulo')</div>
            </td>
            <td class="generado-texto">
                Generado el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }}
            </td>
        </tr>
    </table>
    @yield('contenido')
</body>
</html>