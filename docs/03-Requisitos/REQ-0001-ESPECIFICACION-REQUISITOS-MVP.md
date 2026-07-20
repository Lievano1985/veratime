---
id: REQ-0001
title: Especificación de requisitos del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-01
updated: 2026-07-03
tags:
  - requisitos
  - mvp
  - funcionales
  - no-funcionales
  - veratime
---

# REQ-0001 — Especificación de requisitos del MVP

## 1. Objetivo

Definir los requisitos funcionales y no funcionales del MVP de Vera Time que deberá estar listo para producción antes del 1 de enero de 2027.

Este documento convierte en especificaciones de producto:

- La investigación jurídica aprobada.
- El modelo de negocio.
- El alcance del MVP.
- El presupuesto.
- El roadmap acelerado.
- Las decisiones sobre alertas preventivas.
- La revisión y conformidad digital de la jornada.

No define todavía tablas físicas, endpoints, componentes de interfaz ni arquitectura detallada.

---

## 2. Alcance del MVP

El MVP de Vera Time deberá concentrarse en entregar una plataforma operativa, vendible y legalmente útil antes del 1 de enero de 2027.

El alcance se divide en capacidades indispensables. Cada capacidad debe aportar valor directo al cumplimiento, a la operación diaria o a la evidencia documental.

### 2.1 Plataforma SaaS multi-tenant

**Incluye:**

- Administración de múltiples empresas dentro de una sola plataforma.
- Separación estricta de datos por empresa.
- Usuarios con acceso a una o varias empresas.
- Cambio de empresa activa cuando el usuario tenga permiso.
- Roles y permisos por empresa.
- Restricción de acceso por centro, área o grupo cuando aplique.
- Plan o suscripción asignado por empresa.
- Estado de empresa: activa, suspendida, cancelada o en piloto.

**No incluye en el MVP:**

- Ambientes dedicados por cliente.
- Marca blanca.
- Subdominios personalizados.
- Facturación automatizada avanzada.
- Marketplace de integraciones.

**Valor:** permite operar Vera Time como SaaS real, atendiendo varias empresas sin crear una instalación separada para cada cliente.

**Criterio de validación:** una empresa no debe poder ver, modificar ni exportar información de otra empresa bajo ninguna condición.

### 2.2 Empresas, centros y estructura básica

**Incluye:**

- Registro de empresa.
- Razón social.
- Nombre comercial.
- RFC.
- Zona horaria principal.
- Centros de trabajo.
- Zona horaria por centro.
- Estado del centro.
- Datos básicos de contacto.
- Configuración inicial del periodo de nómina o cierre.
- Configuración de días laborales generales.
- Configuración de descansos obligatorios aplicables.

**No incluye en el MVP:**

- Estructura organizacional compleja.
- Organigramas.
- Presupuestos por área.
- Administración avanzada de sucursales.
- Múltiples países.

**Valor:** permite ubicar correctamente a cada persona trabajadora, aplicar zona horaria, generar reportes por centro y delimitar evidencia.

**Criterio de validación:** debe poder configurarse una empresa con al menos dos centros y generar reportes separados por cada uno.

### 2.3 Personas trabajadoras y relaciones laborales

**Incluye:**

- Alta manual de personas trabajadoras.
- Alta masiva por CSV.
- Identificador interno del trabajador.
- Nombre completo.
- CURP opcional.
- RFC opcional.
- Correo o usuario de acceso.
- Centro asignado.
- Puesto.
- Fecha de ingreso.
- Estado: activo, baja, suspendido.
- Relación laboral vigente.
- Historial de cambios relevantes.
- Fecha de baja sin eliminación de registros.
- Condiciones laborales con vigencia.
- Modalidad: presencial, híbrida, teletrabajo básico o campo.
- Día de descanso asignado.
- Horario o turno aplicable.
- Política de registro aplicable.

**No incluye en el MVP:**

- Expediente laboral completo.
- Documentos personales avanzados.
- Contratos generados automáticamente.
- Incapacidades.
- Vacaciones.
- Evaluaciones de desempeño.
- Reclutamiento.

**Valor:** es la base para calcular jornada, generar evidencia individual y cobrar por persona activa.

**Criterio de validación:** una persona debe poder cambiar de horario o centro sin que se modifiquen sus jornadas históricas.

### 2.4 Horarios, turnos y vigencias

**Incluye:**

- Catálogo de horarios.
- Tipo legal programado: diurno, nocturno o mixto.
- Hora de entrada programada.
- Hora de salida programada.
- Pausas o descansos programados.
- Días aplicables.
- Vigencia del horario.
- Turnos fijos.
- Turnos rotativos básicos.
- Asignación de turno por persona.
- Asignación de turno por grupo.
- Cambio de horario con fecha efectiva.
- Día de descanso semanal.
- Calendario de descansos obligatorios.
- Identificación de jornadas que cruzan medianoche.

**No incluye en el MVP:**

- Planeación avanzada de turnos con optimización automática.
- Bolsa de turnos.
- Intercambio de turnos entre trabajadores.
- Forecast de demanda.
- Inteligencia para asignación automática.
- Calendario laboral complejo por convenio colectivo.

**Valor:** permite comparar lo planeado contra lo registrado y calcular si existe una posible desviación.

**Criterio de validación:** debe poder configurarse un trabajador con turno nocturno que inicia un día y termina al siguiente, sin romper el cálculo.

### 2.5 Registro electrónico de jornada

**Incluye:**

- Registro de entrada.
- Registro de salida.
- Registro de inicio de pausa.
- Registro de fin de pausa.
- Registro desde web responsiva o PWA.
- Registro desde kiosco o dispositivo compartido.
- Captura administrativa justificada.
- Importación de eventos por CSV.
- API oficial para recibir eventos.
- Fecha y hora del hecho.
- Fecha y hora de recepción.
- Zona horaria.
- Fuente del evento.
- Usuario, dispositivo o integración de origen.
- Estado del evento.
- Prevención de duplicados.
- Manejo de eventos fuera de orden.
- Registro tardío.
- Bitácora del evento.
- No eliminación destructiva.

**No incluye en el MVP:**

- App móvil nativa.
- Checador biométrico propio.
- Reconocimiento facial propio.
- Huella digital propia.
- GPS obligatorio.
- Foto obligatoria.
- Registro offline avanzado con app nativa.
- Integración con todos los relojes checadores del mercado.

**Valor:** cumple el núcleo del registro electrónico y genera la materia prima para cálculos, reportes y evidencia.

**Criterio de validación:** una persona debe poder registrar entrada y salida; el sistema debe reconstruir la jornada y conservar la fuente del registro.

### 2.6 Motor legal de cálculo

**Incluye:**

- Reconstrucción de jornada a partir de eventos.
- Clasificación diurna, nocturna o mixta.
- Cálculo de minutos diurnos y nocturnos.
- Límite diario por tipo de jornada.
- Límite semanal vigente por año.
- Cálculo de tiempo ordinario.
- Cálculo de horas extraordinarias.
- Separación de bandas de horas extra.
- Validación de máximo diario de doce horas.
- Descanso mínimo en jornada continua.
- Pausas computables y no computables.
- Trabajo en domingo.
- Trabajo en descanso semanal.
- Trabajo en descanso obligatorio.
- Más de seis días consecutivos.
- Reglas legales versionadas.
- Condiciones más favorables configurables.
- Explicación del cálculo.
- Recalculo después de corrección.

**No incluye en el MVP:**

- Cálculo completo de nómina.
- Impuestos.
- Seguridad social.
- Recibos de nómina.
- Cálculo monetario definitivo.
- Casos especiales de todos los capítulos laborales.
- Interpretaciones jurídicas automáticas.

**Valor:** convierte los eventos en información útil, explicable y defendible.

**Criterio de validación:** dado un conjunto de eventos, el sistema debe explicar qué regla aplicó, cuántas horas ordinarias calculó, si existieron horas extra y qué alertas generó.

### 2.7 Alertas preventivas de posibles incumplimientos

**Incluye:**

- Generación automática de alertas.
- Alertas por entrada faltante.
- Alertas por salida faltante.
- Eventos duplicados.
- Jornada incompleta.
- Jornada diaria excedida.
- Jornada semanal excedida.
- Horas extraordinarias.
- Más de doce horas totales en un día.
- Descanso insuficiente.
- Más de seis días consecutivos trabajados.
- Trabajo en domingo.
- Trabajo en descanso obligatorio.
- Horario no vigente.
- Relación laboral no vigente.
- Corrección pendiente.
- Diferencia entre tiempo calculado y autorizado.
- Diferencia entre tiempo autorizado y exportado.
- Reporte de periodo con diferencias.
- Niveles: informativa, advertencia, alta y crítica.
- Estados: nueva, en revisión, pendiente de información, justificada, corregida y cerrada.
- Responsable de atención.
- Comentarios.
- Evidencia.
- Resolución trazable.
- Bloqueo de cierre cuando exista alerta crítica pendiente.

**No incluye en el MVP:**

- Predicción con inteligencia artificial.
- Recomendaciones automáticas avanzadas.
- Envío masivo por WhatsApp.
- Tablero avanzado de riesgos.
- Priorización automática por impacto económico.
- Dictamen jurídico automático.

**Valor:** permite actuar antes del cierre del periodo y evita que los problemas se descubran hasta la nómina, auditoría o inspección.

**Criterio de validación:** si una jornada supera el límite configurado, el sistema debe generar una alerta neutral de posible desviación sin modificar el registro real.

### 2.8 Incidencias y correcciones no destructivas

**Incluye:**

- Creación automática de incidencia desde alerta.
- Creación manual de incidencia.
- Solicitud de corrección por persona trabajadora.
- Solicitud de corrección por supervisor o RH.
- Tipos de incidencia.
- Comentarios.
- Evidencia.
- Valor original.
- Valor propuesto.
- Motivo.
- Aprobación o rechazo.
- Recalculo posterior.
- Conservación de versión previa.
- Historial completo.
- Estado de controversia cuando no exista acuerdo.
- Cierre de incidencia.

**No incluye en el MVP:**

- Flujos complejos con muchas aprobaciones.
- Firma avanzada de cada incidencia.
- Conciliación laboral formal.
- Comunicación automática con autoridades.
- Chat interno avanzado.

**Valor:** permite corregir errores sin destruir evidencia ni perder confianza.

**Criterio de validación:** una salida faltante debe poder corregirse mediante una solicitud aprobada, conservando el registro original y generando una nueva versión del cálculo.

### 2.9 Portal de la persona trabajadora

**Incluye:**

- Acceso individual.
- Consulta de jornadas diarias.
- Consulta semanal o por periodo.
- Visualización de entradas y salidas.
- Visualización de pausas.
- Visualización de horas ordinarias.
- Visualización de posibles horas extra.
- Visualización de incidencias.
- Visualización de alertas visibles para el trabajador.
- Solicitud de aclaración.
- Adjuntar evidencia.
- Seguimiento de estado.
- Consulta del reporte de cierre.
- Conformidad o no conformidad.

**No incluye en el MVP:**

- Red social interna.
- Chat completo.
- Documentos laborales completos.
- Vacaciones.
- Recibos de nómina.
- Beneficios.
- Encuestas.

**Valor:** da transparencia, reduce reclamos tardíos y permite que el trabajador participe en la validación de su información.

**Criterio de validación:** una persona trabajadora debe poder entrar, ver su semana y solicitar una aclaración sobre un registro específico.

### 2.10 Cierre de periodo y conformidad digital

**Incluye:**

- Configuración de periodo: semanal, quincenal, mensual o periodo de nómina.
- Cierre administrativo.
- Revisión previa de alertas.
- Generación de reporte individual.
- Versionamiento del reporte.
- Estados del periodo.
- Envío a revisión del trabajador.
- Opción conforme.
- Opción no conforme / solicitar aclaración.
- Opción pendiente de revisión.
- Confirmación expresa.
- Texto de aceptación sin renuncia de derechos.
- Identidad de la persona.
- Fecha y hora.
- Zona horaria.
- Versión exacta del reporte.
- Hash del reporte.
- Método de autenticación.
- IP y dispositivo como datos auxiliares.
- Nueva versión si hay corrección.
- Nueva revisión cuando cambia el reporte.
- Sin aceptación automática por silencio.

**No incluye en el MVP:**

- Firma electrónica avanzada de proveedor externo.
- e.firma SAT.
- Sellado de tiempo certificado externo.
- Firma biométrica.
- Reconocimiento facial para firmar.
- Notarización.
- Blockchain.

**Valor:** fortalece la evidencia laboral, permite detectar inconformidades a tiempo y mejora la defensa documental de empresa y trabajador.

**Criterio de validación:** un reporte firmado no puede modificarse. Si cambia, se conserva la versión anterior y se genera una nueva versión pendiente de revisión.

### 2.11 Reportes operativos y regulatorios

**Incluye:**

- Reporte diario.
- Reporte semanal.
- Reporte por periodo.
- Reporte por persona.
- Reporte por centro.
- Reporte de horas extraordinarias.
- Reporte de descansos.
- Reporte de domingos.
- Reporte de descansos obligatorios.
- Reporte de incidencias.
- Reporte de alertas.
- Reporte de conformidad digital.
- Reporte de personas sin cierre.
- Reporte de jornadas incompletas.
- Exportación PDF.
- Exportación CSV o XLSX.
- Filtros básicos.
- Totales y detalles.

**No incluye en el MVP:**

- BI avanzado.
- Dashboards ejecutivos complejos.
- Gráficas predictivas.
- Reportes personalizados ilimitados.
- Conector directo con Power BI.
- Constructor visual de reportes.

**Valor:** permite operar día a día, revisar riesgos y preparar información para nómina o autoridad.

**Criterio de validación:** RH debe poder generar un reporte semanal por centro con jornadas completas, incompletas, horas extra e incidencias.

### 2.12 Expedientes y exportaciones de evidencia

**Incluye:**

- Expediente por persona.
- Expediente por centro.
- Expediente por periodo.
- Expediente por solicitud.
- Eventos fuente.
- Cálculos.
- Incidencias.
- Correcciones.
- Alertas.
- Reportes firmados.
- Versiones.
- Manifiesto de integridad.
- Hash.
- Fecha de generación.
- Usuario que generó.
- Alcance del expediente.
- Exportación en PDF.
- Exportación estructurada.
- Paquete ZIP cuando aplique.
- Registro de entrega o descarga.

**No incluye en el MVP:**

- Portal especial para autoridad.
- Envío automático a STPS.
- Integración con plataformas oficiales no publicadas.
- Certificación externa de expediente.
- Firma avanzada institucional.

**Valor:** permite responder de forma ordenada y delimitada ante auditorías, revisiones internas o inspecciones.

**Criterio de validación:** el sistema debe generar un expediente de una persona y un periodo específico sin incluir datos de otras personas no solicitadas.

### 2.13 Importaciones CSV e interoperabilidad API

**Incluye:**

- Plantilla CSV para personas.
- Plantilla CSV para horarios.
- Plantilla CSV para eventos.
- Validación por fila.
- Resultado de importación.
- Errores descargables.
- API oficial para crear o actualizar trabajadores.
- API oficial para crear eventos de jornada.
- API oficial para consultar jornadas calculadas.
- API oficial para consultar reportes de periodo.
- Interoperabilidad bidireccional entre interfaz, API, CSV, jobs e integraciones.
- Credenciales por empresa.
- Idempotencia.
- Bitácora técnica.
- Identificador externo.

**No incluye en el MVP:**

- Marketplace de integraciones.
- Conectores listos para todos los relojes.
- Integración directa con todas las nóminas.
- Webhooks avanzados.
- SDK público.
- Sincronización bidireccional compleja.

**Valor:** permite adoptar el sistema sin capturar todo manualmente y abre la puerta a integraciones futuras.

**Criterio de validación:** una empresa debe poder importar trabajadores y eventos desde CSV, con errores claros cuando una fila no sea válida.

### 2.14 Seguridad, auditoría, respaldos y monitoreo

**Incluye:**

- Autenticación.
- Roles y permisos.
- Protección contra acceso cruzado.
- Auditoría de operaciones sensibles.
- Registro de cambios.
- Registro de accesos relevantes.
- Respaldos automáticos.
- Restauración probada.
- Logs de errores.
- Monitoreo básico.
- Alertas técnicas.
- Protección de secretos.
- Cifrado en tránsito.
- Control de sesiones.
- Principio de mínimo privilegio.

**No incluye en el MVP:**

- Certificación ISO.
- SOC 2.
- Pentest formal completo.
- SSO empresarial avanzado.
- MFA obligatorio para todos.
- SIEM dedicado.
- Alta disponibilidad multi-región.

**Valor:** sin seguridad y trazabilidad, el producto no puede venderse como plataforma de evidencia laboral.

**Criterio de validación:** debe existir bitácora de quién modificó una jornada, cuándo lo hizo, qué cambió y por qué.

### 2.15 Piloto e implementación inicial

**Incluye:**

- Selección de empresas piloto.
- Diagnóstico inicial.
- Carga de datos.
- Configuración de horarios.
- Capacitación a administradores.
- Capacitación básica a trabajadores.
- Soporte durante arranque.
- Revisión diaria durante el piloto.
- Registro de problemas.
- Ajustes de configuración.
- Medición de uso.
- Retroalimentación.
- Validación de disposición de pago.

**No incluye en el MVP:**

- Implementación masiva nacional.
- Mesa de ayuda 24/7.
- Consultoría laboral profunda para cada cliente.
- Migración histórica extensa.
- Capacitación presencial ilimitada.
- Personalización profunda por cliente.

**Valor:** el piloto valida producto, precio, operación y riesgo antes de escalar comercialmente.

**Criterio de validación:** una empresa piloto debe operar al menos dos semanas con jornadas reales, correcciones, reportes y cierre de periodo.

### 2.16 Capacidades P1 que aportan valor pero no deben retrasar el MVP

Estas capacidades pueden incluirse solo si no comprometen la fecha de producción.

| Capacidad | Incluye | No incluye |
|---|---|---|
| Teletrabajo básico | Modalidad de teletrabajo, lugar acordado, política de desconexión y documento asociado. | NOM-037 completa, listas avanzadas, gestión completa de equipos y evaluaciones de seguridad y salud. |
| Exportación a prenómina | Horas ordinarias, horas extra, domingos, descanso obligatorio e incidencias. | Salario, ISR, IMSS, timbrado y recibos de nómina. |
| Notificaciones | Recordatorios de cierre, revisión y alertas a responsables. | WhatsApp masivo, SMS masivo y automatizaciones complejas. |

### 2.17 Preguntas de validación del alcance

Antes de cerrar el alcance, se deberán responder afirmativamente estas preguntas:

1. ¿El MVP permite registrar inicio y fin de jornada por persona?
2. ¿Puede calcular correctamente jornadas diurnas, nocturnas y mixtas?
3. ¿Puede detectar horas extraordinarias y límites excedidos?
4. ¿Puede detectar descansos insuficientes?
5. ¿Puede detectar jornadas incompletas?
6. ¿Puede generar alertas antes del cierre?
7. ¿Puede corregir sin borrar historial?
8. ¿Puede generar una nueva versión después de una corrección?
9. ¿Puede el trabajador revisar su reporte?
10. ¿Puede marcar conforme o no conforme?
11. ¿Puede generarse evidencia del reporte firmado?
12. ¿Puede exportarse un expediente por periodo?
13. ¿Puede RH operar sin depender del desarrollador?
14. ¿Puede una empresa piloto configurarse en menos de una semana?
15. ¿Puede el sistema cobrar por persona activa?
16. ¿Puede crecer a varias empresas sin mezclar datos?
17. ¿Puede actualizar reglas legales sin tocar pantallas?
18. ¿Puede funcionar sin biometría ni app nativa?
19. ¿Puede venderse como cumplimiento y evidencia, no como simple checador?
20. ¿Hay algo que, si falta, impide vender o pilotear antes de enero de 2027?

---

## 3. Fuera del MVP

Quedan fuera:

- Aplicaciones móviles nativas.
- Biometría propia.
- Reconocimiento facial.
- Hardware propio.
- Nómina integral.
- Inteligencia artificial.
- Analítica avanzada.
- Integraciones múltiples con relojes checadores.
- Operación internacional.
- Módulo completo de seguridad y salud en teletrabajo.
- Firma electrónica avanzada de terceros.
- Sellado de tiempo certificado externo, salvo decisión posterior.

---

## 4. Actores

| Código | Actor | Descripción |
|---|---|---|
| ACT-001 | Superadministrador | Administra la plataforma SaaS, planes, empresas y configuraciones globales. |
| ACT-002 | Administrador de empresa | Configura la empresa, centros, usuarios, trabajadores, horarios y políticas. |
| ACT-003 | Recursos humanos | Administra relaciones laborales, turnos, incidencias y reportes. |
| ACT-004 | Supervisor | Revisa jornadas, incidencias y alertas de personas bajo su responsabilidad. |
| ACT-005 | Nómina/Prenómina | Consulta y exporta tiempos y conceptos autorizados. |
| ACT-006 | Jurídico/Cumplimiento | Consulta evidencia, expedientes y trazabilidad. |
| ACT-007 | Persona trabajadora | Registra eventos, consulta jornadas y manifiesta conformidad o inconformidad. |
| ACT-008 | Auditor/Inspector autorizado | Recibe un expediente delimitado y previamente autorizado. |
| ACT-009 | Integración externa | Envía o consulta información mediante API o importación. |
| ACT-010 | Soporte Vera Time | Atiende incidencias técnicas con acceso restringido y auditado. |

---

## 5. Módulos del MVP

| Código | Módulo |
|---|---|
| MOD-001 | Plataforma multi-tenant |
| MOD-002 | Empresas y centros |
| MOD-003 | Personas y relaciones laborales |
| MOD-004 | Horarios y turnos |
| MOD-005 | Registro electrónico |
| MOD-006 | Motor legal |
| MOD-007 | Alertas preventivas |
| MOD-008 | Incidencias y correcciones |
| MOD-009 | Portal de la persona trabajadora |
| MOD-010 | Cierre y conformidad digital |
| MOD-011 | Reportes y expedientes |
| MOD-012 | Importaciones e integraciones |
| MOD-013 | Seguridad y auditoría |
| MOD-014 | Suscripción y límites del plan |
| MOD-015 | Administración global |

---

# 6. Requisitos funcionales

## 6.1 Plataforma multi-tenant

### RF-MT-001 — Aislamiento por empresa

El sistema deberá impedir que una empresa consulte, modifique o exporte datos de otra empresa.

**Prioridad:** P0

**Criterios de aceptación:**

- Toda consulta operativa aplica el contexto de empresa.
- Un usuario sin acceso a una empresa recibe denegación.
- Una URL o identificador manipulado no permite acceso cruzado.
- Las exportaciones solo contienen información del tenant activo.

### RF-MT-002 — Usuario con acceso a varias empresas

Un usuario autorizado podrá pertenecer a una o varias empresas y cambiar entre ellas.

**Prioridad:** P0

### RF-MT-003 — Roles y permisos

El sistema deberá manejar permisos por rol y alcance.

**Prioridad:** P0

Como mínimo:

- Superadministrador.
- Administrador de empresa.
- Recursos humanos.
- Supervisor.
- Nómina.
- Jurídico.
- Persona trabajadora.
- Solo lectura.

### RF-MT-004 — Alcance por centro o equipo

Los permisos podrán limitarse por centro, área o grupo de personas.

**Prioridad:** P0

---

## 6.2 Empresas y centros

### RF-EMP-001 — Alta de empresa

El superadministrador podrá registrar una empresa cliente.

**Datos mínimos:**

- Razón social.
- Nombre comercial.
- RFC.
- Zona horaria principal.
- Estado.
- Plan.
- Fecha de activación.

**Prioridad:** P0

### RF-EMP-002 — Centros de trabajo

La empresa podrá registrar uno o varios centros de trabajo.

**Prioridad:** P0

### RF-EMP-003 — Zona horaria por centro

Cada centro podrá tener una zona horaria específica.

**Prioridad:** P0

### RF-EMP-004 — Configuración histórica

Los cambios relevantes de empresa o centro tendrán vigencia y no alterarán registros cerrados.

**Prioridad:** P0

---

## 6.3 Personas trabajadoras y relaciones laborales

### RF-PER-001 — Alta de persona trabajadora

La empresa podrá registrar personas trabajadoras manualmente, por CSV o API.

**Prioridad:** P0

### RF-PER-002 — Relación laboral

Cada persona deberá tener al menos una relación laboral con:

- Empresa.
- Centro.
- Número o clave interna.
- Fecha de ingreso.
- Puesto.
- Estado.
- Fecha de baja, cuando corresponda.

**Prioridad:** P0

### RF-PER-003 — Condiciones laborales con vigencia

El sistema deberá conservar históricamente:

- Tipo de jornada.
- Jornada semanal pactada.
- Horario o turno.
- Día de descanso.
- Modalidad.
- Política aplicable.
- Fecha de vigencia.

**Prioridad:** P0

### RF-PER-004 — Baja sin eliminación

Una baja laboral no eliminará jornadas, eventos, reportes ni evidencias.

**Prioridad:** P0

### RF-PER-005 — Acceso individual

Cada persona trabajadora tendrá acceso únicamente a sus registros y solicitudes.

**Prioridad:** P0

---

## 6.4 Horarios y turnos

### RF-HOR-001 — Catálogo de horarios

La empresa podrá crear horarios con:

- Nombre.
- Tipo legal programado.
- Hora de inicio.
- Hora de fin.
- Descansos.
- Días aplicables.
- Zona horaria.
- Vigencia.

**Prioridad:** P0

### RF-HOR-002 — Turnos rotativos

El sistema permitirá programar turnos rotativos.

**Prioridad:** P0

### RF-HOR-003 — Asignación por persona o grupo

Los horarios podrán asignarse individualmente o por grupo.

**Prioridad:** P0

### RF-HOR-004 — Cambio con fecha efectiva

Un cambio de horario no modificará jornadas anteriores.

**Prioridad:** P0

### RF-HOR-005 — Calendario de descanso

Se podrá configurar el día de descanso semanal y descansos obligatorios aplicables.

Los descansos obligatorios se separan en dos conceptos:

- `type`: `legal_mandatory`, `electoral` o `company_internal`.
- `scope`: `national`, `subnational` o `company`.

Combinaciones permitidas:

- `legal_mandatory`: `national` o `subnational`.
- `electoral`: `national` o `subnational`.
- `company_internal`: únicamente `company`.

Normalización requerida:

- `national`: requiere `country_code`, sin `company_id` y sin `jurisdiction_code`.
- `subnational`: requiere `country_code` y `jurisdiction_code` normalizado, sin `company_id`.
- `company`: con `company_id` y sin `jurisdiction_code`.

Durante el MVP el país operativo queda fijo en México (`country_code = MX`). No se implementan calendarios de otros países, reglas laborales extranjeras ni selector internacional de país.

Los registros nacionales, subnacionales o electorales globales solo podrán administrarse por `super_admin`. Los usuarios de empresa solo podrán administrar descansos `company_internal` de su empresa.

**Prioridad:** P0

---

### RF-HOR-006 — Programacion diaria publicada

La programacion diaria publicada sera la unica fuente de verdad operativa para registro, calculo, alertas, cierres y reportes.

Los perfiles de horario no tendran efecto operativo directo. Solo generaran borradores de programacion diaria que deberan revisarse y publicarse.

Cada dia publicado debera conservar snapshot JSON canonico, version consecutiva por centro y periodo, `published_by`, `published_at` y hash SHA-256.

La publicacion sera inmutable. Una correccion generara una nueva version y la version anterior quedara `superseded`.

`daily_schedule_assignments` publicados y `daily_schedule_segments` seran la unica fuente operativa.

En Bloque F1 se implementa el nucleo de datos y dominio: batches por empresa/centro/periodo/version, asignaciones diarias, segmentos diarios, snapshot canonico con SHA-256 y resolucion de programacion publicada. En Bloque F2 se implementa la generacion de borradores desde perfiles con modos `missing_only` y `refresh_profile_generated`. En Bloque F3A se implementa la validacion integral y publicacion atomica de batches completos desde dominio. En Bloque F3B se implementa la interfaz `/scheduling/daily` para crear lotes, generar desde perfiles, editar borradores, validar, publicar y verificar integridad de publicaciones.

La generacion F2 debe preservar dias manuales, CSV, API o system ajenos al generador; debe congelar unidad principal y timezone por fecha; `calendar` y ausencia de perfil generan `unassigned` con motivo explicito. No publica, no persiste snapshot de publicacion y no calcula jornada.

La publicacion F3A/F3B debe bloquear batches incompletos, dias `unassigned`, conflictos con programacion ya publicada por relacion laboral/fecha, versiones correctivas y cualquier configuracion incompatible por tipo de dia. Al publicar debe persistir `snapshot_schema_version`, `snapshot_canonical_json`, `snapshot_sha256`, `published_by` y `published_at`; la verificacion de integridad debe validar el JSON y hash persistidos sin reconstruir desde catalogos actuales. F3B no implementa correcciones versionadas, CSV/XLSX, API WFM, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

**Prioridad:** P0

### RF-HOR-007 — Perfiles WFM

Vera Time debera soportar perfiles de horario:

- `pattern`: perfiles por patron.
  - `pattern_mode = weekly`: reglas semanales.
  - `pattern_mode = cycle`: ciclos rotativos futuros.
- `calendar`: captura manual, CSV/XLSX o API.
- `flexible`: minutos requeridos y ventanas.
- `on_call`: disponibilidad bajo demanda futura.

El perfil `flexible` no debera mezclarse con una plantilla de turno rigida.

En D1/D2 esta operativo `pattern` con `pattern_mode = weekly` y `calendar`. En E1/E2 queda operativo el dominio y la interfaz de `pattern` con `pattern_mode = cycle`, `flexible` y `on_call`: reglas, validacion y resolucion por fecha. En F2 esos perfiles pueden generar borradores diarios. No incluye activaciones bajo demanda, publicacion, alertas ni calculos.

**Prioridad:** P0

### RF-HOR-008 — Estructura organizacional

La empresa podra operar solo con centros o agregar unidades organizacionales opcionales por centro con jerarquia visible inicial `department` -> `area` -> `team`.

Un trabajador podra tener una unidad principal vigente y apoyos temporales opcionales.

Los responsables y supervisores solo podran operar trabajadores dentro de sus centros completos o unidades asignadas. Nunca obtendran alcance automatico solo por poseer el rol.

**Prioridad:** P0

## 6.5 Registro electrónico

### RF-REG-001 — Registro de entrada

La persona trabajadora podrá registrar el inicio de su jornada.

**Prioridad:** P0

### RF-REG-002 — Registro de salida

La persona trabajadora podrá registrar el final de su jornada.

**Prioridad:** P0

### RF-REG-003 — Registro de pausas

El sistema permitirá registrar inicio y fin de pausas cuando la política lo requiera.

**Prioridad:** P0

### RF-REG-004 — Múltiples fuentes de captura

El sistema podrá recibir eventos mediante:

- Web responsiva/PWA.
- Kiosco.
- Captura administrativa justificada.
- CSV.
- API.

**Prioridad:** P0

### RF-REG-005 — Datos mínimos del evento

Cada evento deberá conservar:

- Identificador único.
- Empresa.
- Persona.
- Tipo.
- Fecha y hora del hecho.
- Zona horaria.
- Fecha y hora de recepción.
- Fuente.
- Usuario, dispositivo o integración.
- Estado.

**Prioridad:** P0

### RF-REG-006 — Idempotencia

La API y las importaciones deberán evitar duplicar eventos cuando se reintente una operación.

**Prioridad:** P0

### RF-REG-007 — Registro tardío

El sistema distinguirá entre la hora del hecho y la hora de recepción.

**Prioridad:** P0

### RF-REG-008 — Eventos fuera de orden

El sistema permitirá recibir eventos fuera de orden y marcará la jornada para recalculo o revisión.

**Prioridad:** P0

### RF-REG-009 — Captura manual justificada

Una captura manual deberá registrar:

- Motivo.
- Autor.
- Fecha.
- Evidencia opcional.
- Aprobación cuando aplique.

**Prioridad:** P0

### RF-REG-010 — No eliminación destructiva

Un evento utilizado no podrá eliminarse mediante una operación ordinaria.

**Prioridad:** P0

---

## 6.6 Motor legal

### RF-CAL-001 — Reconstrucción de jornada

El sistema deberá reconstruir la jornada a partir de eventos válidos.

**Prioridad:** P0

### RF-CAL-002 — Clasificación por tipo

El sistema calculará minutos diurnos y nocturnos y clasificará la jornada como:

- Diurna.
- Nocturna.
- Mixta.
- Pendiente.

**Prioridad:** P0

### RF-CAL-003 — Límites diarios

El sistema validará el máximo diario aplicable.

**Prioridad:** P0

### RF-CAL-004 — Límite semanal por vigencia

El sistema aplicará el máximo semanal vigente en la fecha trabajada.

**Prioridad:** P0

### RF-CAL-005 — Horas extraordinarias

El sistema separará:

- Tiempo ordinario.
- Emergencia.
- Extraordinario dentro del artículo 66.
- Excedente del artículo 68.
- Tiempo superior al máximo diario.

**Prioridad:** P0

### RF-CAL-006 — Descansos

El motor determinará si una pausa es computable y detectará descansos insuficientes.

**Prioridad:** P0

### RF-CAL-007 — Domingo y descansos obligatorios

El sistema identificará tiempo trabajado:

- En domingo.
- En descanso semanal.
- En descanso obligatorio.

**Prioridad:** P0

### RF-CAL-008 — Regla más favorable

El motor podrá aplicar condiciones contractuales más favorables que el máximo legal.

**Prioridad:** P0

### RF-CAL-009 — Reglas versionadas

Los parámetros normativos tendrán:

- Fuente.
- Versión.
- Inicio de vigencia.
- Fin de vigencia.
- Estado.

**Prioridad:** P0

### RF-CAL-010 — Explicación del cálculo

Cada resultado deberá mostrar:

- Eventos considerados.
- Regla aplicada.
- Minutos por categoría.
- Alertas generadas.
- Versión del cálculo.

**Prioridad:** P0

---

## 6.7 Alertas preventivas

### RF-ALT-001 — Generación automática

El sistema deberá generar alertas cuando detecte posibles desviaciones.

**Prioridad:** P0

### RF-ALT-002 — Catálogo mínimo de alertas

Como mínimo:

- Entrada faltante.
- Salida faltante.
- Evento duplicado.
- Jornada incompleta.
- Jornada diaria excedida.
- Jornada semanal excedida.
- Horas extraordinarias.
- Más de doce horas en un día.
- Descanso insuficiente.
- Más de seis días consecutivos.
- Trabajo en domingo.
- Trabajo en descanso obligatorio.
- Horario o relación no vigente.
- Corrección pendiente.
- Diferencia entre tiempo calculado, autorizado y exportado.
- Reporte de periodo con diferencias.

**Prioridad:** P0

### RF-ALT-003 — Niveles de prioridad

Las alertas tendrán niveles:

- Informativa.
- Advertencia.
- Alta.
- Crítica.

**Prioridad:** P0

### RF-ALT-004 — Estados

Las alertas tendrán los siguientes estados:

- Nueva.
- En revisión.
- Pendiente de información.
- Justificada.
- Corregida.
- Cerrada.

**Prioridad:** P0

### RF-ALT-005 — Lenguaje neutral

La interfaz deberá utilizar expresiones como:

- Posible incumplimiento.
- Situación pendiente de revisión.
- Requiere validación.
- Tiempo superior al programado.

No deberá presentar automáticamente una infracción confirmada.

**Prioridad:** P0

### RF-ALT-006 — Responsable y vencimiento

Una alerta podrá asignarse a un responsable y tener una fecha objetivo.

**Prioridad:** P0

### RF-ALT-007 — Resolución trazable

La resolución deberá conservar:

- Responsable.
- Fecha.
- Comentario.
- Evidencia.
- Acción aplicada.
- Estado final.

**Prioridad:** P0

### RF-ALT-008 — Bloqueo de cierre

Una alerta crítica pendiente podrá bloquear el cierre definitivo del periodo.

**Prioridad:** P0

### RF-ALT-009 — No alteración de eventos

Resolver una alerta no deberá modificar eventos sin utilizar el flujo de corrección.

**Prioridad:** P0

---

## 6.8 Incidencias y correcciones

### RF-INC-001 — Creación de incidencia

El sistema permitirá crear incidencias manuales o automáticas.

**Prioridad:** P0

### RF-INC-002 — Tipos mínimos

- Registro faltante.
- Registro incorrecto.
- Descanso.
- Horario.
- Turno.
- Horas extraordinarias.
- Trabajo en domingo.
- Descanso obligatorio.
- Diferencia de cálculo.
- Problema técnico.
- Solicitud de la persona trabajadora.

**Prioridad:** P0

### RF-INC-003 — Corrección propuesta

Toda corrección deberá mostrar:

- Valor original.
- Valor propuesto.
- Motivo.
- Evidencia.
- Solicitante.
- Fecha.

**Prioridad:** P0

### RF-INC-004 — Flujo de aprobación

La corrección podrá requerir aprobación del supervisor o recursos humanos.

**Prioridad:** P0

### RF-INC-005 — Historial

La corrección no sobrescribirá el valor original.

**Prioridad:** P0

### RF-INC-006 — Recalculo

Una corrección aprobada generará una nueva versión del cálculo.

**Prioridad:** P0

### RF-INC-007 — Controversia

Si no existe acuerdo, el sistema conservará:

- Registro original.
- Solicitud.
- Respuesta.
- Evidencias.
- Estado de controversia.

**Prioridad:** P0

---

## 6.9 Portal de la persona trabajadora

### RF-PORT-001 — Consulta de jornadas

La persona podrá consultar sus jornadas por día, semana y periodo.

**Prioridad:** P0

### RF-PORT-002 — Detalle explicable

La persona podrá consultar:

- Eventos.
- Horario.
- Pausas.
- Horas ordinarias.
- Horas extraordinarias.
- Alertas visibles.
- Correcciones.

**Prioridad:** P0

### RF-PORT-003 — Solicitud de aclaración

La persona podrá solicitar una aclaración desde una jornada o reporte.

**Prioridad:** P0

### RF-PORT-004 — Evidencia adjunta

La persona podrá adjuntar evidencia a su solicitud.

**Prioridad:** P0

### RF-PORT-005 — Seguimiento

La persona podrá consultar el estado y resolución de sus solicitudes.

**Prioridad:** P0

### RF-PORT-006 — Acceso a políticas

La persona podrá consultar políticas y mecanismos de registro vigentes que le apliquen.

**Prioridad:** P1

---

## 6.10 Cierre y conformidad digital

### RF-CIE-000 — Perfiles multiples de cierre

Toda empresa debera tener un perfil de cierre predeterminado. Podran existir excepciones por centro, unidad organizacional o relacion laboral.

La prioridad del perfil efectivo sera:

1. Relacion laboral.
2. Unidad organizacional.
3. Centro.
4. Empresa.

Frecuencias soportadas:

- `weekly`
- `fourteen_day`
- `semimonthly`
- `monthly`
- `custom`

Quincenal y catorcenal son frecuencias distintas.

Los cierres publicados deberan congelar trabajadores, configuracion, versiones y origen del perfil efectivo.

**Prioridad:** P0

### RF-CON-001 — Periodos de cierre

La empresa podrá configurar cierres:

- Semanales.
- Quincenales.
- Mensuales.
- Por periodo de nómina.

**Prioridad:** P0

### RF-CON-002 — Generación de reporte individual

Al cierre se generará un reporte individual con:

- Eventos.
- Horarios.
- Pausas.
- Tiempo ordinario.
- Tiempo extraordinario.
- Domingo y descansos.
- Incidencias.
- Correcciones.
- Alertas.
- Totales.

**Prioridad:** P0

### RF-CON-003 — Estados del periodo

El periodo podrá estar en:

- En cálculo.
- Con alertas.
- En revisión administrativa.
- Disponible para revisión.
- Conforme.
- No conforme.
- En aclaración.
- Cerrado.

**Prioridad:** P0

### RF-CON-004 — Opciones de la persona trabajadora

La persona podrá seleccionar:

- Conforme.
- No conforme / solicitar aclaración.
- Pendiente de revisión.

**Prioridad:** P0

### RF-CON-005 — Confirmación expresa

La conformidad requerirá una acción expresa de la persona autenticada.

**Prioridad:** P0

### RF-CON-006 — Evidencia de confirmación

Se conservará:

- Identidad.
- Periodo.
- Versión.
- Fecha y hora.
- Zona horaria.
- Resultado.
- Texto aceptado.
- Método de autenticación.
- Hash del reporte.
- IP y dispositivo como datos auxiliares.

**Prioridad:** P0

### RF-CON-007 — Texto sin renuncia de derechos

El texto de conformidad deberá aclarar que no implica renuncia a salarios, prestaciones ni derechos laborales.

**Prioridad:** P0

### RF-CON-008 — No conformidad

Una no conformidad deberá generar una incidencia vinculada al reporte.

**Prioridad:** P0

### RF-CON-009 — Nueva versión

Si el reporte cambia después de una corrección:

1. Se conserva la versión anterior.
2. Se genera una nueva versión.
3. Se invalidará únicamente el cierre de la nueva versión.
4. Se solicitará una nueva revisión.

**Prioridad:** P0

### RF-CON-010 — Sin aceptación automática

La falta de respuesta nunca se considerará conformidad.

**Prioridad:** P0

### RF-CON-011 — Alerta crítica

Un reporte con alertas críticas pendientes no podrá cerrarse definitivamente.

**Prioridad:** P0

### RF-CON-012 — Recordatorios

El sistema podrá enviar recordatorios de revisión.

**Prioridad:** P1

### RF-CON-013 — Método de autenticación

Para el MVP se admitirá:

- Sesión individual.
- Confirmación expresa.
- NIP o código por correo.
- Hash.
- Bitácora.

**Prioridad:** P0

---

## 6.11 Reportes y expedientes

### RF-REP-001 — Reporte diario

El sistema generará reportes diarios por persona, centro y empresa.

**Prioridad:** P0

### RF-REP-002 — Reporte semanal o de periodo

El sistema generará acumulados por periodo.

**Prioridad:** P0

### RF-REP-003 — Reporte de horas extraordinarias

El sistema mostrará bandas, acumulados y alertas.

**Prioridad:** P0

### RF-REP-004 — Reporte de incidencias

El sistema mostrará incidencias por tipo, responsable y estado.

**Prioridad:** P0

### RF-REP-005 — Reporte de alertas

El sistema mostrará:

- Tipo.
- Nivel.
- Estado.
- Persona.
- Centro.
- Periodo.
- Responsable.

**Prioridad:** P0

### RF-REP-006 — Expediente delimitado

El sistema generará expedientes por:

- Empresa.
- Centro.
- Persona.
- Periodo.
- Solicitud.

**Prioridad:** P0

### RF-REP-007 — Manifiesto de integridad

El expediente deberá incluir:

- Fecha de generación.
- Alcance.
- Usuario.
- Versión.
- Hash o manifiesto.
- Archivos incluidos.

**Prioridad:** P0

### RF-REP-008 — Formatos

El MVP deberá exportar al menos:

- PDF legible.
- CSV o XLSX estructurado.
- Paquete ZIP cuando existan varios archivos.

**Prioridad:** P0

### RF-REP-009 — Exportación para prenómina

El sistema podrá exportar horas y conceptos, sin calcular nómina integral.

**Prioridad:** P1

---

## 6.12 Importaciones e integraciones

### RF-INT-001 — Importación de personas

Se podrán importar personas mediante plantilla CSV.

**Prioridad:** P0

### RF-INT-002 — Importación de horarios

Se podrán importar asignaciones de horario.

**Prioridad:** P0

### RF-INT-003 — Importación de eventos

Se podrán importar eventos con validación y resultado por fila.

**Prioridad:** P0

### RF-INT-004 — API oficial del MVP

El MVP deberá contar con una API oficial versionada para interoperabilidad.

Como mínimo deberá permitir:

- Crear o actualizar trabajadores.
- Consultar trabajadores.
- Crear eventos de jornada.
- Consultar eventos.
- Consultar jornadas calculadas.
- Consultar alertas.
- Crear incidencias.
- Consultar reportes de periodo.

**Prioridad:** P0

### RF-INT-005 — Credenciales por empresa

Cada integración utilizará credenciales limitadas a una empresa.

**Prioridad:** P0

### RF-INT-006 — Registro técnico

Toda operación de integración conservará:

- Origen.
- Fecha.
- Resultado.
- Errores.
- Identificador externo.

**Prioridad:** P0

---

## 6.13 Suscripción y límites

### RF-PLAN-001 — Plan por empresa

Cada empresa estará asociada a un plan.

**Prioridad:** P0

### RF-PLAN-002 — Persona activa

El sistema deberá identificar personas activas para medición y cobro.

**Prioridad:** P0

### RF-PLAN-003 — Límites

El sistema podrá limitar:

- Personas activas.
- Centros.
- Administradores.
- Almacenamiento.
- Integraciones.
- Retención.
- Funciones.

**Prioridad:** P0

### RF-PLAN-004 — Sin pérdida de datos

Al superar un límite, el sistema no eliminará información.

**Prioridad:** P0

### RF-PLAN-005 — Suspensión controlada

Una suspensión comercial deberá mantener la información bajo la política aplicable y limitar nuevas operaciones.

**Prioridad:** P1

---

## 6.14 Administración global

### RF-ADM-001 — Parámetros legales globales

Solo usuarios autorizados podrán administrar reglas legales globales.

**Prioridad:** P0

### RF-ADM-002 — Publicación de versión

Una nueva regla deberá pasar por estados:

- Borrador.
- Revisada.
- Programada.
- Vigente.
- Sustituida.

**Prioridad:** P0

### RF-ADM-003 — Historial normativo

El sistema conservará todas las versiones.

**Prioridad:** P0

### RF-ADM-004 — Soporte auditado

Todo acceso de soporte a una empresa deberá:

- Estar autorizado.
- Tener motivo.
- Tener duración.
- Quedar auditado.

**Prioridad:** P0

---

## 6.15 API e interoperabilidad bidireccional

### RF-API-001 — Operaciones críticas por API

Las funcionalidades críticas del MVP deberán poder ejecutarse por API cuando formen parte de la operación P0.

**Prioridad:** P0

### RF-API-002 — Bidireccionalidad interfaz/API

Lo creado por API deberá verse en la interfaz cuando el usuario tenga permiso. Lo creado por la interfaz deberá poder consultarse por API cuando aplique al alcance de integración.

**Prioridad:** P0

### RF-API-003 — Lógica compartida

API, CSV, jobs e integraciones deberán usar las mismas acciones, servicios de aplicación y servicios de dominio que la interfaz.

La API no deberá duplicar reglas ni saltarse validaciones del sistema.

**Prioridad:** P0

### RF-API-004 — Fuente del dato

Cada registro creado o modificado deberá conservar su fuente.

Ejemplos:

- `web`
- `pwa`
- `kiosco`
- `api`
- `csv`
- `admin_manual`
- `job`
- `integration_clickbalance`

**Prioridad:** P0

### RF-API-005 — Seguridad de API

La API deberá tener:

- Autenticación.
- Permisos.
- Alcance por empresa.
- Versionamiento.
- Idempotencia cuando cree datos sensibles.
- Auditoría.
- Errores estandarizados.

**Prioridad:** P0

### RF-API-006 — Capacidades API P0

La API del MVP deberá cubrir:

| Capacidad | Prioridad |
|---|---|
| Crear/actualizar trabajadores | P0 |
| Consultar trabajadores | P0 |
| Crear eventos de jornada | P0 |
| Consultar eventos | P0 |
| Consultar jornadas calculadas | P0 |
| Consultar alertas | P0 |
| Crear incidencias | P0 |
| Consultar reportes de periodo | P0 |

**Prioridad:** P0

### RF-API-007 — Capacidades API P1

Las siguientes capacidades podrán implementarse si no comprometen la fecha del MVP:

| Capacidad | Prioridad |
|---|---|
| Crear horarios | P1 |
| Asignar horarios | P1 |
| Resolver alertas | P1 |
| Aprobar correcciones | P1 |
| Generar expedientes | P1 |
| Confirmar conformidad vía API | P1 |
| Integración directa ClickBalance | P1 |

**Prioridad:** P1

---

# 7. Requisitos no funcionales

## RNF-001 — Seguridad

La plataforma deberá aplicar:

- HTTPS.
- Contraseñas seguras.
- Protección CSRF.
- Prevención de acceso horizontal.
- Rate limiting.
- Gestión segura de secretos.
- Principio de mínimo privilegio.

**Prioridad:** P0

## RNF-002 — Aislamiento multi-tenant

Toda capa de acceso a datos deberá respetar el tenant.

**Prioridad:** P0

## RNF-003 — Integridad

Los eventos, cálculos cerrados, reportes firmados y expedientes deberán ser verificables.

**Prioridad:** P0

## RNF-004 — Trazabilidad

Toda operación sensible deberá registrar:

- Actor.
- Fecha.
- Acción.
- Entidad.
- Valor anterior.
- Valor nuevo.
- Motivo.

**Prioridad:** P0

## RNF-005 — Disponibilidad

Objetivo inicial del MVP:

```text
99.5 % mensual
```

Excluyendo mantenimientos programados.

**Prioridad:** P0

## RNF-006 — Recuperación

El sistema deberá contar con:

- Respaldos automáticos.
- Prueba periódica de restauración.
- RPO y RTO definidos antes del piloto.

**Prioridad:** P0

## RNF-007 — Rendimiento

Objetivos iniciales:

- Pantallas comunes: menos de 2 segundos en condiciones normales.
- Registro de evento: confirmación menor de 3 segundos.
- Reportes pesados: procesamiento asíncrono cuando sea necesario.

**Prioridad:** P0

## RNF-008 — Escalabilidad

El registro de eventos deberá soportar crecimiento sin rediseñar el dominio principal.

**Prioridad:** P0

## RNF-009 — Accesibilidad

Las pantallas principales deberán cumplir criterios básicos de accesibilidad:

- Navegación por teclado.
- Etiquetas.
- Contraste.
- Mensajes comprensibles.
- Diseño responsivo.

**Prioridad:** P0

## RNF-010 — Privacidad

El sistema aplicará:

- Minimización.
- Finalidad.
- Acceso restringido.
- Aviso de privacidad.
- Retención.
- Procedimientos ARCO.

**Prioridad:** P0

## RNF-011 — Observabilidad

La plataforma deberá contar con:

- Logs.
- Monitoreo.
- Alertas técnicas.
- Seguimiento de errores.
- Métricas de uso.

**Prioridad:** P0

## RNF-012 — Mantenibilidad

Las reglas legales deberán estar separadas de la interfaz y versionadas.

**Prioridad:** P0

## RNF-013 — Pruebas

El proyecto deberá tener pruebas automatizadas para:

- Cálculos.
- Multi-tenant.
- Permisos.
- Correcciones.
- Alertas.
- Conformidad digital.
- Integridad de reportes.

**Prioridad:** P0

## RNF-014 — Portabilidad

La empresa podrá obtener una exportación de su información.

**Prioridad:** P0

## RNF-015 — Neutralidad jurídica

La plataforma deberá evitar presentar alertas como sentencias definitivas.

**Prioridad:** P0

---

# 8. Flujos principales

## FLUJO-001 — Alta y configuración inicial

```text
Crear empresa
→ Crear centros
→ Crear usuarios
→ Importar personas
→ Crear horarios y turnos
→ Asignar condiciones
→ Activar registro
```

## FLUJO-002 — Registro y cálculo

```text
Registrar entrada
→ Registrar pausas
→ Registrar salida
→ Reconstruir jornada
→ Aplicar reglas
→ Generar resultados
→ Generar alertas
```

## FLUJO-003 — Incidencia y corrección

```text
Detectar diferencia
→ Crear incidencia
→ Revisar datos
→ Proponer corrección
→ Aprobar o rechazar
→ Recalcular
→ Conservar versión previa
```

## FLUJO-004 — Cierre y conformidad

```text
Finalizar periodo
→ Calcular jornadas
→ Revisar alertas
→ Resolver críticas
→ Generar reporte individual
→ Enviar a revisión
→ Conforme / No conforme / Pendiente
→ Cerrar o abrir aclaración
```

## FLUJO-005 — Expediente

```text
Recibir solicitud
→ Delimitar empresa, personas y periodo
→ Generar información
→ Revisar
→ Cerrar expediente
→ Generar hash
→ Entregar
→ Conservar acuse
```

---

# 9. Criterios globales de aceptación del MVP

El MVP estará listo para piloto cuando:

1. Una empresa pueda configurarse sin intervención directa en base de datos.
2. Puedan importarse personas y horarios.
3. Una persona pueda registrar entrada y salida.
4. El motor pueda reconstruir y clasificar jornadas.
5. El sistema detecte alertas P0.
6. Las incidencias puedan corregirse sin eliminar el historial.
7. La persona trabajadora pueda consultar sus registros.
8. Pueda marcar conforme o no conforme un reporte.
9. Una corrección genere una nueva versión.
10. Un reporte firmado conserve hash y evidencia.
11. Puedan generarse reportes por persona, centro y periodo.
12. Pueda generarse un expediente delimitado.
13. Los datos de una empresa no sean accesibles desde otra.
14. Existan pruebas automatizadas de reglas críticas.
15. Existan respaldos y monitoreo.
16. El piloto pueda operar durante al menos dos semanas sin pérdida de información.

---

# 10. Priorización resumida

## P0 — Obligatorio para producción

- Multi-tenant.
- Empresas y centros.
- Personas y relaciones laborales.
- Horarios y turnos.
- Registro electrónico.
- Motor legal.
- Alertas.
- Incidencias.
- Portal del trabajador.
- Cierre y conformidad.
- Reportes.
- Expedientes.
- CSV.
- API e interoperabilidad bidireccional.
- Seguridad.
- Auditoría.
- Respaldos.
- Monitoreo.

## P1 — Solo si no afecta la fecha

- Recordatorios avanzados.
- Exportación de prenómina específica.
- Teletrabajo básico.
- Políticas visibles en portal.
- Suspensión comercial automatizada.
- Notificaciones configurables.
- Crear horarios por API.
- Asignar horarios por API.
- Resolver alertas por API.
- Aprobar correcciones por API.
- Generar expedientes por API.
- Confirmar conformidad vía API.
- Integración directa ClickBalance.

## Fuera

- App nativa.
- Biometría.
- Hardware.
- Nómina.
- IA.
- Analítica avanzada.
- Internacionalización.

---

# 11. Dependencias

Este documento depende de:

- `docs/01-Legal/LEG-0001-LFT/`
- `docs/02-Negocio/NEG-0001-MODELO-DE-NEGOCIO.md`
- `docs/11-Roadmap/RM-0001-ROADMAP-PRODUCTO.md`
- `docs/11-Roadmap/RM-0002-ALCANCE-Y-PRESUPUESTO-MVP.md`
- `docs/00-Gobierno/DOC-0003-PROPUESTA-SOCIO-INVERSIONISTA.md`

---

# 12. Siguiente paso

Después de aprobar este documento, se deberá continuar con:

```text
docs/04-Arquitectura/
```

El siguiente documento recomendado será:

```text
ARQ-0001-ARQUITECTURA-DEL-MVP.md
```

Ahí se definirán:

- Módulos técnicos.
- Límites del dominio.
- Estrategia multi-tenant.
- Componentes Laravel/Livewire.
- Motor de reglas.
- Eventos y colas.
- Almacenamiento.
- Seguridad.
- Integraciones.
- Despliegue.
