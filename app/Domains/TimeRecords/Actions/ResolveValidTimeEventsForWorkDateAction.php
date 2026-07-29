<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ResolveValidTimeEventsForWorkDateAction
{
    /**
     * @return Collection<int, TimeEvent>
     */
    public function handle(Company $company, EmploymentRelationship $relationship, string|CarbonInterface $workDate): Collection
    {
        $this->assertRelationshipBelongsToCompany($company, $relationship);

        $date = $workDate instanceof CarbonInterface
            ? $workDate->toDateString()
            : (string) $workDate;

        return TimeEvent::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $relationship->worker_id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('occurred_local_date', $date)
            ->where('status', 'valid')
            ->whereNull('voided_at')
            ->orderBy('occurred_at_utc')
            ->orderBy('received_at')
            ->orderByRaw("case event_type when 'clock_in' then 1 when 'break_start' then 2 when 'break_end' then 3 when 'clock_out' then 4 else 9 end")
            ->orderBy('source')
            ->orderBy('external_id')
            ->orderBy('idempotency_key')
            ->get();
    }

    private function assertRelationshipBelongsToCompany(Company $company, EmploymentRelationship $relationship): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('La resolucion de eventos requiere una empresa activa.');
        }

        if ($relationship->company_id !== $company->id) {
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la empresa activa.');
        }
    }
}
