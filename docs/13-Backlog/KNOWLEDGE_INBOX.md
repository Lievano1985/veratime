---

id: BACKLOG-KNOWLEDGE-INBOX
title: Knowledge Inbox
project: Jornada 360
version: 0.1.0
status: Draft
owner: Product Architecture
created: 2026-06-29
updated: 2026-06-29
tags:

* backlog
* knowledge
* inbox
* investigacion

---

# KNOWLEDGE INBOX

* Este documento funciona como la bandeja de entrada de conocimiento de Jornada 360.

* Aquí se registran ideas, preguntas, riesgos, principios y oportunidades detectadas durante el diseño del producto.

* Ningún elemento debe desarrollarse directamente desde este documento.

* Cada registro deberá ser evaluado y posteriormente trasladado a su documentación definitiva (Product Bible, ADR, Reglas de Negocio, Arquitectura, Backlog, etc.).

---

## Estado de un registro

* Nuevo
* En análisis
* Documentado
* Descartado

---

# PP-0001

**Tipo:** Principio de Producto

**Estado:** Nuevo

**Título:**
La plataforma protege a la empresa y al trabajador.

**Descripción:**

Jornada 360 no tiene como finalidad vigilar o sancionar a las empresas.

Su propósito es ayudar a las organizaciones a demostrar cumplimiento, reducir riesgos legales y mantener evidencia confiable de sus procesos.

La plataforma genera respaldo documental, no mecanismos de persecución.

**Destino:**

* DOC-0003 Product Bible

---

# IDEA-0001

**Tipo:** Idea

**Estado:** Nuevo

**Título:**
Arquitectura basada en conectores.

**Descripción:**

Las integraciones con sistemas externos deberán desarrollarse mediante conectores independientes para evitar acoplamiento con el núcleo de Jornada 360.

La primera integración prevista será ClickBalance.

**Destino:**

* Arquitectura
* Integraciones

---

# IDEA-0002

**Tipo:** Idea

**Estado:** Nuevo

**Título:**
Motor de políticas configurable.

**Descripción:**

Las reglas de operación no deberán estar escritas de forma fija en el código.

Cada empresa podrá configurar aspectos como:

* Métodos de registro.
* Geolocalización.
* Fotografía.
* Tolerancias.
* Correcciones.
* Validaciones.
* Evidencias requeridas.

**Destino:**

* Arquitectura
* Reglas de Negocio

---

# IDEA-0003

**Tipo:** Idea

**Estado:** Nuevo

**Título:**
Modelo híbrido de trabajadores.

**Descripción:**

Jornada 360 deberá permitir administrar trabajadores propios dentro de la plataforma y, al mismo tiempo, sincronizar trabajadores provenientes de sistemas externos mediante conectores.

La empresa decidirá cuál estrategia utilizar.

**Destino:**

* Base de Datos
* Integraciones

---

# IDEA-0004

**Tipo:** Idea

**Estado:** Nuevo

**Título:**
Registro mediante múltiples métodos.

**Descripción:**

El sistema deberá permitir diferentes mecanismos de registro sin modificar el núcleo de la aplicación.

Ejemplos:

* Web.
* Aplicación móvil.
* Código QR.
* Integraciones.
* API.
* Dispositivos futuros.

**Destino:**

* Arquitectura
* Registro Electrónico

---

# IDEA-0005

**Tipo:** Idea

**Estado:** Nuevo

**Título:**
Motor de evidencia digital.

**Descripción:**

Toda evidencia relacionada con un registro de jornada deberá administrarse mediante un componente independiente y configurable.

Las evidencias podrán incluir, según la configuración de la empresa y el marco legal aplicable:

* Fecha y hora.
* Ubicación.
* Dirección IP.
* Dispositivo.
* Fotografía.
* Comentarios.
* Archivos adjuntos.
* Historial de modificaciones.

**Destino:**

* Arquitectura
* Base de Datos

---

# IDEA-0006

**Tipo:** Idea

**Estado:** Nuevo

**Título:**
Motor de Políticas Empresariales.

**Descripción:**

Jornada 360 no implementará reglas fijas de registro.

Cada empresa podrá configurar sus propias políticas de operación dentro de los límites permitidos por la legislación.

Estas políticas determinarán:

* Métodos de registro permitidos.
* Evidencias requeridas.
* Correcciones.
* Validaciones.
* Tolerancias.
* Geolocalización.
* Fotografía.
* Dispositivos autorizados.
* Horarios.
* Excepciones.
* Integraciones.

**Destino:**

* Product Bible.
* Arquitectura.
* Base de Datos.
* Reglas de Negocio.

---

# QUESTION-0001

**Tipo:** Pregunta de investigación

**Estado:** Nuevo

**Título:**
¿Qué información exige realmente la legislación para el Registro Electrónico de la Jornada?

**Descripción:**

Determinar exactamente qué datos son obligatorios por ley, cuáles dependerán de lineamientos de la STPS y cuáles serán funcionalidades opcionales de Jornada 360.

**Destino:**

* LEG-0001-LFT

---

# QUESTION-0002

**Tipo:** Pregunta de investigación

**Estado:** Nuevo

**Título:**
¿Cómo deben manejarse los escenarios de trabajo remoto, híbrido y en campo?

**Descripción:**

Identificar las diferencias jurídicas y operativas para diseñar un sistema flexible que funcione en distintos modelos de trabajo.

**Destino:**

* LEG-0001-LFT
* Reglas de Negocio

---

# RISK-0001

**Tipo:** Riesgo

**Estado:** Nuevo

**Título:**
Cambios futuros en la legislación.

**Descripción:**

La plataforma deberá diseñarse para adaptarse a nuevas obligaciones legales mediante configuración y no mediante cambios constantes al código.

**Destino:**

* Arquitectura
* Product Bible

---

# RISK-0002

**Tipo:** Riesgo

**Estado:** Nuevo

**Título:**
Acoplamiento con proveedores externos.

**Descripción:**

El núcleo del sistema nunca deberá depender de un proveedor específico de nómina o recursos humanos.

Todas las integraciones deberán implementarse mediante conectores desacoplados.

**Destino:**

* ADR
* Arquitectura

---

# Próxima revisión

En cada sesión de trabajo, los registros de este documento deberán revisarse para decidir si:

* Se convierten en documentación oficial.
* Se transforman en una tarea del backlog.
* Se descartan.
* Permanecen en análisis.
