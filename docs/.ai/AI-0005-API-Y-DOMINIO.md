---
id: AI-0005
title: API y dominio para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - api
  - dominio
  - sanctum
  - veratime
---

# AI-0005 — API y dominio para Codex

## 1. Principio

La API es un canal oficial del producto.

No es una integración secundaria.

---

## 2. Base API

Usar:

```text
/api/v1
```

No crear endpoints sin versión.

---

## 3. Autenticación

Usar:

```text
Laravel Sanctum
Bearer Token
```

Cada token debe estar asociado a una empresa.

---

## 4. Scopes iniciales

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

---

## 5. Multi-tenant API

La API no debe aceptar `company_id` manipulable para operar recursos P0.

La empresa debe resolverse por token.

Correcto:

```http
GET /api/v1/workers
```

Incorrecto para P0 externo:

```http
GET /api/v1/companies/{company_id}/workers
```

---

## 6. Formato de respuesta

Respuesta exitosa:

```json
{
  "data": {},
  "meta": {
    "trace_id": "trc_..."
  }
}
```

Error:

```json
{
  "message": "La solicitud no pudo procesarse.",
  "errors": {},
  "meta": {
    "trace_id": "trc_..."
  }
}
```

---

## 7. Idempotencia

Para eventos y operaciones externas usar:

```http
Idempotency-Key: unique-key
```

o:

```text
company_id + source + external_id
```

No duplicar eventos si una integración reintenta.

---

## 8. Endpoints P0

```text
GET    /api/v1/workers
POST   /api/v1/workers
GET    /api/v1/workers/{id}
PATCH  /api/v1/workers/{id}

GET    /api/v1/workers/{id}/relationships
POST   /api/v1/workers/{id}/relationships
PATCH  /api/v1/relationships/{id}

POST   /api/v1/time-events
GET    /api/v1/time-events
GET    /api/v1/time-events/{id}

GET    /api/v1/work-days
GET    /api/v1/work-days/{id}

GET    /api/v1/alerts
GET    /api/v1/alerts/{id}

GET    /api/v1/incidents
POST   /api/v1/incidents
GET    /api/v1/incidents/{id}
POST   /api/v1/incidents/{id}/comments

GET    /api/v1/closing-periods
GET    /api/v1/closing-periods/{id}

GET    /api/v1/period-reports
GET    /api/v1/period-reports/{id}

POST   /api/v1/imports
GET    /api/v1/imports/{id}
GET    /api/v1/imports/{id}/rows

POST   /api/v1/exports/payroll
GET    /api/v1/exports/{id}

GET    /api/v1/integration-logs
```

---

## 9. Controladores delgados

API Controllers no deben contener lógica.

Deben:

1. validar Request;
2. llamar Action/Service;
3. devolver Resource;
4. manejar errores estándar.

---

## 10. Resources

Usar API Resources para transformar respuestas.

No devolver modelos completos sin control.

---

## 11. Logs de integración

Cada operación API relevante debe registrar:

- empresa;
- token;
- operación;
- estado;
- trace ID;
- IP;
- error si aplica;
- payload sanitizado.

---

## 12. Tests API mínimos

Codex debe probar:

- token válido;
- token inválido;
- token sin scope;
- token de otra empresa;
- crear trabajador;
- crear evento;
- idempotencia;
- error estándar;
- logs de integración.


