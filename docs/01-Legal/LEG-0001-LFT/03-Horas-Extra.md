---
id: LEG-0001-03
title: Horas extraordinarias
project: Jornada 360
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
  - horas-extra
  - jornada-extraordinaria
  - reglas-negocio
---

# 03 - Horas extraordinarias

## 1. Objetivo

Definir cómo debe identificar, clasificar y conservar Jornada 360 el tiempo trabajado fuera de la jornada ordinaria, tomando en cuenta los límites diarios y semanales, la aplicación gradual de la reforma de 2026 y la diferencia entre:

- Prolongación por emergencia.
- Trabajo extraordinario dentro del límite del artículo 66.
- Tiempo que supera el límite del artículo 66.
- Tiempo que supera el máximo diario absoluto.

Este documento define reglas de control y evidencia. El cálculo final de nómina deberá considerar también el salario aplicable, el contrato, el convenio colectivo y otras disposiciones que correspondan.

> Jornada 360 debe registrar el tiempo realmente trabajado aun cuando exista una desviación. Una alerta no elimina la obligación de revisar y, en su caso, pagar el tiempo correspondiente.

## 2. Alcance

Incluye:

- Artículos 65 a 68 de la Ley Federal del Trabajo.
- Aplicación gradual del límite semanal de horas extraordinarias.
- Límites diarios y semanales.
- Clasificación del tiempo por banda de pago.
- Trabajo por siniestro o riesgo inminente.
- Autorización y regularización interna.
- Evidencia y trazabilidad.
- Datos necesarios para una futura integración con nómina.

No incluye:

- Fórmulas completas de nómina, impuestos o seguridad social.
- Reglas especiales de industrias o relaciones laborales sujetas a capítulos particulares.
- Descansos semanales o días obligatorios, tratados en `04-Descansos.md`.
- El formato definitivo del registro electrónico, tratado en `05-Registro-Electronico.md`.

## 3. Fundamento legal relevante

| Fundamento | Regla o criterio jurídico | Implicación para Jornada 360 |
| --- | --- | --- |
| LFT, artículo 65 | Permite prolongar la jornada por el tiempo estrictamente indispensable ante siniestro o riesgo inminente. | Clasificar emergencias por separado y exigir causa documentada. |
| LFT, artículo 66 | Regula la prolongación por circunstancias extraordinarias, su pago y sus límites de distribución. | Administrar banda ordinaria de tiempo extraordinario con límites diarios, semanales y por días. |
| LFT, artículo 67 | El tiempo utilizado para atender la emergencia del artículo 65 se retribuye como hora ordinaria. | Asignar multiplicador ordinario a emergencias validadas, sin mezclarlas con horas extra. |
| LFT, artículo 68 | La persona trabajadora no está obligada a trabajar más del tiempo permitido; regula el excedente sobre el artículo 66 y fija un máximo total diario. | Detectar excedentes, alertar y conservar evidencia sin recortar registros reales. |
| Decreto DOF 01-05-2026, transitorio cuarto | Establece la aplicación gradual del máximo semanal previsto en el artículo 66. | Versionar límites semanales por año y fecha trabajada. |
| LFT, artículo 784, fracción VIII | Asigna a la parte empleadora carga probatoria sobre jornada ordinaria y extraordinaria cuando esta última no exceda nueve horas semanales. | Conservar evidencia probatoria sin usar nueve horas como límite universal del motor. |
| LFT, artículos 804 y 805 | Regulan la conservación de controles de asistencia y las consecuencias procesales de no exhibirlos. | Mantener registros, cálculos y auditoría disponibles para revisión. |

## 4. Conclusiones jurídicas confirmadas

### 4.1 La prolongación por emergencia no es igual al trabajo extraordinario ordinario

El artículo 65 contempla situaciones como siniestro o riesgo inminente que ponen en peligro a personas o a la propia empresa. En esos casos, la jornada puede prolongarse únicamente durante el tiempo indispensable para evitar el daño.

El artículo 67 dispone para ese tiempo una retribución equivalente a la hora ordinaria.

Jornada 360 deberá clasificarlo de forma separada y exigir al menos:

- Tipo de emergencia.
- Descripción.
- Inicio y finalización.
- Persona que reportó el evento.
- Personas afectadas.
- Evidencia o referencia documental disponible.
- Validación posterior.

No debe utilizarse la categoría de emergencia para reclasificar horas extraordinarias ordinarias.

### 4.2 El trabajo extraordinario del artículo 66 se paga al doble

El artículo 66 establece un pago de cien por ciento adicional sobre la hora ordinaria. Para fines de cálculo, representa:

```text
valor total de la hora = hora ordinaria × 2
```

Jornada 360 deberá guardar el multiplicador aplicable, pero no asumir que conoce por sí solo el salario base definitivo de nómina.

### 4.3 El máximo semanal del artículo 66 cambia según el año

La reforma de 2026 establece la siguiente aplicación gradual:

| Año de la jornada | Máximo semanal del artículo 66 | Tratamiento en el sistema |
| ---: | ---: | --- |
| 2026 | 9 horas | Parámetro transitorio vigente para fechas trabajadas en 2026. |
| 2027 | 9 horas | Nueva versión anual, aunque conserve el mismo valor. |
| 2028 | 10 horas | Nueva versión anual del máximo semanal. |
| 2029 | 11 horas | Nueva versión anual del máximo semanal. |
| 2030 en adelante | 12 horas | Regla objetivo para ejercicios posteriores. |

Además, el artículo 66 permite distribuir ese tiempo:

- En hasta cuatro horas extraordinarias por día.
- En un máximo de cuatro días dentro de la semana.

Estos parámetros deben almacenarse con vigencia. No deberán escribirse directamente como constantes dispersas en el código.

### 4.4 El excedente sobre el artículo 66 se paga al triple y tiene un límite adicional

El artículo 68 establece que el tiempo que supere el límite del artículo 66:

- No podrá superar cuatro horas adicionales por semana.
- Genera un pago de doscientos por ciento adicional sobre la hora ordinaria.

Para fines de cálculo:

```text
valor total de la hora = hora ordinaria × 3
```

El pago mayor no convierte el excedente en una práctica normal ni elimina la alerta de cumplimiento.

### 4.5 La suma diaria de jornada ordinaria y extraordinaria no puede superar doce horas

El artículo 68 fija un límite absoluto:

```text
jornada ordinaria + jornada extraordinaria <= 12 horas diarias
```

Si el registro real supera ese valor:

1. Se conservarán los eventos.
2. Se calculará el tiempo registrado.
3. Se generará una alerta crítica.
4. No se recortará el resultado a doce horas.
5. Se requerirá revisión y regularización.

### 4.6 La falta de autorización interna no borra el tiempo registrado

La empresa puede establecer un flujo de autorización previa o posterior. Sin embargo, Jornada 360 no debe convertir automáticamente una hora real en tiempo no trabajado solo porque falte autorización.

El sistema deberá distinguir:

- Tiempo registrado.
- Tiempo calculado como extraordinario.
- Tiempo autorizado por la empresa.
- Tiempo enviado a nómina.
- Tiempo pagado.
- Tiempo controvertido o pendiente.

### 4.7 La regla procesal de nueve horas no es el límite operativo después de 2027

El artículo 784, fracción VIII, conserva una referencia a nueve horas semanales para efectos de carga de la prueba. Esa disposición no debe confundirse con el máximo gradual del artículo 66.

Por tanto:

- `9 horas` no será un límite fijo del motor para todos los años.
- La plataforma conservará evidencia también cuando se superen nueve horas.
- La estrategia probatoria de un caso concreto requerirá revisión jurídica.

## 5. Clasificación funcional del tiempo

| Código | Clasificación | Referencia | Multiplicador orientativo | Estado operativo |
| --- | --- | --- | ---: | --- |
| `ORDINARY` | Tiempo ordinario | Jornada pactada y límites aplicables | 1.0 | Calculable y exportable. |
| `EMERGENCY` | Prolongación por siniestro o riesgo inminente | Artículos 65 y 67 | 1.0 | Requiere causa y validación. |
| `OVERTIME_ART66` | Extraordinario dentro de la banda vigente | Artículo 66 y transitorio cuarto | 2.0 | Calculable, autorizable y exportable. |
| `OVERTIME_EXCESS_ART68` | Excedente sobre la banda del artículo 66 | Artículo 68 | 3.0 | Calculable con alerta de revisión. |
| `OVER_DAILY_MAXIMUM` | Tiempo que rebasa doce horas totales en el día | Artículo 68 | Requiere revisión | Alerta crítica; no se recorta. |
| `PENDING_CLASSIFICATION` | Tiempo que no puede clasificarse por datos incompletos | Regla de producto | Pendiente | Incidencia abierta. |

Los multiplicadores son referencias de clasificación laboral. El importe monetario definitivo corresponde al módulo o integración de nómina.

## 6. Secuencia de cálculo propuesta

### Paso 1 — Reconstruir la jornada

Obtener los intervalos computables después de resolver:

- Entradas y salidas.
- Descansos.
- Incidencias.
- Correcciones autorizadas.
- Zona horaria.
- Fecha operativa.

### Paso 2 — Determinar el tiempo ordinario aplicable

Usar la menor duración que corresponda entre:

- Jornada pactada.
- Límite diario por tipo de jornada.
- Límite contractual o colectivo más favorable.
- Política empresarial más favorable.

### Paso 3 — Detectar tiempo potencialmente extraordinario

El tiempo computable que exceda la jornada ordinaria aplicable será candidato a extraordinario.

No debe clasificarse únicamente por estar fuera del horario programado. Puede existir un horario flexible que permanezca dentro de la duración ordinaria.

### Paso 4 — Excluir o separar emergencias

Cuando exista un evento validado bajo el artículo 65, clasificar ese intervalo como `EMERGENCY` y conservar su justificación.

### Paso 5 — Acumular por semana

Agrupar el tiempo extraordinario dentro del periodo semanal configurado, sin cambiar arbitrariamente el inicio de semana para evitar límites.

### Paso 6 — Aplicar la banda vigente

Asignar primero las horas a `OVERTIME_ART66` hasta alcanzar el máximo semanal correspondiente al año.

### Paso 7 — Aplicar el excedente del artículo 68

Las siguientes cuatro horas semanales, como máximo, se clasificarán como `OVERTIME_EXCESS_ART68` y generarán una alerta.

### Paso 8 — Validar límites diarios

Comprobar:

- Hasta cuatro horas del artículo 66 por día.
- Máximo de cuatro días con tiempo del artículo 66 dentro de la semana.
- Máximo de doce horas entre jornada ordinaria y extraordinaria por día.

### Paso 9 — Conservar explicación

El resultado deberá indicar:

- Jornada ordinaria utilizada.
- Regla anual aplicada.
- Acumulado semanal previo.
- Horas asignadas a cada banda.
- Alertas.
- Eventos fuente.
- Versión del cálculo.

## 7. Reglas de negocio derivadas

### HE-RN-001 — Cálculo por vigencia

Las horas se clasificarán con la regla vigente en la fecha en que fueron trabajadas.

### HE-RN-002 — Banda semanal gradual

El máximo semanal del artículo 66 será de 9, 9, 10, 11 o 12 horas según el año aplicable.

### HE-RN-003 — Límite adicional

Solo podrán clasificarse hasta cuatro horas semanales en la banda de excedente del artículo 68. El tiempo real adicional se conservará con alerta crítica.

### HE-RN-004 — Máximo diario absoluto

La suma de tiempo ordinario y extraordinario se validará contra doce horas diarias.

### HE-RN-005 — Distribución del artículo 66

La banda del artículo 66 admitirá hasta cuatro horas por día y un máximo de cuatro días por semana.

### HE-RN-006 — No recorte de evidencia

El motor no eliminará ni reducirá eventos para hacer que el resultado cumpla con un límite.

### HE-RN-007 — Autorización independiente

La autorización empresarial será un estado separado de la existencia y clasificación del tiempo.

### HE-RN-008 — Emergencia documentada

Una prolongación solo podrá clasificarse como emergencia cuando exista una causa registrada y validada.

### HE-RN-009 — Sin reclasificación automática por conveniencia

Una persona administradora no podrá convertir horas extraordinarias en emergencia, descanso o tiempo ordinario sin dejar trazabilidad.

### HE-RN-010 — Multiplicadores versionados

Los multiplicadores y bandas deberán estar asociados a una fuente, vigencia y versión normativa.

### HE-RN-011 — Jornada incompleta

Si falta información para cerrar la jornada, el sistema no calculará definitivamente las horas extraordinarias.

### HE-RN-012 — Corrección reproducible

Una corrección recalculará la jornada sin destruir el resultado previo.

### HE-RN-013 — Semana histórica estable

El criterio utilizado para agrupar la semana deberá conservarse históricamente y no podrá modificarse retroactivamente sin un proceso controlado.

### HE-RN-014 — Tiempo fuera de horario no equivale siempre a hora extra

El sistema comparará duración, distribución y reglas aplicables; no solo la diferencia respecto de una hora programada.

### HE-RN-015 — Pago y cumplimiento son dimensiones distintas

Que una hora genere un multiplicador de pago no significa que la prolongación haya cumplido todos los límites legales.

### HE-RN-016 — Sin compensación automática

Un descanso posterior o ajuste de horario no sustituirá automáticamente la clasificación y pago del tiempo ya trabajado.

### HE-RN-017 — Estado de pago separado

El sistema distinguirá entre `calculada`, `autorizada`, `enviada a nómina`, `pagada`, `rechazada administrativamente` y `en controversia`.

### HE-RN-018 — Rechazo sin borrado

Un rechazo administrativo deberá conservar el tiempo y el motivo; nunca eliminarlo.

## 8. Requisitos funcionales mínimos

| ID | Requisito | Capacidad esperada |
| --- | --- | --- |
| HE-RF-001 | Configurar límites semanales de horas extraordinarias con fecha de vigencia. | Catálogo legal versionado por año. |
| HE-RF-002 | Configurar límites diarios y número máximo de días con tiempo extraordinario. | Validaciones por día y semana. |
| HE-RF-003 | Calcular automáticamente tiempo ordinario y extraordinario. | Motor de cálculo por minutos. |
| HE-RF-004 | Separar las bandas de pago de los artículos 66 y 68. | Clasificación por banda y multiplicador. |
| HE-RF-005 | Registrar prolongaciones por emergencia con evidencia y validación. | Flujo específico de emergencia. |
| HE-RF-006 | Mantener estados de autorización y pago independientes del cálculo. | Estados separados para operación y nómina. |
| HE-RF-007 | Detectar excedentes diarios y semanales. | Alertas por límite excedido. |
| HE-RF-008 | Explicar la regla utilizada en cada resultado. | Detalle de cálculo reproducible. |
| HE-RF-009 | Recalcular después de una corrección autorizada. | Nueva versión de cálculo auditada. |
| HE-RF-010 | Conservar el cálculo anterior y la razón de la modificación. | Historial de cálculo y motivo. |
| HE-RF-011 | Exportar horas clasificadas para nómina sin perder los eventos fuente. | Integración no destructiva. |
| HE-RF-012 | Registrar identificadores de respuesta de la integración de nómina. | Trazabilidad de envío y respuesta externa. |
| HE-RF-013 | Impedir edición destructiva de jornadas cerradas. | Correcciones controladas. |
| HE-RF-014 | Generar reportes diarios, semanales y por periodo de nómina. | Vistas operativas y exportables. |
| HE-RF-015 | Mostrar acumulados semanales antes de autorizar nuevas horas. | Prevalidación antes de autorización. |
| HE-RF-016 | Detectar diferencias entre tiempo registrado, autorizado, enviado y pagado. | Conciliación laboral-nómina. |
| HE-RF-017 | Permitir observaciones de empresa y persona trabajadora sin sobrescribir el registro. | Comentarios trazables. |
| HE-RF-018 | Mantener la zona horaria y fecha operativa utilizadas en el cálculo. | Contexto temporal completo. |

## 9. Datos conceptuales necesarios

Sin definir todavía tablas físicas, el dominio requerirá:

- Identificador de jornada.
- Minutos ordinarios.
- Minutos de emergencia.
- Minutos del artículo 66.
- Minutos excedentes del artículo 68.
- Minutos por encima del máximo diario.
- Acumulado semanal antes y después del cálculo.
- Año y regla normativa aplicada.
- Multiplicador aplicable.
- Estado de autorización.
- Estado de envío a nómina.
- Estado de pago.
- Motivo de emergencia.
- Evidencias relacionadas.
- Versión del cálculo.
- Persona que validó o corrigió.
- Fecha de cierre.

## 10. Alertas mínimas

| Código | Severidad | Condición | Acción sugerida |
| --- | --- | --- | --- |
| `HE-W001` | Advertencia | Existe tiempo fuera de la jornada ordinaria pendiente de clasificación. | Revisar eventos y reglas aplicadas. |
| `HE-W002` | Advertencia | Se alcanzó el máximo semanal del artículo 66. | Bloquear autorización automática adicional o solicitar revisión. |
| `HE-W003` | Advertencia | Se utilizó la banda de excedente del artículo 68. | Solicitar validación antes de enviar a nómina. |
| `HE-C001` | Crítica | Se superaron cuatro horas del artículo 66 en un día. | Escalar a revisión de cumplimiento. |
| `HE-C002` | Crítica | Se superó el número máximo de días con tiempo del artículo 66. | Escalar a revisión de cumplimiento. |
| `HE-C003` | Crítica | Se superaron cuatro horas semanales adicionales del artículo 68. | Escalar y documentar regularización. |
| `HE-C004` | Crítica | La suma diaria de jornada ordinaria y extraordinaria superó doce horas. | Escalar; conservar eventos sin recorte. |
| `HE-C005` | Crítica | Se intentó clasificar una emergencia sin justificación. | Requerir causa, evidencia y autorización. |
| `HE-C006` | Crítica | Existe diferencia entre horas calculadas y horas pagadas. | Conciliar contra nómina y registrar resultado. |

Las alertas `C` son críticas para revisión, pero no deben borrar los datos reales.

## 11. Casos de prueba mínimos

1. Jornada sin tiempo extraordinario.
2. Una hora extraordinaria dentro del artículo 66.
3. Cuatro horas extraordinarias en un día.
4. Más de cuatro horas del artículo 66 en un día.
5. Horas extraordinarias en más de cuatro días de la semana.
6. Acumulado semanal exacto de 9 horas en 2026.
7. Décima hora semanal en 2026, clasificada en la banda del artículo 68.
8. Acumulado exacto de 10 horas en 2028.
9. Acumulado exacto de 12 horas en 2030.
10. Cuatro horas adicionales después de agotar la banda del artículo 66.
11. Tiempo superior al máximo adicional del artículo 68.
12. Jornada total exacta de doce horas.
13. Jornada total superior a doce horas.
14. Emergencia validada y pagada a multiplicador ordinario.
15. Supuesta emergencia sin evidencia.
16. Hora fuera del horario programado, pero dentro de la duración ordinaria flexible.
17. Jornada incompleta.
18. Corrección que modifica el acumulado semanal.
19. Cambio de año dentro de la misma semana operativa.
20. Tiempo calculado y no enviado a nómina.
21. Tiempo enviado a nómina con respuesta de error.
22. Tiempo rechazado administrativamente que permanece en evidencia.

## 12. Decisiones de producto resultantes

1. Las horas extraordinarias se calcularán en minutos y se presentarán en horas.
2. Los límites se almacenarán por vigencia.
3. Emergencia y trabajo extraordinario serán categorías distintas.
4. La autorización no determinará si el tiempo ocurrió.
5. Jornada 360 no recortará registros superiores a un límite.
6. El módulo laboral entregará a nómina horas, bandas, multiplicadores y evidencia; nómina determinará el importe final.
7. Las alertas de cumplimiento no sustituirán una determinación jurídica.
8. Los cambios de clasificación deberán quedar auditados.
9. La referencia de nueve horas del artículo 784 se tratará como regla probatoria, no como límite universal de cálculo.

## 13. Pendientes y documentos relacionados

- Jornada ordinaria y máximo semanal: `01-Jornada-Laboral.md`.
- Tipo y máximo diario: `02-Tipos-de-Jornada.md`.
- Pausas y descansos: `04-Descansos.md`.
- Evidencia electrónica: `05-Registro-Electronico.md`.
- Inspecciones y sanciones: `07-Inspecciones-y-Sanciones.md`.
- Fórmulas monetarias: especificación futura de integración con nómina.

## 14. Criterios de aceptación

Este documento se considerará aprobado cuando:

- La aplicación gradual de 2026 a 2030 esté correctamente representada.
- Se distingan artículos 65, 66, 67 y 68.
- Se separen cálculo, autorización y pago.
- Se contemple el máximo diario de doce horas.
- Las reglas puedan convertirse en pruebas automatizadas.
- No se utilice el valor de nueve horas como constante permanente.
- Las referencias oficiales estén registradas en `SOURCES.md`.

## 15. Fuentes oficiales relacionadas

- `SRC-001`: Cámara de Diputados, **Ley Federal del Trabajo**, texto vigente con última reforma DOF 14-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- `SRC-002`: Diario Oficial de la Federación, **Decreto en materia de reducción de la jornada laboral**, publicado el 01-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/ref/lft/LFT_ref52_01may26.pdf
