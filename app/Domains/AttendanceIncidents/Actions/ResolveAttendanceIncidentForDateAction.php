<?php

namespace App\Domains\AttendanceIncidents\Actions;

use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use Carbon\CarbonImmutable;

class ResolveAttendanceIncidentForDateAction
{
    public function handle(Company $company, EmploymentRelationship $relationship, string|\DateTimeInterface $date): ?AttendanceIncident
    {
        $workDate = CarbonImmutable::parse($date)->toDateString();

        return AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $relationship->worker_id)
            ->where(function ($query) use ($relationship): void {
                $query->whereNull('employment_relationship_id')
                    ->orWhere('employment_relationship_id', $relationship->id);
            })
            ->where('status', AttendanceIncident::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $workDate)
            ->whereDate('end_date', '>=', $workDate)
            ->orderByDesc('employment_relationship_id')
            ->orderByDesc('created_at')
            ->first();
    }
}
