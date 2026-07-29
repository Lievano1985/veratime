---
id: AI-0004
title: Modelo de datos y multi-tenant para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - base-datos
  - mysql
  - multi-tenant
  - veratime
---

# AI-0004 — Modelo de datos y multi-tenant para Codex

## 1. Base de datos aprobada

Usar:

```text
MySQL 8 / MariaDB compatible
```

No usar PostgreSQL ni características exclusivas de PostgreSQL.

---

## 2. Regla multi-tenant

Toda entidad operativa debe tener:

```text
company_id
```

Ejemplos:

- centers;
- workers;
- employment_relationships;
- labor_conditions;
- schedules;
- schedule_assignments;
- time_events;
- work_days;
- work_day_calculations;
- alerts;
- incidents;
- closing_periods;
- period_reports;
- files;
- imports;
- exports;
- audit_logs.

---

## 3. Tablas base esperadas

```text
companies
company_settings
plans
subscriptions
usage_snapshots
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
kiosk_sessions
legal_rules
legal_rule_versions
legal_parameters
work_days
work_day_calculations
calculation_events
alert_types
alerts
alert_comments
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
notifications
notification_deliveries
```

---

## 4. Reglas de integridad

## 4.1 No borrar historial laboral

No hacer hard delete ordinario en:

- trabajadores;
- relaciones laborales;
- condiciones;
- eventos;
- cálculos;
- reportes;
- conformidades;
- incidencias.

Usar estados, vigencias o soft delete solo cuando sea correcto.

---

## 4.1.1 Regla de evidencia operativa

Aplicar `docs/12-Decisiones/ADR-0004-REGLA-DE-EVIDENCIA-OPERATIVA.md`.

La evidencia protegida es el resultado operativo: horarios diarios publicados, snapshots, correcciones versionadas, eventos de asistencia y futuros `work_days`, calculos, cierres, conformidad, reportes y expedientes.

Catalogos, relaciones laborales, asignaciones organizacionales, perfiles y asignaciones de perfiles son datos intermedios mientras no hayan generado evidencia protegida.

No recalcular ni sobrescribir horarios publicados por cambios posteriores en esos datos. Si la fecha ya esta publicada, cambiar el resultado exige correccion versionada de programacion diaria.

---

## 4.2 Índices mínimos

Agregar índices por:

```text
company_id
company_id + worker_id
company_id + date
company_id + status
company_id + external_id
company_id + employee_code
```

Cuando aplique, usar índices únicos compuestos:

```text
company_id + employee_code
company_id + code
company_id + source + external_id
company_id + worker_id + work_date
```

---

## 4.3 JSON en MySQL

Usar columnas `json` para:

- snapshots;
- metadata;
- payloads sanitizados;
- reglas aplicadas;
- resultados de cálculo;
- manifiestos.

No usar `jsonb`.

---

## 5. Vigencias

Cualquier dato que afecte cálculos históricos debe manejar vigencias:

- relación laboral;
- centro;
- horario;
- condiciones laborales;
- reglas legales.

No cambiar información histórica sin conservar versión/vigencia.

---

## 6. Estados sugeridos

## 6.1 Eventos

```text
valid
pending_review
voided
replaced
ignored
```

Implementacion Bloque 5:

- `time_events.received_at` es la hora explicita de recepcion/captura tecnica.
- La anulacion logica usa `status = voided`, `voided_at`, `voided_by_user_id` y `void_reason`.
- Los eventos anulados no se eliminan fisicamente y no participan en resoluciones futuras.
- Los eventos validos se ordenan por hora del hecho (`occurred_at_utc`) y desempates estables, no por insercion.

## 6.2 Alertas

```text
new
in_review
waiting_information
justified
corrected
closed
```

## 6.3 Incidencias

```text
open
in_review
correction_proposed
approved
rejected
controversy
closed
cancelled
```

## 6.4 Cierres

```text
draft
calculating
with_alerts
admin_review
available_to_workers
closed
cancelled
```

## 6.5 Reportes

```text
draft
available
confirmed
non_conformant
pending
in_clarification
closed
superseded
```

---

## 7. Tests multi-tenant obligatorios

Codex debe agregar pruebas para:

1. Usuario Empresa A no ve Trabajador Empresa B.
2. Token Empresa A no consulta datos Empresa B.
3. Exportación Empresa A no incluye datos Empresa B.
4. Job de Empresa A no procesa Empresa B.
5. Archivo de Empresa A no se descarga desde Empresa B.
