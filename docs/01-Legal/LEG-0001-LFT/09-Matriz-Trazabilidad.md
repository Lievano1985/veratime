---
id: LEG-0001-09
title: Matriz de trazabilidad jurídica y funcional
project: Jornada 360
version: 1.0.0
status: Approved
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
  - LEG-0001-08
tags:
  - legal
  - trazabilidad
  - requisitos
  - pruebas
---

# 09 - Matriz de trazabilidad jurídica y funcional

## 1. Objetivo

Relacionar las reglas maestras de Jornada 360 con su fundamento, reglas de origen, requisitos funcionales, dominio responsable y prueba mínima.

Esta matriz permite recorrer la trazabilidad en ambas direcciones:

```text
Fuente oficial
→ Investigación jurídica
→ Regla específica
→ Regla maestra
→ Requisito funcional
→ Módulo
→ Prueba
```

El documento no sustituye los capítulos `01` a `08`. Los utiliza como fuentes controladas y evita repetir su explicación completa.

## 2. Estado normativo

**Fecha de corte:** 30 de junio de 2026.

La matriz se basa en:

- Ley Federal del Trabajo vigente, última reforma DOF 14-05-2026.
- Decreto de reducción de la jornada publicado el 01-05-2026.
- Ley Federal de Protección de Datos Personales en Posesión de los Particulares vigente.
- NOM-037-STPS-2023.
- Reglamento General de Inspección del Trabajo y Aplicación de Sanciones y su reforma de 2022.

A la fecha de corte no se identificaron disposiciones generales definitivas de la STPS que desarrollen el registro electrónico previsto en el artículo 132, fracción XXXIV. Los parámetros dependientes de esas disposiciones permanecen abiertos y configurables.

## 3. Clasificación de las reglas

| Clasificación | Significado |
|---|---|
| `LEGAL` | Reproduce una obligación, límite o derecho expresamente establecido. |
| `DERIVED` | Traduce una o varias disposiciones a un comportamiento operativo necesario. |
| `PRODUCT` | Decisión de diseño para seguridad, claridad, evidencia o mantenibilidad; no se presenta como mandato textual. |
| `TECHNICAL` | Garantía de implementación necesaria para preservar aislamiento, integridad o confiabilidad. |

## 4. Reglas de mantenimiento

1. Ninguna regla maestra se implementará sin al menos una prueba vinculada.
2. Un cambio legal actualizará primero el capítulo de investigación correspondiente y después esta matriz.
3. Una regla eliminada no reutilizará su identificador.
4. Los cambios de fundamento, prioridad o comportamiento incrementarán la versión del documento.
5. Los requisitos y pruebas futuras conservarán los identificadores referenciados aquí.

## 5. Principios transversales

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-001 | DERIVED | LFT 58, 66, 68 y 804 | JL-RN-006; HE-RN-006; RE-RN-004 | JL-RF-004; HE-RF-007; RE-RF-001 | Eventos / Motor de jornada | Registrar una jornada superior al límite; conservar todos los eventos y generar la alerta correspondiente. |
| RG-002 | DERIVED | LFT 784, 804 y 805; RGITAS 34-35 | JL-RN-007; DS-RN-015; RE-RN-005; IS-RN-010 | JL-RF-009; RE-RF-007; RE-RF-008; IS-RF-007 | Auditoría / Evidencia | Corregir un evento usado; verificar que sobrevivan valor original, nueva versión, motivo y autor. |
| RG-003 | DERIVED | LFT 25-V, 58-61, 66, 69-71 y transitorios 2 y 4 | JL-RN-002; JL-RN-003; HE-RN-001; DS-RN-006; TT-RN-003; TT-RN-014; TT-RN-016; TT-RN-017 | JL-RF-001; JL-RF-003; HE-RF-001; DS-RF-011; TT-RF-001; TT-RF-010; TT-RF-016 | Reglas y vigencias | Cambiar una regla con fecha futura; comprobar que periodos cerrados mantengan la versión anterior. |
| RG-004 | DERIVED | LFT 59, 61, 66 y transitorios 2 y 4 | JL-RN-002; HE-RN-001; RE-RN-016 | JL-RF-010; HE-RF-008; RE-RF-015 | Motor de reglas | Recalcular una jornada histórica y comprobar que utiliza la norma vigente en la fecha trabajada. |
| RG-005 | DERIVED | LFT 5-III, 56, 59, 61 y condiciones contractuales aplicables | JL-RN-010; JL-RN-011; JL-RN-014; TJ-RN-013; DS-RN-019; TT-RN-019 | JL-RF-007; TJ-RF-013; DS-RF-020 | Relaciones / Motor de reglas | Configurar un límite contractual inferior al legal y comprobar que se aplique el más favorable. |
| RG-006 | PRODUCT | LFT 784, 804 y 805; necesidad de acreditación | JL-RN-015; TJ-RN-014; HE-RN-010; RE-RN-010 | JL-RF-006; JL-RF-010; TJ-RF-012; HE-RF-008; HE-RF-014; DS-RF-017; RE-RF-015 | Explicabilidad / Reportes | Consultar un cálculo y obtener eventos, parámetros, fuente, vigencia y versión que lo produjeron. |
| RG-007 | PRODUCT | LFT 540-543 y 992-1004-A; competencia de la autoridad | JL-RN-012; DS-RN-020; IS-RN-016; TT-RN-021 | JL-RF-014; DS-RF-019; DS-RF-020; TT-RF-017; IS-RF-014 | Alertas / Cumplimiento | Generar una alerta crítica y verificar que la interfaz no la presente como infracción confirmada. |
| RG-008 | DERIVED | LFT 66-68 y 73-75 | HE-RN-007; HE-RN-015; HE-RN-017; HE-RN-018 | HE-RF-006; HE-RF-016; HE-RF-017 | Horas extra / Nómina | Registrar tiempo real no autorizado; mantener separados cálculo, autorización, envío y pago. |
| RG-009 | PRODUCT | LFT 784, 804-805 y 132-XXXIV | JL-RN-013; RE-RN-010; RE-RN-019; IS-RN-005; TT-RN-015 | RE-RF-010; RE-RF-013; RE-RF-014; TT-RF-009; IS-RF-005; IS-RF-007 | Evidencia | Generar un expediente verificable que respalde a ambas partes sin alterar los registros fuente. |
| RG-010 | TECHNICAL | LFPDPPP 5-14; LFT 132-XXXIV | RE-RN-017; TT-RN-022; IS-RN-018 | RE-RF-018; TT-RF-005; IS-RF-017 | Seguridad multi-tenant | Intentar consultar o exportar información de otro tenant y comprobar denegación y bitácora. |

## 6. Identidad, empresa y relación laboral

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-011 | LEGAL | LFT 132-XXXIV y 784 | RE-RN-001; JL-RN-001 | RE-RF-001; RE-RF-002; JL-RF-011 | Personas / Registro | Crear un evento y comprobar que queda asociado inequívocamente a una persona y relación laboral. |
| RG-012 | DERIVED | LFT 25-V, 58, 330-B y 330-G | JL-RN-003; TT-RN-003; TT-RN-004 | JL-RF-002; JL-RF-003; TT-RF-001; TT-RF-003 | Personas y relaciones | Cambiar centro, modalidad o jornada con fecha efectiva y comprobar que el historial no se reescriba. |
| RG-013 | TECHNICAL | LFPDPPP 5 y principios de control de acceso | RE-RN-013; RE-RN-017 | RE-RF-018; RE-RF-019 | Identidad / Integraciones | Enviar un evento desde un dispositivo compartido y validar empresa mediante credencial y asignación vigentes. |
| RG-014 | DERIVED | LFT 70, 74-IX y 330-A; RGITAS | DS-RN-012; TT-RN-005; IS-RN-001 | DS-RF-012; TT-RF-004; IS-RF-001 | Centros / Calendarios | Asignar centro y jurisdicción y comprobar calendario electoral, teletrabajo e inspección aplicables. |
| RG-015 | DERIVED | LFT 60, 71 y 74; cómputo por hora local | TJ-RN-009; DS-RN-014; RE-RN-007 | JL-RF-015; TJ-RF-009; RE-RF-002 | Tiempo y zonas | Registrar eventos en una zona distinta a la del servidor y clasificar correctamente franja y domingo. |
| RG-016 | PRODUCT | LFT 60-68; necesidad operativa de jornadas transmedianoche | TJ-RN-006; HE-RN-018; DS-RN-017 | TJ-RF-008; HE-RF-018; DS-RF-009 | Motor de jornada | Procesar una jornada que cruza medianoche manteniendo fecha operativa y timestamps civiles reales. |

## 7. Eventos y jornada

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-017 | DERIVED | LFT 132-XXXIV, 784 y 804 | JL-RN-001; RE-RN-001 | JL-RF-004; JL-RF-005; RE-RF-001; RE-RF-006 | Eventos / Motor de jornada | Modificar un total mostrado y comprobar que el resultado solo cambie mediante eventos o corrección autorizada. |
| RG-018 | LEGAL | LFT 132-XXXIV | JL-RN-008; RE-RN-002 | JL-RF-008; RE-RF-002 | Registro electrónico | Intentar cerrar una jornada sin salida y verificar incidencia explícita en lugar de cierre normal. |
| RG-019 | PRODUCT | LFT 132-XXXIV; integridad de registro electrónico | RE-RN-006 | RE-RF-004; RE-RF-015 | Eventos / Sincronización | Sincronizar un evento tardío y conservar hora del hecho, recepción y procesamiento por separado. |
| RG-020 | DERIVED | LFT 784, 804 y prohibición de fabricar evidencia | JL-RN-008; TJ-RN-007; HE-RN-011; RE-RN-002 | JL-RF-008; TJ-RF-010; RE-RF-007 | Incidencias | Procesar una entrada sin salida y comprobar que no se complete con una hora estimada. |
| RG-021 | TECHNICAL | Integridad necesaria para LFT 132-XXXIV y 804 | RE-RN-003; RE-RN-006 | RE-RF-005; RE-RF-019 | API / Eventos | Reenviar la misma petición con la misma clave idempotente y crear un solo evento. |
| RG-022 | PRODUCT | LFT 60-68 y 71-75 | TJ-RN-006; DS-RN-017 | TJ-RF-006; TJ-RF-008; DS-RF-009 | Motor de jornada | Procesar 22:00-05:00 como una jornada operativa y atribuir minutos a las fechas locales correctas. |
| RG-023 | DERIVED | LFT 63-64 | TJ-RN-011; DS-RN-005 | TJ-RF-007; DS-RF-002; DS-RF-004 | Motor de jornada / Descansos | Reconstruir una jornada con dos intervalos de trabajo y una pausa sin perder ninguno. |
| RG-024 | DERIVED | LFT 784, 804-805; evidencia documental | JL-RN-007; TJ-RN-008; HE-RN-012; RE-RN-005 | JL-RF-009; TJ-RF-011; HE-RF-009; HE-RF-010; DS-RF-015; DS-RF-016; RE-RF-007; RE-RF-008; RE-RF-011 | Correcciones / Auditoría | Autorizar una corrección y verificar anterior, nuevo, motivo, autor, fecha y resultado recalculado. |
| RG-025 | PRODUCT | LFT 804-805; conservación de evidencia | RE-RN-014; RE-RN-015 | RE-RF-007; RE-RF-008; RE-RF-017 | Cierre de jornadas | Reabrir una jornada cerrada y exigir motivo, permiso y nueva versión del cálculo. |

## 8. Duración y tipo de jornada

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-026 | LEGAL | LFT 59 y transitorio segundo del decreto 01-05-2026 | JL-RN-004 | JL-RF-001; JL-RF-007 | Motor de reglas | Validar semanas de 2026, 2028 y 2030 con máximos de 48, 44 y 40 horas respectivamente. |
| RG-027 | LEGAL | LFT 59 y 61 | JL-RN-005; TJ-RN-004; TJ-RN-005 | JL-RF-007; TJ-RF-005 | Motor de jornada | Crear una semana dentro del máximo semanal pero con un día excedido y generar alerta diaria. |
| RG-028 | LEGAL | LFT 60 | TJ-RN-001 | TJ-RF-002; TJ-RF-003 | Clasificación de jornada | Calcular minutos diurnos y nocturnos de un intervalo y obtener el tipo resultante correcto. |
| RG-029 | PRODUCT | LFT 60-61; comparación planeado-real | TJ-RN-002; TJ-RN-010 | TJ-RF-001; TJ-RF-004 | Horarios / Motor | Asignar turno diurno y registrar resultado nocturno; conservar ambos y generar observación. |
| RG-030 | LEGAL | LFT 60 | TJ-RN-003 | TJ-RF-003 | Clasificación de jornada | Clasificar 209 minutos nocturnos como mixta y 210 minutos como nocturna. |
| RG-031 | DERIVED | LFT 60-61 | TJ-RN-012 | TJ-RF-002; TJ-RF-003 | Motor de cálculo | Procesar 209.6 minutos internos sin redondear antes de aplicar el umbral; redondear solo al presentar. |
| RG-032 | DERIVED | LFT 58-59 y 66 | JL-RN-009; HE-RN-014 | JL-RF-002; HE-RF-003 | Horarios / Horas extra | Registrar trabajo fuera de la hora prevista pero dentro de la duración flexible y no clasificarlo automáticamente como extra. |

## 9. Horas extraordinarias

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-033 | DERIVED | LFT 58-61 y 66 | HE-RN-014 | HE-RF-003 | Horas extraordinarias | Determinar primero el límite ordinario aplicable y clasificar únicamente el excedente como potencial extra. |
| RG-034 | LEGAL | LFT 66 y transitorio cuarto del decreto 01-05-2026 | HE-RN-002; HE-RN-005; HE-RN-013 | HE-RF-001; HE-RF-015 | Horas extraordinarias | Acumular horas en una semana histórica y aplicar 9, 10, 11 o 12 según el año. |
| RG-035 | LEGAL | LFT 66 y 68 | HE-RN-003 | HE-RF-004; HE-RF-007 | Horas extraordinarias / Nómina | Agotar la banda del artículo 66 y clasificar las siguientes horas en la banda del artículo 68. |
| RG-036 | LEGAL | LFT 65 y 67 | HE-RN-008; HE-RN-009 | HE-RF-005 | Emergencias laborales | Intentar clasificar una prolongación como emergencia sin causa; dejarla pendiente y generar alerta. |
| RG-037 | LEGAL | LFT 68 | HE-RN-004 | HE-RF-002; HE-RF-007 | Horas extraordinarias | Registrar exactamente 12 horas totales y luego 12 h 1 min; alertar solo el segundo caso por máximo absoluto. |
| RG-038 | DERIVED | LFT 68, 784 y 804 | HE-RN-006 | HE-RF-007; HE-RF-013; TJ-RF-014 | Horas extra / Evidencia | Registrar tiempo superior al límite y verificar que permanezca completo, con alerta, sin recorte. |
| RG-039 | DERIVED | LFT 66-68 y carga probatoria del 784 | HE-RN-007; HE-RN-016; HE-RN-018 | HE-RF-006; HE-RF-016; HE-RF-017 | Autorizaciones | Rechazar administrativamente horas registradas y comprobar que el hecho y la controversia permanezcan. |
| RG-040 | PRODUCT | LFT 66-68, 73 y 75 | HE-RN-015; HE-RN-017 | HE-RF-011; HE-RF-012; HE-RF-016; TT-RF-010 | Integración con nómina | Exportar minutos, banda y multiplicador; recibir respuesta de nómina sin recalcular el importe en Jornada 360. |

## 10. Descansos y calendario

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-041 | DERIVED | LFT 63-64 | DS-RN-001; DS-RN-003; DS-RN-018 | DS-RF-001; DS-RF-002; DS-RF-004 | Descansos | Programar una pausa sin eventos reales y comprobar que no se descuente automáticamente. |
| RG-042 | LEGAL | LFT 64 | DS-RN-004 | DS-RF-003; DS-RF-004 | Descansos | Marcar que la persona no puede salir y comprobar que la pausa se compute como tiempo efectivo. |
| RG-043 | LEGAL | LFT 63 | DS-RN-002; DS-RN-005 | DS-RF-005 | Descansos | Validar jornada continua con pausa de 30 minutos y otra con 29 minutos. |
| RG-044 | DERIVED | LFT 69-70 | DS-RN-006; DS-RN-008 | DS-RF-006 | Calendarios laborales | Cambiar el descanso semanal con vigencia futura y conservar semanas previas sin cambios. |
| RG-045 | LEGAL | LFT 69 | DS-RN-007 | DS-RF-007 | Descansos semanales | Registrar siete días consecutivos trabajados y generar alerta de descanso no identificado. |
| RG-046 | LEGAL | LFT 71 | DS-RN-009 | DS-RF-009; DS-RF-010 | Calendario / Nómina | Trabajar en domingo como día ordinario y generar el concepto de prima dominical independientemente del descanso semanal. |
| RG-047 | LEGAL | LFT 74 | DS-RN-011; DS-RN-012 | DS-RF-011; DS-RF-012 | Calendarios | Calcular un feriado fijo y uno electoral local usando catálogo versionado y jurisdicción. |
| RG-048 | DERIVED | LFT 66-68, 71, 73 y 75 | DS-RN-010; DS-RN-013; DS-RN-016 | DS-RF-008; DS-RF-013; DS-RF-014; DS-RF-018 | Descansos / Nómina | Procesar un domingo que también sea descanso semanal y obligatorio; conservar conceptos separados. |

## 11. Registro electrónico y evidencia

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-049 | LEGAL | LFT 132-XXXIV y transitorio quinto | RE-RN-001; RE-RN-002; RE-RN-018; TT-RN-020 | RE-RF-001; RE-RF-002; RE-RF-020; TT-RF-019 | Registro electrónico | Con obligación activa, cerrar registros individuales con inicio y fin; con excepción configurada, conservar la justificación. |
| RG-050 | PRODUCT | LFT 132-XXXIV no prescribe una tecnología específica | RE-RN-003; RE-RN-012 | JL-RF-013; RE-RF-003; RE-RF-019 | Canales de captura | Capturar la misma estructura de evento desde web, kiosco y API sin cambiar el dominio. |
| RG-051 | DERIVED | LFPDPPP 5-14; LFT 330-I | RE-RN-012; RE-RN-020; TJ-RN-015; TT-RN-013 | RE-RF-003; TT-RF-014 | Privacidad / Captura | Operar registro básico sin solicitar GPS, fotografía o biometría; habilitarlos solo con configuración y finalidad válida. |
| RG-052 | LEGAL | LFT 132-XXXIV, párrafo probatorio | RE-RN-008 | RE-RF-009; TT-RF-021 | Acuerdos y políticas | Cambiar la política de registro y relacionar cada jornada con la versión vigente aceptada o notificada. |
| RG-053 | DERIVED | LFPDPPP 5-14 y LFT 132-XXXIV | RE-RN-009 | RE-RF-009 | Consentimientos / Acuerdos | Iniciar sesión o usar la app sin generar automáticamente un registro de aceptación jurídica. |
| RG-054 | PRODUCT | LFT 132-XXXIV, 784 y 804 | RE-RN-010; RE-RN-019 | RE-RF-012; RE-RF-013; RE-RF-014 | Evidencia / Exportaciones | Generar el mismo expediente con manifiesto y verificar integridad y alcance de su versión cerrada. |
| RG-055 | DERIVED | LFT 804; LFPDPPP 5-14; futuras disposiciones STPS | RE-RN-020; IS-RN-009 | RE-RF-016 | Retención documental | Aplicar políticas distintas por tipo de registro y bloquear una depuración no permitida. |
| RG-056 | DERIVED | LFT 804-805; RGITAS | IS-RN-009; RE-RN-019 | RE-RF-016; IS-RF-011 | Retención legal | Abrir una inspección o controversia y comprobar que los registros vinculados no puedan eliminarse. |

## 12. Teletrabajo

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-057 | LEGAL | LFT 330-A | TT-RN-001 | TT-RF-001; TT-RF-002 | Teletrabajo | Comparar porcentaje pactado y observado durante el periodo configurado. |
| RG-058 | LEGAL | LFT 330-A | TT-RN-002 | TT-RF-001; TT-RF-002 | Teletrabajo | Clasificar 40 % exacto y actividad ocasional sin aplicar automáticamente el capítulo XII Bis; alertar al superar 40 %. |
| RG-059 | DERIVED | LFT 330-B y NOM-037 | TT-RN-003; TT-RN-005 | TT-RF-004; TT-RF-006 | Lugares de teletrabajo | Cambiar lugar con fecha efectiva y conservar propuesta, validación e historial anterior. |
| RG-060 | DERIVED | LFPDPPP 5-14 y NOM-037 | TT-RN-008; TT-RN-022 | TT-RF-005; TT-RF-018 | Teletrabajo / Privacidad | Consultar domicilio con rol no autorizado y comprobar denegación, enmascaramiento y bitácora. |
| RG-061 | LEGAL | NOM-037-STPS-2023 | TT-RN-006; TT-RN-007 | TT-RF-007; TT-RF-008 | Seguridad y salud | Registrar una visita solo con consentimiento; permitir autoevaluación como alternativa configurada. |
| RG-062 | DERIVED | LFT 330-I; LFPDPPP 5-14; NOM-037 | TT-RN-009; TT-RN-012 | TT-RF-006; TT-RF-013; TT-RF-014 | Teletrabajo / Privacidad | Adjuntar evidencia de seguridad y comprobar que no sea visible en métricas de productividad. |
| RG-063 | LEGAL | LFT 330-E-VI y NOM-037 | TT-RN-010 | TT-RF-011; TT-RF-012 | Desconexión | Enviar comunicación fuera de horario y comprobar que no se exija respuesta inmediata ni se penalice automáticamente. |
| RG-064 | DERIVED | LFT 330-E-VI; definición de desconexión NOM-037 | TT-RN-011 | TT-RF-012 | Desconexión / Jornada | Registrar un mensaje fuera de horario sin actividad y comprobar que no genere tiempo trabajado. |
| RG-065 | LEGAL | LFT 330-I | TT-RN-013 | TT-RF-013; TT-RF-014 | Supervisión | Intentar habilitar cámara continua sin justificación extraordinaria y bloquear la configuración. |
| RG-066 | LEGAL | LFT 330-G | TT-RN-018 | TT-RF-003; TT-RF-015 | Teletrabajo / Relaciones | Ejecutar retorno a presencial y conservar solicitud, resolución, fecha efectiva y modalidad anterior. |

## 13. Inspecciones y cumplimiento

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-067 | DERIVED | LFT 540-543 y RGITAS | IS-RN-001; IS-RN-014 | IS-RF-001; IS-RF-002 | Inspecciones | Crear expedientes separados para inspecciones presencial y documental del mismo centro. |
| RG-068 | DERIVED | RGITAS 29-30 | IS-RN-003; IS-RN-013 | IS-RF-003 | Inspecciones | Registrar orden e inspector y no liberar información hasta documentar validación o excepción autorizada. |
| RG-069 | DERIVED | RGITAS 29-35; LFPDPPP 5-14 | IS-RN-002; IS-RN-004; IS-RN-006; IS-RN-018 | JL-RF-012; IS-RF-004; IS-RF-005; IS-RF-017; IS-RF-018 | Inspecciones / Evidencia | Solicitar un centro y periodo; impedir que el paquete incluya personas o tenants fuera del alcance. |
| RG-070 | PRODUCT | LFT 804; RGITAS 34-35 | IS-RN-005; IS-RN-007; IS-RN-020; IS-RN-021 | IS-RF-006; IS-RF-007 | Inspecciones / Evidencia | Entregar un paquete y comprobar que no pueda editarse; una corrección debe crear otra versión vinculada. |
| RG-071 | DERIVED | RGITAS 30, 34 y 35 | IS-RN-010; IS-RN-011; IS-RN-012 | IS-RF-008; IS-RF-009; IS-RF-018; IS-RF-019 | Inspecciones | Registrar acta original y presentar aclaración como documento separado con evidencia vinculada. |
| RG-072 | DERIVED | RGITAS y notificación concreta | IS-RN-008 | IS-RF-009; IS-RF-015 | Inspecciones / Plazos | Capturar una fecha límite desde el acta y generar avisos sin sustituirla por un plazo fijo global. |
| RG-073 | LEGAL | LFT 992, 994 y 1000-1004-A | IS-RN-015; IS-RN-016; IS-RN-017 | IS-RF-012; IS-RF-013; IS-RF-014; IS-RF-019 | Sanciones | Registrar UMA y rango aplicable, pero exigir resolución o revisión humana para monto definitivo. |
| RG-074 | DERIVED | LFT 992-1004-A | IS-RN-022 | IS-RF-010; IS-RF-020 | Cumplimiento correctivo | Marcar una multa como pagada y comprobar que la medida correctiva permanezca abierta. |

## 14. Privacidad y seguridad

| Regla | Tipo | Fundamento o base | Reglas de origen | Requisitos vinculados | Dominio responsable | Prueba mínima |
|---|---|---|---|---|---|---|
| RG-075 | LEGAL | LFPDPPP 5-14 | RE-RN-020; TT-RN-009 | RE-RF-016; RE-RF-018; TT-RF-020 | Privacidad | Configurar una captura con datos innecesarios y bloquear o exigir justificación de finalidad. |
| RG-076 | DERIVED | LFPDPPP 5-14 | RE-RN-017; TT-RN-022; IS-RN-019 | RE-RF-018; TT-RF-005; IS-RF-017 | Autorización | Un rol de supervisor puede ver jornadas asignadas, pero no domicilios ni expedientes jurídicos sin permiso. |
| RG-077 | DERIVED | LFPDPPP 5 y principio de responsabilidad; LFT 804 | RE-RN-019; IS-RN-019 | RE-RF-017; IS-RF-016 | Auditoría de seguridad | Consultar domicilio, corregir jornada y descargar expediente; registrar actor, fecha, alcance y resultado. |
| RG-078 | DERIVED | LFPDPPP 5-14; LFT 132-XXXIV | RE-RN-011; RE-RN-017; IS-RN-018 | RE-RF-014; RE-RF-018; IS-RF-005; IS-RF-017 | Portabilidad / Exportación | Exportar datos de una empresa y comprobar que no aparezcan identificadores ni registros de otra. |
| RG-079 | TECHNICAL | LFPDPPP 5-14; integridad del registro electrónico | RE-RN-017; TT-RN-022 | RE-RF-019; TT-RF-022; IS-RF-016 | Integraciones / Seguridad | Rechazar API sin credenciales o alcance; registrar intento y aplicar idempotencia en una petición válida. |
| RG-080 | PRODUCT | LFPDPPP 5-14; LFT 804-805 | RE-RN-004; RE-RN-005; RE-RN-019 | RE-RF-007; RE-RF-008; IS-RF-010 | Incidentes / Integridad | Detectar corrupción o acceso indebido y crear incidente y nueva versión sin alterar silenciosamente la evidencia. |

## 15. Cobertura documental

| Documento fuente | Reglas específicas | Requisitos funcionales | Cobertura en reglas maestras |
|---|---:|---:|---|
| `01-Jornada-Laboral.md` | 15 | 15 | Jornada pactada, vigencias, límites, evidencia y explicabilidad. |
| `02-Tipos-de-Jornada.md` | 15 | 14 | Clasificación, umbral nocturno, zona horaria e intervalos. |
| `03-Horas-Extra.md` | 18 | 18 | Bandas, acumulados, emergencias, autorización y nómina. |
| `04-Descansos.md` | 20 | 20 | Pausas, descanso semanal, domingo, obligatorios y concurrencia. |
| `05-Registro-Electronico.md` | 20 | 20 | Eventos, acuerdos, correcciones, exportación y conservación. |
| `06-Teletrabajo.md` | 22 | 22 | Aplicabilidad, lugar, desconexión, supervisión y reversibilidad. |
| `07-Inspecciones-y-Sanciones.md` | 22 | 20 | Expediente, alcance, actas, retención y sanciones. |
| **Total de origen** | **132** | **129** | Consolidado en **80 reglas maestras**. |

La reducción de 132 reglas específicas a 80 reglas maestras es intencional. Las reglas específicas conservan el detalle de su capítulo; las maestras eliminan duplicidad y gobiernan la implementación transversal.

## 16. Dependencias normativas abiertas

| ID | Dependencia | Impacto esperado | Acción |
|---|---|---|---|
| `PEND-001` | Disposiciones generales STPS del registro electrónico. | Ámbito, excepciones, formato, operación, conservación o entrega. | Revisar DOF y STPS; actualizar `05`, `08` y `09` antes de liberar cumplimiento definitivo. |
| `PEND-002` | Formato o canal oficial de entrega electrónica, si se publica. | Exportaciones, manifiestos e interoperabilidad. | Mantener adaptadores y formatos configurables. |
| `PEND-003` | Plazo específico de conservación del nuevo registro. | Retención, archivo y depuración. | Aplicar política conservadora y retención legal hasta contar con regla definitiva. |
| `PEND-004` | Reglas sectoriales o trabajos especiales fuera del alcance actual. | Límites, descansos, evidencia o excepciones particulares. | Analizar por módulo o sector antes de habilitarlo comercialmente. |
| `PEND-005` | Fórmulas monetarias completas de nómina. | Importe final de extras, primas y descansos trabajados. | Entregar conceptos y multiplicadores; definir especificación independiente de nómina. |

## 17. Criterios de cierre de la investigación LEG-0001

La investigación podrá marcarse como versión `1.0.0` concluida cuando:

- Los documentos `01` a `09` estén aprobados.
- `SOURCES.md` contenga las fuentes oficiales utilizadas y su fecha de consulta.
- No existan identificadores rotos entre reglas, requisitos y matriz.
- Las dependencias abiertas estén registradas como pendientes normativos, no como obligaciones confirmadas.
- El `README.md` refleje el índice definitivo.
- `CHANGELOG.md` registre la publicación inicial.

La conclusión de la investigación no significa que el marco normativo quede cerrado permanentemente. Cualquier reforma o disposición nueva iniciará una actualización versionada.

## 18. Fuentes oficiales

- `SRC-001`: Cámara de Diputados, **Ley Federal del Trabajo**, texto vigente con última reforma DOF 14-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- `SRC-002`: Diario Oficial de la Federación, **Decreto en materia de reducción de la jornada laboral**, publicado el 01-05-2026.  
  https://www.diputados.gob.mx/LeyesBiblio/ref/lft/LFT_ref52_01may26.pdf
- `SRC-004`: Cámara de Diputados, **Ley Federal de Protección de Datos Personales en Posesión de los Particulares**, última reforma DOF 14-11-2025.  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFPDPPP.pdf
- `SRC-005`: Secretaría del Trabajo y Previsión Social, **NOM-037-STPS-2023, Teletrabajo - Condiciones de seguridad y salud en el trabajo**.  
  https://asinom.stps.gob.mx/upload/nom/51.pdf
- `SRC-007`: Cámara de Diputados, **Reglamento General de Inspección del Trabajo y Aplicación de Sanciones**.  
  https://www.diputados.gob.mx/LeyesBiblio/regla/n395.pdf
- `SRC-008`: Cámara de Diputados, **Reforma al Reglamento General de Inspección del Trabajo y Aplicación de Sanciones**, DOF 23-08-2022.  
  https://www.diputados.gob.mx/LeyesBiblio/norma/reglamento/reg079_23ago22.doc
- `SRC-009`: Secretaría del Trabajo y Previsión Social, **Proceso de inspección - Conoce a tu inspector**.  
  https://conocetuinspector.stps.gob.mx/Publico/ProcesoInspeccion.aspx
- `SRC-010`: Diario Oficial de la Federación, **Lineamientos Operativos en Materia de Inspección Federal del Trabajo 2025**.  
  https://www.dof.gob.mx/2025/STPS/AvisoIFT.pdf

## 19. Documentos relacionados

- `01-Jornada-Laboral.md`
- `02-Tipos-de-Jornada.md`
- `03-Horas-Extra.md`
- `04-Descansos.md`
- `05-Registro-Electronico.md`
- `06-Teletrabajo.md`
- `07-Inspecciones-y-Sanciones.md`
- `08-Reglas-Derivadas.md`
- `README.md`
- `SOURCES.md`
- `CHANGELOG.md`
