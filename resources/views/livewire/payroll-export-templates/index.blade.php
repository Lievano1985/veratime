<?php

use App\Domains\Attendance\Actions\EnsureDefaultPayrollExportTemplateAction;
use App\Domains\Attendance\Actions\ExportAttendancePeriodPayrollCsvAction;
use App\Domains\Attendance\Actions\SavePayrollExportTemplateAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\PayrollExportTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingTemplateId = null;
    public bool $showEditor = false;
    public array $form = [];

    public function mount(CurrentCompany $currentCompany, EnsureDefaultPayrollExportTemplateAction $ensureDefault): void
    {
        $company = $this->company($currentCompany);
        Gate::authorize('viewAny', [PayrollExportTemplate::class, $company]);
        $ensureDefault->handle($company);
        $this->form = $this->emptyForm();
    }

    public function openCreate(): void
    {
        Gate::authorize('create', [PayrollExportTemplate::class, $this->company(app(CurrentCompany::class))]);
        $this->editingTemplateId = null;
        $this->form = $this->emptyForm();
        $this->showEditor = true;
    }

    public function editTemplate(int $templateId, CurrentCompany $currentCompany): void
    {
        $template = $this->template($templateId, $currentCompany);
        Gate::authorize('update', $template);

        $this->editingTemplateId = $template->id;
        $this->form = [
            'name' => $template->name,
            'description' => $template->description ?? '',
            'status' => $template->status,
            'is_default' => $template->is_default,
            'delimiter' => $template->delimiter,
            'columns' => $template->columns->map(fn ($column): array => [
                'source_key' => $column->source_key,
                'header' => $column->header,
            ])->all(),
        ];
        $this->showEditor = true;
    }

    public function duplicateTemplate(int $templateId, CurrentCompany $currentCompany): void
    {
        $template = $this->template($templateId, $currentCompany);
        Gate::authorize('viewAny', [PayrollExportTemplate::class, $this->company($currentCompany)]);

        $this->editingTemplateId = null;
        $this->form = [
            'name' => 'Copia de '.$template->name,
            'description' => $template->description ?? '',
            'status' => PayrollExportTemplate::STATUS_ACTIVE,
            'is_default' => false,
            'delimiter' => $template->delimiter,
            'columns' => $template->columns->map(fn ($column): array => [
                'source_key' => $column->source_key,
                'header' => $column->header,
            ])->all(),
        ];
        $this->showEditor = true;
    }

    public function addColumn(string $sourceKey): void
    {
        if (collect($this->form['columns'])->contains('source_key', $sourceKey)) {
            return;
        }

        $field = collect(ExportAttendancePeriodPayrollCsvAction::defaultColumns())->firstWhere('key', $sourceKey);

        if ($field) {
            $this->form['columns'][] = ['source_key' => $field['key'], 'header' => $field['header']];
        }
    }

    public function removeColumn(int $index): void
    {
        unset($this->form['columns'][$index]);
        $this->form['columns'] = array_values($this->form['columns']);
    }

    public function moveColumn(int $from, int $to): void
    {
        if (! isset($this->form['columns'][$from], $this->form['columns'][$to]) || $from === $to) {
            return;
        }

        $column = $this->form['columns'][$from];
        array_splice($this->form['columns'], $from, 1);
        array_splice($this->form['columns'], $to, 0, [$column]);
    }

    public function save(CurrentCompany $currentCompany, SavePayrollExportTemplateAction $saveAction): void
    {
        $company = $this->company($currentCompany);
        $template = $this->editingTemplateId ? $this->template($this->editingTemplateId, $currentCompany) : null;
        $template
            ? Gate::authorize('update', $template)
            : Gate::authorize('create', [PayrollExportTemplate::class, $company]);

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:120', Rule::unique('payroll_export_templates', 'name')->where('company_id', $company->id)->ignore($template?->id)],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.status' => ['required', Rule::in([PayrollExportTemplate::STATUS_ACTIVE, PayrollExportTemplate::STATUS_INACTIVE])],
            'form.is_default' => ['boolean'],
            'form.delimiter' => ['required', Rule::in([',', ';', "\t"])],
            'form.columns' => ['required', 'array', 'min:1'],
            'form.columns.*.source_key' => ['required', 'string'],
            'form.columns.*.header' => ['required', 'string', 'max:255'],
        ])['form'];

        $allowed = collect(ExportAttendancePeriodPayrollCsvAction::defaultColumns())->pluck('key')->all();
        $sourceKeys = collect($validated['columns'])->pluck('source_key');
        $headers = collect($validated['columns'])->pluck('header')->map(fn (string $header) => mb_strtolower(trim($header)));

        if ($sourceKeys->diff($allowed)->isNotEmpty()) {
            throw ValidationException::withMessages(['form.columns' => 'La plantilla contiene una columna no permitida.']);
        }

        if ($sourceKeys->count() !== $sourceKeys->unique()->count()) {
            throw ValidationException::withMessages(['form.columns' => 'No puedes agregar la misma columna más de una vez.']);
        }

        if ($headers->contains('') || $headers->count() !== $headers->unique()->count()) {
            throw ValidationException::withMessages(['form.columns' => 'Cada encabezado debe tener un nombre único.']);
        }

        if ($sourceKeys->intersect(['empleado_id', 'numero_empleado'])->isEmpty()) {
            throw ValidationException::withMessages(['form.columns' => 'Incluye empleado_id o numero_empleado para identificar cada fila.']);
        }

        $saveAction->handle($company, $validated, $template);
        $this->showEditor = false;
        $this->editingTemplateId = null;
        $this->form = $this->emptyForm();
        Session::flash('status', 'Plantilla de CSV guardada.');
    }

    public function render(): mixed
    {
        $company = $this->company(app(CurrentCompany::class));
        $templates = $company->payrollExportTemplates()->with('columns')->orderByDesc('is_default')->orderBy('name')->get();
        $selected = collect($this->form['columns'] ?? [])->pluck('source_key')->all();

        return view('livewire.payroll-export-templates.index', [
            'templates' => $templates,
            'availableColumns' => collect(ExportAttendancePeriodPayrollCsvAction::defaultColumns())->reject(fn (array $field) => in_array($field['key'], $selected, true))->values(),
        ]);
    }

    private function emptyForm(): array
    {
        return [
            'name' => '',
            'description' => '',
            'status' => PayrollExportTemplate::STATUS_ACTIVE,
            'is_default' => false,
            'delimiter' => ',',
            'columns' => [],
        ];
    }

    private function company(CurrentCompany $currentCompany)
    {
        return $currentCompany->get() ?? abort(403);
    }

    private function template(int $templateId, CurrentCompany $currentCompany): PayrollExportTemplate
    {
        return $this->company($currentCompany)->payrollExportTemplates()->with('columns')->findOrFail($templateId);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">CSV de períodos</flux:heading>
            <flux:text class="mt-1">Configura el orden y los encabezados que requiere tu proveedor de nómina. Vera Time conserva los cálculos de asistencia originales.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="openCreate">Crear plantilla</flux:button>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><tr><th class="px-4 py-3">Plantilla</th><th class="px-4 py-3">Columnas</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($templates as $template)
                        <tr>
                            <td class="px-4 py-3"><div class="font-medium text-zinc-900">{{ $template->name }} @if ($template->is_default)<span class="ml-2 rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800">Predeterminada</span>@endif</div><div class="text-xs text-zinc-500">{{ $template->is_system ? 'Plantilla base de Vera Time' : ($template->description ?: 'Plantilla personalizada') }}</div></td>
                            <td class="px-4 py-3 text-zinc-600">{{ $template->columns->count() }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $template->status === 'active' ? 'Activa' : 'Inactiva' }}</td>
                            <td class="px-4 py-3 text-right"><div class="inline-flex gap-2"><flux:button size="sm" variant="ghost" wire:click="duplicateTemplate({{ $template->id }})">Duplicar</flux:button>@if (! $template->is_system)<flux:button size="sm" variant="ghost" wire:click="editTemplate({{ $template->id }})">Editar</flux:button>@endif</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($showEditor)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm" x-data>
            <div class="mb-5 flex items-center justify-between"><flux:heading>{{ $editingTemplateId ? 'Editar plantilla' : 'Nueva plantilla' }}</flux:heading><flux:button size="sm" variant="ghost" wire:click="$set('showEditor', false)">Cerrar</flux:button></div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input label="Nombre" wire:model="form.name" />
                <flux:select label="Separador" wire:model="form.delimiter"><option value=",">Coma (,)</option><option value=";">Punto y coma (;)</option><option value="&#9;">Tabulador</option></flux:select>
                <flux:textarea class="md:col-span-2" label="Descripción" wire:model="form.description" />
                <flux:checkbox label="Usar como plantilla predeterminada de la empresa" wire:model="form.is_default" />
            </div>

            <div class="mt-6 grid gap-5 lg:grid-cols-2">
                <div class="rounded-lg border border-dashed border-zinc-300 p-4"><div class="mb-3 text-sm font-semibold text-zinc-800">Columnas disponibles</div><div class="grid gap-2 sm:grid-cols-2">@foreach ($availableColumns as $field)<button type="button" draggable="true" x-on:dragstart="$event.dataTransfer.setData('text/plain', 'field:{{ $field['key'] }}')" x-on:click="$wire.addColumn('{{ $field['key'] }}')" class="cursor-grab rounded border border-zinc-200 bg-zinc-50 px-3 py-2 text-left text-sm text-zinc-700 hover:border-blue-300 hover:bg-blue-50">+ {{ $field['header'] }}</button>@endforeach</div></div>
                <div class="rounded-lg border-2 border-dashed border-blue-200 bg-blue-50/40 p-4" x-on:dragover.prevent x-on:drop.prevent="const value = $event.dataTransfer.getData('text/plain'); if (value.startsWith('field:')) { $wire.addColumn(value.slice(6)); }"><div class="mb-3 text-sm font-semibold text-zinc-800">Pizarra del archivo de salida</div><div class="space-y-2">@forelse ($form['columns'] as $index => $column)<div draggable="true" x-on:dragstart="$event.dataTransfer.setData('text/plain', 'position:{{ $index }}')" x-on:dragover.prevent x-on:drop.prevent="const value = $event.dataTransfer.getData('text/plain'); if (value.startsWith('position:')) { $wire.moveColumn(Number(value.slice(9)), {{ $index }}); }" class="flex items-center gap-2 rounded border border-blue-200 bg-white p-2"><span class="text-zinc-400">⋮⋮</span><span class="w-6 text-xs text-zinc-500">{{ $index + 1 }}</span><input class="min-w-0 flex-1 rounded border-zinc-300 text-sm" wire:model="form.columns.{{ $index }}.header"><button type="button" wire:click="moveColumn({{ $index }}, {{ max(0, $index - 1) }})" class="rounded px-2 py-1 text-xs hover:bg-zinc-100">↑</button><button type="button" wire:click="moveColumn({{ $index }}, {{ min(count($form['columns']) - 1, $index + 1) }})" class="rounded px-2 py-1 text-xs hover:bg-zinc-100">↓</button><button type="button" wire:click="removeColumn({{ $index }})" class="rounded px-2 py-1 text-xs text-red-700 hover:bg-red-50">Quitar</button></div>@empty<div class="rounded border border-dashed border-blue-300 px-4 py-8 text-center text-sm text-zinc-500">Arrastra aquí las columnas que llevará el archivo.</div>@endforelse</div></div>
            </div>
            @error('form.columns')<div class="mt-3 text-sm text-red-700">{{ $message }}</div>@enderror
            <div class="mt-6 flex justify-end"><flux:button variant="primary" wire:click="save">Guardar plantilla</flux:button></div>
        </section>
    @endif
</div>
