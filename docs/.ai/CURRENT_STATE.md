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