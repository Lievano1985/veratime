<?php

namespace Tests\Feature;

use App\Domains\Scheduling\Actions\ApplyPlannerScheduleAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Livewire\Scheduling\Planner;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\ScheduleProfileAssignment;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PlannerScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_cells_render_a_valid_selection_handler_and_can_be_removed_from_a_column(): void
    {
        [, $center, , $people] = $this->scenario();
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->assertSeeHtml('toggleCells([cellKey], !selectedCells.includes(cellKey))')
            ->assertSeeHtml('selectedCells.includes(cellKey)')
            ->assertDontSeeHtml('@js($key)')
            ->call('selectDay', '2026-11-02')->assertHasNoErrors();
        $key = $people[0]->id.'|2026-11-02';
        $selected = $ui->get('cells');
        $this->assertContains($key, $selected);
        $ui->call('toggleCell', $people[0]->id, '2026-11-02')->assertHasNoErrors();
        $this->assertNotContains($key, $ui->get('cells'));
        $this->assertCount(count($selected) - 1, $ui->get('cells'));
        $ui->call('toggleCell', $people[0]->id, '2026-11-02')->assertHasNoErrors();
        $this->assertContains($key, $ui->get('cells'));
    }

    public function test_copy_week_search_changes_only_destination_and_handles_iso_year_boundaries(): void
    {
        [, $center, , , , $batch] = $this->scenario();
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->set('cloneWeeks', 3)->set('copyWeekSearchYear', 2020)->set('copyWeekSearchNumber', 53)->call('chooseCopyWeek')
            ->assertHasNoErrors()->assertSet('copyTarget', '2020-12-28')->assertSet('week', '2026-11-02')->assertSet('batchId', $batch->id)
            ->call('navigateCopyWeek', 1)->assertSet('copyTarget', '2021-01-04')->assertSet('copyWeekSearchYear', 2021)->assertSet('copyWeekSearchNumber', 1)
            ->assertSet('cloneWeeks', 3)->set('copyWeekSearchNumber', 53)->call('chooseCopyWeek')->assertHasErrors('copyWeekSearchNumber')
            ->assertSet('copyTarget', '2021-01-04')->assertSet('week', '2026-11-02');
    }

    public function test_cloning_multiple_weeks_as_drafts_or_publications_reuses_the_published_source(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn, 'manual'));
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->call('reviewWeek')->call('publish')->assertHasNoErrors()
            ->assertSee('Cambiar publicación')->assertSee('Clonar semana')->assertDontSee('Preparar próximas semanas con los horarios habituales');
        $hash = $batch->fresh()->snapshot_sha256;
        $ui->set('copyTarget', '2026-11-09')->set('cloneWeeks', 2)->call('cloneWeek')->assertHasNoErrors()->assertSet('week', '2026-11-16')
            ->assertSet('weekSearchYear', 2026)->assertSet('weekSearchNumber', 47)
            ->assertSee('Del 16/11/2026 al 22/11/2026')
            ->assertSeeHtml('wire:key="planner-week-selector-'.$center->id.'-2026-11-16"')
            ->assertDispatched('planner-week-changed');
        $this->assertSame(2, $company->scheduleBatches()->where('status', 'draft')->count());
        $ui->set('week', '2026-11-02')->set('copyTarget', '2026-11-23')->set('cloneMode', 'publish')->call('cloneWeek')->assertHasNoErrors()->assertSet('week', '2026-11-30')
            ->assertSet('weekSearchYear', 2026)->assertSet('weekSearchNumber', 49)
            ->assertSee('Del 30/11/2026 al 06/12/2026')
            ->assertSeeHtml('wire:key="planner-week-selector-'.$center->id.'-2026-11-30"');
        $this->assertSame(3, $company->scheduleBatches()->where('status', 'published')->whereNotNull('snapshot_sha256')->count());
        $this->assertSame($hash, $batch->fresh()->snapshot_sha256);
    }

    public function test_multi_week_clone_rolls_back_earlier_publications_when_later_week_conflicts(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn, 'manual'));
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->call('reviewWeek')->call('publish')->assertHasNoErrors();
        app(CreateScheduleBatchAction::class)->handle($company, $center, ['period_start' => '2026-11-16'], $user);
        $ui->set('copyTarget', '2026-11-09')->set('cloneWeeks', 2)->set('cloneMode', 'publish')->call('cloneWeek')->assertHasErrors('planner');
        $this->assertSame(2, $company->scheduleBatches()->count());
        $this->assertFalse($company->scheduleBatches()->whereDate('period_start', '2026-11-09')->exists());
        $this->assertSame('published', $batch->fresh()->status);
    }

    public function test_direct_clone_publication_still_blocks_missing_coverage_for_new_people(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn, 'manual'));
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->call('reviewWeek')->call('publish')->assertHasNoErrors();
        EmploymentRelationship::factory()->create(['company_id' => $company->id, 'center_id' => $center->id,
            'worker_id' => Worker::factory()->for($company)->create()->id, 'started_at' => '2026-11-09']);
        $ui->set('copyTarget', '2026-11-09')->set('cloneMode', 'publish')->call('cloneWeek')->assertHasErrors('planner');
        $this->assertSame(1, $company->scheduleBatches()->count());
    }

    public function test_reset_clears_all_draft_schedules_despite_filters_and_keeps_habitual_profiles(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn));
        Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class)
            ->assertSee('Restablecer horarios')->set('search', 'Persona 1')
            ->call('openResetConfirmation')->assertSet('showResetConfirmation', true)
            ->assertSee('Restablecer horarios del borrador')
            ->call('resetDraftSchedules')->assertHasNoErrors()->assertSet('showResetConfirmation', false)
            ->assertSet('batchId', $batch->id)->assertSet('cells', [])->assertSet('people', [])
            ->call('reviewWeek')->assertSet('review.valid', false);
        $this->assertSame('draft', $batch->fresh()->status);
        $this->assertSame(14, $batch->dailyAssignments()->where('day_type', 'unassigned')->whereNull('shift_template_id')->count());
        $this->assertDatabaseCount('daily_schedule_segments', 0);
        $this->assertDatabaseCount('schedule_profiles', 1);
        $this->assertDatabaseCount('schedule_profile_assignments', 2);
        $this->assertDatabaseHas('shift_templates', ['id' => $turn->id]);
    }

    public function test_reset_is_forbidden_on_publications_and_keeps_original_snapshot_when_resetting_correction(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn, 'manual'));
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->call('reviewWeek')->call('publish')->assertHasNoErrors()->assertDontSee('Restablecer horarios');
        $hash = $batch->fresh()->snapshot_sha256;
        $ui->call('resetDraftSchedules')->assertForbidden();
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->set('correctionReason', 'Reprogramación')->call('createCorrection')->call('resetDraftSchedules')->assertHasNoErrors()
            ->call('reviewWeek')->assertSet('review.valid', false);
        $this->assertSame('published', $batch->fresh()->status);
        $this->assertSame($hash, $batch->fresh()->snapshot_sha256);
        $this->assertSame(14, $batch->dailyAssignments()->count());
    }

    public function test_day_selector_selects_all_filtered_pages_and_skips_people_not_yet_effective(): void
    {
        [$company, $center, , $people, $turn, $batch] = $this->scenario();
        EmploymentRelationship::factory()->count(20)->create([
            'company_id' => $company->id, 'center_id' => $center->id,
            'worker_id' => $people[0]->worker_id, 'started_at' => '2026-01-01',
        ]);
        $people[1]->update(['started_at' => '2026-11-04']);
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->call('selectDay', '2026-11-02')->assertHasNoErrors();
        $this->assertCount(21, $ui->get('cells'));
        $ui->set('brush', (string) $turn->id)->call('applyCells')->assertHasNoErrors();
        $this->assertSame(21, $batch->dailyAssignments()->count());
        $ui->set('search', 'Persona 2')->call('selectDay', '2026-11-04')
            ->assertSet('cells', [$people[1]->id.'|2026-11-04'])
            ->call('selectDay', '2026-11-04')->assertSet('cells', [])
            ->call('selectDay', '2026-12-01')->assertHasErrors('planner');
    }

    public function test_week_search_handles_old_weeks_and_iso_year_boundaries(): void
    {
        [, $center] = $this->scenario();
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->set('weekSearchYear', 2020)->set('weekSearchNumber', 53)->call('jumpToWeek')
            ->assertHasNoErrors()->assertSet('week', '2020-12-28')->assertSee('03/01/2021');
        $ui->call('navigateWeek', 1)->assertSet('week', '2021-01-04')->assertSet('weekSearchNumber', 1)->assertSet('weekSearchYear', 2021);
        $ui->set('weekSearchNumber', 53)->call('jumpToWeek')->assertHasErrors('weekSearchNumber')->assertSet('week', '2021-01-04');
    }

    private function scenario(): array
    {
        $company = Company::factory()->create();
        $center = Center::factory()->for($company)->create();
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['key' => RoleKey::RH_ADMIN], ['name' => 'RH', 'is_system' => true]);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $people = collect(range(1, 2))->map(fn ($i) => EmploymentRelationship::factory()->create([
            'company_id' => $company->id, 'center_id' => $center->id,
            'worker_id' => Worker::factory()->for($company)->create(['full_name' => 'Persona '.$i])->id,
            'started_at' => '2026-01-01',
        ]));
        $turn = app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'DIA', 'name' => 'Diurno'], [[
            'segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '16:00',
            'start_day_offset' => 0, 'end_day_offset' => 0, 'sort_order' => 1,
        ]]);
        $batch = app(CreateScheduleBatchAction::class)->handle($company, $center, ['period_start' => '2026-11-02'], $user);

        return [$company, $center, $user, $people, $turn, $batch];
    }

    private function input($people, $turn, string $mode = 'weekly'): array
    {
        return ['relationships' => $people->pluck('id')->all(), 'mode' => $mode, 'name' => 'Oficina',
            'effective_from' => '2026-11-02', 'effective_to' => null, 'replace_days' => false, 'replace_habitual' => false,
            'rules' => collect(range(1, 7))->map(fn ($day) => ['day' => $day, 'value' => $day < 6 ? (string) $turn->id : 'rest'])->all()];
    }

    public function test_draft_can_be_deleted_without_removing_reusable_schedules(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn));
        Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class)
            ->assertSee('Eliminar borrador')->call('deleteDraft')->assertHasNoErrors()->assertSet('batchId', null)
            ->assertSee('Semana sin preparar');
        $this->assertDatabaseMissing('schedule_batches', ['id' => $batch->id]);
        $this->assertDatabaseCount('daily_schedule_assignments', 0);
        $this->assertDatabaseCount('daily_schedule_segments', 0);
        $this->assertDatabaseCount('schedule_profiles', 1);
        $this->assertDatabaseCount('schedule_profile_assignments', 2);
        $this->assertDatabaseHas('shift_templates', ['id' => $turn->id]);
    }

    public function test_deleting_a_correction_restores_published_view_and_cannot_delete_publication(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn, 'manual'));
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->call('reviewWeek')->call('publish')->assertHasNoErrors();
        $hash = $batch->fresh()->snapshot_sha256;
        $ui->set('correctionReason', 'Prueba de corrección')->call('createCorrection')->assertHasNoErrors()->assertSet('correctionReason', '');
        $correctionId = $ui->get('batchId');
        $this->assertDatabaseHas('schedule_batches', ['id' => $correctionId, 'correction_reason' => 'Prueba de corrección']);
        $ui->call('deleteDraft')->assertHasNoErrors()->assertSet('batchId', $batch->id)->assertDontSee('Eliminar borrador');
        $this->assertDatabaseMissing('schedule_batches', ['id' => $correctionId]);
        $this->assertSame('published', $batch->fresh()->status);
        $this->assertSame($hash, $batch->fresh()->snapshot_sha256);
        $ui->call('deleteDraft')->assertForbidden();
        $this->assertDatabaseHas('schedule_batches', ['id' => $batch->id]);
    }

    public function test_weekly_assignment_creates_one_reusable_profile_and_generates_future_weeks(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        $result = app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn));
        $this->assertSame(14, $result['changed']);
        $this->assertDatabaseCount('schedule_profiles', 1);
        $this->assertDatabaseCount('schedule_profile_assignments', 2);
        $this->assertSame(4, $batch->dailyAssignments()->where('day_type', 'rest')->count());
        $future = app(CreateScheduleBatchAction::class)->handle($company, $center, ['period_start' => '2026-11-09'], $user);
        app(GenerateDraftScheduleBatchFromProfilesAction::class)->handle($user, $company, $future);
        $this->assertSame(10, $future->dailyAssignments()->where('day_type', 'shift')->count());
    }

    public function test_manual_assignment_has_no_profile_setup_and_preserves_defined_days_by_default(): void
    {
        [$company, , $user, $people, $turn, $batch] = $this->scenario();
        $input = $this->input($people, $turn, 'manual');
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
        $input['rules'][0]['value'] = 'rest';
        $result = app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
        $this->assertSame(14, $result['preserved']);
        $this->assertSame(0, $result['changed']);
        $this->assertDatabaseCount('schedule_profiles', 0);
        $input['replace_days'] = true;
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
        $this->assertSame(6, $batch->dailyAssignments()->where('day_type', 'rest')->count());
    }

    public function test_cycle_uses_assignment_date_as_day_one_across_weeks(): void
    {
        [$company, , $user, $people, $turn, $batch] = $this->scenario();
        $input = $this->input($people, $turn, 'cycle');
        $input['effective_from'] = '2026-10-30';
        $input['rules'] = [['day' => 1, 'value' => (string) $turn->id], ['day' => 2, 'value' => 'rest'], ['day' => 3, 'value' => 'rest']];
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
        $this->assertSame('shift', $batch->dailyAssignments()->where('employment_relationship_id', $people[0]->id)->whereDate('work_date', '2026-11-02')->firstOrFail()->day_type);
        $this->assertSame('rest', $batch->dailyAssignments()->where('employment_relationship_id', $people[0]->id)->whereDate('work_date', '2026-11-03')->firstOrFail()->day_type);
    }

    public function test_cross_company_selection_rolls_back_everything(): void
    {
        [$company, , $user, $people, $turn, $batch] = $this->scenario();
        $input = $this->input($people, $turn);
        $input['relationships'][] = EmploymentRelationship::factory()->create()->id;
        try {
            app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
            $this->fail('Cross-company assignment accepted');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('otra empresa', $error->getMessage());
        }
        $this->assertDatabaseCount('schedule_profiles', 0);
        $this->assertSame(0, $batch->dailyAssignments()->count());
    }

    public function test_overlapping_habitual_assignment_requires_explicit_replacement_and_is_atomic(): void
    {
        [$company, , $user, $people, $turn, $batch] = $this->scenario();
        $input = $this->input($people, $turn);
        $input['effective_from'] = '2026-10-01';
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
        $input['effective_from'] = '2026-11-02';
        try {
            app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
            $this->fail('Overlap accepted');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('habituales', $error->getMessage());
        }
        $this->assertDatabaseCount('schedule_profiles', 1);
        $input['replace_habitual'] = true;
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
        $this->assertSame(2, ScheduleProfileAssignment::where('status', 'replaced')->count());
        $this->assertSame(14, $batch->dailyAssignments()->count());
    }

    public function test_existing_models_are_reused_without_duplication(): void
    {
        [$company, , $user, $people, $turn, $batch] = $this->scenario();
        $input = $this->input($people->take(1), $turn);
        $result = app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
        $input = $this->input($people->skip(1), $turn, 'existing');
        $input['profile_id'] = $result['profile_id'];
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $input);
        $this->assertDatabaseCount('schedule_profiles', 1);
        $this->assertSame(14, $batch->dailyAssignments()->count());
    }

    public function test_planner_renders_and_filters_workers(): void
    {
        [, $center] = $this->scenario();
        Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class)
            ->assertSee('Planificador de horarios')->assertSee('Persona 1')->assertSee('Persona 2')
            ->set('search', 'Persona 1')->assertSee('Persona 1')->assertDontSee('Persona 2');
    }

    public function test_quick_edit_and_publication_block_missing_people_then_freeze_evidence(): void
    {
        [$company, $center, , $people, $turn, $batch] = $this->scenario();
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->set('cells', [$people[0]->id.'|2026-11-02'])->set('brush', (string) $turn->id)->call('applyCells')->assertHasNoErrors();
        $ui->call('reviewWeek')->assertSet('review.valid', false);
        $ui->set('people', $people->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->set('rules', array_fill(1, 7, (string) $turn->id))->call('applyAssignment')->assertHasNoErrors()
            ->call('reviewWeek')->assertSet('review.valid', true)->call('publish')->assertHasNoErrors();
        $this->assertSame('published', $batch->fresh()->status);
        $hash = $batch->fresh()->snapshot_sha256;
        $ui->set('cells', [$people[0]->id.'|2026-11-02'])->set('brush', 'rest')->call('applyCells')->assertForbidden();
        $this->assertSame($hash, $batch->fresh()->snapshot_sha256);
    }

    public function test_quick_edit_rolls_back_when_one_cell_is_invalid(): void
    {
        [, $center, , $people, $turn, $batch] = $this->scenario();
        Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class)
            ->set('brush', (string) $turn->id)->set('cells', [$people[0]->id.'|2026-11-02', $people[0]->id.'|2027-01-01'])
            ->call('applyCells')->assertHasErrors('planner');
        $this->assertSame(0, $batch->dailyAssignments()->count());
    }

    public function test_planner_reuses_catalog_sidebar_and_receives_saved_turn_without_losing_selection(): void
    {
        [$company, $center, , $people] = $this->scenario();
        $planner = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class)
            ->assertSeeLivewire('scheduling.shifts')->set('people', [(string) $people[0]->id])
            ->call('openCreateShift')->assertDispatched('planner-create-shift');
        $catalog = Volt::test('scheduling.shifts', ['embedded' => true])
            ->assertSet('embedded', true)->assertDontSee('No hay plantillas con los filtros actuales.')
            ->dispatch('planner-create-shift')->assertSet('showFormPanel', true)
            ->set('form.code', 'NOCHE')->set('form.name', 'Noche')
            ->set('segments.0.start_local_time', '22:00')->set('segments.0.end_local_time', '06:00')
            ->set('segments.0.end_day_offset', '1')->call('save')->assertHasNoErrors()
            ->assertSet('showFormPanel', false);
        $turn = $company->shiftTemplates()->where('name', 'Noche')->firstOrFail();
        $catalog->assertDispatched('planner-shift-saved', templateId: $turn->id);
        $planner->dispatch('planner-shift-saved', templateId: $turn->id)
            ->assertSet('fillTurn', (string) $turn->id)->assertSet('brush', (string) $turn->id)
            ->assertSet('people', [(string) $people[0]->id]);
        $this->assertSame(1, $turn->segments()->count());
        $this->assertSame(1, $turn->segments()->where('segment_type', 'work')->firstOrFail()->end_day_offset);
    }

    public function test_forged_foreign_center_is_rejected(): void
    {
        $this->scenario();
        $foreign = Center::factory()->create();
        $this->expectException(ModelNotFoundException::class);
        Livewire::withQueryParams(['centerId' => (string) $foreign->id, 'week' => '2026-11-02'])->test(Planner::class);
    }

    public function test_copy_row_copies_segments_without_creating_profiles(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people->take(1), $turn, 'manual'));
        Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class)
            ->set('copySource', (string) $people[0]->id)->set('people', [(string) $people[1]->id])->call('copyRow')->assertHasNoErrors();
        $this->assertSame(14, $batch->dailyAssignments()->count());
        $copied = $batch->dailyAssignments()->where('employment_relationship_id', $people[1]->id)->whereDate('work_date', '2026-11-02')->firstOrFail();
        $this->assertSame('08:00:00', $copied->segments()->firstOrFail()->start_local_time);
        $this->assertSame('planner_row_copy', $copied->source_reference['reason']);
        $this->assertDatabaseCount('schedule_profiles', 0);
    }

    public function test_publication_can_be_cloned_and_corrected_without_mutating_original_snapshot(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn, 'manual'));
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->call('reviewWeek')->call('publish')->assertHasNoErrors();
        $hash = $batch->fresh()->snapshot_sha256;
        $ui->set('copyTarget', '2026-11-09')->call('cloneWeek')->assertHasNoErrors()->assertSet('week', '2026-11-09');
        $copy = $company->scheduleBatches()->whereDate('period_start', '2026-11-09')->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertSame(14, $copy->dailyAssignments()->count());
        $ui->set('week', '2026-11-02')->set('correctionReason', 'Cambio de descanso')->call('createCorrection')->assertHasNoErrors();
        $ui->set('cells', [$people[0]->id.'|2026-11-02'])->set('brush', 'rest')->call('applyCells')->assertHasNoErrors()
            ->call('reviewWeek')->assertSet('review.valid', true)->call('publish')->assertHasNoErrors();
        $this->assertSame('superseded', $batch->fresh()->status);
        $this->assertSame($hash, $batch->fresh()->snapshot_sha256);
        $ui->set('copyTarget', '2026-11-23')->call('cloneWeek')->assertHasNoErrors()->assertSet('week', '2026-11-23');
        $this->assertSame(14, $company->scheduleBatches()->whereDate('period_start', '2026-11-23')->firstOrFail()->dailyAssignments()->count());
    }

    public function test_direct_publication_requires_review_and_late_joiners_only_get_effective_days(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class)
            ->call('publish')->assertHasErrors('planner');
        $people[1]->update(['started_at' => '2026-11-04']);
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn, 'manual'));
        $this->assertSame(12, $batch->dailyAssignments()->count());
        $this->assertFalse($batch->dailyAssignments()->where('employment_relationship_id', $people[1]->id)->whereDate('work_date', '2026-11-02')->exists());
    }

    public function test_cycle_blocks_and_future_preparation_do_not_repeat_existing_weeks(): void
    {
        [$company, $center, $user, $people, $turn, $batch] = $this->scenario();
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, $this->input($people, $turn));
        $ui = Livewire::withQueryParams(['centerId' => (string) $center->id, 'week' => '2026-11-02'])->test(Planner::class);
        $ui->set('cycleTurn', (string) $turn->id)->set('cycleDays', 2)->call('addCycleBlock')
            ->set('cycleTurn', 'rest')->call('addCycleBlock')->assertSet('cycle', [(string) $turn->id, (string) $turn->id, 'rest', 'rest']);
        $ui->set('futureWeeks', 2)->call('prepareFutureWeeks')->assertHasNoErrors()->assertSet('week', '2026-11-16');
        $this->assertSame(3, $company->scheduleBatches()->count());
        $ui->set('week', '2026-11-02')->set('futureWeeks', 2)->call('prepareFutureWeeks')->assertHasNoErrors();
        $this->assertSame(3, $company->scheduleBatches()->count());
    }
}
