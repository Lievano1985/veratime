<?php

namespace App\Domains\Alerts\Actions;

use App\Models\Alert;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ConfirmWeeklyRestReviewAction
{
    public function evidence(Company $company, Alert $alert): array
    {
        if ($alert->company_id !== $company->id || $alert->rule_code !== 'weekly_rest_missing' || ! $alert->workDay) {
            throw new InvalidArgumentException('El aviso no corresponde a una revisión semanal de esta empresa.');
        }
        $start = CarbonImmutable::parse($alert->workDay->work_date)->startOfWeek();
        $days = WorkDay::with('activeCalculation')->where('company_id', $company->id)
            ->where('employment_relationship_id', $alert->workDay->employment_relationship_id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $start->endOfWeek()->toDateString())->get();
        $reviews = Alert::with('workDay')->where('company_id', $company->id)->whereIn('work_day_id', $days->pluck('id'))
            ->whereIn('rule_code', ['scheduled_rest_work', 'mandatory_rest_work', 'sunday_work'])->orderBy('id')->get();
        $pending = $reviews->whereIn('status', Alert::OPEN_STATUSES);
        $reviewed = $reviews->filter(fn ($item) => ! in_array($item->status, Alert::OPEN_STATUSES, true) && $item->resolved_by && ! $item->isClosedByRecalculation());
        $worked = $days->filter(fn ($day) => $day->activeCalculation?->status === 'active'
            && $day->activeCalculation->total_work_minutes > 0
            && ($day->activeCalculation->result_snapshot['issues'] ?? []) === []);

        $withoutWork = $days->filter(fn ($day) => $day->activeCalculation?->status === 'active'
            && $day->activeCalculation->total_work_minutes === 0
            && ($day->activeCalculation->result_snapshot['issues'] ?? []) === []);

        return ['reviews' => $reviewed, 'pending' => $pending,
            'worked_days' => $worked->count(), 'days_without_work' => $withoutWork->count(),
            'pending_days' => max(0, 7 - $worked->count() - $withoutWork->count()),
            'can_confirm' => $worked->count() === 7 && $pending->isEmpty() && $reviewed->isNotEmpty()
                && $start->endOfWeek()->toDateString() < CarbonImmutable::now($company->timezone)->toDateString()];
    }

    public function handle(Company $company, Alert $alert, User $actor): Alert
    {
        Gate::forUser($actor)->authorize('resolve', $alert);

        return DB::transaction(function () use ($company, $alert, $actor) {
            $alert = Alert::where('company_id', $company->id)->lockForUpdate()->findOrFail($alert->id);
            $evidence = $this->evidence($company, $alert);
            if (! $evidence['can_confirm'] || ! in_array($alert->status, Alert::OPEN_STATUSES, true)) {
                throw new InvalidArgumentException('Completa la semana y revisa primero los avisos de descanso trabajado y domingo.');
            }
            $result = app(ResolveAlertAction::class)->handle($company, $alert, $actor, [
                'status' => Alert::STATUS_CLOSED,
                'resolution' => 'Revisado: semana sin descanso. Se consultaron las revisiones diarias vinculadas. Esta confirmación no acredita descanso disfrutado ni cumplimiento.',
            ]);
            $metadata = $result->metadata ?? [];
            $metadata['resolution']['daily_reviews'] = $evidence['reviews']->map(fn ($review) => [
                'alert_id' => $review->id, 'date' => $review->workDay->work_date->toDateString(),
                'rule' => $review->rule_code, 'status' => $review->status, 'resolution' => $review->resolution,
                'resolved_by' => $review->resolved_by, 'resolved_at' => $review->resolved_at?->toIso8601String(),
            ])->all();
            $result->update(['metadata' => $metadata]);

            return $result;
        });
    }
}
