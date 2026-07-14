<?php

namespace App\Domains\Organization\Support;

use App\Models\OrganizationalUnit;
use Illuminate\Support\Collection;

class OrganizationalUnitTree
{
    /**
     * @return list<int>
     */
    public function descendantIds(OrganizationalUnit $unit, bool $includeSelf = true): array
    {
        $ids = $includeSelf ? [$unit->id] : [];
        $frontier = [$unit->id];

        while ($frontier !== []) {
            $children = OrganizationalUnit::query()
                ->where('company_id', $unit->company_id)
                ->where('center_id', $unit->center_id)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            $children = array_values(array_diff($children, $ids));
            if ($children === []) {
                break;
            }

            $ids = array_values(array_unique([...$ids, ...$children]));
            $frontier = $children;
        }

        return $ids;
    }

    public function contains(OrganizationalUnit $scopeUnit, OrganizationalUnit $candidate): bool
    {
        if ($scopeUnit->company_id !== $candidate->company_id || $scopeUnit->center_id !== $candidate->center_id) {
            return false;
        }

        return in_array($candidate->id, $this->descendantIds($scopeUnit), true);
    }
}