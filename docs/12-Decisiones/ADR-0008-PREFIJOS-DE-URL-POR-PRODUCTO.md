---
id: ADR-0008
title: Prefijos de URL por producto VERA
project: Vera
status: Accepted
owner: Product Architecture
created: 2026-09-16
updated: 2026-09-16
tags:
  - arquitectura
  - rutas
  - productos
  - api
  - veratime
---

# ADR-0008 - Prefijos de URL por producto VERA

## Contexto

VERA evolucionara desde Vera Time hacia varios productos, incluyendo Payroll y HR. Las rutas historicas de Time usaban nombres genericos como `/dashboard` y `/workers`, que se volverian ambiguos cuando mas de un producto tenga paneles, personas o procesos similares.

La identidad, sesion y contexto de empresa deben seguir siendo comunes. La separacion de URL no debe crear aplicaciones, cuentas ni tenants independientes.

## Decision

Cada producto operara bajo un prefijo web propio:

```text
/time/...
/payroll/...
/hr/...
```

Las rutas globales se mantienen fuera de un producto:

```text
/login
/forgot-password
/companies
/customer-accounts
/settings/...
```

La API publica se versiona antes del prefijo de producto:

```text
/api/v1/time/...
/api/v1/payroll/...
/api/v1/hr/...
```

Vera Time adopta desde ahora `/time/dashboard`, `/time/workers`, `/time/kiosk` y demas rutas operativas bajo `/time/...`.

## Consecuencias

- La navegacion y los enlaces deben usar nombres de ruta Laravel, no rutas escritas a mano.
- Login, identidad, selector de empresa, roles y permisos se reutilizan entre productos.
- El prefijo no autoriza acceso: cada ruta conserva autenticacion, producto contratado, empresa activa y permisos.
- Payroll y HR podran agregarse al monolito modular sin colisiones de URL ni autenticacion duplicada.
- Las integraciones futuras deben usar el prefijo API de su producto y los tokens solo resolveran empresas autorizadas.

## Fuera de esta decision

Esta decision no implementa Payroll, HR, una app nativa ni una separacion en microservicios. Solo establece la frontera de navegacion y API para el monolito VERA.
