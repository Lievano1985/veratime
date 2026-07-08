---
id: AI-0013
title: Modo de uso de agentes controlados
project: Vera Time
version: 1.1.0
status: Draft
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - agentes
  - flujo
  - veratime
---

# AI-0013 — Modo de uso de agentes controlados

## 1. Decisión

Vera Time usará inicialmente 4 agentes controlados:

```text
1. Arquitecto / Reviewer
2. Backend Laravel
3. QA y Seguridad
4. Documentación
```

No se crearán más agentes al inicio para evitar complejidad innecesaria.

---

## 2. Por qué solo 4

Estamos comenzando a trabajar con agentes.

El objetivo es caminar antes de correr.

Estos 4 roles cubren lo esencial:

| Agente | Qué cuida |
|---|---|
| Arquitecto / Reviewer | Que no se rompa la arquitectura |
| Backend Laravel | Que se implemente la funcionalidad |
| QA y Seguridad | Que no se rompa multi-tenant, pruebas ni seguridad |
| Documentación | Que los documentos sigan alineados con el código |

---

## 3. Flujo recomendado por historia

Para cada historia P0:

```text
1. Backend Laravel implementa.
2. Arquitecto / Reviewer revisa arquitectura y alcance.
3. QA y Seguridad revisa pruebas, permisos y multi-tenant.
4. Documentación actualiza notas, backlog o docs afectados si aplica.
5. Se corrigen observaciones.
6. Se hace commit.
```

---

## 4. Cuándo usar cada agente

| Momento | Agente |
|---|---|
| Antes de una historia compleja | Arquitecto / Reviewer |
| Durante implementación | Backend Laravel |
| Antes de aceptar cambios | QA y Seguridad |
| Después de terminar una historia | Documentación |

---

## 5. Prompt base para usar los 4 agentes

```text
Codex, trabaja con los 4 agentes controlados del proyecto Vera Time:

1. Usa AGENT-02-BACKEND-LARAVEL para implementar la historia.
2. Usa AGENT-01-ARQUITECTO-REVIEWER para revisar arquitectura y alcance.
3. Usa AGENT-03-QA-SEGURIDAD para revisar multi-tenant, seguridad y pruebas.
4. Usa AGENT-04-DOCUMENTACION para actualizar documentación afectada.

No uses otros agentes.
No implementes funcionalidades fuera del backlog.
Entrega un resumen consolidado con:
- implementación realizada;
- observaciones del arquitecto;
- observaciones de QA/seguridad;
- documentación actualizada;
- pruebas agregadas;
- riesgos pendientes.
```

---

## 6. Cuándo agregar más agentes

Agregar nuevos agentes solo cuando el proyecto avance.

| Momento | Agente posible |
|---|---|
| Cuando inicie motor legal fuerte | Agente Motor Legal |
| Cuando la API crezca mucho | Agente API |
| Antes de piloto real | Agente Deployment |
| Antes de producción comercial | Agente Performance/Observabilidad |
| Cuando haya ClickBalance confirmado | Agente Integraciones |

---

## 7. Regla

No agregar agentes por moda.

Agregar agente solo si:

- reduce errores;
- cuida un riesgo real;
- tiene responsabilidad clara;
- no duplica a otro agente;
- ayuda a avanzar más ordenado.


