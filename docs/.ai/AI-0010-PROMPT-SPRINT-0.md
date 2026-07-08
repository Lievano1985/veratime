---
id: AI-0010
title: Prompt Sprint 0 para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - prompt
  - sprint-0
  - veratime
---

# AI-0010 — Prompt Sprint 0 para Codex

## Prompt listo para usar

```text
Trabaja únicamente sobre el proyecto Laravel actual.

Objetivo:
Implementar Sprint 0 — Base técnica de Vera Time.

Contexto:
Vera Time es un SaaS multi-tenant para registro electrónico de jornada laboral, evidencia, alertas preventivas, correcciones, conformidad digital, reportes y cumplimiento laboral en México.

Documentos base obligatorios:
- docs/.ai/AI-0001-CONTEXTO-GENERAL.md
- docs/.ai/AI-0002-REGLAS-OBLIGATORIAS-PARA-CODEX.md
- docs/.ai/AI-0003-ARQUITECTURA-Y-PATRONES.md
- docs/.ai/AI-0004-MODELO-DE-DATOS-Y-MULTITENANT.md
- docs/04-Arquitectura/ARQ-0001-ARQUITECTURA-DEL-MVP.md
- docs/05-BaseDatos/BD-0001-MODELO-DE-DATOS-MVP.md
- docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md

Historias a implementar:
- BL-0001 — Inicializar proyecto Laravel
- BL-0002 — Crear estructura modular app/Domains
- BL-0003 — Configurar MySQL/MariaDB
- BL-0004 — Configurar database queue
- BL-0006 — Configurar testing base
- BL-0101 — Login y logout
- BL-0102 — Modelo Company
- BL-0103 — Contexto CurrentCompany/TenantContext
- BL-0104 — Relación usuario-empresa
- BL-0106 — Roles iniciales
- BL-0107 — Policies multi-tenant

Restricciones:
- Usar MySQL/MariaDB compatible.
- No usar PostgreSQL.
- No usar Redis como dependencia obligatoria.
- No asumir AWS.
- No meter motor legal todavía salvo estructura base si es necesaria.
- Mantener arquitectura domain-first.
- No poner lógica de negocio en componentes Livewire.
- Implementar multi-tenant por company_id.
- Agregar pruebas básicas.
- No crear funcionalidades fuera del alcance.
- No implementar biometría, app nativa ni ClickBalance API.

Entregables esperados:
- Migraciones necesarias.
- Modelos y relaciones.
- Middleware/servicio de empresa activa.
- Roles iniciales.
- Policies mínimas.
- Configuración de queue database.
- Tests de multi-tenant básicos.
- Tests de autenticación básicos.
- README o nota corta si hay comandos necesarios.

Antes de finalizar:
- Ejecuta o deja indicado el comando de pruebas.
- Lista archivos modificados.
- Explica decisiones tomadas.
- Señala pendientes si algo requiere configuración manual.
```


