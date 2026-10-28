<?php

use App\Domains\ExportLayouts\Actions\BuildLayoutMatrixAction;
use App\Domains\ExportLayouts\Actions\BuildPeriodLayoutAction;
use App\Domains\ExportLayouts\Actions\DownloadLayoutMatrixAction;
use App\Domains\ExportLayouts\Actions\SaveExportLayoutAction;
use App\Domains\ExportLayouts\Actions\SetDefaultExportLayoutAction;
use App\Domains\ExportLayouts\Support\IncidentCatalog;
use App\Domains\ExportLayouts\Support\LayoutDefinition;
use App\Domains\ExportLayouts\Support\LayoutExample;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\AttendancePeriod;
use App\Models\ExportLayout;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [];
    public string $activeTab = 'design';
    public string $incidentSearch = '';
    public string $selectedPeriodId = '';
    #[Locked] public ?int $editingLayoutId = null;
    #[Locked] public ?int $editingRevision = null;
    #[Locked] public array $periodPreview = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', [ExportLayout::class, $this->company()]);
        $this->form = LayoutDefinition::defaults();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'form.') || $property === 'selectedPeriodId') {
            $this->periodPreview = [];
        }
    }

    public function startExample(): void
    {
        $this->start(true);
    }

    public function startBlank(): void
    {
        $this->start(false);
    }

    private function start(bool $example): void
    {
        Gate::authorize('create', [ExportLayout::class, $this->company()]);
        $this->editingLayoutId = null;
        $this->editingRevision = null;
        $this->form = LayoutDefinition::defaults($example);
        $this->periodPreview = [];
        $this->activeTab = 'design';
        $this->resetValidation();
    }

    public function openLayout(int $id): void
    {
        $layout = $this->layout($id);
        $this->editingLayoutId = $layout->id;
        $this->editingRevision = $layout->revision;
        $this->form = LayoutDefinition::withCurrentCatalog($layout->configuration);
        $this->periodPreview = [];
        $this->resetValidation();
    }

    public function makeDefault(int $id, SetDefaultExportLayoutAction $action): void
    {
        $action->handle(auth()->user(), $this->company(), $this->layout($id));
        session()->flash('layout-status', 'Layout predeterminado para exportar períodos de esta empresa.');
    }

    public function duplicateLayout(int $id): void
    {
        $this->openLayout($id);
        $this->editingLayoutId = null;
        $this->editingRevision = null;
        $this->form['name'] = 'Copia de '.mb_substr($this->form['name'], 0, 100);
    }

    public function setOrientation(string $orientation): void
    {
        if (! in_array($orientation, ['workers_rows', 'dates_rows'], true)) { return; }
        $this->form['orientation'] = $orientation;
        $this->periodPreview = [];
    }

    public function moveRule(int $from, int $to): void
    {
        if (! isset($this->form['rules'][$from], $this->form['rules'][$to]) || $from === $to) { return; }
        $rule = $this->form['rules'][$from];
        array_splice($this->form['rules'], $from, 1);
        array_splice($this->form['rules'], $to, 0, [$rule]);
        $this->periodPreview = [];
    }

    public function saveLayout(SaveExportLayoutAction $action): void
    {
        $layout = $this->editingLayoutId ? $this->layout($this->editingLayoutId) : null;
        $saved = $action->handle(auth()->user(), $this->company(), $this->form, $layout, $this->editingRevision);
        $this->editingLayoutId = $saved->id;
        $this->editingRevision = $saved->revision;
        $this->form = $saved->configuration;
        $this->resetValidation();
        session()->flash('layout-status', 'Layout guardado.');
    }

    public function previewPeriod(BuildPeriodLayoutAction $action): void
    {
        $this->resetValidation();
        $matrix = $action->handle(auth()->user(), $this->company(), $this->period(), $this->form);
        $this->periodPreview = $this->previewSlice($matrix);
        $this->activeTab = 'preview';
    }

    public function downloadPeriod(BuildPeriodLayoutAction $builder, DownloadLayoutMatrixAction $download): mixed
    {
        if (! $this->editingLayoutId) {
            throw ValidationException::withMessages(['export' => 'Guarda el layout antes de descargar un período.']);
        }
        $layout = $this->layout($this->editingLayoutId);
        if (LayoutDefinition::validate($layout->configuration) != LayoutDefinition::validate($this->form)) {
            throw ValidationException::withMessages(['export' => 'Guarda tus cambios antes de descargar.']);
        }
        if ($layout->status !== 'active') {
            throw ValidationException::withMessages(['export' => 'Activa y guarda el layout antes de exportar.']);
        }
        $period = $this->period();
        $matrix = $builder->handle(auth()->user(), $this->company(), $period, $layout->configuration);
        if ($matrix['errors']) {
            $this->periodPreview = $this->previewSlice($matrix);
            $this->activeTab = 'preview';
            throw ValidationException::withMessages(['export' => 'Hay conflictos. Revisa la vista previa antes de descargar.']);
        }
        return $download->handle($layout->configuration, $matrix, 'incidencias-'.Str::slug($layout->name).'-'.$period->period_start->format('Ymd').'-r'.$layout->revision);
    }

    public function downloadExample(BuildLayoutMatrixAction $builder, DownloadLayoutMatrixAction $download): mixed
    {
        Gate::authorize('create', [ExportLayout::class, $this->company()]);
        $definition = LayoutDefinition::validate($this->form);
        $matrix = $builder->handle($definition, LayoutExample::rows(), '2026-10-12', '2026-10-18');
        return $download->handle($definition, $matrix, 'ejemplo-layout-datos-ficticios');
    }

    private function previewSlice(array $matrix): array
    {
        $matrix['rows'] = array_map(fn ($row) => array_slice($row, 0, 16), array_slice($matrix['rows'], 0, 20));
        $matrix['headers'] = array_slice($matrix['headers'], 0, 16);
        $matrix['errors'] = array_slice($matrix['errors'], 0, 100);
        unset($matrix['date_cells']);
        return $matrix;
    }

    private function company(): \App\Models\Company
    {
        return app(CurrentCompany::class)->get() ?? abort(403);
    }

    private function layout(int $id): ExportLayout
    {
        $layout = ExportLayout::where('company_id', $this->company()->id)->findOrFail($id);
        Gate::authorize('update', $layout);
        return $layout;
    }

    private function period(): AttendancePeriod
    {
        if ($this->selectedPeriodId === '') {
            throw ValidationException::withMessages(['selectedPeriodId' => 'Selecciona un período cerrado.']);
        }
        return AttendancePeriod::where('company_id', $this->company()->id)->findOrFail($this->selectedPeriodId);
    }

    public function with(): array
    {
        Gate::authorize('viewAny', [ExportLayout::class, $this->company()]);
        $matrix = $this->periodPreview;
        $previewErrors = [];
        if ($matrix === []) {
            try {
                $matrix = app(BuildLayoutMatrixAction::class)->handle($this->form, LayoutExample::rows(), '2026-10-12', '2026-10-18');
            } catch (ValidationException $exception) {
                $previewErrors = $exception->validator->errors()->all();
            }
        }
        return [
            'layouts' => ExportLayout::where('company_id', $this->company()->id)->orderBy('name')->get(),
            'catalog' => IncidentCatalog::all(), 'matrix' => $matrix, 'previewErrors' => $previewErrors,
        ];
    }
}; ?>

<div class="space-y-6 p-4 sm:p-6" x-data="{ dragAxis: null, dragRule: null }">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-blue">Diseñador de archivos</p>
            <flux:heading size="xl">Layouts de exportación</flux:heading>
            <p class="mt-2 max-w-3xl text-sm text-surface-muted">Define cómo se acomodan los trabajadores, las fechas y las incidencias. Cada layout conserva sus propias claves.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="btn-outline" type="button" wire:click="startBlank">Crear desde cero</button>
            <button class="btn-primary" type="button" wire:click="saveLayout" wire:loading.attr="disabled" wire:target="saveLayout">Guardar layout</button>
        </div>
    </header>

    @if (session('layout-status'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('layout-status') }}</div>@endif
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"><p class="font-semibold">Revisa estos puntos</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="grid items-start gap-6 xl:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="space-y-5">
            <section class="rounded-2xl border border-blue-100 bg-blue-50 p-5">
                <span class="rounded-lg bg-white px-2 py-1 text-xs font-semibold text-brand-blue">Ejemplo editable</span>
                <h2 class="mt-3 font-display font-semibold text-brand-navy">NomiPAQ DYH</h2>
                <p class="mt-2 text-sm leading-relaxed text-surface-muted">Trabajadores en filas y fechas en columnas.</p>
                <button type="button" class="btn-outline btn-sm mt-4 w-full" wire:click="startExample">Usar este ejemplo</button>
            </section>
            <section class="rounded-2xl border border-surface-line bg-white p-4">
                <h2 class="text-sm font-semibold text-brand-navy">Mis layouts <span class="text-surface-muted">({{ $layouts->count() }})</span></h2>
                <div class="mt-3 space-y-2">
                    @forelse ($layouts as $layout)
                        <div wire:key="layout-{{ $layout->id }}" class="rounded-xl border p-3 {{ $editingLayoutId === $layout->id ? 'border-brand-blue bg-blue-50' : 'border-surface-line' }}">
                            <button type="button" class="w-full text-left" wire:click="openLayout({{ $layout->id }})"><span class="block break-words text-sm font-semibold text-brand-navy">{{ $layout->name }}</span><span class="mt-1 block text-xs text-surface-muted">{{ strtoupper($layout->configuration['format']) }} · Rev. {{ $layout->revision }} · {{ $layout->status === 'active' ? 'Activo' : 'Inactivo' }}</span></button>
                            <button type="button" class="mt-2 text-xs font-semibold text-brand-blue" wire:click="duplicateLayout({{ $layout->id }})">Duplicar</button>
                            @if ($layout->is_default)
                                <span class="mt-2 block text-xs font-semibold text-emerald-700">Predeterminado para períodos</span>
                            @elseif ($layout->status === 'active')
                                <button type="button" class="btn-outline btn-sm mt-2" wire:click="makeDefault({{ $layout->id }})" wire:loading.attr="disabled">Usar como predeterminado</button>
                            @endif
                        </div>
                    @empty
                        <p class="py-4 text-sm leading-relaxed text-surface-muted">Guarda tu primer layout o comienza con el ejemplo.</p>
                    @endforelse
                </div>
            </section>
        </aside>

        <main class="min-w-0 space-y-5">
            <section class="rounded-2xl border border-surface-line bg-white p-5">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <flux:input label="Nombre del layout" wire:model.live.debounce.400ms="form.name" placeholder="Mi formato de incidencias" required />
                    <flux:input label="Aplicación destino" wire:model.live.debounce.400ms="form.application" placeholder="Nombre de la aplicación" />
                    <flux:select label="Tipo de archivo" wire:model.live="form.format"><option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV delimitado</option><option value="txt">TXT delimitado</option></flux:select>
                    <flux:select label="Estado del layout" wire:model.live="form.status"><option value="active">Activo</option><option value="inactive">Inactivo</option></flux:select>
                </div>
                <p class="mt-3 text-xs text-surface-muted">{{ $editingLayoutId ? 'Editando revisión '.$editingRevision : 'Nuevo layout sin guardar' }}</p>
            </section>

            <nav aria-label="Configuración del layout" class="flex flex-wrap gap-2 rounded-2xl border border-surface-line bg-white p-2">
                @foreach (['design' => ['01', 'Distribución'], 'incidents' => ['02', 'Incidencias a mostrar'], 'codes' => ['03', 'Nomenclaturas'], 'preview' => ['04', 'Vista previa']] as $tab => [$number, $label])
                    <button type="button" wire:click="$set('activeTab', '{{ $tab }}')" aria-pressed="{{ $activeTab === $tab ? 'true' : 'false' }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium {{ $activeTab === $tab ? 'bg-blue-50 text-brand-blue' : 'text-surface-muted hover:bg-surface-bg' }}"><span aria-hidden="true" class="rounded-lg bg-surface-bg px-2 py-1 text-xs">{{ $number }}</span>{{ $label }}</button>
                @endforeach
            </nav>

            @if ($activeTab === 'design')
                <section class="space-y-5 rounded-2xl border border-surface-line bg-white p-5 sm:p-6">
                    <div><h2 class="font-display font-semibold text-brand-navy">Acomoda tu reporte</h2><p class="mt-1 text-sm text-surface-muted">Arrastra Trabajadores o Fechas a Filas o Columnas.</p></div>
                    <div class="flex flex-wrap gap-3">
                        @foreach (['workers' => 'Trabajadores', 'dates' => 'Fechas'] as $axis => $label)
                            <span draggable="true" x-on:dragstart="dragAxis = '{{ $axis }}'; $event.dataTransfer.setData('text/plain', '{{ $axis }}')" x-on:dragend="dragAxis = null" class="cursor-grab rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-brand-blue"><span aria-hidden="true">⠿</span> {{ $label }}</span>
                        @endforeach
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach (['rows' => 'Filas ↓', 'columns' => 'Columnas →'] as $axis => $label)
                            <div x-on:dragover.prevent x-on:drop.prevent="if (dragAxis) { $wire.setOrientation((dragAxis === 'workers') === ('{{ $axis }}' === 'rows') ? 'workers_rows' : 'dates_rows'); dragAxis = null }" class="rounded-2xl border-2 border-dashed border-blue-200 bg-surface-bg p-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-surface-muted">{{ $label }}</p>
                                <p class="mt-3 font-display text-lg font-semibold text-brand-navy">{{ ($form['orientation'] === 'workers_rows') === ($axis === 'rows') ? 'Trabajadores' : 'Fechas' }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="grid gap-4 border-t border-surface-line pt-5 sm:grid-cols-2">
                        <flux:select label="Identificar trabajadores con" wire:model.live="form.identifier"><option value="code">Código de empleado</option><option value="name">Nombre completo</option><option value="code_name">Código y nombre</option></flux:select>
                        <flux:select label="Formato de fecha" wire:model.live="form.date_format"><option value="d/m/Y">Día/mes/año</option><option value="Y-m-d">Año-mes-día</option><option value="d/m">Día/mes</option></flux:select>
                        <flux:select label="Contenido de cada celda" wire:model.live="form.cell_format"><option value="quantity_code">Cantidad + código · 2.5HE2</option><option value="code_quantity">Código + cantidad · HE22.5</option><option value="code_only">Solo código · HE2</option></flux:select>
                        <flux:select label="Máximo de decimales" wire:model.live="form.precision">@foreach ([0, 1, 2, 3, 4] as $precision)<option value="{{ $precision }}">{{ $precision }}</option>@endforeach</flux:select>
                        @if ($form['format'] !== 'xlsx')<flux:select label="Separador" wire:model.live="form.delimiter"><option value="tab">Tabulador</option><option value=",">Coma</option><option value=";">Punto y coma</option><option value="|">Barra vertical</option></flux:select>@endif
                    </div>
                    <flux:checkbox label="Incluir solo trabajadores con incidencias seleccionadas" wire:model.live="form.only_incident_workers" />
                </section>
            @elseif ($activeTab === 'incidents')
                <section class="space-y-5 rounded-2xl border border-surface-line bg-white p-5 sm:p-6">
                    <div><h2 class="font-display font-semibold text-brand-navy">¿Qué incidencias llevará este archivo?</h2><p class="mt-1 text-sm text-surface-muted">Activa solo los conceptos que recibirá el destino. Arrástralos para organizar el catálogo; el orden no resuelve conflictos entre incidencias.</p></div>
                    <flux:input label="Buscar incidencia" placeholder="Domingo, prima, descanso, retardo…" wire:model.live.debounce.300ms="incidentSearch" />
                    <div class="space-y-2">
                        @foreach ($form['rules'] as $index => $rule)
                            @php($item = $catalog[$rule['key']] ?? null)
                            @if ($item && $item['available'] && ($incidentSearch === '' || mb_stripos($item['label'].' '.$rule['code'], $incidentSearch) !== false))
                                <div wire:key="incident-{{ $rule['key'] }}" x-on:dragover.prevent x-on:drop.prevent="if (dragRule !== null) { $wire.moveRule(dragRule, {{ $index }}); dragRule = null }" class="flex items-start gap-3 rounded-xl border p-4 {{ $rule['enabled'] ? 'border-blue-200 bg-blue-50/50' : 'border-surface-line' }}">
                                    <span draggable="true" x-on:dragstart="dragRule = {{ $index }}; $event.dataTransfer.setData('text/plain', '{{ $index }}')" x-on:dragend="dragRule = null" class="cursor-grab text-lg text-surface-muted" aria-hidden="true">⠿</span>
                                    <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-3"><input type="checkbox" wire:model.live="form.rules.{{ $index }}.enabled" @disabled(! $item['available']) class="mt-1 rounded border-surface-line text-brand-blue"><span><span class="block text-sm font-semibold text-brand-navy">{{ $item['label'] }}</span><span class="mt-1 block text-xs text-surface-muted">{{ $rule['code'] ?: 'Configura su nomenclatura' }}@if (! empty($item['note']))<span class="mt-1 block">{{ $item['note'] }}</span>@endif</span></span></label>
                                    <div class="flex gap-1"><button type="button" aria-label="Subir {{ $item['label'] }}" class="rounded-lg px-2 py-1 text-sm hover:bg-surface-bg" wire:click="moveRule({{ $index }}, {{ max(0, $index - 1) }})">↑</button><button type="button" aria-label="Bajar {{ $item['label'] }}" class="rounded-lg px-2 py-1 text-sm hover:bg-surface-bg" wire:click="moveRule({{ $index }}, {{ min(count($form['rules']) - 1, $index + 1) }})">↓</button></div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>
            @elseif ($activeTab === 'codes')
                <section class="space-y-4 rounded-2xl border border-surface-line bg-white p-5 sm:p-6">
                    <div><h2 class="font-display font-semibold text-brand-navy">Nomenclaturas del destino</h2><p class="mt-1 text-sm text-surface-muted">Cada incidencia activa necesita una clave única en mayúsculas. Confirma especialmente si el destino expresa retardos en minutos u horas.</p></div>
                    <flux:input label="Buscar nomenclatura" placeholder="Nombre de incidencia o código" wire:model.live.debounce.300ms="incidentSearch" />
                    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-surface-line text-xs text-surface-muted"><th class="py-3">Incidencia en Vera Time</th><th class="px-3 py-3">Código de salida</th><th class="px-3 py-3">Unidad</th></tr></thead><tbody>
                        @foreach ($form['rules'] as $index => $rule)
                            @php($item = $catalog[$rule['key']] ?? null)
                            @if ($item && $item['available'] && ($incidentSearch === '' || mb_stripos($item['label'].' '.$rule['code'], $incidentSearch) !== false))
                                <tr wire:key="code-{{ $rule['key'] }}" class="border-b border-surface-line"><td class="py-4 pr-3"><span class="block font-medium text-brand-navy">{{ $item['label'] }}</span><span class="text-xs {{ $rule['enabled'] ? 'text-emerald-700' : 'text-surface-muted' }}">{{ $rule['enabled'] ? 'Incluida' : 'Excluida' }}</span></td><td class="px-3 py-3"><input aria-label="Código de {{ $item['label'] }}" wire:model.live.debounce.500ms="form.rules.{{ $index }}.code" class="form-input min-w-24 font-mono" maxlength="20" placeholder="Clave"></td><td class="px-3 py-3"><select aria-label="Unidad de {{ $item['label'] }}" wire:model.live="form.rules.{{ $index }}.unit" class="form-select min-w-28">@foreach (IncidentCatalog::units($item) as $unit => $label)<option value="{{ $unit }}">{{ $label }}</option>@endforeach</select></td></tr>
                            @endif
                        @endforeach
                    </tbody></table></div>
                </section>
            @endif

            <section class="overflow-hidden rounded-2xl border border-surface-line bg-white">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-surface-line p-5">
                    <div><h2 class="font-display font-semibold text-brand-navy">Vista previa del archivo</h2><p class="mt-1 text-sm text-surface-muted">{{ $periodPreview ? 'Datos del período seleccionado. Se muestran hasta 20 filas; la descarga incluye todas.' : 'Datos ficticios · 12 al 18 de octubre de 2026. Cambia las opciones para ver el resultado.' }}</p></div>
                    <span class="rounded-lg px-3 py-1 text-xs font-semibold {{ $periodPreview ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $periodPreview ? 'Período real' : 'Ejemplo' }}</span>
                </div>
                @if ($previewErrors)
                    <p class="p-5 text-sm text-surface-muted">Completa la configuración para generar la vista previa: {{ implode(' ', array_slice($previewErrors, 0, 3)) }}</p>
                @elseif ($matrix)
                    <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="bg-blue-50 text-brand-navy"><tr>@foreach (array_slice($matrix['headers'], 0, 16) as $header)<th class="border-b border-r border-blue-100 px-4 py-3 font-semibold">{{ $header }}</th>@endforeach</tr></thead><tbody>@forelse (array_slice($matrix['rows'], 0, 20) as $row)<tr class="border-b border-surface-line">@foreach (array_slice($row, 0, 16) as $column => $value)<td class="border-r border-surface-line px-4 py-3 {{ $column === 0 ? 'bg-surface-bg font-medium text-brand-navy' : 'font-mono text-brand-blue' }}">{{ $value }}</td>@endforeach</tr>@empty<tr><td class="px-5 py-6 text-surface-muted" colspan="{{ min(16, count($matrix['headers'])) }}">Sin incidencias para esta selección.</td></tr>@endforelse</tbody></table></div>
                    <div class="flex flex-wrap gap-4 p-4 text-xs text-surface-muted"><span>{{ $matrix['worker_count'] }} trabajadores</span><span>{{ $matrix['date_count'] }} fechas</span><span>{{ $matrix['incident_cells'] }} celdas con incidencia</span><span>Vista limitada a 16 columnas y 20 filas.</span></div>
                    @if ($matrix['errors'])<div role="alert" class="m-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"><p class="font-semibold">Resuelve estos conflictos antes de exportar</p><ul class="mt-2 list-inside list-disc">@foreach (array_slice($matrix['errors'], 0, 30) as $error)<li>{{ $error }}</li>@endforeach</ul>@if (count($matrix['errors']) > 30)<p class="mt-2">Se muestran los primeros 30 conflictos. Reduce el alcance para revisarlos.</p>@endif</div>@endif
                    @if ($matrix['warnings'])<div class="m-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><p class="font-semibold">Conceptos fuera de la selección</p><ul class="mt-2 list-inside list-disc">@foreach ($matrix['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>@endif
                @endif
            </section>

            @if ($activeTab === 'preview')
                <div class="flex justify-end">
                    <button type="button" class="btn-outline" wire:click="downloadExample" wire:loading.attr="disabled" wire:target="downloadExample">Descargar reporte ficticio de prueba</button>
                </div>
            @endif
        </main>
    </div>
</div>
