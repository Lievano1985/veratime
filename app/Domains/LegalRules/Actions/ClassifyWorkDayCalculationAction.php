<?php

namespace App\Domains\LegalRules\Actions;

use App\Models\LegalRuleVersion;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ClassifyWorkDayCalculationAction
{
    private const RULE_DAYTIME_WINDOW = 'daytime_window';
    private const RULE_NIGHT_MIXED_THRESHOLD = 'night_minutes_mixed_threshold';
    private const RULE_DAILY_LIMIT_DIURNAL = 'daily_limit_diurnal';
    private const RULE_DAILY_LIMIT_NOCTURNAL = 'daily_limit_nocturnal';
    private const RULE_DAILY_LIMIT_MIXED = 'daily_limit_mixed';

    public function __construct(
        private readonly ResolveLegalRuleVersionForDateAction $rules,
    ) {}

    public function handle(WorkDayCalculation $calculation): WorkDayCalculation
    {
        $calculation->loadMissing(['workDay.company']);
        $workDay = $calculation->workDay;

        if (! $workDay || $calculation->status !== WorkDayCalculation::STATUS_ACTIVE) {
            return $calculation;
        }

        $resultSnapshot = $calculation->result_snapshot ?? [];
        $issues = $resultSnapshot['issues'] ?? [];
        $workIntervals = $resultSnapshot['work_intervals'] ?? [];

        if ($workIntervals === [] || $issues !== [] || $calculation->total_work_minutes <= 0) {
            return $this->markPending($calculation, 'La jornada requiere revision antes de aplicar clasificacion legal.');
        }

        $workDate = $workDay->work_date;
        $timezone = $workDay->timezone ?: ($workDay->company?->setting?->default_timezone ?: $workDay->company?->timezone ?: config('app.timezone'));
        $resolvedRules = $this->resolvedRules($workDate);
        $daytimeWindow = $resolvedRules[self::RULE_DAYTIME_WINDOW]['value'];
        $nightThreshold = (int) $resolvedRules[self::RULE_NIGHT_MIXED_THRESHOLD]['value']['minutes'];

        $dayMinutes = 0;
        $nightMinutes = 0;

        foreach ($workIntervals as $interval) {
            [$day, $night] = $this->splitDayAndNightMinutes($interval, $timezone, $daytimeWindow);
            $dayMinutes += $day;
            $nightMinutes += $night;
        }

        foreach (($resultSnapshot['break_intervals'] ?? []) as $interval) {
            [$day, $night] = $this->splitDayAndNightMinutes($interval, $timezone, $daytimeWindow);
            $dayMinutes = max(0, $dayMinutes - $day);
            $nightMinutes = max(0, $nightMinutes - $night);
        }

        $classification = $this->classification($dayMinutes, $nightMinutes, $nightThreshold);
        $dailyLimit = $this->dailyLimitFor($classification, $resolvedRules);

        $calculation->forceFill([
            'night_minutes' => $nightMinutes,
            'classification' => $classification,
            'rules_snapshot' => array_replace_recursive($calculation->rules_snapshot ?? [], [
                'legal_engine_applied' => true,
                'legal_classification' => [
                    'schema_version' => 1,
                    'rules' => $this->ruleSnapshots($resolvedRules),
                    'fallback_used' => collect($resolvedRules)->contains(fn (array $rule): bool => $rule['fallback']),
                ],
            ]),
            'result_snapshot' => array_replace_recursive($resultSnapshot, [
                'legal_classification' => [
                    'schema_version' => 1,
                    'classification' => $classification,
                    'day_minutes' => $dayMinutes,
                    'night_minutes' => $nightMinutes,
                    'night_mixed_threshold_minutes' => $nightThreshold,
                    'daily_limit_minutes' => $dailyLimit,
                    'timezone' => $timezone,
                ],
            ]),
            'explanation' => array_replace_recursive($calculation->explanation ?? [], [
                'legal_pending' => false,
                'legal_classification' => "Jornada {$this->classificationName($classification)}: {$dayMinutes} minutos diurnos y {$nightMinutes} minutos nocturnos.",
            ]),
        ])->save();

        return $calculation->refresh();
    }

    private function markPending(WorkDayCalculation $calculation, string $reason): WorkDayCalculation
    {
        $calculation->forceFill([
            'classification' => WorkDayCalculation::CLASSIFICATION_PENDING,
            'explanation' => array_replace_recursive($calculation->explanation ?? [], [
                'legal_pending' => true,
                'legal_classification' => $reason,
            ]),
        ])->save();

        return $calculation->refresh();
    }

    /**
     * @return array<string, array{value: array<string, mixed>, version: ?LegalRuleVersion, fallback: bool}>
     */
    private function resolvedRules(CarbonInterface|string $workDate): array
    {
        return [
            self::RULE_DAYTIME_WINDOW => $this->resolveRule(self::RULE_DAYTIME_WINDOW, $workDate, ['start' => '06:00', 'end' => '20:00']),
            self::RULE_NIGHT_MIXED_THRESHOLD => $this->resolveRule(self::RULE_NIGHT_MIXED_THRESHOLD, $workDate, ['minutes' => 210]),
            self::RULE_DAILY_LIMIT_DIURNAL => $this->resolveRule(self::RULE_DAILY_LIMIT_DIURNAL, $workDate, ['minutes' => 480]),
            self::RULE_DAILY_LIMIT_NOCTURNAL => $this->resolveRule(self::RULE_DAILY_LIMIT_NOCTURNAL, $workDate, ['minutes' => 420]),
            self::RULE_DAILY_LIMIT_MIXED => $this->resolveRule(self::RULE_DAILY_LIMIT_MIXED, $workDate, ['minutes' => 450]),
        ];
    }

    /**
     * @return array{value: array<string, mixed>, version: ?LegalRuleVersion, fallback: bool}
     */
    private function resolveRule(string $code, CarbonInterface|string $workDate, array $fallback): array
    {
        $version = $this->rules->handle($code, $workDate);

        return [
            'value' => $version?->value ?? $fallback,
            'version' => $version,
            'fallback' => $version === null,
        ];
    }

    /**
     * @param array<string, mixed> $interval
     * @param array{start: string, end: string} $daytimeWindow
     * @return array{0: int, 1: int}
     */
    private function splitDayAndNightMinutes(array $interval, string $timezone, array $daytimeWindow): array
    {
        $start = CarbonImmutable::parse((string) $interval['start_utc'], 'UTC')->setTimezone($timezone);
        $end = CarbonImmutable::parse((string) $interval['end_utc'], 'UTC')->setTimezone($timezone);

        $dayMinutes = 0;
        $nightMinutes = 0;
        $cursor = $start;

        while ($cursor->lt($end)) {
            $nextBoundary = $this->nextDaytimeBoundary($cursor, $daytimeWindow);
            $sliceEnd = $nextBoundary->lt($end) ? $nextBoundary : $end;
            $minutes = max(0, (int) $cursor->diffInMinutes($sliceEnd, false));

            if ($this->isDaytime($cursor, $daytimeWindow)) {
                $dayMinutes += $minutes;
            } else {
                $nightMinutes += $minutes;
            }

            $cursor = $sliceEnd;
        }

        return [$dayMinutes, $nightMinutes];
    }

    /**
     * @param array{start: string, end: string} $daytimeWindow
     */
    private function nextDaytimeBoundary(CarbonImmutable $cursor, array $daytimeWindow): CarbonImmutable
    {
        $start = $this->timeOnDate($cursor, $daytimeWindow['start']);
        $end = $this->timeOnDate($cursor, $daytimeWindow['end']);
        $candidates = collect([$start, $end, $start->addDay(), $end->addDay()])
            ->filter(fn (CarbonImmutable $candidate): bool => $candidate->gt($cursor))
            ->sortBy(fn (CarbonImmutable $candidate): int => $candidate->getTimestamp())
            ->values();

        return $candidates->first();
    }

    /**
     * @param array{start: string, end: string} $daytimeWindow
     */
    private function isDaytime(CarbonImmutable $dateTime, array $daytimeWindow): bool
    {
        $start = $this->timeOnDate($dateTime, $daytimeWindow['start']);
        $end = $this->timeOnDate($dateTime, $daytimeWindow['end']);

        return $dateTime->gte($start) && $dateTime->lt($end);
    }

    private function timeOnDate(CarbonImmutable $dateTime, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $dateTime->setTime($hour, $minute);
    }

    private function classification(int $dayMinutes, int $nightMinutes, int $nightThreshold): string
    {
        if ($dayMinutes > 0 && $nightMinutes === 0) {
            return WorkDayCalculation::CLASSIFICATION_DIURNAL;
        }

        if ($nightMinutes > 0 && $dayMinutes === 0) {
            return WorkDayCalculation::CLASSIFICATION_NOCTURNAL;
        }

        if ($nightMinutes >= $nightThreshold) {
            return WorkDayCalculation::CLASSIFICATION_NOCTURNAL;
        }

        return WorkDayCalculation::CLASSIFICATION_MIXED;
    }

    /**
     * @param array<string, array{value: array<string, mixed>, version: ?LegalRuleVersion, fallback: bool}> $rules
     */
    private function dailyLimitFor(string $classification, array $rules): int
    {
        $code = match ($classification) {
            WorkDayCalculation::CLASSIFICATION_NOCTURNAL => self::RULE_DAILY_LIMIT_NOCTURNAL,
            WorkDayCalculation::CLASSIFICATION_MIXED => self::RULE_DAILY_LIMIT_MIXED,
            default => self::RULE_DAILY_LIMIT_DIURNAL,
        };

        return (int) $rules[$code]['value']['minutes'];
    }

    /**
     * @param array<string, array{value: array<string, mixed>, version: ?LegalRuleVersion, fallback: bool}> $rules
     * @return array<string, array<string, mixed>>
     */
    private function ruleSnapshots(array $rules): array
    {
        return collect($rules)->mapWithKeys(function (array $entry, string $code): array {
            $version = $entry['version'];

            return [$code => [
                'legal_rule_id' => $version?->legal_rule_id,
                'legal_rule_version_id' => $version?->id,
                'version' => $version?->version,
                'value' => $entry['value'],
                'source_reference' => $version?->source_reference,
                'fallback' => $entry['fallback'],
            ]];
        })->all();
    }

    private function classificationName(string $classification): string
    {
        return match ($classification) {
            WorkDayCalculation::CLASSIFICATION_DIURNAL => 'diurna',
            WorkDayCalculation::CLASSIFICATION_NOCTURNAL => 'nocturna',
            WorkDayCalculation::CLASSIFICATION_MIXED => 'mixta',
            default => 'pendiente',
        };
    }
}
