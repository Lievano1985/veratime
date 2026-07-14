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

- Sprint 2G en progreso: seeder demo local hasta Sprint 2.

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
- No se ha implementado todavia WFM nuevo completo, importacion CSV/XLSX, cierre multiple ni programacion diaria publicada.

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
