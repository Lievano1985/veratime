<?php

namespace App\Domains\WorkDays\Jobs;

use App\Domains\WorkDays\Actions\ProcessSingleWorkDayAction;
use App\Models\TimeEvent;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateWorkDayFromTimeEventJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $timeEventId,
        public readonly string $trigger = 'time_event',
    ) {
        $this->onQueue('work-days');
    }

    public function uniqueId(): string
    {
        return 'time_event:'.$this->timeEventId.':'.$this->trigger;
    }

    public function handle(ProcessSingleWorkDayAction $processSingleWorkDay): void
    {
        $event = TimeEvent::query()
            ->with(['company', 'employmentRelationship.center'])
            ->find($this->timeEventId);

        if (! $event || ! $event->company || ! $event->employmentRelationship) {
            return;
        }

        if ($event->company->status !== 'active') {
            return;
        }

        foreach ($this->affectedDates($event) as $date) {
            $processSingleWorkDay->handle(
                $event->company,
                $event->employmentRelationship,
                $date,
                generatedByType: WorkDayCalculation::GENERATED_BY_JOB,
                reason: $this->reason($event),
                mode: 'event_job',
            );
        }
    }

    /**
     * @return list<string>
     */
    private function affectedDates(TimeEvent $event): array
    {
        $date = $event->occurred_local_date?->toDateString();

        if (! $date) {
            return [];
        }

        $dates = [$date];

        if ($event->event_type !== 'clock_in') {
            $dates[] = CarbonImmutable::parse($date)->subDay()->toDateString();
        }

        return array_values(array_unique($dates));
    }

    private function reason(TimeEvent $event): string
    {
        return match ($this->trigger) {
            'manual_approval' => 'Recalculo por aprobacion de captura manual.',
            'void' => 'Recalculo por anulacion de evento.',
            default => "Recalculo automatico por evento {$event->event_type}.",
        };
    }
}
