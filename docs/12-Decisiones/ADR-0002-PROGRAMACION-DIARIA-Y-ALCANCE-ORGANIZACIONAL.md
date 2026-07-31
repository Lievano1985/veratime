# ADR-0002 - Programacion diaria y alcance organizacional

## Estado

Aceptado.

## Contexto

Vera Time ya cuenta con centros de trabajo, trabajadores, relaciones laborales, horarios base, pausas programadas, asignaciones de horario por vigencia y eventos `time_events`.

El modelo actual permite probar horarios simples, pero no cubre todavia un esquema WFM profesional con programacion diaria publicada, varios segmentos por dia, turnos variables, rotativos, flexibles, responsables por area ni historial exacto de lo publicado.

## Problema del Modelo Actual

El modelo actual usa:

- `schedules` como horario base.
- `schedule_days` como reglas semanales.
- `schedule_breaks` como pausas del horario.
- `schedule_assignments` como asignacion vigente por trabajador.

Esto deja al perfil o plantilla como fuente operativa indirecta. Si una plantilla cambia, existe riesgo conceptual de afectar interpretaciones futuras si no se conserva un snapshot diario. Tambien limita horarios variables, rotativos y flexibles, porque no todos los dias nacen de una semana fija.

## Decisiones

- La asignacion diaria publicada sera la unica fuente de verdad operativa.
- Los perfiles de horario solo generan borradores.
- La experiencia visible de horarios se organizara en tres caminos operativos: Horario fijo semanal, Programacion semanal y Rol rotativo / ciclo.
- Un borrador no afecta registro, calculo, alertas, cierres ni reportes.
- Los dias publicados conservan snapshot JSON canonico, version consecutiva por centro y periodo, autor, fecha de publicacion y hash SHA-256.
- La publicacion es inmutable. Una correccion genera una nueva version y la version anterior queda `superseded`.
- La base soportara multiples segmentos de trabajo el mismo dia.
- Las plantillas de turno pertenecen a una empresa, no a un centro.
- Las plantillas de turno usan horas locales de reloj y no guardan timezone.
- Las plantillas de turno no guardan clasificacion legal diurna, nocturna o mixta; esa clasificacion sera resultado del motor legal.
- Flexible no es una plantilla de turno rigida; minutos requeridos y ventanas flexibles pertenecen al perfil flexible y al dia publicado.
- No habra doble escritura entre `schedules` legacy y el nuevo catalogo `shift_templates`.
- `daily_schedule_assignments` publicados y `daily_schedule_segments` son la unica fuente operativa.
- Existira un unico resolutor operativo inicial: `ResolveDailyScheduleForRelationshipDateAction`.
- Mexico es el unico pais operativo del MVP, pero se conserva modelo compatible con `country_code` y `jurisdiction_code`.
- La clave oficial de Recursos Humanos es `rh`; el uso operativo de `hr` fue retirado en Bloque A.

## Modelo Organizacional

- Los centros representan ubicaciones u operaciones reales.
- Las unidades organizacionales son opcionales y pertenecen a un centro.
- La jerarquia tecnica soporta `department`, `area` y `team` mediante `parent_id`.
- La interfaz MVP visible se limitara a tres niveles: `department` -> `area` -> `team`.
- Un trabajador tiene una unidad principal activa actual para segmentacion. Los apoyos temporales quedan como legado fuera del flujo operativo visible.
- Una empresa sin unidades puede operar normalmente por centro.
- `owner`, `admin_empresa` y `rh` tienen alcance completo de empresa.
- `supervisor` o responsable requiere alcance explicito por centro completo o una o varias unidades organizacionales.
- `supervisor` o responsable nunca obtiene alcance automatico solo por poseer el rol.

## Modelo WFM

Entidades propuestas:

- `organizational_units`
- `employment_unit_assignments`
- `operational_scope_assignments`
- `shift_templates`
- `shift_template_segments`
- `schedule_profiles`
- `schedule_profile_weekly_rules`
- `schedule_profile_cycle_rules`
- `schedule_profile_flexible_rules`
- `schedule_profile_on_call_rules`
- `schedule_profile_assignments`
- `schedule_batches`
- `daily_schedule_assignments`
- `daily_schedule_segments`

## Perfiles

### Modelo operativo visible

La interfaz no debe obligar al usuario a decidir desde nombres tecnicos como `pattern`, `calendar` o `cycle`. La decision de producto para Vera Time queda:

- Horario fijo semanal: plantilla semanal por empleado, puesto, unidad, centro o empresa. Se captura una vez y se repite semana tras semana hasta reemplazo o excepcion.
- Programacion semanal: roster explicito lunes-domingo para operaciones variables como retail, restaurantes o call centers. Se captura, importa o ajusta por semana.
- Rol rotativo / ciclo: secuencia de varios dias, catorcena, mes o patron operativo similar. El ciclo completo se captura una vez y se repite desde una fecha de inicio.

Las opciones `flexible`, `on_call` y `calendar` siguen existiendo como soporte avanzado o como forma de dejar pendientes para programacion semanal, pero no son el primer lenguaje del usuario comun.

En la aplicacion visible, la fecha de aplicacion se interpreta segun el modelo: para horario fijo semanal es vigencia desde; para rol rotativo / ciclo es el Dia 1 del ciclo; para programacion semanal manual solo habilita el modelo para generar pendientes dentro de las semanas naturales.

### Pattern

Representa perfiles por patron. Usa `pattern_mode` para distinguir la modalidad.

Modalidades previstas:

- `weekly`: reglas semanales. Genera dias borrador a partir de `schedule_profile_weekly_rules`.
- `cycle`: ciclo repetitivo a partir de `schedule_profile_cycle_rules`. La fecha `effective_from` de la asignacion representa el dia 1 del ciclo.

En D1/D2 solo esta operativo `pattern` con `pattern_mode = weekly`. En Bloque E1 queda operativo el dominio de `pattern` con `pattern_mode = cycle`, sin interfaz y sin publicar programacion diaria.

### Calendar

Se captura manualmente, por CSV/XLSX o por API. Siempre genera borrador para revision antes de publicar.

### Flexible

Define minutos requeridos y ventanas por dia de semana mediante `schedule_profile_flexible_rules`. No debe mezclarse con una plantilla de turno rigida. La ventana representa disponibilidad o margen operativo para iniciar o realizar la jornada; el detalle exacto se congelara despues en el dia publicado. En E1 queda operativo solo a nivel dominio, sin interfaz ni publicacion.

### On call

Representa disponibilidad bajo demanda o guardias mediante `schedule_profile_on_call_rules`. Separa disponibilidad, activacion futura y trabajo real. La disponibilidad no cuenta automaticamente como tiempo trabajado. En E1 no existen todavia activaciones `on_call`, alertas ni eventos automaticos.

No se mantienen alias operativos `fixed`, `variable` ni `rotating` para `schedule_profiles`.

## Publicacion Diaria

`schedule_batches` agrupa la programacion de una empresa, un centro obligatorio, una semana natural lunes-domingo y una version.

La programacion diaria se administra en semanas naturales completas. Si la UI o una Action recibe una fecha intermedia, el dominio normaliza el lote al lunes y domingo de esa semana. La vigencia de la relacion laboral no recorta el lote: solo determina que dias del trabajador quedan fuera de vigencia o son generables. Esta regla prepara `work_days`, horas extra semanales y futuras ventanas de nomina sin mezclar calculo semanal con periodo de pago.

Una operacion empresarial completa crea un batch por centro.

En Bloque F1 se implementa el nucleo de datos y dominio para crear batches en `draft`, reemplazar dias de borrador de forma atomica, construir snapshots canonicos y resolver programacion publicada. F1 no agrega pantalla, generacion desde perfiles ni publicacion operativa.

En Bloque F2 se implementa la generacion de dias en borrador desde perfiles. La generacion opera solo sobre batches `draft`, resuelve nuevamente perfil y regla por cada relacion laboral/fecha, congela unidad principal y timezone del centro, copia segmentos de plantilla y preserva dias manuales, CSV, API o de otros procesos. F2 no publica, no persiste snapshots de publicacion y no crea `work_days`.

En Bloque F3A se implementa la publicacion atomica desde dominio. La publicacion inicial solo acepta batches `draft` sin `previous_batch_id`; el numero `version = 1` se asigna al publicar. Exige cobertura completa de relaciones laborales vigentes por centro y periodo; bloquea dias `unassigned`; valida tipos `shift`, `rest`, `flexible` y `on_call`; y detecta conflictos con cualquier batch `published` por relacion laboral y fecha. No agrega interfaz, CSV, API ni correcciones de versiones.

En Bloque F3B se implementa la interfaz `/scheduling/daily`. Livewire/Volt solo captura intencion y muestra resultados: creacion de lotes, generacion, edicion individual, cambio masivo basico, validacion, publicacion y verificacion delegan en Actions de dominio. F3B no agrega correcciones versionadas, supersede automatico, CSV/XLSX ni API WFM.

En Bloque F4 se implementan correcciones versionadas no destructivas. Solo una version `published` vigente puede iniciar correccion. La correccion se crea como `draft` sin numero de version publicada, conserva empresa, centro y periodo, usa `previous_batch_id` y exige `correction_reason`. La clonacion toma dias y segmentos congelados de la publicacion anterior; no consulta perfiles ni plantillas actuales. Al publicar, recibe la version consecutiva, la version anterior pasa a `superseded` y la correctiva queda `published` en una unica transaccion. No existen ramas paralelas, despublicacion ni reapertura de publicaciones.

Al publicar, el batch queda con snapshot JSON canonico, hash SHA-256, `published_by` y `published_at`. Cada `daily_schedule_assignment` queda ligado al batch versionado con:

- relacion laboral;
- unidad organizacional, si aplica;
- fecha de trabajo;
- timezone;
- tipo de dia: `shift`, `rest`, `flexible`, `on_call` o `unassigned`;
- plantilla fuente opcional;
- fuente y referencia de origen;
- minutos o ventanas cuando el tipo de dia lo requiera.

## Multiples Segmentos

`daily_schedule_segments` permite representar:

- trabajo;
- pausa;
- ventana flexible;
- minutos requeridos;
- segmentos futuros.

Esto evita forzar todos los escenarios a un solo par entrada/salida.

## Resolucion Centralizada

Las vistas Livewire, API, CSV, jobs y calculos futuros no deben reproducir reglas de resolucion. Deben usar Actions/Services:

- `ResolveUserOperationalScopeAction`
- `EnsureUserCanManageWorkerAction`
- `ResolveScheduleProfileForRelationshipAction`
- `ResolveScheduleProfileRuleForDateAction`
- `GenerateDailySchedulesFromProfileAction`
- `GenerateDraftScheduleBatchFromProfilesAction`
- `ResolveScheduleBatchExpectedRelationshipDatesAction`
- `ValidateScheduleBatchForPublicationAction`
- `PublishScheduleBatchAction`
- `VerifyPublishedScheduleBatchSnapshotAction`
- `ResolveDailyScheduleForRelationshipDateAction`
- `BulkReplaceDraftDailyScheduleAssignmentsAction`

En Bloque D1, `ResolveScheduleProfileForRelationshipAction` resuelve perfiles con prioridad: relacion laboral, unidad principal activa actual, centro y empresa. Los apoyos temporales (`temporary_support`) no modifican la herencia del perfil ni el alcance supervisor. En Bloque E1, `ResolveScheduleProfileRuleForDateAction` interpreta la regla de la asignacion efectiva para `weekly`, `cycle`, `calendar`, `flexible` y `on_call`. En Bloque F2, `GenerateDraftScheduleBatchFromProfilesAction` usa esos resolutores para crear o refrescar dias draft. En Bloque F3A, `ResolveScheduleBatchExpectedRelationshipDatesAction` se reutiliza para validar cobertura antes de publicar. En Bloque F1, `ResolveDailyScheduleForRelationshipDateAction` consulta exclusivamente batches publicados y devuelve ausencia controlada si no existe programacion diaria publicada.

En Bloque F5A, la importacion CSV de programacion diaria se resuelve como dominio interno, sin UI ni API. El flujo registra `import_batches`, parsea CSV version 1, valida filas en `import_rows`, genera preview con huella, detecta cambios posteriores y aplica en transaccion usando `ReplaceDraftDailyScheduleAssignmentAction`. No publica automaticamente y no duplica reglas de asignacion diaria.

En Bloque F5B, la importacion CSV queda disponible desde `/scheduling/daily` para lotes `draft`. La UI descarga plantilla version 1, carga archivo privado, muestra preview paginado, aplica con confirmacion y hash vigente, permite cancelar importaciones y descarga reporte de errores. No agrega API WFM, XLSX, jobs asincronos ni publicacion automatica.

## Snapshots y Versionamiento

La programacion publicada conserva snapshot JSON canonico a nivel batch. Cualquier cambio posterior crea nueva version y marca el batch previo como `superseded`, sin destruir historial. Las cancelaciones usan estado `cancelled` y tambien conservan evidencia.

En F3A la verificacion de integridad calcula SHA-256 directamente sobre `snapshot_canonical_json` persistido y compara con `snapshot_sha256` mediante comparacion segura. No reconstruye el snapshot desde relaciones actuales, porque nombres, plantillas, unidades o catalogos pueden cambiar despues de publicar sin invalidar la evidencia historica.

La serializacion de publicaciones del mismo centro se apoya en una transaccion con `lockForUpdate` sobre `Center`, el batch, sus dias y segmentos. MySQL/MariaDB no usa indice parcial portable para "un published por relacion y fecha"; la Action valida conflictos dentro de la transaccion antes de persistir.

## Seguridad por Alcance

- Todas las tablas operativas llevan `company_id`.
- Los responsables se validan por centro o unidad.
- Un supervisor no puede administrar trabajadores fuera de sus alcances.
- Si se usa `scope_type`/`scope_id`, una Action debe validar pertenencia al tenant y tipo permitido antes de persistir. Para el MVP se recomienda preferir columnas explicitas nullable cuando existan pocos alcances.

## Regla de Escritura del Modelo Organizacional

Toda escritura sobre `organizational_units`, `employment_unit_assignments` y `operational_scope_assignments` debe ejecutarse mediante las Actions de dominio del Bloque B1.

No se permite usar `Model::create()`, `update()`, `delete()` ni `save()` directamente desde componentes Livewire, controladores, endpoints, comandos o jobs para estas tablas.

Excepciones controladas:

- factories;
- seeders;
- preparacion explicita de pruebas.

Las pantallas del Bloque B2 solo capturan intencion, validan permisos de entrada y delegan la persistencia a las Actions existentes.

## Importacion

CSV/XLSX/API preparados para programacion por calendario:

- crean batches en `draft`;
- validan empresa, centro, unidad y trabajador;
- no publican automaticamente;
- registran errores por fila;
- reutilizan las mismas Actions de dominio.

F5A implementa la primera parte para CSV de programacion diaria:

- encabezados version 1 estrictos;
- almacenamiento privado por disco/ruta;
- idempotencia por empresa, tipo e idempotency key;
- validacion previa completa antes de escribir dias;
- aplicacion all-or-nothing a lotes draft;
- `source_type = csv`;
- bloqueo de preview obsoleto mediante `validation_sha256`.

Quedan fuera de F5B: XLSX, API WFM, jobs asincronos y publicacion automatica.

## Alternativas Descartadas

- Mantener `schedule_assignments` como fuente final.
- Calcular siempre desde perfiles en tiempo real.
- Guardar solo turnos sin calendario diario.
- Permitir que Livewire resuelva reglas de alcance o publicacion.

## Consecuencias

- Mas tablas y mas flujo de publicacion.
- Mayor trazabilidad y menor riesgo de cambios destructivos.
- Mejor soporte para calendar, pattern cycle, flexible y on call.
- Base preparada para incidencias, alertas, reportes y cierres.

## Riesgos

- Refactor amplio sobre Sprint 2A/2B.
- MySQL/MariaDB no ofrece unique parcial portable para "un publicado activo por trabajador y fecha"; debe controlarse con transacciones, indices compuestos y validaciones.
- La UX de publicacion puede crecer si no se divide en bloques pequenos.

## Criterios de Aceptacion

- Existe programacion diaria publicada no destructiva.
- Un borrador no afecta registro ni calculo.
- El resolutor diario devuelve un solo resultado efectivo por trabajador y fecha.
- Los perfiles generan borradores, no resultados operativos.
- Supervisor solo accede a trabajadores dentro de su alcance.
- Empresas sin unidades operan por centro.
- La importacion por calendario crea borrador revisable.
- No se implementa optimizacion automatica ni IA.
