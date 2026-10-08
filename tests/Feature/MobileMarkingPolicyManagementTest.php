<?php

namespace Tests\Feature;

use App\Domains\TimeRecords\Actions\ActivateMobileMarkingPolicyAction;
use App\Domains\TimeRecords\Actions\CreateMobileMarkingPolicyDraftVersionAction;
use App\Domains\TimeRecords\Actions\DeactivateMobileMarkingPolicyAction;
use App\Domains\TimeRecords\Actions\SaveMobileMarkingPolicyDraftAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MobileMarkingPolicyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_a_new_policy_preserves_the_previous_version_as_inactive(): void
    {
        $company = Company::factory()->create();
        $center = Center::factory()->for($company)->create();
        $previous = MobileMarkingPolicy::factory()->for($company)->active()->create([
            'center_id' => $center->id,
            'version' => 1,
            'mode' => MobileMarkingPolicy::MODE_FREE,
        ]);

        $draft = app(SaveMobileMarkingPolicyDraftAction::class)->handle($company, [
            'center_id' => $center->id,
            'organizational_unit_id' => null,
            'mode' => MobileMarkingPolicy::MODE_CIRCLE,
            'requires_device_binding' => true,
            'requires_biometric_unlock' => false,
            'center_latitude' => '19.4326000',
            'center_longitude' => '-99.1332000',
            'radius_meters' => 150,
            'max_accuracy_meters' => 30,
            'max_location_age_seconds' => 60,
        ]);

        $this->assertSame(MobileMarkingPolicy::STATUS_DRAFT, $draft->status);
        $this->assertSame(2, $draft->version);

        app(ActivateMobileMarkingPolicyAction::class)->handle($company, $draft);

        $this->assertDatabaseHas('mobile_marking_policies', ['id' => $previous->id, 'status' => MobileMarkingPolicy::STATUS_INACTIVE]);
        $this->assertDatabaseHas('mobile_marking_policies', ['id' => $draft->id, 'status' => MobileMarkingPolicy::STATUS_ACTIVE]);
    }

    public function test_policy_scope_cannot_use_a_center_or_unit_from_another_company(): void
    {
        $company = Company::factory()->create();
        $foreignCenter = Center::factory()->create();
        $foreignUnit = OrganizationalUnit::factory()->forCenter($foreignCenter)->create();

        try {
            app(SaveMobileMarkingPolicyDraftAction::class)->handle($company, [
                'center_id' => $foreignCenter->id,
                'organizational_unit_id' => null,
                'mode' => MobileMarkingPolicy::MODE_FREE,
                'requires_device_binding' => false,
                'requires_biometric_unlock' => false,
            ]);
            $this->fail('La acción debía rechazar el centro de otra empresa.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('policyForm.center_id', $exception->errors());
        }

        try {
            app(SaveMobileMarkingPolicyDraftAction::class)->handle($company, [
                'center_id' => null,
                'organizational_unit_id' => $foreignUnit->id,
                'mode' => MobileMarkingPolicy::MODE_FREE,
                'requires_device_binding' => false,
                'requires_biometric_unlock' => false,
            ]);
            $this->fail('La acción debía rechazar la unidad de otra empresa.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('policyForm.organizational_unit_id', $exception->errors());
        }
    }

    public function test_deactivation_keeps_the_policy_row_for_evidence_history(): void
    {
        $company = Company::factory()->create();
        $policy = MobileMarkingPolicy::factory()->for($company)->active()->create();

        app(DeactivateMobileMarkingPolicyAction::class)->handle($company, $policy);

        $this->assertDatabaseHas('mobile_marking_policies', [
            'id' => $policy->id,
            'company_id' => $company->id,
            'status' => MobileMarkingPolicy::STATUS_INACTIVE,
        ]);
    }

    public function test_biometric_unlock_requirement_needs_a_linked_device_key(): void
    {
        $company = Company::factory()->create();

        try {
            app(SaveMobileMarkingPolicyDraftAction::class)->handle($company, [
                'center_id' => null,
                'organizational_unit_id' => null,
                'mode' => MobileMarkingPolicy::MODE_FREE,
                'requires_device_binding' => false,
                'requires_biometric_unlock' => true,
            ]);
            $this->fail('La política no debe usar desbloqueo biométrico sin clave de dispositivo vinculada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('policyForm.requires_biometric_unlock', $exception->errors());
        }
    }

    public function test_active_policy_can_be_copied_to_an_editable_new_draft_version(): void
    {
        $company = Company::factory()->create();
        $source = MobileMarkingPolicy::factory()->for($company)->active()->create([
            'version' => 2,
            'mode' => MobileMarkingPolicy::MODE_CIRCLE,
            'requires_device_binding' => true,
            'center_latitude' => '17.9971944',
            'center_longitude' => '-92.9328333',
            'radius_meters' => 200,
            'offline_authorization_duration_minutes' => 720,
        ]);

        $draft = app(CreateMobileMarkingPolicyDraftVersionAction::class)->handle($company, $source);

        $this->assertSame(MobileMarkingPolicy::STATUS_DRAFT, $draft->status);
        $this->assertSame(3, $draft->version);
        $this->assertSame(MobileMarkingPolicy::STATUS_ACTIVE, $source->refresh()->status);
        $this->assertSame('17.9971944', $draft->center_latitude);
        $this->assertSame('-92.9328333', $draft->center_longitude);
        $this->assertSame(200, $draft->radius_meters);
        $this->assertSame(720, $draft->offline_authorization_duration_minutes);
    }
}
