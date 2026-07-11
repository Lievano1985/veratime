<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEvent>
 */
class TimeEventFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();
        $timezone = 'America/Mexico_City';
        $local = CarbonImmutable::parse(fake()->dateTimeBetween('-1 week', 'now'), $timezone);

        return [
            'company_id' => $company,
            'worker_id' => Worker::factory()->for($company),
            'employment_relationship_id' => null,
            'center_id' => null,
            'device_id' => null,
            'event_type' => fake()->randomElement(TimeEvent::EVENT_TYPES),
            'occurred_at_utc' => $local->utc(),
            'occurred_local_date' => $local->toDateString(),
            'occurred_local_time' => $local->format('H:i:s'),
            'timezone' => $timezone,
            'received_at' => now('UTC'),
            'source' => fake()->randomElement(TimeEvent::SOURCES),
            'source_user_id' => null,
            'external_id' => null,
            'idempotency_key' => null,
            'status' => fake()->randomElement(TimeEvent::STATUSES),
            'metadata' => [],
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => [
            'company_id' => $company->id,
            'worker_id' => Worker::factory()->for($company),
        ]);
    }
}