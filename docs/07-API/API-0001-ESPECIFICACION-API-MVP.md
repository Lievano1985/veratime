---
id: API-0001
title: Especificación API del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-03
updated: 2026-07-03
tags:
  - api
  - mvp
  - rest
  - sanctum
  - domain-first
  - integraciones
  - veratime
---

# API-0001 — Especificación API del MVP

## 1. Objetivo

Definir la API mínima del MVP de Vera Time.

La API deberá permitir que funcionalidades clave del sistema puedan operar mediante integraciones externas, importaciones, futuras aplicaciones móviles, clientes empresariales y servicios internos, sin duplicar lógica de negocio.

Este documento parte de las decisiones aprobadas:

- Arquitectura domain-first.
- Exposición API-first pragmática y bidireccional.
- Monolito modular Laravel.
- MySQL 8 / MariaDB compatible como base inicial.
- Colas por base de datos en MVP.
- AWS u otra nube como evolución posterior al MVP/piloto.
- API REST versionada.
- Autenticación por tokens.
- Multi-tenant estricto por empresa.

---

## 2. Principio central

La API no será una copia secundaria de la interfaz.

La API será una entrada oficial al sistema.

El patrón obligatorio será:

```text
Livewire / Web
        ↓
Application Action / Service
        ↓
Domain Service
        ↓
Persistence

API /api/v1
        ↓
Application Action / Service
        ↓
Domain Service
        ↓
Persistence

CSV / Job / Integración
        ↓
Application Action / Service
        ↓
Domain Service
        ↓
Persistence
```

La lógica principal no deberá vivir en controladores API, componentes Livewire ni jobs.

---

## 3. Alcance API del MVP

## 3.1 API P0

La API P0 deberá permitir:

- Crear o actualizar trabajadores.
- Consultar trabajadores.
- Crear o actualizar relaciones laborales.
- Registrar eventos de jornada.
- Consultar eventos.
- Consultar jornadas calculadas.
- Consultar alertas.
- Crear incidencias.
- Consultar incidencias.
- Consultar reportes de periodo.
- Consultar exportaciones.
- Crear importaciones.
- Consultar estado de importaciones.
- Consultar logs básicos de integración.

## 3.2 API P1 / controlada

Queda para fase posterior o habilitación controlada:

- Crear centros.
- Crear horarios.
- Asignar horarios.
- Resolver alertas.
- Aprobar o rechazar correcciones.
- Generar expedientes.
- Confirmar conformidad digital vía API.
- Sincronización directa con ClickBalance.
- Webhooks salientes.
- API pública para terceros con portal de desarrolladores.

---

## 4. Base URL y versionamiento

## 4.1 Base URL

En producción:

```text
https://app.veratime.com/api/v1
```

En desarrollo/staging:

```text
https://staging.veratime.com/api/v1
```

o según el hosting disponible.

## 4.2 Versionamiento

Toda la API inicia en:

```text
/api/v1
```

No se crearán endpoints sin versión.

## 4.3 Política de compatibilidad

Mientras sea posible, los cambios serán compatibles hacia atrás.

Cambios no compatibles deberán ir a:

```text
/api/v2
```

---

## 5. Autenticación

## 5.1 Decisión

La API utilizará tokens Bearer mediante Laravel Sanctum.

Encabezado:

```http
Authorization: Bearer {token}
```

## 5.2 Token por empresa

Cada token estará ligado a una empresa.

El token define el contexto `company_id`.

Por seguridad, los endpoints externos no deberán permitir operar libremente sobre otra empresa mediante un parámetro manipulable.

## 5.3 Alcances del token

Cada token deberá tener capacidades limitadas.

Ejemplos:

```text
workers:read
workers:write
relationships:write
time-events:write
time-events:read
work-days:read
alerts:read
incidents:read
incidents:write
reports:read
imports:write
exports:read
integrations:logs
```

## 5.4 Regla

Un token sin alcance suficiente recibirá:

```http
403 Forbidden
```

---

## 6. Multi-tenant en API

## 6.1 Contexto de empresa

El contexto de empresa se resolverá por:

1. Token API.
2. Relación del token con la empresa.
3. Permisos del token.

## 6.2 Regla

La API nunca debe devolver datos de una empresa distinta a la del token.

## 6.3 URLs

No se usará `company_id` como parámetro principal en endpoints externos P0.

Ejemplo correcto:

```http
GET /api/v1/workers
```

Ejemplo a evitar en API externa P0:

```http
GET /api/v1/companies/{company_id}/workers
```

La empresa se infiere por token.

---

## 7. Formato general

## 7.1 Content-Type

```http
Content-Type: application/json
Accept: application/json
```

## 7.2 Fechas

Fechas y horas se enviarán en ISO 8601.

Ejemplo:

```json
{
  "occurred_at": "2026-09-15T08:00:00-06:00"
}
```

## 7.3 Zona horaria

Cuando sea relevante, se deberá enviar:

```json
{
  "timezone": "America/Mexico_City"
}
```

Si no se envía, se usará la zona horaria del centro o empresa, según configuración.

## 7.4 IDs

Los IDs públicos serán ULID/string.

Ejemplo:

```json
{
  "id": "01JZ7X6QK6YPD9V0RMK9FE8YEG"
}
```

---

## 8. Respuesta estándar

## 8.1 Respuesta exitosa

```json
{
  "data": {},
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

## 8.2 Listados paginados

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 150,
    "trace_id": "trc_01JZ7X..."
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

## 8.3 Error estándar

```json
{
  "message": "La solicitud no pudo procesarse.",
  "errors": {
    "employee_code": [
      "El código de empleado ya existe."
    ]
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

---

## 9. Códigos HTTP

| Código | Uso |
|---|---|
| `200` | Consulta exitosa |
| `201` | Recurso creado |
| `202` | Proceso aceptado para ejecución asíncrona |
| `204` | Sin contenido |
| `400` | Solicitud inválida |
| `401` | No autenticado |
| `403` | Sin permisos |
| `404` | No encontrado |
| `409` | Conflicto o duplicado |
| `422` | Error de validación |
| `429` | Límite de uso excedido |
| `500` | Error interno |
| `503` | Servicio no disponible |

---

## 10. Idempotencia

## 10.1 Objetivo

Evitar duplicados cuando una integración reintente una solicitud.

## 10.2 Encabezado

```http
Idempotency-Key: evt_abc_123
```

## 10.3 Reglas

Para eventos de jornada, trabajadores e importaciones, la API deberá soportar:

```text
company_id + source + external_id
```

o:

```text
company_id + idempotency_key
```

## 10.4 Respuesta ante repetición

Si llega una solicitud repetida con la misma clave, deberá devolver el mismo recurso o indicar conflicto controlado.

```http
200 OK
```

o:

```http
409 Conflict
```

según el caso.

---

## 11. Rate limiting

La API aplicará límites por token y empresa.

Recomendación inicial:

```text
60 requests por minuto por token
```

Para endpoints críticos o de importación se podrán aplicar límites diferentes.

Respuesta:

```http
429 Too Many Requests
```

---

# 12. Recursos API P0

---

## 12.1 Workers — Personas trabajadoras

### GET `/workers`

Consulta trabajadores.

Alcance requerido:

```text
workers:read
```

Filtros:

```text
status
center_id
employee_code
search
page
per_page
```

Ejemplo:

```http
GET /api/v1/workers?status=active&search=juan
```

Respuesta:

```json
{
  "data": [
    {
      "id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
      "employee_code": "000123",
      "full_name": "Juan Pérez López",
      "email": "juan@example.com",
      "status": "active",
      "center": {
        "id": "01JZ7X8KZ...",
        "name": "Planta Villahermosa"
      },
      "source": "api",
      "external_id": "EMP-123"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1,
    "trace_id": "trc_01JZ7X..."
  }
}
```

### POST `/workers`

Crea trabajador.

Alcance requerido:

```text
workers:write
```

Request:

```json
{
  "employee_code": "000123",
  "full_name": "Juan Pérez López",
  "email": "juan@example.com",
  "phone": "9930000000",
  "rfc": "PELJ900101XXX",
  "curp": "PELJ900101HTCRPN01",
  "center_id": "01JZ7X8KZ...",
  "position_name": "Operador",
  "started_at": "2026-09-01",
  "work_modality": "onsite",
  "external_id": "EMP-123"
}
```

Respuesta:

```http
201 Created
```

```json
{
  "data": {
    "id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
    "employee_code": "000123",
    "full_name": "Juan Pérez López",
    "status": "active"
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/workers/{worker_id}`

Consulta detalle del trabajador.

Alcance requerido:

```text
workers:read
```

### PATCH `/workers/{worker_id}`

Actualiza datos permitidos.

Alcance requerido:

```text
workers:write
```

Regla:

Cambios que afecten vigencias laborales deberán manejarse mediante endpoints de relaciones o condiciones, no sobreescribiendo historial.

---

## 12.2 Employment Relationships — Relaciones laborales

### GET `/workers/{worker_id}/relationships`

Consulta relaciones laborales.

Alcance requerido:

```text
workers:read
```

### POST `/workers/{worker_id}/relationships`

Crea relación laboral.

Alcance requerido:

```text
relationships:write
```

Request:

```json
{
  "center_id": "01JZ7X8KZ...",
  "position_name": "Operador",
  "started_at": "2026-09-01",
  "external_id": "REL-123"
}
```

### PATCH `/relationships/{relationship_id}`

Actualiza relación laboral.

Uso:

- Cambio de estado.
- Baja.
- Corrección controlada.

---

## 12.3 Time Events — Eventos de jornada

### POST `/time-events`

Registra evento de jornada.

Alcance requerido:

```text
time-events:write
```

Headers recomendados:

```http
Idempotency-Key: evt_000123_20260915_080000
```

Request:

```json
{
  "worker_id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
  "employee_code": "000123",
  "event_type": "clock_in",
  "occurred_at": "2026-09-15T08:00:00-06:00",
  "timezone": "America/Mexico_City",
  "center_id": "01JZ7X8KZ...",
  "source": "api",
  "external_id": "CLK-998877",
  "device": {
    "code": "CLOCK-01",
    "name": "Reloj entrada principal"
  },
  "metadata": {
    "raw_payload_id": "abc-123"
  }
}
```

Reglas:

- `worker_id` o `employee_code` debe identificar al trabajador.
- `event_type` debe ser válido.
- Se debe conservar hora del hecho y hora de recepción.
- El evento deberá poder disparar recalculo y alertas.
- La API no debe permitir crear eventos para trabajadores de otra empresa.

Respuesta:

```http
201 Created
```

```json
{
  "data": {
    "id": "01JZ7XEVT...",
    "worker_id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
    "event_type": "clock_in",
    "occurred_at": "2026-09-15T08:00:00-06:00",
    "status": "valid",
    "source": "api"
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/time-events`

Consulta eventos.

Alcance requerido:

```text
time-events:read
```

Filtros:

```text
worker_id
employee_code
center_id
date_from
date_to
event_type
source
status
```

### GET `/time-events/{event_id}`

Consulta detalle de evento.

Alcance requerido:

```text
time-events:read
```

### POST `/time-events/{event_id}/void`

Anulación lógica de evento.

Prioridad:

```text
P1 controlado
```

No se recomienda habilitar a integraciones generales en el MVP.

---

## 12.4 Work Days — Jornadas calculadas

### GET `/work-days`

Consulta jornadas calculadas.

Alcance requerido:

```text
work-days:read
```

Filtros:

```text
worker_id
employee_code
center_id
date_from
date_to
status
with_alerts
```

Respuesta:

```json
{
  "data": [
    {
      "id": "01JZ7XWD...",
      "work_date": "2026-09-15",
      "worker": {
        "id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
        "employee_code": "000123",
        "full_name": "Juan Pérez López"
      },
      "status": "with_alerts",
      "active_calculation": {
        "id": "01JZ7XCALC...",
        "version": 2,
        "classification": "diurnal",
        "total_work_minutes": 540,
        "ordinary_minutes": 480,
        "overtime_minutes": 60,
        "alerts_count": 1
      }
    }
  ],
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/work-days/{work_day_id}`

Consulta detalle de jornada.

Incluye:

- Eventos.
- Cálculo activo.
- Alertas.
- Incidencias.
- Versiones.

### POST `/work-days/{work_day_id}/recalculate`

Recalcula jornada.

Prioridad:

```text
P1 controlado
```

Regla:

Solo sistemas o usuarios con permisos fuertes podrán ejecutar recalculo manual por API.

---

## 12.5 Alerts — Alertas preventivas

### GET `/alerts`

Consulta alertas.

Alcance requerido:

```text
alerts:read
```

Filtros:

```text
worker_id
center_id
severity
status
date_from
date_to
alert_type
assigned_to
```

Respuesta:

```json
{
  "data": [
    {
      "id": "01JZ7XALT...",
      "type": "daily_limit_exceeded",
      "title": "Tiempo superior al límite diario configurado",
      "severity": "high",
      "status": "new",
      "worker": {
        "employee_code": "000123",
        "full_name": "Juan Pérez López"
      },
      "work_date": "2026-09-15",
      "detected_at": "2026-09-15T19:00:00-06:00"
    }
  ],
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/alerts/{alert_id}`

Consulta detalle de alerta.

### PATCH `/alerts/{alert_id}`

Resolver o cambiar estado.

Prioridad:

```text
P1 controlado
```

En el MVP puede resolverse desde la interfaz web.

---

## 12.6 Incidents — Incidencias

### GET `/incidents`

Consulta incidencias.

Alcance requerido:

```text
incidents:read
```

Filtros:

```text
worker_id
center_id
status
type
date_from
date_to
```

### POST `/incidents`

Crea incidencia.

Alcance requerido:

```text
incidents:write
```

Request:

```json
{
  "worker_id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
  "work_day_id": "01JZ7XWD...",
  "type": "missing_event",
  "title": "Salida no registrada",
  "description": "El trabajador reporta que olvidó registrar salida.",
  "requested_correction": {
    "correction_type": "add_event",
    "event_type": "clock_out",
    "occurred_at": "2026-09-15T17:00:00-06:00",
    "reason": "Olvido de registro"
  }
}
```

Respuesta:

```http
201 Created
```

### GET `/incidents/{incident_id}`

Consulta detalle.

### POST `/incidents/{incident_id}/comments`

Agrega comentario.

Alcance requerido:

```text
incidents:write
```

### POST `/incidents/{incident_id}/corrections`

Propone corrección.

Prioridad:

```text
P0 si se requiere para portal/trabajador o integración controlada.
```

### POST `/corrections/{correction_id}/approve`

Aprobar corrección.

Prioridad:

```text
P1 controlado
```

---

## 12.7 Closing Periods — Cierres de periodo

### GET `/closing-periods`

Consulta periodos.

Alcance requerido:

```text
reports:read
```

Filtros:

```text
period_start
period_end
status
center_id
```

### GET `/closing-periods/{period_id}`

Consulta detalle del periodo.

Incluye:

- Estado.
- Trabajadores incluidos.
- Alertas.
- Reportes individuales.
- Totales.
- Exportaciones.

### POST `/closing-periods`

Crear cierre.

Prioridad:

```text
P1 controlado
```

En MVP puede ser principalmente web.

---

## 12.8 Period Reports — Reportes de periodo

### GET `/period-reports`

Consulta reportes individuales.

Alcance requerido:

```text
reports:read
```

Filtros:

```text
closing_period_id
worker_id
employee_code
status
```

### GET `/period-reports/{report_id}`

Consulta reporte individual.

Respuesta:

```json
{
  "data": {
    "id": "01JZ7XRPT...",
    "worker": {
      "employee_code": "000123",
      "full_name": "Juan Pérez López"
    },
    "period": {
      "start": "2026-09-15",
      "end": "2026-09-21"
    },
    "status": "available",
    "active_version": {
      "id": "01JZ7XVER...",
      "version": 1,
      "hash": "sha256:...",
      "summary": {
        "ordinary_minutes": 2400,
        "overtime_minutes": 120,
        "alerts_count": 2
      }
    }
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### POST `/period-reports/{report_id}/confirm`

Confirmar conformidad o no conformidad.

Prioridad:

```text
P1 controlado
```

En MVP, la confirmación principal ocurre desde el portal trabajador. La API se prepara para futura app móvil.

---

## 12.9 Imports — Importaciones

### POST `/imports`

Crea lote de importación.

Alcance requerido:

```text
imports:write
```

Tipos:

```text
workers
schedules
time_events
```

Para archivos reales, el endpoint podrá usar `multipart/form-data` o flujo de subida definido por storage.

Request conceptual JSON:

```json
{
  "type": "time_events",
  "source": "csv",
  "file_id": "01JZ7XFILE..."
}
```

Respuesta:

```http
202 Accepted
```

```json
{
  "data": {
    "id": "01JZ7XIMP...",
    "type": "time_events",
    "status": "queued"
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/imports/{import_id}`

Consulta estado del lote.

### GET `/imports/{import_id}/rows`

Consulta errores o resultados por fila.

---

## 12.10 Exports — Exportaciones

### POST `/exports/payroll`

Genera exportación de prenómina.

Alcance requerido:

```text
exports:write
```

Prioridad:

```text
P0/P1 alto
```

Request:

```json
{
  "closing_period_id": "01JZ7XPER...",
  "center_id": "01JZ7X8KZ...",
  "format": "xlsx",
  "concepts": [
    "ordinary_hours",
    "overtime",
    "sunday_work",
    "mandatory_rest_work",
    "incidents"
  ]
}
```

Respuesta:

```http
202 Accepted
```

```json
{
  "data": {
    "id": "01JZ7XEXP...",
    "status": "queued"
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/exports/{export_id}`

Consulta estado y archivo.

---

## 12.11 Integration Logs — Logs de integración

### GET `/integration-logs`

Consulta logs técnicos.

Alcance requerido:

```text
integrations:logs
```

Filtros:

```text
operation
status
date_from
date_to
trace_id
```

Respuesta:

```json
{
  "data": [
    {
      "id": "01JZ7XLOG...",
      "operation": "time-events.create",
      "status": "success",
      "direction": "inbound",
      "trace_id": "trc_01JZ7X...",
      "created_at": "2026-09-15T08:00:01-06:00"
    }
  ]
}
```

---

# 13. Endpoints administrativos P1

Estos endpoints se diseñan, pero no necesariamente se liberan en el MVP público.

## 13.1 Centers

```http
GET    /centers
POST   /centers
GET    /centers/{center_id}
PATCH  /centers/{center_id}
```

## 13.2 WFM scheduling

```http
GET    /shift-templates
POST   /shift-templates
GET    /schedule-profiles
POST   /schedule-profiles
POST   /schedule-profile-assignments
POST   /schedule-batches
POST   /schedule-batches/{batch_id}/import
POST   /schedule-batches/{batch_id}/publish
GET    /daily-schedule-assignments
```

Los endpoints legacy `/schedules` y `/schedule-assignments` pertenecen al modelo Sprint 2A/2B y deberan reemplazarse cuando se implemente la programacion diaria publicada.

Nota WFM F5B: el nucleo interno ya cuenta con batches, asignaciones diarias, segmentos, snapshots canonicos, resolucion de programacion publicada, generacion interna de borradores desde perfiles, publicacion atomica de batches completos desde dominio, interfaz web `/scheduling/daily`, correcciones versionadas e importacion CSV web a lotes `draft`. No existe todavia API funcional para administrar perfiles avanzados, disparar generacion, publicar programacion diaria, cargar calendarios por endpoint ni crear activaciones bajo demanda.

## 13.3 Evidence Packages

```http
POST   /evidence-packages
GET    /evidence-packages
GET    /evidence-packages/{package_id}
```

## 13.3.1 Closing profiles

```http
GET    /closing-period-profiles
POST   /closing-period-profiles
POST   /closing-profile-assignments
POST   /closing-periods/generate
GET    /closing-periods
GET    /closing-periods/{period_id}/members
```

La API debera mostrar el perfil efectivo y su origen: empresa, centro, unidad organizacional o relacion laboral.

## 13.4 API Tokens

La administración de tokens podrá iniciar desde interfaz web.

Endpoints futuros:

```http
GET    /api-tokens
POST   /api-tokens
DELETE /api-tokens/{token_id}
```

---

# 14. Webhooks futuros

No son P0.

Fase posterior:

```text
work_day.calculated
alert.created
incident.created
period_report.available
period_report.confirmed
export.ready
```

Reglas futuras:

- Firma HMAC.
- Reintentos.
- Logs.
- Secret por empresa.
- Desactivación por fallos.

---

# 15. Seguridad

## 15.1 Reglas mínimas

- HTTPS obligatorio.
- Tokens Bearer.
- Alcances por token.
- Rate limiting.
- Validación estricta.
- Contexto de empresa por token.
- Auditoría de operaciones sensibles.
- Logs de integración.
- No exponer secretos.
- No devolver datos de otra empresa.
- No exponer campos internos innecesarios.

## 15.2 Datos sensibles

La API deberá limitar datos personales y evidencia según el alcance del token.

Ejemplo:

Un token de reloj checador no necesita consultar reportes ni evidencias.

---

# 16. Auditoría API

Operaciones auditables:

- Crear trabajador.
- Actualizar trabajador.
- Crear relación laboral.
- Registrar evento.
- Crear incidencia.
- Proponer corrección.
- Aprobar corrección.
- Generar exportación.
- Consultar expediente.
- Revocar token.
- Fallos repetidos.

Cada operación deberá registrar:

- Empresa.
- Token o actor.
- Operación.
- Entidad.
- IP.
- User-Agent.
- Trace ID.
- Resultado.
- Fecha.

---

# 17. Procesos asíncronos

Usan colas por base de datos en el MVP.

Procesos asíncronos:

- Importaciones.
- Recalculos masivos.
- Generación de reportes.
- Exportaciones.
- Expedientes.
- Notificaciones.
- Sincronizaciones futuras.

Respuesta estándar:

```http
202 Accepted
```

con estado consultable:

```http
GET /api/v1/imports/{id}
GET /api/v1/exports/{id}
```

---

# 18. ClickBalance

## 18.1 Decisión MVP

Para ClickBalance se priorizará exportación compatible por archivo.

La API directa queda como P1 condicionado.

## 18.2 API futura

La integración futura deberá usar servicios del dominio, no lógica separada.

Flujo:

```text
ClickBalance / Sistema externo
→ Vera Time API
→ Actions/Services
→ Motor legal / Reportes
→ Exportación o API de salida
→ ClickBalance / Sistema externo
```

## 18.3 Datos necesarios

Antes de desarrollar API directa se debe confirmar:

- Documentación.
- Credenciales.
- Ambiente de pruebas.
- Empleados.
- Periodos.
- Conceptos.
- Movimientos.
- Formatos.
- Manejo de errores.

---

# 19. Estructura sugerida en Laravel

## 19.1 Rutas

```text
routes/api.php
```

Grupos:

```php
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'api.tenant', 'throttle:api'])
    ->group(function () {
        // endpoints
    });
```

## 19.2 Controladores

Controladores delgados:

```text
app/Http/Controllers/Api/V1/
```

Ejemplos:

```text
WorkerController
TimeEventController
WorkDayController
AlertController
IncidentController
PeriodReportController
ImportController
ExportController
IntegrationLogController
```

## 19.3 Requests

Validaciones:

```text
app/Http/Requests/Api/V1/
```

## 19.4 Resources

Transformación de respuesta:

```text
app/Http/Resources/Api/V1/
```

## 19.5 Actions/Services

Toda lógica va a:

```text
app/Domains/*/Actions
app/Domains/*/Services
```

Ejemplos:

```text
CreateWorkerAction
UpdateWorkerAction
RegisterTimeEventAction
RecalculateWorkDayAction
GeneratePreventiveAlertsAction
CreateIncidentAction
GeneratePayrollExportAction
```

---

# 20. Pruebas API

## 20.1 Pruebas P0

- Token válido.
- Token inválido.
- Token sin alcance.
- No acceso entre empresas.
- Crear trabajador.
- Crear evento.
- Idempotencia de evento.
- Consultar jornada.
- Consultar alerta.
- Crear incidencia.
- Exportación asíncrona.
- Rate limit.
- Errores de validación.
- Logs de integración.

## 20.2 Prueba crítica multi-tenant

Escenario:

```text
Empresa A tiene trabajador A.
Empresa B tiene token B.

Token B intenta consultar trabajador A.
Resultado esperado: 404 o 403.
```

No debe revelar si el recurso existe en otra empresa.

---

# 21. Priorización final API

## P0

```text
GET    /workers
POST   /workers
GET    /workers/{id}
PATCH  /workers/{id}

GET    /workers/{id}/relationships
POST   /workers/{id}/relationships
PATCH  /relationships/{id}

POST   /time-events
GET    /time-events
GET    /time-events/{id}

GET    /work-days
GET    /work-days/{id}

GET    /alerts
GET    /alerts/{id}

GET    /incidents
POST   /incidents
GET    /incidents/{id}
POST   /incidents/{id}/comments

GET    /closing-periods
GET    /closing-periods/{id}

GET    /period-reports
GET    /period-reports/{id}

POST   /imports
GET    /imports/{id}
GET    /imports/{id}/rows

POST   /exports/payroll
GET    /exports/{id}

GET    /integration-logs
```

## P1 controlado

```text
POST   /centers
PATCH  /centers/{id}

POST   /shift-templates
PATCH  /shift-templates/{id}
POST   /schedule-profiles
PATCH  /schedule-profiles/{id}
POST   /schedule-profile-assignments
POST   /schedule-batches
POST   /schedule-batches/{id}/import
POST   /schedule-batches/{id}/publish
GET    /daily-schedule-assignments

POST   /alerts/{id}/resolve
POST   /incidents/{id}/corrections
POST   /corrections/{id}/approve
POST   /work-days/{id}/recalculate

POST   /period-reports/{id}/confirm
POST   /evidence-packages
GET    /evidence-packages/{id}

Webhooks
ClickBalance API directa
```

Nota WFM: CSV/XLSX/API de programacion por calendario deberan crear batches en `draft` para revision. Ningun endpoint de importacion publica automaticamente programacion diaria.

Cada `schedule_batch` pertenece obligatoriamente a una empresa, un centro y un rango de fechas. Una operacion de empresa completa debe crear un batch por centro.

La publicacion API debe generar version consecutiva por centro y periodo, snapshot JSON canonico, hash SHA-256, `published_by` y `published_at`. Una correccion no edita una publicacion existente: crea nueva version y deja la anterior `superseded`.

---

# 22. Criterios de aceptación

La API del MVP se considera aceptada cuando:

1. Usa `/api/v1`.
2. Requiere token Bearer.
3. Resuelve empresa por token.
4. Aplica alcances por token.
5. Impide acceso cruzado entre empresas.
6. Permite crear y consultar trabajadores.
7. Permite registrar eventos de jornada.
8. Soporta idempotencia en eventos.
9. Dispara el mismo flujo de cálculo que la interfaz.
10. Permite consultar jornadas calculadas.
11. Permite consultar alertas.
12. Permite crear incidencias.
13. Permite consultar reportes de periodo.
14. Permite generar importaciones/exportaciones asíncronas.
15. Devuelve errores estándar.
16. Registra logs de integración.
17. Registra auditoría en operaciones sensibles.
18. No duplica lógica de negocio.
19. Puede convivir con Livewire, CSV, jobs e integraciones.
20. Está preparada para ClickBalance y futuras integraciones.

---

# 23. Siguiente documento

Después de aprobar esta especificación API, el siguiente documento recomendado será:

```text
docs/09-Testing/TEST-0001-ESTRATEGIA-DE-PRUEBAS-MVP.md
```

Ahí se definirán:

- Pruebas funcionales.
- Pruebas legales del motor.
- Pruebas multi-tenant.
- Pruebas API.
- Pruebas de seguridad.
- Pruebas de cierre y conformidad.
- Pruebas de importaciones/exportaciones.
- Criterios mínimos para piloto.

---

## Nota Bloque F4

F4 implementa correcciones versionadas desde dominio e interfaz web. No agrega endpoints API WFM. Cuando se exponga por API, debera reutilizar las mismas Actions de dominio: crear correccion, comparar versiones, validar y publicar correccion.
