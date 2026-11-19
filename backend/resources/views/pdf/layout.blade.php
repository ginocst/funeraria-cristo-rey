<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>@yield('titulo')</title>
    <style>
        @page {
            margin: 28px 34px 40px 34px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #2a1d1f;
        }

        .cabecera {
            border-bottom: 2px solid #8f1d24;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .cabecera td {
            vertical-align: middle;
        }

        .logo {
            width: 74px;
        }

        .marca {
            font-size: 15px;
            color: #8f1d24;
            font-weight: bold;
            letter-spacing: .3px;
        }

        .sub {
            color: #6d5a5c;
            font-size: 9.5px;
        }

        h1 {
            font-size: 15px;
            margin: 0 0 2px 0;
            color: #5e1419;
        }

        h2 {
            font-size: 11.5px;
            color: #8f1d24;
            margin: 14px 0 6px;
            border-bottom: 1px solid #e3cfd1;
            padding-bottom: 3px;
        }

        table.datos {
            width: 100%;
            border-collapse: collapse;
        }

        table.datos th {
            text-align: left;
            background: #f6eeef;
            color: #5e1419;
            font-weight: bold;
            padding: 5px 6px;
            border-bottom: 1px solid #e3cfd1;
        }

        table.datos td {
            padding: 5px 6px;
            border-bottom: 1px solid #efe5e6;
        }

        .kv td {
            padding: 3px 6px 3px 0;
            vertical-align: top;
        }

        .kv td.k {
            color: #6d5a5c;
            width: 34%;
        }

        .num {
            text-align: right;
        }

        .total {
            font-weight: bold;
            font-size: 12px;
        }

        .caja {
            border: 1px solid #e3cfd1;
            padding: 8px 10px;
            background: #fbf7f7;
        }

        .pie {
            position: fixed;
            bottom: -24px;
            left: 0;
            right: 0;
            font-size: 8.5px;
            color: #8a7a7c;
            text-align: center;
        }

        .firma {
            margin-top: 46px;
            width: 100%;
        }

        .firma td {
            width: 50%;
            text-align: center;
            padding: 0 20px;
        }

        .linea {
            border-top: 1px solid #2a1d1f;
            padding-top: 4px;
        }
    </style>
</head>

<body>
    <table width="100%" class="cabecera">
        <tr>
            <td style="width:84px"><img src="{{ $logo }}" class="logo" alt="Funeraria Cristo Rey"></td>
            <td>
                <div class="marca">Funeraria Cristo Rey</div>
                <div class="sub">Temuco y Padre Las Casas · Región de La Araucanía</div>
            </td>
            <td style="text-align:right">
                <h1>@yield('titulo')</h1>
                <div class="sub">@yield('subtitulo')</div>
            </td>
        </tr>
    </table>
    @yield('contenido')
    <div class="pie">Documento generado por el sistema el {{ \App\Shared\Infrastructure\HoraLocal::formato(now()) }}.</div>
</body>

</html>