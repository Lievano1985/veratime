<?php

namespace Database\Seeders;

use App\Domains\Scheduling\Actions\BuildDailyScheduleSegmentsFromShiftTemplateAction;
use App\Domains\Scheduling\Actions\CreateCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\PublishCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class VeraTimeCorrectedScheduleScenarioSeeder extends Seeder
{
    private const PERIOD_START = '2026-08-03';
    private const PERIOD_END = '2026-08-09';

    public function run(): void
    {
        $this->call(VeraTimePublishedScheduleScenarioSeeder::class);

        $this->publishedCorrection('VTSP-OFFICE', 'office');
        $this->draftCorrection('VTSP-CYCLE', 'cycle');
    }

    private function publishedCorrection(string $taxId, string $slug): void
    {
        $company = Company::query()->where('tax_id', $taxId)->firstOrFail();
        $actor = User::query()->where('email', "rh.{$slug}.demo@veratime.local")->firstOrFail();

        $current = $this->currentPublished($company);
        if ($current->version > 1) {
            return;
        }

        $draft = $this->createCorrection($actor, $company, $current, 'Correccion demo publicada para revisar historial versionado.');
        $this->changeFirstShiftToRest($company, $draft, 'Cambio demo publicado: descanso autorizado.');

        app(PublishCorrectiveScheduleBatchAction::class)->handle($actor, $company, $draft);
    }

    private function draftCorrection(string $taxId, string $slug): void
    {
        $company = Company::query()->where('tax_id', $taxId)->firstOrFail();
        $actor = User::query()->where('email', "rh.{$slug}.demo@veratime.local")->firstOrFail();
        $current = $this->currentPublished($company);

        $existing = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('previous_batch_id', $current->id)
            ->where('status', 'draft')
            ->first();
        if ($existing) {
            return;
        }

        $draft = $this->createCorrection($actor, $company, $current, 'Correccion demo en borrador para probar la interfaz.');
        $this->changeFirstShiftToAnotherShift($company, $draft, 'Cambio demo en borrador: ajuste de turno.');
    }

    private function createCorrection(User $actor, Company $company, ScheduleBatch $published, string $reason): ScheduleBatch
    {
        return app(CreateCorrectiveScheduleBatchAction::class)
            ->handle($actor, $company, $published, $reason)
            ->correctiveBatch;
    }

    private function changeFirstShiftToRest(Company $company, ScheduleBatch $draft, string $reason): void
    {
        $assignment = $this->firstShift($draft);
        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $draft, $assignment->employmentRelationship, [
            'work_date' => $assignment->work_date->toDateString(),
            'day_type' => 'rest',
            'timezone' => $assignment->timezone,
            'source_type' => 'manual',
            'source_reference' => [
                'schema_version' => 1,
                'editor' => 'demo_seeder',
                'correction' => true,
                'reason' => $reason,
                'previous_source_type' => $assignment->source_type,
            ],
        ]);
    }

    private function changeFirstShiftToAnotherShift(Company $company, ScheduleBatch $draft, string $reason): void
    {
        $assignment = $this->firstShift($draft);
        $template = ShiftTemplate::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereKeyNot($assignment->shift_template_id)
            ->orderBy('id')
            ->firstOrFail();

        $segments = app(BuildDailyScheduleSegmentsFromShiftTemplateAction::class)
            ->handle($template, $assignment->work_date->toDateString(), $draft->center->timezone ?: $company->timezone);

        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $draft, $assignment->employmentRelationship, [
            'work_date' => $assignment->work_date->toDateString(),
            'day_type' => 'shift',
            'timezone' => $assignment->timezone,
            'shift_template_id' => $template->id,
            'source_type' => 'manual',
            'source_reference' => [
                'schema_version' => 1,
                'editor' => 'demo_seeder',
                'correction' => true,
                'reason' => $reason,
                'previous_source_type' => $assignment->source_type,
            ],
        ], $segments);
    }

    private function firstShift(ScheduleBatch $draft): DailyScheduleAssignment
    {
        return DailyScheduleAssignment::query()
            ->with(['employmentRelationship', 'scheduleBatch.center'])
            ->where('schedule_batch_id', $draft->id)
            ->where('day_type', 'shift')
            ->orderBy('work_date')
            ->orderBy('employment_relationship_id')
            ->firstOrFail();
    }

    private function currentPublished(Company $company): ScheduleBatch
    {
        return ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', self::PERIOD_START)
            ->whereDate('period_end', self::PERIOD_END)
            ->where('status', 'published')
            ->orderByDesc('version')
            ->firstOrFail();
    }
}
