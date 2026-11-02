            @if ($editingCompanyId && $canManageEditingCompany)
                <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
                    <div class="mb-4">
                        <h2 class="font-display text-lg font-bold text-brand-navy">Datos basicos</h2>
                        <p class="mt-1 text-[13px] text-surface-muted">Editar informacion general y estado operativo de la empresa.</p>
                    </div>

                    <form wire:submit="update" class="space-y-4">
                        <flux:input wire:model="editForm.name" label="Nombre comercial" required />
                        <flux:input wire:model="editForm.legal_name" label="Razón social" />
                        <flux:input wire:model="editForm.tax_id" label="RFC" />
                        <flux:input wire:model="editForm.timezone" label="Zona horaria" required />

                        <div>
                            <label class="form-label">Estado</label>
                            <x-ui.select wire:model="editForm.status">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                                @if ($isSuperAdmin)
                                    <option value="suspended">Suspendida</option>
                                    <option value="cancelled">Cancelada</option>
                                @endif
                            </x-ui.select>
                            @error('editForm.status')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($isSuperAdmin)
                            <flux:select wire:model="editForm.account_type" label="Tipo de cuenta cliente">
                                <flux:select.option value="single_company">Monoempresa</flux:select.option>
                                <flux:select.option value="multi_company">Multiempresa</flux:select.option>
                            </flux:select>
                        @endif

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <button type="submit" class="btn-primary">Guardar empresa</button>

                            @if ($isSuperAdmin)
                                <button type="button" class="btn-danger" wire:click="openDeleteDrawer({{ $editingCompanyId }})">
                                    Eliminar empresa
                                </button>
                            @endif
                        </div>
                    </form>

                    @if ($isSuperAdmin && $editingAdministratorId)
                        <section class="mt-6 rounded-2xl border border-surface-line bg-surface-bg p-4">
                            <h3 class="font-semibold text-brand-navy">Acceso del administrador</h3>
                            @if (session('status') === 'Datos de acceso del administrador actualizados.')
                                <p role="status" class="mt-3 rounded-xl border border-status-rest-line bg-status-rest-bg p-3 text-sm text-status-rest-text">{{ session('status') }}</p>
                            @endif
                            <p class="mt-1 text-sm text-surface-muted">Edita su cuenta existente. Si administra otras empresas, el cambio de acceso también aplica en ellas.</p>
                            @php($administrators = \App\Models\Company::findOrFail($editingCompanyId)->users()->whereNull('users.global_role')->wherePivot('role_id', \App\Models\Role::where('key', \App\Support\RoleKey::ADMIN_EMPRESA)->value('id'))->get())
                            @if ($administrators->count() > 1)
                                <label class="form-label mt-4">Administrador a editar</label>
                                <select class="form-select" wire:change="loadAdministrator($event.target.value)">
                                    @foreach ($administrators as $administrator)
                                        <option value="{{ $administrator->id }}" @selected($editingAdministratorId === $administrator->id)>{{ $administrator->name }} · {{ $administrator->email }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <form wire:submit="saveAdministrator" class="mt-4 space-y-4">
                                <flux:input label="Nombre del administrador" wire:model="administratorForm.name" required />
                                <flux:input label="Correo de acceso" type="email" wire:model="administratorForm.email" required />
                                <flux:input label="Nueva contraseña (opcional)" type="password" autocomplete="new-password" wire:model="administratorForm.password" />
                                <p class="text-xs text-surface-muted">Deja la contraseña vacía para conservarla. Cambiar el correo o la contraseña cierra las sesiones y revoca los tokens de esta cuenta.</p>
                                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveAdministrator">Guardar administrador</button>
                            </form>
                        </section>
                    @endif

                    <section class="mt-6 rounded-2xl border border-brand-primary/20 bg-brand-primary/5 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-brand-navy">Escenario de demostracion</h3>
                                @if (data_get($editingDemoEligibility, 'allowed'))
                                    <p class="mt-1 text-xs leading-5 text-surface-muted">Genera datos ficticios completos para validar centros, horarios, jornadas, alertas, incidencias y exportaciones.</p>
                                @else
                                    <p class="mt-1 text-xs leading-5 text-surface-muted">{{ data_get($editingDemoEligibility, 'reason') }}</p>
                                @endif

                                @if (data_get($editingDemoEligibility, 'scenario.status') === \App\Models\CompanyDemoScenario::STATUS_FAILED)
                                    <p class="mt-2 text-xs leading-5 text-status-danger-text">La preparación anterior no concluyó: {{ data_get($editingDemoEligibility, 'scenario.error_message') ?: 'sin detalle disponible' }}</p>
                                @endif
                            </div>

                            @if (data_get($editingDemoEligibility, 'allowed'))
                                <flux:modal.trigger name="confirm-company-demo-generation">
                                    <button
                                        type="button"
                                        class="btn-primary shrink-0"
                                        x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'confirm-company-demo-generation')"
                                    >
                                        Generar escenario demo
                                    </button>
                                </flux:modal.trigger>
                            @elseif ($isSuperAdmin && in_array(data_get($editingDemoEligibility, 'scenario.status'), ['completed', 'failed'], true))
                                <button type="button" class="btn-danger shrink-0" wire:click="openDemoReset({{ $editingCompanyId }})">Borrar datos de empresa</button>
                            @endif
                        </div>
                    </section>

                    @if ($isSuperAdmin && in_array(data_get($editingDemoEligibility, 'scenario.status'), ['completed', 'failed'], true))
                        <div class="mt-4 rounded-xl border border-status-warn-line bg-status-warn-bg p-4">
                            <p class="text-sm text-status-warn-text">Puedes borrar todos los datos operativos de esta empresa y dejarla vacía, o sustituirlos por otro escenario. Se conservan la empresa, usuarios, configuración y layouts.</p>
                        </div>
                        <flux:modal name="reset-company-demo" focusable class="max-w-lg">
                            <form wire:submit="resetDemo({{ $editingCompanyId }})" class="space-y-5 p-6">
                                <flux:heading size="lg">Borrar o reemplazar demo</flux:heading>
                                <p class="text-sm text-surface-muted">Se borrarán TODOS los datos operativos de esta empresa: trabajadores, centros, horarios, marcajes, incidencias, cálculos, períodos (incluidos los cerrados) y registros agregados después del demo. Se guardará una copia interna antes de continuar.</p>
                                <flux:select wire:model="demoResetReplacement" label="Después de borrar el demo">
                                    <option value="empty">Dejar empresa vacía</option>
                                    <option value="operational">Generar demo de operación y revisión</option>
                                    <option value="closed_week">Generar dos semanas terminadas para exportar</option>
                                </flux:select>
                                <flux:input wire:model="demoResetConfirmation" label="Escribe el nombre de la empresa para confirmar" />
                                <p class="text-sm font-semibold text-brand-navy">{{ $editForm['name'] }}</p>
                                @error('demoReset') <p role="alert" class="text-sm text-status-danger-text">{{ $message }}</p> @enderror
                                @error('demoResetConfirmation') <p role="alert" class="text-sm text-status-danger-text">{{ $message }}</p> @enderror
                                <div class="flex justify-end gap-3">
                                    <flux:modal.close><button type="button" class="btn-secondary" wire:loading.attr="disabled" wire:target="resetDemo">Cancelar</button></flux:modal.close>
                                    <button type="submit" class="btn-danger" wire:loading.attr="disabled" wire:target="resetDemo">
                                        <span wire:loading.remove wire:target="resetDemo">Confirmar limpieza</span>
                                        <span wire:loading wire:target="resetDemo">Procesando...</span>
                                    </button>
                                </div>
                            </form>
                        </flux:modal>
                    @endif

                    @if (data_get($editingDemoEligibility, 'allowed'))
                        <flux:modal name="confirm-company-demo-generation" focusable class="max-w-lg">
                            <div class="space-y-5 p-6">
                                <div class="space-y-2">
                                    <flux:heading size="lg">Preparar escenario de demostración</flux:heading>
                                    <flux:subheading>
                                        Se crearán datos ficticios de centros, trabajadores, horarios, marcajes, incidencias, jornadas, alertas y periodos. Solo se genera en una empresa sin datos operativos. El superadministrador puede borrar o reemplazar un demo existente.
                                    </flux:subheading>
                                </div>

                                @if ($isSuperAdmin)
                                    <fieldset class="space-y-3">
                                        <legend class="text-sm font-semibold text-brand-navy">¿Qué escenario quieres generar?</legend>
                                        <label class="flex gap-3 rounded-xl border border-surface-line p-4">
                                            <input type="radio" wire:model="demoScenarioType" value="operational">
                                            <span><strong>Operación y revisión</strong><span class="block text-sm text-surface-muted">Demo actual con jornadas, alertas y pendientes para practicar su atención.</span></span>
                                        </label>
                                        <label class="flex gap-3 rounded-xl border border-surface-line p-4">
                                            <input type="radio" wire:model="demoScenarioType" value="closed_week">
                                            <span><strong>Dos semanas terminadas para exportar</strong><span class="block text-sm text-surface-muted">Dos últimas semanas completas de lunes a domingo, con 5 trabajadores, incidencias aprobadas y períodos cerrados. Listas para CSV y layouts.</span></span>
                                        </label>
                                    </fieldset>
                                @endif

                                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <flux:modal.close>
                                        <button type="button" class="btn-secondary w-full sm:w-auto">Cancelar</button>
                                    </flux:modal.close>

                                    <button
                                        type="button"
                                        class="btn-primary w-full sm:w-auto"
                                        wire:click="requestDemo({{ $editingCompanyId }})"
                                        wire:target="requestDemo"
                                        wire:loading.attr="disabled"
                                        x-data=""
                                        x-on:click="$dispatch('close-modal', 'confirm-company-demo-generation')"
                                    >
                                        <span wire:loading.remove wire:target="requestDemo">Sí, generar demo</span>
                                        <span wire:loading wire:target="requestDemo">Preparando escenario...</span>
                                    </button>
                                </div>
                            </div>
                        </flux:modal>
                    @endif
                </section>
            @endif
