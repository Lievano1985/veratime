<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\LegalRules\Actions\ResolveLegalParameterForDateAction;
use App\Models\Company;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use InvalidArgumentException;

class ResolveOvertimeReviewMarginAction
{
    public function __construct(private readonly ResolveLegalParameterForDateAction $parameters) {}

    /** @return array{minutes: int, within_margin: bool, overtime_minutes: int, parameter_id: ?int} */
    public function handle(Company $company, WorkDay $day): array
    {
        if ($day->company_id !== $company->id) {
            throw new InvalidArgumentException('La jornada debe pertenecer a la empresa activa.');
        }

        $parameter = $this->parameters->handle($company, 'overtime_review_margin_minutes', $day->work_date);
        $margin = max(0, min(30, (int) ($parameter?->value['minutes'] ?? 0)));
        $calculation = $day->activeCalculation;
        $extra = (int) $calculation?->overtime_minutes;
        $dailyLimit = (int) data_get($calculation?->result_snapshot, 'ordinary_overtime.daily_limit_minutes', 0);
        $within = $margin > 0 && $extra > 0 && $extra <= $margin
            && $calculation?->status === WorkDayCalculation::STATUS_ACTIVE
            && $day->status !== WorkDay::STATUS_UNDER_REVIEW
            && ($calculation->result_snapshot['issues'] ?? []) === []
            && ! data_get($calculation->explanation, 'legal_pending', false)
            && $dailyLimit > 0 && $calculation->total_work_minutes - $dailyLimit === $extra
            && $calculation->ordinary_minutes + $extra === $calculation->total_work_minutes;

        return ['minutes' => $margin, 'within_margin' => $within, 'overtime_minutes' => $extra, 'parameter_id' => $parameter?->id];
    }
}
