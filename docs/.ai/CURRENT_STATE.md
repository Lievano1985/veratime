---
title: Estado actual compacto
project: Vera Time
updated: 2026-07-12
---

# Estado actual compacto

## Producto

Vera Time.

## Stack

- Laravel.
- Livewire.
- Blade.
- Tailwind CSS.
- MySQL 8 / MariaDB compatible.
- Database queue.
- cPanel como despliegue inicial.

## Restricciones vigentes

- No PostgreSQL.
- No Redis obligatorio.
- No AWS obligatorio.
- No biometria.
- No app nativa.
- No ClickBalance API directa en P0.

## Estado de sprints

Cerrados o candidatos ya validados antes de Sprint 2G:

- Sprint 0.
- Sprint 1A.
- Sprint 1B.
- Sprint 1C.
- Sprint 1D.
- Sprint 2A.
- Sprint 2B.
- Sprint 2C.
- Sprint 2D.
- Sprint 2E.
- Sprint 2F.

Sprint actual:

- Bloque F1 en progreso: nucleo de programacion diaria, batches, asignaciones diarias, segmentos, snapshots deterministas y resolucion de programacion publicada.

## Estado de epics

- EPIC-04 cerrado.
- EPIC-05 en progreso.

EPIC-05:

- BL-0501 cerrado.
- BL-0502 cerrado.
- BL-0503 cerrado.
- BL-0504 implementado / candidato a cierre.
- BL-0505 implementado / candidato a cierre.
- BL-0506 pendiente.
- BL-0507 pendiente.

## Implementado

- Empresas.
- Centros.
- Trabajadores.
- Relaciones laborales.
- Condiciones laborales.
- Credenciales kiosco.
- Horarios.
- Pausas programadas.
- Asignaciones.
- Descansos obligatorios.
- time_events.
- /time-clock.
- /kiosk.
- Captura manual justificada.

Nota de descansos obligatorios:

- `mandatory_rest_days` no usa `center_id`.
- `type`: `legal_mandatory`, `electoral`, `company_internal`.
- `scope`: `national`, `subnational`, `company`.
- `country_code`: ISO de 2 letras; durante el MVP se fija en `MX`.
- `jurisdiction_code`: jurisdiccion normalizada, por ejemplo `MX-NLE`.
- `legal_mandatory` y `electoral` solo aplican a `national` o `subnational`.
- `company_internal` solo aplica a `company`.
- `country_code` y `jurisdiction_code` se toman normalizados desde `centers.address` al resolver por fecha; no se usan nombres libres de estados o regiones.
- `source_reference` guarda el fundamento o referencia visible.
- `capture_source` guarda el origen tecnico de captura: `manual`, `seeder`, `import`, `system`.
- Solo `super_admin` administra catalogos nacionales, subnacionales o electorales globales.
- Usuarios de empresa solo administran descansos internos de su empresa.
- No se implementa cumplimiento internacional, calendarios extranjeros ni selector de pais en el MVP.

## No implementado

- Anulacion logica.
- Eventos tardios / fuera de orden.
- Motor legal.
- work_days.
- work_day_calculations.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.
- API de negocio.
- CSV.
- Programacion diaria operativa desde UI.
- Generacion desde perfiles.
- Publicacion operativa de batches.

## Validacion Sprint 2F

- Arquitectura aprobada con observaciones corregidas.
- QA aprobado con S3 no bloqueantes.
- S1: ninguno.
- S2: ninguno.
- `php artisan migrate:fresh --seed`: OK.
- `php artisan test tests\Feature\Sprint2F`: OK, 28 passed / 136 assertions.
- `php artisan test`: OK, 259 passed / 783 assertions.
- `npm.cmd run build`: OK, con warning no bloqueante `Generated an empty chunk: "app"`.

## Pendientes S3 Sprint 2F

- Agregar prueba futura para codigo/NIP duplicado entre empresas.
- Agregar prueba futura para credencial cuyo `worker_id` no corresponda a la misma empresa.
- Revisar warning no bloqueante de Vite: `Generated an empty chunk: "app"`.
- Endurecer en futura revision la estrategia de timezone de captura manual si se expone fuera de la UI actual.
## Sprint 2G en progreso

Objetivo: seeder demo local para probar Vera Time hasta lo implementado en Sprint 2.

- Seeder: `database/seeders/VeraTimeDemoSeeder.php`.
- Ejecucion manual: `php artisan db:seed --class=VeraTimeDemoSeeder`.
- Datos ficticios/locales: empresa demo, usuarios demo, centros, trabajadores, relaciones laborales, condiciones, credenciales kiosco, horarios, pausas, asignaciones, descansos obligatorios y eventos `time_events` demo.
- No crea `work_days`, `work_day_calculations`, alertas, incidencias, reportes, API ni CSV.
## UX-01 refinamiento general Sprint 2

## Consolidacion WFM propuesta

- Estado: diseno documental en progreso, sin codigo funcional implementado.
- ADRs propuestos:
  - `docs/12-Decisiones/ADR-0002-PROGRAMACION-DIARIA-Y-ALCANCE-ORGANIZACIONAL.md`.
  - `docs/12-Decisiones/ADR-0003-PERFILES-MULTIPLES-DE-CIERRE.md`.
- Mexico es el unico pais operativo del MVP; el modelo conserva `country_code` y `jurisdiction_code`.
- La clave oficial de Recursos Humanos es `rh`; el uso operativo de `hr` fue retirado en Bloque A.
- La asignacion diaria publicada sera la unica fuente de verdad operativa.
- `daily_schedule_assignments` publicados y `daily_schedule_segments` son la unica fuente operativa.
- Los perfiles de horario solo generaran borradores.
- Supervisor/responsable requiere alcance explicito por centro completo o unidad; el rol no otorga alcance automatico.
- La jerarquia visible MVP sera `department` -> `area` -> `team`, conservando `parent_id`.
- Cada `schedule_batch` pertenece a empresa, centro y rango; una operacion de empresa completa crea un batch por centro.
- La publicacion usa version consecutiva por centro/periodo, snapshot JSON canonico, hash SHA-256, `published_by` y `published_at`; una correccion crea nueva version y la anterior queda `superseded`.
- Los cierres multiples tendran prioridad: relacion laboral, unidad, centro, empresa.
- Bloque B1 implementa modelo organizacional base, asignaciones de trabajadores a unidades y alcances operativos por centro/unidad.
- Bloque B2 implementa pantallas Livewire/Volt para areas/departamentos, asignaciones organizacionales, responsables/supervisores y consulta "Mi alcance".
- Bloque C implementa el catalogo nuevo de plantillas de turno y segmentos diarios en `/scheduling/shifts`.
- `shift_templates` y `shift_template_segments` existen como catalogo reutilizable por empresa.
- Las plantillas usan horas locales de reloj, sin `timezone`, sin `center_id`, sin clasificacion legal y sin ventanas flexibles.
- El modelo legacy `schedules`, `schedule_days`, `schedule_breaks` y `schedule_assignments` sigue temporalmente disponible; su retiro queda pendiente para Bloque J.
- Bloque D1 implementa perfiles de horario `pattern` con `pattern_mode = weekly` y `calendar`, reglas semanales para patron semanal, asignaciones por vigencia y resolucion por herencia.
- Bloque D2 implementa las pantallas `/scheduling/profiles` y `/scheduling/profile-assignments`, consulta de perfil efectivo y navegacion reorganizada de horarios.
- Bloque E1 implementa dominio y pruebas para `pattern_mode = cycle`, perfiles `flexible` y perfiles `on_call`, con reglas completas y resolucion por fecha desde una asignacion efectiva.
- Bloque E2 implementa la interfaz de `/scheduling/profiles` para ciclo repetitivo, flexible y bajo demanda.
- No se ha implementado todavia importacion CSV/XLSX, cierre multiple, generacion desde perfiles ni publicacion operativa desde UI. El nucleo de programacion diaria se implementa en Bloque F1.

Rama de trabajo: `ux-01-refinamiento-general-sprint-2`.

Objetivo en curso: refinamiento visual y localizacion al espanol de Mexico de las pantallas visibles actuales.

Incluye:

- Logo Vera Time en login y kiosco.
- Favicon de Vera Time.
- Textos visibles del starter kit traducidos al espanol.
- `APP_LOCALE=es` y `APP_FAKER_LOCALE=es_MX` en `.env.example`.
- Archivos base de idioma en `lang/es*`.

No incluye:

- Selector de idioma.
- Nuevas funcionalidades.
- Cambios de base de datos.
- Redisenos fuera del refinamiento visual actual.

## Bloque A - roles y autorizacion base

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Rol canonico de Recursos Humanos: `rh`.
- `hr` no se mantiene como alias operativo.
- Las claves de rol se centralizan en `App\Support\RoleKey`.
- `owner`, `admin` y `rh` conservan acceso empresarial completo en el MVP actual.
- `supervisor` no obtiene permisos globales; su alcance explicito se administra desde Bloque B2.
- No se implementaron unidades organizacionales, alcances operativos ni programacion diaria en este bloque.
## Bloque B1 - modelo organizacional y alcances

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Tablas: `organizational_units`, `employment_unit_assignments`, `operational_scope_assignments`.
- Jerarquia soportada: `department` -> `area` -> `team` dentro de un centro.
- Las unidades son opcionales; una empresa puede operar solo con centros.
- Trabajadores pueden tener una unidad principal vigente y apoyos temporales.
- Supervisores solo tienen alcance mediante registros explicitos por centro o unidad.
- `owner`, `admin` y `rh` conservan alcance completo de empresa sin scope explicito.
- Las escrituras sobre `organizational_units`, `employment_unit_assignments` y `operational_scope_assignments` deben pasar por Actions de dominio.
- B2 agrega la UI operativa; importacion, programacion diaria, perfiles de horario y cierres siguen pendientes.

## Bloque B2 - UI organizacional y alcances

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Pantallas:
  - `/organization/units`
  - `/organization/assignments`
  - `/organization/scopes`
  - `/organization/my-scope`
- `owner`, `admin` y `rh` administran unidades, asignaciones y alcances.
- `supervisor` puede consultar unidades y ver su propio alcance, pero no administra estructura ni scopes.
- Las pantallas reutilizan Actions B1 para crear, reemplazar, finalizar e inactivar.
- No se implementaron plantillas de turno, perfiles de horario, programacion diaria, incidencias, alertas, reportes, API WFM ni CSV.

## Bloque C - catalogo de plantillas de turno

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Tablas: `shift_templates` y `shift_template_segments`.
- Ruta: `/scheduling/shifts`.
- Las plantillas pertenecen a una empresa, no a un centro.
- No guardan timezone, tipo legal, trabajador, vigencias, minutos flexibles ni ventanas flexibles.
- Soportan multiples segmentos de trabajo, descansos fijos, descansos por duracion y cruce de medianoche mediante offsets 0/1.
- Las metricas derivadas distinguen trabajo programado bruto, descansos fijos pagados/no pagados, descansos por duracion pagados/no pagados, trabajo efectivo programado y duracion total.
- El trabajo efectivo programado descuenta solo descansos por duracion no pagados; los descansos fijos ya estan fuera de los segmentos de trabajo y no se descuentan doble.
- La pantalla permite listar, buscar, filtrar, crear, editar, inactivar, reactivar y previsualizar plantillas.
- Supervisores solo consultan plantillas activas si tienen alcance operativo vigente; no administran.
- El seeder demo crea plantillas neutrales sin asignar trabajadores ni generar dias.
- En Bloque C no se implementaron perfiles, programacion diaria, API WFM ni CSV; esos elementos se abordan en bloques posteriores. El nucleo `schedule_batches`, `daily_schedule_assignments` y `daily_schedule_segments` se implementa en Bloque F1.

## Bloque D1 - perfiles pattern weekly y calendar

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Tablas: `schedule_profiles`, `schedule_profile_weekly_rules`, `schedule_profile_assignments`.
- Tipos implementados: `pattern` con `pattern_mode = weekly` y `calendar`.
- `pattern` + `weekly` requiere exactamente siete reglas semanales ISO 1-7 y al menos un dia con turno.
- `calendar` no admite reglas semanales en D1.
- `pattern_mode = cycle`, `flexible` y `on_call` quedan preparados conceptualmente en D1 y operativos a nivel dominio en E1.
- Los turnos de perfiles por patron semanal usan `shift_templates` activos de la misma empresa.
- Las asignaciones soportan alcance `company`, `center`, `organizational_unit` y `employment_relationship`.
- La herencia se resuelve en este orden: relacion laboral -> unidad principal vigente -> centro -> empresa.
- `temporary_support` no altera la herencia de perfil; solo se usa la unidad principal vigente.
- `ResolveScheduleProfileForRelationshipAction` es el resolutor central de D1.
- Supervisores no crean perfiles; solo pueden asignar directamente a relaciones laborales dentro de su alcance operativo.
- No se implementaron `pattern_mode = cycle`, `flexible`, `on_call`, generacion diaria, publicacion operativa, API WFM ni CSV. El nucleo `schedule_batches`, `daily_schedule_assignments` y `daily_schedule_segments` se implementa en Bloque F1.

## Bloque D2 - UI de perfiles y asignaciones

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Rutas:
  - `/scheduling/profiles`.
  - `/scheduling/profile-assignments`.
- `/scheduling/profiles` permite listar, buscar, filtrar, crear, editar, consultar, inactivar y reactivar perfiles `pattern` semanal y `calendar`.
- Los perfiles por patron semanal muestran exactamente siete reglas semanales ISO 1-7 y usan `shift_templates` activos de la empresa.
- Los perfiles por calendario no crean reglas semanales y muestran nota neutral de captura futura por periodo, importacion o API.
- `/scheduling/profile-assignments` permite asignar perfiles con vigencia a empresa, centro, unidad organizacional o relacion laboral.
- La consulta de perfil efectivo usa `ResolveScheduleProfileForRelationshipAction` y muestra origen por relacion laboral, unidad, centro, empresa o sin perfil.
- El sidebar muestra Horarios con catalogo de turnos, perfiles de horario, asignaciones de perfiles y descansos obligatorios.
- Las rutas legacy `/schedules` y `/schedule-assignments` siguen existiendo, pero ya no aparecen en la navegacion normal.
- Supervisores solo pueden asignar perfiles directamente a relaciones laborales dentro de su alcance operativo.
- Existe seeder manual independiente `VeraTimeScheduleProfileScenarioSeeder` para probar aislamiento multi-tenant, perfiles `pattern` semanal, perfiles `calendar`, herencia empresa -> centro -> unidad -> relacion laboral y empresa sin perfil efectivo. Se ejecuta con `php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder`.
- El seeder manual no se llama desde `DatabaseSeeder` y crea escenarios D2/E1 de perfiles, incluyendo ciclo, flexible y bajo demanda, sin generar programacion diaria.
- Bloque E2 implementa la UI de perfiles avanzados, pero no implementa generacion semanal/publicada, publicacion operativa, API WFM, CSV/XLSX, incidencias, alertas ni calculos legales.

## Bloque E1 - perfiles avanzados de dominio

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Tablas:
  - `schedule_profile_cycle_rules`.
  - `schedule_profile_flexible_rules`.
  - `schedule_profile_on_call_rules`.
- `pattern` admite `pattern_mode = weekly` y `pattern_mode = cycle`.
- `calendar`, `flexible` y `on_call` usan `pattern_mode = null`.
- `fixed`, `variable` y `rotating` no son alias operativos.
- El ciclo usa dias consecutivos desde 1 y la fecha `effective_from` de la asignacion como dia 1.
- Flexible define minutos requeridos y ventana opcional por dia; la ventana no es turno fijo.
- Bajo demanda define disponibilidad y maximo de trabajo futuro; la disponibilidad no cuenta como tiempo trabajado.
- `ResolveScheduleProfileRuleForDateAction` resuelve la regla de una asignacion efectiva sin crear programacion diaria.
- E2 agrega interfaz para administrar estas reglas desde `/scheduling/profiles`.
- No se implementaron activaciones on-call, API WFM, CSV/XLSX ni calculos legales. El nucleo `schedule_batches`, `daily_schedule_assignments` y `daily_schedule_segments` se implementa en Bloque F1 sin generacion ni publicacion operativa desde UI.

## Bloque E2 - interfaz de perfiles avanzados

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- `/scheduling/profiles` permite administrar cuatro metodos visibles:
  - Por patron.
  - Por calendario.
  - Flexible.
  - Bajo demanda.
- Por patron permite:
  - Patron semanal.
  - Ciclo repetitivo.
- La UI de ciclo permite agregar, quitar y ordenar dias, con numeracion consecutiva automatica.
- La UI flexible administra siete reglas con minutos requeridos y ventana opcional.
- La UI bajo demanda administra siete reglas de disponibilidad y maximo al activarse.
- Cambiar de metodo en edicion requiere confirmacion antes de reemplazar reglas existentes.
- Supervisores solo consultan perfiles activos con alcance; no crean ni editan reglas.
- No se implementaron generacion de programacion diaria desde perfiles, publicacion operativa desde UI, activaciones on-call, alertas, API WFM, CSV/XLSX ni calculos. El nucleo de batches, asignaciones diarias, segmentos y snapshots se implementa en Bloque F1.

## Bloque F1 - nucleo de programacion diaria

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Tablas:
  - `schedule_batches`.
  - `daily_schedule_assignments`.
  - `daily_schedule_segments`.
- Cada batch pertenece a empresa, centro, periodo y version.
- Estados de batch: `draft`, `published`, `superseded`, `cancelled`.
- Las asignaciones diarias soportan `day_type`: `shift`, `rest`, `flexible`, `on_call`, `unassigned`.
- Los segmentos diarios congelan horas locales, offsets, UTC opcional, tipo, modalidad, pago, obligatoriedad y orden.
- El snapshot canonico se construye desde el batch y sus dias/segmentos con JSON determinista y hash SHA-256.
- `ResolveDailyScheduleForRelationshipDateAction` resuelve solo programacion diaria publicada; no usa perfiles ni modelo legacy como fallback.
- Las versiones no draft son inmutables desde las Actions F1.
- MySQL/MariaDB compatible; la unicidad del dia dentro del batch se apoya en indice compuesto portable.
- No hay pantalla F1 todavia.
- No se implementa generacion desde perfiles, publicacion operativa desde UI, CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias ni reportes.
