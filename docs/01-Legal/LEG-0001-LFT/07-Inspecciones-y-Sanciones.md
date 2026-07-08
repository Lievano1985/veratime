---
id: LEG-0001-07
title: Inspecciones y sanciones
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-06-30
updated: 2026-06-30
sources:
  - SRC-001
  - SRC-002
  - SRC-006
  - SRC-007
  - SRC-008
  - SRC-009
  - SRC-010
tags:
  - legal
  - inspeccion-laboral
  - sanciones
  - evidencia
  - reglas-negocio
---

# 07 - Inspecciones y sanciones

## 1. Objetivo

Definir cómo debe apoyar Vera Time la atención de inspecciones laborales relacionadas con jornada, asistencia, descansos, horas extraordinarias, teletrabajo y registro electrónico.

El sistema deberá permitir reunir, revisar, entregar y conservar evidencia sin sustituir a la autoridad, al representante legal ni al equipo responsable de cumplimiento.

> Vera Time no determinará automáticamente que una empresa cometió una infracción ni calculará una multa definitiva. Identificará riesgos, conservará evidencia y administrará el expediente.

## 2. Alcance

Incluye:

- Facultades generales de inspección laboral.
- Inspecciones ordinarias y extraordinarias.
- Inspecciones presenciales, documentales o apoyadas por tecnologías de información.
- Validación de la orden y del inspector.
- Preparación y entrega de información.
- Actas, observaciones, pruebas y requerimientos.
- Conservación de controles de asistencia.
- Sanciones directamente relacionadas con el alcance de Vera Time.
- Trazabilidad del expediente.

No incluye:

- Representación jurídica.
- Presentación automática de promociones ante la autoridad.
- Cálculo definitivo del monto en pesos de una sanción.
- Sustitución del procedimiento administrativo sancionador.
- Inspecciones de materias ajenas al producto, salvo que se integren expresamente.

## 3. Fundamento legal relevante

| Fundamento | Regla o criterio jurídico | Implicación para Vera Time |
| --- | --- | --- |
| LFT, artículos 540 a 543 | Definen funciones, facultades, obligaciones de la inspección y valor de los hechos asentados en actas. | Modelar actas, hechos observados y evidencia relacionada. |
| LFT, artículo 541, fracción IV | El inspector puede exigir libros, registros y documentos exigidos por las normas laborales. | Preparar paquetes documentales por alcance y materia. |
| LFT, artículos 804 y 805 | Regulan conservación y exhibición en juicio de contratos, nómina, controles de asistencia y otros documentos, así como la presunción derivada de no exhibirlos. | Aplicar retención, conservación y trazabilidad de documentos fuente. |
| LFT, artículo 992 | Establece criterios de cuantificación, reincidencia, sanción por cada persona afectada e independencia entre infracciones. | Registrar UMA, reincidencia y personas afectadas como datos de riesgo, no como multa automática. |
| LFT, artículo 994, fracciones I y IV Bis | Sanciona determinados incumplimientos de jornada y descanso, y el incumplimiento del registro electrónico por la persona empleadora obligada. | Relacionar alertas de jornada y registro electrónico con posibles fundamentos. |
| LFT, artículos 1000 a 1002 | Regulan infracciones vinculadas con contratos colectivos, reglamento interior y normas sin sanción específica. | Permitir vincular observaciones a contrato colectivo, reglamento o norma general. |
| LFT, artículo 1004-A | Sanciona impedir la inspección y prevé requerimiento posterior de información. | Registrar intentos, negativa, requerimientos posteriores y acuses. |
| Reglamento General de Inspección del Trabajo y Aplicación de Sanciones | Regula el procedimiento de inspección y aplicación de sanciones. | Administrar expediente, plazos, modalidad, acta y procedimiento sancionador. |
| Reforma al Reglamento, DOF 23-08-2022 | Reconoce inspecciones presenciales, mediante TIC y por requerimientos documentales; modifica plazos y procedimiento. | Soportar inspección presencial, remota, documental o mixta. |
| Lineamientos Operativos de Inspección Federal del Trabajo 2025 | Establecen criterios operativos para la actuación inspectiva federal. | Mantener campos para criterios operativos, programas y documentación solicitada. |

## 4. Facultades de la inspección

La Inspección del Trabajo puede:

- Vigilar el cumplimiento de las normas laborales.
- Asesorar a empresas y personas trabajadoras sobre su cumplimiento.
- Visitar centros de trabajo durante horarios diurnos o nocturnos.
- Entrevistar a personas trabajadoras y empleadoras.
- Exigir libros, registros y documentos obligatorios.
- Hacer constar incumplimientos en actas.
- Dar vista a otras autoridades cuando corresponda.

Los hechos certificados por los inspectores en el ejercicio de sus funciones se tienen por ciertos mientras no se demuestre lo contrario. Por ello, Vera Time deberá permitir relacionar cada observación con la evidencia que la confirma, aclara o controvierte.

## 5. Formas de inspección

Después de la reforma reglamentaria de 2022, la inspección puede desarrollarse:

- Presencialmente en el centro de trabajo.
- Mediante tecnologías de información y comunicaciones.
- Mediante requerimientos documentales.
- Mediante mecanismos análogos autorizados.

El módulo no deberá asumir que toda inspección exige una visita física.

## 6. Tipos principales de inspección

### 6.1 Ordinarias

Comprenden, entre otras:

- Iniciales.
- Periódicas.
- De comprobación.

Como regla general, la inspección ordinaria se practica con citatorio previo entregado al menos con veinticuatro horas de anticipación. El citatorio debe identificar el centro, fecha, tipo de inspección, orden, documentos solicitados, aspectos a revisar y fundamento.

### 6.2 Extraordinarias

Pueden ordenarse sin citatorio previo, incluso en días u horas inhábiles, cuando la autoridad conozca, entre otros supuestos:

- Quejas o denuncias.
- Posibles incumplimientos.
- Información falsa o irregular.
- Accidentes o siniestros.
- Riesgo inminente.
- Necesidad de verificar medidas previamente ordenadas.
- Estrategias específicas del programa de inspección.

La falta de citatorio previo no elimina la obligación de la autoridad de presentar la orden correspondiente y de identificarse.

## 7. Validación inicial de la diligencia

Antes de entregar información, la empresa deberá poder registrar y verificar:

- Autoridad federal o local.
- Número y fecha de la orden.
- Código verificador, cuando exista.
- Nombre del inspector.
- Credencial y vigencia.
- Centro de trabajo.
- Materia.
- Objeto y alcance.
- Fundamento.
- Fecha y hora.
- Persona que atenderá.
- Documentos solicitados.

Vera Time podrá enlazar al mecanismo oficial de validación, pero no deberá afirmar que verificó una orden cuando la consulta oficial no se haya completado.

## 8. Derechos y obligaciones durante la inspección

### 8.1 Obligaciones de la empresa

- Permitir el acceso cuando exista una orden válida.
- Otorgar facilidades administrativas.
- Proporcionar la información y documentación exigible dentro del alcance.
- Permitir entrevistas y recorridos autorizados.
- Designar a la persona que atiende.
- Evitar ocultamiento, alteración o fabricación de evidencia.

### 8.2 Derechos de la empresa

- Recibir la orden correspondiente.
- Verificar la identidad del inspector.
- Conocer el objeto y alcance.
- Formular aclaraciones para que se asienten en el acta.
- Revisar el acta antes de su cierre.
- Recibir copia.
- Presentar observaciones y pruebas dentro del plazo aplicable.
- Denunciar irregularidades de la actuación inspectiva.

El portal oficial de la STPS informa como referencia un plazo de cinco días hábiles posteriores a la inspección para presentar observaciones y pruebas. El sistema deberá conservar el plazo concreto indicado en el acta o notificación y no sustituirlo por una constante universal.

## 9. Documentación relacionada con jornada

Dependiendo del alcance, una inspección puede requerir:

- Contratos individuales.
- Contrato colectivo o contrato-ley.
- Reglamento interior.
- Horarios y turnos.
- Controles de asistencia.
- Registro electrónico de jornada.
- Descansos y días laborados.
- Horas extraordinarias.
- Pagos y recibos.
- Prima dominical.
- Incidencias y correcciones.
- Acuerdos sobre mecanismos de registro.
- Políticas y evidencia de teletrabajo.
- Inventarios, listas y capacitación de NOM-037, cuando aplique.

Vera Time deberá generar la evidencia desde sus fuentes originales y no desde tablas editadas manualmente para la inspección.

## 10. Conservación documental

El artículo 804 de la LFT establece, para los documentos ahí señalados:

- Contratos individuales: durante la relación laboral y hasta un año después.
- Nóminas, recibos, controles de asistencia y comprobantes de determinadas prestaciones: durante el último año y un año después de que termine la relación laboral.
- Otros documentos: conforme a la ley que los rija.

La nueva obligación de registro electrónico puede recibir reglas específicas adicionales mediante disposiciones de la STPS. Por ello, Vera Time deberá:

- Administrar políticas de conservación versionadas.
- Aplicar el plazo más amplio que corresponda entre obligaciones configuradas.
- Impedir eliminación durante inspección, juicio, controversia o retención legal.
- Registrar toda depuración autorizada.
- Conservar copias de expedientes entregados.

El producto no deberá presentar el plazo del artículo 804 como la única política posible para todos los registros.

## 11. Expediente de inspección

Cada inspección se administrará como un expediente independiente.

### 11.1 Datos generales

- Empresa.
- Centro de trabajo.
- Autoridad.
- Tipo y modalidad.
- Número de orden.
- Alcance.
- Materias.
- Fecha de notificación.
- Fecha de inicio.
- Persona responsable.
- Representante legal.
- Estado.

### 11.2 Documentos del expediente

- Citatorio.
- Orden.
- Identificación o validación del inspector.
- Lista de documentos solicitados.
- Paquete preparado.
- Manifestaciones.
- Acta.
- Observaciones.
- Pruebas.
- Requerimientos.
- Medidas correctivas.
- Resolución.
- Sanción.
- Medio de defensa, cuando se registre.
- Acuses.

### 11.3 Estados propuestos

```text
RECIBIDA
VALIDANDO_ORDEN
PREPARANDO_EVIDENCIA
EN_REVISION
LISTA_PARA_ENTREGA
INSPECCION_EN_CURSO
ACTA_RECIBIDA
EN_PERIODO_DE_OBSERVACIONES
EN_CORRECCION
PROCEDIMIENTO_SANCIONADOR
RESUELTA
CERRADA
```

Los estados son operativos y no representan por sí mismos una conclusión jurídica.

## 12. Generación del paquete de evidencia

El paquete deberá:

1. Respetar empresa, centro, personas y periodo solicitados.
2. Incluir únicamente datos comprendidos en el alcance.
3. Mostrar eventos originales y correcciones.
4. Indicar zona horaria.
5. Identificar reglas y versiones aplicadas.
6. Incluir manifiesto de archivos.
7. Generar hash o medio de verificación.
8. Registrar fecha y persona que lo generó.
9. Pasar por revisión antes de la entrega.
10. Conservar exactamente la versión entregada.

El sistema deberá impedir que una exportación mezcle accidentalmente datos de distintos tenants.

## 13. Acta, observaciones y pruebas

### 13.1 Acta

El acta registra cumplimientos e incumplimientos detectados. Vera Time deberá permitir capturar:

- Hecho asentado.
- Fundamento citado.
- Documento relacionado.
- Persona o periodo afectado.
- Manifestación realizada durante la diligencia.
- Clasificación interna.
- Responsable de atención.

### 13.2 Observaciones

Por cada hecho señalado se podrá preparar:

- Aclaración.
- Evidencia.
- Referencia al registro fuente.
- Responsable.
- Fecha límite.
- Estado de revisión.
- Documento presentado.
- Acuse.

### 13.3 Medidas correctivas

La corrección de un proceso no deberá alterar retroactivamente la evidencia histórica. El expediente debe distinguir:

- Situación observada.
- Corrección operativa.
- Fecha de aplicación.
- Personas o periodos corregidos.
- Evidencia de implementación.
- Necesidad de recalcular o pagar diferencias.

## 14. Procedimiento sancionador

Cuando las actas y pruebas no desvirtúan un posible incumplimiento, la autoridad puede iniciar el procedimiento administrativo sancionador.

Vera Time deberá registrar, sin automatizar la estrategia jurídica:

- Emplazamiento.
- Hechos imputados.
- Fundamentos.
- Plazo para responder.
- Defensas y pruebas.
- Audiencia, cuando corresponda.
- Cierre de instrucción.
- Resolución.
- Monto en UMA y pesos.
- Fecha de la UMA utilizada.
- Cumplimiento o medio de defensa.

El reglamento establece que el término para contestar el emplazamiento no puede ser inferior a quince días hábiles. El plazo concreto deberá capturarse desde la notificación.

## 15. Sanciones relevantes para el producto

| Supuesto | Fundamento | Rango legal | Tratamiento en Vera Time |
| --- | --- | ---: | --- |
| Incumplimiento de duración diaria del artículo 61 o descanso semanal del artículo 69 | LFT 994, fracción I | 50 a 250 UMA | Mostrar como riesgo vinculado a jornada o descanso. |
| Incumplimiento del registro electrónico por la persona empleadora obligada | LFT 994, fracción IV Bis | 250 a 5000 UMA | Mostrar como riesgo vinculado al registro electrónico. |
| Incumplimiento sobre remuneración, jornada o descansos contenidos en contrato-ley o colectivo | LFT 1000 | 250 a 5000 UMA | Requiere vínculo documental con contrato aplicable. |
| Violación al Reglamento Interior de Trabajo | LFT 1001 | 50 a 500 UMA | Requiere vínculo con reglamento y política interna. |
| Violación sin sanción específica | LFT 1002 | 50 a 5000 UMA | Registrar como fundamento general, sujeto a revisión jurídica. |
| Impedir inspección y vigilancia | LFT 1004-A | 250 a 5000 UMA | Registrar hechos, requerimientos y evidencia de atención. |

La tabla no implica que todas las sanciones se acumulen en cada caso. La autoridad determina la infracción y su cuantificación conforme al procedimiento aplicable.

## 16. Reglas generales de cuantificación

El artículo 992 dispone que:

- La UMA aplicable es la vigente al momento de la violación.
- Se considera intencionalidad, gravedad, daños, capacidad económica y reincidencia.
- La reincidencia duplica la multa anterior.
- Si una conducta afecta a varias personas, puede imponerse sanción por cada persona afectada.
- Si una conducta constituye distintas infracciones, pueden aplicarse sanciones independientes.

Por ello, el sistema no debe mostrar como “multa estimada” el simple resultado de multiplicar un mínimo por una cantidad de personas sin revisión jurídica.

## 17. Reglas de negocio derivadas

### IS-RN-001 — Expediente independiente

Cada inspección tendrá un expediente separado por empresa y centro de trabajo.

### IS-RN-002 — Alcance obligatorio

Ninguna exportación se generará sin registrar materia, periodo, personas y documentos solicitados.

### IS-RN-003 — Validación antes de entrega

La orden y la identidad del inspector se registrarán antes de liberar información, salvo una situación documentada que exija actuación inmediata.

### IS-RN-004 — Fuente original

La evidencia se obtendrá de los registros fuente y no de documentos reconstruidos sin trazabilidad.

### IS-RN-005 — Copia exacta

Se conservará la versión exacta de todo paquete entregado.

### IS-RN-006 — Exportación mínima

El paquete excluirá información ajena al alcance.

### IS-RN-007 — Revisión de cuatro ojos

La entrega requerirá al menos generación y aprobación por personas distintas cuando la configuración empresarial lo exija.

### IS-RN-008 — Plazos capturados

Los plazos se tomarán de la notificación concreta; no se deducirán únicamente de valores predeterminados.

### IS-RN-009 — Retención legal

La apertura de una inspección impedirá eliminar los registros relacionados.

### IS-RN-010 — Acta no editable

El acta recibida se conservará sin alteración; las aclaraciones se registrarán por separado.

### IS-RN-011 — Corrección prospectiva

Una medida correctiva no modificará silenciosamente el historial observado.

### IS-RN-012 — Prueba vinculada

Toda observación interna deberá vincularse con documentos o registros fuente.

### IS-RN-013 — Autoridad diferenciada

El sistema distinguirá autoridad federal, estatal y otra competente.

### IS-RN-014 — Modalidad diferenciada

La inspección podrá registrarse como presencial, remota, documental o mixta.

### IS-RN-015 — UMA versionada

Los valores de UMA se almacenarán por fecha de vigencia.

### IS-RN-016 — Sin determinación automática

Una alerta de Vera Time no se convertirá automáticamente en infracción o sanción.

### IS-RN-017 — Multiplicidad no calculada sin revisión

El posible efecto por persona afectada se mostrará como riesgo, no como multa definitiva.

### IS-RN-018 — Evidencia multi-tenant

Ningún expediente podrá incluir datos de otra empresa.

### IS-RN-019 — Acceso restringido

Los expedientes tendrán permisos específicos y bitácora de acceso.

### IS-RN-020 — Nueva versión para correcciones

Una entrega corregida generará una nueva versión vinculada con la anterior.

### IS-RN-021 — Acuse obligatorio

Toda entrega deberá conservar acuse, medio o evidencia de recepción cuando exista.

### IS-RN-022 — Sanción no extingue cumplimiento

El cierre de una sanción no cerrará automáticamente las medidas correctivas pendientes.

## 18. Requisitos funcionales mínimos

| ID | Requisito | Capacidad esperada |
| --- | --- | --- |
| IS-RF-001 | Crear expedientes de inspección por empresa y centro. | Expediente independiente con alcance y estado. |
| IS-RF-002 | Registrar citatorio, orden, inspector, autoridad, alcance y modalidad. | Datos de diligencia y validación inicial. |
| IS-RF-003 | Validar o registrar la validación oficial de la orden. | Evidencia de consulta o motivo de imposibilidad. |
| IS-RF-004 | Administrar documentos solicitados y responsables. | Checklist con responsables, fechas y estado. |
| IS-RF-005 | Generar paquetes de evidencia con manifiesto e integridad. | Exportación con hash, versión y alcance. |
| IS-RF-006 | Aplicar revisión y autorización antes de entregar. | Flujo de aprobación configurable. |
| IS-RF-007 | Conservar versiones y acuses. | Historial de entregas y recepción. |
| IS-RF-008 | Registrar acta, hechos observados y fundamentos. | Captura estructurada de observaciones. |
| IS-RF-009 | Administrar observaciones, pruebas y fechas límite. | Seguimiento de plazos y responsables. |
| IS-RF-010 | Gestionar medidas correctivas sin alterar el historial. | Plan de acción separado de evidencia histórica. |
| IS-RF-011 | Aplicar retención legal a registros relacionados. | Bloqueo de depuración por expediente. |
| IS-RF-012 | Registrar emplazamiento y procedimiento sancionador. | Flujo de procedimiento y resolución. |
| IS-RF-013 | Administrar valores históricos de UMA. | Catálogo por vigencia. |
| IS-RF-014 | Registrar sanciones sin presentarlas como cálculo automático. | Captura de resolución y monto determinado. |
| IS-RF-015 | Generar reportes de expedientes, vencimientos y acciones pendientes. | Panel operativo de cumplimiento. |
| IS-RF-016 | Mantener bitácora de acceso, generación, descarga y entrega. | Auditoría de operaciones sensibles. |
| IS-RF-017 | Restringir información por rol y empresa. | Control multi-tenant y mínimo privilegio. |
| IS-RF-018 | Relacionar hechos con jornadas, personas, pagos y documentos. | Trazabilidad desde acta hasta evidencia. |
| IS-RF-019 | Permitir adjuntar medio de defensa y resolución final. | Documentación posterior al acta. |
| IS-RF-020 | Mantener separadas resolución, pago de multa y cumplimiento correctivo. | Estados independientes por dimensión. |

## 19. Alertas mínimas

| Código | Severidad | Condición | Acción sugerida |
| --- | --- | --- | --- |
| `IS-W001` | Advertencia | Orden pendiente de validación. | Validar antes de liberar información o documentar excepción. |
| `IS-W002` | Advertencia | Documento solicitado pendiente. | Asignar responsable y fecha límite. |
| `IS-W003` | Advertencia | Fecha límite próxima. | Escalar a responsable del expediente. |
| `IS-W004` | Advertencia | Evidencia sin registro fuente asociado. | Vincular fuente o marcar como evidencia externa. |
| `IS-W005` | Advertencia | Acta recibida sin captura de hechos. | Capturar hechos y fundamentos. |
| `IS-W006` | Advertencia | Medida correctiva pendiente. | Dar seguimiento a plan de acción. |
| `IS-C001` | Crítica | Intento de exportar datos de otra empresa. | Bloquear exportación y registrar incidente. |
| `IS-C002` | Crítica | Intento de modificar un paquete entregado. | Generar nueva versión, no alterar la original. |
| `IS-C003` | Crítica | Fecha límite vencida. | Escalar a responsable legal o cumplimiento. |
| `IS-C004` | Crítica | Eliminación solicitada durante retención legal. | Bloquear eliminación. |
| `IS-C005` | Crítica | Entrega sin aprobación requerida. | Detener envío o registrar excepción autorizada. |
| `IS-C006` | Crítica | Diferencia entre paquete aprobado y paquete entregado. | Investigar y generar versión corregida. |
| `IS-C007` | Crítica | Acceso no autorizado al expediente. | Escalar a seguridad y auditoría. |

## 20. Casos de prueba mínimos

1. Inspección ordinaria con citatorio.
2. Inspección extraordinaria sin citatorio.
3. Inspección documental.
4. Inspección remota.
5. Orden validada oficialmente.
6. Orden que no puede validarse.
7. Alcance limitado a un centro y periodo.
8. Exportación que intenta incluir otra empresa.
9. Paquete con correcciones históricas.
10. Revisión y autorización por personas distintas.
11. Acta con varios hechos observados.
12. Observaciones presentadas dentro del plazo.
13. Plazo vencido.
14. Retención legal activa.
15. Medida correctiva sin alteración histórica.
16. Emplazamiento sancionador.
17. Sanción expresada en UMA y pesos.
18. Reincidencia registrada.
19. Varias personas potencialmente afectadas.
20. Nueva versión de una entrega.
21. Cierre de multa con corrección aún pendiente.
22. Intento de borrar un expediente cerrado.

## 21. Decisiones de producto resultantes

1. La inspección será un expediente, no una simple exportación.
2. Vera Time permitirá inspecciones presenciales, remotas y documentales.
3. Los plazos serán datos capturados y auditables.
4. El paquete entregado será inmutable y versionado.
5. Las sanciones se registrarán, pero no se resolverán automáticamente.
6. El sistema protegerá estrictamente el alcance y aislamiento de cada empresa.
7. Las correcciones operativas permanecerán separadas de la evidencia histórica.
8. El cumplimiento correctivo continuará aunque exista una sanción pagada.

## 22. Pendientes y documentos relacionados

- Registro de jornada: `05-Registro-Electronico.md`.
- Teletrabajo y NOM-037: `06-Teletrabajo.md`.
- Reglas consolidadas: `08-Reglas-Derivadas.md`.
- Matriz de trazabilidad: `09-Matriz-Trazabilidad.md`.
- Disposiciones generales de la STPS sobre registro electrónico: pendientes de publicación o actualización oficial.

## 23. Criterios de aceptación

Este documento se considerará aprobado cuando:

- Distinga inspecciones ordinarias y extraordinarias.
- Contemple modalidad presencial, remota y documental.
- Registre orden, inspector, alcance y plazos.
- Conserve la versión exacta entregada.
- Aplique retención legal.
- No calcule automáticamente una sanción definitiva.
- Distinga multa y cumplimiento correctivo.
- Proteja los datos por empresa y alcance.
- Sus reglas puedan convertirse en requisitos y pruebas.

## 24. Fuentes oficiales relacionadas

- `SRC-001`: Cámara de Diputados, **Ley Federal del Trabajo**, texto vigente con última reforma DOF 14-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- `SRC-002`: Diario Oficial de la Federación, **Decreto en materia de reducción de la jornada laboral**, publicado el 01-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/ref/lft/LFT_ref52_01may26.pdf
- `SRC-007`: Cámara de Diputados, **Reglamento General de Inspección del Trabajo y Aplicación de Sanciones**, publicado el 17-06-2014.  
  https://www.diputados.gob.mx/LeyesBiblio/regla/n395.pdf
- `SRC-008`: Cámara de Diputados, **Reforma al Reglamento General de Inspección del Trabajo y Aplicación de Sanciones**, DOF 23-08-2022.  
  https://www.diputados.gob.mx/LeyesBiblio/norma/reglamento/reg079_23ago22.doc
- `SRC-009`: Secretaría del Trabajo y Previsión Social, **Proceso de inspección - Conoce a tu inspector**.  
  https://conocetuinspector.stps.gob.mx/Publico/ProcesoInspeccion.aspx
- `SRC-010`: Secretaría del Trabajo y Previsión Social, **Lineamientos Operativos en Materia de Inspección Federal del Trabajo 2025**.  
  https://www.dof.gob.mx/2025/STPS/AvisoIFT.pdf


