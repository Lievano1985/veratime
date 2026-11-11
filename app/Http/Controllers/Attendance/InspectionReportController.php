<?php

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Actions\BuildWeeklyComplianceReportAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\AttendancePeriod;
use App\Models\TimeEvent;
use App\Models\WorkDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InspectionReportController
{
    public function issue(Request $request, CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        Gate::authorize('viewAny', [AttendancePeriod::class, $company]);
        $cut = $request->validate(['cut' => ['required', 'uuid']])['cut'];
        $user = $request->user();
        $issued = DB::table('issued_attendance_reports')->where('company_id', $company->id)
            ->where('issued_by', $user->id)->where('id', $cut)->exists();
        if ($issued) {
            return redirect()->route('reports.issued', $cut);
        }

        $report = Cache::get("inspection-report:{$company->id}:{$user->id}:{$cut}");
        abort_unless(is_array($report), 404, 'El corte venció. Genera una nueva vista previa en Reportes.');
        abort_if($report['rows']->isEmpty() && $report['eventRows']->isEmpty(), 422, 'El expediente no contiene registros.');
        $report['company'] = $report['company']->only(['legal_name', 'name', 'tax_id']);
        $report['issuedAt'] = now($company->timezone)->format('Y-m-d H:i:s T');
        $report['issuerName'] = $user->name;
        DB::table('issued_attendance_reports')->insertOrIgnore([
            'id' => $cut,
            'company_id' => $company->id,
            'issued_by' => $user->id,
            'issuer_name' => $user->name,
            'title' => $report['title'],
            'scope' => $report['scope'],
            'period_start' => $report['filters']['from'],
            'period_end' => $report['filters']['to'],
            'snapshot' => json_encode($report, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => now('UTC'),
        ]);

        return redirect()->route('reports.issued', $cut);
    }

    public function issued(Request $request, CurrentCompany $currentCompany, string $folio)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        Gate::authorize('viewAny', [AttendancePeriod::class, $company]);
        $record = DB::table('issued_attendance_reports')->where('company_id', $company->id)->where('id', $folio)->first();
        abort_unless($record, 404);
        $report = json_decode($record->snapshot, true, 512, JSON_THROW_ON_ERROR);
        $report['company'] = (object) $report['company'];
        $report['rows'] = collect($report['rows']);
        $report['eventRows'] = collect($report['eventRows']);
        // The links consult live data, so the issued document only presents the saved evidence.
        unset($report['markingLinks']);

        return $request->query('format') === 'xlsx'
            ? $this->xlsx($report)
            : response()->view('reports.inspection', $report)->header('Cache-Control', 'private, no-store');
    }

    public function __invoke(Request $request, CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        Gate::authorize('viewAny', [AttendancePeriod::class, $company]);

        $cachePrefix = "inspection-report:{$company->id}:{$request->user()->id}:";
        if ($request->filled('cut')) {
            $cut = $request->validate(['cut' => ['required', 'uuid']])['cut'];
            $report = Cache::get($cachePrefix.$cut);
            abort_unless(is_array($report), 404, 'El corte venció. Genera una nueva vista previa en Reportes.');

            return $this->xlsx($report);
        }

        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'scope' => ['required', Rule::in(['company', 'center', 'unit', 'worker'])],
            'center' => ['required_if:scope,center', 'nullable', 'integer', Rule::exists('centers', 'id')->where('company_id', $company->id)],
            'unit' => ['required_if:scope,unit', 'nullable', 'integer', Rule::exists('organizational_units', 'id')->where('company_id', $company->id)],
            'worker' => ['required_if:scope,worker', 'nullable', 'integer', Rule::exists('workers', 'id')->where('company_id', $company->id)],
            'report' => ['nullable', Rule::in(['inspection', 'weekly'])],
        ]);

        $query = WorkDay::query()->where('work_days.company_id', $company->id)
            ->whereDate('work_date', '>=', $filters['from'])
            ->whereDate('work_date', '<=', $filters['to'])
            ->with(['worker', 'center', 'activeCalculation', 'dailyScheduleAssignment.shiftTemplate'])
            ->orderBy('work_date')->orderBy('worker_id')->orderBy('id');
        $scope = match ($filters['scope']) {
            'center' => $company->centers()->findOrFail($filters['center'])->name,
            'unit' => $company->organizationalUnits()->findOrFail($filters['unit'])->name,
            'worker' => $company->workers()->findOrFail($filters['worker'])->full_name,
            default => 'Toda la empresa',
        };

        $this->applyScope($query, $filters, $company->id, 'work_days.work_date');

        $days = $query->limit(5001)->get();
        if ($days->count() > 5000) {
            return redirect()->route('reports.index')->withErrors(['from' => 'El reporte supera 5,000 jornadas. Reduce el rango o selecciona un alcance más específico.'])->withInput();
        }

        if (($filters['report'] ?? null) === 'weekly') {
            $report = app(BuildWeeklyComplianceReportAction::class)->handle($company->id, $days);

            return $this->preview($cachePrefix, compact('company', 'scope', 'filters') + $report);
        }

        $columns = ['Fecha', 'Clave', 'Trabajador', 'Centro', 'Horario', 'Primer marcaje', 'Último marcaje', 'Marcajes válidos', 'Minutos trabajados', 'Minutos ordinarios', 'Minutos extra', 'Retardo (min)', 'Salida anticipada (min)', 'Estado'];
        $rows = $days->map(function (WorkDay $day): array {
            $calculation = $day->activeCalculation;
            $status = match ($day->status) {
                WorkDay::STATUS_CALCULATED => 'Calculada',
                WorkDay::STATUS_WITH_ALERTS => 'Con avisos',
                WorkDay::STATUS_UNDER_REVIEW => 'En revisión',
                default => 'Pendiente',
            };

            return [
                $day->work_date->toDateString(), $day->worker?->employee_code, $day->worker?->full_name, $day->center?->name,
                $day->dailyScheduleAssignment?->shiftTemplate?->name ?? match ($day->day_type) {
                    'rest' => 'Descanso', 'flexible' => 'Flexible', 'on_call' => 'Guardia', default => 'Sin turno identificado',
                },
                $day->first_event_at_utc?->timezone($day->timezone)->format('d/m/Y H:i'),
                $day->last_event_at_utc?->timezone($day->timezone)->format('d/m/Y H:i'),
                $day->valid_time_event_count, $calculation?->total_work_minutes, $calculation?->ordinary_minutes,
                $calculation?->overtime_minutes, $calculation?->late_arrival_minutes, $calculation?->early_departure_minutes,
                $status,
            ];
        });

        $events = TimeEvent::query()->where('time_events.company_id', $company->id)
            ->whereDate('occurred_local_date', '>=', $filters['from'])
            ->whereDate('occurred_local_date', '<=', $filters['to'])
            ->with(['worker', 'center', 'sourceUser', 'voidedBy'])
            ->orderBy('occurred_at_utc')->orderBy('id');
        $this->applyScope($events, $filters, $company->id, 'time_events.occurred_local_date');
        $events = $events->limit(20001)->get();
        if ($events->count() > 20000) {
            return redirect()->route('reports.index')->withErrors(['from' => 'El reporte supera 20,000 marcajes. Reduce el rango o el alcance.'])->withInput();
        }

        $eventColumns = ['Folio', 'Clave', 'Trabajador', 'Centro', 'Fecha y hora local', 'Zona horaria', 'Evento', 'Origen', 'Estado', 'Recibido (UTC)', 'Registrado por', 'Motivo de captura', 'Anulado (UTC)', 'Anulado por', 'Motivo de anulación'];
        $eventRows = $events->map(fn (TimeEvent $event): array => [
            (string) $event->id, $event->worker?->employee_code, $event->worker?->full_name, $event->center?->name,
            trim($event->occurred_local_date?->toDateString().' '.$event->occurred_local_time), $event->timezone,
            match ($event->event_type) {
                'clock_in' => 'Entrada', 'clock_out' => 'Salida', 'break_start' => 'Inicio de pausa', 'break_end' => 'Fin de pausa', default => $event->event_type,
            },
            match ($event->source) {
                'admin_manual' => 'Captura manual', 'kiosk' => 'Kiosco', 'pwa' => 'App móvil', default => $event->source,
            },
            match ($event->status) {
                'valid' => 'Válido', 'pending_review' => 'Pendiente de revisión', 'voided' => 'Anulado', 'replaced' => 'Reemplazado', 'ignored' => 'Ignorado', default => $event->status,
            },
            $event->received_at?->utc()->format('Y-m-d H:i:s'), $event->sourceUser?->name,
            $event->source === 'admin_manual' ? data_get($event->metadata, 'reason') : null,
            $event->voided_at?->utc()->format('Y-m-d H:i:s'), $event->voidedBy?->name, $event->void_reason,
        ]);

        return $this->preview($cachePrefix, compact('company', 'scope', 'filters', 'columns', 'rows', 'eventColumns', 'eventRows'));
    }

    private function preview(string $cachePrefix, array $report)
    {
        $report['cut'] = (string) Str::uuid();
        $report['generatedAt'] = now($report['company']->timezone)->format('Y-m-d H:i:s T');
        $weekly = ($report['filters']['report'] ?? null) === 'weekly';
        $report['title'] = $weekly ? 'Cumplimiento semanal de jornada' : 'Expediente de jornada para inspección STPS';
        $report['summaryTitle'] = $weekly ? 'Resumen semanal' : 'Resumen de jornadas';
        $report['detailTitle'] = $weekly ? 'Detalle diario y avisos' : 'Marcajes y correcciones registradas';
        Cache::put($cachePrefix.$report['cut'], $report, now()->addMinutes(30));
        return response()->view('reports.inspection', $report)->header('Cache-Control', 'private, no-store');
    }

    private function applyScope(Builder $query, array $filters, int $companyId, string $dateColumn): void
    {
        match ($filters['scope']) {
            'center' => $query->where('center_id', $filters['center']),
            'worker' => $query->where('worker_id', $filters['worker']),
            'unit' => $query->whereHas('employmentRelationship.employmentUnitAssignments', fn ($units) => $units
                ->where('company_id', $companyId)
                ->where('organizational_unit_id', $filters['unit'])
                ->where(fn ($status) => $status->where('status', 'active')
                    ->orWhere(fn ($ended) => $ended->where('status', 'inactive')->whereNotNull('effective_to')))
                ->whereRaw("DATE(effective_from) <= DATE({$dateColumn})")
                ->where(fn ($dates) => $dates->whereNull('effective_to')->orWhereRaw("DATE(effective_to) >= DATE({$dateColumn})"))),
            default => null,
        };
    }

    private function xlsx(array $report)
    {
        return response()->streamDownload(function () use ($report): void {
            $spreadsheet = new Spreadsheet;
            $heading = [
                [$report['title']],
                [$report['company']->legal_name ?: $report['company']->name, $report['company']->tax_id],
                [$report['scope'], $report['filters']['from'], $report['filters']['to']],
                ['Corte', $report['cut'], $report['generatedAt']],
            ];
            if (isset($report['issuedAt'])) {
                $heading[3] = ['Folio', $report['cut'], $report['issuedAt'], $report['issuerName']];
            }

            foreach ([
                [$report['summaryTitle'], $report['columns'], $report['rows']],
                [$report['detailTitle'], $report['eventColumns'], $report['eventRows']],
            ] as $index => [$title, $columns, $rows]) {
                $sheet = ($index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet())->setTitle(Str::limit($title, 31, ''));
                $lines = [...$heading, $columns, ...$rows];
                foreach ($lines as $row => $values) {
                    foreach ($values as $column => $value) {
                        $sheet->setCellValueExplicit([$column + 1, $row + 1], $value ?? '', is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    }
                }
                $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
                $sheet->getStyle("A5:{$lastColumn}5")->getFont()->setBold(true);
                $sheet->setAutoFilter("A5:{$lastColumn}".count($lines));
                $sheet->freezePane('A6');
                $sheet->getDefaultColumnDimension()->setWidth(20);
            }
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, ($report['filters']['report'] ?? null) === 'weekly' ? 'cumplimiento-semanal.xlsx' : 'expediente-jornada.xlsx', ['Cache-Control' => 'private, no-store']);
    }
}
