---
id: LEG-0001-01
title: Jornada laboral
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
  - jornada-laboral
  - reglas-negocio
---

# 01 - Jornada laboral

## 1. Objetivo

Definir qué debe entender Jornada 360 por **jornada laboral**, identificar las obligaciones legales que afectan su administración y traducirlas en reglas de negocio y requisitos útiles para construir el producto.

Este documento se concentra en el concepto general de jornada. La clasificación diurna, nocturna y mixta se desarrolla en `02-Tipos-de-Jornada.md`. Las horas extraordinarias, descansos y registro electrónico se documentarán por separado.

> Este documento es una especificación de producto basada en fuentes oficiales. No sustituye asesoría jurídica para un caso concreto.

## 2. Fundamento legal relevante

| Fundamento | Regla o criterio jurídico | Implicación para Jornada 360 |
| --- | --- | --- |
| LFT, artículo 5, fracciones II y III | No produce efectos una estipulación con jornada mayor a la permitida o notoriamente excesiva. | Validar que contratos, políticas y turnos no autoricen jornadas superiores a los límites aplicables. |
| LFT, artículo 25, fracción V | La duración de la jornada forma parte de las condiciones de trabajo que deben constar por escrito cuando corresponda. | Conservar la jornada pactada como dato histórico y trazable. |
| LFT, artículo 57 | La persona trabajadora puede solicitar modificación de condiciones cuando la jornada sea excesiva. | Registrar cambios de jornada con vigencia, motivo y respaldo documental. |
| LFT, artículo 58 | La jornada es el tiempo durante el cual la persona trabajadora está a disposición de la persona empleadora. | Separar jornada real, horario, turno, eventos y cálculo. |
| LFT, artículo 59 | La jornada ordinaria tiene un máximo semanal de cuarenta horas, sujeto al régimen transitorio gradual de la reforma de 2026. | Administrar límites semanales como reglas versionadas por vigencia. |
| LFT, artículos 60 y 61 | La clasificación de la jornada determina límites diarios distintos. | Validar el máximo diario según tipo diurno, nocturno o mixto. |
| LFT, artículos 62 a 68 | Regulan límites, descansos, prolongaciones por emergencia y trabajo extraordinario. | Separar tiempo ordinario, descansos, emergencias y tiempo extraordinario. |
| Decreto publicado el 1 de mayo de 2026, transitorios segundo y tercero | Establece la reducción gradual de la jornada semanal y un periodo de ajuste durante 2026. | Usar parámetros legales por año y fecha de aplicación. |
| Decreto publicado el 1 de mayo de 2026, transitorio séptimo | La reducción de jornada no puede implicar disminución de sueldos, salarios o prestaciones. | Evitar reglas automáticas que reduzcan remuneración por la transición legal. |

## 3. Conclusiones jurídicas confirmadas

### 3.1 La jornada no equivale a una checada

Una marca de entrada o salida es un **evento de registro**. La jornada es el periodo durante el cual la persona trabajadora se encuentra a disposición de la empresa.

Por ello, Jornada 360 no debe modelar la jornada como dos columnas aisladas de entrada y salida. Debe poder relacionar:

- Condición de trabajo pactada.
- Distribución semanal.
- Horario o turno asignado.
- Eventos realmente registrados.
- Descansos e incidencias.
- Tiempo ordinario y extraordinario calculado.
- Correcciones y justificaciones posteriores.

### 3.2 La duración pactada debe conservarse históricamente

La duración de la jornada forma parte de las condiciones de trabajo. Si cambia, el sistema debe conservar:

- Valor anterior.
- Nuevo valor.
- Fecha a partir de la cual aplica.
- Motivo o documento que respalda el cambio.
- Persona que registró la actualización.

No debe modificarse retroactivamente el significado de jornadas ya cerradas.

### 3.3 La distribución puede acordarse, pero no eliminar los límites legales

El artículo 58 permite distribuir la jornada de común acuerdo. Esto habilita distintos esquemas operativos, pero no autoriza exceder los máximos legales aplicables.

Jornada 360 deberá permitir configurar distribuciones semanales y turnos, validándolos contra:

1. El máximo semanal vigente.
2. El máximo diario aplicable por tipo de jornada.
3. Las reglas de descanso.
4. Las reglas de trabajo extraordinario.
5. Condiciones más favorables derivadas de contrato individual, colectivo o política interna.

### 3.4 El máximo semanal debe ser versionado por vigencia

El texto vigente establece cuarenta horas semanales, pero el decreto ordena alcanzarlas gradualmente:

| Periodo de aplicación | Máximo semanal transitorio | Tratamiento en el sistema |
| ---: | ---: | --- |
| 2026 | 48 horas | Parámetro legal vigente durante el periodo transitorio inicial. |
| 2027 | 46 horas | Nueva versión de regla semanal aplicable por fecha trabajada. |
| 2028 | 44 horas | Nueva versión de regla semanal aplicable por fecha trabajada. |
| 2029 | 42 horas | Nueva versión de regla semanal aplicable por fecha trabajada. |
| 2030 en adelante | 40 horas | Regla semanal ordinaria objetivo. |

Estos valores no deben quedar escritos directamente en el código. Deben almacenarse como parámetros legales con fecha de inicio y fin de vigencia.

### 3.5 El sistema debe registrar la realidad, aun cuando exista una desviación

Jornada 360 no debe impedir una salida, ocultar una marca o recortar automáticamente el tiempo real porque se haya superado un límite. Eso destruiría evidencia.

El comportamiento correcto es:

1. Conservar los eventos reales.
2. Calcular el resultado con la regla vigente.
3. Identificar la desviación.
4. Presentarla como situación pendiente de revisión.
5. Permitir su regularización mediante un proceso documentado.

### 3.6 El sistema no debe emitir por sí mismo una sentencia jurídica

Una alerta del sistema no equivale a una determinación de autoridad. La interfaz debe utilizar lenguaje neutral, por ejemplo:

- `Jornada pendiente de revisión`.
- `Tiempo superior al programado`.
- `Registro incompleto`.
- `Requiere validación`.
- `Posible tiempo extraordinario`.

Debe evitar expresiones automáticas como `empresa infractora` o `violación confirmada`.

## 4. Modelo conceptual mínimo

Jornada 360 deberá distinguir los siguientes conceptos:

| Concepto | Función en el dominio | Debe conservar |
| --- | --- | --- |
| Jornada pactada | Duración y distribución establecida como condición de trabajo. | Vigencia, fuente y cambios históricos. |
| Horario | Referencia de horas esperadas de inicio, fin y pausas. | Configuración esperada por día o periodo. |
| Turno | Asignación operativa aplicable a una fecha o periodo. | Trabajador, centro, horario y vigencia. |
| Jornada registrada | Conjunto de eventos que permiten reconstruir lo ocurrido. | Eventos reales, estado y resultado calculado. |
| Evento de jornada | Entrada, salida, inicio o fin de descanso, corrección u otra marca autorizada. | Fecha, hora, zona, origen y evidencia asociada. |
| Tiempo computable | Tiempo que el motor determina como integrante de la jornada conforme a reglas vigentes. | Regla aplicada y explicación del cálculo. |
| Incidencia | Situación que impide cerrar o interpretar normalmente la jornada. | Tipo, causa, estado y responsable de atención. |
| Corrección | Regularización documentada sin eliminación silenciosa del dato original. | Valor anterior, valor nuevo, motivo y autorización. |
| Política empresarial | Configuración operativa de la empresa, subordinada a los límites legales. | Vigencia, alcance y relación con reglas legales. |
| Regla legal versionada | Parámetro normativo con vigencia definida. | Fecha de inicio, fecha de fin, fuente y versión. |

## 5. Reglas de negocio derivadas

Las reglas siguientes son especificaciones de producto derivadas del marco legal. No todas son obligaciones textuales de la LFT.

### JL-RN-001 — Separación entre jornada planeada y jornada registrada

El sistema conservará de forma independiente:

- La jornada pactada.
- El horario o turno asignado.
- Los eventos realmente registrados.
- El cálculo resultante.

### JL-RN-002 — Reglas por fecha de vigencia

Cada cálculo deberá utilizar la regla legal vigente en la fecha trabajada, no la regla vigente al momento de consultar el reporte.

### JL-RN-003 — Conservación histórica

Un cambio en horario, contrato, jornada o política no alterará jornadas cerradas de periodos anteriores.

### JL-RN-004 — Máximo semanal configurable y versionado

El máximo semanal se obtendrá del catálogo legal por vigencia. La aplicación no utilizará valores fijos como `40`, `46` o `48` dentro de la lógica de interfaz.

### JL-RN-005 — Validación simultánea

Una jornada puede cumplir el máximo semanal y exceder el diario, o viceversa. El motor deberá validar ambas dimensiones de forma independiente.

### JL-RN-006 — Registro íntegro de eventos

Los eventos efectivamente capturados se conservarán aunque produzcan una alerta o excedente.

### JL-RN-007 — Sin eliminación silenciosa

Una corrección no borrará el evento original. Se conservarán el dato previo, la razón y el resultado autorizado.

### JL-RN-008 — Jornada incompleta

La ausencia de salida no implica automáticamente que la persona trabajó hasta una hora determinada. La jornada quedará incompleta y requerirá revisión.

### JL-RN-009 — Distribución acordada

El sistema permitirá configurar la distribución semanal e identificar el documento o política que la respalda cuando la empresa decida conservarlo.

### JL-RN-010 — Condición más favorable

La empresa podrá configurar límites contractuales inferiores a los máximos legales. El motor aplicará el límite más restrictivo que corresponda a la relación laboral configurada.

### JL-RN-011 — No reducción automática de remuneración

El producto no generará una reducción salarial como consecuencia automática de la disminución gradual de la jornada.

### JL-RN-012 — Diferencia entre alerta y determinación legal

Las alertas se presentarán como resultados de validación que requieren gestión; no como declaraciones jurídicas definitivas.

### JL-RN-013 — Protección documental

La trazabilidad se diseñará como respaldo para empresa y trabajador. La finalidad será reconstruir el proceso, no exponer públicamente a la organización.

### JL-RN-014 — Política subordinada a la ley

Una política de empresa podrá establecer reglas más favorables o detalles operativos, pero no autorizar una jornada superior a la legalmente aplicable.

### JL-RN-015 — Cálculo reproducible

Todo cálculo deberá poder explicar:

- Regla legal aplicada.
- Política empresarial aplicada.
- Eventos considerados.
- Pausas descontadas o computadas.
- Resultado ordinario.
- Resultado extraordinario.
- Alertas generadas.

## 6. Requisitos funcionales mínimos

| ID | Requisito | Capacidad esperada |
| --- | --- | --- |
| JL-RF-001 | Administrar parámetros legales de jornada con fechas de vigencia. | Catálogo legal versionado. |
| JL-RF-002 | Configurar por trabajador o grupo la duración y distribución pactadas. | Políticas por empresa, grupo o persona. |
| JL-RF-003 | Asignar horarios y turnos sin reescribir el historial. | Versionado por fecha efectiva. |
| JL-RF-004 | Capturar eventos de inicio, fin y otros eventos autorizados. | Registro de eventos con origen y evidencia. |
| JL-RF-005 | Reconstruir una jornada a partir de sus eventos. | Línea de tiempo auditable. |
| JL-RF-006 | Calcular tiempo ordinario, computable y potencialmente extraordinario. | Motor de cálculo reproducible. |
| JL-RF-007 | Validar límites diarios, semanales, contractuales y de política. | Validaciones independientes por dimensión. |
| JL-RF-008 | Detectar jornadas incompletas, traslapadas o inconsistentes. | Incidencias de revisión. |
| JL-RF-009 | Permitir correcciones justificadas conservando el dato original. | Flujo de corrección con auditoría. |
| JL-RF-010 | Mostrar la regla y versión utilizadas en cada cálculo. | Explicación visible en reportes y detalle. |
| JL-RF-011 | Generar reportes por persona, empresa, centro y periodo. | Reportes filtrables y exportables. |
| JL-RF-012 | Exportar la información sin alterar los registros fuente. | Exportación no destructiva. |
| JL-RF-013 | Permitir que integraciones externas envíen trabajadores u horarios sin convertirse en dueñas del historial de jornada. | Conectores desacoplados del núcleo. |
| JL-RF-014 | Distinguir alertas operativas de alertas de cumplimiento. | Clasificación clara de alertas. |
| JL-RF-015 | Conservar el contexto local de fecha, hora y zona horaria de cada evento. | Timestamps completos y zona aplicable. |

## 7. Requisitos no funcionales relevantes

- **Aislamiento multi-tenant:** ninguna empresa podrá consultar o modificar datos de otra.
- **Trazabilidad:** las correcciones relevantes conservarán autor, fecha, motivo y valores anterior/nuevo.
- **Reproducibilidad:** un cálculo histórico deberá producir el mismo resultado mientras no exista una corrección autorizada o cambio explícito de versión.
- **Disponibilidad:** los registros deberán poder recibirse con alta continuidad; el modo offline se analizará en la especificación del registro electrónico.
- **Seguridad:** acceso por roles y principio de mínimo privilegio.
- **Privacidad:** solo se recopilará evidencia necesaria para la finalidad configurada y permitida.
- **Escalabilidad:** los eventos de jornada deberán soportar altos volúmenes sin convertir los reportes en operaciones bloqueantes.
- **Neutralidad:** el sistema informará resultados y alertas sin sustituir la evaluación jurídica humana.

## 8. Casos límite que el diseño debe contemplar

1. Jornada que inicia un día y termina al siguiente.
2. Jornada sin evento de salida.
3. Dos eventos de inicio consecutivos.
4. Eventos recibidos fuera de orden por sincronización tardía.
5. Cambio de horario a mitad de semana.
6. Cambio de centro de trabajo o zona horaria.
7. Jornada dividida en varios intervalos.
8. Registro manual posterior por falla técnica.
9. Tiempo real superior al programado.
10. Política empresarial más favorable que el máximo legal.
11. Integración externa temporalmente no disponible.
12. Corrección aprobada después del cierre de nómina.
13. Trabajador con más de una asignación dentro de la misma empresa.
14. Distribución semanal variable.
15. Registros que abarcan el cambio de año y, por tanto, distintos parámetros semanales.

## 9. Decisiones de producto resultantes

1. **Jornada 360 registrará hechos y calculará resultados; no ocultará hechos para aparentar cumplimiento.**
2. **Las reglas legales serán datos versionados, no constantes dispersas en código.**
3. **La plataforma manejará correcciones, no edición destructiva.**
4. **La trazabilidad se comunicará como respaldo documental.**
5. **La jornada pactada, el turno y los eventos reales serán entidades distintas.**
6. **La interfaz evitará emitir conclusiones jurídicas definitivas.**
7. **GPS, fotografía, biometría o dispositivo no forman parte de la definición legal general de jornada y no serán obligatorios por defecto.**

## 10. Pendientes y documentos relacionados

Este documento no resuelve por sí solo:

- Clasificación diurna, nocturna y mixta: `02-Tipos-de-Jornada.md`.
- Descansos computables: `04-Descansos.md`.
- Horas extraordinarias: `03-Horas-Extra.md`.
- Contenido y forma del registro electrónico: `05-Registro-Electronico.md`.
- Teletrabajo y desconexión: `06-Teletrabajo.md`.
- Sanciones e inspecciones: `07-Inspecciones-y-Sanciones.md`.

## 11. Criterios de aceptación

Este documento se considerará aprobado cuando:

- La definición de jornada se encuentre separada de horario, turno y evento.
- El régimen gradual semanal esté reflejado correctamente.
- Las reglas derivadas no se presenten falsamente como texto literal de la ley.
- Los requisitos permitan diseñar posteriormente el dominio, base de datos y pruebas.
- Las referencias oficiales estén registradas en `SOURCES.md`.

## 12. Fuentes oficiales

- Cámara de Diputados, **Ley Federal del Trabajo**, texto vigente con última reforma DOF 14-05-2026:  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- Diario Oficial de la Federación, **Decreto en materia de reducción de la jornada laboral**, publicado el 01-05-2026:  
  https://www.dof.gob.mx/nota_to_pdf.php?edicion=VES&fecha=01%2F05%2F2026
