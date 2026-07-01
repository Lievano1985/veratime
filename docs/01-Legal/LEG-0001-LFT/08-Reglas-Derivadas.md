---
id: LEG-0001-08
title: Reglas derivadas de la investigación LFT
project: Jornada 360
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-06-30
updated: 2026-06-30
depends_on:
  - LEG-0001-01
  - LEG-0001-02
  - LEG-0001-03
  - LEG-0001-04
  - LEG-0001-05
  - LEG-0001-06
  - LEG-0001-07
tags:
  - legal
  - reglas-negocio
  - cumplimiento
  - trazabilidad
---

# 08 - Reglas derivadas

## 1. Objetivo

Consolidar las reglas maestras que deben gobernar Jornada 360 a partir de la investigación contenida en los documentos `01` a `07`.

Este archivo no repite toda la investigación jurídica. Su función es:

- Resolver reglas que cruzan varios módulos.
- Definir precedencia.
- Servir como entrada para requisitos, arquitectura, datos y pruebas.
- Evitar interpretaciones contradictorias durante el desarrollo.

Las reglas específicas continúan documentadas en su capítulo de origen.

## 2. Alcance y uso

Este documento es normativo para el diseño interno del producto, pero no sustituye la Ley Federal del Trabajo ni una determinación de autoridad.

Cuando exista conflicto entre este documento y una fuente oficial vigente:

1. Se conserva la fuente oficial.
2. Se registra la discrepancia.
3. Se actualiza la regla.
4. Se incrementa la versión.
5. Se recalculan únicamente los periodos que jurídicamente deban recalcularse.

## 3. Jerarquía de aplicación

Para calcular o validar una jornada se aplicará el siguiente orden:

1. **Norma legal vigente en la fecha trabajada.**
2. **Disposición especial aplicable a la persona, actividad o modalidad.**
3. **Contrato colectivo, contrato-ley o condición individual más favorable.**
4. **Reglamento y política empresarial válida.**
5. **Horario o turno asignado.**
6. **Eventos realmente registrados.**
7. **Correcciones autorizadas y versionadas.**

Una política empresarial no podrá reducir derechos mínimos. Sí podrá establecer condiciones más favorables.

## 4. Principios transversales

### RG-001 — Hecho antes que apariencia

El sistema conservará lo que realmente ocurrió, aun cuando genere una alerta.

### RG-002 — Sin edición destructiva

Ningún evento, cálculo, expediente o entrega utilizado como evidencia se modificará o eliminará silenciosamente.

### RG-003 — Vigencia histórica

Toda regla legal, contractual y operativa tendrá fecha de inicio y, cuando corresponda, fecha de finalización.

### RG-004 — Cálculo por fecha trabajada

Los resultados históricos usarán las reglas vigentes en la fecha de la jornada, no las reglas actuales.

### RG-005 — Condición más favorable

Cuando exista una condición válida más favorable que el mínimo legal, el motor aplicará la más protectora.

### RG-006 — Explicabilidad

Todo resultado deberá poder explicar eventos, reglas, parámetros, versión y decisiones utilizadas.

### RG-007 — Separación entre alerta e infracción

Una alerta del sistema no equivale a una resolución jurídica.

### RG-008 — Separación entre registro, autorización y pago

Que el tiempo haya ocurrido, haya sido autorizado y haya sido pagado son estados independientes.

### RG-009 — Protección bilateral

La trazabilidad deberá proteger tanto a la empresa como a la persona trabajadora mediante evidencia íntegra.

### RG-010 — Multi-tenant estricto

Toda consulta, cálculo, exportación e integración estará limitada a la empresa correspondiente.

## 5. Identidad, empresa y relación laboral

### RG-011 — Persona individualizable

Cada jornada se relacionará con una persona trabajadora identificable.

### RG-012 — Relación laboral versionada

Empresa, centro, puesto, modalidad, jornada pactada y condiciones aplicables se conservarán históricamente.

### RG-013 — Empresa no inferida por dispositivo

La empresa del evento deberá provenir de una asignación o credencial válida, no solo del dispositivo utilizado.

### RG-014 — Centro y jurisdicción

Cada jornada conservará el centro y jurisdicción aplicables cuando sean relevantes para calendario, inspección o teletrabajo.

### RG-015 — Zona horaria explícita

Cada evento deberá tener una zona horaria o contexto inequívoco para determinarla.

### RG-016 — Fecha operativa separada

La fecha operativa facilitará agrupación, pero no sustituirá la fecha y hora reales.

## 6. Eventos y jornada

### RG-017 — Evento como fuente primaria

El cálculo partirá de eventos, no de un total diario editable.

### RG-018 — Inicio y finalización

Una jornada cerrada tendrá inicio y finalización o una incidencia explícita.

### RG-019 — Hora del hecho y recepción

El sistema conservará por separado cuándo ocurrió, cuándo se recibió y cuándo se procesó un evento.

### RG-020 — Jornada incompleta

La falta de una marca no se completará mediante una hora inventada.

### RG-021 — Duplicidad e idempotencia

Las fuentes e integraciones deberán impedir que un mismo evento sea procesado más de una vez.

### RG-022 — Cruce de medianoche

Una jornada podrá abarcar dos fechas civiles y mantener una sola unidad operativa.

### RG-023 — Intervalos múltiples

Una jornada podrá componerse de varios intervalos de trabajo y descanso.

### RG-024 — Corrección versionada

Toda corrección conservará dato anterior, dato nuevo, motivo, autor, fecha y autorización.

### RG-025 — Reapertura controlada

Una jornada cerrada solo podrá reabrirse mediante un flujo auditado.

## 7. Duración y tipo de jornada

### RG-026 — Máximo semanal versionado

El máximo semanal se obtendrá de parámetros legales con vigencia.

### RG-027 — Máximo diario independiente

El cumplimiento semanal no sustituye la validación del máximo diario.

### RG-028 — Tipo calculado

El tipo diurno, nocturno o mixto se calculará con minutos reales en cada franja.

### RG-029 — Programado y resultante separados

Se conservarán el tipo programado y el tipo resultante.

### RG-030 — Umbral nocturno exacto

Una jornada combinada con 210 minutos nocturnos o más se clasificará como nocturna.

### RG-031 — Sin redondeo previo

La clasificación y límites se evaluarán en minutos antes de redondear para presentación.

### RG-032 — Flexibilidad no equivale a tiempo extra

Trabajar fuera del horario esperado no implica automáticamente exceder la duración ordinaria.

## 8. Horas extraordinarias

### RG-033 — Ordinario antes que extraordinario

Primero se determinará el tiempo ordinario aplicable y después el posible excedente.

### RG-034 — Acumulación semanal

Las horas extraordinarias se acumularán en la semana configurada conforme a la regla vigente.

### RG-035 — Bandas separadas

El tiempo del artículo 66 y su excedente del artículo 68 se clasificarán por separado.

### RG-036 — Emergencia independiente

La prolongación por siniestro o riesgo inminente requerirá causa y validación específicas.

### RG-037 — Máximo total diario

Se validará que la suma de jornada ordinaria y extraordinaria no supere doce horas.

### RG-038 — Sin recorte

El tiempo que supere un límite se conservará completo y generará una alerta.

### RG-039 — Autorización no crea el hecho

La falta de autorización no elimina el tiempo realmente trabajado.

### RG-040 — Nómina recibe conceptos

Jornada 360 entregará minutos, bandas y multiplicadores; el sistema de nómina determinará el importe definitivo.

## 9. Descansos y calendario

### RG-041 — Pausa programada no demuestra disfrute

Una pausa solo se descontará cuando exista una regla válida y evidencia suficiente de que ocurrió.

### RG-042 — Permanencia a disposición

Si la persona no puede salir o permanece a disposición durante comida o reposo, ese tiempo se computará.

### RG-043 — Descanso mínimo

La jornada continua se validará contra el descanso mínimo aplicable.

### RG-044 — Día semanal histórico

El día de descanso semanal se conservará con vigencia.

### RG-045 — Seis días trabajados

El sistema detectará secuencias sin el descanso semanal requerido.

### RG-046 — Domingo independiente

Trabajar en domingo será una dimensión distinta del descanso semanal.

### RG-047 — Obligatorios versionados

Los días de descanso obligatorio se administrarán por regla, vigencia y jurisdicción.

### RG-048 — Conceptos concurrentes

Prima dominical, descanso semanal, descanso obligatorio y horas extra podrán coexistir sin fusionarse.

## 10. Registro electrónico y evidencia

### RG-049 — Registro electrónico individual

Cada persona tendrá registros electrónicos de inicio y finalización cuando la obligación resulte aplicable.

### RG-050 — Fuente tecnológica intercambiable

El dominio no dependerá de web, móvil, kiosco, reloj, QR, API o biometría específicos.

### RG-051 — Biometría y ubicación opcionales

GPS, fotografía, huella o reconocimiento facial no serán requisitos del núcleo.

### RG-052 — Acuerdo versionado

El mecanismo de registro acordado entre las partes tendrá documento, versión y vigencia.

### RG-053 — Sin consentimiento ficticio

El uso de la aplicación no se registrará automáticamente como aceptación jurídica.

### RG-054 — Expediente reproducible

Toda exportación podrá verificarse mediante manifiesto, versión e integridad.

### RG-055 — Conservación configurable

La retención combinará LFT, disposiciones STPS, contratos, controversias y protección de datos.

### RG-056 — Retención legal prioritaria

Una inspección, juicio o controversia impedirá la depuración de información relacionada.

## 11. Teletrabajo

### RG-057 — Modalidad calculable

Se conservará el porcentaje pactado y el observado de trabajo remoto.

### RG-058 — Umbral superior al 40 %

El capítulo de teletrabajo no se aplicará automáticamente a un porcentaje de 40 % exacto ni a trabajo ocasional.

### RG-059 — Lugar acordado e histórico

Los lugares de teletrabajo tendrán propuesta, validación, vigencia e historial.

### RG-060 — Domicilio restringido

El domicilio tendrá permisos reforzados y bitácora de acceso.

### RG-061 — Visita con consentimiento

La verificación física del domicilio requerirá autorización previa.

### RG-062 — Evidencia con finalidad limitada

Fotografías o videos de seguridad no se reutilizarán para supervisar productividad.

### RG-063 — Desconexión protegida

Las comunicaciones fuera de horario no podrán utilizarse para exigir respuesta inmediata por defecto.

### RG-064 — Mensaje no equivale a trabajo

Una comunicación fuera de horario solo generará tiempo trabajado cuando exista actividad confirmada.

### RG-065 — Vigilancia invasiva bloqueada

Cámara, micrófono y monitoreo continuo permanecerán deshabilitados por defecto.

### RG-066 — Reversibilidad documentada

Todo cambio entre presencial y teletrabajo será versionado y respaldado.

## 12. Inspecciones y cumplimiento

### RG-067 — Inspección como expediente

Cada inspección tendrá alcance, documentos, plazos, acta, pruebas y resolución propios.

### RG-068 — Orden validada

La empresa registrará la validación de la orden e identidad del inspector.

### RG-069 — Entrega limitada

Solo se exportarán empresa, centro, personas, periodo y documentos solicitados.

### RG-070 — Paquete inmutable

La versión entregada se conservará sin modificación.

### RG-071 — Acta separada de aclaración

El acta oficial no se editará; observaciones y pruebas se registrarán por separado.

### RG-072 — Plazo tomado de la notificación

Las fechas límite se capturarán del documento recibido.

### RG-073 — Sanción no automática

El sistema podrá registrar rangos y riesgos, pero no resolverá la infracción ni la multa definitiva.

### RG-074 — Cumplimiento posterior

Pagar una sanción no cerrará una medida correctiva pendiente.

## 13. Privacidad y seguridad

### RG-075 — Minimización

Solo se recopilarán datos necesarios para la finalidad laboral definida.

### RG-076 — Mínimo privilegio

Cada rol accederá únicamente a la información necesaria.

### RG-077 — Operaciones sensibles auditadas

Consulta de domicilios, correcciones, exportaciones y entregas quedarán registradas.

### RG-078 — Portabilidad controlada

La empresa podrá exportar su información sin exponer datos de otros tenants.

### RG-079 — Integración autenticada

Toda integración utilizará credenciales, alcance, idempotencia y bitácora.

### RG-080 — Incidente sin ocultamiento

Una falla de seguridad o integridad no se corregirá alterando silenciosamente los registros afectados.

## 14. Estados comunes

Los módulos podrán utilizar estados especializados, pero deberán mapearlos a estas categorías:

| Categoría | Significado | Uso esperado |
| --- | --- | --- |
| `OPEN` | Registro o proceso en captura. | Jornadas, expedientes o solicitudes todavía editables. |
| `PENDING_REVIEW` | Requiere revisión humana. | Incidencias, alertas, datos incompletos o reglas pendientes. |
| `VALIDATED` | Revisado conforme al flujo configurado. | Resultado aceptado operativamente. |
| `DISPUTED` | Existe desacuerdo o controversia. | Correcciones, jornadas o evidencias con posiciones distintas. |
| `CORRECTED` | Existe una nueva versión autorizada. | Resultado histórico con versión posterior vigente. |
| `CLOSED` | Proceso cerrado sin acciones ordinarias pendientes. | Expedientes, jornadas o periodos cerrados. |
| `LEGAL_HOLD` | La información no puede eliminarse. | Retención por inspección, juicio, controversia o requerimiento. |

Un estado de negocio no deberá reutilizarse para representar simultáneamente cálculo, autorización, pago y resolución jurídica.

## 15. Severidad de alertas

| Nivel | Uso | Acción general |
| --- | --- | --- |
| `INFO` | Información operativa sin acción obligatoria inmediata. | Mostrar en detalle o historial. |
| `WARNING` | Posible inconsistencia o vencimiento próximo. | Enviar a revisión o seguimiento. |
| `CRITICAL` | Riesgo relevante, plazo vencido, acceso indebido o incumplimiento potencial grave. | Escalar, bloquear acción automática o exigir autorización. |

La severidad no constituye una calificación jurídica.

## 16. Reglas que deben ser configurables

No deberán quedar como valores fijos en código:

- Máximos semanales por año.
- Bandas semanales de horas extraordinarias.
- Porcentajes y multiplicadores.
- Inicio de semana.
- Días de descanso obligatorio.
- Calendarios electorales.
- UMA.
- Políticas de conservación.
- Zonas horarias.
- Plazos capturados desde notificaciones.
- Condiciones contractuales más favorables.
- Parámetros futuros de disposiciones STPS.

## 17. Responsabilidad por módulo

| Dominio | Responsabilidad principal | No debe asumir |
| --- | --- | --- |
| Personas y relaciones | Identidad, empresa, contrato, centro y vigencias. | Cálculos de jornada o pago. |
| Horarios y turnos | Planeación y distribución esperada. | Que lo programado ocurrió realmente. |
| Eventos | Captura íntegra e idempotente. | Interpretación jurídica definitiva. |
| Motor de jornada | Reconstrucción, clasificación y duración. | Autorización empresarial o pago de nómina. |
| Descansos | Pausas, descanso semanal, domingo y obligatorios. | Compensaciones automáticas sin regla. |
| Horas extraordinarias | Bandas, acumulados y alertas. | Importe final de nómina. |
| Teletrabajo | Modalidad, lugar, equipos, costos y desconexión. | Diagnósticos médicos o vigilancia invasiva. |
| Evidencia | Versiones, manifiestos, integridad y retención. | Alterar hechos para mejorar cumplimiento aparente. |
| Inspecciones | Expedientes, entregas, actas y medidas. | Determinar sanciones definitivas. |
| Integraciones | Intercambio con nómina, dispositivos y terceros. | Ser dueñas del historial laboral. |

## 18. Criterios para convertir una regla en código

Antes de implementar una regla deberá existir:

1. Identificador.
2. Fuente o decisión de producto.
3. Datos de entrada.
4. Resultado esperado.
5. Fecha de vigencia.
6. Prioridad frente a otras reglas.
7. Casos de prueba.
8. Comportamiento ante datos incompletos.
9. Evidencia del cálculo.

Las reglas no deberán implementarse únicamente a partir de texto libre en una pantalla.

## 19. Pendientes para requisitos y arquitectura

A partir de este documento deberán definirse:

- Casos de uso.
- Requisitos funcionales consolidados.
- Contextos y módulos.
- Modelo de eventos.
- Motor de reglas versionadas.
- Modelo de auditoría.
- Política de retención.
- Contratos de API.
- Estrategia de pruebas.

La estructura física de base de datos todavía no se define en este documento.

## 20. Criterios de aceptación

Este documento se considerará aprobado cuando:

- Consolide las reglas transversales sin copiar toda la investigación.
- Defina una jerarquía clara de aplicación.
- Separe hechos, cálculos, autorizaciones, pagos y resoluciones.
- Identifique parámetros que deben versionarse.
- Cubra jornada, descansos, extras, registro, teletrabajo e inspecciones.
- Permita derivar requisitos y pruebas.
- No contradiga los documentos `01` a `07`.

## 21. Documentos fuente

- `01-Jornada-Laboral.md`
- `02-Tipos-de-Jornada.md`
- `03-Horas-Extra.md`
- `04-Descansos.md`
- `05-Registro-Electronico.md`
- `06-Teletrabajo.md`
- `07-Inspecciones-y-Sanciones.md`
- `SOURCES.md`
