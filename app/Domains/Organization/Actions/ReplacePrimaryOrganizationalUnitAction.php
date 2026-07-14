<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplacePrimaryOrganizationalUnitAction
{
    public function __construct(private AssignPrimaryOrganizationalUnitAction $assignAction)
    {
    }

    public function handle(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, array $data): EmploymentUnitAssignment
    {
        [$from, $to] = $this->assignAction->dates($data);
        $this->assignAction->assertValid($company, $relationship, $unit, $from, $to);

        return DB::transaction(function () use ($company, $relationship, $unit, $data, $from, $to): EmploymentUnitAssignment {
            $active = EmploymentUnitAssignment::query()
                ->where('company_id', $company->id)
                ->where('employment_relationship_id', $relationship->id)
                ->where('assignment_type', 'primary')
                ->where('status', 'active')
                ->lockForUpdate()
                ->orderBy('effective_from')
                ->get();

            foreach ($active as $assignment) {
                if ($assignment->effective_from->gte($from)) {
                    throw new InvalidArgumentException('La nueva unidad principal debe iniciar despues de la vigente.');
                }

                if ($assignment->effective_to && $assignment->effective_to->lt($from)) {
                    continue;
                }

                $assignment->forceFill([
                    'effective_to' => $from->subDay()->toDateString(),
                    'status' => 'replaced',
                ])->save();
            }

            $new = $this->assignAction->create($company, $relationship, $unit, 'primary', $data, $from, $to);

            foreach ($active as $assignment) {
                if ($assignment->status === 'replaced') {
                    $assignment->forceFill(['replaced_by_id' => $new->id])->save();
                }
            }

            return $new->refresh();
        });
    }
}