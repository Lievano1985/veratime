<?php

use App\Domains\Companies\Actions\UpdateCompanySettingsAction;
use App\Domains\LegalRules\Actions\ResolveCompanyLegalConfigurationAction;
use App\Domains\LegalRules\Actions\UpdateCompanyLegalParameterAction;
use App\Domains\TimeRecords\Actions\CreateKioskDevicePairingAction;
use App\Domains\TimeRecords\Actions\DeleteKioskDeviceAction;
use App\Domains\TimeRecords\Actions\RevokeKioskDeviceAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use App\Models\KioskDevice;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url(as: 'tab', except: 'operation')]
    public string $activeTab = 'operation';

    public array $settingsForm = [];
    public array $legalParameterForm = [];
    public array $kioskDeviceForm = ['name' => '', 'center_id' => ''];
    public ?string $pairingCode = null;
    public ?string $pairingQrCode = null;
    public bool $pairingQrUnavailable = false;
    public ?int $pairingDeviceId = null;
    public ?string $pairingExpiresAt = null;
    public ?string $pairingDeviceName = null;

    public function mount(CurrentCompany $currentCompany): void
    {
        $this->ensureValidTab();

        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('update', $company);

        $this->loadSettingsForm($company);
        $this->loadLegalParameterForm($company);
    }

    public function updateSettings(UpdateCompanySettingsAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('update', $company);

        $validated = $this->validate([
            'settingsForm.payroll_period_type' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'custom'])],
            'settingsForm.default_timezone' => ['required', 'string', 'max:100'],
            'settingsForm.default_closure_day' => ['nullable', 'integer', 'between:1,31'],
            'settingsForm.work_days_auto_refresh_time' => ['nullable', 'date_format:H:i'],
            'settingsForm.late_arrival_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'settingsForm.early_departure_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'settingsForm.allow_worker_corrections' => ['boolean'],
            'settingsForm.require_pin_for_kiosk' => ['boolean'],
            'settingsForm.kiosk_key' => ['nullable', 'string', 'min:8', 'max:80', 'regex:/^(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).+$/'],
            'settingsForm.require_authorized_kiosk_devices' => ['boolean'],
            'settingsForm.require_pin_for_confirmation' => ['boolean'],
        ])['settingsForm'];

        $action->handle($company, $validated);
        $this->loadSettingsForm($company->refresh());

        Session::flash('status', 'Configuracion de empresa actualizada.');
    }

    public function createKioskDevicePairing(CreateKioskDevicePairingAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [KioskDevice::class, $company]);

        $validated = $this->validate([
            'kioskDeviceForm.name' => ['required', 'string', 'max:120'],
            'kioskDeviceForm.center_id' => ['nullable', 'integer'],
        ])['kioskDeviceForm'];

        try {
            $pairing = $action->handle(
                $company,
                auth()->user(),
                $validated['name'],
                filled($validated['center_id'] ?? null) ? (int) $validated['center_id'] : null,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['kioskDeviceForm.center_id' => $exception->getMessage()]);
        }

        $this->kioskDeviceForm = ['name' => '', 'center_id' => ''];
        $this->pairingCode = $pairing['pairing_code'];
        $this->pairingDeviceId = $pairing['device']->id;
        $this->pairingDeviceName = $pairing['device']->name;
        $this->pairingExpiresAt = $pairing['device']->pairing_expires_at?->format('d/m/Y H:i');
        $this->pairingQrCode = null;
        $this->pairingQrUnavailable = false;

        try {
            $pairingUrl = route('kiosk.authorize', ['code' => $this->pairingCode]);
            $this->pairingQrCode = (new SvgWriter())->write(new QrCode(data: $pairingUrl, size: 320, margin: 10))->getDataUri();
        } catch (\Throwable $exception) {
            report($exception);
            $this->pairingQrUnavailable = true;
        }
    }

    public function revokeKioskDevice(int $deviceId, RevokeKioskDeviceAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $device = KioskDevice::query()->where('company_id', $company->id)->findOrFail($deviceId);

        $action->handle($device, auth()->user());
        Session::flash('status', 'La terminal fue revocada. Ya no puede registrar marcajes.');
    }

    public function deleteKioskDevice(int $deviceId, DeleteKioskDeviceAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $device = KioskDevice::query()->where('company_id', $company->id)->findOrFail($deviceId);

        $action->handle($device, auth()->user());

        if ($this->pairingDeviceId === $device->id) {
            $this->pairingCode = null;
            $this->pairingQrCode = null;
            $this->pairingQrUnavailable = false;
            $this->pairingDeviceId = null;
            $this->pairingDeviceName = null;
            $this->pairingExpiresAt = null;
        }

        Session::flash('status', 'La terminal fue eliminada. Ya no puede registrar marcajes.');
    }

    public function updateLegalParameter(string $code, UpdateCompanyLegalParameterAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('update', $company);

        $validated = $this->validate([
            "legalParameterForm.{$code}.value" => ['required', 'integer'],
            "legalParameterForm.{$code}.effective_from" => ['required', 'date'],
            "legalParameterForm.{$code}.reason" => ['required', 'string', 'max:500'],
        ])['legalParameterForm'][$code];

        $action->handle(
            $company,
            $code,
            (int) $validated['value'],
            $validated['effective_from'],
            $validated['reason'],
            auth()->user(),
        );

        $this->loadLegalParameterForm($company);

        Session::flash('status', 'Parametro legal actualizado.');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('update', $company);

        return [
            'currentCompany' => $company,
            'legalConfiguration' => app(ResolveCompanyLegalConfigurationAction::class)->handle($company),
            'kioskDevices' => KioskDevice::query()
                ->with(['center', 'createdBy', 'revokedBy'])
                ->where('company_id', $company->id)
                ->latest()
                ->get(),
            'activeCenters' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany): Company
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function ensureValidTab(): void
    {
        if (! in_array($this->activeTab, ['operation', 'legal', 'users', 'kiosk'], true)) {
            $this->activeTab = 'operation';
        }
    }

    private function loadSettingsForm(Company $company): void
    {
        $settings = array_merge(Company::defaultSettings(), $company->setting?->toArray() ?? []);

        $this->settingsForm = [
            'payroll_period_type' => $settings['payroll_period_type'],
            'default_timezone' => $settings['default_timezone'] ?? $company->timezone,
            'default_closure_day' => $settings['default_closure_day'],
            'work_days_auto_refresh_time' => $settings['work_days_auto_refresh_time']
                ? substr((string) $settings['work_days_auto_refresh_time'], 0, 5)
                : null,
            'late_arrival_tolerance_minutes' => (int) ($settings['late_arrival_tolerance_minutes'] ?? 0),
            'early_departure_tolerance_minutes' => (int) ($settings['early_departure_tolerance_minutes'] ?? 0),
            'allow_worker_corrections' => (bool) $settings['allow_worker_corrections'],
            'require_pin_for_kiosk' => (bool) $settings['require_pin_for_kiosk'],
            'kiosk_key' => '',
            'kiosk_key_configured' => filled($settings['kiosk_key_hash'] ?? null),
            'require_authorized_kiosk_devices' => (bool) ($settings['require_authorized_kiosk_devices'] ?? false),
            'require_pin_for_confirmation' => (bool) $settings['require_pin_for_confirmation'],
        ];
    }

    private function loadLegalParameterForm(Company $company): void
    {
        $configuration = app(ResolveCompanyLegalConfigurationAction::class)->handle($company);

        $this->legalParameterForm = collect($configuration['parameters'])
            ->mapWithKeys(fn (array $parameter, string $code): array => [$code => [
                'value' => $parameter['value'],
                'effective_from' => $parameter['effective_from'],
                'reason' => $parameter['reason'] ?: 'Configuracion interna de empresa',
            ]])
            ->all();
    }

    private function minutesLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return 'No aplica';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining === 0 ? "{$hours} h" : "{$hours} h {$remaining} min";
    }

    private function ruleValueLabel(array $rule): string
    {
        $value = $rule['value'] ?? [];

        if (array_key_exists('minutes', $value)) {
            return $this->minutesLabel((int) $value['minutes']);
        }

        if (array_key_exists('start', $value) && array_key_exists('end', $value)) {
            return "{$value['start']} - {$value['end']}";
        }

        return 'Configurada';
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div>
        <flux:heading size="xl">Configuracion de empresa</flux:heading>
        <flux:subheading>Parametros operativos, cierre, kiosco y configuracion legal de la empresa activa.</flux:subheading>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <nav class="flex gap-1 overflow-x-auto border-b border-surface-line" aria-label="Secciones de configuración de empresa">
        <button type="button" wire:click="$set('activeTab', 'operation')" class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $activeTab === 'operation' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-surface-muted hover:border-surface-line hover:text-brand-navy' }}" aria-selected="{{ $activeTab === 'operation' ? 'true' : 'false' }}">
            Operación
        </button>
        <button type="button" wire:click="$set('activeTab', 'legal')" class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $activeTab === 'legal' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-surface-muted hover:border-surface-line hover:text-brand-navy' }}" aria-selected="{{ $activeTab === 'legal' ? 'true' : 'false' }}">
            Configuración legal
        </button>
        <button type="button" wire:click="$set('activeTab', 'users')" class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $activeTab === 'users' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-surface-muted hover:border-surface-line hover:text-brand-navy' }}" aria-selected="{{ $activeTab === 'users' ? 'true' : 'false' }}">
            Usuarios
        </button>
        <button type="button" wire:click="$set('activeTab', 'kiosk')" class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $activeTab === 'kiosk' ? 'border-brand-blue text-brand-blue' : 'border-transparent text-surface-muted hover:border-surface-line hover:text-brand-navy' }}" aria-selected="{{ $activeTab === 'kiosk' ? 'true' : 'false' }}">
            Terminales de kiosco
        </button>
    </nav>

    @if ($activeTab === 'operation')
        <section class="max-w-2xl rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4">
                <flux:heading>Operacion</flux:heading>
                <flux:subheading>Parametros iniciales de cierre, kiosco, correcciones y conformidad.</flux:subheading>
            </div>

            <form wire:submit="updateSettings" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Periodo de cierre</label>
                    <x-ui.select wire:model="settingsForm.payroll_period_type">
                        <option value="weekly">Semanal</option>
                        <option value="biweekly">Quincenal</option>
                        <option value="monthly">Mensual</option>
                        <option value="custom">Personalizado</option>
                    </x-ui.select>
                    @error('settingsForm.payroll_period_type')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input wire:model="settingsForm.default_timezone" label="Zona horaria" required />
                <flux:input wire:model="settingsForm.default_closure_day" label="Dia de cierre" type="number" min="1" max="31" />
                <flux:input wire:model="settingsForm.work_days_auto_refresh_time" label="Hora automatica de jornadas" type="time" />

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="settingsForm.late_arrival_tolerance_minutes" label="Tolerancia de retardo (min)" type="number" min="0" max="240" />
                    <flux:input wire:model="settingsForm.early_departure_tolerance_minutes" label="Tolerancia de salida anticipada (min)" type="number" min="0" max="240" />
                </div>
                <p class="text-xs text-surface-muted">Estas tolerancias ajustan los minutos de retardo y salida anticipada que se reportan en jornadas y CSV de periodo.</p>

                <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <flux:input wire:model="settingsForm.kiosk_key" label="Clave de kiosco" type="password" autocomplete="new-password" placeholder="Dejar vacio para conservar la actual" />
                    <p class="mt-2 text-xs text-zinc-500">Esta clave activa el kiosco en un dispositivo y fija el contexto de la empresa. Debe tener minimo 8 caracteres, una mayuscula, un numero y un simbolo. No se muestra despues de guardarla.</p>
                    @if ($settingsForm['kiosk_key_configured'] ?? false)
                        <x-ui.badge variant="success" class="mt-2">Clave configurada</x-ui.badge>
                    @else
                        <x-ui.badge variant="warning" class="mt-2">Sin clave configurada</x-ui.badge>
                    @endif
                    @error('settingsForm.kiosk_key')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-3">
                    <flux:checkbox wire:model="settingsForm.allow_worker_corrections" label="Permitir solicitudes de correccion" />
                    <flux:checkbox wire:model="settingsForm.require_pin_for_kiosk" label="Requerir NIP en kiosco" />
                    <flux:checkbox wire:model="settingsForm.require_authorized_kiosk_devices" label="Permitir marcajes solo desde terminales autorizadas" />
                    <p class="-mt-2 text-xs text-surface-muted">Activalo cuando todas las terminales de la empresa ya esten autorizadas. La clave compartida de kiosco dejara de activar equipos nuevos.</p>
                    <flux:checkbox wire:model="settingsForm.require_pin_for_confirmation" label="Requerir NIP para conformidad" />
                </div>

                <button type="submit" class="btn-primary">Guardar configuración</button>
            </form>
        </section>
    @endif

    @if ($activeTab === 'legal')
        <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4">
                <flux:heading>Configuracion legal</flux:heading>
                <flux:subheading>Mexico preconfigurado. Las reglas base son protegidas; solo se ajustan parametros internos permitidos.</flux:subheading>
            </div>

            <div class="space-y-5">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Reglas base del pais</h3>
                        <x-ui.badge variant="neutral">MX</x-ui.badge>
                    </div>

                    <div class="overflow-x-auto rounded-md border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                <tr>
                                    <th class="px-3 py-2">Regla</th>
                                    <th class="px-3 py-2">Valor</th>
                                    <th class="px-3 py-2">Version</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                                @foreach ($legalConfiguration['rules'] as $rule)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <span class="block font-medium">{{ $rule['name'] }}</span>
                                            <span class="text-xs text-zinc-500">{{ $rule['code'] }}</span>
                                        </td>
                                        <td class="px-3 py-2">{{ $this->ruleValueLabel($rule) }}</td>
                                        <td class="px-3 py-2">
                                            <x-ui.badge variant="info">v{{ $rule['version'] }}</x-ui.badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Parametros internos</h3>

                    <div class="grid gap-3 lg:grid-cols-2">
                        @foreach ($legalConfiguration['parameters'] as $code => $parameter)
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                                <div class="mb-3 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $parameter['definition']['label'] }}</p>
                                        <p class="text-xs text-zinc-500">{{ $parameter['definition']['description'] }}</p>
                                    </div>
                                    @if ($parameter['definition']['protected_max'])
                                        <x-ui.badge variant="warning">Protegido</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="neutral">Interno</x-ui.badge>
                                    @endif
                                </div>

                                <div class="space-y-3">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <flux:input
                                            wire:model="legalParameterForm.{{ $code }}.value"
                                            label="Minutos"
                                            type="number"
                                            min="{{ $parameter['definition']['min'] }}"
                                            max="{{ $parameter['definition']['max'] }}"
                                        />
                                        <flux:input wire:model="legalParameterForm.{{ $code }}.effective_from" label="Vigente desde" type="date" />
                                    </div>

                                    <flux:input wire:model="legalParameterForm.{{ $code }}.reason" label="Motivo" />

                                    <div class="flex justify-end">
                                        <button type="button" class="btn-primary btn-sm" wire:click="updateLegalParameter('{{ $code }}')">
                                            Guardar
                                        </button>
                                    </div>
                                </div>

                                <p class="mt-2 text-xs text-zinc-500">
                                    Limite permitido: {{ $parameter['definition']['min'] }} a {{ $parameter['definition']['max'] }} minutos.
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($activeTab === 'users')
        <section class="max-w-2xl rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>Usuarios de la empresa</flux:heading>
            <flux:subheading>Administra el acceso, rol y estado de las personas con cuenta en la empresa activa.</flux:subheading>

            <div class="mt-5 rounded-lg border border-surface-line bg-surface-bg p-4">
                <p class="text-sm text-surface-text">La administración de usuarios se realiza en su pantalla especializada para mantener los permisos y el historial de acceso en un solo lugar.</p>
                <a href="{{ route('users.index') }}" wire:navigate class="btn-primary mt-4 inline-flex">Administrar usuarios</a>
            </div>
        </section>
    @endif

    @if ($activeTab === 'kiosk')
        <section class="space-y-5 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <flux:heading>Terminales autorizadas</flux:heading>
                <flux:subheading>Autoriza cada computadora o tableta antes de usarla como kiosco. El codigo de emparejamiento vence una hora despues de generarse.</flux:subheading>
            </div>

            <form wire:submit="createKioskDevicePairing" class="grid gap-3 rounded-lg border border-surface-line bg-surface-bg p-4 md:grid-cols-[1fr_240px_auto] md:items-end">
                <flux:input wire:model="kioskDeviceForm.name" label="Nombre de la terminal" placeholder="Ej. Recepcion planta norte" />
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Centro (opcional)</label>
                    <x-ui.select wire:model="kioskDeviceForm.center_id">
                        <option value="">Todos los centros</option>
                        @foreach ($activeCenters as $center)
                            <option value="{{ $center->id }}">{{ $center->name }}</option>
                        @endforeach
                    </x-ui.select>
                    @error('kioskDeviceForm.center_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-primary">Generar codigo</button>
            </form>

            @if ($pairingCode)
                <div class="grid gap-5 rounded-lg border border-brand-blue/30 bg-blue-50 p-5 dark:bg-blue-950/20 md:grid-cols-[180px_1fr]">
                    @if ($pairingQrCode)
                        <img src="{{ $pairingQrCode }}" alt="Codigo QR para autorizar {{ $pairingDeviceName }}" class="h-44 w-44 rounded bg-white p-2">
                    @else
                        <div class="flex h-44 w-44 items-center justify-center rounded border border-blue-200 bg-white p-4 text-center text-xs text-surface-muted">
                            QR no disponible. Usa el codigo manual.
                        </div>
                    @endif
                    <div>
                        <p class="font-semibold text-brand-navy">Autoriza: {{ $pairingDeviceName }}</p>
                        <p class="mt-1 text-sm text-surface-muted">Escanea el QR desde la terminal o abre <span class="font-mono">/time/kiosk/authorize</span> y pega este codigo.</p>
                        <p class="mt-3 break-all rounded border border-blue-200 bg-white px-3 py-2 font-mono text-sm text-surface-text">{{ $pairingCode }}</p>
                        <p class="mt-3 text-xs font-medium text-status-pending-text">Vence el {{ $pairingExpiresAt }}. Al completar el emparejamiento, este codigo deja de servir.</p>
                        @if ($pairingQrUnavailable)
                            <p class="mt-2 text-xs text-surface-muted">El codigo manual sigue siendo valido. Revisa la instalacion de la dependencia QR en el servidor para volver a mostrar la imagen.</p>
                        @endif
                    </div>
                </div>
            @endif

            <div class="table-wrap rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2">Terminal</th>
                            <th class="px-3 py-2">Centro</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Ultima conexion</th>
                            <th class="px-3 py-2">Autorizada por</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($kioskDevices as $device)
                            <tr>
                                <td class="px-3 py-3 font-medium">{{ $device->name }}</td>
                                <td class="px-3 py-3">{{ $device->center?->name ?? 'Todos los centros' }}</td>
                                <td class="px-3 py-3">
                                    @if ($device->status === 'active')
                                        <x-ui.badge variant="success">{{ $device->isOnline() ? 'Conectada' : 'Autorizada' }}</x-ui.badge>
                                    @elseif ($device->status === 'pending')
                                        <x-ui.badge variant="warning">Pendiente</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger">Revocada</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-surface-muted">{{ $device->last_seen_at?->timezone($currentCompany->timezone)->format('d/m/Y H:i') ?? 'Sin conexion' }}</td>
                                <td class="px-3 py-3">{{ $device->createdBy?->name ?? 'No disponible' }}</td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                    @if (in_array($device->status, ['active', 'pending'], true))
                                        <button type="button" wire:click="revokeKioskDevice({{ $device->id }})" wire:confirm="La terminal dejara de poder registrar marcajes. ¿Continuar?" class="btn-danger btn-sm">Revocar</button>
                                    @endif
                                        <button type="button" wire:click="deleteKioskDevice({{ $device->id }})" wire:confirm="La terminal se eliminara de la lista y dejara de poder registrar marcajes. El historial tecnico se conservara." class="btn-secondary btn-sm">Eliminar</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-8 text-center text-surface-muted">Aun no hay terminales autorizadas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</section>
