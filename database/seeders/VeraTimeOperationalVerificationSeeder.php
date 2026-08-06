<?php

namespace Database\Seeders;

use App\Domains\Scheduling\Actions\BuildDailyScheduleSegmentsFromShiftTemplateAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\PublishScheduleBatchAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\UpdateShiftTemplateAction;
use App\Domains\WorkDays\Actions\ProcessCompanyWorkDaysAction;
use App\Models\Alert;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class VeraTimeOperationalVerificationSeeder extends Seeder
{
    private const COMPANY_TAX_ID = 'VTD260712XX1';
    private const ANCHOR_DATE = '2026-08-05';

    public function run(): void
    {
        $currentWeek = CarbonImmutable::parse(self::ANCHOR_DATE)->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $previousWeek = $currentWeek->subWeek();
        $end = $currentWeek->addDays(6);
        $existingCompany = Company::query()->where('tax_id', self::COMPANY_TAX_ID)->first();

        if ($existingCompany) {
            $this->clearOperationalRange($existingCompany, $previousWeek->toDateString(), $end->toDateString());
            $this->clearDemoTimeEvents($existingCompany, $previousWeek->toDateString(), $end->addDay()->toDateString());
        }

        $this->call(VeraTimeDemoSeeder::class);

        $company = Company::query()->where('tax_id', self::COMPANY_TAX_ID)->firstOrFail();
        $actor = User::query()->where('email', 'rh.demo@veratime.local')->firstOrFail();

        $this->clearOperationalRange($company, $previousWeek->toDateString(), $end->toDateString());

        $templates = $this->shiftTemplates($company);

        foreach ([$previousWeek, $currentWeek] as $weekStart) {
            foreach ($company->centers()->where('status', 'active')->orderBy('code')->get() as $center) {
                $batch = app(CreateScheduleBatchAction::class)->handle($company, $center, [
                    'period_start' => $weekStart->toDateString(),
                    'creation_source' => 'system',
                    'notes' => 'Demo operativo alineado para verificar calendario, eventos y jornadas.',
                ], $actor);

                $this->assignWeek($company, $center, $batch, $templates);

                app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch);
            }
        }

        app(ProcessCompanyWorkDaysAction::class)->handle(
            $company,
            $previousWeek->toDateString(),
            $end->toDateString(),
            actor: $actor,
            mode: 'seeder',
            reason: 'Seeder demo: calendario, eventos y jornadas alineados',
            onlyPendingOrStale: false,
        );
    }

    /**
     * @return array<string, ShiftTemplate>
     */
    private function shiftTemplates(Company $company): array
    {
        return [
            'day_8h' => $this->shiftTemplate($company, 'DIA8', 'Diurno demo 08:00-17:00', [
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '13:00', 'sort_order' => 1],
                ['segment_type' => 'break', 'timing_mode' => 'fixed', 'start_local_time' => '13:00', 'end_local_time' => '14:00', 'is_paid' => false, 'is_required' => true, 'sort_order' => 2],
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '14:00', 'end_local_time' => '17:00', 'sort_order' => 3],
            ]),
            'day_75h' => $this->shiftTemplate($company, 'DIA75', 'Diurno demo 08:00-16:00', [
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '12:30', 'sort_order' => 1],
                ['segment_type' => 'break', 'timing_mode' => 'fixed', 'start_local_time' => '12:30', 'end_local_time' => '13:00', 'is_paid' => false, 'is_required' => true, 'sort_order' => 2],
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '13:00', 'end_local_time' => '16:00', 'sort_order' => 3],
            ]),
            'night_75h' => $this->shiftTemplate($company, 'NOCT75', 'Nocturno demo 22:00-06:00', [
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '22:00', 'end_local_time' => '02:00', 'start_day_offset' => 0, 'end_day_offset' => 1, 'sort_order' => 1],
                ['segment_type' => 'break', 'timing_mode' => 'fixed', 'start_local_time' => '02:00', 'end_local_time' => '02:30', 'start_day_offset' => 1, 'end_day_offset' => 1, 'is_paid' => false, 'is_required' => true, 'sort_order' => 2],
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '02:30', 'end_local_time' => '06:00', 'start_day_offset' => 1, 'end_day_offset' => 1, 'sort_order' => 3],
            ]),
        ];
    }

    /**
     * @param list<array<string, mixed>> $segments
     */
    private function shiftTemplate(Company $company, string $code, string $name, array $segments): ShiftTemplate
    {
        $data = [
            'code' => $code,
            'name' => $name,
            'description' => 'Plantilla demo para verificacion operativa de jornadas.',
            'status' => 'active',
            'metadata' => ['demo' => true, 'operational_verification' => true],
        ];

        $template = ShiftTemplate::query()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->first();

        return $template
            ? app(UpdateShiftTemplateAction::class)->handle($company, $template, $data, $segments)
            : app(CreateShiftTemplateAction::class)->handle($company, $data, $segments);
    }

    /**
     * @param array<string, ShiftTemplate> $templates
     */
    private function assignWeek(Company $company, Center $center, ScheduleBatch $batch, array $templates): void
    {
        $relationships = EmploymentRelationship::query()
            ->with('worker')
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->where('status', 'active')
            ->orderBy('worker_id')
            ->get();

        foreach ($relationships as $relationship) {
            $template = $this->templateForRelationship($relationship, $templates);

            for ($offset = 0; $offset < 7; $offset++) {
                $date = $batch->period_start->copy()->addDays($offset)->toDateString();
                $isWorkDay = $offset < 5;

                $this->replaceAssignment(
                    $company,
                    $batch,
                    $relationship,
                    $date,
                    $isWorkDay ? 'shift' : 'rest',
                    $isWorkDay ? $template : null,
                );
            }
        }
    }

    /**
     * @param array<string, ShiftTemplate> $templates
     */
    private function templateForRelationship(EmploymentRelationship $relationship, array $templates): ShiftTemplate
    {
        return match ($relationship->worker?->employee_code) {
            'VT-002' => $templates['night_75h'],
            'VT-004' => $templates['day_75h'],
            default => $templates['day_8h'],
        };
    }

    private function replaceAssignment(
        Company $company,
        ScheduleBatch $batch,
        EmploymentRelationship $relationship,
        string $date,
        string $dayType,
        ?ShiftTemplate $template,
    ): void {
        $segments = $template
            ? app(BuildDailyScheduleSegmentsFromShiftTemplateAction::class)->handle($template, $date, $batch->center->timezone ?: $company->timezone)
            : [];

        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $relationship, [
            'work_date' => $date,
            'day_type' => $dayType,
            'timezone' => $batch->center->timezone ?: $company->timezone,
            'shift_template_id' => $template?->id,
            'source_type' => 'system',
            'source_reference' => [
                'source' => 'vera_time_operational_verification_seeder',
                'anchor_date' => self::ANCHOR_DATE,
            ],
            'metadata' => ['demo' => true, 'operational_verification' => true],
        ], $segments);
    }

    private function clearOperationalRange(Company $company, string $startDate, string $endDate): void
    {
        $workDayIds = WorkDay::query()
            ->where('company_id', $company->id)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->pluck('id');

        if ($workDayIds->isNotEmpty()) {
            Alert::query()->where('company_id', $company->id)->whereIn('work_day_id', $workDayIds)->delete();
            WorkDay::query()->whereIn('id', $workDayIds)->update(['active_calculation_id' => null]);
            WorkDayCalculation::query()->whereIn('work_day_id', $workDayIds)->delete();
            WorkDay::query()->whereIn('id', $workDayIds)->delete();
        }

        $batchIds = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', '<=', $endDate)
            ->whereDate('period_end', '>=', $startDate)
            ->pluck('id');

        if ($batchIds->isEmpty()) {
            return;
        }

        $assignmentIds = DailyScheduleAssignment::query()
            ->whereIn('schedule_batch_id', $batchIds)
            ->pluck('id');

        DailyScheduleSegment::query()->whereIn('daily_schedule_assignment_id', $assignmentIds)->delete();
        DailyScheduleAssignment::query()->whereIn('id', $assignmentIds)->delete();
        ScheduleBatch::query()->whereIn('id', $batchIds)->delete();
    }

    private function clearDemoTimeEvents(Company $company, string $startDate, string $endDate): void
    {
        TimeEvent::query()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'like', 'demo-%')
            ->whereDate('occurred_local_date', '>=', $startDate)
            ->whereDate('occurred_local_date', '<=', $endDate)
            ->delete();
    }
}
