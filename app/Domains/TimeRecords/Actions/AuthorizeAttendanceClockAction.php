<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\AttendanceClock;
use Illuminate\Support\Facades\DB;

final class AuthorizeAttendanceClockAction
{
    public function handle(string $serial): bool
    {
        return AttendanceClock::query()
            ->where('serial', strtoupper($serial))
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))->from('companies')
                    ->whereColumn('companies.id', 'attendance_clocks.company_id')
                    ->where('companies.status', 'active');
            })
            ->whereHas('center', fn ($query) => $query
                ->whereColumn('centers.company_id', 'attendance_clocks.company_id')
                ->where('status', 'active'))
            ->exists();
    }
}
