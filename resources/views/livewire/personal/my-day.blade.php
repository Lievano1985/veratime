<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Workers\Actions\ResolvePersonalWorkerAction;
use App\Models\TimeEvent;
use App\Models\WorkDay;
use Livewire\Volt\Component;

new class extends Component {
    public function with(CurrentCompany $currentCompany, ResolvePersonalWorkerAction $resolve): array
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        $worker = $resolve->handle(auth()->user(), $company);
        return [
            'worker' => $worker,
            'events' => TimeEvent::query()->where('company_id', $company->id)->where('worker_id', $worker->id)->latest('occurred_at_utc')->limit(10)->get(),
            'workDays' => WorkDay::query()->where('company_id', $company->id)->where('worker_id', $worker->id)->latest('work_date')->limit(7)->get(),
        ];
    }
}; ?>
<section class="w-full space-y-6 p-6">
    <div><flux:heading size="xl">Mi jornada</flux:heading><flux:subheading>{{ $worker->full_name }}</flux:subheading></div>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border p-5"><flux:heading>Mis eventos recientes</flux:heading><div class="mt-4 space-y-2">@forelse($events as $event)<p class="text-sm">{{ $event->occurred_at_utc?->setTimezone($event->timezone)->format('d/m H:i') }} · {{ $event->event_type }}</p>@empty<p class="text-sm text-surface-muted">Sin eventos.</p>@endforelse</div></section>
        <section class="rounded-lg border p-5"><flux:heading>Mis jornadas</flux:heading><div class="mt-4 space-y-2">@forelse($workDays as $day)<p class="text-sm">{{ $day->work_date?->format('d/m/Y') }} · {{ $day->status }}</p>@empty<p class="text-sm text-surface-muted">Sin jornadas.</p>@endforelse</div></section>
    </div>
</section>
