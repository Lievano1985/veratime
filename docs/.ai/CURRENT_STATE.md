---
title: Estado actual compacto
project: Vera Time
updated: 2026-07-29
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
- Bloque F1.
- Bloque F2.
- Bloque F3A.
- Bloque F3B.
- Bloque F4.

Sprint actual:

- Bloque F5B aprobado manualmente: interfaz de importacion CSV de programacion diaria sobre lotes draft.
- Modulo de horarios F1-F5B aprobado manualmente sin hallazgos S1/S2 abiertos.

## Estado de epics

- EPIC-04 cerrado.
- EPIC-05 en progreso.

EPIC-05:

- BL-0501 cerrado.
- BL-0502 cerrado.
- BL-0503 cerrado.
- BL-0504 implementado / candidato a cierre.
- BL-0505 implementado / candidato a cierre.
- BL-0506 implementado / candidato a cierre.
- BL-0507 implementado / candidato a cierre.

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
- Anulacion logica de time_events.
- Eventos tardios / fuera de orden preparados para reconstruccion.
- /time-clock.
- /kiosk.
- Captura manual justificada.
- Nucleo de programacion diaria.
- Generacion draft desde perfiles.
- Publicacion atomica de batches diarios.
- /scheduling/daily para programacion diaria.
- Calendario semanal de programacion diaria.
- Edicion individual y masiva basica de dias en borrador.
- Verificacion de integridad de publicaciones.
- Correcciones versionadas no destructivas de programacion diaria publicada.
- Dominio de importacion CSV de programacion diaria:
  - `import_batches`.
  - `import_rows`.
  - registro, parseo, validacion, preview, huella de vigencia y aplicacion transaccional.
  - aplica solo a lotes `draft`.
  - usa `source_type = csv`.
  - reutiliza `ReplaceDraftDailyScheduleAssignmentAction`.
- Interfaz CSV de programacion diaria en `/scheduling/daily`:
  - accion compacta `Importar CSV` dentro del lote borrador.
  - descarga plantilla CSV version 1 dentro del panel de importacion.
  - carga archivo CSV privado.
  - muestra vista previa paginada con errores y advertencias.
  - permite aplicar al lote borrador despues de confirmar la vista previa.
  - descarga reporte CSV de errores.
  - no muestra historial persistente ni motivo visible de importacion en la UI.
  - no publica automaticamente.
- Base `work_days`:
  - tabla `work_days`.
  - modelo `WorkDay`.
  - generacion desde programacion diaria publicada aunque no existan eventos.
  - deteccion de eventos validos sin programacion publicada como jornada no programada.
  - resolucion por empresa, relacion laboral y fecha.
  - eventos anulados quedan excluidos mediante `ResolveValidTimeEventsForWorkDateAction`.
- Refresco operativo de `work_days`:
  - configuracion opcional de hora automatica por empresa.
  - actualizacion manual desde panel lateral en `/work-days`.
  - comandos `work-days:refresh` y `work-days:auto-refresh`.
  - scheduler cada minuto sobre database/cron, ejecutando solo empresas vencidas por hora local.
- Consulta operativa inicial de `work_days`:
  - ruta `/work-days`.
  - listado paginado y filtrable por rango, centro, horario, estado y trabajador.
  - visible para `owner`, `admin` y `rh`.
- Calculo operativo base de jornadas:
  - tabla `work_day_calculations`.
  - modelo `WorkDayCalculation`.
  - calculos versionados por jornada con version activa en `work_days.active_calculation_id`.
  - pares entrada/salida por `occurred_at_utc` y desempate estable del resolver de eventos validos.
  - jornadas que cruzan medianoche conservan los eventos de madrugada en la fecha de la entrada abierta.
  - el calculo sincroniza el resumen visible de eventos validos de la jornada.
  - pausas completas se descuentan del total operativo.
  - secuencias incompletas pasan la jornada a `under_review`.
  - `/work-days` permite ejecutar calculo manual por rango desde panel lateral.
- Clasificacion legal diaria inicial:
  - `legal_rules`, `legal_rule_versions` y `legal_parameters` guardan reglas versionadas y parametros por vigencia.
  - `LegalRuleSeeder` carga reglas base para ventana diurna, umbral nocturno de jornada mixta y limites diarios por tipo de jornada.
  - `ClassifyWorkDayCalculationAction` clasifica calculos activos como `diurnal`, `nocturnal`, `mixed` o `pending`.
  - La clasificacion usa intervalos reconstruidos, descuenta pausas y guarda snapshot de reglas aplicadas en `work_day_calculations`.
  - `/work-days` muestra columna `Legal` con Diurna/Nocturna/Mixta/Pendiente y minutos nocturnos cuando aplica.
  - no calcula horas extra, alertas ni incidencias.
- Decision de producto para motor legal configurable:
  - registrada en `docs/12-Decisiones/ADR-0005-MOTOR-LEGAL-POR-PAIS-Y-PARAMETROS-DE-EMPRESA.md`.
  - Vera tendra reglas base por pais, empezando por Mexico.
  - las reglas minimas de pais son protegidas.
  - cada empresa podra configurar parametros internos permitidos con vigencia, motivo y trazabilidad.
  - los calculos historicos conservaran snapshot de reglas y parametros usados.
- Revision de capturas manuales:
  - capturas `admin_manual` nacen como `pending_review`.
  - `owner`, `admin` y `rh` pueden aprobar o rechazar capturas pendientes.
  - aprobar cambia el evento a `valid` y refresca `work_days` para la fecha.
  - rechazar cambia el evento a `ignored` y conserva motivo de rechazo.

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

- Aplicacion completa del motor legal sobre `work_day_calculations`.
- Alertas por horas extra, limites especiales y casos de descanso.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.
- API de negocio.
- Carga XLSX WFM.
- API WFM.

## Revision integral del modulo de horarios

Estado: revision manual ejecutada y aprobada el 2026-07-28.

Documentos creados:

- `docs/06-UX/MANUAL-OPERATIVO-MODULO-DE-HORARIOS.md`.
- `docs/09-Testing/PLAN-REVISION-INTEGRAL-MODULO-DE-HORARIOS.md`.
- `docs/09-Testing/MATRIZ-RESULTADOS-REVISION-MODULO-DE-HORARIOS.md`.
- `docs/09-Testing/INVENTARIO-FUNCIONAL-MODULO-DE-HORARIOS.md`.

Objetivo:

- revisar el modulo de horarios de punta a punta antes de iniciar `work_days` y motor legal;
- validar rutas, permisos, multi-tenant, programacion diaria, snapshots, versiones y CSV;
- registrar hallazgos manuales con severidad.

Resultado:

- Horarios F1-F5B pasan flujo completo manual.
- CSV valido/invalido funciona sin publicar automaticamente.
- Correcciones versionadas conservan historial.
- Supervisor no puede modificar ni publicar.
- Responsive no bloquea uso real.
- Empresas no activas no generan 404 en `/companies`.
- No quedan fallos S1/S2 abiertos.

Motor legal, calculos, alertas, incidencias, cierres, conformidad, reportes, API WFM y XLSX siguen pendientes.

## Bloque 5 - eventos de tiempo completos

Estado: implementado/candidato a cierre, condicionado a validacion verde final y revision manual si aplica.

- `time_events` conserva `occurred_at_utc`, fecha/hora local, timezone, `received_at`, fuente, usuario/canal y metadata.
- `received_at` es el campo explicito de recepcion/captura tecnica; no se agrega `captured_at`.
- La anulacion logica usa `status = voided`, `voided_at`, `voided_by_user_id` y `void_reason`.
- La anulacion exige motivo obligatorio, actor autorizado y bloquea segunda anulacion.
- `ResolveValidTimeEventsForWorkDateAction` prepara la consulta futura de `work_days` por relacion laboral y fecha.
- Los resolvers excluyen eventos anulados, ordenan por `occurred_at_utc` y usan desempate estable por `received_at`, tipo de evento, fuente e identificadores externos/idempotencia.
- `/time-events/manual` muestra eventos recientes de la empresa y permite anular con motivo a `owner`, `admin` y `rh`.
- `supervisor`, otra empresa y membresias inactivas no pueden anular.
- No se implementa `work_days`, motor legal, horas extra, alertas, incidencias, reportes ni API.
- Siguiente bloque pendiente: `work_days`.
- Pendiente UI siguiente bloque: `/time-events/manual` lista solo 10 eventos recientes; agregar paginacion y filtros por fuente/estado para que eventos web/kiosco/anulados no queden ocultos por capturas manuales recientes.

## Bloque Work Days base

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- `work_days` existe como jornada operativa unica por empresa, trabajador y fecha.
- `RefreshWorkDaysForDateRangeAction` orquesta la generacion por rango y centro opcional.
- `GenerateWorkDaysFromPublishedSchedulesAction` crea/actualiza jornadas esperadas desde `daily_schedule_assignments` de batches `published`, aun sin eventos.
- `GenerateUnscheduledWorkDaysFromTimeEventsAction` crea/actualiza jornadas no programadas cuando existen eventos validos sin programacion publicada.
- `ResolveWorkDayForRelationshipDateAction` consulta una jornada por empresa, relacion laboral y fecha.
- La jornada conserva referencia a trabajador, relacion laboral, centro, batch publicado, asignacion diaria, tipo de dia, minutos esperados cuando aplica y resumen de eventos validos.
- No se implementa `work_day_calculations`, motor legal, horas extra, alertas, incidencias, cierres, conformidad, reportes, API ni UI de jornadas.
- Eventos sin `employment_relationship_id` quedan pendientes de resolucion futura; no se ligan automaticamente en este bloque.

## Bloque Work Days refresco operativo

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- `company_settings` permite configurar `work_days_auto_refresh_time` por empresa.
- Se guarda ultima ejecucion de jornadas con fecha UTC, estado y resumen JSON.
- `/companies` solo conserva la hora automatica de jornadas dentro de configuracion.
- `/work-days` permite ejecutar refresco manual por rango de fechas desde un panel lateral.
- `work-days:refresh` permite refresco manual por empresa, rango y centro opcional.
- `work-days:auto-refresh` evalua empresas activas con hora configurada y actualiza rango operativo por timezone local.
- El scheduler registra `work-days:auto-refresh` cada minuto con `withoutOverlapping`; en hosting/cPanel requiere cron de `php artisan schedule:run`.
- El refresco automatico no se bloquea por ejecuciones manuales previas del mismo dia.
- No se implementa `work_day_calculations`, motor legal, horas extra, alertas, incidencias, cierres, conformidad, reportes ni API.
- Siguiente bloque recomendado: consulta/listado operativo de jornadas o `work_day_calculations` basicos segun decision de producto.

## Bloque Work Days consulta operativa

Estado: cerrado e integrado a `main`.

- `/work-days` muestra las jornadas generadas por `work_days` con filtros basicos.
- `/work-days` concentra la accion manual `Actualizar jornadas` en un panel lateral; configuracion de empresa solo conserva la hora automatica.
- La consulta se concentra en `ListWorkDaysAction`; Livewire solo orquesta filtros y render.
- La policy `WorkDayPolicy` limita la vista inicial a `owner`, `admin` y `rh`.
- No se agregan calculos, motor legal, horas extra, alertas, incidencias, cierres, reportes ni API.

## Bloque Work Day Calculations base

Estado: implementado/candidato a cierre en rama `feature/work-day-calculations-foundation`.

- `work_day_calculations` versiona resultados de una jornada y conserva versiones anteriores como `superseded`.
- `CalculateWorkDayAction` calcula una jornada con eventos validos de su relacion laboral y fecha.
- `CalculateWorkDaysForDateRangeAction` calcula jornadas por empresa, rango y centro opcional.
- La version activa queda referenciada desde `work_days.active_calculation_id`.
- El calculo usa pares `clock_in`/`clock_out`, descuenta pausas completas `break_start`/`break_end` y conserva snapshots de eventos usados.
- Eventos fuera de orden se resuelven por `occurred_at_utc` con desempate estable heredado de `ResolveValidTimeEventsForWorkDateAction`.
- Eventos de madrugada que continuan una entrada abierta del dia anterior pertenecen a la jornada del dia de entrada y no crean una segunda jornada no programada.
- Cada calculo sincroniza `valid_time_event_count`, `valid_time_event_ids`, `first_event_at_utc` y `last_event_at_utc` con los eventos realmente usados.
- Jornadas con eventos incompletos quedan `under_review`; jornadas sin eventos validos permanecen `pending`.
- `/work-days` muestra minutos trabajados cuando hay calculo activo y permite ejecutar calculo manual desde un panel lateral.
- No se implementan motor legal, clasificacion diurna/nocturna/mixta real, horas extra, alertas, incidencias, cierres, reportes ni API.

## Bloque Legal Rules versionado

Estado: L1 y L2 integrados a `main`; L3 en progreso en rama `feature/work-day-ordinary-overtime-calculation`.

- Tablas base:
  - `legal_rules`.
  - `legal_rule_versions`.
  - `legal_parameters`.
- Modelos base:
  - `LegalRule`.
  - `LegalRuleVersion`.
  - `LegalParameter`.
- `ResolveLegalRuleVersionForDateAction` resuelve la version activa de una regla por codigo y fecha trabajada.
- `ResolveLegalParameterForDateAction` resuelve parametros globales o de empresa con prioridad de empresa sobre global.
- `LegalRuleSeeder` carga reglas base para clasificacion diaria.
- `ClassifyWorkDayCalculationAction` aplica clasificacion diurna/nocturna/mixta sobre `work_day_calculations` activos usando intervalos reconstruidos y pausas.
- `ClassifyWorkDayCalculationsForDateRangeAction` clasifica un rango de jornadas de una empresa.
- `/work-days` aplica la clasificacion despues del calculo manual y muestra columna `Legal`.
- Decision de configuracion legal por pais/empresa documentada en ADR-0005.
- L2 agrega configuracion legal por empresa:
  - migracion de auditoria para `legal_parameters`: `reason`, `created_by`, `updated_by`, `metadata`.
  - `CompanyLegalParameterCatalog` define parametros internos permitidos y limites protegidos.
  - `ResolveCompanyLegalConfigurationAction` muestra reglas base del pais y parametros efectivos de empresa.
  - `UpdateCompanyLegalParameterAction` guarda parametros con vigencia, motivo y actor.
  - `/companies` muestra reglas base Mexico en lectura y permite editar parametros internos permitidos.
  - parametros que superan limites protegidos quedan bloqueados.
- L3 agrega ordinario/extra:
  - `ApplyOrdinaryOvertimeForDateRangeAction` calcula ordinario y extra por dia y semana natural lunes-domingo.
  - usa reglas versionadas de limite diario/semanal o parametros de empresa mas favorables.
  - guarda `ordinary_minutes`, `overtime_minutes`, snapshot de reglas y explicacion.
  - `/work-days` muestra columnas `Ordinario` y `Extra`.
- Este bloque todavia no calcula dominicales, descansos obligatorios, alertas, incidencias ni reportes.
- Siguiente paso recomendado: validar L3; despues Bloque L4 - domingo, descanso semanal y descanso obligatorio.

## Bloque revision de capturas manuales

Estado: en progreso en rama `feature/manual-time-event-review`.

- `ApproveManualTimeEventAction` aprueba capturas manuales `pending_review` y las convierte en eventos `valid`.
- `RejectManualTimeEventAction` rechaza capturas manuales `pending_review` y las convierte en eventos `ignored` con motivo obligatorio.
- La revision conserva metadata con decision, actor, fecha UTC, estado anterior y estado resultante.
- `/time-events/manual` muestra acciones `Aprobar` y `Rechazar` para capturas manuales pendientes.
- Al aprobar, la UI refresca `work_days` de la relacion laboral y fecha del evento para que Jornadas quede actualizada.
- `supervisor`, otra empresa, membresias inactivas y eventos ya revisados quedan bloqueados.
- No se implementan calculos legales, horas extra, alertas, incidencias, cierres, reportes ni API.

## Bloque A - regla de evidencia operativa

Estado: documentado/candidato a cierre.

- Decision registrada en `docs/12-Decisiones/ADR-0004-REGLA-DE-EVIDENCIA-OPERATIVA.md`.
- La evidencia protegida es el resultado operativo: horario diario publicado, snapshots, correcciones versionadas, eventos de asistencia y futuros `work_days`, calculos, cierres, conformidad, reportes y expedientes.
- Catalogos, relaciones laborales, asignaciones organizacionales, perfiles y asignaciones de perfiles son datos intermedios mientras no hayan generado evidencia protegida.
- Un cambio posterior en catalogos, relaciones, areas o perfiles no debe recalcular ni sobrescribir horarios ya publicados.
- Para modificar una fecha ya publicada se debe usar correccion versionada de programacion diaria.
- `work_days` debe generarse desde horarios publicados aunque no existan eventos y debe identificar eventos validos sin horario como jornada no programada.
- Bloque A no cambia codigo ni comportamiento operativo; fija el criterio para relaciones laborales, asignaciones organizacionales, asignaciones de perfiles y `work_days`.

## Bloque B - correccion de relaciones laborales

Estado: implementado/candidato a cierre, condicionado a validacion verde final y prueba manual si aplica.

- Rama de trabajo: `feature/employment-relationship-corrections`.
- `AssessEmploymentRelationshipEvidenceAction` detecta evidencia protegida por relacion laboral:
  - programacion diaria en batches `published`, `superseded` o `cancelled`;
  - eventos `time_events` asociados a la relacion.
- En trabajadores, cambiar centro, puesto o fecha de ingreso de una relacion sin evidencia protegida corrige la misma relacion laboral.
- La correccion administrativa exige motivo obligatorio y registra metadata con motivo, actor, fecha, valores anteriores y valores nuevos.
- Si la relacion ya tiene evidencia protegida, no se sobrescriben centro, puesto ni fecha historica desde trabajadores.
- Cuando existe evidencia protegida, solo se permite crear una nueva vigencia hacia adelante si la fecha nueva no corta horarios publicados ni asistencias existentes.
- La UI de trabajadores muestra `Motivo del cambio laboral` al editar; Livewire solo orquesta Actions.
- Pendiente UI siguiente bloque: revisar reseteo completo del formulario de edicion de trabajadores, porque algunos inputs pueden conservar valores al cambiar/cerrar formularios.
- Bloque B no implementa `work_days`, motor legal, calculos, alertas, incidencias, reportes, API ni cambios de asignaciones organizacionales o perfiles.
- Siguientes bloques recomendados: asignaciones organizacionales y asignaciones de perfiles alineadas con la misma regla de evidencia.

## Bloque C - asignaciones organizacionales y pendientes UI

Estado: cerrado e integrado a `main`.

- El reemplazo de unidad principal podia conservar historial por vigencia o corregir el mismo registro segun la fecha indicada.
- La correccion de asignacion organizacional no modifica programacion diaria publicada; los `daily_schedule_assignments` mantienen la unidad congelada al publicar.
- `/time-events/manual` pagina eventos y filtra por fuente/estado para no ocultar registros por el limite anterior de 10.
- El formulario de trabajadores resetea sus inputs al cerrar, guardar, cancelar o cambiar entre trabajador y alta nueva.
- Bloque C no implementa `work_days`, motor legal, calculos, alertas, incidencias, reportes, API ni asignaciones de perfiles.

## Bloque D - simplificacion de vigencia laboral y asignacion organizacional

Estado: implementado/candidato a cierre en rama `feature/simplify-organization-assignment-validity`.

- La vigencia operativa manda desde la relacion laboral/trabajador: alta, baja y estado laboral.
- La asignacion organizacional deja de usar fechas visibles como regla operativa; solo segmenta trabajadores activos por unidad principal actual.
- `AssignPrimaryOrganizationalUnitAction` mantiene una sola unidad principal activa por relacion laboral.
- `ReplacePrimaryOrganizationalUnitAction` corrige la unidad principal activa sobre el mismo registro con motivo y metadata de auditoria; no abre vigencias nuevas por fecha.
- `ResolveEmploymentUnitsForDateAction` devuelve la unidad principal activa sin filtrar por vigencia de asignacion.
- Los apoyos temporales quedan fuera del flujo visible y no participan en resolucion de unidad, herencia de perfiles ni alcance supervisor.
- El seeder demo ya no crea apoyos temporales activos.
- El layout principal muestra el nombre de la vista actual en el tab del navegador.
- El menu lateral usa scrollbar delgado y discreto.
- La programacion diaria publicada conserva su unidad congelada y no se recalcula por cambios posteriores de segmentacion.
- Bloque D no implementa `work_days`, motor legal, calculos, alertas, incidencias, reportes, API ni cambios de usuarios/roles.
- Siguiente bloque recomendado: `work_days`.

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
- Bloque F3B implementa interfaz de calendario y publicacion operativa desde UI.
- Bloque F4 implementa correcciones versionadas no destructivas: clona desde la publicacion congelada, exige motivo general, compara cambios funcionales y publica en una transaccion que sustituye la version anterior.
- Bloque F5A implementa dominio de importacion CSV de programacion diaria.
- Bloque F5B implementa la interfaz de importacion CSV en `/scheduling/daily`, descarga de plantilla CSV y descarga de errores.
- No se ha implementado todavia XLSX, cierre multiple, API WFM, calculos legales, `work_days`, alertas, incidencias ni reportes.

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
- Trabajadores tienen una unidad principal activa actual para segmentacion; apoyos temporales quedan como legado fuera del flujo visible.
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
- La herencia se resuelve en este orden: relacion laboral -> unidad principal activa actual -> centro -> empresa.
- `temporary_support` no altera la herencia de perfil; solo se usa la unidad principal activa actual.
- `ResolveScheduleProfileForRelationshipAction` es el resolutor central de D1.
- Supervisores no crean perfiles; solo pueden asignar directamente a relaciones laborales dentro de su alcance operativo.
- No se implementaron `pattern_mode = cycle`, `flexible`, `on_call`, generacion diaria, publicacion operativa, API WFM ni CSV. El nucleo `schedule_batches`, `daily_schedule_assignments` y `daily_schedule_segments` se implementa en Bloque F1.

## Bloque D2 - UI de perfiles y asignaciones

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

Actualizacion de modelo operativo visible:

- La experiencia de horarios se reorganiza en tres caminos de producto:
  - Horario fijo semanal: base `pattern` con `pattern_mode = weekly`.
  - Programacion semanal: lotes lunes-domingo, edicion manual, CSV y publicacion.
  - Rol rotativo / ciclo: base `pattern` con `pattern_mode = cycle`.
- La navegacion visible cambia a "Modelos de horario", "Aplicacion de modelos" y "Programacion semanal" para reducir lenguaje tecnico.
- `calendar`, `flexible` y `on_call` siguen disponibles como opciones avanzadas o soporte para captura semanal, sin cambiar tablas ni Actions.
- Bloques 3/4 de la mejora operativa:
  - `/scheduling/profiles` filtra por camino operativo visible: horario fijo semanal, rol rotativo/ciclo, programacion semanal manual, flexible avanzado y guardia avanzada.
  - El formulario de modelo muestra una sintesis contextual de la forma seleccionada.
  - `/scheduling/profile-assignments` muestra el tipo de modelo en los combos de seleccion.
  - En ciclos, la fecha inicial se etiqueta como "Inicio del ciclo (Dia 1)" y la tabla muestra "Dia 1" en la vigencia.
  - En modelos semanales, la fecha se mantiene como vigencia desde y el texto aclara que se repite por semana y aplica solo a trabajadores vigentes por dia.

- Rutas:
  - `/scheduling/profiles`.
  - `/scheduling/profile-assignments`.
- `/scheduling/profiles` permite listar, buscar, filtrar, crear, editar, consultar, inactivar y reactivar perfiles `pattern` semanal y `calendar`.
- Los perfiles por patron semanal muestran exactamente siete reglas semanales ISO 1-7 y usan `shift_templates` activos de la empresa.
- Los perfiles por calendario no crean reglas semanales y muestran nota neutral de captura futura por periodo, importacion o API.
- `/scheduling/profile-assignments` permite asignar perfiles con vigencia a empresa, centro, unidad organizacional o relacion laboral.
- La consulta de perfil efectivo usa `ResolveScheduleProfileForRelationshipAction` y muestra origen por relacion laboral, unidad, centro, empresa o sin perfil.
- El sidebar muestra Horarios con catalogo de turnos, modelos de horario, aplicacion de modelos, programacion semanal y descansos obligatorios.
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
- En F1 no se implemento generacion desde perfiles, publicacion operativa desde UI, CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias ni reportes.

## Bloque F2 - generacion draft desde perfiles

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- `GenerateDraftScheduleBatchFromProfilesAction` llena un `schedule_batch` en `draft`.
- Modos permitidos: `missing_only` y `refresh_profile_generated`.
- `missing_only` solo crea dias faltantes y es idempotente.
- `refresh_profile_generated` reemplaza solo dias creados por el generador `schedule_profile_generation`.
- Se conservan dias `manual`, `csv`, `api` y `system` de otros procesos.
- La generacion resuelve perfil por relacion laboral y fecha de perfil: relacion laboral -> unidad principal activa actual -> centro -> empresa.
- Los apoyos temporales no modifican el perfil heredado y quedan fuera del flujo visible.
- La unidad principal activa actual y timezone del centro se congelan en la asignacion diaria.
- `pattern` semanal/ciclo genera `shift` o `rest`.
- `calendar` genera `unassigned` con `reason = calendar_requires_daily_definition`.
- `flexible` genera `flexible` o `rest`.
- `on_call` genera `on_call` o `rest`.
- Sin perfil efectivo genera `unassigned` con `source_type = system` y `reason = no_effective_schedule_profile`.
- Los segmentos de `shift_templates` se copian como snapshot diario y calculan UTC con la zona horaria del centro.
- Seeder manual: `php artisan db:seed --class=VeraTimeDailyScheduleScenarioSeeder`.
- No hay interfaz F2 todavia.
- No se implementa publicacion, snapshots persistidos, CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias ni reportes.

## Bloque F3A - publicacion atomica de batches diarios

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- `ValidateScheduleBatchForPublicationAction` valida cobertura completa por centro y periodo antes de publicar.
- La cobertura usa `ResolveScheduleBatchExpectedRelationshipDatesAction`, compartida con F2, para resolver relaciones laborales activas y fechas vigentes.
- Un batch inicial solo publica si esta en `draft`, no es correctivo (`previous_batch_id = null`), pertenece a empresa/centro activos y no contiene dias `unassigned`; el numero `version = 1` se asigna al publicar.
- La validacion bloquea dias faltantes, dias fuera de vigencia, relaciones de otro centro/tenant, configuraciones incompatibles y conflictos con batches `published` por relacion laboral y fecha.
- `PublishScheduleBatchAction` publica dentro de una transaccion, bloquea empresa, centro, batch, dias, segmentos y batches publicados intersectados del mismo centro.
- Al publicar persiste `status = published`, `snapshot_schema_version`, `snapshot_canonical_json`, `snapshot_sha256`, `published_by` y `published_at`.
- `VerifyPublishedScheduleBatchSnapshotAction` verifica el JSON y hash persistidos sin reconstruir desde catalogos actuales.
- `ResolveDailyScheduleForRelationshipDateAction` resuelve programacion publicada y devuelve `snapshot_sha256`; si no existe publicacion devuelve ausencia controlada.
- Seeder manual: `php artisan db:seed --class=VeraTimePublishedScheduleScenarioSeeder`.
- El seeder publica Oficina por Patron, Ciclo Rotativo, Horario Flexible y Bajo Demanda; deja Tienda por Calendario y Sin Perfil en `draft` por dias `unassigned`.
- No se implementan versiones correctivas, supersede automatico, CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias ni reportes.

## Bloque F3B - interfaz de programacion semanal

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Ruta: `/scheduling/daily`.
- Navegacion: Horarios -> Programacion semanal.
- La pantalla permite a `owner`, `admin` y `rh` crear de 1 a 4 lotes `draft` por centro y semanas naturales lunes-domingo consecutivas, crear semanas vacias o crear y generar desde perfiles.
- La fecha elegida para un lote se normaliza al lunes-domingo de esa semana; la vigencia de la relacion laboral solo marca dias fuera de vigencia o generables dentro de la semana.
- `PrepareNextScheduleWeekAction` permite preparar la semana siguiente o de 1 a 4 semanas futuras desde el lote seleccionado: si ya existe una semana activa la abre/salta; si no existe, crea un `draft`, genera desde modelos y nunca publica automaticamente.
- El listado usa filtros principales compactos, filtros avanzados colapsables y tabla de lotes de una fila por lote.
- Por defecto el listado muestra lotes actuales/futuros; los historicos siguen consultables cambiando el filtro `Periodo` a `Historicas` o `Todas`.
- El listado filtra por centro, periodo, estado, trabajador, unidad organizacional, tipo de dia y solo pendientes.
- El calendario muestra la semana natural del lote seleccionado, con filas por relacion laboral/trabajador, colores por tipo de dia y alternativa movil en lista.
- La navegacion `Semana anterior` / `Semana siguiente` abre lotes semanales existentes del mismo centro; si no existe el siguiente lote, la UI invita a usar `Preparar semanas` en vez de mostrar fechas vacias dentro del mismo lote.
- El calendario y los paneles de revision, comparacion, historial e integridad se pueden ocultar para mantener limpia la vista.
- Permite generar faltantes (`missing_only`) y actualizar desde perfiles (`refresh_profile_generated`) sin tocar cambios manuales ni fuentes externas.
- Permite clonar una semana publicada vigente a una nueva semana natural en `draft` mediante `ClonePublishedScheduleWeekToDraftAction`, o clonar y publicar directamente mediante `ClonePublishedScheduleWeekAndPublishAction`; los dias se desplazan al periodo destino y se omiten trabajadores sin relacion vigente.
- Permite editar un dia en borrador como `shift`, `rest`, `flexible`, `on_call` o `unassigned` usando `ReplaceDraftDailyScheduleAssignmentAction`.
- Permite cambio masivo basico con `BulkReplaceDraftDailyScheduleAssignmentsAction`; si falla un dia se revierte toda la operacion.
- Los lotes `draft` se pueden borrar definitivamente desde la UI; no pasan por estado intermedio `cancelled`. Los lotes ya publicados siguen protegidos y solo se corrigen/versionan.
- La publicacion usa `ValidateScheduleBatchForPublicationAction` y `PublishScheduleBatchAction`; despues de publicar el lote queda solo lectura.
- La consulta de lotes publicados permite verificar integridad con `VerifyPublishedScheduleBatchSnapshotAction`; el hash se consulta en el panel de integridad, no como aviso permanente.
- Supervisores pueden consultar lotes segun `ScheduleBatchPolicy` y alcance operativo vigente; no crean, generan, editan masivamente ni publican.
- F4 implementa correcciones versionadas en la misma pantalla; clonado de semanas desde una semana manual no publicada, CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias, cierres, conformidad y reportes siguen pendientes.

## Bloque F4 - correcciones versionadas

Estado: implementado/candidato a cierre, condicionado a validacion verde final.

- Una publicacion vigente puede crear un borrador correctivo sin numero de version publicada.
- `correction_reason` es obligatorio para borradores correctivos y versiones correctivas publicadas.
- La correccion se clona desde `daily_schedule_assignments` y `daily_schedule_segments` congelados, sin consultar perfiles actuales.
- La comparacion ignora IDs y timestamps y exige al menos un cambio funcional para publicar.
- Al publicar, la version anterior pasa a `superseded`, la correctiva recibe el siguiente numero de version y queda `published` dentro de una transaccion.
- `/scheduling/daily` muestra crear correccion, comparar con version anterior, publicar correccion e historial de versiones.
- `VeraTimeCorrectedScheduleScenarioSeeder` prepara una correccion publicada y una correccion draft para pruebas manuales.
- F5 CSV/XLSX, API WFM, calculos legales, `work_days`, alertas, incidencias, cierres, conformidad y reportes siguen pendientes.
