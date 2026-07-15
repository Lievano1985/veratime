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
- Existira un unico resolutor operativo: `ResolveDailyScheduleForWorkerAction`.
- Mexico es el unico pais operativo del MVP, pero se conserva modelo compatible con `country_code` y `jurisdiction_code`.
- La clave oficial de Recursos Humanos es `rh`; el uso operativo de `hr` fue retirado en Bloque A.

## Modelo Organizacional

- Los centros representan ubicaciones u operaciones reales.
- Las unidades organizacionales son opcionales y pertenecen a un centro.
- La jerarquia tecnica soporta `department`, `area` y `team` mediante `parent_id`.
- La interfaz MVP visible se limitara a tres niveles: `department` -> `area` -> `team`.
- Un trabajador puede tener una unidad principal vigente y apoyos temporales opcionales.
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
- `rotation_patterns`
- `rotation_pattern_days`
- `schedule_profile_assignments`
- `schedule_batches`
- `daily_schedule_assignments`
- `daily_schedule_segments`

## Perfiles

### Fixed

Usa reglas semanales. Genera dias borrador a partir de `schedule_profile_weekly_rules`.

### Variable

Se captura manualmente, por CSV/XLSX o por API. Siempre genera borrador para revision antes de publicar.

### Rotating

Usa patrones y ciclos rotativos con fecha ancla. Genera dias borrador segun `rotation_patterns` y `rotation_pattern_days`.

### Flexible

Define minutos requeridos y ventanas. No debe mezclarse con una plantilla de turno rigida.

## Publicacion Diaria

`schedule_batches` agrupa la programacion de una empresa, un centro obligatorio y un rango de fechas. La unidad organizacional puede usarse como filtro o alcance dentro del batch.

Una operacion empresarial completa crea un batch por centro.

Al publicar, cada `daily_schedule_assignment` queda con:

- trabajador;
- relacion laboral;
- centro;
- unidad organizacional, si aplica;
- fecha de trabajo;
- timezone;
- estado publicado;
- snapshot JSON canonico;
- version consecutiva por centro y periodo;
- `published_by`;
- `published_at`;
- hash SHA-256.

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
- `GenerateDailySchedulesFromProfileAction`
- `PublishScheduleBatchAction`
- `ResolveDailyScheduleForWorkerAction`

En Bloque D1, `ResolveScheduleProfileForRelationshipAction` resuelve perfiles `fixed` y `variable` con prioridad: relacion laboral, unidad principal vigente, centro y empresa. Los apoyos temporales (`temporary_support`) no modifican la herencia del perfil.

## Snapshots y Versionamiento

La programacion publicada conserva snapshot JSON canonico. Cualquier cambio posterior crea nueva version y marca registros previos como `superseded`, sin destruir historial. Las cancelaciones usan estado `cancelled` y tambien conservan evidencia.

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

CSV/XLSX/API preparados para programacion variable:

- crean batches en `draft`;
- validan empresa, centro, unidad y trabajador;
- no publican automaticamente;
- registran errores por fila;
- reutilizan las mismas Actions de dominio.

## Alternativas Descartadas

- Mantener `schedule_assignments` como fuente final.
- Calcular siempre desde perfiles en tiempo real.
- Guardar solo turnos sin calendario diario.
- Permitir que Livewire resuelva reglas de alcance o publicacion.

## Consecuencias

- Mas tablas y mas flujo de publicacion.
- Mayor trazabilidad y menor riesgo de cambios destructivos.
- Mejor soporte para variable, rotating y flexible.
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
- La importacion variable crea borrador revisable.
- No se implementa optimizacion automatica ni IA.
