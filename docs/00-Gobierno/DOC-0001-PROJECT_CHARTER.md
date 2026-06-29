---

id: DOC-0001
title: Project Charter
project: Jornada 360
version: 0.1.0
status: Draft
owner: Product Architecture
created: 2026-06-29
updated: 2026-06-29
tags:

* gobierno
* producto
* charter
* jornada-360

---

# DOC-0001 - Project Charter

# Jornada 360

## Plataforma SaaS de Cumplimiento de Jornada Laboral

---

## 1. Propósito del documento

Este documento establece formalmente el inicio del producto **Jornada 360**.

Su propósito es definir la razón de existencia del producto, el problema que busca resolver, el alcance inicial, los límites del proyecto, los principios rectores, los riesgos principales y los criterios que guiarán las decisiones futuras.

Este documento será la base para los documentos posteriores de investigación legal, requisitos funcionales, arquitectura, base de datos, API, UX, integraciones y roadmap.

---

## 2. Nombre del producto

**Jornada 360**

---

## 3. Descripción general

Jornada 360 será una plataforma SaaS multi-tenant orientada al cumplimiento laboral en México.

Su objetivo principal será permitir que empresas, despachos y corporativos administren el Registro Electrónico de la Jornada Laboral, controlen jornadas, turnos, horas extraordinarias, evidencias digitales, reportes e integraciones con sistemas externos de nómina o recursos humanos.

Jornada 360 no será únicamente un reloj checador digital.

Será una plataforma de cumplimiento laboral basada en evidencia, configuración, trazabilidad e integraciones.

---

## 4. Contexto

La legislación laboral mexicana incorpora nuevas obligaciones relacionadas con el control y registro electrónico de la jornada laboral.

La reforma a la Ley Federal del Trabajo establece la obligación patronal de registrar electrónicamente la jornada laboral de cada persona trabajadora, incluyendo el horario de inicio y finalización, y proporcionar dicha información a la autoridad cuando sea requerida.

Adicionalmente, la reducción gradual de la jornada laboral y los límites a las horas extraordinarias generan una necesidad operativa y legal para que las empresas cuenten con herramientas tecnológicas capaces de administrar, calcular, auditar y demostrar el cumplimiento.

Jornada 360 nace como respuesta a esa necesidad.

---

## 5. Problema a resolver

Las empresas mexicanas necesitarán contar con mecanismos confiables para registrar la jornada laboral de sus trabajadores, controlar horas ordinarias y extraordinarias, generar evidencia digital y atender posibles inspecciones o requerimientos de la autoridad.

El problema no se limita a registrar entradas y salidas.

Las empresas enfrentan distintos escenarios operativos:

* Trabajadores en oficina.
* Trabajadores remotos.
* Trabajadores híbridos.
* Trabajadores en campo.
* Trabajadores en obras temporales.
* Trabajadores con turnos rotativos.
* Trabajadores nocturnos.
* Trabajadores móviles.
* Empresas con varias sucursales.
* Despachos que administran múltiples empresas.
* Empresas que ya usan sistemas externos de nómina.

Las soluciones tradicionales de asistencia pueden ser insuficientes cuando no contemplan multi-tenant, integraciones, evidencia digital, políticas configurables, auditoría, reportes legales y trazabilidad.

---

## 6. Oportunidad

Jornada 360 tiene la oportunidad de posicionarse como una plataforma especializada en cumplimiento laboral mexicano.

El mercado potencial incluye:

* Microempresas.
* Pequeñas y medianas empresas.
* Empresas medianas.
* Corporativos.
* Despachos contables.
* Consultores laborales.
* Áreas de recursos humanos.
* Empresas con personal remoto, híbrido o en campo.
* Empresas que requieren integración con sistemas de nómina.

La oportunidad principal no está en competir como un simple sistema de asistencia, sino como una plataforma que ayude a las empresas a demostrar cumplimiento legal mediante evidencia digital organizada y auditable.

---

## 7. Objetivo general

Desarrollar una plataforma SaaS multi-tenant que permita a empresas, despachos y corporativos administrar el Registro Electrónico de la Jornada Laboral, cumplir con la legislación mexicana vigente y generar evidencia digital confiable para auditorías, revisiones internas e inspecciones laborales.

---

## 8. Objetivos específicos

### 8.1 Objetivos legales y de cumplimiento

* Registrar electrónicamente el inicio de la jornada laboral.
* Registrar electrónicamente la finalización de la jornada laboral.
* Conservar evidencia asociada a los registros.
* Generar reportes consultables y exportables.
* Facilitar la atención de requerimientos de autoridad.
* Controlar jornadas ordinarias y extraordinarias.
* Permitir configuración conforme a reglas legales vigentes y futuras.
* Separar requisitos obligatorios, requisitos pendientes de lineamientos, buenas prácticas y funcionalidades comerciales.

### 8.2 Objetivos operativos

* Gestionar empresas en un modelo multi-tenant.
* Gestionar usuarios, roles y permisos.
* Gestionar trabajadores internos.
* Permitir sincronización de trabajadores desde sistemas externos.
* Administrar centros de trabajo.
* Administrar jornadas, turnos y políticas.
* Registrar entradas, salidas, descansos e incidencias.
* Permitir distintos esquemas de operación: oficina, remoto, híbrido, campo y móvil.

### 8.3 Objetivos comerciales

* Ofrecer planes SaaS por número de trabajadores, empresas, centros de trabajo, integraciones y funcionalidades.
* Permitir a despachos administrar múltiples empresas.
* Incorporar integraciones con sistemas de nómina iniciando con ClickBalance.
* Diseñar la plataforma para agregar nuevas integraciones en el futuro sin modificar el núcleo del sistema.
* Construir una solución vendible al público y escalable comercialmente.

### 8.4 Objetivos técnicos

* Construir una arquitectura modular.
* Mantener separación entre lógica de negocio, infraestructura, presentación e integraciones.
* Diseñar el sistema bajo un enfoque API First.
* Usar reglas configurables en lugar de valores fijos en código.
* Garantizar trazabilidad entre requisito legal, regla de negocio, módulo, base de datos, API y pantalla.
* Preparar la plataforma para una futura aplicación móvil.

---

## 9. Alcance inicial del producto

El alcance inicial contempla una primera versión funcional orientada a cumplimiento básico y operación SaaS.

### 9.1 Módulos incluidos en el alcance inicial

* Gestión de empresas.
* Gestión multi-tenant.
* Gestión de usuarios.
* Roles y permisos.
* Planes SaaS.
* Suscripciones.
* Trabajadores internos.
* Centros de trabajo.
* Jornadas.
* Turnos.
* Registro electrónico de jornada.
* Registro de inicio y finalización.
* Registro de descansos.
* Cálculo inicial de horas trabajadas.
* Cálculo inicial de horas extraordinarias.
* Evidencia digital básica.
* Reportes básicos.
* Auditoría de cambios.
* Integración inicial con ClickBalance.
* Importación CSV como alternativa operativa.

### 9.2 Evidencias consideradas en diseño inicial

Las siguientes evidencias podrán ser consideradas como configurables por empresa:

* Fecha y hora.
* Usuario.
* Trabajador.
* Empresa.
* Centro de trabajo.
* Dirección IP.
* Dispositivo.
* Navegador o aplicación.
* Geolocalización.
* Distancia respecto a centro autorizado.
* Fotografía.
* Firma o aceptación digital.
* Hash del registro.
* Bitácora de modificaciones.

No todas las evidencias serán obligatorias por defecto.

Su uso dependerá de la ley, lineamientos futuros, política de empresa, proporcionalidad, protección de datos y escenario laboral.

---

## 10. Fuera del alcance inicial

Jornada 360 no será inicialmente:

* Sistema de nómina.
* Sistema contable.
* ERP.
* CRM.
* Sistema completo de recursos humanos.
* Sistema de reclutamiento.
* Sistema de evaluación de desempeño.
* Sistema de productividad.
* Sistema de vigilancia permanente.
* Sistema de monitoreo invasivo.

La plataforma podrá integrarse con sistemas externos, pero no buscará reemplazarlos en la primera versión.

---

## 11. Clientes objetivo

### 11.1 Empresas

Organizaciones que necesitan cumplir con el registro electrónico de jornada y administrar trabajadores propios.

### 11.2 Despachos

Despachos contables, laborales o administrativos que desean ofrecer el servicio a múltiples empresas cliente.

### 11.3 Corporativos

Empresas con múltiples centros de trabajo, áreas, sucursales, razones sociales o esquemas laborales diversos.

### 11.4 Consultores laborales

Profesionales que asesoran empresas en cumplimiento laboral y podrían usar Jornada 360 como herramienta de gestión y evidencia.

---

## 12. Usuarios del sistema

### 12.1 Super administrador de plataforma

Usuario interno de Jornada 360 con capacidad de administrar empresas, planes, suscripciones, integraciones globales y configuración general de la plataforma.

### 12.2 Administrador de despacho

Usuario que administra varias empresas cliente dentro de la plataforma.

### 12.3 Administrador de empresa

Usuario responsable de configurar la empresa, trabajadores, centros de trabajo, políticas, jornadas, turnos e integraciones.

### 12.4 Recursos humanos

Usuario operativo encargado de revisar registros, incidencias, horas extraordinarias, reportes y evidencias.

### 12.5 Supervisor

Usuario responsable de validar registros, autorizar incidencias o revisar trabajadores asignados.

### 12.6 Trabajador

Usuario que registra su jornada laboral, consulta sus registros y puede solicitar correcciones o aclaraciones según las políticas configuradas.

---

## 13. Principios rectores del producto

### 13.1 Compliance First

Toda decisión priorizará el cumplimiento laboral, la evidencia y la trazabilidad.

### 13.2 API First

Toda funcionalidad relevante deberá poder exponerse mediante API para permitir futuras integraciones, aplicaciones móviles y ecosistema externo.

### 13.3 Configuración antes que programación

Las reglas legales, operativas y de evidencia deberán ser configurables cuando exista posibilidad razonable de cambio.

### 13.4 Multi-tenant desde el inicio

La plataforma será diseñada para atender múltiples empresas desde una misma base tecnológica, garantizando aislamiento lógico de datos.

### 13.5 Integraciones desacopladas

Las integraciones con sistemas externos deberán funcionar como conectores o plugins independientes del núcleo del sistema.

### 13.6 Evidencia auditable

Todo registro relevante deberá poder auditarse: quién, cuándo, desde dónde, mediante qué método y bajo qué política.

### 13.7 No dependencia de terceros

La plataforma deberá poder operar de manera independiente aunque una integración externa falle, se desconecte o cambie.

### 13.8 Trazabilidad completa

Cada módulo deberá poder rastrearse hacia un requisito legal, regla de negocio, escenario operativo o decisión de producto.

### 13.9 Privacidad y proporcionalidad

El uso de datos como GPS, fotografía, biometría, IP o dispositivo deberá justificarse, configurarse y documentarse conforme a protección de datos y proporcionalidad.

### 13.10 Producto enfocado

Jornada 360 deberá evitar convertirse en un ERP o sistema generalista de recursos humanos. Su enfoque principal será cumplimiento laboral relacionado con jornada, evidencia e inspección.

---

## 14. Supuestos iniciales

* La plataforma se venderá al público bajo modelo SaaS.
* La plataforma deberá permitir registro de múltiples empresas.
* Las empresas podrán tener trabajadores internos o sincronizados desde sistemas externos.
* ClickBalance será la primera integración considerada.
* Las reglas legales pueden cambiar o ser complementadas por disposiciones de autoridad.
* La STPS podrá emitir lineamientos técnicos adicionales.
* Geolocalización, fotografía, QR, biometría y otros métodos de evidencia deberán ser configurables.
* El sistema deberá operar para escenarios de oficina, remoto, híbrido, campo y móviles.
* La documentación será fuente oficial antes del código.

---

## 15. Restricciones iniciales

* No se desarrollará código sin documentación previa.
* No se asumirán requisitos legales no confirmados.
* No se harán obligatorias evidencias que la ley no exija expresamente, salvo configuración de empresa.
* No se mezclará la lógica de negocio con la interfaz.
* No se acoplará el núcleo del sistema a ClickBalance ni a ningún proveedor externo.
* No se eliminarán registros laborales críticos; se manejarán estados, cancelaciones o archivado con auditoría.
* No se construirá nómina en la primera versión.

---

## 16. Riesgos iniciales

| ID    | Riesgo                                     | Impacto | Mitigación                                                         |
| ----- | ------------------------------------------ | ------- | ------------------------------------------------------------------ |
| R-001 | Cambios o lineamientos posteriores de STPS | Alto    | Motor de reglas configurable                                       |
| R-002 | Interpretación legal incorrecta            | Alto    | Matriz legal documentada y validación profesional                  |
| R-003 | Construir demasiadas funciones en MVP      | Alto    | Alcance controlado y roadmap                                       |
| R-004 | Acoplamiento con ClickBalance              | Medio   | Arquitectura de plugins                                            |
| R-005 | Problemas de privacidad por GPS/foto       | Alto    | Configuración, aviso de privacidad y proporcionalidad              |
| R-006 | Falta de adopción por trabajadores         | Medio   | UX simple y flujos claros                                          |
| R-007 | Fallas de conectividad en campo            | Medio   | Diseño futuro para modo offline                                    |
| R-008 | Volumen alto de registros                  | Alto    | PostgreSQL, índices, particionamiento futuro y auditoría eficiente |
| R-009 | Multi-tenant mal implementado              | Alto    | Aislamiento por empresa y pruebas de seguridad                     |
| R-010 | Competencia de relojes checadores baratos  | Medio   | Posicionamiento como cumplimiento laboral, no solo asistencia      |

---

## 17. Factores críticos de éxito

* Investigación legal sólida.
* Matriz de cumplimiento clara.
* Arquitectura modular.
* Buen modelo multi-tenant.
* Reportes confiables.
* Facilidad de uso para trabajadores.
* Configuración flexible por empresa.
* Integraciones desacopladas.
* Buen manejo de datos personales.
* Documentación viva y mantenible.
* MVP enfocado.
* Capacidad de adaptarse a cambios legales.

---

## 18. Definición de terminado del proyecto inicial

El proyecto inicial se considerará listo para entrar a fase de desarrollo cuando existan, como mínimo, los siguientes documentos aprobados:

* DOC-0001 - Project Charter.
* DOC-0002 - Product Vision.
* DOC-0003 - Product Bible.
* DOC-0004 - Project Principles.
* DOC-0005 - Glossary.
* LEG-0001 - Ley Federal del Trabajo.
* LEG-0002 - Reforma 2026.
* LEG-0005 - Protección de Datos.
* LEG-0006 - Matriz de Cumplimiento.
* BUS-0004 - Escenarios.
* PRD.
* SRS.
* Arquitectura general.
* Modelo inicial de base de datos.
* ADR iniciales de stack tecnológico.

---

## 19. Roadmap documental inicial

### Fase 0 - Gobierno del producto

* DOC-0001 - Project Charter.
* DOC-0002 - Product Vision.
* DOC-0003 - Product Bible.
* DOC-0004 - Project Principles.
* DOC-0005 - Glossary.

### Fase 1 - Investigación legal

* LEG-0001 - Ley Federal del Trabajo.
* LEG-0002 - Reforma 2026.
* LEG-0003 - STPS.
* LEG-0004 - Teletrabajo.
* LEG-0005 - Protección de Datos.
* LEG-0006 - Matriz de Cumplimiento.

### Fase 2 - Investigación de negocio

* BUS-0001 - Mercado.
* BUS-0002 - Competencia.
* BUS-0003 - Personas.
* BUS-0004 - Escenarios.
* BUS-0005 - Industrias.
* BUS-0006 - Planes SaaS.

### Fase 3 - Requisitos

* PRD.
* SRS.
* Requisitos funcionales.
* Requisitos no funcionales.
* Reglas de negocio.
* Casos de uso.

### Fase 4 - Arquitectura

* Arquitectura general.
* Arquitectura multi-tenant.
* Arquitectura modular.
* Arquitectura de integraciones.
* Seguridad.
* Eventos.
* Servicios.

### Fase 5 - Base de datos

* Modelo entidad-relación.
* Diccionario de datos.
* Índices.
* Auditoría.
* Guía de migraciones.

---

## 20. Decisiones iniciales registradas

| ID       | Decisión                                                                        |
| -------- | ------------------------------------------------------------------------------- |
| DEC-0001 | Jornada 360 será SaaS multi-tenant.                                             |
| DEC-0002 | Jornada 360 será API First.                                                     |
| DEC-0003 | Las integraciones serán desacopladas mediante conectores/plugins.               |
| DEC-0004 | Las reglas legales vivirán en un motor configurable.                            |
| DEC-0005 | Todo será parametrizable por empresa cuando aplique.                            |
| DEC-0006 | No se desarrollará funcionalidad sin documentación previa.                      |
| DEC-0007 | Jornada 360 será Compliance First.                                              |
| DEC-0008 | Toda investigación deberá terminar en requisitos funcionales accionables.       |
| DEC-0009 | El stack base propuesto será Laravel, PostgreSQL, Redis y arquitectura modular. |

---

## 21. Criterios de aceptación de este documento

Este documento será aceptado cuando:

* Defina claramente qué es Jornada 360.
* Defina el problema que resuelve.
* Defina el alcance inicial.
* Defina lo que queda fuera del alcance inicial.
* Defina principios rectores.
* Identifique riesgos iniciales.
* Establezca una base suficiente para continuar con Product Vision e Investigación Legal.
* Sea aprobado por el Product Owner.

---

## 22. Estado del documento

**Estado actual:** Draft.

Este documento deberá ser revisado y aprobado antes de avanzar formalmente a DOC-0002 - Product Vision.

---

## 23. Notas

Este documento no sustituye la investigación legal detallada.

Los fundamentos legales, artículos específicos, criterios de cumplimiento, obligaciones, excepciones y reglas derivadas serán desarrollados en los documentos legales correspondientes dentro de la carpeta `docs/01-Legal/`.

---
