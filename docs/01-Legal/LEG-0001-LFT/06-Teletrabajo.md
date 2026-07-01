---
id: LEG-0001-06
title: Teletrabajo
project: Jornada 360
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-06-30
updated: 2026-06-30
sources:
  - SRC-001
  - SRC-003
  - SRC-004
  - SRC-005
tags:
  - legal
  - teletrabajo
  - desconexion
  - nom-037
  - reglas-negocio
---

# 06 - Teletrabajo

## 1. Objetivo

Definir cómo debe identificar y administrar Jornada 360 las relaciones laborales bajo la modalidad de teletrabajo, incluyendo:

- Aplicabilidad.
- Condiciones pactadas.
- Jornada y desconexión.
- Lugar de trabajo.
- Equipos e insumos.
- Costos.
- Seguridad y salud.
- Privacidad.
- Supervisión.
- Reversibilidad.
- Evidencia documental.

Este documento no convierte Jornada 360 en un sistema completo de seguridad y salud en el trabajo. Define la información y flujos que la plataforma deberá conservar o integrar para respaldar el cumplimiento.

## 2. Alcance normativo

Incluye:

- Capítulo XII Bis de la Ley Federal del Trabajo, artículos 330-A a 330-K.
- NOM-037-STPS-2023, Teletrabajo — Condiciones de seguridad y salud en el trabajo.
- Registro electrónico de jornada cuando resulte aplicable.
- Protección de datos personales asociada a supervisión y evidencias.
- Reglas de producto para empresas privadas en México.

No incluye:

- Dictámenes médicos.
- Evaluación clínica.
- Nómina completa.
- Administración integral de comisiones de seguridad e higiene.
- Casos del sector público sujetos a ordenamientos distintos.
- Trabajo ocasional o esporádico que no alcance la modalidad legal de teletrabajo.

## 3. Fundamento legal relevante

| Fundamento | Regla o criterio jurídico | Implicación para Jornada 360 |
| --- | --- | --- |
| LFT, artículo 330-A | Define teletrabajo y establece el umbral de más del 40 % del tiempo fuera del centro, en el domicilio de la persona o el elegido por ella. Excluye lo ocasional o esporádico. | Calcular porcentaje pactado y observado sin clasificar automáticamente todo trabajo remoto como teletrabajo. |
| LFT, artículo 330-B | Exige condiciones por escrito y establece contenido mínimo del contrato. | Vincular modalidad, lugar, horario, equipo y costos con documentos versionados. |
| LFT, artículos 330-C y 330-D | Obligan a incorporar la modalidad en contrato colectivo o reglamento interior, según corresponda, y establecer mecanismos de vinculación. | Relacionar la modalidad con políticas internas y mecanismos de comunicación. |
| LFT, artículo 330-E | Establece obligaciones especiales de la persona empleadora. | Registrar equipos, costos, capacitación, desconexión y seguridad de la información. |
| LFT, artículo 330-F | Establece obligaciones especiales de la persona trabajadora. | Registrar obligaciones, acuses, reportes y participación en verificaciones. |
| LFT, artículo 330-G | Regula voluntariedad y reversibilidad. | Modelar cambios de modalidad con solicitud, resolución, vigencia e historial. |
| LFT, artículo 330-H | Exige igualdad de trato, perspectiva de género y conciliación de la vida personal. | Evitar métricas o flujos que penalicen injustificadamente la modalidad. |
| LFT, artículo 330-I | Exige supervisión proporcional, privacidad y uso extraordinario de cámaras y micrófonos. | Bloquear vigilancia invasiva por defecto y exigir justificación. |
| LFT, artículos 330-J y 330-K | Remiten a la NOM y establecen atribuciones de inspección. | Preparar evidencia exportable para inspección sin reemplazar procesos de seguridad y salud. |
| NOM-037-STPS-2023 | Detalla condiciones de seguridad y salud, listas, políticas, capacitación, equipos, lugar de trabajo y evidencias. | Administrar listas, lugares, capacitación, evidencias y seguimiento. |
| LFPDPPP | Regula el tratamiento de datos personales recabados en el teletrabajo. | Restringir domicilio, evidencias y supervisión bajo finalidad y proporcionalidad. |

## 4. Determinación de aplicabilidad

### 4.1 Umbral legal

La modalidad se rige por el Capítulo XII Bis cuando la relación se desarrolla:

```text
más del 40 % del tiempo
```

en:

- El domicilio de la persona trabajadora.
- Un domicilio elegido por ella.
- Un lugar fijo, privado, fuera del centro de trabajo y acordado con la persona empleadora conforme a la NOM.

El teletrabajo ocasional o esporádico no se considera incluido en esta modalidad legal.

### 4.2 El sistema debe calcular, no solo preguntar

Jornada 360 deberá conservar:

- Porcentaje pactado de teletrabajo.
- Días o periodos remotos programados.
- Días o periodos efectivamente remotos.
- Periodo utilizado para calcular el porcentaje.
- Fuente de la configuración.
- Fecha de inicio y finalización.
- Cambios de modalidad.

El sistema podrá mostrar una alerta cuando la operación real supere de manera sostenida el porcentaje pactado.

### 4.3 No todo trabajo fuera de oficina es teletrabajo

Deberán distinguirse:

| Modalidad | Descripción | Tratamiento en el sistema |
| --- | --- | --- |
| Presencial | Trabajo habitual en instalaciones de la empresa. | No activar flujos de teletrabajo salvo cambio documentado. |
| Teletrabajo | Más del 40 % en lugar externo acordado y mediante TIC. | Exigir documentos, lugar, políticas, equipos, costos y verificaciones aplicables. |
| Híbrido no sujeto al capítulo | Trabajo remoto que no supera el umbral, sujeto a revisión del caso. | Registrar modalidad y monitorear porcentaje real sin activar obligaciones completas automáticamente. |
| Ocasional o esporádico | Trabajo remoto excepcional. | Registrar evento o autorización sin cambiar la modalidad laboral por sí solo. |
| Móvil o de campo | Actividad cuya naturaleza implica desplazamiento y no necesariamente teletrabajo. | Tratar como escenario operativo distinto, vinculado a registro de jornada y evidencia proporcional. |

El sistema no deberá clasificar automáticamente como teletrabajo toda conexión realizada desde fuera de la oficina.

## 5. Condiciones por escrito

El contrato de teletrabajo debe contener, además de las condiciones generales:

- Identificación y domicilio de las partes.
- Naturaleza y características del trabajo.
- Salario, fecha, lugar o forma de pago.
- Equipo e insumos entregados.
- Descripción y monto de los costos asumidos por la empresa.
- Mecanismos de contacto y supervisión.
- Duración y distribución de horarios.
- Otras estipulaciones acordadas.

Jornada 360 deberá permitir vincular:

- Contrato o convenio.
- Versión.
- Vigencia.
- Acuse o firma.
- Política de teletrabajo.
- Reglamento interior o contrato colectivo.
- Lugar o lugares acordados.
- Horario y jornada aplicables.

El sistema no sustituirá el documento jurídico con una simple configuración interna.

## 6. Obligaciones especiales de la empresa

Jornada 360 deberá permitir registrar o integrar evidencia de:

1. Equipos proporcionados.
2. Instalación y mantenimiento.
3. Silla ergonómica e insumos.
4. Costos de telecomunicaciones.
5. Parte proporcional de electricidad.
6. Inventario de bienes.
7. Seguridad de la información.
8. Derecho a la desconexión.
9. Inscripción a seguridad social.
10. Capacitación y asesoría.
11. Mecanismos de adaptación a la modalidad.
12. Listas de verificación de seguridad y salud.
13. Atención a accidentes.
14. Reversibilidad.
15. Mecanismos de atención en casos de violencia familiar cuando corresponda.

No todos estos procesos deben ejecutarse dentro de Jornada 360. La plataforma deberá definir cuáles administra directamente y cuáles acredita mediante integración o documento.

## 7. Obligaciones especiales de la persona trabajadora

La plataforma podrá apoyar el registro de:

- Recepción y cuidado de equipos.
- Reporte de costos pactados.
- Cumplimiento de medidas de seguridad y salud.
- Uso de mecanismos de supervisión autorizados.
- Cumplimiento de políticas de protección de datos.
- Aviso de cambios de domicilio.
- Avisos de accidentes o alteraciones de condiciones.
- Participación en capacitación.
- Autoaplicación de listas de verificación.

La existencia de una obligación no autoriza vigilancia desproporcionada.

## 8. Lugar de teletrabajo

### 8.1 Lugar acordado

La NOM exige que los lugares propuestos y convenidos permitan:

- Conectividad.
- Condiciones de seguridad y salud.
- Instalaciones eléctricas adecuadas.
- Iluminación.
- Ventilación.
- Condiciones ergonómicas.

El sistema deberá conservar:

- Identificador del lugar.
- Tipo de lugar.
- Domicilio protegido.
- Persona trabajadora.
- Empresa.
- Fecha de propuesta.
- Fecha de acuerdo.
- Estado de validación.
- Vigencia.
- Lista de verificación.
- Próxima revisión.
- Cambios reportados.

### 8.2 Domicilio como dato protegido

El domicilio no deberá mostrarse en reportes generales ni estar disponible para cualquier administrador.

Se requerirán:

- Permisos restringidos.
- Enmascaramiento parcial.
- Registro de accesos.
- Finalidad documentada.
- Exportación limitada.
- Protección contractual y técnica.

### 8.3 Cambio de domicilio

La persona trabajadora deberá poder informar un cambio definitivo o temporal.

El flujo deberá:

1. Registrar fecha de solicitud.
2. Identificar lugar anterior y propuesto.
3. Aplicar verificación.
4. Conservar resolución.
5. Definir fecha efectiva.
6. Evitar borrar el lugar histórico.

## 9. Verificación de seguridad y salud

### 9.1 Alternativas

La NOM permite:

- Visita física, con autorización previa de la persona trabajadora.
- Autoaplicación de la lista de verificación.
- Comprobación a distancia mediante TIC o evidencias, cuando corresponda.

Jornada 360 no deberá asumir que la empresa puede entrar al domicilio sin consentimiento.

### 9.2 Lista inicial y periódica

El sistema deberá permitir:

- Plantillas versionadas.
- Aplicación inicial.
- Aplicación periódica.
- Respuestas.
- Evidencias opcionales.
- Validación de la Comisión de Seguridad e Higiene.
- Hallazgos.
- Plan de acción.
- Fecha de resolución.
- Próxima revisión.

### 9.3 Evidencia fotográfica o de video

La NOM contempla evidencias fotográficas o de video cuando existan, pero no convierte la grabación permanente en un requisito.

El sistema deberá:

- Solicitar solo evidencia necesaria.
- Informar la finalidad.
- Restringir acceso.
- Permitir ocultar elementos ajenos al área de trabajo cuando sea viable.
- Definir conservación.
- Evitar reutilización para supervisión de productividad.

## 10. Equipos, mobiliario e insumos

El inventario deberá contemplar:

- Equipo de cómputo.
- Silla ergonómica.
- Impresora, cuando corresponda.
- Herramientas.
- Insumos.
- Aditamentos ergonómicos.
- Accesorios de conectividad.
- Fecha de entrega.
- Estado.
- Número de serie.
- Mantenimiento.
- Devolución.
- Evidencia de recepción.

El módulo deberá distinguir:

- Bien propiedad de la empresa.
- Bien propiedad de la persona trabajadora autorizado para uso.
- Insumo consumible.
- Servicio reembolsable.
- Equipo pendiente de mantenimiento.

## 11. Costos del teletrabajo

El sistema deberá permitir configurar conceptos como:

- Telecomunicaciones.
- Electricidad.
- Otros servicios pactados.

Por cada concepto:

- Método de cálculo.
- Monto o porcentaje.
- Periodicidad.
- Vigencia.
- Documento que lo respalda.
- Estado de pago.
- Integración con nómina o cuentas por pagar.

Jornada 360 no determinará unilateralmente el monto legal correcto. Conservará la regla pactada y su ejecución.

## 12. Jornada, pausas y desconexión

### 12.1 La jornada sigue sujeta a límites

El teletrabajo no elimina:

- Máximos diarios.
- Máximo semanal.
- Descansos.
- Horas extraordinarias.
- Días de descanso.
- Registro electrónico cuando resulte aplicable.

### 12.2 Derecho a la desconexión

El sistema deberá poder identificar periodos protegidos:

- Después de la jornada.
- Horarios no laborables.
- Pausas convenidas en horarios flexibles.
- Vacaciones.
- Permisos.
- Licencias.

La desconexión implica que la persona pueda apartarse del trabajo y abstenerse de participar en comunicaciones laborales durante esos periodos.

### 12.3 Contacto no equivale siempre a trabajo

Un correo o mensaje fuera de horario no debe convertirse automáticamente en tiempo trabajado sin análisis.

Jornada 360 deberá distinguir:

- Comunicación enviada.
- Comunicación recibida.
- Actividad realizada.
- Evento de jornada.
- Solicitud de trabajo.
- Tiempo confirmado como trabajado.
- Incidencia de desconexión.

### 12.4 Notificaciones

El producto deberá permitir:

- Silenciar notificaciones fuera del horario.
- Programar envíos.
- Marcar comunicaciones urgentes.
- Registrar excepciones justificadas.
- Evitar métricas que castiguen la falta de respuesta durante la desconexión.

## 13. Supervisión, intimidad y datos personales

### 13.1 Proporcionalidad

Las herramientas de supervisión deberán ser proporcionales a su objetivo.

No se deberá habilitar por defecto:

- Grabación permanente.
- Cámara continua.
- Micrófono continuo.
- Capturas constantes de pantalla.
- Registro indiscriminado de teclado.
- Seguimiento permanente de ubicación.
- Monitoreo de dispositivos personales.

### 13.2 Cámaras y micrófonos

La LFT permite su uso para supervisar teletrabajo únicamente:

- De manera extraordinaria.
- Cuando la naturaleza de las funciones lo requiera.

Por tanto, cualquier capacidad de este tipo deberá requerir:

- Justificación.
- Finalidad.
- Periodo.
- Autorización interna.
- Aviso de privacidad.
- Acceso restringido.
- Política de conservación.
- Evaluación jurídica.

### 13.3 Métricas de productividad

Las métricas deberán basarse preferentemente en:

- Entregables.
- Tareas.
- Objetivos.
- Cumplimiento de jornada.
- Resultados acordados.

No deberán utilizar vigilancia invasiva como sustituto de una gestión adecuada.

## 14. Voluntariedad y reversibilidad

### 14.1 Cambio voluntario

El cambio de presencial a teletrabajo deberá:

- Constar por escrito.
- Tener fecha efectiva.
- Relacionarse con un contrato o convenio.
- Conservar la voluntad de las partes.
- Registrar excepciones de fuerza mayor cuando correspondan.

### 14.2 Reversibilidad

El sistema deberá permitir:

- Solicitud de retorno.
- Causa.
- Modalidad temporal o permanente.
- Plazos pactados.
- Resolución.
- Fecha efectiva.
- Devolución o reasignación de bienes.
- Cambio de lugar y horario.
- Conservación histórica.

La reversibilidad no deberá ejecutarse mediante modificación directa del registro anterior.

## 15. Capacitación y seguimiento

La NOM exige capacitación al menos una vez al año sobre las condiciones de seguridad y salud que deben mantenerse en el lugar de trabajo.

Jornada 360 deberá permitir registrar o integrar:

- Curso.
- Versión.
- Fecha.
- Personas convocadas.
- Asistencia.
- Evaluación.
- Evidencia.
- Vigencia.
- Próxima capacitación.

También podrá relacionar manuales, guías e instructivos proporcionados.

## 16. Accidentes e incidencias

El sistema deberá permitir reportar:

- Accidente.
- Incidente.
- Alteración de condiciones de seguridad.
- Fuerza mayor.
- Riesgo ergonómico.
- Riesgo psicosocial.
- Falla de equipo.
- Cambio del lugar.
- Violencia familiar que afecte la modalidad.

Cada reporte deberá conservar:

- Fecha de elaboración.
- Fecha del acontecimiento.
- Persona.
- Lugar.
- Descripción.
- Evidencia.
- Personas notificadas.
- Acciones.
- Resultado.
- Decisión de continuidad o reversibilidad.

El sistema no emitirá diagnósticos médicos.

## 17. Lista de personas en teletrabajo

La NOM exige un listado actualizado con información de las personas bajo esta modalidad.

Jornada 360 deberá permitir generar el listado con:

- Nombre.
- Datos requeridos por la NOM.
- Actividades.
- Puesto.
- Porcentaje de teletrabajo.
- Contacto.
- Lugar acordado.
- Centro de trabajo.
- Equipos proporcionados.

Debido a que incluye domicilio y otros datos personales, el reporte deberá tener acceso restringido y registrar quién lo genera.

## 18. Reglas de negocio derivadas

### TT-RN-001 — Umbral calculable

El sistema distinguirá el porcentaje pactado del porcentaje realmente observado.

### TT-RN-002 — Ocasional no es teletrabajo automático

Una jornada remota aislada no cambiará por sí sola la modalidad laboral.

### TT-RN-003 — Vigencia histórica

Los cambios de modalidad, lugar, horario y porcentaje tendrán fecha efectiva.

### TT-RN-004 — Documento jurídico asociado

Toda modalidad de teletrabajo deberá relacionarse con el contrato, convenio o documento aplicable.

### TT-RN-005 — Lugar acordado

Solo los lugares propuestos, acordados y validados podrán figurar como lugar activo de teletrabajo.

### TT-RN-006 — Visita con consentimiento

No se registrará una visita física como autorizada sin evidencia de consentimiento previo.

### TT-RN-007 — Alternativa de autoevaluación

La negativa a una visita no impedirá por sí sola el proceso cuando pueda aplicarse la lista de verificación conforme a la NOM.

### TT-RN-008 — Domicilio restringido

El domicilio tendrá permisos más estrictos que la información operativa ordinaria.

### TT-RN-009 — Evidencia con finalidad limitada

Fotos y videos de seguridad y salud no se reutilizarán para supervisión de productividad.

### TT-RN-010 — Desconexión protegida

No se exigirán respuestas durante periodos de desconexión configurados.

### TT-RN-011 — Mensaje no equivale a tiempo trabajado

La comunicación fuera de horario requerirá confirmar si produjo actividad laboral.

### TT-RN-012 — Supervisión proporcional

Toda herramienta de supervisión tendrá finalidad, alcance y vigencia.

### TT-RN-013 — Cámara excepcional

El uso de cámara o micrófono para supervisión requerirá justificación extraordinaria o funcional.

### TT-RN-014 — Costos versionados

Los conceptos de telecomunicaciones y electricidad tendrán regla, vigencia y documento de soporte.

### TT-RN-015 — Inventario trazable

La entrega, mantenimiento y devolución de equipos conservarán evidencia.

### TT-RN-016 — Capacitación anual

El sistema alertará cuando la capacitación anual se encuentre pendiente o vencida.

### TT-RN-017 — Verificación periódica

La lista de seguridad y salud tendrá periodicidad y próxima fecha.

### TT-RN-018 — Reversibilidad documentada

Todo retorno a presencial conservará solicitud, resolución y fecha efectiva.

### TT-RN-019 — Igualdad de trato

Los reportes permitirán detectar diferencias operativas sin asumir automáticamente discriminación.

### TT-RN-020 — Registro de jornada independiente

Teletrabajo será una dimensión de la relación laboral; no sustituirá los eventos de jornada.

### TT-RN-021 — Accidente no diagnosticado

El sistema registrará hechos reportados, no conclusiones clínicas.

### TT-RN-022 — Multi-tenant y mínimo privilegio

Los datos personales y evidencias de una empresa no serán accesibles por otra ni por roles no autorizados.

## 19. Requisitos funcionales mínimos

| ID | Requisito | Capacidad esperada |
| --- | --- | --- |
| TT-RF-001 | Configurar modalidad y porcentaje de teletrabajo con vigencia. | Versionado por relación laboral. |
| TT-RF-002 | Calcular porcentaje real por periodo configurable. | Comparación entre operación real y modalidad pactada. |
| TT-RF-003 | Asociar contrato, convenio, reglamento y política. | Repositorio documental vinculado. |
| TT-RF-004 | Administrar lugares propuestos, acordados, validados e históricos. | Catálogo restringido de lugares de teletrabajo. |
| TT-RF-005 | Restringir acceso al domicilio. | Permisos especiales, enmascaramiento y bitácora. |
| TT-RF-006 | Administrar listas de verificación iniciales y periódicas. | Plantillas versionadas y seguimiento. |
| TT-RF-007 | Registrar consentimiento para visita física o alternativa aplicada. | Evidencia de consentimiento o autoevaluación. |
| TT-RF-008 | Administrar hallazgos y planes de acción. | Seguimiento con responsables y fechas. |
| TT-RF-009 | Registrar inventario, mantenimiento y devolución. | Control de bienes e insumos. |
| TT-RF-010 | Configurar conceptos de costos y seguimiento de pago. | Reglas por vigencia e integración con nómina o cuentas por pagar. |
| TT-RF-011 | Configurar horarios, pausas y periodos de desconexión. | Políticas aplicables a jornada y comunicación. |
| TT-RF-012 | Registrar posibles incidencias de desconexión. | Flujo de revisión sin asumir trabajo automático. |
| TT-RF-013 | Administrar herramientas de supervisión y su justificación. | Finalidad, vigencia y autorización. |
| TT-RF-014 | Bloquear por defecto cámaras, micrófonos y vigilancia invasiva. | Controles preventivos y evaluación jurídica. |
| TT-RF-015 | Gestionar solicitudes de reversibilidad. | Solicitud, resolución, fecha efectiva e historial. |
| TT-RF-016 | Registrar capacitación anual. | Control de cumplimiento y vencimiento. |
| TT-RF-017 | Registrar accidentes, incidentes y cambios de condiciones. | Reportes sin diagnóstico médico. |
| TT-RF-018 | Generar listado NOM-037 con acceso controlado. | Reporte restringido y auditable. |
| TT-RF-019 | Relacionar teletrabajo con el registro de jornada. | Cruce entre modalidad y eventos de jornada. |
| TT-RF-020 | Exportar evidencia para inspección sin exponer datos ajenos. | Paquetes por alcance y rol. |
| TT-RF-021 | Registrar aceptación o acuse de políticas. | Acuses versionados. |
| TT-RF-022 | Permitir integración con nómina, inventario, capacitación y seguridad y salud. | Conectores desacoplados del núcleo. |

## 20. Datos conceptuales necesarios

- Modalidad laboral.
- Porcentaje pactado.
- Porcentaje observado.
- Periodo de cálculo.
- Fecha de inicio y fin.
- Documento contractual.
- Política aplicable.
- Lugar de teletrabajo.
- Domicilio protegido.
- Estado de validación.
- Lista de verificación.
- Hallazgos.
- Plan de acción.
- Consentimiento de visita.
- Equipo e insumo.
- Mantenimiento.
- Costo pactado.
- Estado de pago.
- Horario.
- Periodos de desconexión.
- Herramienta de supervisión.
- Justificación.
- Capacitación.
- Accidente o incidencia.
- Solicitud de reversibilidad.
- Evidencias.
- Historial de cambios.

## 21. Alertas mínimas

| Código | Severidad | Condición | Acción sugerida |
| --- | --- | --- | --- |
| `TT-W001` | Advertencia | Porcentaje real superior al pactado. | Revisar modalidad y posible actualización documental. |
| `TT-W002` | Advertencia | Modalidad sin documento vigente. | Vincular contrato, convenio o política aplicable. |
| `TT-W003` | Advertencia | Lugar sin validación vigente. | Solicitar verificación o autoevaluación. |
| `TT-W004` | Advertencia | Lista de verificación próxima a vencer. | Programar revisión. |
| `TT-W005` | Advertencia | Capacitación anual pendiente. | Programar o registrar capacitación. |
| `TT-W006` | Advertencia | Equipo con mantenimiento vencido. | Programar mantenimiento o sustitución. |
| `TT-W007` | Advertencia | Costo pactado pendiente de pago. | Conciliar con nómina o cuentas por pagar. |
| `TT-W008` | Advertencia | Comunicación fuera de periodo de desconexión pendiente de revisión. | Determinar si generó trabajo o solo comunicación. |
| `TT-W009` | Advertencia | Cambio de domicilio pendiente de validación. | Revisar lugar propuesto y fecha efectiva. |
| `TT-C001` | Crítica | Acceso no autorizado al domicilio. | Escalar a seguridad y privacidad. |
| `TT-C002` | Crítica | Visita registrada sin consentimiento. | Bloquear registro y solicitar revisión jurídica. |
| `TT-C003` | Crítica | Cámara o micrófono habilitado sin justificación. | Deshabilitar y documentar revisión. |
| `TT-C004` | Crítica | Evidencia de seguridad reutilizada para supervisión. | Escalar por finalidad incompatible. |
| `TT-C005` | Crítica | Persona en teletrabajo sin equipo o condiciones requeridas. | Revisar continuidad de la modalidad. |
| `TT-C006` | Crítica | Supervisión configurada sin finalidad o vigencia. | Bloquear configuración hasta completarla. |
| `TT-C007` | Crítica | Solicitud de reversibilidad sin atención dentro del plazo pactado. | Escalar a responsable de RH o cumplimiento. |

## 22. Casos de prueba mínimos

1. Persona con 41 % pactado de teletrabajo.
2. Persona con 40 % exacto.
3. Trabajo remoto ocasional.
4. Modalidad presencial que supera sostenidamente el umbral.
5. Cambio voluntario con convenio.
6. Fuerza mayor documentada.
7. Reversibilidad temporal.
8. Reversibilidad permanente.
9. Lugar propuesto y aprobado.
10. Cambio temporal de domicilio.
11. Visita con consentimiento.
12. Rechazo de visita y autoevaluación.
13. Lista inicial aprobada.
14. Lista periódica con hallazgo.
15. Evidencia fotográfica con acceso restringido.
16. Entrega de equipo.
17. Mantenimiento y devolución.
18. Costo de internet con vigencia.
19. Trabajo después del horario.
20. Mensaje fuera de horario sin actividad.
21. Actividad confirmada fuera de horario.
22. Horario flexible con pausa protegida.
23. Cámara configurada sin justificación.
24. Cámara extraordinaria debidamente autorizada.
25. Capacitación anual vencida.
26. Reporte de accidente.
27. Alteración de condiciones del lugar.
28. Solicitud por violencia familiar y retorno temporal.
29. Generación del listado NOM.
30. Intento de acceso al domicilio por un rol no autorizado.
31. Integración con registro electrónico de jornada.
32. Trabajo en lugar no acordado.

## 23. Decisiones de producto resultantes

1. Teletrabajo será una modalidad versionada de la relación laboral.
2. El porcentaje real podrá compararse con el pactado.
3. El domicilio tendrá tratamiento restringido.
4. La visita física requerirá consentimiento documentado.
5. La autoevaluación será un flujo soportado.
6. Las evidencias de seguridad no se usarán como vigilancia de productividad.
7. La desconexión formará parte de horarios y políticas.
8. El uso de cámaras y micrófonos estará bloqueado por defecto.
9. La plataforma administrará evidencia e integraciones, no diagnósticos médicos.
10. El registro de jornada se aplicará también al teletrabajo cuando corresponda, sin exigir vigilancia continua.

## 24. Pendientes y documentos relacionados

- Jornada general: `01-Jornada-Laboral.md`.
- Tipos de jornada: `02-Tipos-de-Jornada.md`.
- Horas extraordinarias: `03-Horas-Extra.md`.
- Descansos: `04-Descansos.md`.
- Registro electrónico: `05-Registro-Electronico.md`.
- Inspecciones y sanciones: `07-Inspecciones-y-Sanciones.md`.
- Reglas consolidadas: `08-Reglas-Derivadas.md`.

## 25. Criterios de aceptación

Este documento se considerará aprobado cuando:

- Distinga teletrabajo, trabajo híbrido, ocasional y de campo.
- Aplique correctamente el umbral superior al 40 %.
- Incluya condiciones por escrito.
- Contemple equipos, costos, lugar y capacitación.
- Respete desconexión, privacidad y supervisión proporcional.
- Bloquee vigilancia invasiva por defecto.
- Incluya lista inicial y periódica de la NOM.
- Documente consentimiento de visitas.
- Permita reversibilidad.
- Sus reglas puedan convertirse en pruebas automatizadas.

## 26. Fuentes oficiales relacionadas

- `SRC-001`: Cámara de Diputados, **Ley Federal del Trabajo**, texto vigente con última reforma DOF 14-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- `SRC-004`: Cámara de Diputados, **Ley Federal de Protección de Datos Personales en Posesión de los Particulares**, última reforma DOF 14-11-2025.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFPDPPP.pdf
- `SRC-005`: Secretaría del Trabajo y Previsión Social, **NOM-037-STPS-2023, Teletrabajo - Condiciones de seguridad y salud en el trabajo**, DOF 08-06-2023.  
  https://asinom.stps.gob.mx/upload/nom/51.pdf
