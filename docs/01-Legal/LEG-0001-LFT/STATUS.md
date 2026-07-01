---
id: LEG-0001-STATUS
title: Estado de la investigación jurídica
project: Jornada 360
version: 1.0.0
status: Closed
owner: Product Architecture
created: 2026-06-29
updated: 2026-06-30
tags:
  - legal
  - estado
  - lft
---

# Estado de la investigación jurídica

## Estado general

**Proyecto:** Jornada 360  
**Investigación:** Ley Federal del Trabajo  
**Versión:** 1.0.0  
**Estado:** Cerrada con pendientes normativos identificados  
**Fecha de corte:** 2026-06-30

## Progreso general

| Capítulo | Estado | Prioridad | Dependencias |
|---|---|---|---|
| README | Aprobado | Alta | Ninguna |
| 01 - Jornada Laboral | Aprobado | Crítica | LFT |
| 02 - Tipos de Jornada | Aprobado | Crítica | 01 |
| 03 - Horas Extra | Aprobado | Crítica | 01, 02 |
| 04 - Descansos | Aprobado | Alta | 01 |
| 05 - Registro Electrónico | Aprobado | Crítica | LFT, reforma 2026, privacidad |
| 06 - Teletrabajo | Aprobado | Alta | LFT, NOM-037, privacidad |
| 07 - Inspecciones y Sanciones | Aprobado | Alta | LFT, RGITAS, STPS |
| 08 - Reglas Derivadas | Aprobado | Crítica | 01 a 07 |
| 09 - Matriz de Trazabilidad | Aprobado | Crítica | 01 a 08 |
| SOURCES | Aprobado | Alta | Fuentes oficiales |
| CHANGELOG | Aprobado | Media | Control documental |

## Indicadores

| Indicador | Resultado |
|---|---:|
| Documentos principales completados | 9 / 9 |
| Documentos de control actualizados | 3 / 3 |
| Investigación jurídica | 100 % |
| Reglas específicas identificadas | 132 |
| Requisitos funcionales derivados | 129 |
| Reglas maestras consolidadas | 80 |
| Matriz de trazabilidad | 100 % |

## Pendientes abiertos

| ID | Pendiente | Estado | Acción |
|---|---|---|---|
| PEND-001 | Disposiciones generales STPS del registro electrónico. | Abierto | Monitorear DOF y STPS. |
| PEND-002 | Formato o canal oficial de entrega electrónica. | Abierto | Mantener exportaciones configurables. |
| PEND-003 | Plazo específico de conservación del nuevo registro. | Abierto | Aplicar política conservadora y retención legal. |
| PEND-004 | Reglas sectoriales o trabajos especiales. | Abierto | Analizar antes de activar módulos especializados. |
| PEND-005 | Fórmulas monetarias completas de nómina. | Abierto | Documentar en especificación de nómina. |

## Próximo paso recomendado

Trasladar las reglas aprobadas a:

- Product Bible.
- Arquitectura.
- Base de datos.
- Reglas de negocio.
- API.
- Plan de pruebas.
