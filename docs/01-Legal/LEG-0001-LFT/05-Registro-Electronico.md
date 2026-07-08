---
id: LEG-0001-05
title: Registro electrónico de jornada
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-06-30
updated: 2026-06-30
sources:
  - SRC-001
  - SRC-002
  - SRC-003
  - SRC-004
tags:
  - legal
  - registro-electronico
  - evidencia-digital
  - trazabilidad
  - reglas-negocio
---

# 05 - Registro electrónico de jornada

## 1. Objetivo

Definir los requisitos mínimos que Vera Time deberá cumplir para registrar electrónicamente la jornada laboral de cada persona trabajadora, conservar su integridad, permitir su consulta y proporcionarla a la autoridad cuando sea requerida.

Este documento distingue entre:

- Obligaciones expresamente establecidas en la Ley Federal del Trabajo.
- Elementos que quedan pendientes de las disposiciones generales de la STPS.
- Decisiones de producto necesarias para construir una solución confiable sin inventar requisitos legales.

> El sistema debe conservar los hechos registrados y permitir su explicación. No debe modificar, ocultar o completar silenciosamente información para aparentar cumplimiento.

## 2. Estado normativo al 30 de junio de 2026

La reforma publicada el 1 de mayo de 2026 adicionó la fracción XXXIV al artículo 132 de la Ley Federal del Trabajo.

La obligación legal establece que la persona empleadora deberá:

1. Registrar electrónicamente la jornada laboral de cada persona trabajadora.
2. Incluir el horario de inicio y finalización.
3. Proporcionar el registro a la autoridad cuando sea requerido.

La misma disposición ordena a la Secretaría del Trabajo y Previsión Social emitir disposiciones generales para determinar:

- El ámbito de aplicación.
- Las excepciones a la obligación.
- Los detalles operativos que la autoridad defina.

El decreto señala que esas disposiciones generales entrarán en vigor el 1 de enero de 2027.

Durante la revisión realizada para este documento no se localizaron todavía disposiciones generales definitivas publicadas por la STPS que detallen el formato técnico, los mecanismos de identificación, la conservación, la entrega o las excepciones.

Por lo tanto, Vera Time debe:

- Implementar desde ahora el núcleo legal confirmado.
- Mantener configurables los aspectos que todavía no están regulados.
- Evitar presentar como obligación definitiva cualquier requisito no publicado.
- Revisar este documento cuando la STPS emita las disposiciones correspondientes.

## 3. Fundamento legal relevante

| Fundamento | Regla o criterio jurídico | Implicación para Vera Time |
| --- | --- | --- |
| LFT, artículo 132, fracción XXXIV | Obliga a registrar electrónicamente la jornada de cada persona, incluyendo inicio y finalización, y a proporcionarla a la autoridad cuando sea requerida. | Construir el registro electrónico individual como núcleo del producto. |
| LFT, artículo 132, fracción XXXIV, segundo párrafo | La STPS deberá determinar el ámbito de aplicación y las excepciones. | Mantener parámetros configurables hasta que existan disposiciones generales definitivas. |
| LFT, artículo 132, fracción XXXIV, tercer párrafo | El contenido del registro hará prueba plena si se acredita que fue acordado entre persona trabajadora y empleadora. | Relacionar cada mecanismo de registro con acuerdo, versión, vigencia y evidencia. |
| Decreto DOF 01-05-2026, transitorio quinto | Las disposiciones generales entrarán en vigor a partir del 1 de enero de 2027. | Operar en modo de transición normativa y actualizar reglas cuando la STPS publique disposiciones. |
| LFT, artículo 994, fracción IV Bis | Prevé multa de 250 a 5000 UMA por incumplimiento de la fracción XXXIV para la persona empleadora obligada. | Generar alertas de cumplimiento sin sustituir la evaluación jurídica. |
| LFT, artículo 784 | Regula la carga probatoria de la parte empleadora en diversas controversias laborales. | Conservar evidencia y explicación de registros, cálculos y correcciones. |
| LFT, artículos 804 y 805 | Regulan la conservación y exhibición de controles de asistencia y las consecuencias de no presentarlos. | Mantener exportaciones, historial y bitácoras disponibles para revisión. |
| LFPDPPP, artículos 5, 11, 12, 13 y 14 | Exigen licitud, finalidad, proporcionalidad, información, responsabilidad y aviso de privacidad en el tratamiento de datos personales. | Aplicar minimización, aviso de privacidad, roles y controles de acceso. |

## 4. Obligaciones confirmadas

### 4.1 El registro debe ser electrónico

La solución no debe depender exclusivamente de hojas impresas, firmas en papel o archivos manuales sin estructura.

Puede existir una representación imprimible, pero la fuente principal deberá ser un registro electrónico capaz de:

- Identificar a la persona trabajadora.
- Identificar la empresa y relación laboral correspondiente.
- Conservar fecha y hora.
- Mostrar el inicio y la finalización.
- Preservar el historial de modificaciones.
- Ser consultado y exportado.

### 4.2 Debe existir un registro por persona trabajadora

Los registros no deberán almacenarse únicamente como totales por área, turno o centro de trabajo.

Cada jornada debe relacionarse de manera inequívoca con:

- Una persona trabajadora.
- Una empresa.
- Una relación o asignación laboral.
- Un periodo de trabajo.
- Los eventos que originaron el resultado.

### 4.3 Inicio y finalización son el mínimo expresamente indicado

La LFT exige que el registro incluya el horario de inicio y finalización.

Vera Time también podrá registrar otros eventos necesarios para reconstruir la jornada, como:

- Inicio y fin de descanso.
- Inicio y fin de una interrupción.
- Incidencia.
- Corrección.
- Trabajo extraordinario.
- Evento recibido fuera de línea.

Estos eventos adicionales son decisiones de producto para mejorar la exactitud y trazabilidad; no deben presentarse como contenido mínimo definitivo de las futuras disposiciones de la STPS.

### 4.4 La información debe poder proporcionarse a la autoridad

La exportación no puede consistir únicamente en una captura de pantalla.

El sistema deberá generar un expediente comprensible que contenga:

- Empresa y centro de trabajo.
- Personas incluidas.
- Periodo solicitado.
- Jornadas y eventos.
- Correcciones.
- Zona horaria.
- Regla de cálculo aplicada.
- Fecha de generación.
- Identificador del expediente.
- Manifestación de integridad.
- Formato legible para personas.
- Formato estructurado cuando resulte aplicable.

El formato definitivo deberá adaptarse a las disposiciones que publique la STPS.

### 4.5 El acuerdo entre las partes tiene relevancia probatoria

La ley señala que el contenido del registro electrónico hará prueba plena cuando se acredite que fue acordado entre la persona trabajadora y la empleadora.

Vera Time deberá permitir conservar evidencia del acuerdo sobre el mecanismo de registro, por ejemplo:

- Contrato individual.
- Convenio.
- Reglamento interior.
- Política de registro.
- Contrato colectivo.
- Aceptación electrónica de una versión.
- Acuse de entrega o conocimiento.

El sistema no debe afirmar que un registro tiene automáticamente valor probatorio pleno. Debe mostrar:

- Qué documento sustenta el acuerdo.
- Qué versión estaba vigente.
- Desde cuándo aplicaba.
- A quién fue comunicado.
- Qué evidencia de aceptación existe.

La forma jurídica suficiente para acreditar el acuerdo deberá validarse según el caso concreto y las futuras disposiciones oficiales.

## 5. Aspectos todavía pendientes de regulación

Los siguientes puntos no deben cerrarse como requisitos legales definitivos hasta que la STPS publique las disposiciones generales:

1. Empresas o sectores obligados.
2. Personas o relaciones exceptuadas.
3. Mecanismos de identificación autorizados.
4. Uso o no de firma electrónica.
5. Formatos de entrega.
6. Plazo específico de conservación del nuevo registro.
7. Requisitos de disponibilidad.
8. Reglas para trabajo sin conectividad.
9. Interoperabilidad con plataformas oficiales.
10. Contenido adicional obligatorio.
11. Forma de acreditar el acuerdo entre las partes.
12. Tratamiento de correcciones.
13. Reglas para trabajadores móviles o sin centro fijo.
14. Procedimientos para incidencias técnicas.
15. Requisitos de certificación o sellado de tiempo, si llegaran a establecerse.

## 6. Modelo conceptual del registro

Vera Time deberá separar las siguientes entidades conceptuales:

| Concepto | Descripción | Debe conservar |
| --- | --- | --- |
| Persona trabajadora | Persona a quien corresponde la jornada. | Identidad, relación y alcance de consulta. |
| Relación laboral | Vínculo con una empresa y condiciones vigentes. | Empresa, vigencia, puesto, centro y políticas aplicables. |
| Jornada | Unidad de trabajo reconstruida para una fecha operativa. | Estado, fecha operativa, eventos y resultado calculado. |
| Evento | Hecho capturado: entrada, salida, descanso, corrección u otro. | Hora del hecho, hora de recepción, zona, fuente y evidencia. |
| Fuente del evento | Web, aplicación móvil, kiosco, API, importación u otra. | Método, identificador externo e idempotencia cuando aplique. |
| Resultado calculado | Tiempo ordinario, descansos, extraordinario y alertas. | Versión, reglas aplicadas y explicación. |
| Incidencia | Situación que impide cerrar o interpretar normalmente la jornada. | Tipo, causa, estado y responsable. |
| Corrección | Ajuste documentado que conserva el valor original. | Valor anterior, valor nuevo, motivo, autorización y versión. |
| Acuerdo de registro | Documento o aceptación que respalda el mecanismo utilizado. | Documento, versión, vigencia, alcance y acuse. |
| Regla normativa | Parámetro legal vigente utilizado en el cálculo. | Fuente, versión y vigencia. |
| Expediente de autoridad | Exportación cerrada de un periodo y alcance determinados. | Alcance, manifiesto, hash, fecha y acuse. |

## 7. Contenido mínimo propuesto para cada evento

La siguiente estructura es una decisión de producto y deberá ajustarse a las disposiciones oficiales:

- Identificador único.
- Empresa.
- Persona trabajadora.
- Relación laboral.
- Tipo de evento.
- Fecha y hora del hecho.
- Zona horaria.
- Fecha operativa.
- Fuente de captura.
- Fecha y hora de recepción.
- Usuario, dispositivo o integración de origen.
- Estado de sincronización.
- Referencia al evento corregido, cuando corresponda.
- Motivo de captura manual o corrección.
- Metadatos de integridad.
- Versión del esquema.

No deberá exigirse por defecto:

- Geolocalización.
- Fotografía.
- Reconocimiento facial.
- Huella.
- Grabación continua.
- Cámara o micrófono.

Esos datos solo podrán habilitarse cuando exista una finalidad válida, base jurídica, proporcionalidad, información al titular y configuración expresa de la empresa.

## 8. Métodos de registro permitidos por el producto

Vera Time podrá recibir eventos mediante:

- Portal web.
- Aplicación móvil.
- Kiosco o terminal compartida.
- Código personal.
- Código QR.
- API.
- Integración con reloj checador.
- Importación controlada.
- Captura manual justificada.
- Sincronización posterior desde un dispositivo sin conexión.

La ley actual no obliga a utilizar una tecnología biométrica específica. Por ello, el núcleo del sistema deberá ser independiente del método de captura.

## 9. Integridad y trazabilidad

### 9.1 No eliminación destructiva

Un evento utilizado para calcular una jornada no deberá eliminarse físicamente mediante una operación ordinaria.

Cuando exista un error:

1. Se conservará el evento original.
2. Se registrará una corrección o anulación lógica.
3. Se documentará el motivo.
4. Se identificará a la persona que realizó o autorizó el cambio.
5. Se recalculará la jornada.
6. Se conservará el resultado anterior.

### 9.2 Orden de eventos

El sistema distinguirá entre:

- Hora en que ocurrió el evento.
- Hora en que fue recibido.
- Hora en que fue procesado.

Esto permite manejar registros fuera de línea o enviados con retraso sin falsificar la hora original.

### 9.3 Integridad verificable

El producto deberá poder demostrar que un expediente no fue modificado después de su cierre.

Podrá utilizar:

- Hash de contenido.
- Manifiesto de archivos.
- Versiones inmutables.
- Sellos internos de tiempo.
- Firmas digitales cuando sean configuradas o exigidas.
- Bitácora de accesos y exportaciones.

Estas medidas son decisiones técnicas de integridad; no se presentarán como certificación oficial mientras no exista una disposición que así lo reconozca.

## 10. Registro fuera de línea

Cuando no exista conexión:

- El dispositivo podrá capturar eventos localmente.
- Cada evento tendrá un identificador generado antes de sincronizarse.
- Se conservará la hora local y la zona horaria.
- Se registrará la hora de sincronización.
- Se detectarán duplicados.
- Se informará si la hora del dispositivo presenta una desviación relevante.
- El evento tardío no reemplazará automáticamente un evento ya existente.
- Las inconsistencias quedarán pendientes de revisión.

## 11. Correcciones y controversias

### 11.1 Corrección solicitada por la persona trabajadora

El sistema deberá permitir:

- Identificar la jornada.
- Indicar el dato cuestionado.
- Proponer la corrección.
- Explicar el motivo.
- Adjuntar evidencia.
- Dar seguimiento al estado.
- Conocer la resolución.

### 11.2 Corrección iniciada por la empresa

La empresa podrá proponer una corrección, pero deberá:

- Indicar motivo.
- Mostrar el valor anterior y el nuevo.
- Conservar autor y fecha.
- Aplicar un flujo de autorización.
- Permitir observación de la persona trabajadora cuando la política lo establezca.

### 11.3 Desacuerdo

Si las partes no coinciden, Vera Time no debe escoger silenciosamente una versión.

Deberá conservar:

- Registro original.
- Propuesta de corrección.
- Posición de cada parte.
- Evidencias.
- Estado de controversia.
- Resultado operativo que la empresa utilice, claramente identificado.

## 12. Entrega a la autoridad

### 12.1 Solicitud

Toda entrega deberá registrar:

- Autoridad solicitante.
- Número de oficio o expediente.
- Fecha de recepción.
- Fundamento o referencia.
- Alcance.
- Periodo.
- Personas incluidas.
- Fecha límite.
- Persona responsable de atender.

### 12.2 Generación

La exportación deberá:

- Congelar el conjunto de información entregado.
- Generar un manifiesto.
- Identificar filtros y periodo.
- Incluir fecha y zona horaria.
- Mostrar correcciones.
- Excluir datos ajenos al requerimiento.
- Evitar acceso cruzado entre empresas.
- Permitir revisión antes de la entrega.

### 12.3 Evidencia de entrega

Se conservará:

- Archivo o paquete entregado.
- Hash o identificador.
- Fecha y hora.
- Medio de entrega.
- Persona que autorizó.
- Acuse o respuesta de la autoridad.
- Versiones posteriores, si existieron.

## 13. Protección de datos personales

El registro de jornada contiene datos personales laborales.

Vera Time deberá aplicar:

- Finalidad definida.
- Proporcionalidad.
- Minimización.
- Acceso por roles.
- Aviso de privacidad.
- Confidencialidad.
- Medidas de seguridad.
- Registro de accesos relevantes.
- Procedimientos para derechos ARCO.
- Separación entre empresas.
- Contratos que definan las responsabilidades de la empresa usuaria y del proveedor SaaS.

La empresa usuaria normalmente determinará las finalidades del tratamiento. Vera Time deberá documentar contractualmente su función como proveedor y encargado del tratamiento cuando corresponda.

## 14. Conservación

Los controles de asistencia mencionados por el artículo 804 tienen reglas de conservación asociadas. Sin embargo, la conservación específica del nuevo registro electrónico puede ser detallada posteriormente por la STPS.

Hasta que exista una regla definitiva, el producto deberá:

- Permitir políticas de conservación configurables.
- Impedir eliminación durante un requerimiento, controversia o retención legal.
- Mantener respaldo recuperable.
- Separar archivo activo y archivo histórico.
- Registrar toda eliminación autorizada.
- Evitar prometer un plazo único como requisito definitivo.

La política final deberá considerar conjuntamente:

- LFT.
- Disposiciones de la STPS.
- Prescripción de acciones.
- Contratos.
- Protección de datos.
- Requerimientos sectoriales.

## 15. Reglas de negocio derivadas

### RE-RN-001 — Registro individual

Cada jornada se relacionará con una persona trabajadora y relación laboral identificables.

### RE-RN-002 — Inicio y finalización obligatorios

Una jornada cerrada deberá contener inicio y finalización o una incidencia que explique por qué falta alguno.

### RE-RN-003 — Fuente independiente

El dominio no dependerá de una tecnología específica de captura.

### RE-RN-004 — Datos reales conservados

Los eventos reales no se recortarán ni eliminarán para ajustar el resultado a límites legales.

### RE-RN-005 — Corrección no destructiva

Toda corrección conservará el evento y cálculo previos.

### RE-RN-006 — Hora del hecho y recepción separadas

Los eventos tardíos conservarán cuándo ocurrieron y cuándo fueron recibidos.

### RE-RN-007 — Zona horaria explícita

Todo evento deberá conservar una zona horaria o un contexto que permita determinarla.

### RE-RN-008 — Acuerdo versionado

El mecanismo acordado entre empresa y persona trabajadora tendrá versión y vigencia.

### RE-RN-009 — Sin aceptación ficticia

El inicio de sesión, silencio o uso de la plataforma no se presentará automáticamente como acuerdo jurídico.

### RE-RN-010 — Expediente reproducible

Una exportación deberá poder regenerarse o verificarse con los mismos datos y reglas.

### RE-RN-011 — Entrega limitada

Los expedientes para autoridad incluirán únicamente empresas, personas y periodos comprendidos en el requerimiento.

### RE-RN-012 — Sin biometría obligatoria

Biometría, GPS, fotografía y video serán capacidades opcionales, no requisitos del núcleo.

### RE-RN-013 — Identidad diferenciada

El sistema distinguirá entre la persona a quien corresponde el evento, quien lo capturó y quien lo modificó.

### RE-RN-014 — Cierre controlado

Una jornada podrá cerrarse automáticamente solo si cumple las condiciones configuradas y no tiene incidencias críticas.

### RE-RN-015 — Reapertura auditada

La reapertura de una jornada cerrada exigirá motivo, autorización y nueva versión.

### RE-RN-016 — Regla vigente por fecha

Los cálculos usarán las reglas aplicables a la fecha trabajada.

### RE-RN-017 — Multi-tenant estricto

Un identificador, exportación o integración no podrá exponer registros de otra empresa.

### RE-RN-018 — Modo transición normativa

Los parámetros pendientes de STPS se mantendrán configurables y claramente identificados como provisionales.

### RE-RN-019 — Evidencia de autoridad inmutable

Una exportación ya entregada no será reemplazada; cualquier corrección generará una nueva versión vinculada.

### RE-RN-020 — Protección de información

Los mecanismos de registro serán proporcionales y recopilarán únicamente los datos necesarios para la finalidad definida.

## 16. Requisitos funcionales mínimos

| ID | Requisito | Capacidad esperada |
| --- | --- | --- |
| RE-RF-001 | Registrar electrónicamente eventos de jornada por persona. | Bitácora individual por relación laboral. |
| RE-RF-002 | Capturar inicio y finalización con fecha, hora y zona horaria. | Timestamps completos y contexto local. |
| RE-RF-003 | Admitir múltiples fuentes de captura. | Web, móvil, kiosco, API, integración e importación. |
| RE-RF-004 | Procesar eventos fuera de línea y sincronización tardía. | Identificadores previos, recepción diferida y detección de duplicados. |
| RE-RF-005 | Detectar duplicados, traslapes e inconsistencias. | Motor de incidencias. |
| RE-RF-006 | Reconstruir jornadas a partir de eventos. | Línea de tiempo auditable. |
| RE-RF-007 | Administrar incidencias y correcciones no destructivas. | Flujos con motivo, autorización y evidencia. |
| RE-RF-008 | Mantener versiones de los cálculos. | Historial reproducible. |
| RE-RF-009 | Asociar el mecanismo de registro con un acuerdo versionado. | Relación con contrato, política o acuse. |
| RE-RF-010 | Permitir consulta de la persona trabajadora sobre sus registros. | Portal o vista de consulta. |
| RE-RF-011 | Permitir solicitudes de aclaración o corrección. | Flujo de solicitud y resolución. |
| RE-RF-012 | Generar expedientes para autoridad por alcance y periodo. | Exportación cerrada con manifiesto. |
| RE-RF-013 | Conservar evidencia de generación, revisión y entrega. | Bitácora de expediente y acuse. |
| RE-RF-014 | Exportar formatos legibles y estructurados. | PDF/CSV/JSON u otros formatos configurables. |
| RE-RF-015 | Mostrar la explicación del cálculo de cada jornada. | Reglas, eventos y resultado. |
| RE-RF-016 | Administrar políticas de conservación y retención legal. | Retención por empresa, fuente y estado legal. |
| RE-RF-017 | Registrar accesos y operaciones sensibles. | Auditoría de lectura, exportación y modificación. |
| RE-RF-018 | Implementar permisos por empresa, centro, rol y alcance. | Multi-tenant estricto. |
| RE-RF-019 | Permitir integraciones mediante API con idempotencia. | Endpoints seguros y control de duplicados. |
| RE-RF-020 | Mantener parámetros provisionales actualizables cuando la STPS publique disposiciones. | Configuración normativa versionable. |

## 17. Requisitos no funcionales

- **Integridad:** los eventos y expedientes cerrados deberán ser verificables.
- **Disponibilidad:** el registro deberá tolerar fallas temporales y permitir sincronización posterior.
- **Escalabilidad:** la arquitectura deberá procesar altos volúmenes de eventos.
- **Seguridad:** cifrado en tránsito, control de acceso y secretos protegidos.
- **Aislamiento:** separación lógica estricta entre empresas.
- **Trazabilidad:** toda operación sensible deberá quedar registrada.
- **Portabilidad:** la empresa deberá poder exportar su información.
- **Legibilidad:** un expediente deberá ser comprensible sin conocer la estructura interna de la base de datos.
- **Reproducibilidad:** un cálculo histórico deberá poder explicarse con la regla y eventos utilizados.
- **Privacidad:** la captura será proporcional a la finalidad.

## 18. Alertas mínimas

| Código | Severidad | Condición | Acción sugerida |
| --- | --- | --- | --- |
| `RE-W001` | Advertencia | Jornada sin evento de inicio. | Solicitar revisión o corrección. |
| `RE-W002` | Advertencia | Jornada sin evento de finalización. | Solicitar revisión o cierre documentado. |
| `RE-W003` | Advertencia | Evento recibido fuera de orden. | Reprocesar conservando hora del hecho y recepción. |
| `RE-W004` | Advertencia | Posible duplicado. | Revisión de idempotencia o conciliación. |
| `RE-W005` | Advertencia | Diferencia entre tipo de evento y horario esperado. | Marcar observación operativa. |
| `RE-W006` | Advertencia | Mecanismo de registro sin acuerdo vigente asociado. | Asociar documento o dejar pendiente. |
| `RE-W007` | Advertencia | Corrección pendiente de resolución. | Resolver antes del cierre definitivo. |
| `RE-C001` | Crítica | Intento de eliminar un evento utilizado. | Bloquear eliminación ordinaria y registrar intento. |
| `RE-C002` | Crítica | Intento de modificar una jornada cerrada sin autorización. | Exigir reapertura auditada. |
| `RE-C003` | Crítica | Evento asignado a una empresa incorrecta. | Aislar, investigar y corregir con trazabilidad. |
| `RE-C004` | Crítica | Exportación que incluye datos fuera del alcance solicitado. | Bloquear entrega y regenerar expediente. |
| `RE-C005` | Crítica | Expediente modificado después del cierre. | Invalidar versión y generar expediente nuevo. |
| `RE-C006` | Crítica | Acceso no autorizado a datos de jornada. | Escalar a seguridad y registrar incidente. |
| `RE-C007` | Crítica | Política provisional incompatible con una nueva disposición oficial. | Actualizar regla y revisar cálculos afectados. |

## 19. Casos de prueba mínimos

1. Entrada y salida normales.
2. Entrada sin salida.
3. Salida sin entrada.
4. Dos entradas consecutivas.
5. Eventos recibidos fuera de orden.
6. Registro sin conexión y sincronización posterior.
7. Evento duplicado enviado por API.
8. Jornada que cruza medianoche.
9. Cambio de zona horaria.
10. Corrección solicitada por la persona trabajadora.
11. Corrección iniciada por la empresa.
12. Desacuerdo entre las partes.
13. Reapertura de jornada cerrada.
14. Cambio de acuerdo de registro con vigencia futura.
15. Persona sin acuerdo vigente asociado.
16. Exportación de una sola persona.
17. Exportación masiva por centro y periodo.
18. Intento de incluir información de otra empresa.
19. Generación de nueva versión después de una corrección.
20. Verificación de hash del expediente.
21. Retención legal que impide eliminación.
22. Solicitud ARCO.
23. Captura mediante kiosco.
24. Importación desde reloj checador.
25. Actualización de parámetros después de nuevas disposiciones STPS.

## 20. Decisiones de producto resultantes

1. Vera Time será independiente del dispositivo de captura.
2. El núcleo registrará eventos, no solamente totales diarios.
3. Inicio y finalización serán el mínimo obligatorio confirmado.
4. Las correcciones serán versionadas y no destructivas.
5. La evidencia de acuerdo entre las partes será una entidad explícita.
6. Las exportaciones para autoridad se conservarán como expedientes cerrados.
7. GPS, fotografía y biometría no serán obligatorios por defecto.
8. Los requisitos todavía pendientes de STPS serán configurables.
9. El sistema no garantizará por sí solo valor probatorio pleno; conservará los elementos para acreditarlo.
10. La privacidad y minimización serán requisitos del diseño.

## 21. Pendientes y documentos relacionados

- Concepto y cálculo de jornada: `01-Jornada-Laboral.md`.
- Tipos de jornada: `02-Tipos-de-Jornada.md`.
- Horas extraordinarias: `03-Horas-Extra.md`.
- Descansos: `04-Descansos.md`.
- Teletrabajo: `06-Teletrabajo.md`.
- Entrega, inspecciones y sanciones: `07-Inspecciones-y-Sanciones.md`.
- Matriz final: `09-Matriz-Trazabilidad.md`.

## 22. Criterios de aceptación

Este documento se considerará aprobado cuando:

- No invente el contenido de las futuras disposiciones de la STPS.
- Incluya los mínimos confirmados del artículo 132, fracción XXXIV.
- Distinga eventos, jornada, cálculo, corrección y expediente.
- Contemple acuerdo entre las partes sin asumir aceptación automática.
- Permita entrega controlada a autoridad.
- Evite edición destructiva.
- Sea independiente de biometría o GPS.
- Incluya protección de datos y aislamiento multi-tenant.
- Sus reglas puedan convertirse en pruebas automatizadas.

## 23. Fuentes oficiales relacionadas

- `SRC-001`: Cámara de Diputados, **Ley Federal del Trabajo**, texto vigente con última reforma DOF 14-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- `SRC-002`: Diario Oficial de la Federación, **Decreto en materia de reducción de la jornada laboral**, publicado el 01-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/ref/lft/LFT_ref52_01may26.pdf
- `SRC-004`: Cámara de Diputados, **Ley Federal de Protección de Datos Personales en Posesión de los Particulares**, última reforma DOF 14-11-2025.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFPDPPP.pdf


