<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ShiftTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReactivateShiftTemplateAction
{
    public function __construct(private ValidateShiftTemplateSegmentsAction $validateSegments)
    {
    }

    public function handle(Company $company, ShiftTemplate $template): ShiftTemplate
    {
        if ($company->status !== 'active' || $template->company_id !== $company->id) {
            throw new InvalidArgumentException('La plantilla no pertenece a la empresa activa.');
        }

        return DB::transaction(function () use ($company, $template): ShiftTemplate {
            $lockedTemplate = ShiftTemplate::query()
                ->with('segments')
                ->where('company_id', $company->id)
                ->whereKey($template->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateSegments->handle($lockedTemplate->segments->map->only([
                'segment_type',
                'timing_mode',
                'start_local_time',
                'end_local_time',
                'start_day_offset',
                'end_day_offset',
                'duration_minutes',
                'is_paid',
                'is_required',
                'sort_order',
                'metadata',
            ])->all());

            $lockedTemplate->forceFill(['status' => 'active'])->save();

            return $lockedTemplate->refresh()->load('segments');
        });
    }
}
