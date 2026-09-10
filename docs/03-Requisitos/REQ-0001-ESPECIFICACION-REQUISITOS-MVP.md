---
id: REQ-0001
title: EspecificaciÃ³n de requisitos del MVP
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

# REQ-0001 â€” EspecificaciÃ³n de requisitos del MVP

## 1. Objetivo

Definir los requisitos funcionales y no funcionales del MVP de Vera Time que deberÃ¡ estar listo para producciÃ³n antes del 1 de enero de 2027.

Este documento convierte en especificaciones de producto:

- La investigaciÃ³n jurÃ­dica aprobada.
- El modelo de negocio.
- El alcance del MVP.
- El presupuesto.
- El roadmap acelerado.
- Las decisiones sobre alertas preventivas.
- La revisiÃ³n y conformidad digital de la jornada.

No define todavÃ­a tablas fÃ­sicas, endpoints, componentes de interfaz ni arquitectura detallada.

---

## 2. Alcance del MVP

El MVP de Vera Time deberÃ¡ concentrarse en entregar una plataforma operativa, vendible y legalmente Ãºtil antes del 1 de enero de 2027.

El alcance se divide en capacidades indispensables. Cada capacidad debe aportar valor directo al cumplimiento, a la operaciÃ³n diaria o a la evidencia documental.

### 2.0 Regla de evidencia operativa

Vera Time protege el resultado operativo final, no cada paso intermedio usado para construirlo.

Se considera evidencia protegida:

- horarios diarios publicados, snapshots y hashes;
- correcciones versionadas de horarios publicados;
- eventos de asistencia y sus anulaciones logicas;
- futuros `work_days`, calculos, alertas, incidencias, cierres, conformidad, reportes y expedientes.

Se consideran datos intermedios los catalogos, relaciones laborales, asignaciones organizacionales, plantillas, perfiles y asignaciones de perfiles mientras no hayan generado evidencia protegida.

Requisitos derivados:

- un cambio en catalogos, relaciones, areas o perfiles no debe modificar horarios ya publicados;
- para cambiar una fecha publicada se debe usar correccion versionada de programacion diaria;
- los datos intermedios capturados por error deben poder corregirse o eliminarse si no tienen uso en evidencia protegida;
- si ya existe evidencia protegida, la correccion debe aplicar hacia adelante o guiar al usuario hacia la correccion del resultado;
- `work_days` debe generarse desde horarios publicados aunque no existan eventos;
- eventos validos sin horario publicado deben identificarse como jornada no programada.

### 2.0.1 Bloque B - relaciones laborales

La administracion de trabajadores debe permitir corregir centro, puesto y fecha de ingreso de la relacion laboral cuando todavia no existan horarios publicados ni eventos de asistencia asociados a esa relacion.

La correccion debe:

- conservar el mismo registro de relacion laboral;
- exigir motivo;
- registrar actor, fecha, valores anteriores y valores nuevos en metadata;
- bloquear sobrescritura si la relacion ya tiene evidencia protegida;
- permitir una nueva vigencia hacia adelante solo si no corta horarios publicados ni asistencias existentes.

No se debe modificar el horario publicado desde la pantalla de trabajadores.

### 2.1 Plataforma SaaS multi-tenant

**Incluye:**

- AdministraciÃ³n de mÃºltiples empresas dentro de una sola plataforma.
- SeparaciÃ³n estricta de datos por empresa.
- Usuarios con acceso a una o varias empresas.
- Cambio de empresa activa cuando el usuario tenga permiso.
- Roles y permisos por empresa.
- RestricciÃ³n de acceso por centro, Ã¡rea o grupo cuando aplique.
- Plan o suscripciÃ³n asignado por empresa.
- Estado de empresa: activa, suspendida, cancelada o en piloto.

**No incluye en el MVP:**

- Ambientes dedicados por cliente.
- Marca blanca.
- Subdominios personalizados.
- FacturaciÃ³n automatizada avanzada.
- Marketplace de integraciones.

**Valor:** permite operar Vera Time como SaaS real, atendiendo varias empresas sin crear una instalaciÃ³n separada para cada cliente.

**Criterio de validaciÃ³n:** una empresa no debe poder ver, modificar ni exportar informaciÃ³n de otra empresa bajo ninguna condiciÃ³n.

### 2.2 Empresas, centros y estructura bÃ¡sica

**Incluye:**

- Registro de empresa.
- RazÃ³n social.
- Nombre comercial.
- RFC.
- Zona horaria principal.
- Centros de trabajo.
- Zona horaria por centro.
- Estado del centro.
- Datos bÃ¡sicos de contacto.
- ConfiguraciÃ³n inicial del periodo de nÃ³mina o cierre.
- ConfiguraciÃ³n de dÃ­as laborales generales.
- ConfiguraciÃ³n de descansos obligatorios aplicables.

**No incluye en el MVP:**

- Estructura organizacional compleja.
- Organigramas.
- Presupuestos por Ã¡rea.
- AdministraciÃ³n avanzada de sucursales.
- MÃºltiples paÃ­ses.

**Valor:** permite ubicar correctamente a cada persona trabajadora, aplicar zona horaria, generar reportes por centro y delimitar evidencia.

**Criterio de validaciÃ³n:** debe poder configurarse una empresa con al menos dos centros y generar reportes separados por cada uno.

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
- RelaciÃ³n laboral vigente.
- Historial de cambios relevantes.
- Fecha de baja sin eliminaciÃ³n de registros.
- Condiciones laborales con vigencia.
- Modalidad: presencial, hÃ­brida, teletrabajo bÃ¡sico o campo.
- DÃ­a de descanso asignado.
- Horario o turno aplicable.
- PolÃ­tica de registro aplicable.

**No incluye en el MVP:**

- Expediente laboral completo.
- Documentos personales avanzados.
- Contratos generados automÃ¡ticamente.
- Incapacidades.
- Vacaciones.
- Evaluaciones de desempeÃ±o.
- Reclutamiento.

**Valor:** es la base para calcular jornada, generar evidencia individual y cobrar por persona activa.

**Criterio de validaciÃ³n:** una persona debe poder cambiar de horario o centro sin que se modifiquen sus jornadas histÃ³ricas.

### 2.4 Horarios, turnos y vigencias

**Incluye:**

- CatÃ¡logo de horarios.
- Tipo legal programado: diurno, nocturno o mixto.
- Hora de entrada programada.
- Hora de salida programada.
- Pausas o descansos programados.
- DÃ­as aplicables.
- Vigencia del horario.
- Turnos fijos.
- Turnos rotativos bÃ¡sicos.
- AsignaciÃ³n de turno por persona.
- AsignaciÃ³n de turno por grupo.
- Cambio de horario con fecha efectiva.
- DÃ­a de descanso semanal.
- Calendario de descansos obligatorios.
- IdentificaciÃ³n de jornadas que cruzan medianoche.

**No incluye en el MVP:**

- PlaneaciÃ³n avanzada de turnos con optimizaciÃ³n automÃ¡tica.
- Bolsa de turnos.
- Intercambio de turnos entre trabajadores.
- Forecast de demanda.
- Inteligencia para asignaciÃ³n automÃ¡tica.
- Calendario laboral complejo por convenio colectivo.

**Valor:** permite comparar lo planeado contra lo registrado y calcular si existe una posible desviaciÃ³n.

**Criterio de validaciÃ³n:** debe poder configurarse un trabajador con turno nocturno que inicia un dÃ­a y termina al siguiente, sin romper el cÃ¡lculo.

### 2.5 Registro electrÃ³nico de jornada

**Incluye:**

- Registro de entrada.
- Registro de salida.
- Registro de inicio de pausa.
- Registro de fin de pausa.
- Registro desde web responsiva o PWA.
- Registro desde kiosco o dispositivo compartido.
- Captura administrativa justificada.
- ImportaciÃ³n de eventos por CSV.
- API oficial para recibir eventos.
- Fecha y hora del hecho.
- Fecha y hora de recepciÃ³n.
- Zona horaria.
- Fuente del evento.
- Usuario, dispositivo o integraciÃ³n de origen.
- Estado del evento.
- PrevenciÃ³n de duplicados.
- Manejo de eventos fuera de orden.
- Registro tardÃ­o.
- BitÃ¡cora del evento.
- No eliminaciÃ³n destructiva.

**No incluye en el MVP:**

- App mÃ³vil nativa.
- Checador biomÃ©trico propio.
- Reconocimiento facial propio.
- Huella digital propia.
- GPS obligatorio.
- Foto obligatoria.
- Registro offline avanzado con app nativa.
- IntegraciÃ³n con todos los relojes checadores del mercado.

**Valor:** cumple el nÃºcleo del registro electrÃ³nico y genera la materia prima para cÃ¡lculos, reportes y evidencia.

**Criterio de validaciÃ³n:** una persona debe poder registrar entrada y salida; el sistema debe reconstruir la jornada y conservar la fuente del registro.

### 2.6 Motor legal de cÃ¡lculo

**Incluye:**

- ReconstrucciÃ³n de jornada a partir de eventos.
- ClasificaciÃ³n diurna, nocturna o mixta.
- CÃ¡lculo de minutos diurnos y nocturnos.
- LÃ­mite diario por tipo de jornada.
- LÃ­mite semanal vigente por aÃ±o.
- CÃ¡lculo de tiempo ordinario.
- CÃ¡lculo de horas extraordinarias.
- SeparaciÃ³n de bandas de horas extra.
- ValidaciÃ³n de mÃ¡ximo diario de doce horas.
- Descanso mÃ­nimo en jornada continua.
- Pausas computables y no computables.
- Trabajo en domingo.
- Trabajo en descanso semanal.
- Trabajo en descanso obligatorio.
- MÃ¡s de seis dÃ­as consecutivos.
- Reglas legales versionadas.
- Condiciones mÃ¡s favorables configurables.
- ExplicaciÃ³n del cÃ¡lculo.
- Recalculo despuÃ©s de correcciÃ³n.

**No incluye en el MVP:**

- CÃ¡lculo completo de nÃ³mina.
- Impuestos.
- Seguridad social.
- Recibos de nÃ³mina.
- CÃ¡lculo monetario definitivo.
- Casos especiales de todos los capÃ­tulos laborales.
- Interpretaciones jurÃ­dicas automÃ¡ticas.

**Valor:** convierte los eventos en informaciÃ³n Ãºtil, explicable y defendible.

**Criterio de validaciÃ³n:** dado un conjunto de eventos, el sistema debe explicar quÃ© regla aplicÃ³, cuÃ¡ntas horas ordinarias calculÃ³, si existieron horas extra y quÃ© alertas generÃ³.

### 2.7 Alertas preventivas de posibles incumplimientos

**Incluye:**

- GeneraciÃ³n automÃ¡tica de alertas.
- Alertas por entrada faltante.
- Alertas por salida faltante.
- Eventos duplicados.
- Jornada incompleta.
- Jornada diaria excedida.
- Jornada semanal excedida.
- Horas extraordinarias.
- Retardo.
- Salida anticipada.
- MÃ¡s de doce horas totales en un dÃ­a.
- Descanso insuficiente.
- MÃ¡s de seis dÃ­as consecutivos trabajados.
- Trabajo en domingo.
- Trabajo en descanso obligatorio.
- Horario no vigente.
- RelaciÃ³n laboral no vigente.
- CorrecciÃ³n pendiente.
- Diferencia entre tiempo calculado y autorizado.
- Diferencia entre tiempo autorizado y exportado.
- Reporte de periodo con diferencias.
- Niveles: informativa, advertencia, alta y crÃ­tica.
- Estados: nueva, en revisiÃ³n, pendiente de informaciÃ³n, justificada, corregida y cerrada.
- Responsable de atenciÃ³n.
- Comentarios.
- Evidencia.
- ResoluciÃ³n trazable.
- Bloqueo de cierre cuando exista alerta crÃ­tica pendiente.

**No incluye en el MVP:**

- PredicciÃ³n con inteligencia artificial.
- Recomendaciones automÃ¡ticas avanzadas.
- EnvÃ­o masivo por WhatsApp.
- Tablero avanzado de riesgos.
- PriorizaciÃ³n automÃ¡tica por impacto econÃ³mico.
- Dictamen jurÃ­dico automÃ¡tico.

**Valor:** permite actuar antes del cierre del periodo y evita que los problemas se descubran hasta la nÃ³mina, auditorÃ­a o inspecciÃ³n.

**Criterio de validaciÃ³n:** si una jornada supera el lÃ­mite configurado, el sistema debe generar una alerta neutral de posible desviaciÃ³n sin modificar el registro real.

### 2.8 Incidencias y correcciones no destructivas

**Incluye:**

- CreaciÃ³n automÃ¡tica de incidencia desde alerta.
- CreaciÃ³n manual de incidencia.
- Solicitud de correcciÃ³n por persona trabajadora.
- Solicitud de correcciÃ³n por supervisor o RH.
- Tipos de incidencia.
- Comentarios.
- Evidencia.
- Valor original.
- Valor propuesto.
- Motivo.
- AprobaciÃ³n o rechazo.
- Recalculo posterior.
- ConservaciÃ³n de versiÃ³n previa.
- Historial completo.
- Estado de controversia cuando no exista acuerdo.
- Cierre de incidencia.

**No incluye en el MVP:**

- Flujos complejos con muchas aprobaciones.
- Firma avanzada de cada incidencia.
- ConciliaciÃ³n laboral formal.
- ComunicaciÃ³n automÃ¡tica con autoridades.
- Chat interno avanzado.

**Valor:** permite corregir errores sin destruir evidencia ni perder confianza.

**Criterio de validaciÃ³n:** una salida faltante debe poder corregirse mediante una solicitud aprobada, conservando el registro original y generando una nueva versiÃ³n del cÃ¡lculo.

### 2.9 Portal de la persona trabajadora

**Incluye:**

- Acceso individual.
- Consulta de jornadas diarias.
- Consulta semanal o por periodo.
- VisualizaciÃ³n de entradas y salidas.
- VisualizaciÃ³n de pausas.
- VisualizaciÃ³n de horas ordinarias.
- VisualizaciÃ³n de posibles horas extra.
- VisualizaciÃ³n de incidencias.
- VisualizaciÃ³n de alertas visibles para el trabajador.
- Solicitud de aclaraciÃ³n.
- Adjuntar evidencia.
- Seguimiento de estado.
- Consulta del reporte de cierre.
- Conformidad o no conformidad.

**No incluye en el MVP:**

- Red social interna.
- Chat completo.
- Documentos laborales completos.
- Vacaciones.
- Recibos de nÃ³mina.
- Beneficios.
- Encuestas.

**Valor:** da transparencia, reduce reclamos tardÃ­os y permite que el trabajador participe en la validaciÃ³n de su informaciÃ³n.

**Criterio de validaciÃ³n:** una persona trabajadora debe poder entrar, ver su semana y solicitar una aclaraciÃ³n sobre un registro especÃ­fico.

### 2.10 Cierre de periodo y conformidad digital

**Incluye:**

- ConfiguraciÃ³n de periodo: semanal, quincenal, mensual o periodo de nÃ³mina.
- Cierre administrativo.
- RevisiÃ³n previa de alertas.
- GeneraciÃ³n de reporte individual.
- Versionamiento del reporte.
- Estados del periodo.
- EnvÃ­o a revisiÃ³n del trabajador.
- OpciÃ³n conforme.
- OpciÃ³n no conforme / solicitar aclaraciÃ³n.
- OpciÃ³n pendiente de revisiÃ³n.
- ConfirmaciÃ³n expresa.
- Texto de aceptaciÃ³n sin renuncia de derechos.
- Identidad de la persona.
- Fecha y hora.
- Zona horaria.
- VersiÃ³n exacta del reporte.
- Hash del reporte.
- MÃ©todo de autenticaciÃ³n.
- IP y dispositivo como datos auxiliares.
- Nueva versiÃ³n si hay correcciÃ³n.
- Nueva revisiÃ³n cuando cambia el reporte.
- Sin aceptaciÃ³n automÃ¡tica por silencio.

**No incluye en el MVP:**

- Firma electrÃ³nica avanzada de proveedor externo.
- e.firma SAT.
- Sellado de tiempo certificado externo.
- Firma biomÃ©trica.
- Reconocimiento facial para firmar.
- NotarizaciÃ³n.
- Blockchain.

**Valor:** fortalece la evidencia laboral, permite detectar inconformidades a tiempo y mejora la defensa documental de empresa y trabajador.

**Criterio de validaciÃ³n:** un reporte firmado no puede modificarse. Si cambia, se conserva la versiÃ³n anterior y se genera una nueva versiÃ³n pendiente de revisiÃ³n.

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
- ExportaciÃ³n PDF.
- ExportaciÃ³n CSV o XLSX.
- Filtros bÃ¡sicos.
- Totales y detalles.

**No incluye en el MVP:**

- BI avanzado.
- Dashboards ejecutivos complejos.
- GrÃ¡ficas predictivas.
- Reportes personalizados ilimitados.
- Conector directo con Power BI.
- Constructor visual de reportes.

**Valor:** permite operar dÃ­a a dÃ­a, revisar riesgos y preparar informaciÃ³n para nÃ³mina o autoridad.

**Criterio de validaciÃ³n:** RH debe poder generar un reporte semanal por centro con jornadas completas, incompletas, horas extra e incidencias.

### 2.12 Expedientes y exportaciones de evidencia

**Incluye:**

- Expediente por persona.
- Expediente por centro.
- Expediente por periodo.
- Expediente por solicitud.
- Eventos fuente.
- CÃ¡lculos.
- Incidencias.
- Correcciones.
- Alertas.
- Reportes firmados.
- Versiones.
- Manifiesto de integridad.
- Hash.
- Fecha de generaciÃ³n.
- Usuario que generÃ³.
- Alcance del expediente.
- ExportaciÃ³n en PDF.
- ExportaciÃ³n estructurada.
- Paquete ZIP cuando aplique.
- Registro de entrega o descarga.

**No incluye en el MVP:**

- Portal especial para autoridad.
- EnvÃ­o automÃ¡tico a STPS.
- IntegraciÃ³n con plataformas oficiales no publicadas.
- CertificaciÃ³n externa de expediente.
- Firma avanzada institucional.

**Valor:** permite responder de forma ordenada y delimitada ante auditorÃ­as, revisiones internas o inspecciones.

**Criterio de validaciÃ³n:** el sistema debe generar un expediente de una persona y un periodo especÃ­fico sin incluir datos de otras personas no solicitadas.

### 2.13 Importaciones CSV e interoperabilidad API

**Incluye:**

- Plantilla CSV para personas.
- Plantilla CSV para horarios.
- Plantilla CSV para eventos.
- ValidaciÃ³n por fila.
- Resultado de importaciÃ³n.
- Errores descargables.
- API oficial para crear o actualizar trabajadores.
- API oficial para crear eventos de jornada.
- API oficial para consultar jornadas calculadas.
- API oficial para consultar reportes de periodo.
- Interoperabilidad bidireccional entre interfaz, API, CSV, jobs e integraciones.
- Credenciales por empresa.
- Idempotencia.
- BitÃ¡cora tÃ©cnica.
- Identificador externo.

**No incluye en el MVP:**

- Marketplace de integraciones.
- Conectores listos para todos los relojes.
- IntegraciÃ³n directa con todas las nÃ³minas.
- Webhooks avanzados.
- SDK pÃºblico.
- SincronizaciÃ³n bidireccional compleja.

**Valor:** permite adoptar el sistema sin capturar todo manualmente y abre la puerta a integraciones futuras.

**Criterio de validaciÃ³n:** una empresa debe poder importar trabajadores y eventos desde CSV, con errores claros cuando una fila no sea vÃ¡lida.

### 2.14 Seguridad, auditorÃ­a, respaldos y monitoreo

**Incluye:**

- AutenticaciÃ³n.
- Roles y permisos.
- ProtecciÃ³n contra acceso cruzado.
- AuditorÃ­a de operaciones sensibles.
- Registro de cambios.
- Registro de accesos relevantes.
- Respaldos automÃ¡ticos.
- RestauraciÃ³n probada.
- Logs de errores.
- Monitoreo bÃ¡sico.
- Alertas tÃ©cnicas.
- ProtecciÃ³n de secretos.
- Cifrado en trÃ¡nsito.
- Control de sesiones.
- Principio de mÃ­nimo privilegio.

**No incluye en el MVP:**

- CertificaciÃ³n ISO.
- SOC 2.
- Pentest formal completo.
- SSO empresarial avanzado.
- MFA obligatorio para todos.
- SIEM dedicado.
- Alta disponibilidad multi-regiÃ³n.

**Valor:** sin seguridad y trazabilidad, el producto no puede venderse como plataforma de evidencia laboral.

**Criterio de validaciÃ³n:** debe existir bitÃ¡cora de quiÃ©n modificÃ³ una jornada, cuÃ¡ndo lo hizo, quÃ© cambiÃ³ y por quÃ©.

### 2.15 Piloto e implementaciÃ³n inicial

**Incluye:**

- SelecciÃ³n de empresas piloto.
- DiagnÃ³stico inicial.
- Carga de datos.
- ConfiguraciÃ³n de horarios.
- CapacitaciÃ³n a administradores.
- CapacitaciÃ³n bÃ¡sica a trabajadores.
- Soporte durante arranque.
- RevisiÃ³n diaria durante el piloto.
- Registro de problemas.
- Ajustes de configuraciÃ³n.
- MediciÃ³n de uso.
- RetroalimentaciÃ³n.
- ValidaciÃ³n de disposiciÃ³n de pago.

**No incluye en el MVP:**

- ImplementaciÃ³n masiva nacional.
- Mesa de ayuda 24/7.
- ConsultorÃ­a laboral profunda para cada cliente.
- MigraciÃ³n histÃ³rica extensa.
- CapacitaciÃ³n presencial ilimitada.
- PersonalizaciÃ³n profunda por cliente.

**Valor:** el piloto valida producto, precio, operaciÃ³n y riesgo antes de escalar comercialmente.

**Criterio de validaciÃ³n:** una empresa piloto debe operar al menos dos semanas con jornadas reales, correcciones, reportes y cierre de periodo.

### 2.16 Capacidades P1 que aportan valor pero no deben retrasar el MVP

Estas capacidades pueden incluirse solo si no comprometen la fecha de producciÃ³n.

| Capacidad | Incluye | No incluye |
|---|---|---|
| Teletrabajo bÃ¡sico | Modalidad de teletrabajo, lugar acordado, polÃ­tica de desconexiÃ³n y documento asociado. | NOM-037 completa, listas avanzadas, gestiÃ³n completa de equipos y evaluaciones de seguridad y salud. |
| ExportaciÃ³n a prenÃ³mina | Horas ordinarias, horas extra, domingos, descanso obligatorio e incidencias. | Salario, ISR, IMSS, timbrado y recibos de nÃ³mina. |
| Notificaciones | Recordatorios de cierre, revisiÃ³n y alertas a responsables. | WhatsApp masivo, SMS masivo y automatizaciones complejas. |

### 2.17 Preguntas de validaciÃ³n del alcance

Antes de cerrar el alcance, se deberÃ¡n responder afirmativamente estas preguntas:

1. Â¿El MVP permite registrar inicio y fin de jornada por persona?
2. Â¿Puede calcular correctamente jornadas diurnas, nocturnas y mixtas?
3. Â¿Puede detectar horas extraordinarias y lÃ­mites excedidos?
4. Â¿Puede detectar descansos insuficientes?
5. Â¿Puede detectar jornadas incompletas?
6. Â¿Puede generar alertas antes del cierre?
7. Â¿Puede corregir sin borrar historial?
8. Â¿Puede generar una nueva versiÃ³n despuÃ©s de una correcciÃ³n?
9. Â¿Puede el trabajador revisar su reporte?
10. Â¿Puede marcar conforme o no conforme?
11. Â¿Puede generarse evidencia del reporte firmado?
12. Â¿Puede exportarse un expediente por periodo?
13. Â¿Puede RH operar sin depender del desarrollador?
14. Â¿Puede una empresa piloto configurarse en menos de una semana?
15. Â¿Puede el sistema cobrar por persona activa?
16. Â¿Puede crecer a varias empresas sin mezclar datos?
17. Â¿Puede actualizar reglas legales sin tocar pantallas?
18. Â¿Puede funcionar sin biometrÃ­a ni app nativa?
19. Â¿Puede venderse como cumplimiento y evidencia, no como simple checador?
20. Â¿Hay algo que, si falta, impide vender o pilotear antes de enero de 2027?

---

## 3. Fuera del MVP

Quedan fuera:

- Aplicaciones mÃ³viles nativas.
- BiometrÃ­a propia.
- Reconocimiento facial.
- Hardware propio.
- NÃ³mina integral.
- Inteligencia artificial.
- AnalÃ­tica avanzada.
- Integraciones mÃºltiples con relojes checadores.
- OperaciÃ³n internacional.
- MÃ³dulo completo de seguridad y salud en teletrabajo.
- Firma electrÃ³nica avanzada de terceros.
- Sellado de tiempo certificado externo, salvo decisiÃ³n posterior.

---

## 4. Actores

| CÃ³digo | Actor | DescripciÃ³n |
|---|---|---|
| ACT-001 | Superadministrador | Administra la plataforma SaaS, planes, empresas y configuraciones globales. |
| ACT-002 | Administrador de empresa | Configura la empresa, centros, usuarios, trabajadores, horarios y polÃ­ticas. |
| ACT-003 | Recursos humanos | Administra relaciones laborales, turnos, incidencias y reportes. |
| ACT-004 | Supervisor | Revisa jornadas, incidencias y alertas de personas bajo su responsabilidad. |
| ACT-005 | NÃ³mina/PrenÃ³mina | Consulta y exporta tiempos y conceptos autorizados. |
| ACT-006 | JurÃ­dico/Cumplimiento | Consulta evidencia, expedientes y trazabilidad. |
| ACT-007 | Persona trabajadora | Registra eventos, consulta jornadas y manifiesta conformidad o inconformidad. |
| ACT-008 | Auditor/Inspector autorizado | Recibe un expediente delimitado y previamente autorizado. |
| ACT-009 | IntegraciÃ³n externa | EnvÃ­a o consulta informaciÃ³n mediante API o importaciÃ³n. |
| ACT-010 | Soporte Vera Time | Atiende incidencias tÃ©cnicas con acceso restringido y auditado. |

---

## 5. MÃ³dulos del MVP

| CÃ³digo | MÃ³dulo |
|---|---|
| MOD-001 | Plataforma multi-tenant |
| MOD-002 | Empresas y centros |
| MOD-003 | Personas y relaciones laborales |
| MOD-004 | Horarios y turnos |
| MOD-005 | Registro electrÃ³nico |
| MOD-006 | Motor legal |
| MOD-007 | Alertas preventivas |
| MOD-008 | Incidencias y correcciones |
| MOD-009 | Portal de la persona trabajadora |
| MOD-010 | Cierre y conformidad digital |
| MOD-011 | Reportes y expedientes |
| MOD-012 | Importaciones e integraciones |
| MOD-013 | Seguridad y auditorÃ­a |
| MOD-014 | SuscripciÃ³n y lÃ­mites del plan |
| MOD-015 | AdministraciÃ³n global |

---

# 6. Requisitos funcionales

## 6.1 Plataforma multi-tenant

### RF-MT-001 â€” Aislamiento por empresa

El sistema deberÃ¡ impedir que una empresa consulte, modifique o exporte datos de otra empresa.

**Prioridad:** P0

**Criterios de aceptaciÃ³n:**

- Toda consulta operativa aplica el contexto de empresa.
- Un usuario sin acceso a una empresa recibe denegaciÃ³n.
- Una URL o identificador manipulado no permite acceso cruzado.
- Las exportaciones solo contienen informaciÃ³n del tenant activo.

### RF-MT-002 â€” Usuario con acceso a varias empresas

Un usuario autorizado podrÃ¡ pertenecer a una o varias empresas y cambiar entre ellas.

**Prioridad:** P0

### RF-MT-003 â€” Roles y permisos

El sistema deberÃ¡ manejar permisos por rol y alcance.

**Prioridad:** P0

Como mÃ­nimo:

- Superadministrador.
- Administrador de empresa.
- Recursos humanos.
- Supervisor.
- NÃ³mina.
- JurÃ­dico.
- Persona trabajadora.
- Solo lectura.

### RF-MT-004 â€” Alcance por centro o equipo

Los permisos podrÃ¡n limitarse por centro, Ã¡rea o grupo de personas.

**Prioridad:** P0

---

## 6.2 Empresas y centros

### RF-EMP-001 â€” Alta de empresa

El superadministrador podrÃ¡ registrar una empresa cliente.

**Datos mÃ­nimos:**

- RazÃ³n social.
- Nombre comercial.
- RFC.
- Zona horaria principal.
- Estado.
- Plan.
- Fecha de activaciÃ³n.

**Prioridad:** P0

### RF-EMP-002 â€” Centros de trabajo

La empresa podrÃ¡ registrar uno o varios centros de trabajo.

**Prioridad:** P0

### RF-EMP-003 â€” Zona horaria por centro

Cada centro podrÃ¡ tener una zona horaria especÃ­fica.

**Prioridad:** P0

### RF-EMP-004 â€” ConfiguraciÃ³n histÃ³rica

Los cambios relevantes de empresa o centro tendrÃ¡n vigencia y no alterarÃ¡n registros cerrados.

**Prioridad:** P0

---

## 6.3 Personas trabajadoras y relaciones laborales

### RF-PER-001 â€” Alta de persona trabajadora

La empresa podrÃ¡ registrar personas trabajadoras manualmente, por CSV o API.

**Prioridad:** P0

### RF-PER-002 â€” RelaciÃ³n laboral

Cada persona deberÃ¡ tener al menos una relaciÃ³n laboral con:

- Empresa.
- Centro.
- NÃºmero o clave interna.
- Fecha de ingreso.
- Puesto.
- Estado.
- Fecha de baja, cuando corresponda.

**Prioridad:** P0

### RF-PER-003 â€” Condiciones laborales con vigencia

El sistema deberÃ¡ conservar histÃ³ricamente:

- Tipo de jornada.
- Jornada semanal pactada.
- Horario o turno.
- DÃ­a de descanso.
- Modalidad.
- PolÃ­tica aplicable.
- Fecha de vigencia.

**Prioridad:** P0

### RF-PER-004 â€” Baja sin eliminaciÃ³n

Una baja laboral no eliminarÃ¡ jornadas, eventos, reportes ni evidencias.

**Prioridad:** P0

### RF-PER-004.1 â€” Limpieza de catalogos sin uso

El sistema permitira eliminar catalogos capturados por error cuando no tengan asignaciones, horarios generados, asistencias, reportes, evidencias ni dependencias operativas. Si el registro ya participa en horario o cumplimiento, se bloqueara la eliminacion ordinaria y se ofrecera inactivacion, baja, finalizacion o versionamiento segun corresponda.

**Prioridad:** P0

### RF-PER-005 â€” Acceso individual

Cada persona trabajadora tendrÃ¡ acceso Ãºnicamente a sus registros y solicitudes.

**Prioridad:** P0

---

## 6.4 Horarios y turnos

### RF-HOR-001 â€” CatÃ¡logo de horarios

La empresa podrÃ¡ crear horarios con:

- Nombre.
- Tipo legal programado.
- Hora de inicio.
- Hora de fin.
- Descansos.
- DÃ­as aplicables.
- Zona horaria.
- Vigencia.

**Prioridad:** P0

### RF-HOR-002 â€” Turnos rotativos

El sistema permitirÃ¡ programar turnos rotativos.

**Prioridad:** P0

### RF-HOR-003 â€” AsignaciÃ³n por persona o grupo

Los horarios podrÃ¡n asignarse individualmente o por grupo.

**Prioridad:** P0

### RF-HOR-004 â€” Cambio con fecha efectiva

Un cambio de horario no modificarÃ¡ jornadas anteriores.

**Prioridad:** P0

### RF-HOR-005 â€” Calendario de descanso

Se podrÃ¡ configurar el dÃ­a de descanso semanal y descansos obligatorios aplicables.

Los descansos obligatorios se separan en dos conceptos:

- `type`: `legal_mandatory`, `electoral` o `company_internal`.
- `scope`: `national`, `subnational` o `company`.

Combinaciones permitidas:

- `legal_mandatory`: `national` o `subnational`.
- `electoral`: `national` o `subnational`.
- `company_internal`: Ãºnicamente `company`.

NormalizaciÃ³n requerida:

- `national`: requiere `country_code`, sin `company_id` y sin `jurisdiction_code`.
- `subnational`: requiere `country_code` y `jurisdiction_code` normalizado, sin `company_id`.
- `company`: con `company_id` y sin `jurisdiction_code`.

Durante el MVP el paÃ­s operativo queda fijo en MÃ©xico (`country_code = MX`). No se implementan calendarios de otros paÃ­ses, reglas laborales extranjeras ni selector internacional de paÃ­s.

Los registros nacionales, subnacionales o electorales globales solo podrÃ¡n administrarse por `super_admin`. Los usuarios de empresa solo podrÃ¡n administrar descansos `company_internal` de su empresa.

**Prioridad:** P0

---

### RF-HOR-006 â€” Programacion diaria publicada

La programacion diaria publicada sera la unica fuente de verdad operativa para registro, calculo, alertas, cierres y reportes.

Los perfiles de horario no tendran efecto operativo directo. Solo generaran borradores de programacion diaria que deberan revisarse y publicarse.

Cada dia publicado debera conservar snapshot JSON canonico, version consecutiva por centro y periodo, `published_by`, `published_at` y hash SHA-256.

La publicacion sera inmutable. Una correccion generara una nueva version y la version anterior quedara `superseded`.

`daily_schedule_assignments` publicados y `daily_schedule_segments` seran la unica fuente operativa.

En Bloque F1 se implementa el nucleo de datos y dominio: batches por empresa/centro/periodo, asignaciones diarias, segmentos diarios, version asignada al publicar, snapshot canonico con SHA-256 y resolucion de programacion publicada. En Bloque F2 se implementa la generacion de borradores desde perfiles con modos `missing_only` y `refresh_profile_generated`. En Bloque F3A se implementa la validacion integral y publicacion atomica de batches completos desde dominio. En Bloque F3B se implementa la interfaz `/scheduling/daily` para crear lotes, generar desde perfiles, editar borradores, validar, publicar y verificar integridad de publicaciones.

La generacion F2 debe preservar dias manuales, CSV, API o system ajenos al generador; debe congelar unidad principal y timezone por fecha; `calendar` y ausencia de perfil generan `unassigned` con motivo explicito. No publica, no persiste snapshot de publicacion y no calcula jornada.

La publicacion F3A/F3B debe bloquear batches incompletos, dias `unassigned`, conflictos con programacion ya publicada por relacion laboral/fecha, versiones correctivas y cualquier configuracion incompatible por tipo de dia. Al publicar debe persistir `snapshot_schema_version`, `snapshot_canonical_json`, `snapshot_sha256`, `published_by` y `published_at`; la verificacion de integridad debe validar el JSON y hash persistidos sin reconstruir desde catalogos actuales. F3B no implementa correcciones versionadas, CSV/XLSX, API WFM, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

**Prioridad:** P0

### RF-HOR-007 â€” Perfiles WFM

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

### RF-HOR-008 â€” Estructura organizacional

La empresa podra operar solo con centros o agregar unidades organizacionales opcionales por centro con jerarquia visible inicial `department` -> `area` -> `team`.

Un trabajador podra tener una unidad principal vigente y apoyos temporales opcionales.

Los responsables y supervisores solo podran operar trabajadores dentro de sus centros completos o unidades asignadas. Nunca obtendran alcance automatico solo por poseer el rol.

**Prioridad:** P0

## 6.5 Registro electrÃ³nico

### RF-REG-001 â€” Registro de entrada

La persona trabajadora podrÃ¡ registrar el inicio de su jornada.

**Prioridad:** P0

### RF-REG-002 â€” Registro de salida

La persona trabajadora podrÃ¡ registrar el final de su jornada.

**Prioridad:** P0

### RF-REG-003 â€” Registro de pausas

El sistema permitirÃ¡ registrar inicio y fin de pausas cuando la polÃ­tica lo requiera.

**Prioridad:** P0

### RF-REG-004 â€” MÃºltiples fuentes de captura

El sistema podrÃ¡ recibir eventos mediante:

- Web responsiva/PWA.
- Kiosco.
- Captura administrativa justificada.
- CSV.
- API.

**Prioridad:** P0

### RF-REG-005 â€” Datos mÃ­nimos del evento

Cada evento deberÃ¡ conservar:

- Identificador Ãºnico.
- Empresa.
- Persona.
- Tipo.
- Fecha y hora del hecho.
- Zona horaria.
- Fecha y hora de recepciÃ³n.
- Fuente.
- Usuario, dispositivo o integraciÃ³n.
- Estado.

**Prioridad:** P0

### RF-REG-006 â€” Idempotencia

La API y las importaciones deberÃ¡n evitar duplicar eventos cuando se reintente una operaciÃ³n.

**Prioridad:** P0

### RF-REG-007 â€” Registro tardÃ­o

El sistema distinguirÃ¡ entre la hora del hecho y la hora de recepciÃ³n.

**Prioridad:** P0

### RF-REG-008 â€” Eventos fuera de orden

El sistema permitirÃ¡ recibir eventos fuera de orden y marcarÃ¡ la jornada para recalculo o revisiÃ³n.

**Prioridad:** P0

### RF-REG-009 â€” Captura manual justificada

Una captura manual deberÃ¡ registrar:

- Motivo.
- Autor.
- Fecha.
- Evidencia opcional.
- AprobaciÃ³n cuando aplique.

**Prioridad:** P0

### RF-REG-010 â€” No eliminaciÃ³n destructiva

Un evento utilizado no podrÃ¡ eliminarse mediante una operaciÃ³n ordinaria.

**Prioridad:** P0

---

## 6.6 Motor legal

### RF-CAL-001 â€” ReconstrucciÃ³n de jornada

El sistema deberÃ¡ reconstruir la jornada a partir de eventos vÃ¡lidos.

**Prioridad:** P0

### RF-CAL-002 â€” ClasificaciÃ³n por tipo

El sistema calcularÃ¡ minutos diurnos y nocturnos y clasificarÃ¡ la jornada como:

- Diurna.
- Nocturna.
- Mixta.
- Pendiente.

**Prioridad:** P0

### RF-CAL-003 â€” LÃ­mites diarios

El sistema validarÃ¡ el mÃ¡ximo diario aplicable.

**Prioridad:** P0

### RF-CAL-004 â€” LÃ­mite semanal por vigencia

El sistema aplicarÃ¡ el mÃ¡ximo semanal vigente en la fecha trabajada.

**Prioridad:** P0

### RF-CAL-005 â€” Horas extraordinarias

El sistema separarÃ¡:

- Tiempo ordinario.
- Emergencia.
- Extraordinario dentro del artÃ­culo 66.
- Excedente del artÃ­culo 68.
- Tiempo superior al mÃ¡ximo diario.

**Prioridad:** P0

### RF-CAL-006 â€” Descansos

El motor determinarÃ¡ si una pausa es computable y detectarÃ¡ descansos insuficientes.

**Prioridad:** P0

### RF-CAL-007 â€” Domingo y descansos obligatorios

El sistema identificarÃ¡ tiempo trabajado:

- En domingo.
- En descanso semanal.
- En descanso obligatorio.

**Prioridad:** P0

### RF-CAL-008 â€” Regla mÃ¡s favorable

El motor podrÃ¡ aplicar condiciones contractuales mÃ¡s favorables que el mÃ¡ximo legal.

**Prioridad:** P0

### RF-CAL-009 â€” Reglas versionadas

Los parÃ¡metros normativos tendrÃ¡n:

- Fuente.
- VersiÃ³n.
- Inicio de vigencia.
- Fin de vigencia.
- Estado.

**Prioridad:** P0

### RF-CAL-010 â€” ExplicaciÃ³n del cÃ¡lculo

Cada resultado deberÃ¡ mostrar:

- Eventos considerados.
- Regla aplicada.
- Minutos por categorÃ­a.
- Alertas generadas.
- VersiÃ³n del cÃ¡lculo.

**Prioridad:** P0

---

## 6.7 Alertas preventivas

### RF-ALT-001 â€” GeneraciÃ³n automÃ¡tica

El sistema deberÃ¡ generar alertas cuando detecte posibles desviaciones.

**Prioridad:** P0

### RF-ALT-002 â€” CatÃ¡logo mÃ­nimo de alertas

Como mÃ­nimo:

- Entrada faltante.
- Salida faltante.
- Evento duplicado.
- Jornada incompleta.
- Jornada diaria excedida.
- Jornada semanal excedida.
- Horas extraordinarias.
- Retardo.
- Salida anticipada.
- MÃ¡s de doce horas en un dÃ­a.
- Descanso insuficiente.
- MÃ¡s de seis dÃ­as consecutivos.
- Trabajo en domingo.
- Trabajo en descanso obligatorio.
- Horario o relaciÃ³n no vigente.
- CorrecciÃ³n pendiente.
- Diferencia entre tiempo calculado, autorizado y exportado.
- Reporte de periodo con diferencias.

**Prioridad:** P0

### RF-ALT-003 â€” Niveles de prioridad

Las alertas tendrÃ¡n niveles:

- Informativa.
- Advertencia.
- Alta.
- CrÃ­tica.

**Prioridad:** P0

### RF-ALT-004 â€” Estados

Las alertas tendrÃ¡n los siguientes estados:

- Nueva.
- En revisiÃ³n.
- Pendiente de informaciÃ³n.
- Justificada.
- Corregida.
- Cerrada.

**Prioridad:** P0

### RF-ALT-005 â€” Lenguaje neutral

La interfaz deberÃ¡ utilizar expresiones como:

- Posible incumplimiento.
- SituaciÃ³n pendiente de revisiÃ³n.
- Requiere validaciÃ³n.
- Tiempo superior al programado.

No deberÃ¡ presentar automÃ¡ticamente una infracciÃ³n confirmada.

**Prioridad:** P0

### RF-ALT-006 â€” Responsable y vencimiento

Una alerta podrÃ¡ asignarse a un responsable y tener una fecha objetivo.

**Prioridad:** P0

### RF-ALT-007 â€” ResoluciÃ³n trazable

La resoluciÃ³n deberÃ¡ conservar:

- Responsable.
- Fecha.
- Comentario.
- Evidencia.
- AcciÃ³n aplicada.
- Estado final.

**Prioridad:** P0

### RF-ALT-008 â€” Bloqueo de cierre

Una alerta crÃ­tica pendiente podrÃ¡ bloquear el cierre definitivo del periodo.

**Prioridad:** P0

### RF-ALT-009 â€” No alteraciÃ³n de eventos

Resolver una alerta no deberÃ¡ modificar eventos sin utilizar el flujo de correcciÃ³n.

**Prioridad:** P0

---

## 6.8 Incidencias y correcciones

### RF-INC-001 â€” CreaciÃ³n de incidencia

El sistema permitirÃ¡ crear incidencias manuales o automÃ¡ticas.

**Prioridad:** P0

### RF-INC-002 â€” Tipos mÃ­nimos

- Registro faltante.
- Registro incorrecto.
- Descanso.
- Horario.
- Turno.
- Horas extraordinarias.
- Retardo.
- Salida anticipada.
- Trabajo en domingo.
- Descanso obligatorio.
- Diferencia de cÃ¡lculo.
- Problema tÃ©cnico.
- Solicitud de la persona trabajadora.

**Prioridad:** P0

### RF-INC-003 â€” CorrecciÃ³n propuesta

Toda correcciÃ³n deberÃ¡ mostrar:

- Valor original.
- Valor propuesto.
- Motivo.
- Evidencia.
- Solicitante.
- Fecha.

**Prioridad:** P0

### RF-INC-004 â€” Flujo de aprobaciÃ³n

La correcciÃ³n podrÃ¡ requerir aprobaciÃ³n del supervisor o recursos humanos.

**Prioridad:** P0

### RF-INC-005 â€” Historial

La correcciÃ³n no sobrescribirÃ¡ el valor original.

**Prioridad:** P0

### RF-INC-006 â€” Recalculo

Una correcciÃ³n aprobada generarÃ¡ una nueva versiÃ³n del cÃ¡lculo.

**Prioridad:** P0

### RF-INC-007 â€” Controversia

Si no existe acuerdo, el sistema conservarÃ¡:

- Registro original.
- Solicitud.
- Respuesta.
- Evidencias.
- Estado de controversia.

**Prioridad:** P0

---

## 6.9 Portal de la persona trabajadora

### RF-PORT-001 â€” Consulta de jornadas

La persona podrÃ¡ consultar sus jornadas por dÃ­a, semana y periodo.

**Prioridad:** P0

### RF-PORT-002 â€” Detalle explicable

La persona podrÃ¡ consultar:

- Eventos.
- Horario.
- Pausas.
- Horas ordinarias.
- Horas extraordinarias.
- Retardo.
- Salida anticipada.
- Alertas visibles.
- Correcciones.

**Prioridad:** P0

### RF-PORT-003 â€” Solicitud de aclaraciÃ³n

La persona podrÃ¡ solicitar una aclaraciÃ³n desde una jornada o reporte.

**Prioridad:** P0

### RF-PORT-004 â€” Evidencia adjunta

La persona podrÃ¡ adjuntar evidencia a su solicitud.

**Prioridad:** P0

### RF-PORT-005 â€” Seguimiento

La persona podrÃ¡ consultar el estado y resoluciÃ³n de sus solicitudes.

**Prioridad:** P0

### RF-PORT-006 â€” Acceso a polÃ­ticas

La persona podrÃ¡ consultar polÃ­ticas y mecanismos de registro vigentes que le apliquen.

**Prioridad:** P1

---

## 6.10 Cierre y conformidad digital

### RF-CIE-H1 â€” Periodos de asistencia por alcance

El sistema permitira crear periodos de asistencia por centro completo o por una o varias unidades organizacionales del mismo centro, usando un rango de fechas definido por el usuario.

Reglas:

- El periodo pertenece a la empresa activa.
- El centro debe pertenecer a la empresa activa.
- Las unidades seleccionadas deben pertenecer al mismo centro.
- No se acepta `company_id` desde la interfaz.
- H1 genera periodos abiertos.
- H2 valida si existen bloqueantes en Jornadas y permite cerrar solo periodos sin bloqueantes.
- H3 genera un reporte base congelado al cierre con resumen general y desglose por trabajador.
- El modulo de periodos no revisa jornadas en detalle ni resuelve incidencias; enlaza a Jornadas para atenderlas.
- La exportacion CSV base queda disponible solo para periodos cerrados; no calcula nomina.

Valor:

Permite que RH prepare paquetes de asistencia por rango real de operacion, sin imponer reglas rigidas de nomina ni dispersiÃ³n.

### RF-CIE-H2 â€” Validacion y cierre operativo

El sistema permitira validar un periodo contra las jornadas existentes del mismo centro, unidades y rango.

Reglas:

- Si existen alertas abiertas o jornadas pendientes/en revision, el periodo no podra cerrarse.
- El usuario debera atender los bloqueantes desde Jornadas.
- Si no existen bloqueantes, el periodo podra cerrarse.
- El cierre conservara usuario, fecha, resumen de validacion, snapshot canonico y hash SHA-256.

### RF-CIE-H3 â€” Reporte base del periodo

Al cerrar un periodo, Vera Time generara un reporte base congelado.

Debe incluir:

- Centro, alcance y rango.
- Trabajadores incluidos.
- Jornadas programadas.
- Asistencias.
- Faltas.
- Jornadas incompletas.
- Minutos ordinarios.
- Minutos extra aprobados.
- Domingos trabajados.
- Descansos obligatorios trabajados.
- Incidencias abiertas y cerradas.

El reporte es operativo para revision y para exportacion CSV base del periodo cerrado. No calcula pagos, nomina, impuestos ni dispersion.

### RF-CIE-H4 - Exportacion CSV base del periodo cerrado

El sistema permitira descargar un CSV operativo desde un periodo cerrado.

Debe incluir, cuando exista informacion disponible:

- Identificacion de empresa, centro, unidad y trabajador.
- RFC, CURP y NSS del trabajador.
- Fecha, tipo de dia y estatus de jornada.
- Hora de entrada y salida.
- Horas ordinarias, extra totales, dobles y triples.
- Horas nocturnas.
- Domingo trabajado y descanso obligatorio trabajado.
- Referencia de descanso obligatorio cuando aplique.
- Descansos pagados y no pagados.
- Minutos de retardo y salida anticipada calculados contra la programacion publicada con tolerancias configurables por empresa.
- Minutos de ausencia.
- Tipo, estatus y pago operativo de incidencia.
- Observaciones sanitizadas para evitar formulas en CSV.

Cierre funcional antes de API:

- Retardo y salida anticipada quedan calculados en el MVP cuando la jornada tiene programacion publicada y eventos completos, y generan alertas operativas cuando superan la tolerancia configurada.
- Vacaciones, incapacidad, permisos y faltas justificadas pagadas/no pagadas salen al CSV cuando existen como incidencias/ausencias operativas en el snapshot de jornada.
- Queda pendiente definir el contrato API equivalente al CSV para consulta externa del periodo.

### RF-CIE-000 â€” Perfiles multiples de cierre

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

### RF-CON-001 â€” Periodos de cierre

La empresa podrÃ¡ configurar cierres:

- Semanales.
- Quincenales.
- Mensuales.
- Por periodo de nÃ³mina.

**Prioridad:** P0

### RF-CON-002 â€” GeneraciÃ³n de reporte individual

Al cierre se generarÃ¡ un reporte individual con:

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

### RF-CON-003 â€” Estados del periodo

El periodo podrÃ¡ estar en:

- En cÃ¡lculo.
- Con alertas.
- En revisiÃ³n administrativa.
- Disponible para revisiÃ³n.
- Conforme.
- No conforme.
- En aclaraciÃ³n.
- Cerrado.

**Prioridad:** P0

### RF-CON-004 â€” Opciones de la persona trabajadora

La persona podrÃ¡ seleccionar:

- Conforme.
- No conforme / solicitar aclaraciÃ³n.
- Pendiente de revisiÃ³n.

**Prioridad:** P0

### RF-CON-005 â€” ConfirmaciÃ³n expresa

La conformidad requerirÃ¡ una acciÃ³n expresa de la persona autenticada.

**Prioridad:** P0

### RF-CON-006 â€” Evidencia de confirmaciÃ³n

Se conservarÃ¡:

- Identidad.
- Periodo.
- VersiÃ³n.
- Fecha y hora.
- Zona horaria.
- Resultado.
- Texto aceptado.
- MÃ©todo de autenticaciÃ³n.
- Hash del reporte.
- IP y dispositivo como datos auxiliares.

**Prioridad:** P0

### RF-CON-007 â€” Texto sin renuncia de derechos

El texto de conformidad deberÃ¡ aclarar que no implica renuncia a salarios, prestaciones ni derechos laborales.

**Prioridad:** P0

### RF-CON-008 â€” No conformidad

Una no conformidad deberÃ¡ generar una incidencia vinculada al reporte.

**Prioridad:** P0

### RF-CON-009 â€” Nueva versiÃ³n

Si el reporte cambia despuÃ©s de una correcciÃ³n:

1. Se conserva la versiÃ³n anterior.
2. Se genera una nueva versiÃ³n.
3. Se invalidarÃ¡ Ãºnicamente el cierre de la nueva versiÃ³n.
4. Se solicitarÃ¡ una nueva revisiÃ³n.

**Prioridad:** P0

### RF-CON-010 â€” Sin aceptaciÃ³n automÃ¡tica

La falta de respuesta nunca se considerarÃ¡ conformidad.

**Prioridad:** P0

### RF-CON-011 â€” Alerta crÃ­tica

Un reporte con alertas crÃ­ticas pendientes no podrÃ¡ cerrarse definitivamente.

**Prioridad:** P0

### RF-CON-012 â€” Recordatorios

El sistema podrÃ¡ enviar recordatorios de revisiÃ³n.

**Prioridad:** P1

### RF-CON-013 â€” MÃ©todo de autenticaciÃ³n

Para el MVP se admitirÃ¡:

- SesiÃ³n individual.
- ConfirmaciÃ³n expresa.
- NIP o cÃ³digo por correo.
- Hash.
- BitÃ¡cora.

**Prioridad:** P0

---

## 6.11 Reportes y expedientes

### RF-REP-001 â€” Reporte diario

El sistema generarÃ¡ reportes diarios por persona, centro y empresa.

**Prioridad:** P0

### RF-REP-002 â€” Reporte semanal o de periodo

El sistema generarÃ¡ acumulados por periodo.

**Prioridad:** P0

### RF-REP-003 â€” Reporte de horas extraordinarias

El sistema mostrarÃ¡ bandas, acumulados y alertas.

**Prioridad:** P0

### RF-REP-004 â€” Reporte de incidencias

El sistema mostrarÃ¡ incidencias por tipo, responsable y estado.

**Prioridad:** P0

### RF-REP-005 â€” Reporte de alertas

El sistema mostrarÃ¡:

- Tipo.
- Nivel.
- Estado.
- Persona.
- Centro.
- Periodo.
- Responsable.

**Prioridad:** P0

### RF-REP-006 â€” Expediente delimitado

El sistema generarÃ¡ expedientes por:

- Empresa.
- Centro.
- Persona.
- Periodo.
- Solicitud.

**Prioridad:** P0

### RF-REP-007 â€” Manifiesto de integridad

El expediente deberÃ¡ incluir:

- Fecha de generaciÃ³n.
- Alcance.
- Usuario.
- VersiÃ³n.
- Hash o manifiesto.
- Archivos incluidos.

**Prioridad:** P0

### RF-REP-008 â€” Formatos

El MVP deberÃ¡ exportar al menos:

- PDF legible.
- CSV o XLSX estructurado.
- Paquete ZIP cuando existan varios archivos.

**Prioridad:** P0

### RF-REP-009 â€” ExportaciÃ³n para prenÃ³mina

El sistema podrÃ¡ exportar horas y conceptos, sin calcular nÃ³mina integral.

**Prioridad:** P1

---

## 6.12 Importaciones e integraciones

### RF-INT-001 â€” ImportaciÃ³n de personas

Se podrÃ¡n importar personas mediante plantilla CSV.

**Prioridad:** P0

### RF-INT-002 â€” ImportaciÃ³n de horarios

Se podrÃ¡n importar asignaciones de horario.

**Prioridad:** P0

### RF-INT-003 â€” ImportaciÃ³n de eventos

Se podrÃ¡n importar eventos con validaciÃ³n y resultado por fila.

**Prioridad:** P0

### RF-INT-004 â€” API oficial del MVP

El MVP deberÃ¡ contar con una API oficial versionada para interoperabilidad.

Como mÃ­nimo deberÃ¡ permitir:

- Crear o actualizar trabajadores.
- Consultar trabajadores.
- Crear eventos de jornada.
- Consultar eventos.
- Consultar jornadas calculadas.
- Consultar alertas.
- Crear incidencias.
- Consultar reportes de periodo.

**Prioridad:** P0

### RF-INT-005 â€” Credenciales por empresa

Cada integraciÃ³n utilizarÃ¡ credenciales limitadas a una empresa.

**Prioridad:** P0

### RF-INT-006 â€” Registro tÃ©cnico

Toda operaciÃ³n de integraciÃ³n conservarÃ¡:

- Origen.
- Fecha.
- Resultado.
- Errores.
- Identificador externo.

**Prioridad:** P0

---

## 6.13 SuscripciÃ³n y lÃ­mites

### RF-PLAN-001 â€” Plan por empresa

Cada empresa estarÃ¡ asociada a un plan.

**Prioridad:** P0

### RF-PLAN-002 â€” Persona activa

El sistema deberÃ¡ identificar personas activas para mediciÃ³n y cobro.

**Prioridad:** P0

### RF-PLAN-003 â€” LÃ­mites

El sistema podrÃ¡ limitar:

- Personas activas.
- Centros.
- Administradores.
- Almacenamiento.
- Integraciones.
- RetenciÃ³n.
- Funciones.

**Prioridad:** P0

### RF-PLAN-004 â€” Sin pÃ©rdida de datos

Al superar un lÃ­mite, el sistema no eliminarÃ¡ informaciÃ³n.

**Prioridad:** P0

### RF-PLAN-005 â€” SuspensiÃ³n controlada

Una suspensiÃ³n comercial deberÃ¡ mantener la informaciÃ³n bajo la polÃ­tica aplicable y limitar nuevas operaciones.

**Prioridad:** P1

---

## 6.14 AdministraciÃ³n global

### RF-ADM-001 â€” ParÃ¡metros legales globales

Solo usuarios autorizados podrÃ¡n administrar reglas legales globales.

**Prioridad:** P0

### RF-ADM-002 â€” PublicaciÃ³n de versiÃ³n

Una nueva regla deberÃ¡ pasar por estados:

- Borrador.
- Revisada.
- Programada.
- Vigente.
- Sustituida.

**Prioridad:** P0

### RF-ADM-003 â€” Historial normativo

El sistema conservarÃ¡ todas las versiones.

**Prioridad:** P0

### RF-ADM-004 â€” Soporte auditado

Todo acceso de soporte a una empresa deberÃ¡:

- Estar autorizado.
- Tener motivo.
- Tener duraciÃ³n.
- Quedar auditado.

**Prioridad:** P0

---

## 6.15 API e interoperabilidad bidireccional

### RF-API-001 â€” Operaciones crÃ­ticas por API

Las funcionalidades crÃ­ticas del MVP deberÃ¡n poder ejecutarse por API cuando formen parte de la operaciÃ³n P0.

**Prioridad:** P0

### RF-API-002 â€” Bidireccionalidad interfaz/API

Lo creado por API deberÃ¡ verse en la interfaz cuando el usuario tenga permiso. Lo creado por la interfaz deberÃ¡ poder consultarse por API cuando aplique al alcance de integraciÃ³n.

**Prioridad:** P0

### RF-API-003 â€” LÃ³gica compartida

API, CSV, jobs e integraciones deberÃ¡n usar las mismas acciones, servicios de aplicaciÃ³n y servicios de dominio que la interfaz.

La API no deberÃ¡ duplicar reglas ni saltarse validaciones del sistema.

**Prioridad:** P0

### RF-API-004 â€” Fuente del dato

Cada registro creado o modificado deberÃ¡ conservar su fuente.

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

### RF-API-005 â€” Seguridad de API

La API deberÃ¡ tener:

- AutenticaciÃ³n.
- Permisos.
- Alcance por empresa.
- Versionamiento.
- Idempotencia cuando cree datos sensibles.
- AuditorÃ­a.
- Errores estandarizados.

**Prioridad:** P0

### RF-API-006 â€” Capacidades API P0

La API del MVP deberÃ¡ cubrir:

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

### RF-API-007 â€” Capacidades API P1

Las siguientes capacidades podrÃ¡n implementarse si no comprometen la fecha del MVP:

| Capacidad | Prioridad |
|---|---|
| Crear horarios | P1 |
| Asignar horarios | P1 |
| Resolver alertas | P1 |
| Aprobar correcciones | P1 |
| Generar expedientes | P1 |
| Confirmar conformidad vÃ­a API | P1 |
| IntegraciÃ³n directa ClickBalance | P1 |

**Prioridad:** P1

---

# 7. Requisitos no funcionales

## RNF-001 â€” Seguridad

La plataforma deberÃ¡ aplicar:

- HTTPS.
- ContraseÃ±as seguras.
- ProtecciÃ³n CSRF.
- PrevenciÃ³n de acceso horizontal.
- Rate limiting.
- GestiÃ³n segura de secretos.
- Principio de mÃ­nimo privilegio.

**Prioridad:** P0

## RNF-002 â€” Aislamiento multi-tenant

Toda capa de acceso a datos deberÃ¡ respetar el tenant.

**Prioridad:** P0

## RNF-003 â€” Integridad

Los eventos, cÃ¡lculos cerrados, reportes firmados y expedientes deberÃ¡n ser verificables.

**Prioridad:** P0

## RNF-004 â€” Trazabilidad

Toda operaciÃ³n sensible deberÃ¡ registrar:

- Actor.
- Fecha.
- AcciÃ³n.
- Entidad.
- Valor anterior.
- Valor nuevo.
- Motivo.

**Prioridad:** P0

## RNF-005 â€” Disponibilidad

Objetivo inicial del MVP:

```text
99.5 % mensual
```

Excluyendo mantenimientos programados.

**Prioridad:** P0

## RNF-006 â€” RecuperaciÃ³n

El sistema deberÃ¡ contar con:

- Respaldos automÃ¡ticos.
- Prueba periÃ³dica de restauraciÃ³n.
- RPO y RTO definidos antes del piloto.

**Prioridad:** P0

## RNF-007 â€” Rendimiento

Objetivos iniciales:

- Pantallas comunes: menos de 2 segundos en condiciones normales.
- Registro de evento: confirmaciÃ³n menor de 3 segundos.
- Reportes pesados: procesamiento asÃ­ncrono cuando sea necesario.

**Prioridad:** P0

## RNF-008 â€” Escalabilidad

El registro de eventos deberÃ¡ soportar crecimiento sin rediseÃ±ar el dominio principal.

**Prioridad:** P0

## RNF-009 â€” Accesibilidad

Las pantallas principales deberÃ¡n cumplir criterios bÃ¡sicos de accesibilidad:

- NavegaciÃ³n por teclado.
- Etiquetas.
- Contraste.
- Mensajes comprensibles.
- DiseÃ±o responsivo.

**Prioridad:** P0

## RNF-010 â€” Privacidad

El sistema aplicarÃ¡:

- MinimizaciÃ³n.
- Finalidad.
- Acceso restringido.
- Aviso de privacidad.
- RetenciÃ³n.
- Procedimientos ARCO.

**Prioridad:** P0

## RNF-011 â€” Observabilidad

La plataforma deberÃ¡ contar con:

- Logs.
- Monitoreo.
- Alertas tÃ©cnicas.
- Seguimiento de errores.
- MÃ©tricas de uso.

**Prioridad:** P0

## RNF-012 â€” Mantenibilidad

Las reglas legales deberÃ¡n estar separadas de la interfaz y versionadas.

**Prioridad:** P0

## RNF-013 â€” Pruebas

El proyecto deberÃ¡ tener pruebas automatizadas para:

- CÃ¡lculos.
- Multi-tenant.
- Permisos.
- Correcciones.
- Alertas.
- Conformidad digital.
- Integridad de reportes.

**Prioridad:** P0

## RNF-014 â€” Portabilidad

La empresa podrÃ¡ obtener una exportaciÃ³n de su informaciÃ³n.

**Prioridad:** P0

## RNF-015 â€” Neutralidad jurÃ­dica

La plataforma deberÃ¡ evitar presentar alertas como sentencias definitivas.

**Prioridad:** P0

---

# 8. Flujos principales

## FLUJO-001 â€” Alta y configuraciÃ³n inicial

```text
Crear empresa
â†’ Crear centros
â†’ Crear usuarios
â†’ Importar personas
â†’ Crear horarios y turnos
â†’ Asignar condiciones
â†’ Activar registro
```

## FLUJO-002 â€” Registro y cÃ¡lculo

```text
Registrar entrada
â†’ Registrar pausas
â†’ Registrar salida
â†’ Reconstruir jornada
â†’ Aplicar reglas
â†’ Generar resultados
â†’ Generar alertas
```

## FLUJO-003 â€” Incidencia y correcciÃ³n

```text
Detectar diferencia
â†’ Crear incidencia
â†’ Revisar datos
â†’ Proponer correcciÃ³n
â†’ Aprobar o rechazar
â†’ Recalcular
â†’ Conservar versiÃ³n previa
```

## FLUJO-004 â€” Cierre y conformidad

```text
Finalizar periodo
â†’ Calcular jornadas
â†’ Revisar alertas
â†’ Resolver crÃ­ticas
â†’ Generar reporte individual
â†’ Enviar a revisiÃ³n
â†’ Conforme / No conforme / Pendiente
â†’ Cerrar o abrir aclaraciÃ³n
```

## FLUJO-005 â€” Expediente

```text
Recibir solicitud
â†’ Delimitar empresa, personas y periodo
â†’ Generar informaciÃ³n
â†’ Revisar
â†’ Cerrar expediente
â†’ Generar hash
â†’ Entregar
â†’ Conservar acuse
```

---

# 9. Criterios globales de aceptaciÃ³n del MVP

El MVP estarÃ¡ listo para piloto cuando:

1. Una empresa pueda configurarse sin intervenciÃ³n directa en base de datos.
2. Puedan importarse personas y horarios.
3. Una persona pueda registrar entrada y salida.
4. El motor pueda reconstruir y clasificar jornadas.
5. El sistema detecte alertas P0.
6. Las incidencias puedan corregirse sin eliminar el historial.
7. La persona trabajadora pueda consultar sus registros.
8. Pueda marcar conforme o no conforme un reporte.
9. Una correcciÃ³n genere una nueva versiÃ³n.
10. Un reporte firmado conserve hash y evidencia.
11. Puedan generarse reportes por persona, centro y periodo.
12. Pueda generarse un expediente delimitado.
13. Los datos de una empresa no sean accesibles desde otra.
14. Existan pruebas automatizadas de reglas crÃ­ticas.
15. Existan respaldos y monitoreo.
16. El piloto pueda operar durante al menos dos semanas sin pÃ©rdida de informaciÃ³n.

---

# 10. PriorizaciÃ³n resumida

## P0 â€” Obligatorio para producciÃ³n

- Multi-tenant.
- Empresas y centros.
- Personas y relaciones laborales.
- Horarios y turnos.
- Registro electrÃ³nico.
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
- AuditorÃ­a.
- Respaldos.
- Monitoreo.

## P1 â€” Solo si no afecta la fecha

- Recordatorios avanzados.
- ExportaciÃ³n de prenÃ³mina especÃ­fica.
- Teletrabajo bÃ¡sico.
- PolÃ­ticas visibles en portal.
- SuspensiÃ³n comercial automatizada.
- Notificaciones configurables.
- Crear horarios por API.
- Asignar horarios por API.
- Resolver alertas por API.
- Aprobar correcciones por API.
- Generar expedientes por API.
- Confirmar conformidad vÃ­a API.
- IntegraciÃ³n directa ClickBalance.

## Fuera

- App nativa.
- BiometrÃ­a.
- Hardware.
- NÃ³mina.
- IA.
- AnalÃ­tica avanzada.
- InternacionalizaciÃ³n.

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

DespuÃ©s de aprobar este documento, se deberÃ¡ continuar con:

```text
docs/04-Arquitectura/
```

El siguiente documento recomendado serÃ¡:

```text
ARQ-0001-ARQUITECTURA-DEL-MVP.md
```

AhÃ­ se definirÃ¡n:

- MÃ³dulos tÃ©cnicos.
- LÃ­mites del dominio.
- Estrategia multi-tenant.
- Componentes Laravel/Livewire.
- Motor de reglas.
- Eventos y colas.
- Almacenamiento.
- Seguridad.
- Integraciones.
- Despliegue.

---

## Nota Bloque F4 - Correcciones versionadas de programacion diaria

- Una programacion diaria publicada no se edita directamente.
- Toda correccion crea un borrador correctivo sin numero de version publicada y con motivo general obligatorio.
- La nueva version conserva empresa, centro y periodo, y usa `previous_batch_id`.
- La cobertura correctiva conserva las mismas combinaciones `employment_relationship_id` + `work_date`.
- La comparacion debe detectar cambios funcionales e ignorar IDs y timestamps.
- Publicar una correccion sustituye atomica y no destructivamente la version anterior.
- CSV/XLSX, API WFM, calculos legales, `work_days`, alertas, incidencias, cierres, conformidad y reportes siguen pendientes.

## Nota Bloque F5A - Dominio de importacion CSV de programacion diaria

- La importacion CSV de programacion diaria registra un lote de importacion asociado a un `schedule_batch` en borrador.
- El archivo usa encabezados estrictos version 1 y se almacena en disco privado.
- La validacion resuelve trabajador, relacion laboral, fecha, tipo de dia y plantilla de turno antes de escribir.
- Las filas invalidas bloquean toda aplicacion.
- La aplicacion es transaccional y usa las Actions de programacion diaria existentes.
- Las filas aplicadas quedan con `source_type = csv`.
- Una correccion versionada en borrador solo puede modificar cobertura ya clonada.
- F5A no implementa pantalla de carga, XLSX, API WFM, jobs asincronos, publicacion automatica, calculos legales, `work_days`, alertas, incidencias, cierres, conformidad ni reportes.

## Nota Bloque F5B - Interfaz de importacion CSV de programacion diaria

- La pantalla `/scheduling/daily` permite importar CSV solo sobre lotes `draft`.
- La interfaz permite descargar plantilla CSV version 1, cargar archivo privado, validar, revisar preview paginado, aplicar con confirmacion y descargar errores.
- La aplicacion exige hash de validacion vigente para evitar aplicar una vista previa obsoleta.
- Los archivos se almacenan con ruta interna aleatoria; la ruta privada no se expone al usuario.
- Supervisores, usuarios de otra empresa y lotes publicados no pueden usar la importacion CSV.
- F5B no implementa XLSX, API WFM, jobs asincronos, publicacion automatica, calculos legales, `work_days`, alertas, incidencias, cierres, conformidad ni reportes.

## Nota Bloque 5 - Ciclo de eventos de tiempo

- `time_events` conserva hora real del hecho en UTC/local, timezone, `received_at`, fuente, usuario/canal y metadata.
- `received_at` es el campo explicito de recepcion/captura tecnica para eventos tardios o fuera de orden.
- La anulacion logica no elimina el evento; marca `status = voided` y registra motivo, actor y fecha/hora.
- Los resolvers excluyen anulados y ordenan eventos validos por hora del hecho con desempates estables.
- `/time-events/manual` permite anular eventos recientes de la empresa a `owner`, `admin` y `rh`.
- Bloque 5 no implementa `work_days`, motor legal, horas extra, alertas, incidencias, reportes ni API.
- Siguiente bloque pendiente: `work_days`.

## Nota Admin A1/A2 - alta guiada y suscripciones

### Admin A1 - alta guiada de empresa y administrador principal

Estado: implementado / candidato a cierre.

Objetivo:

- Permitir que `super_admin` cree una empresa cliente y su administrador principal en un solo flujo guiado.
- Reducir pasos manuales separados entre alta de empresa, configuracion inicial, creacion de usuario y asignacion `admin_empresa`.
- Dejar listo el tenant para iniciar configuracion operativa sin intervencion directa en base de datos.

Reglas funcionales:

- Solo `super_admin` puede ejecutar el alta guiada A1.
- El flujo debe crear la empresa, su configuracion inicial y el usuario administrador principal dentro de una transaccion.
- El usuario administrador principal queda asociado a la empresa con rol `admin_empresa`, membresia activa y empresa activa.
- El `super_admin` no queda agregado automaticamente a `company_user` de la empresa creada.
- El flujo no debe permitir operar jornadas, horarios, incidencias o periodos como si el `super_admin` fuera personal interno de la empresa.
- La creacion debe validar correo unico, datos minimos de empresa, zona horaria y estado inicial.
- Si falla cualquier paso, no debe quedar empresa, usuario o membresia parcial.

Datos minimos sugeridos:

- Nombre comercial.
- Razon social.
- RFC, cuando aplique.
- Zona horaria principal.
- Estado inicial de empresa.
- Nombre del administrador principal.
- Correo del administrador principal.
- Contrasena temporal generada o capturada bajo reglas seguras.

No incluye en A1:

- Cobro automatico.
- Stripe u otro proveedor de pago.
- Facturacion automatica.
- Portal publico de compra.
- Invitaciones por correo, salvo decision posterior.
- Suscripcion multiempresa operativa.

### Admin A2 - cuenta cliente, planes y suscripciones

Estado: implementado parcial / candidato a cierre.

Decision de producto:

- Vera Time se vendera como suscripcion.
- La venta normal inicial sera una cuenta cliente con una empresa operativa.
- Debe prepararse el modelo para una suscripcion multiempresa especial, util para despachos contables, grupos empresariales o administradores externos, sin convertirlo en el flujo principal del MVP.

Conceptos a separar:

- Cuenta cliente: entidad comercial que contrata y paga.
- Empresa operativa: tenant donde viven trabajadores, horarios, jornadas, periodos y evidencias.
- Suscripcion: plan, limites, vigencia y estado comercial.

Reglas A2:

- Una cuenta cliente podra tener una o varias empresas segun el plan.
- Una empresa operativa siempre conserva aislamiento por `company_id`.
- La suscripcion multiempresa no debe mezclar datos entre empresas.
- Los usuarios con acceso a varias empresas siguen usando permisos por empresa y selector de empresa activa.
- A1/A2 crea la cuenta cliente formal y registra el tipo `single_company` o `multi_company`.
- El cobro, limites automaticos, facturacion y suspension formal por cuenta cliente quedan para bloques posteriores.

Riesgo a controlar:

- No amarrar irrevocablemente la suscripcion solo a `companies`, porque eso dificultaria cuentas multiempresa futuras.
- No implementar complejidad multiempresa comercial, cobro o limites antes de validar el flujo simple de una empresa por cuenta cliente.

### Admin A3 - suspensiones y acceso

Estado: implementado / candidato a cierre.

El acceso operativo a una empresa requiere que esten activos:

- cuenta cliente;
- empresa;
- usuario global;
- membresia usuario-empresa;
- rol y alcance aplicable.

Reglas:

- Suspender una cuenta cliente bloquea todas sus empresas sin borrar datos.
- Suspender una empresa bloquea solo esa empresa.
- Suspender un usuario bloquea su identidad en todo Vera Time.
- Suspender una membresia bloquea solo el acceso del usuario a esa empresa.
- Solo `super_admin` administra cuentas cliente.
- Planes, cobro, facturacion y limites automaticos quedan fuera de A3.

### Admin A4 - usuarios y membresias

Estado: implementado / candidato a cierre.

Reglas:

- El estado global del usuario (`users.status`) controla si la persona puede ingresar a Vera Time.
- La membresia (`company_user.status`) controla si la persona puede operar una empresa concreta.
- Solo `super_admin` puede cambiar el estado global del usuario.
- Administradores de empresa y RH administrador pueden cambiar membresias y roles permitidos dentro de la empresa activa.
- Un usuario con membresia inactiva en una empresa puede conservar acceso a otra empresa donde su membresia siga activa.
- La UI debe mostrar ambos conceptos de forma separada para evitar confundir suspension global con suspension por empresa.
