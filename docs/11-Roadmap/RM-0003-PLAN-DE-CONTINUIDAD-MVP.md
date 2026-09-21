---
id: RM-0003
title: Plan de continuidad y estabilizacion del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-09-16
updated: 2026-09-18
tags:
  - roadmap
  - mvp
  - continuidad
  - qa
  - piloto
---

# RM-0003 — Plan de continuidad y estabilización del MVP

## 1. Propósito

Ordenar el trabajo restante para llevar Vera Time desde su estado actual a un piloto controlado, sin ampliar el alcance P0 aprobado. Este documento es una guía de ejecución; no reemplaza el backlog ni modifica las reglas de arquitectura, legales o multi-tenant.

## 2. Punto de partida al 16 de septiembre de 2026

El núcleo operativo está implementado o en candidato a cierre: multi-tenant, acceso, empresas, trabajadores, organización, captura de jornada, horarios y programación diaria, cálculos de jornada, motor legal inicial, alertas, incidencias operativas, periodos de asistencia e intercambio CSV básico.

Las brechas que impiden declarar el MVP listo para piloto son:

1. La suite automatizada y la matriz de pruebas manuales todavía no tienen una evidencia completa de cierre.
2. Falta la API P0 bajo `/api/v1` con tokens por empresa y pruebas de aislamiento.
3. Faltan correcciones laborales versionadas para eventos y jornadas, así como auditoría persistente de operaciones críticas.
4. Faltan reporte de cierre versionado, conformidad explícita de la persona trabajadora y expediente de evidencia.
5. Falta validar la operación real en hosting compatible con cPanel: cron, cola `database`, almacenamiento, respaldos y recuperación.

El avance funcional estimado es orientativo y no sustituye criterios de aceptación: el núcleo está avanzado, pero el flujo regulatorio completo aún no está cerrado.

### Verificación inicial registrada

El 16 de septiembre de 2026 se ejecutaron los siguientes subconjuntos como inicio del Bloque 1:

| Alcance | Resultado |
|---|---|
| `tests/Feature/Sprint0` | 29 pruebas aprobadas, 121 aserciones |
| Jornadas, reglas legales, alertas y periodos de asistencia | 109 pruebas aprobadas, 500 aserciones |

Durante la verificación se corrigió una prueba de recálculo nocturno que invocaba el Job sin la dependencia `ProductAccess` añadida al flujo de productos contratados. Esta evidencia no equivale al cierre de la suite completa ni sustituye la ejecución de los escenarios manuales críticos.

### Regresión automatizada completa

La regresión completa se ejecutó en el worktree de continuidad el 16 de septiembre de 2026, con dependencias y configuración locales propias del worktree:

| Comando | Resultado |
|---|---|
| `php artisan test --compact` | 652 pruebas aprobadas, 2,998 aserciones, 444.48 segundos |

En esta ejecución se ajustó el fixture de asignaciones legacy para que la relación laboral exista desde antes de la fecha efectiva probada. El dominio ya resolvía correctamente la relación vigente; el dato aleatorio del fixture podía crear una relación posterior y volver la prueba no determinista.

El Bloque 1 permanece **en revisión** hasta ejecutar y registrar la matriz de pruebas manuales críticas.

## 3. Principios de ejecución

- No iniciar funciones P1 ni integraciones directas sin cerrar primero los bloques P0 de este plan.
- Toda nueva entidad operativa usa `company_id` y se prueba contra acceso cruzado.
- Web, API, CSV y jobs consumen las mismas Actions o Services de dominio.
- No se elimina ni sobrescribe evidencia laboral; las correcciones crean una nueva versión trazable.
- Los reportes confirmados son inmutables. Una modificación posterior produce una nueva versión pendiente de revisión.
- La cola sigue siendo `database`; no se añade Redis, AWS ni un worker permanente como requisito.

## 4. Secuencia priorizada

### Decisión de prioridad — 16 de septiembre de 2026

Se prioriza la API v1 antes del flujo completo de auditoría y correcciones. La API no espera el expediente ni la interfaz de auditoría, pero toda operación mutable debe conservar como mínimo el contexto de empresa, token o actor, operación, resultado y fecha técnica. El detalle de correcciones versionadas permanece como el bloque siguiente a la fundación API.

| Orden | Bloque | Resultado verificable | Dependencia |
|---:|---|---|---|
| 1 | Estabilización y QA | Suite automatizada reproducible y escenarios críticos ejecutados | Ninguna |
| 2 | API v1 base | Endpoints P0 seguros reutilizando el dominio existente | Bloque 1 |
| 3 | Auditoría y correcciones | Historial no destructivo para operaciones sensibles | Bloque 2 |
| 4 | Cierre, reporte y conformidad | Periodo cerrable, reporte versionado y postura explícita del trabajador | Bloques 2 y 3 |
| 5 | Evidencia, operación y piloto | Expediente exportable y operación validada en entorno de piloto | Bloques 1 a 4 |

## 5. Bloque 1 — Estabilización y QA

### Objetivo

Convertir el código ya implementado en una base verificable antes de incorporar capacidades nuevas.

### Actividades

- Ejecutar Pest por grupos funcionales y registrar el resultado, duración y causa de cada fallo.
- Corregir pruebas fallidas, dependencias de orden y pruebas lentas o bloqueadas.
- Ejecutar la matriz manual crítica para acceso, cambio de empresa, alcance operativo, programación diaria, publicación, eventos, cálculos y periodos.
- Revisar policies, consultas, exports y jobs para confirmar el filtro por `company_id`.
- Validar migraciones y seeds en MySQL/MariaDB compatible y compilar los recursos con `npm run build`.

### Criterio de salida

- `php artisan test` termina con resultado verde y reproducible.
- Los escenarios críticos de la guía manual tienen resultado registrado.
- No existen fallos abiertos de aislamiento multi-tenant, historial destructivo, autorización o cálculo que bloqueen operación.

## 6. Bloque 2 — Auditoría y correcciones versionadas

### Objetivo

Garantizar que una operación sensible pueda explicarse y reconstruirse sin alterar sus fuentes originales.

### Alcance

- Crear auditoría persistente para eventos manuales, anulaciones, decisiones de revisión, alertas, cierres, exportaciones, tokens y cambios de acceso.
- Implementar solicitudes de corrección para eventos y jornadas con motivo, actor, fecha UTC, estado, versión y vínculo al original.
- Recalcular de forma controlada después de aprobar una corrección, conservando snapshots previos.
- Añadir policies y pruebas de autorización, tenant y no destrucción de historial.

### Criterio de salida

- Una corrección no modifica ni elimina la fuente original.
- La auditoría permite conocer qué cambió, quién lo hizo, cuándo y por qué.
- El resultado recalculado conserva relación con sus versiones y reglas aplicadas.

## 7. Bloque 3 — API v1 base

### Objetivo

Cumplir el compromiso API-first del MVP sin crear una segunda lógica de negocio.

### Alcance inicial

- Crear `routes/api.php` y el prefijo `/api/v1`.
- Autenticación por token, resolución de empresa, permisos y rate limiting.
- Endpoints iniciales para centros, trabajadores, eventos de jornada, jornadas y periodos de asistencia.
- Resources, Requests, errores consistentes, idempotencia para recepción de eventos y auditoría de operaciones sensibles.
- Documentación del contrato y pruebas de token, permisos, validación e intento de acceso cruzado.

### Avance registrado — 17 de septiembre de 2026

Se completó la fundación técnica y los primeros recursos de la API: Sanctum con tokens ligados a empresa, middleware de resolución de tenant, scopes, rate limit de 60 solicitudes por minuto por token y empresa, `trace_id` de respuesta y endpoints `GET`/`POST`/`PUT /api/v1/time/workers`, `GET /api/v1/time/workers/{workerId}`, `POST /api/v1/time/time-events`, `GET /api/v1/time/time-events`, `GET /api/v1/time/time-events/{eventId}`, `POST /api/v1/time/time-events/{eventId}/void`, `POST /api/v1/time/time-events/{eventId}/approve`, `POST /api/v1/time/time-events/{eventId}/reject`, `GET /api/v1/time/work-days` y `GET /api/v1/time/work-days/{workDayId}`. El grupo operativo se limita actualmente a `super_admin`, `admin_empresa` y `rh_admin` con membresía activa, para proteger datos de alcance parcial mientras se implementa la identidad personal.

`POST` y `PUT /workers` reutilizan `SaveWorkerWithEmploymentRelationshipAction`, `POST /time-events` reutiliza `CreateTimeEventAction` y las acciones administrativas de eventos reutilizan `VoidTimeEventAction`, `ApproveManualTimeEventAction` y `RejectManualTimeEventAction`; por lo tanto no se introducen rutas de negocio duplicadas. La anulación exige `time-events:write`, rol administrativo, token y tenant válidos, así como motivo. Conserva el evento, actor, motivo, fecha UTC y estado previo, lo deja en `voided` y encola el recálculo de jornada si existe relación laboral. Aprobar o rechazar sólo admite capturas `admin_manual` en `pending_review`; el rechazo exige motivo y cambia el estado a `ignored`, mientras la aprobación lo cambia a `valid` y encola el recálculo cuando corresponde. Un evento externo responde `404`. La actualización exige `workers:write`, token de empresa y rol administrativo. Resuelve tanto trabajador como centro desde el tenant del token: un trabajador ajeno responde `404` y un centro ajeno o inactivo se rechaza con `422`. Si cambia centro, puesto o inicio de relación, requiere motivo; la Action conserva la relación histórica con evidencia protegida y sólo permite una vigencia futura que no corte horarios publicados ni asistencias. La administración web en `/time/api-tokens` permite emitir y revocar las credenciales propias del administrador, mostrando el secreto solo al crearlo. Las pruebas cubren emisión y revocación de token, scope insuficiente, creación, actualización, validación de centro ajeno, aislamiento entre empresas, idempotencia, anulación y revisión administrativa de eventos.

También están disponibles `GET /api/v1/time/centers` y `GET /api/v1/time/centers/{centerId}` con `centers:read`. El listado acepta `search`, `status` y `per_page`, es paginado con enlaces y devuelve únicamente `id`, código, nombre, zona horaria, estado y dirección mediante `CenterResource`. El detalle aplica tenant y Policy, y responde `404` para un identificador ajeno. Son rutas de sólo lectura. Los administradores pueden incluir `centers:read` al emitir una credencial desde `/time/api-tokens`.

Los listados `GET /api/v1/time/time-events` (scope `time-events:read`), `GET /api/v1/time/work-days` (scope `work-days:read`), `GET /api/v1/time/alerts` (scope `alerts:read`) y `GET /api/v1/time/attendance-incidents` (scope `incidents:read`) están disponibles para dichos roles administrativos. Los cuatro filtran siempre desde la empresa del token, responden de forma paginada y son exclusivamente de consulta. Alertas reutiliza `ListAlertsAction` de la interfaz web y acepta filtros de trabajador, centro, tipo, responsable, severidad, estado y fechas. Incidencias admite trabajador, estado, tipo, rango de fechas y tamaño de página. También están disponibles `GET /api/v1/time/attendance-incidents/{incidentId}`, `POST /api/v1/time/attendance-incidents` y `POST /api/v1/time/attendance-incidents/{incidentId}/cancel`: el detalle exige `incidents:read`, resuelve y autoriza la incidencia mediante su Policy dentro del tenant del token, responde `404` ante un identificador ajeno y devuelve `AttendanceIncidentResource` con `trace_id`, sin mutaciones. Creación y cancelación exigen `incidents:write`, reutilizan `CreateAttendanceIncidentAction` y `CancelAttendanceIncidentAction` del flujo web, validan el tenant y preservan evidencia. La cancelación exige motivo y es lógica; conserva el registro, motivo, actor y fecha UTC, y marca las jornadas existentes del rango para recálculo. También están disponibles los detalles administrativos `GET /api/v1/time/workers/{workerId}`, `GET /api/v1/time/time-events/{eventId}` y `GET /api/v1/time/work-days/{workDayId}` con sus scopes de lectura correspondientes. Los tres resuelven el recurso desde la empresa del token, responden `404 Not Found` ante un identificador de otro tenant y adjuntan `trace_id`; no recalculan ni alteran información durante la consulta.

También están disponibles dos detalles administrativos de solo lectura: `GET /api/v1/time/alerts/{alertId}` con `alerts:read` y `GET /api/v1/time/workers/{workerId}/relationships` con `workers:read`. Ambos resuelven primero el recurso dentro de la empresa del token y pasan por su Policy; un identificador de otro tenant responde `404`. El detalle de alerta usa `AlertResource` y no cambia su estado. El historial de relaciones entrega estado, puesto, vigencias, origen, identificador externo y centro, ordenado de lo más reciente a lo más antiguo; no expone creación ni edición de relaciones mediante rutas API específicas.

Los periodos de asistencia ya se consultan mediante `GET /api/v1/time/attendance-periods` y `GET /api/v1/time/attendance-periods/{periodId}`, ambos con `work-days:read`. El listado reutiliza `ListAttendancePeriodsAction`, filtra por centro, estado y rango de fechas, y responde paginado sin cambiar el periodo. La descarga `GET /api/v1/time/attendance-periods/{periodId}/payroll-csv` requiere `exports:read`, pasa por la Policy del periodo y reutiliza `ExportAttendancePeriodPayrollCsvAction` para transmitir el CSV. Sólo los periodos `closed` pueden exportarse; la consulta del periodo se acota primero a la empresa del token.

La identidad personal ya está incorporada: el vínculo explícito y revocable `users` ↔ `workers` vive por empresa en `user_worker_links`. Las consultas propias están disponibles en `GET /api/v1/time/me/time-events`, `GET /api/v1/time/me/time-events/{eventId}`, `GET /api/v1/time/me/work-days` y `GET /api/v1/time/me/work-days/{workDayId}`; el marcaje propio está disponible en `POST /api/v1/time/me/time-events`. Los listados admiten filtros seguros de fecha y estado; los eventos admiten además `event_type`, y ambos son paginados. El servidor deriva trabajador desde el vínculo activo y empresa desde el token; rechaza con `422` cualquier intento de enviar `company_id`, `worker_id`, `employee_code`, `center_id`, `user_id` o `external_id`. El canal queda separado de la API operativa administrativa y del kiosco.

La interfaz actual incluye: gestión del vínculo desde `/time/users`, la vista personal `/time/my-day` con eventos recientes y jornadas propias, y emisión de token personal `self:read` + `self:write` desde `POST /time/personal-access-token` para una sesión web con vínculo válido. El marcaje personal exige `Idempotency-Key`; fija `source: pwa` y `source_user_id` en servidor, devuelve el mismo evento en reintentos idempotentes y no acepta contexto de tenant/trabajador desde el cliente. El cliente puede cerrar su sesión API con `DELETE /api/v1/time/me/access-token`, que revoca sólo el Bearer token actual. El portal todavía consulta su contexto directamente en servidor; queda pendiente integrar el consumo cliente/PWA del token y ampliar la experiencia móvil sin crear una app nativa, biometría ni geolocalización.

El bloque permanece abierto: faltan los recursos P0 restantes, errores API uniformes, auditoría persistente y la cobertura completa de estado de usuario/membresía/producto en la matriz de seguridad.

### Criterio de salida

- Las operaciones expuestas reutilizan las mismas Actions que la interfaz web.
- Un token de una empresa no puede leer ni modificar datos de otra empresa.
- El contrato API y ejemplos mínimos están documentados.

## 8. Bloque 4 — Cierre, reporte y conformidad

### Objetivo

Completar el flujo de periodo: calcular, revisar, cerrar, presentar y conservar la postura de la persona trabajadora.

### Alcance

- Consolidar reglas de bloqueo de cierre para jornadas pendientes, alertas críticas y correcciones abiertas.
- Crear reportes y versiones por periodo y trabajador.
- Implementar portal de revisión y conformidad explícita: texto aceptado, versión exacta, hash, método, fecha UTC e información técnica aplicable.
- Permitir `conforme`, `no conforme` y solicitud de aclaración; nunca conformidad por silencio.
- Si un reporte confirmado cambia, generar una nueva versión y conservar la confirmación anterior ligada a su versión.

### Criterio de salida

- No es posible cerrar un periodo con bloqueantes definidos.
- Una confirmación siempre referencia una única versión inmutable.
- La corrección posterior no transfiere confirmaciones ni altera el reporte anterior.

## 9. Bloque 5 — Evidencia, operación y piloto

### Objetivo

Entregar un flujo usable, recuperable y verificable para una empresa piloto.

### Alcance

- Generar expediente por periodo con eventos, cálculos, reglas/snapshots, alertas, correcciones, auditoría, reportes y conformidad.
- Consolidar exportación CSV operativa. Las integraciones directas requieren documentación, credenciales y ambiente de prueba.
- Documentar y probar cron de cPanel, `schedule:run`, cola `database`, almacenamiento privado, respaldos y recuperación.
- Definir datos de prueba, capacitación mínima, soporte de salida y criterios de piloto.

### Criterio de salida

- El expediente se puede generar y descargar sólo por usuarios autorizados de la empresa correcta.
- Un entorno compatible con cPanel procesa jobs y tareas programadas sin worker permanente.
- Se ejecutó un periodo controlado de piloto con resultados y hallazgos registrados.

## 10. Control de avance

Cada bloque se considera **en revisión** hasta cumplir simultáneamente:

1. Historia y alcance identificados en el backlog.
2. Código, migraciones, policies y documentación actualizados.
3. Pruebas automatizadas relevantes verdes.
4. Pruebas manuales críticas registradas cuando exista interfaz.
5. Revisión de multi-tenant, seguridad e historial no destructivo.
6. Checklist `AI-0011` revisado antes del commit.

## 11. Decision de continuidad - identidad unica y acceso por canal

La vinculacion segura entre usuario y trabajador por empresa ya forma parte de este incremento de API v1. Una cuenta humana sirve para portal web y cliente móvil responsive/PWA; sus tokens personales se limitan al usuario y empresa resueltos en servidor, con `self:read` para consulta y `self:write` adicional para su propio marcaje. El contrato incluye listados y detalles propios de eventos y jornadas, más `POST /api/v1/time/me/time-events`; el trabajador siempre se obtiene desde el vínculo activo del usuario en esa empresa, nunca desde un parámetro del cliente. El marcaje usa `Idempotency-Key`, fija fuente PWA y usuario fuente en servidor y no incorpora biometría, app nativa ni geolocalización. Los roles de alcance parcial siguen sin acceso a listados operativos administrativos. Vera Time opera bajo el prefijo web `/time/...` y API `/api/v1/time/...`, reservando `/payroll/...` y `/hr/...` para productos futuros.

El kiosco conserva el mecanismo actual de activacion tecnica por empresa y codigo/NIP de marcaje. La credencial se podra vincular a la misma cuenta, pero no compartira ni solicitara la contrasena principal y no creara una sesion de portal. La implementacion incluira pruebas de aislamiento, estado y revocacion independiente para cada canal.

La entrega de una aplicacion nativa permanece fuera de P0. La prioridad inmediata es el modulo de identidad y la API reutilizable que permitan al portal responsive/PWA y a futuros clientes operar sin duplicar dominio.

## 12. Fuera de esta secuencia

Permanecen fuera de este plan inmediato: biometría, aplicación nativa, nómina integral, integraciones directas sin contrato técnico confirmado, ClickBalance API directa, Redis obligatorio y AWS como dependencia inicial.
