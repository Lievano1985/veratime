<?php

use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\InactivateShiftTemplateAction;
use App\Domains\Scheduling\Actions\ReactivateShiftTemplateAction;
use App\Domains\Scheduling\Actions\UpdateShiftTemplateAction;
use App\Domains\Scheduling\Actions\ValidateShiftTemplateSegmentsAction;
use App\Domains\Scheduling\Support\ShiftTemplateTimeline;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\ShiftTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $form = [];
    public array $segments = [];
    public array $filters = [];
    public bool $showFormPanel = false;
    public ?int $editingTemplateId = null;
    public ?int $viewingTemplateId = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->segments = [$this->emptySegment(1)];
        $this->filters = ['search' => '', 'status' => 'active'];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if (str_contains((string) $property, '.segment_type') || str_contains((string) $property, '.timing_mode')) {
            $this->normalizeSegmentControls();
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [ShiftTemplate::class, $company]);

        $this->editingTemplateId = null;
        $this->viewingTemplateId = null;
        $this->form = $this->emptyForm();
        $this->segments = [$this->emptySegment(1)];
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $templateId, CurrentCompany $currentCompany): void
    {
        $template = $this->authorizedTemplate($templateId, $currentCompany, true);

        $this->editingTemplateId = $template->id;
        $this->viewingTemplateId = null;
        $this->form = [
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description ?? '',
            'status' => $template->status,
        ];
        $this->segments = $template->segments->map(fn ($segment) => [
            'segment_type' => $segment->segment_type,
            'timing_mode' => $segment->timing_mode,
            'start_local_time' => $segment->start_local_time ? substr((string) $segment->start_local_time, 0, 5) : '',
            'end_local_time' => $segment->end_local_time ? substr((string) $segment->end_local_time, 0, 5) : '',
            'start_day_offset' => (string) $segment->start_day_offset,
            'end_day_offset' => (string) $segment->end_day_offset,
            'duration_minutes' => $segment->duration_minutes ? (string) $segment->duration_minutes : '',
            'is_paid' => (bool) $segment->is_paid,
            'is_required' => (bool) $segment->is_required,
            'sort_order' => (string) $segment->sort_order,
        ])->values()->all();
        $this->showFormPanel = true;
    }

    public function showDetail(int $templateId, CurrentCompany $currentCompany): void
    {
        $template = $this->authorizedTemplate($templateId, $currentCompany, false);

        $this->viewingTemplateId = $template->id;
    }

    public function closeDetail(): void
    {
        $this->viewingTemplateId = null;
    }

    public function addSegment(): void
    {
        $this->segments[] = $this->emptySegment(count($this->segments) + 1);
    }

    public function removeSegment(int $index): void
    {
        if (count($this->segments) <= 1) {
            return;
        }

        unset($this->segments[$index]);
        $this->segments = array_values($this->segments);
        $this->renumberSegments();
    }

    public function moveSegment(int $index, string $direction): void
    {
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($this->segments[$index], $this->segments[$target])) {
            return;
        }

        [$this->segments[$index], $this->segments[$target]] = [$this->segments[$target], $this->segments[$index]];
        $this->renumberSegments();
    }

    public function save(CurrentCompany $currentCompany, CreateShiftTemplateAction $createAction, UpdateShiftTemplateAction $updateAction): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $template = $this->editingTemplateId ? $this->authorizedTemplate($this->editingTemplateId, $currentCompany, true) : null;

        $template ? Gate::authorize('update', $template) : Gate::authorize('create', [ShiftTemplate::class, $company]);

        $validated = $this->validate([
            'form.code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{1,49}$/',
                Rule::unique('shift_templates', 'code')->where('company_id', $company->id)->ignore($template?->id),
            ],
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
            'segments' => ['required', 'array', 'min:1'],
            'segments.*.segment_type' => ['required', Rule::in(['work', 'break'])],
            'segments.*.timing_mode' => ['required', Rule::in(['fixed', 'duration'])],
            'segments.*.start_local_time' => ['nullable', 'date_format:H:i'],
            'segments.*.end_local_time' => ['nullable', 'date_format:H:i'],
            'segments.*.start_day_offset' => ['required', Rule::in(['0', '1', 0, 1])],
            'segments.*.end_day_offset' => ['required', Rule::in(['0', '1', 0, 1])],
            'segments.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'segments.*.is_paid' => ['boolean'],
            'segments.*.is_required' => ['boolean'],
            'segments.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $template
                ? $updateAction->handle($company, $template, $validated['form'], $this->preparedSegments())
                : $createAction->handle($company, $validated['form'], $this->preparedSegments());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['segments' => $exception->getMessage()]);
        }

        $this->showFormPanel = false;
        $this->editingTemplateId = null;
        $this->form = $this->emptyForm();
        $this->segments = [$this->emptySegment(1)];
        $this->resetPage();

        Session::flash('status', $template ? 'Plantilla de turno actualizada.' : 'Plantilla de turno creada.');
    }

    public function inactivate(int $templateId, CurrentCompany $currentCompany, InactivateShiftTemplateAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $template = $this->authorizedTemplate($templateId, $currentCompany, true);

        Gate::authorize('inactivate', $template);
        $action->handle($company, $template);

        Session::flash('status', 'Plantilla inactivada.');
    }

    public function reactivate(int $templateId, CurrentCompany $currentCompany, ReactivateShiftTemplateAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $template = $this->authorizedTemplate($templateId, $currentCompany, true);

        Gate::authorize('reactivate', $template);
        $action->handle($company, $template);

        Session::flash('status', 'Plantilla reactivada.');
    }

    public function closeFormPanel(): void
    {
        $this->showFormPanel = false;
        $this->editingTemplateId = null;
        $this->resetValidation();
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [ShiftTemplate::class, $company]);

        $search = trim((string) ($this->filters['search'] ?? ''));
        $status = trim((string) ($this->filters['status'] ?? 'active'));
        $canManage = Gate::allows('create', [ShiftTemplate::class, $company]);

        $viewingTemplate = $this->viewingTemplateId
            ? $company->shiftTemplates()->with('segments')->whereKey($this->viewingTemplateId)->first()
            : null;

        if ($viewingTemplate && ! Gate::allows('view', $viewingTemplate)) {
            $viewingTemplate = null;
            $this->viewingTemplateId = null;
        }

        return [
            'shiftTemplates' => $company->shiftTemplates()
                ->with('segments')
                ->when(! $canManage, fn ($query) => $query->where('status', 'active'))
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(fn ($searchQuery) => $searchQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"));
                })
                ->orderBy('name')
                ->paginate(10),
            'canManageShiftTemplates' => $canManage,
            'preview' => $this->preview(),
            'viewingTemplate' => $viewingTemplate,
        ];
    }

    private function authorizedTemplate(int $templateId, CurrentCompany $currentCompany, bool $forUpdate): ShiftTemplate
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $template = $company->shiftTemplates()->with('segments')->whereKey($templateId)->firstOrFail();

        Gate::authorize($forUpdate ? 'update' : 'view', $template);

        return $template;
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function normalizeSegmentControls(): void
    {
        foreach ($this->segments as $index => $segment) {
            if (($segment['segment_type'] ?? 'work') === 'work') {
                $this->segments[$index]['timing_mode'] = 'fixed';
                $this->segments[$index]['is_paid'] = true;
            }

            if (($segment['timing_mode'] ?? 'fixed') === 'duration') {
                $this->segments[$index]['start_local_time'] = '';
                $this->segments[$index]['end_local_time'] = '';
                $this->segments[$index]['start_day_offset'] = '0';
                $this->segments[$index]['end_day_offset'] = '0';
            }
        }
    }

    private function preparedSegments(): array
    {
        $this->normalizeSegmentControls();

        return collect($this->segments)->map(fn (array $segment) => [
            'segment_type' => $segment['segment_type'],
            'timing_mode' => $segment['timing_mode'],
            'start_local_time' => $segment['start_local_time'] ?: null,
            'end_local_time' => $segment['end_local_time'] ?: null,
            'start_day_offset' => (int) $segment['start_day_offset'],
            'end_day_offset' => (int) $segment['end_day_offset'],
            'duration_minutes' => filled($segment['duration_minutes'] ?? null) ? (int) $segment['duration_minutes'] : null,
            'is_paid' => (bool) ($segment['is_paid'] ?? false),
            'is_required' => (bool) ($segment['is_required'] ?? true),
            'sort_order' => (int) $segment['sort_order'],
            'metadata' => [],
        ])->all();
    }

    private function preview(): array
    {
        try {
            $segments = app(ValidateShiftTemplateSegmentsAction::class)->handle($this->preparedSegments());
        } catch (\Throwable) {
            return ['valid' => false, 'lines' => [], 'metrics' => null];
        }

        return [
            'valid' => true,
            'lines' => collect($segments)->map(fn (array $segment) => $this->previewLine($segment))->all(),
            'metrics' => ShiftTemplateTimeline::fromSegments($segments)->metrics(),
        ];
    }

    private function previewLine(array $segment): string
    {
        $label = $segment['segment_type'] === 'work' ? 'Trabajo' : ($segment['is_paid'] ? 'Descanso pagado' : 'Descanso no pagado');

        if ($segment['timing_mode'] === 'duration') {
            return "{$segment['duration_minutes']} min - {$label}";
        }

        $endSuffix = (int) $segment['end_day_offset'] === 1 ? ' (+1 dia)' : '';

        return substr((string) $segment['start_local_time'], 0, 5).'–'.substr((string) $segment['end_local_time'], 0, 5).$endSuffix.' '.$label;
    }

    private function formatMinutes(?int $minutes): string
    {
        $minutes = (int) $minutes;
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours > 0 ? trim("{$hours} h ".($rest > 0 ? "{$rest} min" : '')) : "{$rest} min";
    }

    private function renumberSegments(): void
    {
        foreach ($this->segments as $index => $segment) {
            $this->segments[$index]['sort_order'] = (string) ($index + 1);
        }
    }

    private function emptyForm(): array
    {
        return ['code' => '', 'name' => '', 'description' => '', 'status' => 'active'];
    }

    private function emptySegment(int $order): array
    {
        return [
            'segment_type' => 'work',
            'timing_mode' => 'fixed',
            'start_local_time' => '08:00',
            'end_local_time' => '16:00',
            'start_day_offset' => '0',
            'end_day_offset' => '0',
            'duration_minutes' => '',
            'is_paid' => true,
            'is_required' => true,
            'sort_order' => (string) $order,
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Catálogo de turnos</flux:heading>
            <flux:subheading>Plantillas reutilizables de un día. No asignan personas ni generan calendarios todavía.</flux:subheading>
        </div>

        @if ($canManageShiftTemplates)
            <flux:button wire:click="openCreatePanel" icon="plus">Nueva plantilla</flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
        <flux:input label="Buscar" placeholder="Código o nombre" wire:model.live.debounce.350ms="filters.search" />
        <flux:select label="Estado" wire:model.live="filters.status">
            <flux:select.option value="active">Activas</flux:select.option>
            @if ($canManageShiftTemplates)
                <flux:select.option value="inactive">Inactivas</flux:select.option>
                <flux:select.option value="all">Todas</flux:select.option>
            @endif
        </flux:select>
    </div>

    <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
        <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Plantilla</th>
                    <th class="px-4 py-3">Segmentos</th>
                    <th class="px-4 py-3">Trabajo efectivo</th>
                    <th class="px-4 py-3">Duración total</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($shiftTemplates as $template)
                    @php($metrics = $template->metrics())
                    <tr>
                        <td class="px-4 py-3">
                            <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $template->code }} - {{ $template->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $template->description ?: 'Sin descripción' }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $template->segments->count() }}</td>
                        <td class="px-4 py-3">
                            <span class="block">{{ $this->formatMinutes($metrics['effective_work_minutes']) }}</span>
                            <span class="text-xs text-zinc-500">Bruto: {{ $this->formatMinutes($metrics['gross_work_minutes']) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            {{ $this->formatMinutes($metrics['total_span_minutes']) }}
                            @if ($metrics['crosses_midnight'])
                                <span class="ml-1 rounded-full bg-sky-50 px-2 py-1 text-xs text-sky-700 dark:bg-sky-950 dark:text-sky-200">+1 día</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $template->status === 'active' ? 'Activa' : 'Inactiva' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="ghost" wire:click="showDetail({{ $template->id }})">Ver</flux:button>
                                @if ($canManageShiftTemplates)
                                    <flux:button size="xs" variant="ghost" wire:click="loadEditForm({{ $template->id }})">Editar</flux:button>
                                    @if ($template->status === 'active')
                                        <flux:button size="xs" variant="danger" wire:click="inactivate({{ $template->id }})" wire:confirm="¿Inactivar esta plantilla?">Inactivar</flux:button>
                                    @else
                                        <flux:button size="xs" variant="primary" wire:click="reactivate({{ $template->id }})">Reactivar</flux:button>
                                    @endif
                                @else
                                    <span class="text-xs text-zinc-500">Solo consulta</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No hay plantillas con los filtros actuales.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $shiftTemplates->links() }}

    @if ($viewingTemplate)
        @php($detailMetrics = $viewingTemplate->metrics())
        <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <flux:heading>{{ $viewingTemplate->code }} - {{ $viewingTemplate->name }}</flux:heading>
                    <flux:subheading>Detalle de segmentos de la plantilla.</flux:subheading>
                </div>
                <flux:button size="sm" variant="ghost" wire:click="closeDetail">Cerrar</flux:button>
            </div>

            <div class="space-y-2 text-sm">
                @foreach ($viewingTemplate->segments as $segment)
                    <p>{{ $this->previewLine($segment->toArray()) }}</p>
                @endforeach
            </div>

            <div class="mt-4 grid gap-2 text-sm md:grid-cols-2">
                <p>Trabajo programado bruto: {{ $this->formatMinutes($detailMetrics['gross_work_minutes']) }}</p>
                <p>Descanso fijo pagado: {{ $this->formatMinutes($detailMetrics['fixed_paid_break_minutes']) }}</p>
                <p>Descanso fijo no pagado: {{ $this->formatMinutes($detailMetrics['fixed_unpaid_break_minutes']) }}</p>
                <p>Descanso por duración pagado: {{ $this->formatMinutes($detailMetrics['duration_paid_break_minutes']) }}</p>
                <p>Descanso por duración no pagado: {{ $this->formatMinutes($detailMetrics['duration_unpaid_break_minutes']) }}</p>
                <p>Trabajo efectivo programado: {{ $this->formatMinutes($detailMetrics['effective_work_minutes']) }}</p>
                <p>Duración total: {{ $this->formatMinutes($detailMetrics['total_span_minutes']) }}</p>
            </div>
        </section>
    @endif

    @if ($canManageShiftTemplates)
        <x-side-panel wire:model="showFormPanel" maxWidth="max-w-5xl" title="{{ $editingTemplateId ? 'Editar plantilla de turno' : 'Nueva plantilla de turno' }}" subheading="Las horas son locales de reloj; la zona horaria se resolverá al publicar días.">
            <form wire:submit="save" class="space-y-6 p-6">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:input label="Código" wire:model="form.code" required />
                <flux:input label="Nombre" wire:model="form.name" required />
                <flux:select label="Estado" wire:model="form.status">
                    <flux:select.option value="active">Activa</flux:select.option>
                    <flux:select.option value="inactive">Inactiva</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea label="Descripción" wire:model="form.description" rows="2" />

            @error('segments')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading>Segmentos diarios</flux:heading>
                    <flux:button type="button" size="sm" variant="primary" wire:click="addSegment">Agregar segmento</flux:button>
                </div>

                @foreach ($segments as $index => $segment)
                    <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-medium">Segmento {{ $index + 1 }}</p>
                            <div class="flex gap-2">
                                <flux:button type="button" size="xs" variant="ghost" wire:click="moveSegment({{ $index }}, 'up')">Subir</flux:button>
                                <flux:button type="button" size="xs" variant="ghost" wire:click="moveSegment({{ $index }}, 'down')">Bajar</flux:button>
                                <flux:button type="button" size="xs" variant="danger" wire:click="removeSegment({{ $index }})">Quitar</flux:button>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-4">
                            <flux:select label="Tipo" wire:model.live="segments.{{ $index }}.segment_type">
                                <flux:select.option value="work">Trabajo</flux:select.option>
                                <flux:select.option value="break">Descanso</flux:select.option>
                            </flux:select>
                            <flux:select label="Modalidad" wire:model.live="segments.{{ $index }}.timing_mode" :disabled="$segment['segment_type'] === 'work'">
                                <flux:select.option value="fixed">Horario fijo</flux:select.option>
                                <flux:select.option value="duration">Duración</flux:select.option>
                            </flux:select>
                            <flux:input label="Orden" type="number" min="1" wire:model.live.debounce.250ms="segments.{{ $index }}.sort_order" />
                            <div class="flex items-end gap-4">
                                <flux:checkbox label="Pagado" wire:model.live="segments.{{ $index }}.is_paid" :disabled="$segment['segment_type'] === 'work'" />
                                <flux:checkbox label="Obligatorio" wire:model.live="segments.{{ $index }}.is_required" />
                            </div>
                        </div>

                        @if (($segment['timing_mode'] ?? 'fixed') === 'fixed')
                            <div class="mt-4 grid gap-4 md:grid-cols-4">
                                <flux:input label="Hora inicial" type="time" wire:model.live.debounce.250ms="segments.{{ $index }}.start_local_time" />
                                <flux:select label="Día inicial" wire:model.live="segments.{{ $index }}.start_day_offset">
                                    <flux:select.option value="0">Mismo día</flux:select.option>
                                    <flux:select.option value="1">Día siguiente</flux:select.option>
                                </flux:select>
                                <flux:input label="Hora final" type="time" wire:model.live.debounce.250ms="segments.{{ $index }}.end_local_time" />
                                <flux:select label="Día final" wire:model.live="segments.{{ $index }}.end_day_offset">
                                    <flux:select.option value="0">Mismo día</flux:select.option>
                                    <flux:select.option value="1">Día siguiente</flux:select.option>
                                </flux:select>
                            </div>
                        @else
                            <div class="mt-4 max-w-xs">
                                <flux:input label="Duración en minutos" type="number" min="1" wire:model.live.debounce.250ms="segments.{{ $index }}.duration_minutes" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading>Vista previa</flux:heading>
                @if ($preview['valid'])
                    <div class="mt-3 space-y-1 text-sm">
                        @foreach ($preview['lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                    <div class="mt-4 grid gap-2 text-sm md:grid-cols-2">
                        <p>Trabajo programado bruto: {{ $this->formatMinutes($preview['metrics']['gross_work_minutes']) }}</p>
                        <p>Descanso fijo pagado: {{ $this->formatMinutes($preview['metrics']['fixed_paid_break_minutes']) }}</p>
                        <p>Descanso fijo no pagado: {{ $this->formatMinutes($preview['metrics']['fixed_unpaid_break_minutes']) }}</p>
                        <p>Descanso por duración pagado: {{ $this->formatMinutes($preview['metrics']['duration_paid_break_minutes']) }}</p>
                        <p>Descanso por duración no pagado: {{ $this->formatMinutes($preview['metrics']['duration_unpaid_break_minutes']) }}</p>
                        <p>Trabajo efectivo programado: {{ $this->formatMinutes($preview['metrics']['effective_work_minutes']) }}</p>
                        <p>Duración total: {{ $this->formatMinutes($preview['metrics']['total_span_minutes']) }}</p>
                        <p>Cruza medianoche: {{ $preview['metrics']['crosses_midnight'] ? 'Sí' : 'No' }}</p>
                    </div>
                @else
                    <p class="mt-3 text-sm text-zinc-500">Completa segmentos válidos para ver el resumen.</p>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeFormPanel">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
            </form>
        </x-side-panel>
    @endif
</section>
