---
id: LEG-0001-04
title: Descansos
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-06-30
updated: 2026-06-30
sources:
  - SRC-001
  - SRC-002
tags:
  - legal
  - descansos
  - pausas
  - prima-dominical
  - reglas-negocio
---

# 04 - Descansos

## 1. Objetivo

Definir cómo debe representar Vera Time los descansos relacionados con la jornada laboral y diferenciar:

- Descanso durante una jornada continua.
- Tiempo de comida o reposo computable.
- Descanso semanal.
- Trabajo en domingo.
- Trabajo en día de descanso semanal.
- Días de descanso obligatorio.
- Trabajo durante un descanso obligatorio.

El objetivo es evitar que el sistema confunda una pausa dentro del turno con un día completo de descanso o que descuente automáticamente tiempo que legalmente debe considerarse efectivo.

> Las vacaciones pertenecen a un régimen distinto y quedan fuera de este documento.

## 2. Alcance

Incluye:

- Artículos 63 y 64 de la Ley Federal del Trabajo.
- Artículos 69 a 75.
- Descansos programados y realmente disfrutados.
- Tiempo computable durante reposo o comida.
- Descanso semanal y operaciones continuas.
- Prima dominical.
- Descansos obligatorios.
- Datos y alertas necesarias para Vera Time.

No incluye:

- Vacaciones.
- Permisos, licencias o incapacidades.
- Fórmulas fiscales o de seguridad social.
- Reglas especiales de personas trabajadoras del hogar, campo, menores u otros trabajos especiales.
- La determinación completa de nómina.

## 3. Fundamento legal relevante

| Fundamento | Regla o criterio jurídico | Implicación para Vera Time |
| --- | --- | --- |
| LFT, artículo 63 | En una jornada continua debe concederse al menos media hora de descanso. | Detectar descanso mínimo programado y realmente disfrutado. |
| LFT, artículo 64 | Si la persona no puede salir del lugar de trabajo durante reposo o comida, ese tiempo se computa como efectivo. | Registrar si la pausa es computable y por qué. |
| LFT, artículo 69 | Por cada seis días de trabajo debe otorgarse al menos un día de descanso con salario íntegro. | Detectar secuencias sin descanso semanal. |
| LFT, artículo 70 | En labores continuas, las partes acuerdan los días de descanso semanal. | Permitir calendarios rotativos y descansos distintos del domingo. |
| LFT, artículo 71 | Se procura que el descanso semanal sea domingo y trabajar en domingo genera una prima mínima del 25 %. | Identificar trabajo dominical como dimensión independiente. |
| LFT, artículo 72 | Regula el pago proporcional del descanso cuando no se laboran todos los días de la semana o se trabaja para varios patrones. | Conservar datos suficientes para conciliación con nómina. |
| LFT, artículo 73 | Trabajar en el día de descanso semanal genera, además del salario del descanso, salario doble por el servicio. | Separar descanso semanal trabajado de domingo y horas extra. |
| LFT, artículo 74 | Enumera los días de descanso obligatorio e incluye el que determinen las leyes electorales. | Administrar catálogo versionado por fecha y jurisdicción. |
| LFT, artículo 75 | Regula la prestación de servicios y el pago cuando se trabaja en día de descanso obligatorio. | Clasificar servicio en día obligatorio como concepto separado. |
| LFT, artículo 784, fracciones IX y XI | Asigna a la parte empleadora carga probatoria sobre pagos de descansos, días obligatorios y prima dominical. | Conservar evidencia de cálculo, pago y fuente. |
| LFT, artículos 804 y 805 | Exigen conservar determinados documentos y prevén consecuencias por no exhibirlos. | Mantener registros auditables y exportables. |

## 4. Tipos de descanso que debe distinguir el sistema

| Código | Tipo | Unidad principal | Tratamiento esperado |
| --- | --- | --- | --- |
| `INTRA_SHIFT_BREAK` | Pausa dentro de la jornada | Minutos | Puede ser computable o no computable según hechos y regla. |
| `MEAL_BREAK` | Tiempo destinado a comida | Minutos | Requiere determinar si la persona puede salir o permanece a disposición. |
| `WEEKLY_REST` | Descanso semanal | Día operativo | Debe tener vigencia, calendario o rotación. |
| `SUNDAY_WORK` | Trabajo realizado en domingo | Minutos u horas | Genera revisión de prima dominical. |
| `WORKED_WEEKLY_REST` | Trabajo en el día asignado como descanso semanal | Día e intervalos | Concepto separado del domingo y de horas extra. |
| `MANDATORY_REST` | Día de descanso obligatorio | Fecha local | Debe venir de catálogo versionado y fuente oficial. |
| `WORKED_MANDATORY_REST` | Trabajo en descanso obligatorio | Día e intervalos | Concepto separado para nómina y revisión. |
| `PENDING_REST_REVIEW` | Descanso no comprobable o inconsistente | Incidencia | Requiere revisión antes de cerrar o exportar. |

Una misma jornada puede tener más de una clasificación. Por ejemplo, una persona puede trabajar en domingo y, además, ese domingo ser su día de descanso semanal.

## 5. Descanso dentro de una jornada continua

### 5.1 Duración mínima

Durante una jornada continua debe concederse un descanso de por lo menos treinta minutos.

Vera Time deberá comparar:

- Descanso programado.
- Descanso realmente iniciado.
- Descanso realmente finalizado.
- Duración disfrutada.
- Condición de salida del lugar de trabajo.
- Tratamiento como tiempo computable o no computable.

### 5.2 No debe descontarse una pausa solo porque estaba programada

Una pausa programada no demuestra que haya sido disfrutada.

El sistema solo deberá descontar tiempo cuando la configuración y los eventos permitan sostener que:

1. La pausa ocurrió.
2. Su inicio y fin pueden determinarse.
3. No debe computarse como tiempo efectivo conforme a las reglas aplicables.

Si no existe evidencia suficiente, la pausa quedará pendiente de revisión.

### 5.3 Cuando la persona no puede salir, el tiempo se considera efectivo

Si durante reposo o comida la persona no puede salir del lugar donde presta sus servicios, el artículo 64 ordena computar ese tiempo como parte de la jornada.

El sistema deberá permitir registrar, por pausa:

- `puede_salir`.
- `permanece_a_disposicion`.
- `tiempo_computable`.
- Motivo.
- Fuente de la regla.
- Evidencia o aceptación correspondiente.

La empresa no debe poder marcar de forma masiva una pausa como no computable si los hechos indican que la persona permaneció a disposición.

### 5.4 División de la media hora

La LFT establece un descanso mínimo de media hora, pero no desarrolla en estos artículos una regla general para sustituirlo por varias pausas menores.

Por seguridad, Vera Time:

- Permitirá registrar varias pausas reales.
- No asumirá automáticamente que dos pausas de quince minutos equivalen al descanso legal mínimo.
- Permitirá configurar criterios adicionales solo si están respaldados por contrato, convenio, disposición aplicable o validación jurídica.

## 6. Descanso semanal

### 6.1 Regla general

Por cada seis días de trabajo deberá existir al menos un día de descanso con salario íntegro.

El sistema debe poder detectar:

- Días consecutivos trabajados.
- Día semanal programado de descanso.
- Día realmente descansado.
- Trabajo realizado durante el descanso.
- Cambio autorizado del día de descanso.
- Rotaciones históricas.

### 6.2 Operaciones continuas

En operaciones que no pueden detenerse, los días de descanso pueden acordarse. Vera Time deberá permitir calendarios rotativos sin asumir que el domingo siempre es el descanso semanal.

### 6.3 Domingo y descanso semanal no son sinónimos

El artículo 71 procura que el descanso sea domingo, pero una persona puede:

- Descansar el domingo.
- Trabajar el domingo como parte de su jornada normal.
- Trabajar el domingo siendo además su descanso semanal.
- Trabajar el domingo cuando también es descanso obligatorio.

El sistema conservará banderas independientes para cada supuesto.

## 7. Prima dominical

Quien labore en domingo tiene derecho a una prima adicional mínima del 25 % sobre el salario de los días ordinarios.

Para Vera Time:

- La prima se identificará por tiempo trabajado en la fecha local de domingo.
- La zona horaria del centro o asignación será obligatoria.
- No se dependerá de la fecha del servidor.
- El porcentaje será versionado y permitirá una condición contractual más favorable.
- El resultado se enviará a nómina como concepto separado.

El módulo no deberá decidir que la prima desaparece porque el domingo sea un día ordinario de trabajo.

## 8. Trabajo en día de descanso semanal

La persona trabajadora no está obligada a prestar servicios en su descanso semanal. Si trabaja:

- Conserva el salario correspondiente al descanso.
- Genera un salario doble adicional por el servicio prestado.

Para clasificación funcional:

```text
pago del descanso + servicio trabajado con multiplicador 2.0
```

El sistema deberá separar:

- Salario del día de descanso.
- Tiempo efectivamente trabajado.
- Multiplicador del servicio.
- Prima dominical, cuando corresponda.
- Horas extraordinarias, cuando correspondan.

No se debe reemplazar el pago con un descanso compensatorio automático sin fundamento o validación aplicable.

## 9. Días de descanso obligatorio

El catálogo federal vigente incluye:

| Regla | Día | Tratamiento en el catálogo |
| --- | --- | --- |
| Fecha fija | 1 de enero | Generar por año y jurisdicción aplicable. |
| Fecha móvil | Primer lunes de febrero | Calcular por fórmula versionada. |
| Fecha móvil | Tercer lunes de marzo | Calcular por fórmula versionada. |
| Fecha fija | 1 de mayo | Generar por año y jurisdicción aplicable. |
| Fecha fija | 16 de septiembre | Generar por año y jurisdicción aplicable. |
| Fecha móvil | Tercer lunes de noviembre | Calcular por fórmula versionada. |
| Sexenal | 1 de octubre de cada seis años, por transmisión del Poder Ejecutivo Federal | Generar solo para años aplicables y conservar fuente. |
| Fecha fija | 25 de diciembre | Generar por año y jurisdicción aplicable. |
| Electoral | El que determinen las leyes federales y locales para elecciones ordinarias | Registrar manualmente con fuente oficial y ámbito territorial. |

### 9.1 El catálogo debe ser versionado

Los días obligatorios no se programarán como una lista permanente dentro del código.

Cada entrada deberá tener:

- Nombre.
- Tipo de regla.
- Fecha o fórmula.
- Ámbito territorial.
- Año o vigencia.
- Fuente oficial.
- Estado.
- Observaciones.

### 9.2 Días electorales

El día electoral no puede calcularse únicamente con una lista federal estática. Puede depender de leyes federales o locales.

Vera Time necesitará:

- Catálogo por jurisdicción.
- Actualización documentada.
- Fuente oficial.
- Vigencia.
- Posibilidad de asignar una fecha excepcional a determinados centros o personas.

## 10. Trabajo en descanso obligatorio

Cuando sea necesario trabajar en un día del artículo 74:

- Debe determinarse el personal que prestará servicios.
- La persona conserva el salario del descanso obligatorio.
- Genera salario doble adicional por el servicio.

Para clasificación funcional:

```text
pago del descanso obligatorio + servicio trabajado con multiplicador 2.0
```

El sistema deberá mantener por separado otros conceptos potencialmente concurrentes:

- Prima dominical.
- Trabajo en descanso semanal.
- Horas extraordinarias.
- Condiciones contractuales superiores.

Vera Time no deberá fusionar esos conceptos en uno solo ni resolver automáticamente posibles acumulaciones que requieran validación de nómina o jurídica.

## 11. Reglas de negocio derivadas

### DS-RN-001 — Descanso programado y real separados

La existencia de una pausa en el horario no probará que fue disfrutada.

### DS-RN-002 — Duración mínima de jornada continua

La validación comprobará la existencia de un descanso de al menos treinta minutos en una jornada continua.

### DS-RN-003 — Sin descuento automático

No se descontará una pausa no registrada ni confirmada mediante una regla válida.

### DS-RN-004 — Permanencia a disposición

Cuando la persona no pueda salir o permanezca a disposición durante comida o reposo, el tiempo se computará como jornada.

### DS-RN-005 — Pausas múltiples

Las pausas múltiples se conservarán, pero no se presumirá que sustituyen el descanso mínimo continuo sin regla respaldada.

### DS-RN-006 — Descanso semanal histórico

La asignación del día de descanso tendrá fecha de vigencia y no modificará semanas cerradas.

### DS-RN-007 — Control de días consecutivos

El sistema detectará secuencias de más de seis días trabajados sin descanso identificado.

### DS-RN-008 — Operación continua

Se admitirán rotaciones y descansos distintos del domingo.

### DS-RN-009 — Prima dominical independiente

Todo trabajo en domingo se marcará para cálculo de prima, aunque sea parte del turno normal.

### DS-RN-010 — Trabajo en descanso semanal

El trabajo realizado en el descanso semanal conservará el pago del descanso y clasificará el servicio con su concepto adicional.

### DS-RN-011 — Catálogo de obligatorios versionado

Los descansos obligatorios se calcularán con reglas y fuentes vigentes por fecha y jurisdicción.

### DS-RN-012 — Día electoral dinámico

Los días electorales requerirán una entrada oficial específica; no se inferirán por aproximación.

### DS-RN-013 — Conceptos concurrentes

Domingo, descanso semanal, descanso obligatorio y tiempo extraordinario serán dimensiones independientes.

### DS-RN-014 — Zona horaria local

La identificación de domingo y descanso obligatorio utilizará la fecha local aplicable a la jornada.

### DS-RN-015 — Sin edición destructiva

Una modificación de pausa o descanso conservará valores anteriores, autor, motivo y fecha.

### DS-RN-016 — Descanso compensatorio independiente

Un descanso compensatorio podrá registrarse, pero no cancelará automáticamente conceptos ya generados.

### DS-RN-017 — Jornada que cruza medianoche

Los minutos se atribuirán a la fecha local correspondiente para domingo y días obligatorios, sin perder la fecha operativa de la jornada.

### DS-RN-018 — Evidencia de disfrute

El sistema deberá poder mostrar cuándo comenzó y terminó un descanso y qué regla determinó su tratamiento.

### DS-RN-019 — Condición más favorable

Porcentajes, descansos o pagos superiores pactados por contrato o convenio prevalecerán como configuración operativa.

### DS-RN-020 — Revisión neutral

La ausencia o insuficiencia de descanso generará una alerta para revisión, no una sentencia jurídica automática.

## 12. Requisitos funcionales mínimos

| ID | Requisito | Capacidad esperada |
| --- | --- | --- |
| DS-RF-001 | Configurar pausas programadas por horario o turno. | Plantillas de pausa por política, horario o turno. |
| DS-RF-002 | Capturar inicio y fin de pausas reales. | Eventos de pausa con trazabilidad. |
| DS-RF-003 | Registrar si la persona puede salir y si permanece a disposición. | Campos de condición y evidencia. |
| DS-RF-004 | Determinar si la pausa es computable. | Motor de clasificación computable/no computable. |
| DS-RF-005 | Detectar falta o insuficiencia del descanso mínimo. | Alertas por descanso faltante o menor a treinta minutos. |
| DS-RF-006 | Configurar días de descanso semanal y rotaciones con vigencia. | Calendarios versionados por trabajador o grupo. |
| DS-RF-007 | Detectar días consecutivos trabajados. | Conteo semanal y secuencial. |
| DS-RF-008 | Detectar trabajo en el descanso semanal. | Concepto separado de descanso semanal trabajado. |
| DS-RF-009 | Calcular tiempo trabajado en domingo usando zona horaria local. | Identificación dominical por fecha local aplicable. |
| DS-RF-010 | Exponer el concepto de prima dominical para nómina. | Concepto exportable e identificable. |
| DS-RF-011 | Administrar catálogo versionado de descansos obligatorios. | Reglas por fecha, fórmula, jurisdicción y fuente. |
| DS-RF-012 | Configurar días electorales por jurisdicción. | Entradas excepcionales con fuente oficial. |
| DS-RF-013 | Detectar trabajo en descanso obligatorio. | Concepto separado de día obligatorio trabajado. |
| DS-RF-014 | Mantener separados los conceptos concurrentes. | Matriz de conceptos por jornada y fecha local. |
| DS-RF-015 | Recalcular después de una corrección autorizada. | Nueva versión de cálculo auditada. |
| DS-RF-016 | Conservar el resultado anterior y el motivo del cambio. | Historial de resultado y motivo. |
| DS-RF-017 | Generar reportes de descansos disfrutados, omitidos y trabajados. | Reportes por persona, centro, empresa y periodo. |
| DS-RF-018 | Exportar conceptos a nómina con sus eventos fuente. | Integración no destructiva. |
| DS-RF-019 | Permitir observaciones de empresa y persona trabajadora. | Comentarios trazables. |
| DS-RF-020 | Mostrar la fuente y regla utilizadas en cada clasificación. | Explicación visible y exportable. |

## 13. Datos conceptuales necesarios

Sin definir todavía tablas físicas, el dominio requerirá:

- Tipo de descanso.
- Inicio y fin programados.
- Inicio y fin reales.
- Minutos de descanso.
- Minutos computables.
- Puede salir del lugar de trabajo.
- Permanece a disposición.
- Día semanal programado de descanso.
- Día realmente descansado.
- Días consecutivos trabajados.
- Minutos trabajados en domingo.
- Porcentaje de prima dominical.
- Identificador de descanso obligatorio.
- Jurisdicción.
- Fuente y vigencia.
- Conceptos concurrentes.
- Estado de revisión.
- Motivo de corrección.
- Versión del cálculo.

## 14. Alertas mínimas

| Código | Severidad | Condición | Acción sugerida |
| --- | --- | --- | --- |
| `DS-W001` | Advertencia | Descanso programado sin eventos que acrediten su disfrute. | Revisar si procede corrección o validación. |
| `DS-W002` | Advertencia | Descanso menor de treinta minutos en jornada continua. | Enviar a revisión operativa. |
| `DS-W003` | Advertencia | Pausa pendiente de determinar como computable o no computable. | Completar condición de salida/disposición. |
| `DS-W004` | Advertencia | Trabajo en domingo pendiente de envío a nómina. | Revisar prima dominical. |
| `DS-W005` | Advertencia | Trabajo en día de descanso semanal. | Confirmar concepto y autorización. |
| `DS-W006` | Advertencia | Trabajo en descanso obligatorio. | Confirmar concepto y autorización. |
| `DS-C001` | Crítica | Más de seis días consecutivos sin descanso identificado. | Escalar a revisión de cumplimiento. |
| `DS-C002` | Crítica | Pausa descontada aunque la persona permaneció a disposición. | Recalcular como tiempo computable o documentar revisión. |
| `DS-C003` | Crítica | Día obligatorio sin fuente o vigencia válida. | Bloquear cierre automático hasta completar fuente. |
| `DS-C004` | Crítica | Diferencia entre descanso calculado y concepto pagado. | Conciliar contra nómina y registrar resultado. |
| `DS-C005` | Crítica | Cambio retroactivo de descanso sin justificación. | Requerir motivo, autorización y auditoría. |

## 15. Casos de prueba mínimos

1. Jornada continua con pausa exacta de treinta minutos.
2. Jornada continua con pausa menor de treinta minutos.
3. Pausa programada que no fue registrada.
4. Persona que puede salir durante la comida.
5. Persona que no puede salir durante la comida.
6. Pausa en la que la persona permanece a disposición.
7. Dos pausas de quince minutos sin regla adicional.
8. Descanso semanal en domingo.
9. Descanso semanal en un día distinto del domingo.
10. Operación continua con calendario rotativo.
11. Séptimo día consecutivo trabajado.
12. Trabajo normal en domingo.
13. Trabajo en domingo que también es descanso semanal.
14. Trabajo en domingo que también es descanso obligatorio.
15. Descanso obligatorio no trabajado.
16. Descanso obligatorio trabajado.
17. Jornada que inicia antes y termina después de medianoche del domingo.
18. Jornada que cruza hacia un descanso obligatorio.
19. Día electoral federal.
20. Día electoral local aplicable solo a una jurisdicción.
21. Cambio de día de descanso con vigencia futura.
22. Corrección retroactiva de una pausa.
23. Descanso compensatorio posterior.
24. Diferencia entre conceptos calculados y pagados.
25. Persona que trabaja para varios patrones, conservando la información correspondiente a la empresa usuaria.

## 16. Decisiones de producto resultantes

1. Una pausa programada no se descontará automáticamente.
2. Vera Time distinguirá descanso dentro del turno, descanso semanal y descanso obligatorio.
3. El domingo será una dimensión independiente del descanso semanal.
4. Los descansos obligatorios serán reglas versionadas por jurisdicción.
5. Los conceptos concurrentes se conservarán separados para nómina.
6. La plataforma trabajará con la fecha y zona horaria local.
7. Las correcciones no eliminarán el historial.
8. La evidencia del descanso se diseñará para respaldar a empresa y persona trabajadora.
9. Las vacaciones se desarrollarán en otro documento solo si el alcance del producto las incluye.

## 17. Pendientes y documentos relacionados

- Concepto general de jornada: `01-Jornada-Laboral.md`.
- Clasificación diurna, nocturna y mixta: `02-Tipos-de-Jornada.md`.
- Tiempo extraordinario: `03-Horas-Extra.md`.
- Registro de eventos y evidencias: `05-Registro-Electronico.md`.
- Inspecciones y conservación: `07-Inspecciones-y-Sanciones.md`.
- Reglas monetarias finales: especificación futura de integración con nómina.

## 18. Criterios de aceptación

Este documento se considerará aprobado cuando:

- Distinga correctamente todos los tipos de descanso.
- Evite descontar automáticamente pausas no demostradas.
- Aplique la regla de tiempo efectivo cuando la persona no puede salir.
- Contemple descanso semanal, domingo y días obligatorios como dimensiones independientes.
- Incluya el catálogo vigente del artículo 74.
- Permita días electorales por jurisdicción.
- Sus reglas puedan convertirse en pruebas automatizadas.
- Las referencias oficiales estén registradas en `SOURCES.md`.

## 19. Fuentes oficiales relacionadas

- `SRC-001`: Cámara de Diputados, **Ley Federal del Trabajo**, texto vigente con última reforma DOF 14-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- `SRC-002`: Diario Oficial de la Federación, **Decreto en materia de reducción de la jornada laboral**, publicado el 01-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/ref/lft/LFT_ref52_01may26.pdf


