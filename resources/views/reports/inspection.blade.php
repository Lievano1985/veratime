<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Vera Time</title>
    <style>
        body { margin: 2rem; color: #152541; font: 14px system-ui, sans-serif; }
        nav { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
        nav a, nav button { border: 1px solid #b7c9df; border-radius: .5rem; padding: .5rem .75rem; background: white; color: #174983; font: inherit; cursor: pointer; text-decoration: none; }
        .table { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d9e1ec; text-align: left; padding: .45rem; }
        th { background: #edf4fc; }
        .dates { white-space: nowrap; }
        @media print {
            @page { size: landscape; margin: 10mm; }
            body { margin: 0; font-size: 9pt; }
            nav { display: none; }
            .screen-only { display: none; }
            .table { overflow: visible; }
            th, td { padding: .2rem; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <nav aria-label="Acciones del reporte">
        <a href="{{ route('reports.index') }}">Volver a Reportes</a>
        <a href="{{ route('reports.index', ['tab' => 'history']) }}">Historial de expedientes</a>
        @if ($rows->isNotEmpty() || $eventRows->isNotEmpty())
            @isset($issuedAt)
                <a href="{{ route('reports.issued', ['folio' => $cut, 'format' => 'xlsx']) }}">Descargar Excel</a>
            @else
                <a href="{{ route('reports.inspection', ['cut' => $cut]) }}">Descargar Excel</a>
                <form action="{{ route('reports.issue') }}" method="post">
                    @csrf
                    <input type="hidden" name="cut" value="{{ $cut }}">
                    <button type="submit">Emitir expediente</button>
                </form>
            @endisset
            <button type="button" onclick="window.print()">Imprimir / guardar PDF</button>
        @endif
    </nav>
    <h1>{{ $title }}</h1>
    <p><strong>{{ $company->legal_name ?: $company->name }}</strong> · {{ $company->tax_id }}</p>
    <p>{{ $scope }} · {{ $filters['from'] }} al {{ $filters['to'] }} · {{ $rows->count() }} {{ ($filters['report'] ?? null) === 'weekly' ? 'semanas por empleado' : 'jornadas' }}</p>
    @isset($issuedAt)
        <p>Folio {{ $cut }} · Emitido el {{ $issuedAt }} por {{ $issuerName }}.</p>
        <p>Corte de datos del {{ $generatedAt }}.</p>
    @else
        <p>Corte {{ $cut }} · {{ $generatedAt }}. Puedes emitir o descargar este corte durante 30 minutos.</p>
    @endisset
    @if (($filters['report'] ?? null) === 'weekly')
        <p>Los totales usan la clasificación guardada de las jornadas registradas de lunes a domingo. «Contexto semanal» identifica jornadas fuera del rango o alcance seleccionado. Las jornadas sin clasificar se muestran aparte y no suman a los totales.</p>
    @else
        <p>Consulta operativa. Los valores vacíos indican datos no calculados o marcajes sin registrar en la jornada.</p>
    @endif
    <h2>{{ $summaryTitle }}</h2>
    <div class="table">
        <table>
            <thead><tr>@foreach ($columns as $column)<th scope="col">{{ $column }}</th>@endforeach @if (! empty($markingLinks))<th scope="col" class="screen-only">Detalle</th>@endif</tr></thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr>
                        @foreach ($row as $value)<td>{{ $value }}</td>@endforeach
                        @if (isset($markingLinks[$index]))
                            <td class="screen-only"><a href="{{ route('reports.inspection', $markingLinks[$index]) }}">Ver marcajes</a></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}">No hay jornadas registradas para estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <h2>{{ $detailTitle }}</h2>
    @if (($filters['report'] ?? null) !== 'weekly')
        <p>Los marcajes se listan por su fecha local. Las capturas manuales y los eventos anulados conservan su propio folio y se muestran con el motivo y responsable registrados.</p>
    @endif
    <div class="table">
        <table>
            <thead><tr>@foreach ($eventColumns as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($eventRows as $row)
                    <tr>@foreach ($row as $column => $value)<td @class(['dates' => $column === 4])>{{ $value }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($eventColumns) }}">No hay registros de detalle para estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
