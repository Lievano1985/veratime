---
id: BL-0001
title: Backlog inicial del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-09-18
tags:
  - backlog
  - mvp
  - desarrollo
  - laravel
  - livewire
  - api-first
  - domain-first
  - veratime
---

# BL-0001 â€” Backlog inicial del MVP

## 1. Objetivo

Convertir la documentaciÃ³n aprobada de Vera Time en un backlog inicial accionable para iniciar desarrollo con Codex o con equipo tÃ©cnico.

Este backlog se basa en:

```text
docs/03-Requisitos/REQ-0001-ESPECIFICACION-REQUISITOS-MVP.md
docs/04-Arquitectura/ARQ-0001-ARQUITECTURA-DEL-MVP.md
docs/05-BaseDatos/BD-0001-MODELO-DE-DATOS-MVP.md
docs/06-UX/UX-0001-MAPA-DE-PANTALLAS-MVP.md
docs/07-API/API-0001-ESPECIFICACION-API-MVP.md
docs/09-Testing/TEST-0001-ESTRATEGIA-DE-PRUEBAS-MVP.md
docs/10-Deployment/DEP-0001-ESTRATEGIA-DE-DESPLIEGUE-MVP.md
```

---

## 2. Reglas del backlog

| Regla | DecisiÃ³n |
|---|---|
| Arquitectura | Monolito modular Laravel |
| Enfoque | Domain-first + API-first pragmÃ¡tico y bidireccional |
| Base inicial | MySQL 8 / MariaDB compatible |
| Hosting inicial | Hosting actual o cPanel si aplica |
| Colas iniciales | Database queue |
| Frontend | Livewire + Blade + Tailwind |
| API | `/api/v1` con tokens por empresa |
| Multi-tenant | `company_id` obligatorio en entidades operativas |
| BiometrÃ­a | Fuera del P0 |
| App nativa | Fuera del MVP |
| ClickBalance | Archivo primero; API P1 condicionada |
| AWS | EvoluciÃ³n posterior al MVP/piloto, no dependencia inicial |

---

## 3. Prioridades

| Prioridad | Significado |
|---|---|
| P0 | Obligatorio para MVP funcional |
| P1 | Alto valor, pero no bloquea MVP |
| P2 | Fase posterior |
| OUT | Fuera del MVP |

---

## 4. DefiniciÃ³n de Done

Una historia se considera terminada cuando:

- EstÃ¡ implementada.
- Tiene validaciones.
- Respeta multi-tenant.
- Usa Actions/Services si tiene lÃ³gica de negocio.
- No duplica lÃ³gica entre Livewire/API/CSV/jobs.
- Tiene pruebas mÃ­nimas.
- Tiene auditorÃ­a si aplica.
- No destruye historial.
- Funciona en MySQL 8 / MariaDB compatible.
- No introduce dependencia obligatoria de Redis, PostgreSQL, S3 o AWS.

---

# 5. Ã‰picas del MVP

| CÃ³digo | Ã‰pica | Prioridad |
|---|---|---|
| EPIC-00 | PreparaciÃ³n tÃ©cnica | P0 |
| EPIC-01 | AutenticaciÃ³n, roles y multi-tenant | P0 |
| EPIC-02 | Empresas, centros y configuraciÃ³n | P0 |
| EPIC-03 | Trabajadores y relaciones laborales | P0 |
| EPIC-04 | Horarios, turnos y vigencias | P0 |
| EPIC-05 | Registro electrÃ³nico y kiosco | P0 |
| EPIC-06 | API base e interoperabilidad | P0 |
| EPIC-07 | Motor legal y cÃ¡lculos | P0 |
| EPIC-08 | Alertas preventivas | P0 |
| EPIC-09 | Incidencias y correcciones | P0 |
| EPIC-10 | Cierre y conformidad digital | P0 |
| EPIC-11 | Reportes, expedientes y exportaciones | P0 |
| EPIC-12 | Importaciones e integraciones | P0/P1 |
| EPIC-13 | AuditorÃ­a y seguridad | P0 |
| EPIC-14 | Testing y calidad | P0 |
| EPIC-15 | Despliegue y operaciÃ³n | P0 |
| EPIC-16 | Capacidades no bloqueantes | P1 |

---

# 6. Backlog P0 por Ã©pica

## EPIC-00 â€” PreparaciÃ³n tÃ©cnica

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0001 | Inicializar proyecto Laravel | P0 | Laravel, Livewire, Tailwind, Pest y MySQL 8 / MariaDB compatible funcionando |
| BL-0002 | Crear estructura modular `app/Domains` | P0 | Dominios base creados sin lÃ³gica duplicada |
| BL-0003 | Configurar MySQL 8 / MariaDB compatible | P0 | Migraciones corren en MySQL/MariaDB, sin dependencia de PostgreSQL |
| BL-0004 | Configurar database queue | P0 | Tablas `jobs` y `failed_jobs`, job de prueba ejecutado |
| BL-0005 | Definir IDs del dominio | P0 | ULID o estrategia aprobada aplicada consistentemente |
| BL-0006 | Configurar testing base | P0 | `php artisan test` o Pest corriendo |

---

## EPIC-01 â€” AutenticaciÃ³n, roles y multi-tenant

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0101 | Login y logout | P0 | Usuarios activos pueden entrar; inactivos no |
| BL-0102 | Modelo `Company` | P0 | Empresas creadas con estado y zona horaria |
| BL-0103 | Contexto `CurrentCompany/TenantContext` | P0 | Todas las consultas operativas usan empresa activa |
| BL-0104 | RelaciÃ³n usuario-empresa | P0 | Usuario puede pertenecer a una o varias empresas |
| BL-0105 | Selector de empresa | P0 | Solo muestra empresas autorizadas |
| BL-0106 | Roles iniciales | P0 | Roles base creados por seed |
| BL-0107 | Policies multi-tenant | P0 | Usuario A no accede a datos de Empresa B |

---

## EPIC-02 â€” Empresas, centros y configuraciÃ³n

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0201 | CRUD de empresa | P0 | Crear/editar empresa y estado |
| BL-0202 | ConfiguraciÃ³n de empresa | P0 | Periodo, kiosco, conformidad y correcciones configurables |
| BL-0203 | CRUD de centros | P0 | Centros con cÃ³digo Ãºnico por empresa |
| BL-0204 | Zona horaria por centro | P0 | Eventos usan zona horaria correcta |
| BL-0205 | Dashboard inicial | P0 | Muestra trabajadores, jornadas, alertas e incidencias bÃ¡sicas |

Nota de avance API de centros: `GET /api/v1/time/centers` y `GET /api/v1/time/centers/{centerId}` están disponibles con `centers:read`, consulta paginada, Policy y aislamiento por empresa del token. No exponen operaciones mutables de centros.

---

## EPIC-03 â€” Trabajadores y relaciones laborales

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0301 | CRUD de trabajadores | P0 | CÃ³digo Ãºnico por empresa, estado y datos bÃ¡sicos |
| BL-0302 | Baja no destructiva | P0 | Baja no elimina historial |
| BL-0303 | Relaciones laborales | P0 | Centro, puesto, fecha ingreso/baja e historial |
| BL-0304 | Condiciones laborales con vigencia | P0 | No permite solapamientos activos |
| BL-0305 | Credenciales kiosco | P0 | CÃ³digo/NIP con NIP hasheado |
| BL-0306 | Importar trabajadores CSV | P0 | Plantilla, validaciÃ³n por fila y errores descargables |
| BL-0307 | Detalle de trabajador | P0 | Resumen, jornadas, alertas, incidencias y reportes |

---

## EPIC-04 â€” Horarios, turnos y vigencias

Nota de consolidacion WFM:

EPIC-04 debe evolucionar hacia programacion diaria publicada como fuente de verdad. Las historias actuales BL-0401 a BL-0406 quedan como base legacy ya implementada para Sprint 2, pero el desarrollo futuro debe dividirse en los bloques WFM definidos al final de este documento.

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0401 | CRUD de horarios | P0 | Horario con dÃ­as, entrada, salida y tipo legal |
| BL-0402 | Pausas programadas | P0 | Pausa computable/no computable |
| BL-0403 | Horarios que cruzan medianoche | P0 | Turno nocturno 22:00-06:00 funciona |
| BL-0404 | AsignaciÃ³n de horario | P0 | AsignaciÃ³n con fecha efectiva |
| BL-0405 | Descansos obligatorios | P0 | Catalogo por fecha, tipo y alcance |
| BL-0406 | Validar vigencias | P0 | Cambios futuros no alteran jornadas pasadas |

---

## EPIC-05 â€” Registro electrÃ³nico y kiosco

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0501 | Modelo `time_events` | P0 | Guarda fuente, hora local, UTC, zona horaria y estado |
| BL-0502 | Registrar entrada/salida web | P0 | Crea eventos vÃ¡lidos y auditable |
| BL-0503 | Registrar pausas | P0 | Inicio/fin de pausa vÃ¡lidos |
| BL-0504 | Modo kiosco cÃ³digo/NIP | P0 | Trabajador registra con cÃ³digo y NIP |
| BL-0505 | Captura manual justificada | P0 | Requiere motivo y genera auditorÃ­a |
| BL-0506 | AnulaciÃ³n lÃ³gica | P0 | No elimina evento original |
| BL-0507 | Eventos fuera de orden/tardÃ­os | P0 | Conserva hora del hecho y recepciÃ³n |

---

## EPIC-06 â€” API base e interoperabilidad

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0601 | Configurar Sanctum | P0 | Tokens API funcionando |
| BL-0602 | Token ligado a empresa | P0 | Token resuelve `company_id` |
| BL-0603 | Scopes API | P0 | Token sin permiso recibe 403 |
| BL-0604 | Endpoint trabajadores | P0 | Crear, consultar y actualizar trabajadores por API sin alterar evidencia o vigencias protegidas |
| BL-0605 | Endpoint relaciones laborales | P0 | Consultar historial de relaciones por API sin alterar vigencias ni evidencia |
| BL-0606 | Endpoint eventos | P0 | Registrar evento por API |
| BL-0607 | Idempotencia API | P0 | No duplica eventos repetidos |
| BL-0608 | Endpoint jornadas | P0 | Consultar jornadas calculadas |
| BL-0609 | Endpoint alertas | P0 | Consultar listado y detalle de alertas sin cambiar su estado |
| BL-0610 | Endpoint incidencias | P0 | Crear/consultar incidencias |
| BL-0611 | Error estÃ¡ndar API | P0 | Respuestas con `message`, `errors`, `trace_id` |
| BL-0612 | Logs de integraciÃ³n | P0 | Registra operaciÃ³n, estado y trace ID |

---

## EPIC-07 â€” Motor legal y cÃ¡lculos

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0701 | CatÃ¡logo de reglas legales | P0 | Reglas y versiones con vigencia |
| BL-0702 | Resolver regla vigente por fecha | P0 | CÃ¡lculo usa versiÃ³n correcta |
| BL-0703 | Modelo `work_days` | P0 | Jornada Ãºnica por trabajador/fecha |
| BL-0704 | Modelo `work_day_calculations` | P0 | CÃ¡lculos versionados |
| BL-0705 | Reconstruir jornada | P0 | Usa eventos vÃ¡lidos |
| BL-0706 | Clasificar jornada | P0 | Diurna, nocturna, mixta o pendiente |
| BL-0707 | Calcular ordinario/extra | P0 | Minutos ordinarios y extra correctos |
| BL-0708 | Calcular descansos/domingo/obligatorios | P0 | Detecta casos especiales |
| BL-0709 | ExplicaciÃ³n del cÃ¡lculo | P0 | Muestra reglas, eventos y resultado |
| BL-0710 | Snapshot de reglas | P0 | CÃ¡lculo histÃ³rico no cambia por regla futura |
| BL-0711 | Reglas base por pais | P0 | Mexico queda preconfigurado como primer pais operativo |
| BL-0712 | Parametros legales de empresa | P0 | Empresa configura parametros permitidos con vigencia |
| BL-0713 | Reglas protegidas | P0 | Valores menos favorables quedan bloqueados o marcados fuera de cumplimiento |
| BL-0714 | UI de configuracion legal segura | P0 | Muestra reglas pais protegidas y permite editar solo parametros internos |

---

## EPIC-08 â€” Alertas preventivas

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0801 | CatÃ¡logo de tipos de alerta | P0 | Tipos mÃ­nimos creados |
| BL-0802 | Evaluador de alertas | P0 | Genera alertas desde cÃ¡lculo |
| BL-0803 | Alertas por jornada incompleta | P0 | Entrada/salida faltante genera alerta |
| BL-0804 | Alertas por lÃ­mites excedidos | P0 | Jornada diaria/semanal y 12h |
| BL-0805 | Alertas por descanso insuficiente | P0 | Antes del cierre |
| BL-0806 | Evitar duplicados | P0 | Recalculo no duplica sin control |
| BL-0807 | Listado de alertas | P0 | Filtros por estado, severidad y centro |
| BL-0808 | Detalle y resoluciÃ³n | P0 | Comentarios, evidencia y estado final |
| BL-0809 | Bloqueo por alerta crÃ­tica | P0 | Cierre definitivo bloqueado |

---

## EPIC-09 â€” Incidencias y correcciones

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-0901 | Modelo de incidencias | P0 | Incidencia con estado, tipo y trabajador |
| BL-0902 | Crear incidencia manual | P0 | Desde trabajador/jornada |
| BL-0903 | Crear incidencia desde alerta | P0 | Alerta queda vinculada |
| BL-0904 | Comentarios y evidencias | P0 | Adjuntos y visibilidad |
| BL-0905 | Proponer correcciÃ³n | P0 | Valor original/propuesto y motivo |
| BL-0906 | Aprobar correcciÃ³n | P0 | Conserva original y recalcula |
| BL-0907 | Rechazar correcciÃ³n | P0 | No cambia cÃ¡lculo activo |
| BL-0908 | Estado de controversia | P0 | Conserva desacuerdo y evidencia |

---

## EPIC-10 â€” Cierre y conformidad digital

Nota de consolidacion:

El cierre deja de ser una configuracion unica por empresa. Se agregaran perfiles multiples con prioridad: relacion laboral, unidad organizacional, centro, empresa.

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-1001 | Modelo de cierres | P0 | Periodos semanales/quincenales/mensuales |
| BL-1002 | Crear cierre | P0 | Selecciona periodo y trabajadores |
| BL-1003 | Generar reportes individuales | P0 | Un reporte por trabajador |
| BL-1004 | Versionar reporte | P0 | Reporte tiene versiÃ³n y hash |
| BL-1005 | Portal trabajador reporte | P0 | Trabajador ve solo su reporte |
| BL-1006 | Confirmar conforme | P0 | Guarda versiÃ³n, texto, fecha y mÃ©todo |
| BL-1007 | Marcar no conforme | P0 | Crea incidencia |
| BL-1008 | Pendiente no es conforme | P0 | Silencio no confirma |
| BL-1009 | Nueva versiÃ³n por correcciÃ³n | P0 | Firma anterior no aplica a versiÃ³n nueva |

---

## EPIC-11 â€” Reportes, expedientes y exportaciones

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-1101 | Centro de reportes | P0 | Diario, semanal, periodo, alertas, incidencias |
| BL-1102 | Exportar PDF | P0 | Archivo guardado con hash |
| BL-1103 | Exportar CSV/XLSX | P0 | Descarga con permisos |
| BL-1104 | ExportaciÃ³n prenÃ³mina | P0/P1 alto | Ordinarias, extras, domingo, descanso e incidencias |
| BL-1108 | Plantillas configurables de exportación de períodos | P1 alto | Cada empresa ajusta encabezados, orden y formatos CSV/XLSX desde Configuración; incluye vista previa, snapshot y auditoría de cada archivo |
| BL-1105 | Expediente por trabajador/periodo | P0 | Solo alcance solicitado |
| BL-1106 | Manifiesto de expediente | P0 | Lista de elementos y hash |
| BL-1107 | AuditorÃ­a de descarga | P0 | Registra generaciÃ³n/descarga |

---

## EPIC-12 â€” Importaciones e integraciones

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-1201 | Importar eventos CSV | P0 | ValidaciÃ³n por fila |
| BL-1202 | Resultado de importaciÃ³n | P0 | Correctas, errores, saltadas |
| BL-1203 | Idempotencia en CSV | P0 | No duplica por external_id |
| BL-1204 | ExportaciÃ³n compatible ClickBalance | P1 alto | Formato cuando se confirme |
| BL-1205 | API directa ClickBalance | P1 condicionado | Requiere documentaciÃ³n y credenciales |
| BL-1206 | Logs de integraciÃ³n | P0 | OperaciÃ³n, estado, error, trace ID |

---

## EPIC-13 â€” AuditorÃ­a y seguridad

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-1301 | Audit log transversal | P0 | Actor, acciÃ³n, entidad, antes/despuÃ©s |
| BL-1302 | AuditorÃ­a de eventos manuales | P0 | Motivo y usuario |
| BL-1303 | AuditorÃ­a de correcciones | P0 | Propuesta, aprobaciÃ³n y aplicaciÃ³n |
| BL-1304 | AuditorÃ­a de conformidad | P0 | VersiÃ³n, trabajador, fecha y mÃ©todo |
| BL-1305 | AuditorÃ­a de tokens API | P0 | Uso, revocaciÃ³n y errores |
| BL-1306 | ProtecciÃ³n contra acceso horizontal | P0 | Tests web/API |
| BL-1307 | Seguridad NIP | P0 | Hash, reset, bloqueo |

---

## EPIC-14 â€” Testing y calidad

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-1401 | Pruebas multi-tenant | P0 | Usuario/token no accede otra empresa |
| BL-1402 | Pruebas motor legal | P0 | Diurna, nocturna, mixta, extra, descanso |
| BL-1403 | Pruebas API | P0 | Token, scopes, idempotencia, errores |
| BL-1404 | Pruebas correcciones | P0 | Nueva versiÃ³n y original conservado |
| BL-1405 | Pruebas conformidad | P0 | Firma no modifica ni se transfiere |
| BL-1406 | Checklist manual pre-piloto | P0 | RH, trabajador, nÃ³mina, jurÃ­dico |
| BL-1407 | Sin defectos bloqueantes | P0 | Sin S1 ni S2 crÃ­ticos |

---

## EPIC-15 â€” Despliegue y operaciÃ³n

| ID | Historia | Prioridad | Criterio de aceptaciÃ³n |
|---|---|---:|---|
| BL-1501 | Preparar hosting actual/cPanel si aplica | P0 | PHP, MySQL/MariaDB, document root, HTTPS |
| BL-1502 | Configurar `.env` por ambiente | P0 | Local/staging/production |
| BL-1503 | Cron scheduler | P0 | `schedule:run` ejecuta |
| BL-1504 | Queue por cron | P0 | `queue:work --stop-when-empty` |
| BL-1505 | Backups iniciales | P0 | DB y archivos respaldables |
| BL-1506 | Smoke test post-deploy | P0 | Login, evento, API, job |
| BL-1507 | Plan de nube futura | P1 | AWS u otra nube documentada y no bloqueante |

---

# 7. Orden de sprints sugerido

## Sprint 0 â€” Base tÃ©cnica

```text
BL-0001, BL-0002, BL-0003, BL-0004, BL-0005, BL-0006
BL-0101, BL-0102, BL-0103, BL-0104, BL-0107
```

Nota de avance:

```text
Sprint 0 quedo implementado y candidato a cierre.
Validaciones ejecutadas:
- php artisan migrate:fresh --seed
- php artisan test
- npm run build
```

## Sprint 1 â€” Empresas y trabajadores

```text
BL-0105
BL-0201, BL-0202, BL-0203, BL-0204
BL-0301, BL-0302, BL-0303, BL-0304, BL-0305
```

Nota de avance:

```text
Sprint 1A quedo implementado y candidato a cierre para:
- BL-0105 Selector de empresa
- BL-0201 CRUD de empresa
- BL-0202 Configuracion de empresa

Sprint 1B quedo implementado y candidato a cierre para:
- BL-0203 CRUD de centros
- BL-0204 Zona horaria por centro

Sprint 1C quedo implementado y candidato a cierre para:
- BL-0301 CRUD de trabajadores
- BL-0302 Baja no destructiva
- BL-0303 Relaciones laborales

Sprint 1D quedo implementado y candidato a cierre para:
- BL-0304 Condiciones laborales con vigencia
- BL-0305 Credenciales kiosco

No se marcaron como implementadas:
- BL-0205 Dashboard inicial

BL-0205 se mantiene como P0, pero se reubica para implementarse cuando existan datos reales de jornadas, alertas e incidencias.
No se marca como completado en Sprint 1.
```

## Sprint 2 â€” Horarios y registro

```text
BL-0401, BL-0402, BL-0403, BL-0404, BL-0405, BL-0406
BL-0501, BL-0502, BL-0503, BL-0504, BL-0505
```

Nota de avance:

```text
Sprint 2A quedo implementado y candidato a cierre para:
- BL-0401 CRUD de horarios
- BL-0402 Pausas programadas

Sprint 2B quedo implementado y candidato a cierre para:
- BL-0403 Horarios que cruzan medianoche
- BL-0404 Asignacion de horario
- BL-0406 Validar vigencias

Sprint 2C quedo implementado y candidato a cierre para:
- BL-0405 Descansos obligatorios

Sprint 2D quedo implementado y candidato a cierre para:
- BL-0501 Modelo time_events

Sprint 2E quedo implementado y candidato a cierre para:
- BL-0502 Registrar entrada/salida web
- BL-0503 Registrar pausas reales

Sprint 2F quedo implementado y candidato a cierre para:
- BL-0504 Modo kiosco operativo
- BL-0505 Captura manual justificada

Bloque 5 quedo implementado y candidato a cierre para:
- BL-0506 Anulacion logica
- BL-0507 Eventos fuera de orden/tardios

Sprint 2F validado con arquitectura aprobada con observaciones corregidas y QA aprobado con S3 no bloqueantes.

Sprint 2B agrega schedule_assignments con historial no destructivo, restrictOnDelete, reemplazo por vigencia e inactivacion sin borrado.
Sprint 2C agrega mandatory_rest_days por fecha con type/scope separados: legal_mandatory o electoral para alcance national/subnational, y company_internal solo para alcance company. Usa country_code MX durante el MVP, usa jurisdiction_code, no usa center_id, separa source_reference visible de capture_source tecnico, conserva inactivacion no destructiva y no agrega calculos de jornada.
Sprint 2D agrega time_events como modelo interno de eventos fuente.
Sprint 2E agrega registro web basico en /time-clock para entrada, salida e inicio/fin de pausa, usando time_events y sin calculos de jornada.
Bloque 5 agrega anulacion logica no destructiva, conserva `received_at` como hora de recepcion y prepara resolucion de eventos validos por relacion laboral y fecha para `work_days`.
Bloque A documenta en `docs/12-Decisiones/ADR-0004-REGLA-DE-EVIDENCIA-OPERATIVA.md` que la evidencia protegida es el resultado operativo: horarios diarios publicados, snapshots, correcciones versionadas, eventos de asistencia y futuros `work_days`, calculos, cierres, conformidad, reportes y expedientes. Catalogos, relaciones laborales, asignaciones organizacionales, perfiles y asignaciones de perfiles son datos intermedios mientras no hayan generado evidencia protegida. Cambios posteriores en esos datos no deben recalcular ni sobrescribir horarios ya publicados; para modificar una fecha publicada se usa correccion versionada. `work_days` debe nacer desde horarios publicados aunque no existan eventos y debe detectar eventos validos sin horario como jornada no programada.
Bloque B implementa correccion administrativa de relaciones laborales en trabajadores. Si la relacion no tiene horarios publicados ni eventos, centro, puesto y fecha de ingreso se corrigen sobre la misma relacion con motivo, actor, fecha y metadata de valores anteriores/nuevos. Si ya existe evidencia protegida, la relacion historica no se sobrescribe; solo puede abrirse una nueva vigencia hacia adelante cuando no corta la ultima fecha con horario publicado o asistencia. No incluye asignaciones organizacionales, asignaciones de perfiles, `work_days`, calculos ni reportes.
Bloque C inicia correccion administrativa de asignaciones organizacionales y pendientes UI. Un reemplazo retroactivo de unidad principal corrige el mismo registro con motivo y metadata; un reemplazo futuro conserva historial. La programacion diaria publicada conserva su unidad congelada. Incluye paginacion/filtros de `/time-events/manual` y reseteo completo del formulario de trabajadores. No incluye perfiles, `work_days`, calculos ni reportes.
Bloque D simplifica la regla de vigencia: la vigencia manda desde trabajador/relacion laboral y la asignacion organizacional solo segmenta. La UI de asignaciones ya no pide fechas, el reemplazo de unidad principal corrige el registro activo con motivo y metadata, los apoyos temporales salen del flujo visible y no participan en resolucion de perfil ni alcance operativo. La programacion diaria publicada conserva su snapshot de unidad. No incluye `work_days`, calculos, reportes ni API.
Bloque Work Days base implementa `work_days` como jornada operativa unica por empresa, trabajador y fecha. Genera jornadas esperadas desde programacion diaria publicada aunque no existan eventos, identifica eventos validos sin horario publicado como jornada no programada y reutiliza el resolver de eventos validos que excluye anulados. No incluye `work_day_calculations`, motor legal, horas extra, alertas, incidencias, cierres, conformidad, reportes, API ni UI de jornadas.
Bloque Work Days refresco operativo agrega configuracion de hora automatica por empresa, ejecucion manual auditada desde `/work-days`, comandos `work-days:refresh` y `work-days:auto-refresh`, y scheduler cada minuto para refrescar empresas activas por hora local. No incluye `work_day_calculations`, motor legal, horas extra, alertas, incidencias, cierres, conformidad, reportes ni API.
Bloque Work Days consulta operativa agrega `/work-days` como listado inicial de jornadas generadas, con filtros de rango, centro, horario, estado y trabajador para `owner`, `admin` y `rh`. No incluye calculos legales, horas extra, alertas, incidencias, cierres, reportes ni API.
Bloque revision de capturas manuales agrega aprobacion/rechazo de eventos `admin_manual` en `pending_review`. Aprobar cambia el evento a `valid` y refresca `work_days` de la fecha; rechazar cambia a `ignored` con motivo obligatorio. No incluye calculos legales, horas extra, alertas, incidencias, cierres, reportes ni API.
Captura justificada autorizada se ajusta en `feature/work-day-incidence-board`: las capturas `admin_manual` hechas por las claves operativas vigentes antes de A2 (`owner`, `admin`, `rh`) nacen `valid` con motivo obligatorio y metadata `auto_approved`, encolan recalculo de jornada y evitan doble aprobacion. ADR-0006 redefine el objetivo a `admin_empresa`, `rh_admin` y `rh_operativo` con alcances explicitos. Las acciones aprobar/rechazar se conservan para eventos `pending_review` existentes o futuros.
Bloque Work Day Calculations base agrega `work_day_calculations` versionado, `CalculateWorkDayAction`, `CalculateWorkDaysForDateRangeAction` y accion manual desde `/work-days`. Calcula pares entrada/salida, descuenta pausas completas, conserva snapshots y deja secuencias incompletas en revision. No incluye motor legal, horas extra, alertas, incidencias, cierres, reportes ni API.
Bloque Legal Rules versionado inicia motor legal con `legal_rules`, `legal_rule_versions`, `legal_parameters`, resolvers por fecha trabajada, seeder de reglas base y clasificacion diaria visible en `/work-days` para `work_day_calculations` activos. Clasifica como diurna, nocturna, mixta o pendiente y guarda snapshot de reglas aplicadas. No calcula todavia horas extra, dominicales, descansos obligatorios ni genera alertas.
ADR-0005 define la linea de motor legal configurable: reglas base por pais, Mexico preconfigurado, reglas minimas protegidas, parametros internos configurables por empresa, overrides versionados y snapshots historicos. L2 agrega configuracion legal por empresa y L3 inicia calculo ordinario/extra diario y semanal.
EPIC-05 queda completo a nivel web/kiosco/manual para eventos fuente, sin API de negocio.
No se implementaron alertas ni incidencias.
```

## Sprint 3 â€” API y motor legal base

```text
BL-0601, BL-0602, BL-0603, BL-0604, BL-0605, BL-0606, BL-0607
BL-0701, BL-0702, BL-0703, BL-0704
```

## Sprint 4 â€” CÃ¡lculos y alertas

```text
BL-0705, BL-0706, BL-0707, BL-0708, BL-0709, BL-0710
BL-0801, BL-0802, BL-0803, BL-0804, BL-0805, BL-0806
```

## Sprint 5 â€” Incidencias y correcciones

```text
BL-0807, BL-0808, BL-0809
BL-0901, BL-0902, BL-0903, BL-0904, BL-0905, BL-0906, BL-0907, BL-0908
BL-0506, BL-0507
```

## Sprint 5C â€” Dashboard operativo inicial

```text
BL-0205
```

Nota:

```text
BL-0205 Dashboard inicial se mantiene como P0, pero se reubica para implementacion en Sprint 5C,
despues de que existan jornadas calculadas, alertas e incidencias.
No se marca como completado en Sprint 1.
```

## Sprint 6 â€” Cierre y conformidad

```text
BL-1001, BL-1002, BL-1003, BL-1004, BL-1005
BL-1006, BL-1007, BL-1008, BL-1009
```

## Sprint 7 â€” Reportes, expedientes e integraciones

```text
BL-1101, BL-1102, BL-1103, BL-1104, BL-1105, BL-1106, BL-1107
BL-1201, BL-1202, BL-1203, BL-1206
BL-0608, BL-0609, BL-0610, BL-0611, BL-0612
```

## Sprint 8 â€” QA y despliegue

```text
BL-1301, BL-1302, BL-1303, BL-1304, BL-1305, BL-1306, BL-1307
BL-1401, BL-1402, BL-1403, BL-1404, BL-1405, BL-1406, BL-1407
BL-1501, BL-1502, BL-1503, BL-1504, BL-1505, BL-1506
```

---

# 8. Capacidades P1 no bloqueantes

| ID | Capacidad | Motivo para no bloquear |
|---|---|---|
| BL-P1-001 | Notificaciones avanzadas | MVP puede iniciar con notificaciÃ³n bÃ¡sica |
| BL-P1-002 | AdministraciÃ³n visual completa de reglas legales | Puede iniciar con seeds controlados |
| BL-P1-003 | ConfirmaciÃ³n digital por API | Portal trabajador cubre MVP |
| BL-P1-004 | Reconocimiento facial web | Riesgo tÃ©cnico/legal y de privacidad |
| BL-P1-005 | App nativa | PWA cubre MVP |
| BL-P1-006 | ClickBalance API directa | Requiere documentaciÃ³n/credenciales |
| BL-P1-007 | Dashboard ejecutivo avanzado | Reportes bÃ¡sicos cubren MVP |
| BL-P1-008 | SSO/MFA empresarial | No bloquea piloto inicial |

---

# 9. Primer bloque recomendado para Codex

## Historias

```text
BL-0001 â€” Inicializar proyecto Laravel
BL-0002 â€” Crear estructura modular app/Domains
BL-0003 â€” Configurar MySQL 8 / MariaDB compatible
BL-0004 â€” Configurar database queue
BL-0006 â€” Configurar testing base
BL-0101 â€” Login y logout
BL-0102 â€” Modelo Company
BL-0103 â€” Contexto CurrentCompany/TenantContext
BL-0104 â€” RelaciÃ³n usuario-empresa
BL-0106 â€” Roles iniciales
BL-0107 â€” Policies multi-tenant
```

## Prompt sugerido para Codex

```text
Trabaja Ãºnicamente sobre el proyecto Laravel actual.

Objetivo:
Implementar el bloque inicial del MVP de Vera Time.

Documentos base:
- docs/04-Arquitectura/ARQ-0001-ARQUITECTURA-DEL-MVP.md
- docs/05-BaseDatos/BD-0001-MODELO-DE-DATOS-MVP.md
- docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md

Historias:
- BL-0001
- BL-0002
- BL-0003
- BL-0004
- BL-0006
- BL-0101
- BL-0102
- BL-0103
- BL-0104
- BL-0106
- BL-0107

Restricciones:
- Usar MySQL 8 / MariaDB compatible.
- No usar PostgreSQL.
- No usar Redis como dependencia obligatoria.
- Mantener arquitectura domain-first.
- No poner lÃ³gica de negocio en componentes Livewire.
- Preparar Actions/Services cuando aplique.
- Implementar multi-tenant por company_id.
- Agregar pruebas bÃ¡sicas.
- No crear funcionalidades fuera del alcance.
```

---

# 9.1 Backlog propuesto WFM y cierres

| Bloque | Objetivo | Dependencias | Archivos esperados | Pruebas | Criterio de salida | Riesgo | Orden |
|---|---|---|---|---|---|---|---|
| A. Normalizacion de roles y permisos | Unificar `rh` y definir permisos base | Sprint 0/1 | `RoleSeeder`, policies, tests de roles | Pest policies | Implementado/candidato a cierre: `rh` es clave unica y `hr` no opera como alias | Romper usuarios demo | 1 |
| A2. Matriz avanzada de roles y alcances | Normalizar `admin_empresa`, `rh_admin`, `rh_operativo`, `supervisor` y retirar `owner` operativo | Modulo de usuarios / alcances | `RoleKey`, `RoleSeeder`, policies, seeders, vistas de usuarios, tests | Pest roles y acceso horizontal | Implementado/candidato a cierre: roles canonicos, seeders, usuarios, policies y pruebas actualizados | Romper permisos existentes | 1.5 |
| B. Unidades organizacionales y responsables | Crear departamentos/areas/equipos y alcances explicitos por centro completo o unidad | Centros/trabajadores | B1: migraciones, modelos, Actions, factories, tests. B2: pantallas Livewire/Volt | multi-tenant y alcance operativo | B1 y B2 implementados/candidatos a cierre: modelo, dominio y UI operativa listos | Acceso horizontal | 2 |

Nota Bloque A:

`rh` queda como clave canonica inicial de Recursos Humanos; `hr` no opera como alias. Esta regla queda superada por ADR-0006 para la siguiente normalizacion tecnica: `owner` deja de ser rol operativo diferenciado, `admin_empresa` sera el administrador general, RH se dividira en `rh_admin` y `rh_operativo`, y `supervisor` seguira sin alcance global automatico.

Nota Bloque A2:

Se implementa la matriz aceptada en ADR-0006. `admin_empresa` y `rh_admin` conservan alcance empresarial completo; `rh_operativo` y `supervisor` requieren alcance explicito por centro o unidad. No se mantienen alias historicos `owner`, `admin`, `rh` ni `hr` para operacion.

Nota Bloque B1:
Se implementa solo modelo organizacional, asignaciones de unidad y alcances operativos de dominio. No incluye pantallas, plantillas de turno, perfiles, programacion diaria ni cierres.

Nota Bloque B2:
Se implementan pantallas para areas/departamentos, asignaciones organizacionales, responsables/supervisores y "Mi alcance". La pantalla de alcances permite asignar centro completo o unidad a `rh_operativo` y `supervisor`: `rh_operativo` opera dentro de su alcance y `supervisor` consulta sin administrar. Las escrituras usan Actions de B1. No incluye plantillas de turno, perfiles, programacion diaria, incidencias, alertas, reportes, API WFM ni CSV.

Nota Bloque C:
Se implementa `/scheduling/shifts` como catalogo de plantillas de turno por empresa con segmentos diarios. No guarda timezone, `center_id`, tipo legal ni ventanas flexibles. La vista previa distingue trabajo programado bruto, descansos fijos, descansos por duracion, trabajo efectivo programado y duracion total; solo los descansos por duracion no pagados reducen el trabajo efectivo. No asigna personas, no genera calendarios y no sincroniza con el modelo legacy. `schedules`, `schedule_days`, `schedule_breaks` y `schedule_assignments` siguen disponibles temporalmente hasta Bloque J.

Nota Bloque D1:
Se implementan `schedule_profiles`, `schedule_profile_weekly_rules` y `schedule_profile_assignments` para perfiles `pattern` con `pattern_mode = weekly` y `calendar`. Los perfiles por patron semanal usan siete reglas semanales basadas en `shift_templates`; los perfiles por calendario no tienen reglas semanales. La resolucion efectiva usa prioridad relacion laboral, unidad principal activa actual, centro y empresa. `temporary_support` no cambia la herencia.

Nota Bloque D2:
Se implementan `/scheduling/profiles` y `/scheduling/profile-assignments` para administrar perfiles por patron semanal y por calendario, reglas semanales de patron, asignaciones por herencia y consulta de perfil efectivo. La navegacion queda reorganizada y oculta las entradas legacy de `/schedules` y `/schedule-assignments` sin eliminar rutas ni codigo. En este bloque no se implementan `pattern_mode = cycle`, perfiles `flexible`/`on_call`, generacion diaria, publicacion operativa, CSV/API WFM ni calculos.

Nota Bloque E1:
Se implementa dominio y pruebas para `pattern` con `pattern_mode = cycle`, perfiles `flexible` y perfiles `on_call`. Agrega reglas de ciclo, reglas flexibles, reglas bajo demanda, resolucion de regla por fecha y escenarios demo manuales. No incluye interfaz E2, programacion diaria publicada, batches, activaciones bajo demanda, CSV/API WFM ni calculos.

Nota Bloque E2:
Se implementa la interfaz de `/scheduling/profiles` para administrar Por patron con Patron semanal o Ciclo repetitivo, Por calendario, Flexible y Bajo demanda. La pantalla reutiliza Actions E1, conserva permisos existentes, exige confirmacion al cambiar de metodo y no genera programacion diaria, no publica batches, no crea activaciones, CSV/API WFM ni calculos.

Nota Bloque F1:
Se implementa el nucleo de programacion diaria sin interfaz: `schedule_batches`, `daily_schedule_assignments` y `daily_schedule_segments`. Un batch pertenece a empresa, centro y periodo; el numero de version se asigna al publicarse. Las asignaciones soportan `shift`, `rest`, `flexible`, `on_call` y `unassigned`; los segmentos congelan la estructura diaria. Se agrega snapshot JSON canonico con hash SHA-256 y resolver de programacion publicada sin fallback a perfiles ni modelo legacy. No incluye generacion desde perfiles, publicacion operativa, CSV/API WFM, `work_days`, calculos, alertas, incidencias ni reportes.

Nota Bloque F2:
Se implementa generacion de programacion diaria en borrador desde perfiles con `GenerateDraftScheduleBatchFromProfilesAction`. Los modos permitidos son `missing_only` y `refresh_profile_generated`; la regeneracion solo reemplaza dias creados por `schedule_profile_generation` y preserva manual/csv/api/system ajeno. Se generan `shift`, `rest`, `flexible`, `on_call` y `unassigned` segun perfil efectivo por fecha de perfil y unidad principal activa actual. `calendar` y relaciones sin perfil quedan como `unassigned` con motivo. No incluye interfaz, publicacion, snapshots persistidos, CSV/API WFM, `work_days`, calculos, alertas, incidencias ni reportes.

Nota Bloque F3A:
Se implementa validacion integral y publicacion atomica de batches `draft` completos con `ValidateScheduleBatchForPublicationAction`, `PublishScheduleBatchAction` y `VerifyPublishedScheduleBatchSnapshotAction`. Publica batches iniciales sin `previous_batch_id` y les asigna `version = 1` al publicar, exige cobertura completa por centro/periodo, bloquea `unassigned`, valida tipos `shift`, `rest`, `flexible` y `on_call`, detecta conflictos con programacion ya publicada por relacion laboral/fecha, persiste snapshot JSON canonico, SHA-256, `published_by` y `published_at`, y mantiene inmutabilidad desde Actions. No incluye interfaz, correcciones F4, supersede automatico, CSV/API WFM, `work_days`, calculos, alertas, incidencias ni reportes.

Nota Bloque F3B:
Se implementa `/scheduling/daily` como interfaz para administrar programacion semanal. Permite crear de 1 a 4 lotes borrador por centro y semanas naturales lunes-domingo consecutivas, generar faltantes, actualizar desde perfiles conservando cambios manuales, preparar la semana siguiente o de 1 a 4 semanas futuras en borrador desde modelos sin publicar automaticamente, clonar una semana publicada vigente a nuevo borrador o publicarla directamente, revisar calendario semanal, navegar a lotes semanales existentes sin desplazar fechas fuera del lote actual, editar dias individuales, aplicar cambio masivo basico, borrar definitivamente borradores no publicados, validar antes de publicar, publicar con confirmacion y consultar/verificar integridad de publicaciones. La UI queda compactada con filtros principales, filtro de periodo actuales/futuras por defecto con historicos consultables, filtros avanzados colapsables, tabla de lotes de una fila por lote, calendario ocultable y paneles de revision/comparacion/historial/integridad desplegables. `rh_operativo` y `supervisor` consultan segun alcance operativo vigente; no crean, generan, editan ni publican lotes. No incluye F4, versiones correctivas, importacion CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

Nota Bloque F4:
Se implementan correcciones versionadas no destructivas para programacion diaria publicada. Una publicacion vigente puede crear un borrador correctivo sin numero de version publicada y con motivo obligatorio; la correccion clona dias y segmentos congelados, permite editar mediante el calendario F3B, compara contra la version anterior y al publicarse recibe el siguiente numero de version de forma atomica dejando la anterior `superseded`. Incluye historial de versiones y seeder demo `VeraTimeCorrectedScheduleScenarioSeeder`. No incluye CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

Nota Bloque F5A:
Se implementa dominio para importacion CSV de programacion diaria a lotes `draft`. Incluye `import_batches`, `import_rows`, parser CSV version 1, validacion por fila, resolucion de trabajador/relacion/fecha/turno, preview con huella para detectar cambios posteriores, aplicacion transaccional all-or-nothing y seeder demo `VeraTimeDailyScheduleCsvScenarioSeeder`. Las filas aplicadas usan `source_type = csv` y reutilizan `ReplaceDraftDailyScheduleAssignmentAction`. No incluye UI de carga, descarga de plantilla, descarga de errores, API WFM, jobs asincronos, XLSX, `work_days`, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

Nota Bloque F5B:
Se implementa la interfaz de importacion CSV dentro de `/scheduling/daily` para lotes `draft`. La carga aparece como accion compacta del lote, la plantilla se descarga desde el panel de importacion, el archivo queda privado, se valida antes de aplicar, muestra preview paginado y permite descargar errores. La UI no muestra historial persistente ni motivo visible de importacion. No incluye XLSX, API WFM, jobs asincronos, publicacion automatica, `work_days`, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

## Plan motor legal configurable

| Bloque | Objetivo | Dependencia | Incluye | No incluye | Pruebas necesarias | Commit sugerido |
|---|---|---|---|---|---|---|
| L1. Fundacion Legal Rules | Crear reglas versionadas y clasificacion diaria inicial | Work Day Calculations base | `legal_rules`, `legal_rule_versions`, `legal_parameters`, seeder Mexico, resolvers, clasificacion visible en Jornadas | UI legal, horas extra, alertas, incidencias | Resolver por fecha, clasificacion diurna/nocturna/mixta, snapshot, multi-tenant | `legal-rules: add versioned foundation` |
| L2. Configuracion legal por empresa | Permitir consultar reglas pais y configurar parametros internos permitidos | L1 | parametros editables, reglas protegidas, UI compacta, vigencia, motivo, actor | horas extra completa, alertas, incidencias, administracion global avanzada | editar parametro permitido, bloquear valor protegido, prioridad empresa/global, permisos | `legal-rules: add company configuration` |
| L3. Ordinario y extra | Calcular ordinario y extra diario/semanal con reglas versionadas | L2 | limite diario, limite semanal, ordinary/overtime minutes, explicacion visible | alertas preventivas, incidencias, cierres | horas diarias, horas semanales, recalculo versionado, snapshot | `work-days: calculate ordinary and overtime minutes` |
| L4. Casos especiales | Calcular domingo, descanso semanal y obligatorio | L3 | dominical, descanso obligatorio, descanso semanal, reglas por pais/empresa donde aplique | cierre, conformidad, reportes finales | domingo, obligatorio, descanso insuficiente, multi-tenant | `work-days: calculate special legal cases` |

Nota L2:
La configuracion legal por empresa inicia en `feature/company-legal-configuration`. Usa `CompanyLegalParameterCatalog` para definir parametros internos permitidos y limites protegidos, agrega auditoria a `legal_parameters` y expone en `/companies` reglas base Mexico en lectura junto con parametros editables por empresa. No calcula horas extra ni crea alertas.

Nota L3:
Ordinario/extra inicia en `feature/work-day-ordinary-overtime-calculation`. `ApplyOrdinaryOvertimeForDateRangeAction` calcula minutos ordinarios y extra por limite diario y limite semanal lunes-domingo, usando reglas versionadas o parametros de empresa mas favorables. Actualiza `ordinary_minutes`, `overtime_minutes`, snapshots y explicacion en `work_day_calculations`. No crea alertas ni incidencias.
Casos especiales inicia en `feature/work-day-special-legal-cases`. `ApplySpecialLegalCasesForDateRangeAction` calcula minutos trabajados en domingo y descanso obligatorio, reutiliza `mandatory_rest_days` para alcance nacional, subnacional y empresa, y marca en snapshot si una semana natural lunes-domingo no tuvo dia de descanso. Actualiza `sunday_minutes`, `mandatory_rest_minutes`, snapshots y explicacion. No crea alertas, incidencias, cierres, conformidad, reportes ni API.
Mejora operativa de Jornadas unifica `Actualizar` y `Calcular` en `Recalcular jornadas`. La UI ejecuta `ProcessCompanyWorkDaysAction`, que refresca work_days, calcula versiones pendientes/stale, aplica motor legal disponible y exige motivo obligatorio para reproceso manual; el comando manual requiere `--reason` y el job automatico usa el mismo flujo.
Alertas preventivas base inicia en `feature/work-day-alerts-foundation`. Agrega `alert_types`, `alerts`, `AlertTypeSeeder`, evaluador idempotente desde `work_day_calculations`, cierre automatico de alertas stale por recalculo, integracion en `ProcessCompanyWorkDaysAction` y listado `/alerts`. No incluye comentarios, resolucion manual, incidencias, bloqueos de cierre, reportes ni API.
Resolucion basica de alertas inicia en `feature/work-day-alert-resolution`. Agrega dictamen desde `/work-days` al presionar el badge `Con alertas`, registra motivo obligatorio, actor y fecha UTC, y regresa la jornada a `calculated` cuando no quedan alertas abiertas. No incluye comentarios, incidencias, cierres, reportes, API ni alcance operativo avanzado para supervisores.
Recalculo puntual de jornada agrega `ProcessSingleWorkDayAction` como punto de entrada para procesar una relacion laboral y fecha: refresca/crea `work_days`, calcula, aplica motor legal disponible y evalua alertas reutilizando Actions existentes. No incluye cola, dispatch automatico, dashboard, incidencias, reportes, API ni infraestructura AWS.
Job por evento de jornada agrega `RecalculateWorkDayFromTimeEventJob` sobre `database queue`, encolado al crear `clock_out` valido, aprobar captura manual o anular evento. El Job llama `ProcessSingleWorkDayAction`, soporta salidas nocturnas procesando fecha local y fecha anterior, y conserva compatibilidad cPanel. No incluye dashboard, batch nocturno nuevo, incidencias, reportes, API ni AWS/SQS.
Operacion de cola cPanel programa desde Laravel Scheduler `queue:work database --queue=work-days,default --stop-when-empty --max-time=50 --tries=3` cada minuto con `withoutOverlapping`; el cron unico de cPanel debe ejecutar `schedule:run`. No requiere worker permanente, Redis, SQS ni Horizon.
Jornadas como tablero de incidencias inicia en `feature/work-day-incidence-board`. Mantiene `alerts` como tabla tecnica de trazabilidad, oculta `/alerts` del menu operativo, agrega `scheduled_absence` para faltas programadas sin eventos y simplifica `/work-days` para mostrar datos basicos, incidencia, dictamen y accion; el detalle legal/eventos/trazabilidad queda en el side panel. No crea tabla separada de incidencias, adjuntos, reportes, cierres ni API.

| C. Plantillas de turno | Reemplazar horario simple por turnos reutilizables | B opcional | shift_templates, segmentos, UI | CRUD, cruces medianoche | Implementado/candidato a cierre: catalogo disponible sin calcular jornada | Mezclar flexible con turno rigido | 3 |
| D. Modelos de horario weekly y calendar | Crear modelos, reglas semanales y aplicacion con herencia | C | `schedule_profiles`, `pattern_mode`, weekly rules, assignments | dominio, resolucion y UI | D1/D2 implementado/candidato a cierre: perfiles tecnicos conservados; UI visible reorganizada como modelos de horario y aplicacion de modelos; Bloques 3/4 aclaran filtros por camino operativo y fecha Dia 1 para ciclos | Publicacion prematura | 4 |
| E. Perfiles cycle, flexible y on_call | Agregar ciclos, ventanas/minutos y disponibilidad bajo demanda | D | cycle rules, flexible rules, on_call rules, UI perfiles | dominio, resolucion y UI | E1/E2 implementados/candidatos a cierre: dominio e interfaz cycle/flexible/on_call disponibles sin programacion diaria | Complejidad UX | 5 |
| F. Calendario diario y publicacion | Crear batches obligatorios por centro y daily schedules publicados | D/E | `schedule_batches`, `daily_schedule_assignments`, `daily_schedule_segments` | publicacion no destructiva | F1/F2/F3A/F3B/F4 implementados/candidatos a cierre: nucleo diario, generacion draft, publicacion atomica, interfaz y correcciones versionadas | Versionado incorrecto | 6 |
| G. Importacion CSV/XLSX | Importar programacion por calendario a draft | F | import batches/rows, parser, validadores | errores por fila | F5B implementado/candidato a cierre: CSV web disponible para lotes draft; sin API, sin jobs y sin XLSX; importacion nunca publica directo | Calidad de archivos | 7 |
| H. Periodos de asistencia por alcance | Generar paquetes de asistencia por centro o unidades y rango libre | Work days / alertas base | `attendance_periods`, `attendance_period_scopes`, `attendance_incidents`, UI | crear, validar, cerrar, reportar periodo, registrar ausencias operativas y exportar CSV/XLSX | H1/H2/H3/H4 implementados/candidatos a cierre: API administrativa de listado y detalle disponible con `work-days:read`; no calcula nómina | Confundir periodo con nómina | 8 |
| I. Exportación/API del periodo | Consolidar entrega externa del periodo | H | exportación controlada, contrato API | CSV/XLSX/API | Descarga web y API CSV/XLSX implementadas: `GET /api/v1/time/attendance-periods/{periodId}/payroll-csv` y `/payroll-xlsx`; requieren `exports:read`, Policy y período cerrado; no hay entrega externa directa | Formato requerido por nómina externa | 9 |
| J. Eliminacion del modelo legacy | Retirar schedules legacy | F estable | borrar/convertir legacy | regresion Sprint 2 | No queda dependencia activa | Migracion incompleta | 10 |

## 9.2 Modelo legacy

| Elemento | Clasificacion | Tratamiento |
|---|---|---|
| `schedules` | Reemplazar | Migrar a `shift_templates` y `schedule_profiles`. |
| `schedule_days` | Reemplazar | Migrar a `schedule_profile_weekly_rules`. |
| `schedule_breaks` | Transformar | Migrar a `shift_template_segments`. |
| `schedule_assignments` | Reemplazar | Migrar a `schedule_profile_assignments` y dias publicados. |
| `labor_conditions.schedule_id` | Transformar | No debe ser fuente operativa; conservar solo referencia historica si aplica. |
| `ResolveScheduleForWorkerDateAction` | Reemplazar | Usar `ResolveDailyScheduleForRelationshipDateAction`. |
| Vistas `/schedules` y `/schedule-assignments` | Transformar | Sustituir por turnos, perfiles, batches y publicacion. |
| Factories Sprint 2A/2B | Transformar | Crear factories nuevas WFM. |
| Seeders demo | Transformar | Generar turnos, perfiles, batches y dias publicados demo. |
| Pruebas Sprint 2A/2B | Transformar | Mantener cobertura conceptual, reescribir contra modelo WFM. |

# 10. Riesgos del backlog

| Riesgo | MitigaciÃ³n |
|---|---|
| Querer construir todo a la vez | Seguir sprints |
| Multi-tenant dÃ©bil | Tests desde Sprint 0 |
| LÃ³gica en Livewire | Actions/Services obligatorios |
| Motor legal acoplado a UI | Servicios puros |
| API duplicada | Reutilizar dominio |
| Reportes pesados en request | Jobs |
| Hosting actual limita procesos | Database queue + cron |
| ClickBalance bloquea avance | CSV primero |
| BiometrÃ­a retrasa MVP | Mantener P1/OUT |
| Correcciones destructivas | Versionamiento obligatorio |

---

# 11. Criterios de aceptaciÃ³n del backlog

El backlog inicial queda aprobado cuando:

1. Cubre mÃ³dulos P0.
2. Separa P0, P1 y fuera del MVP.
3. Incluye API-first bidireccional.
4. Incluye MySQL 8 / MariaDB compatible y hosting actual como inicio.
5. Incluye evoluciÃ³n futura a AWS u otra nube.
6. Incluye pruebas y despliegue.
7. Tiene primer bloque listo para Codex.
8. No mete app nativa ni biometrÃ­a como bloqueo.
9. Permite llegar a piloto con evidencia, alertas y conformidad digital.

---

# 12. Siguiente paso

DespuÃ©s de aprobar este backlog, el siguiente paso ya es desarrollo.

El primer bloque a ejecutar serÃ¡:

```text
Sprint 0 â€” Base tÃ©cnica
```

No se recomienda crear mÃ¡s documentos grandes antes de iniciar cÃ³digo, salvo que aparezca una decisiÃ³n tÃ©cnica nueva que pueda bloquear el avance.

## Bloque Admin A1 - alta guiada de empresa y administrador principal

Estado: implementado / candidato a cierre.

Historias:

| ID | Historia | Prioridad | Criterio de aceptacion |
|---|---|---:|---|
| BL-ADM-A1-001 | Alta guiada de empresa | P0 | `super_admin` crea empresa desde flujo guiado sin crear registros parciales |
| BL-ADM-A1-002 | Crear administrador principal | P0 | El flujo crea o asigna usuario con rol `admin_empresa` activo en la empresa creada |
| BL-ADM-A1-003 | Configuracion inicial de empresa | P0 | Se crea `company_settings` base y estado inicial de empresa |
| BL-ADM-A1-004 | Validaciones y seguridad | P0 | Correo unico, empresa valida, rol canonico y transaccion atomica |
| BL-ADM-A1-005 | Pruebas de plataforma | P0 | Super admin puede crear tenant; usuarios no autorizados no pueden; no hay datos parciales |

Nota de cierre A1/A2 parcial: se implemento alta guiada desde Empresas para crear cuenta cliente, empresa, configuracion inicial y administrador principal `admin_empresa` sin asociar al `super_admin` como miembro operativo. A2 queda parcial: existe cuenta cliente monoempresa/multiempresa, pero no cobro, facturacion ni limites automaticos.

Fuera de A1:

- cobro automatico;
- facturacion;
- portal publico de compra;
- suscripcion multiempresa operativa;
- soporte auditado avanzado.

## Bloque Admin A2 - cuenta cliente, planes y suscripciones

Estado: implementado / candidato a cierre.

Historias propuestas:

| ID | Historia | Prioridad | Criterio de aceptacion |
|---|---|---:|---|
| BL-ADM-A2-001 | Cuenta cliente comercial | P1 | Implementado/candidato a cierre: `customer_accounts` separa cuenta cliente de empresa operativa |
| BL-ADM-A2-002 | Productos VERA | P1 | Implementado: `products` con producto inicial `time` y claves preparadas para `payroll` y `rh` |
| BL-ADM-A2-003 | Suscripcion simple | P1 | Implementado parcial: cuenta cliente con una empresa operativa sin cobro automatico |
| BL-ADM-A2-004 | Suscripcion multiempresa especial | P2 | Preparado: cuenta cliente puede agrupar varias empresas sin mezclar tenants |
| BL-ADM-A2-005 | Estado comercial | P1 | Piloto, activa, suspendida o cancelada sin borrar historial |
| BL-ADM-A2-006 | Productos contratados por cuenta | P1 | Implementado: `customer_account_products` asigna VERA Time activo a todas las cuentas existentes |
| BL-ADM-A2-007 | Verificacion `hasProduct` | P1 | Implementado: `ProductAccess` valida acceso a `time` desde empresa -> cuenta cliente -> producto contratado |
| BL-ADM-A2-008 | Middleware `product:time` | P1 | Implementado: rutas operativas de Time se bloquean por producto sin duplicar logica |
| BL-ADM-A2-009 | Jobs respetan producto contratado | P1 | Implementado: jobs/comandos de Time procesan `trial/active/past_due` y no procesan `suspended/cancelled` |
| BL-ADM-A2-010 | Administracion de productos contratados | P1 | Implementado: `super_admin` administra estado y fechas de producto desde Cuentas cliente |

Decision:

- El flujo comercial principal del MVP sera una cuenta con una empresa.
- Multiempresa queda reservado para despachos contables, grupos o casos especiales.
- La suspension formal de cuenta cliente, empresa, usuario y membresia queda en Admin A3.
- La capa minima de productos contratados queda definida por ADR-0007.
- `customer_account_products` representa estado actual del producto, con unique `customer_account_id + product_id`.
- Reactivar un producto actualiza la misma fila; historial comercial detallado queda para `customer_account_product_events` futuro.
- No se agrega `company_id` a `customer_account_products`; si se requiere licenciamiento por empresa se agregara `company_product_entitlements` futuro.
- `trial`, `active` y `past_due` permiten jobs de Time; `suspended` y `cancelled` los detienen.

Implementacion A2.1:

- Migracion de `products` y `customer_account_products`.
- Seeder idempotente de productos VERA.
- Backfill de VERA Time activo para cuentas existentes.
- Nuevas cuentas cliente reciben VERA Time activo en los flujos actuales.
- Cuentas cliente muestra productos contratados y permite cambiar estado/fechas del producto.
- Middleware `product:time` protege rutas operativas.
- `work-days:refresh`, `work-days:auto-refresh` y jobs de recalculo por evento respetan producto operativo.

Fuera de A2 actual:

- cobro automatico;
- facturacion;
- precios finales;
- planes completos;
- portal publico de compra;
- permisos por producto a nivel usuario;
- historial comercial detallado.

## Bloque Admin A3 - suspensiones y acceso

Estado: implementado / candidato a cierre.

| ID | Historia | Prioridad | Criterio de aceptacion |
|---|---|---:|---|
| BL-ADM-A3-001 | Suspender cuenta cliente | P1 | `super_admin` suspende/reactiva cuenta y bloquea todas sus empresas operativas |
| BL-ADM-A3-002 | Suspender empresa | P1 | Estado de empresa bloquea solo esa empresa sin cambiar la cuenta cliente |
| BL-ADM-A3-003 | Suspender usuario global | P1 | Usuario inactivo no puede ingresar ni operar en ninguna empresa |
| BL-ADM-A3-004 | Suspender membresia | P1 | Membresia inactiva bloquea acceso solo a una empresa |
| BL-ADM-A3-005 | Pruebas de acceso | P1 | Se validan bloqueo por cuenta, empresa, usuario y membresia |

Regla de acceso A3:

Un usuario puede operar una empresa solo si la cuenta cliente, la empresa, el usuario, la membresia y el rol/alcance aplicable estan activos.

## Bloque Admin A4 - usuarios y membresias

Estado: implementado / candidato a cierre.

| ID | Historia | Prioridad | Criterio de aceptacion |
|---|---|---:|---|
| BL-ADM-A4-001 | Separar estado global y membresia | P1 | Usuarios muestra estado global y acceso a empresa como conceptos distintos |
| BL-ADM-A4-002 | Suspender usuario global | P1 | Solo `super_admin` puede cambiar `users.status` |
| BL-ADM-A4-003 | Suspender membresia | P1 | Admin/RH pueden inactivar acceso solo dentro de su empresa segun permisos |
| BL-ADM-A4-004 | Usuario multiempresa controlado | P1 | Una membresia inactiva no bloquea otras empresas activas |
| BL-ADM-A4-005 | Pruebas de usuarios | P1 | Se validan permisos, estado global y membresia por empresa |

Pendiente posterior:

- vista global de identidades si se requiere gestionar usuarios sin entrar a una empresa activa;
- invitaciones por correo;
- recuperacion operativa de accesos multiempresa desde soporte.

## Decision MVP - identidad unica y marcaje por kiosco

Se incorpora al MVP una identidad humana unica: la misma cuenta de usuario podra acceder al portal web y al cliente movil responsive/PWA. Cuando el usuario represente a una persona trabajadora, se creara una vinculacion explicita y acotada por empresa entre `users` y `workers`.

La credencial de kiosco seguira siendo una credencial de marcaje separada, asociada al trabajador y vinculable a su cuenta humana. Usara codigo/NIP hasheado, con alta, bloqueo, restablecimiento y revocacion propios. La contrasena principal no se captura ni se reutiliza como NIP en una terminal compartida.

Incremento de seguridad de kiosco implementado: una terminal nueva solicita acceso con codigo publico de empresa y clave de solicitud; un administrador acepta o rechaza desde su bandeja. La clave nunca activa por si sola ni entrega una credencial. Solo el navegador solicitante puede reclamar una aprobacion de un solo uso y entonces recibe el secreto de terminal hasheado y revocable. La terminal puede tener centro opcional y eliminacion segura. Ver `ADR-0009-TERMINALES-AUTORIZADAS-DE-KIOSCO.md`.

Historias que se agregan al alcance P0:

| ID | Historia | Criterio de aceptacion |
|---|---|---|
| BL-0108 | Vinculo usuario-trabajador | La cuenta se vincula explícitamente y sólo al trabajador de la empresa autorizada; no se infiere por datos coincidentes ni otorga acceso horizontal. |
| BL-0613 | Acceso personal web/movil por API | Token personal con usuario y empresa resueltos por servidor; `self:read` consulta y `self:write` permite marcaje individual y sincronización de hasta 25 eventos para el trabajador vinculado activo. Incluye recuperación de contraseña sin enumeración de cuentas; no acepta contexto manipulable de empresa/trabajador/centro, exige `Idempotency-Key` o `client_event_id` según el canal y `DELETE /api/v1/time/me/access-token` revoca sólo el token actual. |
| BL-1408 | Pruebas de identidad por canal | Se prueban vínculo, estados, revocación, scopes, recuperación sin enumeración e idempotencia de portal, token personal y kiosco; la API personal no devuelve información ajena ni permite sustituir contexto aunque reciba identificadores externos. |
| BL-0614 | Politica efectiva de seguridad movil | El cliente obtiene solo la politica, vinculo y referencia de tiempo aplicables a su token; no puede seleccionar empresa, trabajador o centro. |
| BL-0615 | Vinculo supervisado de dispositivo | RH autoriza un desafio de un solo uso; el dispositivo prueba posesion de su clave y el vinculo puede revocarse sin borrar evidencia. |
| BL-0616 | Evidencia firmada de marcaje | Los marcajes sujetos a politica preservan version, vinculo, ubicacion y firma; no almacenan huella, rostro, IMEI ni plantilla biometrica. |
| BL-0617 | Validacion y conciliacion de seguridad | Sync conserva idempotencia, rechazos y pendientes; UUID con payload distinto es conflicto y no se pierden eventos por fallas de red. |
| BL-0618 | Pruebas de seguridad y privacidad movil | Se prueban aislamiento por empresa, revocacion, firma, politica vencida, ubicacion no verificable, offline y ausencia de coordenadas en logs. |

Estado de avance del incremento:

- BL-0606 amplía su contrato administrativo con `POST /api/v1/time/time-events/{eventId}/void`, `/approve` y `/reject`: exigen `time-events:write`, token y tenant válidos y rol administrativo. La anulación exige motivo, es lógica y encola el recálculo cuando hay relación laboral; aprobar/rechazar sólo opera capturas `admin_manual` en `pending_review`, el rechazo exige motivo y deja el evento en `ignored`. Un evento de otra empresa responde `404` y las tres operaciones preservan evidencia.
- BL-0610 está implementada en su contrato inicial: `GET /api/v1/time/attendance-incidents` exige `incidents:read` y lista por empresa del token con filtros de trabajador, estado, tipo y fechas; `GET /api/v1/time/attendance-incidents/{incidentId}` exige el mismo scope, busca sólo dentro del tenant, pasa por la Policy `view`, responde `404` ante un identificador ajeno y devuelve `AttendanceIncidentResource` con `trace_id` sin mutar datos. `POST /api/v1/time/attendance-incidents` exige `incidents:write` y reutiliza `CreateAttendanceIncidentAction`. `POST /api/v1/time/attendance-incidents/{incidentId}/cancel` reutiliza `CancelAttendanceIncidentAction`, exige motivo y realiza una cancelación lógica que conserva la incidencia, actor, fecha UTC y motivo. Las cuatro rutas exigen token, tenant, producto y rol administrativo válidos. No incluye por ahora comentarios, adjuntos ni correcciones por API.
- BL-0108 está implementada en `user_worker_links`: el vínculo se gestiona desde `/time/users`, se limita por empresa y puede revocarse sin borrar historial laboral.
- BL-0613 está implementada para consulta y marcaje personal: token Sanctum con `company_id`, emisión desde sesión web o `POST /api/v1/time/auth/login` con vínculo activo y scopes `self:read` + `self:write`; listados y detalle propios de eventos y jornadas bajo `/api/v1/time/me`, más marcaje individual y `POST /api/v1/time/me/time-events/sync` de hasta 25 eventos. El marcaje individual exige `Idempotency-Key`; la sincronización usa `client_event_id` como idempotencia por evento y reporta `accepted`, `already_registered` o `rejected`. `POST /api/v1/time/auth/forgot-password` responde de forma uniforme para no enumerar cuentas. El canal deriva empresa del token y trabajador del vínculo, fija `source` a `pwa` y el usuario fuente en servidor, y rechaza con `422` identificadores o campos que intentarían sustituir el contexto; no implementa biometría, app nativa ni geolocalización.
- BL-1408 permanece **en revisión**: existen pruebas de aislamiento, revocación, scopes, contexto prohibido, recuperación sin enumeración e idempotencia individual y por lote del canal personal; faltan cerrar la matriz de estados de cuenta, membresía, producto y la verificación manual de los canales portal/PWA y kiosco.

La aplicacion nativa iOS/Android continua como proyecto independiente. El contrato API y el portal responsive/PWA son la base movil inicial.

### Bloque P0 adicional - Seguridad de marcaje movil

BL-0614 a BL-0618 se incorporan al MVP por decision expresa de producto. El contrato aprobado esta en `API-0002-PROPUESTA-SEGURIDAD-MARCAJE.md` y la decision arquitectonica en `ADR-0010-SEGURIDAD-DE-MARCAJE-MOVIL.md`.

Estado actualizado: BL-0614 esta implementada en `feature/mobile-marking-security`: `GET /api/v1/time/me/marking-security` resuelve politica activa por unidad organizacional, centro o empresa desde el token, rechaza sustitucion de contexto, genera referencia de tiempo persistente y conserva el marcaje actual sin restricciones hasta BL-0615/BL-0616. BL-0615 a BL-0618 continuan pendientes.

La biometria se limita al desbloqueo local de una clave criptografica y no implica capturar, transmitir o almacenar datos biometricos. Esta decision sustituye, solo para este bloque de seguridad movil, la nota previa que excluia geolocalizacion y biometria del canal personal. El reconocimiento facial y el almacenamiento de biometria continúan fuera del MVP.
