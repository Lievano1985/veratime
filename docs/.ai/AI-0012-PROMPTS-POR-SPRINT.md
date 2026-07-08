---
id: AI-0012
title: Prompts por sprint para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - prompts
  - sprints
  - veratime
---

# AI-0012 — Prompts por sprint para Codex

## 1. Uso

Este archivo contiene prompts base para ejecutar trabajo por sprint.

Cada prompt debe ajustarse según el estado real del repositorio.

---

# 2. Prompt Sprint 0 — Base técnica

```text
Implementa Sprint 0 — Base técnica de Vera Time.

Lee:
- docs/.ai/AI-0001-CONTEXTO-GENERAL.md
- docs/.ai/AI-0002-REGLAS-OBLIGATORIAS-PARA-CODEX.md
- docs/.ai/AI-0003-ARQUITECTURA-Y-PATRONES.md
- docs/.ai/AI-0004-MODELO-DE-DATOS-Y-MULTITENANT.md
- docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md

Historias:
BL-0001, BL-0002, BL-0003, BL-0004, BL-0005, BL-0006,
BL-0101, BL-0102, BL-0103, BL-0104, BL-0106, BL-0107.

Restricciones:
MySQL, database queue, multi-tenant por company_id, sin Redis obligatorio, sin PostgreSQL, sin AWS obligatorio.

Entrega migraciones, modelos, relaciones, policies, middleware/contexto tenant y pruebas básicas.
```

---

# 3. Prompt Sprint 1 — Empresas y trabajadores

```text
Implementa Sprint 1 — Empresas, centros y trabajadores.

Lee:
- docs/.ai/
- docs/05-BaseDatos/BD-0001-MODELO-DE-DATOS-MVP.md
- docs/06-UX/UX-0001-MAPA-DE-PANTALLAS-MVP.md
- docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md

Historias:
BL-0105,
BL-0201, BL-0202, BL-0203, BL-0204, BL-0205,
BL-0301, BL-0302, BL-0303, BL-0304, BL-0305.

Usa Livewire para pantallas, pero lógica de negocio en Actions/Services.
Agrega pruebas multi-tenant.
```

---

# 4. Prompt Sprint 2 — Horarios y registro

```text
Implementa Sprint 2 — Horarios y registro electrónico.

Historias:
BL-0401, BL-0402, BL-0403, BL-0404, BL-0405, BL-0406,
BL-0501, BL-0502, BL-0503, BL-0504, BL-0505.

Requisitos:
- horarios con vigencias;
- eventos con hora local, UTC, zona horaria y fuente;
- kiosco código/NIP;
- NIP hasheado;
- captura manual con motivo;
- no borrar historial;
- pruebas para eventos y multi-tenant.
```

---

# 5. Prompt Sprint 3 — API y motor legal base

```text
Implementa Sprint 3 — API y motor legal base.

Historias:
BL-0601 a BL-0607,
BL-0701 a BL-0704.

Requisitos:
- Sanctum;
- token ligado a empresa;
- scopes;
- endpoints workers/time-events;
- idempotencia;
- legal_rules y legal_rule_versions;
- work_days y work_day_calculations;
- Actions/Services reutilizables;
- pruebas API y multi-tenant.
```

---

# 6. Prompt Sprint 4 — Cálculos y alertas

```text
Implementa Sprint 4 — Cálculos y alertas.

Historias:
BL-0705 a BL-0710,
BL-0801 a BL-0806.

Requisitos:
- clasificar jornada;
- calcular ordinario/extra;
- detectar descansos, domingo y obligatorio;
- guardar snapshot de reglas;
- generar alertas preventivas;
- evitar duplicados;
- lenguaje neutral;
- pruebas del motor legal.
```

---

# 7. Prompt Sprint 5 — Incidencias y correcciones

```text
Implementa Sprint 5 — Incidencias y correcciones.

Historias:
BL-0807 a BL-0809,
BL-0901 a BL-0908,
BL-0506, BL-0507.

Requisitos:
- incidencias;
- comentarios;
- evidencias;
- correcciones no destructivas;
- aprobación/rechazo;
- recalculo;
- nueva versión;
- auditoría.
```

---

# 8. Prompt Sprint 6 — Cierre y conformidad

```text
Implementa Sprint 6 — Cierre y conformidad digital.

Historias:
BL-1001 a BL-1009.

Requisitos:
- cierre de periodo;
- generación de reportes individuales;
- versión y hash;
- portal trabajador;
- confirmar conforme;
- no conforme crea incidencia;
- pendiente no equivale a conforme;
- nueva versión requiere nueva revisión.
```

---

# 9. Prompt Sprint 7 — Reportes e integraciones

```text
Implementa Sprint 7 — Reportes, expedientes e integraciones.

Historias:
BL-1101 a BL-1107,
BL-1201, BL-1202, BL-1203, BL-1206,
BL-0608 a BL-0612.

Requisitos:
- reportes PDF/CSV/XLSX;
- exportación prenómina;
- expedientes con manifiesto;
- importación CSV;
- logs de integración;
- endpoints de consulta;
- archivos con hash.
```

---

# 10. Prompt Sprint 8 — QA y despliegue

```text
Implementa Sprint 8 — QA, seguridad y despliegue.

Historias:
BL-1301 a BL-1307,
BL-1401 a BL-1407,
BL-1501 a BL-1506.

Requisitos:
- audit log transversal;
- pruebas multi-tenant;
- pruebas API;
- pruebas motor legal;
- checklist pre-piloto;
- configuración del hosting actual/cPanel si aplica;
- cron scheduler;
- database queue;
- smoke tests.
```


