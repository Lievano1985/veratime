---
id: AI-0001
title: Contexto general para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - contexto
  - veratime
---

# AI-0001 — Contexto general para Codex

## 1. Propósito

Este archivo le da a Codex el contexto general del proyecto antes de tocar código.

Codex debe usar este archivo junto con los documentos formales del proyecto.

---

## 2. Qué es Vera Time

Vera es la marca principal para una futura serie de aplicaciones empresariales.

Vera Time es el producto enfocado en medición, administración y evidencia del tiempo laboral.

Vera Time es un SaaS multi-tenant para empresas en México enfocado en:

- registro electrónico de jornada laboral;
- control de entradas, salidas y pausas;
- cálculo de jornadas;
- alertas preventivas;
- incidencias y correcciones;
- reportes por periodo;
- conformidad digital de la persona trabajadora;
- generación de evidencia;
- exportaciones para nómina;
- preparación para inspecciones, auditorías o revisiones internas.

No es solamente un reloj checador.

El enfoque del producto es:

```text
cumplimiento + evidencia + trazabilidad
```

---

## 3. Objetivo del MVP

El MVP debe permitir que una empresa pueda:

1. configurar empresa y centros;
2. registrar trabajadores;
3. definir horarios;
4. registrar eventos de jornada;
5. calcular jornadas;
6. detectar posibles desviaciones;
7. gestionar incidencias;
8. corregir sin destruir historial;
9. cerrar periodos;
10. generar reportes;
11. permitir revisión/conformidad del trabajador;
12. exportar información básica;
13. operar mediante interfaz web y API.

---

## 4. Decisiones ya aprobadas

| Área | Decisión |
|---|---|
| Framework | Laravel |
| Interfaz | Livewire + Blade + Tailwind |
| Base de datos inicial | MySQL 8 / MariaDB compatible |
| Hosting inicial | Hosting actual o cPanel si aplica |
| Colas iniciales | Database queue |
| Scheduler inicial | Cron de cPanel |
| Storage inicial | Local/persistente del hosting |
| Redis | No obligatorio en MVP |
| AWS | Evolución posterior al MVP/piloto, no dependencia inicial |
| Arquitectura | Monolito modular |
| Enfoque | Domain-first + API-first pragmático y bidireccional |
| Multi-tenant | Base compartida con separación por `company_id` |
| API | `/api/v1` con tokens por empresa |
| Correcciones | No destructivas y versionadas |
| Conformidad | Digital, ligada a versión exacta del reporte |
| Motor legal | Reglas base por pais, Mexico preconfigurado, parametros de empresa permitidos y snapshots historicos |
| Alertas | Preventivas, con lenguaje neutral |
| Biometría | Fuera de P0 |
| App nativa | Fuera del MVP |
| ClickBalance | Archivo primero; API directa P1 condicionada |

---

## 5. Documentos fuente

Codex debe considerar como fuente de verdad:

```text
docs/00-Gobierno/DOC-0001-PROJECT_CHARTER.md
docs/00-Gobierno/DOC-0002-PRODUCT_VISION.md
docs/03-Requisitos/REQ-0001-ESPECIFICACION-REQUISITOS-MVP.md
docs/04-Arquitectura/ARQ-0001-ARQUITECTURA-DEL-MVP.md
docs/05-BaseDatos/BD-0001-MODELO-DE-DATOS-MVP.md
docs/06-UX/UX-0001-MAPA-DE-PANTALLAS-MVP.md
docs/07-API/API-0001-ESPECIFICACION-API-MVP.md
docs/09-Testing/TEST-0001-ESTRATEGIA-DE-PRUEBAS-MVP.md
docs/10-Deployment/DEP-0001-ESTRATEGIA-DE-DESPLIEGUE-MVP.md
docs/11-Roadmap/RM-0001-ROADMAP-PRODUCTO.md
docs/11-Roadmap/RM-0002-ALCANCE-Y-PRESUPUESTO-MVP.md
docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md
```

Los documentos legales están en:

```text
docs/01-Legal/LEG-0001-LFT/
```

---

## 6. Regla de oro

Codex no debe implementar funcionalidades improvisadas.

Debe trabajar por historia del backlog y respetar:

```text
arquitectura
base de datos
multi-tenant
dominio
API
testing
despliegue
```

Si una funcionalidad no aparece como P0 en el backlog, no debe implementarse salvo instrucción explícita.

