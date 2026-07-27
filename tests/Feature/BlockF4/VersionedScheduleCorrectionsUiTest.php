<?php

namespace Tests\Feature\BlockF4;

use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Database\Seeders\VeraTimeCorrectedScheduleScenarioSeeder;
use Database\Seeders\VeraTimePublishedScheduleScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Volt\Volt;
use Tests\TestCase;

class VersionedScheduleCorrectionsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_creates_corrective_draft_from_published_batch(): void
    {
        $this->seedPublishedScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $published = $this->publishedBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->set('filters.status', 'published')
            ->call('selectBatch', $published->id)
            ->assertSee('Crear correccion')
            ->call('openCorrectionPanel')
            ->set('correctionForm.correction_reason', 'Correccion solicitada desde la interfaz.')
            ->call('createCorrection')
            ->assertHasNoErrors()
            ->assertSee('Correccion creada')
            ->assertSee('Correccion de programacion')
            ->assertDontSee('Generar faltantes')
            ->assertSee('Comparar con version anterior');

        $this->assertTrue(ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('previous_batch_id', $published->id)
            ->whereNull('version')
            ->where('status', 'draft')
            ->exists());
    }

    public function test_ui_compares_publishes_and_shows_version_history(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeCorrectedScheduleScenarioSeeder::class]);
        [$company, $rh] = $this->companyAndUser('VTSP-CYCLE', 'rh.cycle.demo@veratime.local');
        $draft = ScheduleBatch::query()->where('company_id', $company->id)->whereNull('version')->where('status', 'draft')->firstOrFail();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->set('filters.status', 'all')
            ->call('selectBatch', $draft->id)
            ->call('compareWithPrevious')
            ->assertSee('Comparacion con version anterior')
            ->assertSee('Modificados')
            ->call('reviewBatch')
            ->assertSee('Listo para publicar')
            ->call('publishBatch')
            ->assertHasErrors(['confirmPublish']);

        Volt::test('scheduling.daily')
            ->set('filters.status', 'all')
            ->call('selectBatch', $draft->id)
            ->call('reviewBatch')
            ->set('confirmPublish', true)
            ->call('publishBatch')
            ->assertHasNoErrors()
            ->assertSee('Correccion publicada')
            ->call('loadVersionHistory')
            ->assertSee('Historial de versiones')
            ->assertSee('Sustituido')
            ->assertSee('Publicado');

        $this->assertSame('published', $draft->fresh()->status);
        $this->assertSame(2, $draft->fresh()->version);
        $this->assertSame('superseded', $draft->previousBatch->fresh()->status);
    }

    public function test_supervisor_cannot_create_or_publish_corrections(): void
    {
        $this->seedPublishedScenarios();
        [$company] = $this->companyAndUser('VTSP-CYCLE', 'rh.cycle.demo@veratime.local');
        [, $supervisor] = $this->companyAndUser('VTSP-CONSTRUCT', 'supervisor.construction.demo@veratime.local');
        $published = $this->publishedBatch($company);

        $this->assertFalse($supervisor->can('createCorrection', $published));
    }

    private function seedPublishedScenarios(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimePublishedScheduleScenarioSeeder::class]);
    }

    private function companyAndUser(string $taxId, string $email): array
    {
        return [
            Company::query()->where('tax_id', $taxId)->firstOrFail(),
            User::query()->where('email', $email)->firstOrFail(),
        ];
    }

    private function publishedBatch(Company $company): ScheduleBatch
    {
        return ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('status', 'published')
            ->whereDate('period_start', '2026-08-03')
            ->whereDate('period_end', '2026-08-16')
            ->orderByDesc('version')
            ->firstOrFail();
    }
}
