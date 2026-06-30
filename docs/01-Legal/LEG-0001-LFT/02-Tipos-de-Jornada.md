---
id: LEG-0001-02
title: Tipos de jornada
project: Jornada 360
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-06-29
updated: 2026-06-29
sources:
  - SRC-001
  - SRC-002
tags:
  - legal
  - jornada-diurna
  - jornada-nocturna
  - jornada-mixta
---

# 02 - Tipos de jornada

## 1. Objetivo

Definir cómo debe representar Jornada 360 las jornadas **diurna, nocturna y mixta**, así como los límites diarios asociados y las reglas necesarias para clasificar jornadas que cruzan distintos periodos horarios.

> Este documento traduce los artículos 60 y 61 de la LFT a especificaciones de producto. Cuando exista ambigüedad en un caso concreto, el sistema deberá conservar los hechos y permitir revisión, en lugar de emitir una conclusión jurídica irreversible.

## 2. Fundamento legal confirmado

### Artículo 60

La LFT distingue tres tipos:

| Tipo de jornada | Periodo o criterio legal | Criterio para el motor |
| --- | --- | --- |
| Diurna | De 06:00 a 20:00. | Todos los minutos computables caen dentro del periodo diurno. |
| Nocturna | De 20:00 a 06:00. | Todos los minutos computables caen dentro del periodo nocturno, o la parte nocturna alcanza el umbral legal en una jornada combinada. |
| Mixta | Combina periodos diurnos y nocturnos. | Hay minutos diurnos y nocturnos, pero el periodo nocturno es menor de 3 horas con 30 minutos. |

> Si una jornada combinada alcanza 3 horas con 30 minutos o más de periodo nocturno, se considera nocturna.

### Artículo 61

La duración diaria máxima es:

| Tipo de jornada | Máximo ordinario diario | Equivalente en minutos | Validación esperada |
| --- | ---: | ---: | --- |
| Diurna | 8 horas | 480 | Comparar contra tiempo ordinario computable diurno. |
| Nocturna | 7 horas | 420 | Comparar contra tiempo ordinario computable nocturno. |
| Mixta | 7 horas con 30 minutos | 450 | Comparar contra tiempo ordinario computable mixto. |

Estos máximos diarios coexisten con el máximo semanal aplicable por año. El cumplimiento de uno no sustituye la validación del otro.

## 3. Interpretación para Jornada 360

### 3.1 La clasificación depende del tiempo dentro de cada franja

El motor deberá poder dividir un intervalo de trabajo entre:

- Minutos en periodo diurno.
- Minutos en periodo nocturno.

Cuando una jornada incluya ambos periodos:

- Menos de 210 minutos nocturnos: candidata a jornada mixta.
- 210 minutos nocturnos o más: candidata a jornada nocturna.

La palabra **candidata** se utiliza porque un registro incompleto, una pausa sin resolver o una corrección pendiente pueden impedir una clasificación confiable.

### 3.2 Deben existir dos clasificaciones

Jornada 360 debe distinguir:

1. **Tipo programado:** el definido en el horario o turno asignado.
2. **Tipo resultante:** el calculado con los intervalos realmente registrados.

Si son distintos, el sistema generará una observación para revisión. No modificará silenciosamente el turno histórico.

### 3.3 Las jornadas que cruzan medianoche pertenecen a una unidad operativa

Una jornada iniciada antes de medianoche y cerrada al día siguiente debe conservarse como una sola jornada, vinculada a una **fecha operativa** definida por la empresa o por el turno.

Ejemplo:

- Inicio: lunes 22:00.
- Fin: martes 05:00.
- Fecha operativa: lunes.
- Tipo resultante: nocturna.
- Duración: 7 horas, antes de considerar descansos.

La fecha operativa no sustituye las marcas reales con fecha y hora completas.

### 3.4 Los descansos deben tratarse por separado

La clasificación se calculará sobre los intervalos computables como jornada. La forma en que un descanso se descuenta o se considera tiempo efectivo dependerá de las reglas analizadas en `04-Descansos.md`.

El motor no debe asumir que toda diferencia entre entrada y salida es tiempo efectivo.

### 3.5 El tipo de jornada no depende del nombre comercial del turno

Una empresa puede llamar a un turno `vespertino`, `tercero` o `guardia B`. Esa etiqueta no determina su clasificación legal.

Jornada 360 conservará:

- Nombre operativo del turno.
- Tipo legal programado.
- Tipo legal resultante.

## 4. Regla de clasificación propuesta

La siguiente es una regla de producto derivada de los artículos 60 y 61; deberá validarse en casos especiales por el equipo jurídico.

### Paso 1 — Reconstruir intervalos computables

Formar los intervalos de trabajo con base en eventos válidos y descansos resueltos.

### Paso 2 — Aplicar la zona horaria local

Convertir los eventos a la zona horaria del centro, asignación o política aplicable.

### Paso 3 — Calcular superposición

Para cada intervalo, calcular cuántos minutos corresponden a:

- Periodo diurno: 06:00–20:00.
- Periodo nocturno: 20:00–06:00.

### Paso 4 — Clasificar

- Solo minutos diurnos: **diurna**.
- Solo minutos nocturnos: **nocturna**.
- Minutos de ambos periodos y nocturnos < 210: **mixta**.
- Minutos de ambos periodos y nocturnos >= 210: **nocturna**.
- Datos insuficientes o inconsistentes: **pendiente de clasificación**.

### Paso 5 — Validar duración diaria

Comparar el tiempo ordinario computable contra el máximo del tipo resultante.

### Paso 6 — Validar límite semanal

Aplicar además el máximo semanal vigente y las condiciones contractuales más favorables.

## 5. Ejemplos de clasificación

Los ejemplos suponen intervalos continuos sin descansos. Son casos de prueba orientativos, no dictámenes jurídicos.

| Caso | Intervalo | Minutos diurnos | Minutos nocturnos | Clasificación esperada | Validación diaria | Observación |
| ---: | --- | ---: | ---: | --- | --- | --- |
| 1 | 08:00-16:00 | 480 | 0 | Diurna | En límite | Ocho horas: alcanza el máximo diario diurno. |
| 2 | 14:00-21:00 | 360 | 60 | Mixta | Dentro del límite | Siete horas totales. |
| 3 | 13:00-20:30 | 420 | 30 | Mixta | En límite | Siete horas con treinta minutos: alcanza el máximo mixto. |
| 4 | 18:00-01:00 | 120 | 300 | Nocturna | En límite | Al menos 3 h 30 min nocturnas. |
| 5 | 20:00-03:00 | 0 | 420 | Nocturna | En límite | Siete horas: alcanza el máximo nocturno. |
| 6 | 22:00-06:00 | 0 | 480 | Nocturna | Excede | Ocho horas: supera en una hora el máximo ordinario nocturno. |
| 7 | 05:00-12:00 | 360 | 60 | Mixta | Dentro del límite | Combina una hora nocturna y seis diurnas. |
| 8 | 19:00-22:00 | 60 | 120 | Mixta | Dentro del límite | Combina ambos periodos y no alcanza 210 minutos nocturnos. |
| 9 | 19:00-23:30 | 60 | 210 | Nocturna | Dentro del límite | El periodo nocturno alcanza exactamente 3 h 30 min. |
| 10 | 06:00-14:00 | 480 | 0 | Diurna | En límite | El límite de las 06:00 pertenece al periodo diurno. |
| 11 | 12:00-20:00 | 480 | 0 | Diurna | En límite | El intervalo termina en el inicio del periodo nocturno. |

## 6. Casos especiales

### 6.1 Jornada dividida

Cuando existen dos o más intervalos dentro de la misma fecha operativa, la clasificación deberá considerar la suma de minutos diurnos y nocturnos de todos los intervalos computables de esa jornada.

No debe clasificarse cada fragmento de forma aislada si todos pertenecen a la misma jornada.

### 6.2 Jornada incompleta

Si falta entrada, salida o una marca necesaria para cerrar un intervalo:

- No se inventará una hora.
- La clasificación quedará pendiente.
- Se generará una incidencia.
- Una corrección autorizada podrá completar el cálculo conservando trazabilidad.

### 6.3 Cambio de turno

Si una persona cambia de turno:

- El nuevo turno aplicará desde una fecha efectiva.
- Las jornadas anteriores conservarán el tipo programado de su periodo.
- No se recalculará el pasado con el turno actual.

### 6.4 Trabajo extraordinario que invade otra franja

El sistema conservará la clasificación de la jornada conforme a los intervalos resultantes y separará el análisis del tiempo extraordinario.

No debe asumirse que los minutos nocturnos adicionales son automáticamente horas extra; primero se deben aplicar las reglas de jornada diaria, semanal, pausas y autorización correspondientes.

### 6.5 Diferentes zonas horarias

Para personal móvil o centros en distintas zonas:

- Cada evento se almacenará con marca de tiempo inequívoca.
- Se conservará la zona horaria utilizada.
- La clasificación se mostrará en hora local aplicable.
- El sistema evitará clasificar usando la zona horaria del servidor.

### 6.6 Horarios fronterizos y cambios de hora

Aunque gran parte de México no realiza cambios estacionales de horario, pueden existir zonas fronterizas con reglas distintas. El motor deberá utilizar una base de zonas horarias, no desplazamientos fijos como `UTC-6`.

### 6.7 Turnos con nombre no legal

Los nombres `matutino`, `vespertino`, `tercer turno`, `24x24` u otros no sustituyen la clasificación diurna, nocturna o mixta.

## 7. Reglas de negocio derivadas

### TJ-RN-001 — Clasificación por intervalos reales

El tipo resultante se calculará con los intervalos computables realmente registrados.

### TJ-RN-002 — Conservación del tipo programado

El tipo programado se conservará junto al resultado, aun cuando sean distintos.

### TJ-RN-003 — Umbral nocturno

Una jornada combinada con 210 minutos nocturnos o más se clasificará como nocturna.

### TJ-RN-004 — Límites diarios por tipo

El motor aplicará 8 horas a la diurna, 7 a la nocturna y 7 horas 30 minutos a la mixta como máximos ordinarios diarios, salvo una condición más favorable.

### TJ-RN-005 — Validación semanal independiente

La validación diaria por tipo no sustituye la validación semanal.

### TJ-RN-006 — Fecha operativa y marcas reales

La fecha operativa facilitará agrupación y reportes, pero no reemplazará las fechas y horas reales de los eventos.

### TJ-RN-007 — Clasificación pendiente

Una jornada con datos insuficientes no recibirá una clasificación definitiva.

### TJ-RN-008 — Recalculo controlado

Una corrección autorizada podrá recalcular el tipo resultante. El sistema conservará la clasificación anterior y el motivo del cambio.

### TJ-RN-009 — Zona horaria explícita

Todo cálculo deberá conocer la zona horaria aplicable.

### TJ-RN-010 — Nombre operativo independiente

La denominación interna del turno no determinará su tipo legal.

### TJ-RN-011 — Intervalos múltiples

La clasificación tomará en cuenta todos los intervalos computables de una misma jornada.

### TJ-RN-012 — No redondeo previo

El umbral de 210 minutos se evaluará con minutos reales antes de aplicar reglas de presentación o redondeo.

### TJ-RN-013 — Límite contractual más favorable

Si contrato o política establece una duración menor, esa duración será la referencia operativa aplicable.

### TJ-RN-014 — Trazabilidad del cálculo

El sistema deberá mostrar minutos diurnos, nocturnos, tipo resultante y regla aplicada.

### TJ-RN-015 — Sin inferencia por GPS

La ubicación no determina el tipo de jornada. La clasificación depende del tiempo laborado en las franjas legales.

## 8. Requisitos funcionales mínimos

| ID | Requisito | Capacidad esperada |
| --- | --- | --- |
| TJ-RF-001 | Configurar el tipo legal programado de horarios y turnos. | Catálogo de tipos legales asociado a horarios y turnos. |
| TJ-RF-002 | Calcular minutos diurnos y nocturnos de cada jornada. | División de intervalos por franja legal. |
| TJ-RF-003 | Clasificar automáticamente la jornada resultante. | Motor de clasificación basado en minutos reales. |
| TJ-RF-004 | Identificar diferencia entre tipo programado y resultante. | Observación para revisión sin alterar el turno histórico. |
| TJ-RF-005 | Validar el máximo diario correspondiente. | Comparación contra 480, 420 o 450 minutos según tipo. |
| TJ-RF-006 | Procesar jornadas que cruzan medianoche. | Agrupación por fecha operativa y timestamps reales. |
| TJ-RF-007 | Procesar jornadas con múltiples intervalos. | Suma de intervalos computables de la misma jornada. |
| TJ-RF-008 | Mantener una fecha operativa sin perder timestamps reales. | Reportes por fecha operativa con eventos originales intactos. |
| TJ-RF-009 | Administrar zonas horarias por centro o asignación. | Cálculo en hora local aplicable, no en hora del servidor. |
| TJ-RF-010 | Dejar pendiente la clasificación cuando existan datos insuficientes. | Estado pendiente con motivo e incidencia. |
| TJ-RF-011 | Recalcular después de una corrección autorizada. | Nueva versión del cálculo con trazabilidad. |
| TJ-RF-012 | Exponer la explicación del cálculo en reportes y API. | Minutos por franja, regla aplicada y resultado. |
| TJ-RF-013 | Permitir límites más favorables por contrato, grupo o trabajador. | Comparación contra la regla más restrictiva aplicable. |
| TJ-RF-014 | Generar alertas por exceso diario sin eliminar el registro real. | Alerta de revisión con conservación íntegra de eventos. |

## 9. Datos conceptuales necesarios

Sin definir todavía tablas físicas, el dominio requerirá:

- Tipo legal programado.
- Tipo legal resultante.
- Minutos diurnos.
- Minutos nocturnos.
- Duración ordinaria computable.
- Fecha operativa.
- Zona horaria.
- Regla de clasificación aplicada.
- Estado de clasificación.
- Motivo de clasificación pendiente.
- Versión del cálculo.
- Diferencia entre tipo programado y resultante.

## 10. Pruebas mínimas

1. Intervalo completamente diurno.
2. Intervalo completamente nocturno.
3. Mixta con un minuto menos de 3 h 30 min nocturnas.
4. Nocturna con exactamente 3 h 30 min nocturnas.
5. Jornada que cruza medianoche.
6. Jornada nocturna de ocho horas.
7. Jornada dividida con periodos diurnos y nocturnos.
8. Jornada incompleta.
9. Corrección que cambia la clasificación.
10. Cambio de zona horaria.
11. Cambio de turno con vigencia futura.
12. Diferencia entre tipo programado y resultante.
13. Validación diaria correcta y semanal excedida.
14. Límite contractual menor al legal.
15. Eventos recibidos fuera de orden.

## 11. Decisiones de producto resultantes

1. Jornada 360 calculará el tipo legal; no dependerá únicamente de una selección manual.
2. El tipo programado y el resultante se conservarán por separado.
3. La clasificación trabajará con minutos, no con horas redondeadas.
4. Las jornadas incompletas no serán clasificadas mediante suposiciones.
5. La zona horaria será parte del contexto del cálculo.
6. La clasificación no dependerá de GPS, IP, foto o nombre comercial del turno.
7. Los excesos se registrarán y señalarán; no se ocultarán ni recortarán.

## 12. Pendientes y documentos relacionados

- Máximo semanal y concepto general: `01-Jornada-Laboral.md`.
- Cálculo y pago del tiempo extraordinario: `03-Horas-Extra.md`.
- Tratamiento de pausas: `04-Descansos.md`.
- Captura de eventos: `05-Registro-Electronico.md`.
- Escenarios por industria: documentación de negocio.

## 13. Criterios de aceptación

Este documento se considerará aprobado cuando:

- Reproduzca correctamente las franjas del artículo 60.
- Reproduzca correctamente los máximos diarios del artículo 61.
- Contemple el umbral exacto de 3 horas con 30 minutos.
- Distinga tipo programado y tipo resultante.
- Cubra cruces de medianoche, múltiples intervalos y zonas horarias.
- No presente GPS o biometría como requisitos para clasificar la jornada.
- Sus reglas puedan convertirse en pruebas automatizadas.

## 14. Fuentes oficiales

- Cámara de Diputados, **Ley Federal del Trabajo**, texto vigente con última reforma DOF 14-05-2026:  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- Diario Oficial de la Federación, **Decreto en materia de reducción de la jornada laboral**, publicado el 01-05-2026:  
  https://www.dof.gob.mx/nota_to_pdf.php?edicion=VES&fecha=01%2F05%2F2026
