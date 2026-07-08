---
id: AI-0006
title: Motor legal, alertas y correcciones para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - motor-legal
  - alertas
  - correcciones
  - veratime
---

# AI-0006 — Motor legal, alertas y correcciones para Codex

## 1. Principio

El motor legal debe ser un módulo de dominio, no una pantalla.

Debe poder ejecutarse desde:

- web;
- API;
- importación CSV;
- job;
- comando;
- prueba automatizada.

---

## 2. Reglas legales versionadas

Usar:

```text
legal_rules
legal_rule_versions
legal_parameters
```

Cada cálculo debe guardar snapshot de reglas aplicadas.

Si cambia una regla futura, no debe modificar cálculos históricos automáticamente.

---

## 3. Casos mínimos del motor

El motor debe cubrir:

- jornada diurna;
- jornada nocturna;
- jornada mixta;
- cruce de medianoche;
- pausa computable;
- pausa no computable;
- horas ordinarias;
- horas extra;
- más de doce horas;
- descanso insuficiente;
- domingo;
- descanso obligatorio;
- más de seis días consecutivos;
- vigencia legal por fecha;
- condición más favorable configurada.

---

## 4. Cálculo versionado

Modelo esperado:

```text
work_days
work_day_calculations
calculation_events
```

Cada recalculo debe generar nueva versión cuando cambie resultado relevante.

---

## 5. Alertas preventivas

Las alertas no son acusaciones legales.

Lenguaje permitido:

```text
posible desviación
situación pendiente de revisión
tiempo superior al límite configurado
registro incompleto
descanso pendiente de revisión
```

Evitar:

```text
infracción
violación
incumplimiento confirmado
sanción
culpable
```

---

## 6. Tipos mínimos de alerta

```text
missing_clock_in
missing_clock_out
incomplete_break
daily_limit_exceeded
weekly_limit_exceeded
overtime_limit_exceeded
rest_insufficient
mandatory_rest_worked
sunday_work
duplicate_event
out_of_order_event
late_event
```

---

## 7. Reglas de alertas

- Un recalculo no debe duplicar alertas sin control.
- Una alerta debe conservar origen.
- Una alerta debe tener estado.
- Alertas críticas pueden bloquear cierre.
- Una alerta puede generar incidencia.
- Resolver una alerta debe auditarse.

---

## 8. Correcciones

Las correcciones deben ser no destructivas.

Flujo:

```text
incidencia
→ propuesta de corrección
→ aprobación/rechazo
→ aplicación controlada
→ nuevo cálculo
→ nueva versión
→ actualización de alertas
→ auditoría
```

---

## 9. Prohibido

Codex no debe:

- borrar evento original;
- editar reporte firmado;
- modificar cálculo histórico sin nueva versión;
- transferir conformidad a nueva versión;
- cerrar periodo con alerta crítica pendiente;
- marcar conformidad por silencio.

---

## 10. Tests mínimos

Agregar pruebas para:

- cálculo diurno;
- cálculo nocturno;
- cálculo mixto;
- horas extra;
- descanso insuficiente;
- regla por vigencia;
- alerta no duplicada;
- corrección genera nueva versión;
- evento original conservado;
- reporte firmado no cambia.


