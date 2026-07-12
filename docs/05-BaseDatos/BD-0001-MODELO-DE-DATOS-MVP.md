---
id: BD-0001
title: Modelo de datos del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-03
updated: 2026-07-03
tags:
  - base-datos
  - mysql
  - mariadb
  - modelo-datos
  - mvp
  - multi-tenant
  - api-first
  - veratime
---

# BD-0001 — Modelo de datos del MVP

## 1. Objetivo

Definir el modelo de datos base para el MVP de Vera Time.

Este documento aterriza en tablas, relaciones, estados, campos principales, índices y reglas de integridad las decisiones aprobadas en:

```text
docs/03-Requisitos/REQ-0001-ESPECIFICACION-REQUISITOS-MVP.md
docs/04-Arquitectura/ARQ-0001-ARQUITECTURA-DEL-MVP.md
```

El objetivo es que el equipo pueda construir migraciones Laravel para MySQL 8 / MariaDB compatible sin improvisar el dominio.

---

## 2. Principios del modelo

1. **Multi-tenant por `company_id`.**
   Toda entidad operativa pertenece directa o indirectamente a una empresa.

2. **Domain-first + API-first pragmático.**
   Los datos deben funcionar igual si nacen desde web, API, CSV, kiosco, job o integración.

3. **Eventos no destructivos.**
   Los eventos de jornada no se eliminan físicamente en operaciones ordinarias.

4. **Correcciones versionadas.**
   Las correcciones no sobrescriben silenciosamente los datos originales.

5. **Cálculos reproducibles.**
   Cada cálculo debe saber qué datos, reglas y condiciones utilizó.

6. **Reportes versionados.**
   Una conformidad digital se asocia a una versión exacta del reporte.

7. **Reglas legales por vigencia.**
   Las reglas aplican según fecha, fuente y versión.

8. **Trazabilidad de fuente.**
   Todo registro relevante debe saber si nació desde web, PWA, kiosco, API, CSV, job o integración.

9. **Auditoría transversal.**
   Las operaciones sensibles quedan registradas.

10. **Preparado para integraciones.**
   El modelo conserva `external_id`, `source`, `idempotency_key` y logs de integración cuando aplique.

---

## 3. Convenciones

## 3.1 Nombres

Las tablas se nombrarán en inglés para alinearse con Laravel y facilitar APIs.

Ejemplos:

```text
companies
workers
time_events
work_days
alerts
incidents
period_reports
```

La documentación funcional puede seguir en español.

## 3.2 Identificadores

Recomendación:

```text
id ULID
```

Motivos:

- Funciona bien en API.
- Evita exponer secuencias simples.
- Es ordenable por tiempo.
- Es compatible con Laravel.
- Facilita logs, imports y trazabilidad.

Si el equipo decide usar `bigint`, deberá agregar un `public_id` ULID/UUID para API. La recomendación del MVP es usar ULID como ID principal en tablas de dominio.

## 3.3 Campos estándar

Tablas operativas principales deberán incluir:

```text
id
company_id
created_at
updated_at
```

Cuando aplique:

```text
deleted_at
created_by
updated_by
source
external_id
metadata
```

## 3.4 Fechas y zona horaria

Para eventos de jornada se deben conservar dos conceptos:

```text
occurred_at_utc       // fecha/hora real normalizada
occurred_local_date   // fecha local operativa
occurred_local_time   // hora local operativa
timezone              // zona horaria usada
received_at           // cuándo lo recibió el sistema
```

Esto evita errores en turnos nocturnos, centros con distintas zonas horarias y eventos recibidos tarde.

## 3.5 Motor de base de datos y JSON

El MVP usará **MySQL 8 / MariaDB compatible**.

Los campos flexibles se documentan como `JSON`. No se usarán tipos binarios de JSON ni índices exclusivos de otro motor.

Cuando se definan índices únicos sobre columnas nullable, se deberá validar el comportamiento específico de MySQL/MariaDB y documentar cualquier restricción adicional en la migración Laravel correspondiente.

---

# 4. Mapa general de módulos y tablas

| Módulo | Tablas principales |
|---|---|
| Tenancy y planes | `companies`, `company_settings`, `plans`, `subscriptions`, `usage_snapshots` |
| Usuarios y permisos | `users`, `company_user`, `roles`, `permissions`, `role_user`, `role_permission` |
| Centros | `centers` |
| Trabajadores | `workers`, `employment_relationships`, `labor_conditions`, `worker_credentials` |
| Horarios | `schedules`, `schedule_days`, `schedule_breaks`, `schedule_assignments`, `mandatory_rest_days` |
| Registro | `time_events`, `devices`, `kiosk_sessions` |
| Motor legal | `legal_rules`, `legal_rule_versions`, `legal_parameters` |
| Cálculos | `work_days`, `work_day_calculations`, `calculation_events` |
| Alertas | `alert_types`, `alerts`, `alert_comments` |
| Incidencias | `incidents`, `incident_comments`, `correction_requests` |
| Cierres | `closing_periods`, `period_reports`, `period_report_versions`, `report_confirmations` |
| Reportes/expedientes | `generated_reports`, `evidence_packages`, `evidence_package_items`, `files` |
| Integraciones | `integration_connections`, `import_batches`, `import_rows`, `export_batches`, `integration_logs` |
| Auditoría | `audit_logs`, `access_logs` |
| Notificaciones | `notifications`, `notification_deliveries` |

No todas las tablas requieren pantalla propia en el MVP, pero síura mínima para operar correctamente.

---

# 5. Tenancy, empresas y planes

## 5.1 `companies`

Representa una empresa cliente del SaaS.

| Campo | Tipo sugerido | Notas |
|---|---|---|
| `id` | ulid pk | Identificador |
| `legal_name` | string | Razón social |
| `trade_name` | string nullable | Nombre comercial |
| `tax_id` | string nullable | RFC |
| `timezone` | string | Zona horaria principal |
| `status` | enum | `trial`, `active`, `suspended`, `cancelled` |
| `plan_id` | ulid nullable | Plan actual |
| `activated_at` | timestamp nullable | Inicio operativo |
| `cancelled_at` | timestamp nullable | Cancelación |
| `metadata` | JSON nullable | Datos adicionales |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(tax_id) // MySQL/MariaDB permite múltiples NULL
index(status)
index(plan_id)
```

## 5.2 `company_settings`

Configuraciones generales de la empresa.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `payroll_period_type` | enum | `weekly`, `biweekly`, `monthly`, `custom` |
| `default_timezone` | string | Zona horaria |
| `default_closure_day` | smallint nullable | Día de cierre |
| `allow_worker_corrections` | boolean | Solicitudes desde portal |
| `require_pin_for_kiosk` | boolean | Kiosco con NIP |
| `require_pin_for_confirmation` | boolean | Confirmaciíal |
| `metadata` | JSON | Config adicional |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id)
```

## 5.3 `plans`

Catálogo de planes comerciales.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `code` | string unique | `compliance`, `operation`, `corporate` |
| `name` | string | Nombre |
| `minimum_monthly_fee` | decimal | Cuota mínima |
| `price_per_active_worker` | decimal | Precio por trabajador activo |
| `status` | enum | `draft`, `active`, `inactive` |
| `features` | JSON | Límites/capacidades |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

## 5.4 `subscriptions`

Suscripción vigente o histórica de una empresa.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `plan_id` | ulid fk | Plan |
| `status` | enum | `trial`, `active`, `past_due`, `suspended`, `cancelled` |
| `starts_at` | date | Inicio |
| `ends_at` | date nullable | Fin |
| `trial_ends_at` | date nullable | Fin de prueba |
| `minimum_workers` | integer nullable | Umbral comercial |
| `price_override` | JSON nullable | Ajustes pactados |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

## 5.5 `usage_snapshots`

Cortes de uso para medición y cobro.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `period_start` | date | Inicio |
| `period_end` | date | Fin |
| `active_workers_count` | integer | Trabajadores activos |
| `centers_count` | integer | Centros |
| `events_count` | integer | Eventos |
| `api_calls_count` | integer | API |
| `calculated_amount` | decimal nullable | Estimación |
| `created_at` | timestamp |  |

Índices:

```text
index(company_id, period_start, period_end)
```

---

# 6. Usuarios, roles y permisos

## 6.1 `users`

Tabla estándar de usuarios Laravel con campos adicionales.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `name` | string | Nombre |
| `email` | string unique nullable | Email |
| `phone` | string nullable | Teléfono |
| `password` | string nullable | Hash |
| `status` | enum | `active`, `inactive`, `blocked` |
| `last_login_at` | timestamp nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

## 6.2 `company_user`

Relación usuario-empresa.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `user_id` | ulid fk | Usuario |
| `status` | enum | `active`, `inactive` |
| `scope` | JSON nullable | Centros/equipos permitidos |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id, user_id)
index(user_id)
```

## 6.3 Roles y permisos

Puede implementarse con tablas propias o con paquete de permisos.

Tablas conceptuales:

```text
roles
permissions
role_user
role_permission
```

Regla:

Los roles deben evaluarse dentro del contexto de empresa.

No basta con un rol global si el usuario puede pertenecer a varias empresas.

---

# 7. Centros de trabajo

## 7.1 `centers`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `code` | string | Código interno |
| `name` | string | Nombre |
| `timezone` | string | Zona horaria |
| `status` | enum | `active`, `inactive` |
| `address` | JSON nullable | Dirección |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id, code)
index(company_id, status)
```

Nota de implementacion Sprint 1B:

```text
La primera migracion de centers usa los identificadores Laravel existentes del proyecto (`id` y `foreignId`) y mantiene:
- company_id obligatorio
- unique(company_id, code)
- index(company_id, status)
- address y metadata como JSON compatibles con MySQL/MariaDB
```

---

# 8. Personas trabajadoras y relación laboral

## 8.1 `workers`

Persona trabajadora dentro de una empresa.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `employee_code` | string | Número/código interno |
| `full_name` | string | Nombre completo |
| `email` | string nullable | Acceso/avisos |
| `phone` | string nullable | Opcional |
| `curp` | string nullable | Opcional |
| `rfc` | string nullable | Opcional |
| `status` | enum | `active`, `inactive`, `terminated`, `suspended` |
| `source` | enum | `web`, `api`, `csv`, `integration` |
| `external_id` | string nullable | ID externo |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id, employee_code)
index(company_id, status)
index(company_id, external_id)
index(company_id, rfc)
```

## 8.2 `employment_relationships`

Relación laboral vigente o histórica.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `worker_id` | ulid fk | Trabajador |
| `center_id` | ulid fk | Centro |
| `position_name` | string nullable | Puesto |
| `started_at` | date | Inicio |
| `ended_at` | date nullable | Baja |
| `status` | enum | `active`, `ended`, `suspended` |
| `source` | enum |  |
| `external_id` | string nullable | ID externo |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(company_id, worker_id, status)
index(company_id, center_id, status)
index(company_id, started_at, ended_at)
```

Regla:

Una persona puede tener varias relaciones históricas, pero normalmente solo una activa por empresa.

Nota de implementacion Sprint 1C:

```text
La primera migracion de trabajadores y relaciones laborales usa los identificadores Laravel existentes del proyecto (`id` y `foreignId`) y mantiene:
- workers.company_id obligatorio
- employment_relationships.company_id obligatorio
- unique(company_id, employee_code)
- indices por company_id, status, worker_id, center_id, external_id y fechas de relacion
- metadata como JSON compatible con MySQL/MariaDB
- baja no destructiva: el trabajador pasa a terminated y la relacion activa pasa a ended con ended_at
- si cambia centro, puesto o started_at, se cierra la relacion activa anterior y se crea una nueva relacion activa
- Sprint 1C usa started_at como fecha efectiva del cambio; BL-0304 debera formalizar condiciones laborales con vigencia

No se crearon en Sprint 1C:
- labor_conditions
- worker_credentials
```

## 8.3 `labor_conditions`

Condiciones laborales con vigencia.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `employment_relationship_id` | ulid fk | Relación |
| `schedule_id` | ulid fk nullable | Horario base |
| `work_modality` | enum | `onsite`, `hybrid`, `remote`, `field` |
| `weekly_hours` | decimal nullable | Jornada pactada |
| `rest_day_of_week` | smallint nullable | 0-6 |
| `policy_id` | ulid nullable | Políica aplicable futura |
| `effective_from` | date | Inicio vigencia |
| `effective_to` | date nullable | Fin |
| `status` | enum | `active`, `inactive`, `replaced` |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(company_id, employment_relationship_id, effective_from, effective_to)
index(company_id, schedule_id)
```

Regla:

No deben existir dos condiciones activas solapadas para la misma relación laboral.

## 8.4 `worker_credentials`

Credenciales para kiosco/portal trabajador.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `worker_id` | ulid fk | Trabajador |
| `pin_hash` | string nullable | NIP seguro |
| `access_code` | string nullable | Código si difiere de employee_code |
| `status` | enum | `active`, `blocked`, `reset_required` |
| `failed_attempts` | integer | Intentos |
| `last_used_at` | timestamp nullable |  |
| `last_changed_at` | timestamp nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id, worker_id)
unique(company_id, access_code) // MySQL/MariaDB permite múltiples NULL
```

Nota de implementacion Sprint 1D:

```text
La primera migracion de condiciones laborales y credenciales usa los identificadores Laravel existentes del proyecto (`id` y `foreignId`) y mantiene:
- labor_conditions.company_id obligatorio
- labor_conditions.employment_relationship_id obligatorio
- schedule_id nullable sin crear schedules ni schedule_assignments
- metadata como JSON compatible con MySQL/MariaDB
- indices por company_id, relacion laboral, fechas, schedule_id y status
- no se sobrescriben condiciones historicas; una nueva condicion activa reemplaza la anterior cerrando su vigencia
- no se permiten condiciones activas solapadas para la misma relacion laboral
- worker_credentials.company_id obligatorio
- worker_credentials.worker_id obligatorio y unico por empresa
- access_code unico por empresa y repetible en empresas distintas
- el NIP solo se guarda como pin_hash con Hash de Laravel
- temporal_pin no existe como columna y se limpia del formulario en exito y error

No se crearon en Sprint 1D:
- schedules
- schedule_assignments
- time_events
- kiosk_sessions
```

---

# 9. Horarios, turnos y descansos

## 9.1 `schedules`

Catálogo de horarios/turnos.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `code` | string | Código |
| `name` | string | Nombre |
| `legal_type` | enum | `diurnal`, `nocturnal`, `mixed`, `variable` |
| `timezone` | string nullable | Si difiere del centro |
| `status` | enum | `active`, `inactive` |
| `effective_from` | date nullable | Vigencia |
| `effective_to` | date nullable | Vigencia |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id, code)
index(company_id, status)
```

## 9.2 `schedule_days`

Días de un horario.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `schedule_id` | ulid fk | Horario |
| `day_of_week` | smallint | 0-6 |
| `is_working_day` | boolean |  |
| `start_time` | time nullable |  |
| `end_time` | time nullable |  |
| `crosses_midnight` | boolean |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(schedule_id, day_of_week)
index(company_id, schedule_id)
```

## 9.3 `schedule_breaks`

Pausas programadas del horario.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `schedule_day_id` | ulid fk | Día |
| `name` | string nullable | Comida, descanso |
| `start_time` | time nullable | Opcional |
| `end_time` | time nullable | Opcional |
| `duration_minutes` | integer nullable | Si no hay hora fija |
| `is_paid` | boolean | Computable |
| `is_required` | boolean | Obligatoria |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Nota de implementacion Sprint 2A:

```text
La primera migracion de horarios base usa los identificadores Laravel existentes del proyecto (`id` y `foreignId`) y mantiene:
- schedules.company_id obligatorio
- unique(company_id, code)
- index(company_id, status)
- metadata como JSON compatible con MySQL/MariaDB
- schedule_days.company_id obligatorio
- schedule_days.schedule_id obligatorio
- unique(schedule_id, day_of_week)
- index(company_id, schedule_id)
- schedule_breaks.company_id obligatorio
- schedule_breaks.schedule_day_id obligatorio
- index(company_id, schedule_day_id)
- duration_minutes positivo cuando se proporciona desde formulario/Action
- selectedDay y saveBreak deben pertenecer al horario actualmente editado

No se crearon en Sprint 2A:
- schedule_assignments
- mandatory_rest_days
- time_events
- kiosk_sessions
- work_days
```

## 9.4 `schedule_assignments`

Asignación de horarios a una persona trabajadora, con vigencia por fecha efectiva e historial no destructivo.

Implementación inicial Sprint 2B:

- La asignación pertenece siempre a una empresa por `company_id`.
- La asignación referencia `worker_id` y `schedule_id`.
- `employment_relationship_id` es nullable para ligar la asignación a una relación laboral cuando aplique.
- `effective_from` marca el inicio de vigencia.
- `effective_to` marca el fin de vigencia; `null` significa rango abierto.
- `status` puede ser `active`, `inactive` o `replaced`.
- `source` conserva el origen inicial, por ahora `web`.
- `metadata` permite datos complementarios en JSON.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint pk | Identificador |
| `company_id` | bigint fk | Empresa; no debe usar borrado destructivo |
| `worker_id` | bigint fk | Persona trabajadora; no debe usar borrado destructivo |
| `employment_relationship_id` | bigint fk nullable | Relación laboral asociada; no debe usar borrado destructivo |
| `schedule_id` | bigint fk | Horario; no debe usar borrado destructivo |
| `effective_from` | date | Inicio de vigencia |
| `effective_to` | date nullable | Fin de vigencia; `null` = vigente abierto |
| `status` | string | `active`, `inactive`, `replaced` |
| `source` | string nullable | Origen del registro |
| `metadata` | JSON nullable | Datos complementarios |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Reglas de historial:

- No se debe hacer hard delete de asignaciones históricas.
- Las FK de `company_id`, `worker_id`, `employment_relationship_id` y `schedule_id` deben preservar historial; en Sprint 2B se usa `restrictOnDelete`.
- Reemplazar una asignación cierra la vigencia anterior y crea una nueva.
- Inactivar una asignación cambia estado, no elimina el registro.

Índices:

```text
index(company_id, worker_id)
index(company_id, schedule_id)
index(company_id, effective_from, effective_to)
```

## 9.5 `mandatory_rest_days`

Descansos obligatorios aplicables por fecha. Sprint 2C implementa este catalogo como configuracion, sin generar eventos, calculos de jornada ni reglas legales automaticas.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint pk | Identificador Laravel |
| `company_id` | bigint nullable fk | Null si el descanso es global |
| `center_id` | bigint nullable fk | Solo aplica para alcance `center` |
| `name` | string | Nombre del descanso |
| `date` | date | Fecha del descanso |
| `scope` | string | `global`, `company`, `center` |
| `source` | string nullable | Fuente o captura manual |
| `status` | string | `active`, `inactive` |
| `metadata` | JSON nullable | Datos auxiliares compatibles con MySQL/MariaDB |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Indices:

```text
index(date)
index(scope, date)
index(company_id, date)
index(company_id, center_id, date)
index(scope, company_id, center_id, date)
```

Reglas de persistencia:

```text
company_id y center_id usan restrictOnDelete para evitar borrado destructivo de historial.
La inactivacion conserva el registro con status inactive.
La unicidad por alcance, fecha y nombre se valida en Actions para evitar depender de indices unique con columnas nullable en MySQL/MariaDB.
No se define un indice unique nullable como garantia principal porque MySQL/MariaDB permite multiples NULL y podria no bloquear duplicados por alcance.
```

---
# 10. Registro electrónico

## 10.1 `devices`

Dispositivos, kioscos o fuentes identificables.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `center_id` | ulid nullable | Centro |
| `code` | string | Código |
| `name` | string | Nombre |
| `type` | enum | `web`, `pwa`, `kiosk`, `api_client`, `external_clock` |
| `status` | enum | `active`, `inactive`, `blocked` |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id, code)
index(company_id, center_id)
```

## 10.2 `time_events`

Eventos fuente de jornada. Sprint 2D implementa el modelo interno. Sprint 2E agrega registro web basico para entrada, salida e inicio/fin de pausa usando `time_events`, sin kiosco operativo, captura manual, calculos, alertas, incidencias ni reportes.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint pk | Identificador Laravel |
| `company_id` | bigint fk | Empresa activa |
| `worker_id` | bigint fk | Trabajador |
| `employment_relationship_id` | bigint nullable fk | Relacion laboral aplicable, si se conoce |
| `center_id` | bigint nullable fk | Centro, si se conoce |
| `device_id` | bigint nullable | Reservado para `devices`; sin FK en Sprint 2D porque `devices` aun no existe |
| `event_type` | string | `clock_in`, `clock_out`, `break_start`, `break_end`, `manual_entry`, `logical_void` |
| `occurred_at_utc` | dateTime | Fecha/hora normalizada en UTC |
| `occurred_local_date` | date | Fecha local operativa |
| `occurred_local_time` | time | Hora local operativa conservada como `H:i:s` |
| `timezone` | string | Zona horaria usada para normalizar |
| `received_at` | dateTime | Fecha/hora de recepcion |
| `source` | string | `web`, `pwa`, `kiosk`, `api`, `csv`, `admin_manual`, `job`, `integration` |
| `source_user_id` | bigint nullable fk | Usuario fuente, si aplica |
| `external_id` | string nullable | ID externo |
| `idempotency_key` | string nullable | Llave de idempotencia |
| `status` | string | `valid`, `pending_review`, `voided`, `replaced`, `ignored` |
| `metadata` | JSON nullable | Payload auxiliar compatible con MySQL/MariaDB |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Indices:

```text
index(company_id)
index(company_id, worker_id, occurred_at_utc)
index(company_id, worker_id, occurred_local_date)
index(company_id, center_id, occurred_local_date)
index(company_id, status)
unique(company_id, source, external_id) // MySQL/MariaDB permite multiples NULL
unique(company_id, idempotency_key) // MySQL/MariaDB permite multiples NULL
```

Reglas:

- `time_events` es fuente primaria no destructiva de los eventos de jornada.
- No eliminacion fisica ordinaria desde dominio.
- FKs de empresa, trabajador, relacion laboral, centro y usuario fuente usan restricciones que preservan historial.
- `device_id` queda nullable y sin FK hasta que exista el modulo `devices`.
- La idempotencia se valida en `CreateTimeEventAction` para no depender solo de indices unique con valores NULL.
- `occurred_local_time` se expone en el modelo como hora local operativa en formato `H:i:s`.
- Estados `voided`, `replaced` e `ignored` existen como valores posibles, pero sus flujos deben implementarse en Actions especificas cuando llegue `BL-0506`.
- Cuando lleguen API/CSV, deben reutilizar `CreateTimeEventAction` sin duplicar normalizacion de timezone ni reglas de idempotencia.
- Sprint 2E valida una secuencia operativa minima para registro web: entrada, salida, inicio de pausa y fin de pausa. No calcula jornada, no crea `work_days`, no genera alertas ni incidencias.
- En Sprint 2E, los eventos web se crean con source `web`, hora actual del sistema segun zona horaria aplicable y sin aceptar fecha u hora explicita desde la interfaz.
- El registro web debe usar `RegisterWebTimeEventAction`, que orquesta `CreateTimeEventAction` para conservar normalizacion de timezone, fuente `web`, estado `valid`, `received_at` y metadata minima no sensible.
## 10.3 `kiosk_sessions`

Sesiones de kiosco cuando se necesite trazabilidad adicional.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `device_id` | ulid fk | Kiosco |
| `worker_id` | ulid nullable | Trabajador autenticado |
| `started_at` | timestamp | Inicio |
| `ended_at` | timestamp nullable | Fin |
| `status` | enum | `started`, `completed`, `failed`, `expired` |
| `ip_address` | string nullable |  |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |

---

# 11. Reglas legales

## 11.1 `legal_rules`

Catálogo de reglas.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `code` | string unique | Código |
| `name` | string | Nombre |
| `description` | text nullable | Descripción |
| `category` | enum | `daily_limit`, `weekly_limit`, `overtime`, `break`, `rest_day`, `sunday`, `closure` |
| `status` | enum | `active`, `inactive` |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Ejemplos de `code`:

```text
maximum_weekly_hours
daily_limit_diurnal
daily_limit_nocturnal
daily_limit_mixed
minimum_continuous_break
overtime_art66_limit
overtime_art68_extra_limit
```

## 11.2 `legal_rule_versions`

Versiones vigentes por fecha.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `legal_rule_id` | ulid fk | Regla |
| `version` | integer | Versión |
| `value` | decimal/string/JSON | Valor |
| `unit` | string | `hours`, `minutes`, `percent`, `boolean`, `json` |
| `source_reference` | text nullable | Fuente |
| `effective_from` | date | Inicio |
| `effective_to` | date nullable | Fin |
| `status` | enum | `draft`, `reviewed`, `scheduled`, `active`, `replaced`, `inactive` |
| `notes` | text nullable |  |
| `created_by` | ulid nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(legal_rule_id, effective_from, effective_to)
unique(legal_rule_id, version)
```

## 11.3 `legal_parameters`

Parámetros derivados o configuraciones complementarias.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid nullable | Null si es global |
| `code` | string | Código |
| `value` | JSON | Valor |
| `effective_from` | date | Inicio |
| `effective_to` | date nullable | Fin |
| `status` | enum | `active`, `inactive` |
| `source_reference` | text nullable | Fuente |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Uso:

- Condiciones más favorables.
- Parámetros de cierre.
- Reglas internas por empresa.
- Ajustes específicos por política.

---

# 12. Jornadas calculadas

## 12.1 `work_days`

Representa la jornada operativa de una persona en una fecha local.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `worker_id` | ulid fk | Trabajador |
| `employment_relationship_id` | ulid nullable | Relación |
| `center_id` | ulid nullable | Centro |
| `schedule_id` | ulid nullable | Horario |
| `work_date` | date | Fecha local operativa |
| `timezone` | string | Zona horaria |
| `status` | enum | `pending`, `calculated`, `with_alerts`, `under_review`, `closed` |
| `active_calculation_id` | ulid nullable | Versión activa |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id, worker_id, work_date)
index(company_id, work_date)
index(company_id, center_id, work_date)
index(company_id, status)
```

## 12.2 `work_day_calculations`

Versiones de cálculo de una jornada.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `work_day_id` | ulid fk | Jornada |
| `version` | integer | Versión incremental |
| `status` | enum | `draft`, `active`, `superseded`, `invalidated` |
| `calculated_at` | timestamp | Fecha |
| `generated_by_type` | string | `system`, `user`, `job` |
| `generated_by_id` | ulid nullable | Actor |
| `reason` | text nullable | Motivo |
| `total_work_minutes` | integer | Total trabajado |
| `ordinary_minutes` | integer | Ordinario |
| `night_minutes` | integer | Nocturno |
| `overtime_minutes` | integer | Extra total |
| `break_minutes` | integer | Pausas |
| `paid_break_minutes` | integer | Pausa computable |
| `sunday_minutes` | integer | Domingo |
| `mandatory_rest_minutes` | integer | Descanso obligatorio |
| `classification` | enum | `diurnal`, `nocturnal`, `mixed`, `pending` |
| `rules_snapshot` | JSON | Reglas aplicadas |
| `inputs_snapshot` | JSON | Condiciones/eventos resumidos |
| `result_snapshot` | JSON | Resultado completo |
| `explanation` | JSON | Explicación legible |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(work_day_id, version)
index(company_id, calculated_at)
index(company_id, status)
```

## 12.3 `calculation_events`

Eventos usados por un cálculo.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `work_day_calculation_id` | ulid fk | Cálculo |
| `time_event_id` | ulid fk | Evento |
| `role` | enum | `input`, `ignored`, `corrected`, `voided` |
| `created_at` | timestamp |  |

Índices:

```text
unique(work_day_calculation_id, time_event_id)
index(company_id, time_event_id)
```

---

# 13. Alertas preventivas

## 13.1 `alert_types`

Catálogo de tipos de alerta.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `code` | string unique | Código |
| `name` | string | Nombre |
| `description` | text | Descripción |
| `default_severity` | enum | `informational`, `warning`, `high`, `critical` |
| `category` | enum | `event`, `daily`, `weekly`, `break`, `rest`, `closure`, `integration` |
| `status` | enum | `active`, `inactive` |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Códigos mínimos:

```text
missing_clock_in
missing_clock_out
duplicate_event
incomplete_work_day
daily_limit_exceeded
weekly_limit_exceeded
overtime_detected
twelve_hours_exceeded
insufficient_break
six_consecutive_days
sunday_work
mandatory_rest_work
invalid_schedule
inactive_relationship
pending_correction
calculated_vs_authorized_difference
authorized_vs_exported_difference
period_report_difference
```

## 13.2 `alerts`

Alertas generadas.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `alert_type_id` | ulid fk | Tipo |
| `worker_id` | ulid nullable | Trabajador |
| `work_day_id` | ulid nullable | Jornada |
| `work_day_calculation_id` | ulid nullable | Cálculo |
| `closing_period_id` | ulid nullable | Periodo |
| `severity` | enum | `informational`, `warning`, `high`, `critical` |
| `status` | enum | `new`, `in_review`, `pending_information`, `justified`, `corrected`, `closed` |
| `title` | string | Tíulo |
| `description` | text | Descripción general |
| `rule_code` | string nullable | Regla relacionada |
| `detected_at` | timestamp | Fecha |
| `assigned_to` | ulid nullable | Responsable |
| `due_at` | timestamp nullable | Fecha objetivo |
| `resolution` | text nullable | Resolución |
| `resolved_by` | ulid nullable | Usuario |
| `resolved_at` | timestamp nullable | Fecha |
| `fingerprint` | string | Huella para evitar duplicados |
| `metadata` | JSON nullable | Detalles |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(company_id, fingerprint)
index(company_id, status, severity)
index(company_id, worker_id, detected_at)
index(company_id, work_day_id)
index(company_id, assigned_to, status)
```

## 13.3 `alert_comments`

Comentarios o seguimiento de alerta.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `alert_id` | ulid fk | Alerta |
| `user_id` | ulid nullable | Autor |
| `comment` | text | Comentario |
| `created_at` | timestamp |  |

---

# 14. Incidencias y correcciones

## 14.1 `incidents`

Caso gestionable.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `worker_id` | ulid nullable | Trabajador |
| `work_day_id` | ulid nullable | Jornada |
| `alert_id` | ulid nullable | Alerta origen |
| `period_report_id` | ulid nullable | Reporte relacionado |
| `type` | enum | `missing_event`, `wrong_event`, `break`, `schedule`, `overtime`, `technical`, `worker_request`, `other` |
| `status` | enum | `open`, `in_review`, `approved`, `rejected`, `in_dispute`, `closed` |
| `title` | string | Tíulo |
| `description` | text | Descripción |
| `opened_by` | ulid nullable | Usuario |
| `assigned_to` | ulid nullable | Responsable |
| `opened_at` | timestamp | Fecha |
| `closed_at` | timestamp nullable | Cierre |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(company_id, worker_id, status)
index(company_id, work_day_id)
index(company_id, alert_id)
index(company_id, assigned_to, status)
```

## 14.2 `incident_comments`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `incident_id` | ulid fk | Incidencia |
| `user_id` | ulid nullable | Autor |
| `comment` | text | Comentario |
| `visibility` | enum | `internal`, `worker_visible` |
| `created_at` | timestamp |  |

## 14.3 `correction_requests`

Correcciones propuestas/aprobadas.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `incident_id` | ulid fk | Incidencia |
| `target_type` | string | Entidad objetivo |
| `target_id` | ulid nullable | ID objetivo |
| `correction_type` | enum | `add_event`, `void_event`, `replace_event`, `change_schedule`, `adjust_result` |
| `original_value` | JSON nullable | Valor anterior |
| `proposed_value` | JSON | Valor propuesto |
| `status` | enum | `pending`, `approved`, `rejected`, `applied`, `cancelled` |
| `requested_by` | ulid nullable | Solicitante |
| `approved_by` | ulid nullable | Aprobador |
| `applied_by` | ulid nullable | Aplicador |
| `requested_at` | timestamp |  |
| `approved_at` | timestamp nullable |  |
| `applied_at` | timestamp nullable |  |
| `reason` | text nullable | Motivo |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Regla:

Toda corrección aprobada que afecte cálculo genera nueva versión de cálculo y, si aplica, nueva versión del reporte de periodo.

---

# 15. Cierre de periodo y conformidad digital

## 15.1 `closing_periods`

Periodo operativo de cierre.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `center_id` | ulid nullable | Centro, si aplica |
| `period_type` | enum | `weekly`, `biweekly`, `monthly`, `custom` |
| `period_start` | date | Inicio |
| `period_end` | date | Fin |
| `status` | enum | `calculating`, `with_alerts`, `admin_review`, `available_for_worker_review`, `closed`, `cancelled` |
| `created_by` | ulid nullable | Usuario |
| `closed_by` | ulid nullable | Usuario |
| `closed_at` | timestamp nullable | Fecha |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(company_id, period_start, period_end)
index(company_id, status)
index(company_id, center_id, period_start)
```

## 15.2 `period_reports`

Reporte individual del trabajador en un periodo.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `closing_period_id` | ulid fk | Periodo |
| `worker_id` | ulid fk | Trabajador |
| `status` | enum | `draft`, `available`, `conformant`, `non_conformant`, `pending_review`, `in_clarification`, `closed` |
| `active_version_id` | ulid nullable | Versión activa |
| `first_available_at` | timestamp nullable |  |
| `closed_at` | timestamp nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(closing_period_id, worker_id)
index(company_id, worker_id, status)
index(company_id, closing_period_id, status)
```

## 15.3 `period_report_versions`

Versiones del reporte individual.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `period_report_id` | ulid fk | Reporte |
| `version` | integer | Versión |
| `status` | enum | `active`, `superseded`, `invalidated` |
| `generated_at` | timestamp | Fecha |
| `generated_by` | ulid nullable | Usuario/proceso |
| `reason` | text nullable | Motivo |
| `summary` | JSON | Totales |
| `details` | JSON | Detalle |
| `alerts_snapshot` | JSON | Alertas al momento |
| `hash` | string | Hash de integridad |
| `file_id` | ulid nullable | PDF generado |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
unique(period_report_id, version)
index(company_id, hash)
```

## 15.4 `report_confirmations`

Conformidad o no conformidad.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `period_report_id` | ulid fk | Reporte |
| `period_report_version_id` | ulid fk | Versión |
| `worker_id` | ulid fk | Trabajador |
| `result` | enum | `conformant`, `non_conformant`, `pending` |
| `accepted_text` | text nullable | Texto mostrado |
| `comment` | text nullable | Comentario |
| `confirmed_at` | timestamp nullable | Fecha |
| `auth_method` | enum | `session`, `pin`, `email_code`, `admin_recorded` |
| `ip_address` | string nullable | Auxiliar |
| `user_agent` | text nullable | Auxiliar |
| `metadata` | JSON nullable | OTP, dispositivo, etc. |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(company_id, worker_id, confirmed_at)
index(company_id, period_report_version_id)
```

Regla:

La conformidad aplica solo a la versión relacionada. Si nace una nueva versión, requiere nueva revisión.

---

# 16. Reportes, expedientes y archivos

## 16.1 `files`

Registro central de archivos.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `owner_type` | string nullable | Entidad propietaria |
| `owner_id` | ulid nullable | ID |
| `disk` | string | Disco |
| `path` | string | Ruta |
| `filename` | string | Nombre |
| `mime_type` | string nullable | MIME |
| `size_bytes` | bigint nullable | Tamaño |
| `hash` | string nullable | Integridad |
| `category` | enum | `evidence`, `report`, `export`, `import`, `manifest`, `other` |
| `visibility` | enum | `private`, `worker_visible`, `public_link` |
| `created_by` | ulid nullable | Usuario |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(company_id, owner_type, owner_id)
index(company_id, category)
index(company_id, hash)
```

## 16.2 `generated_reports`

Reportes operativos generados.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `report_type` | enum | `daily`, `weekly`, `period`, `alerts`, `incidents`, `overtime`, `conformity` |
| `status` | enum | `queued`, `processing`, `completed`, `failed`, `expired` |
| `requested_by` | ulid nullable | Usuario |
| `parameters` | JSON | Filtros |
| `file_id` | ulid nullable | Archivo |
| `started_at` | timestamp nullable |  |
| `finished_at` | timestamp nullable |  |
| `error_message` | text nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

## 16.3 `evidence_packages`

Expediente delimitado.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `scope_type` | enum | `worker`, `center`, `period`, `request` |
| `scope` | JSON | Alcance |
| `status` | enum | `queued`, `processing`, `ready`, `failed`, `expired` |
| `requested_by` | ulid nullable | Usuario |
| `file_id` | ulid nullable | ZIP/PDF |
| `manifest_hash` | string nullable | Hash |
| `generated_at` | timestamp nullable | Fecha |
| `expires_at` | timestamp nullable | Expiración |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

## 16.4 `evidence_package_items`

Elementos incluidos en expediente.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `evidence_package_id` | ulid fk | Expediente |
| `item_type` | string | Tipo entidad |
| `item_id` | ulid nullable | ID |
| `file_id` | ulid nullable | Archivo |
| `hash` | string nullable | Hash |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |

Índices:

```text
index(company_id, evidence_package_id)
index(company_id, item_type, item_id)
```

---

# 17. Importaciones, exportaciones e integraciones

## 17.1 `integration_connections`

Conexiones externas por empresa.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `provider` | enum/string | `clickbalance`, `custom_api`, `clock`, etc. |
| `name` | string | Nombre |
| `status` | enum | `draft`, `active`, `inactive`, `error` |
| `direction` | enum | `inbound`, `outbound`, `bidirectional` |
| `credentials` | encrypted JSON nullable | Secretos |
| `settings` | JSON nullable | Config |
| `last_sync_at` | timestamp nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(company_id, provider, status)
```

## 17.2 `import_batches`

Lotes de importación.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `type` | enum | `workers`, `schedules`, `time_events` |
| `source` | enum | `csv`, `api`, `integration` |
| `status` | enum | `uploaded`, `processing`, `completed`, `completed_with_errors`, `failed` |
| `file_id` | ulid nullable | Archivo origen |
| `total_rows` | integer | Total |
| `success_rows` | integer | Correctas |
| `error_rows` | integer | Errores |
| `requested_by` | ulid nullable | Usuario |
| `started_at` | timestamp nullable |  |
| `finished_at` | timestamp nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

## 17.3 `import_rows`

Resultado por fila.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `import_batch_id` | ulid fk | Lote |
| `row_number` | integer | Fila |
| `status` | enum | `pending`, `processed`, `error`, `skipped` |
| `raw_data` | JSON | Datos originales |
| `normalized_data` | JSON nullable | Datos normalizados |
| `target_type` | string nullable | Entidad creada |
| `target_id` | ulid nullable | ID |
| `error_messages` | JSON nullable | Errores |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Índices:

```text
index(company_id, import_batch_id, status)
```

## 17.4 `export_batches`

Exportaciones de prenómina u otras salidas.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `type` | enum | `payroll`, `clickbalance_file`, `custom_csv`, `evidence` |
| `status` | enum | `queued`, `processing`, `ready`, `sent`, `failed` |
| `parameters` | JSON | Filtros |
| `file_id` | ulid nullable | Archivo |
| `generated_by` | ulid nullable | Usuario |
| `generated_at` | timestamp nullable |  |
| `sent_at` | timestamp nullable |  |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

## 17.5 `integration_logs`

Logs técnicos de integración.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid fk | Empresa |
| `integration_connection_id` | ulid nullable | Conexión |
| `direction` | enum | `inbound`, `outbound` |
| `operation` | string | Operación |
| `status` | enum | `success`, `failed`, `retrying` |
| `request_payload` | JSON nullable | Sanitizado |
| `response_payload` | JSON nullable | Sanitizado |
| `external_id` | string nullable | ID externo |
| `trace_id` | string nullable | Rastreo |
| `error_message` | text nullable | Error |
| `created_at` | timestamp |  |

Índices:

```text
index(company_id, integration_connection_id, created_at)
index(company_id, status, created_at)
index(company_id, trace_id)
```

---

# 18. Auditoría y accesos

## 18.1 `audit_logs`

Bitácora de operaciones sensibles.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid nullable | Empresa |
| `user_id` | ulid nullable | Usuario |
| `actor_type` | string | `user`, `worker`, `api_client`, `system` |
| `actor_id` | ulid/string nullable | Actor |
| `action` | string | Acción |
| `auditable_type` | string | Entidad |
| `auditable_id` | ulid nullable | ID |
| `old_values` | JSON nullable | Antes |
| `new_values` | JSON nullable | Después |
| `reason` | text nullable | Motivo |
| `source` | enum/string | Origen |
| `ip_address` | string nullable | IP |
| `user_agent` | text nullable | UA |
| `trace_id` | string nullable | Trace |
| `created_at` | timestamp |  |

Índices:

```text
index(company_id, auditable_type, auditable_id)
index(company_id, user_id, created_at)
index(company_id, action, created_at)
index(trace_id)
```

## 18.2 `access_logs`

Registro de accesos relevantes.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid nullable | Empresa |
| `user_id` | ulid nullable | Usuario |
| `event` | enum | `login`, `logout`, `failed_login`, `api_token_used`, `support_access` |
| `ip_address` | string nullable | IP |
| `user_agent` | text nullable | UA |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |

---

# 19. Notificaciones

## 19.1 `notifications`

Notificaciones internas.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `company_id` | ulid nullable | Empresa |
| `recipient_type` | string | Usuario/trabajador |
| `recipient_id` | ulid | ID |
| `type` | string | Tipo |
| `title` | string | Tíulo |
| `body` | text | Mensaje |
| `data` | JSON nullable | Datos |
| `status` | enum | `pending`, `sent`, `read`, `failed` |
| `created_at` | timestamp |  |
| `read_at` | timestamp nullable |  |

## 19.2 `notification_deliveries`

Intentos por canal.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | ulid pk |  |
| `notification_id` | ulid fk | Notificación |
| `channel` | enum | `database`, `email`, `sms`, `webhook` |
| `status` | enum | `pending`, `sent`, `failed` |
| `sent_at` | timestamp nullable |  |
| `error_message` | text nullable |  |
| `metadata` | JSON nullable |  |
| `created_at` | timestamp |  |

---

# 20. Estados principales

## 20.1 Empresa

```text
trial
active
suspended
cancelled
```

## 20.2 Trabajador

```text
active
inactive
terminated
suspended
```

## 20.3 Evento de jornada

```text
valid
pending_review
voided
replaced
ignored
```

## 20.4 Jornada calculada

```text
pending
calculated
with_alerts
under_review
closed
```

## 20.5 Cálculo

```text
draft
active
superseded
invalidated
```

## 20.6 Alerta

```text
new
in_review
pending_information
justified
corrected
closed
```

## 20.7 Incidencia

```text
open
in_review
approved
rejected
in_dispute
closed
```

## 20.8 Corrección

```text
pending
approved
rejected
applied
cancelled
```

## 20.9 Cierre

```text
calculating
with_alerts
admin_review
available_for_worker_review
closed
cancelled
```

## 20.10 Reporte individual

```text
draft
available
conformant
non_conformant
pending_review
in_clarification
closed
```

---

# 21. Relaciones críticas

## 21.1 Empresa → datos operativos

```text
companies
  -,, centers
  -,, workers
  -,, schedules
  -,, time_events
  -,, work_days
  -,, alerts
  -,, incidents
  -,, closing_periods
  -,, period_reports
  →,, files
```

## 21.2 Trabajador → jornada

```text
workers
  →,, employment_relationships
        →,, labor_conditions
              →,, schedules

workers
  →,, time_events
        →,, work_days
              →,, work_day_calculations
                    →,, alerts
```

## 21.3 Correcciones

```text
alerts
  →,, incidents
        →,, correction_requests
              →,, time_events / work_day_calculations / period_report_versions
```

## 21.4 Cierre y conformidad

```text
closing_periods
  →,, period_reports
        →,, period_report_versions
              →,, report_confirmations
```

## 21.5 Expediente

```text
evidence_packages
  →,, evidence_package_items
        -,, files
        -,, time_events
        -,, work_day_calculations
        -,, alerts
        -,, incidents
        →,, period_report_versions
```

---

# 22. Índices obligatorios del MVP

## 22.1 Multi-tenant

Toda tabla operativa de alto volumen deberá tener índice por empresa:

```text
index(company_id)
```

## 22.2 Eventos

```text
index(company_id, worker_id, occurred_at_utc)
index(company_id, worker_id, occurred_local_date)
index(company_id, center_id, occurred_local_date)
unique(company_id, source, external_id) // MySQL/MariaDB permite múltiples NULL
unique(company_id, idempotency_key) // MySQL/MariaDB permite múltiples NULL
```

## 22.3 Jornadas

```text
unique(company_id, worker_id, work_date)
index(company_id, work_date)
index(company_id, status)
```

## 22.4 Alertas

```text
unique(company_id, fingerprint)
index(company_id, status, severity)
index(company_id, worker_id, detected_at)
```

## 22.5 Incidencias

```text
index(company_id, worker_id, status)
index(company_id, assigned_to, status)
```

## 22.6 Reportes

```text
unique(closing_period_id, worker_id)
unique(period_report_id, version)
index(company_id, worker_id, status)
```

## 22.7 Auditoría

```text
index(company_id, auditable_type, auditable_id)
index(company_id, action, created_at)
index(trace_id)
```

---

# 23. Reglas de integridad

## 23.1 No eliminar físicamente

No se eliminarán físicamente en operación ordinaria:

- `workers`
- `employment_relationships`
- `time_events`
- `work_day_calculations`
- `alerts`
- `incidents`
- `correction_requests`
- `period_report_versions`
- `report_confirmations`
- `audit_logs`
- `evidence_packages`

Se usarán estados, anulación lógica o versionamiento.

## 23.2 No editar versiones firmadas

Una versión de reporte con conformidad o no conformidad no se modifica.

Si cambia información:

```text
period_report_versions v1
→ se conserva

period_report_versions v2
→ nueva revisión
```

## 23.3 No solapar vigencias

No deben solaparse vigencias activas para:

- Condiciones laborales.
- Asignaciones de horario.
- Reglas legales.
- Parámetros legales por empresa.

## 23.4 Reglas legales aplicadas

Cada cálculo debe guardar snapshot de reglas aplicadas.

No basta con consultar la regla actual al momento de ver un reporte histórico.

## 23.5 Fuente obligatoria

Registros creados desde canales relevantes deberán conservar:

```text
source
external_id o idempotency_key cuando aplique
trace_id cuando aplique
```

## 23.6 Consistencia API-web

Un registro creado por API debe cumplir las mismas relaciones y validaciones que un registro creado por interfaz.

---

# 24. Estrategia de migraciones

Orden sugerido:

```text
001_users_and_auth
002_plans_companies_settings
003_roles_permissions_company_user
004_centers
005_workers_relationships_conditions_credentials
006_schedules_breaks_assignments_rest_days
007_devices_time_events
008_legal_rules_versions_parameters
009_work_days_calculations_calculation_events
010_alert_types_alerts_comments
011_incidents_comments_correction_requests
012_closing_periods_reports_versions_confirmations
013_files_generated_reports_evidence_packages
014_integrations_imports_exports_logs
015_audit_access_logs
016_notifications
```

---

# 25. Tablas P0 vs P1

## 25.1 P0 obligatorio

```text
companies
company_settings
plans
subscriptions
users
company_user
roles
permissions
centers
workers
employment_relationships
labor_conditions
worker_credentials
schedules
schedule_days
schedule_breaks
schedule_assignments
mandatory_rest_days
devices
time_events
legal_rules
legal_rule_versions
legal_parameters
work_days
work_day_calculations
calculation_events
alert_types
alerts
incidents
incident_comments
correction_requests
closing_periods
period_reports
period_report_versions
report_confirmations
files
generated_reports
evidence_packages
evidence_package_items
integration_connections
import_batches
import_rows
export_batches
integration_logs
audit_logs
access_logs
```

## 25.2 P1

```text
notifications
notification_deliveries
kiosk_sessions
usage_snapshots avanzado
integraciones específicas avanzadas
```

Nota: `kiosk_sessions` puede omitirse inicialmente si `time_events` conserva suficiente trazabilidad del kiosco.

---

# 26. Pendientes de decisión

Antes de crear migraciones finales, se deben confirmar:

1. ¿Se usará ULID como llave primaria en todo el dominio?
2. ¿Se usará paquete de roles/permisos o implementación propia?
3. ¿Los usuarios trabajadores vivirán en `users` o se autenticarán mediante `worker_credentials` separadas?
4. ¿El portal del trabajador usará cuenta completa o acceso por código/NIP?
5. ¿El modo kiosco tendrá sesión propia o solo eventos con fuente `kiosk`?
6. ¿Qué formato exacto de archivo requiere ClickBalance para horas e incidencias?
7. ¿Cuánta metadata se conservará en eventos API?
8. ¿Qué reportes PDF se generarán físicamente y cuáles serán dinámicos?
9. ¿Qué política de retención aplicará a archivos y expedientes?
10. ¿Qué tablas requieren particionamiento futuro por volumen?

---

# 27. Recomendación técnica inicial

Para avanzar rápido sin comprometer arquitectura:

1. Usar `id` ULID en tablas de dominio.
2. Usar `company_id` obligatorio en tablas operativas.
3. Mantener `time_events` como fuente primaria inmutable.
4. Versionar `work_day_calculations`.
5. Versionar `period_report_versions`.
6. Usar `files` como tabla central de archivos.
7. Usar `audit_logs` transversal.
8. Implementar idempotencia desde el primer endpoint de eventos.
9. No construir lógica en Livewire sin Action/Service reutilizable.
10. Diseñar migraciones por módulos, no una migración única.

---

# 28. Siguiente documento

Después de revisar este modelo de datos, el siguiente documento recomendado será:

```text
docs/06-UX/UX-0001-MAPA-DE-PANTALLAS-MVP.md
```

Ese documento definirá:

- Pantallas principales.
- Flujos por rol.
- Portal trabajador.
- Kiosco.
- Cierre de periodo.
- Alertas.
- Incidencias.
- Reportes.
- Importaciones.
- Administración.
