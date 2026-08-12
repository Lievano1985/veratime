<?php

namespace Database\Factories;

use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceIncident>
 */
class AttendanceIncidentFactory extends Factory
{
    protected $model = AttendanceIncident::class;

    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'worker_id' => Worker::factory()->for($company),
            'employment_relationship_id' => EmploymentRelationship::factory()->for($company),
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'incident_type' => AttendanceIncident::TYPE_VACATION,
            'payment_status' => AttendanceIncident::PAYMENT_PAID,
            'status' => AttendanceIncident::STATUS_APPROVED,
            'reference' => 'DEMO-REF',
            'notes' => 'Registro demo de incidencia operativa.',
            'created_by' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'metadata' => [
                'schema_version' => 1,
                'scope' => 'factory',
            ],
        ];
    }
}
