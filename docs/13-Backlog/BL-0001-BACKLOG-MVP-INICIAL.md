---
id: BL-0001
title: Backlog inicial del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
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

# BL-0001 — Backlog inicial del MVP

## 1. Objetivo

Convertir la documentación aprobada de Vera Time en un backlog inicial accionable para iniciar desarrollo con Codex o con equipo técnico.

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

| Regla | Decisión |
|---|---|
| Arquitectura | Monolito modular Laravel |
| Enfoque | Domain-first + API-first pragmático y bidireccional |
| Base inicial | MySQL 8 / MariaDB compatible |
| Hosting inicial | Hosting actual o cPanel si aplica |
| Colas iniciales | Database queue |
| Frontend | Livewire + Blade + Tailwind |
| API | `/api/v1` con tokens por empresa |
| Multi-tenant | `company_id` obligatorio en entidades operativas |
| Biometría | Fuera del P0 |
| App nativa | Fuera del MVP |
| ClickBalance | Archivo primero; API P1 condicionada |
| AWS | Evolución posterior al MVP/piloto, no dependencia inicial |

---

## 3. Prioridades

| Prioridad | Significado |
|---|---|
| P0 | Obligatorio para MVP funcional |
| P1 | Alto valor, pero no bloquea MVP |
| P2 | Fase posterior |
| OUT | Fuera del MVP |

---

## 4. Definición de Done

Una historia se considera terminada cuando:

- Está implementada.
- Tiene validaciones.
- Respeta multi-tenant.
- Usa Actions/Services si tiene lógica de negocio.
- No duplica lógica entre Livewire/API/CSV/jobs.
- Tiene pruebas mínimas.
- Tiene auditoría si aplica.
- No destruye historial.
- Funciona en MySQL 8 / MariaDB compatible.
- No introduce dependencia obligatoria de Redis, PostgreSQL, S3 o AWS.

---

# 5. Épicas del MVP

| Código | Épica | Prioridad |
|---|---|---|
| EPIC-00 | Preparación técnica | P0 |
| EPIC-01 | Autenticación, roles y multi-tenant | P0 |
| EPIC-02 | Empresas, centros y configuración | P0 |
| EPIC-03 | Trabajadores y relaciones laborales | P0 |
| EPIC-04 | Horarios, turnos y vigencias | P0 |
| EPIC-05 | Registro electrónico y kiosco | P0 |
| EPIC-06 | API base e interoperabilidad | P0 |
| EPIC-07 | Motor legal y cálculos | P0 |
| EPIC-08 | Alertas preventivas | P0 |
| EPIC-09 | Incidencias y correcciones | P0 |
| EPIC-10 | Cierre y conformidad digital | P0 |
| EPIC-11 | Reportes, expedientes y exportaciones | P0 |
| EPIC-12 | Importaciones e integraciones | P0/P1 |
| EPIC-13 | Auditoría y seguridad | P0 |
| EPIC-14 | Testing y calidad | P0 |
| EPIC-15 | Despliegue y operación | P0 |
| EPIC-16 | Capacidades no bloqueantes | P1 |

---

# 6. Backlog P0 por épica

## EPIC-00 — Preparación técnica

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0001 | Inicializar proyecto Laravel | P0 | Laravel, Livewire, Tailwind, Pest y MySQL 8 / MariaDB compatible funcionando |
| BL-0002 | Crear estructura modular `app/Domains` | P0 | Dominios base creados sin lógica duplicada |
| BL-0003 | Configurar MySQL 8 / MariaDB compatible | P0 | Migraciones corren en MySQL/MariaDB, sin dependencia de PostgreSQL |
| BL-0004 | Configurar database queue | P0 | Tablas `jobs` y `failed_jobs`, job de prueba ejecutado |
| BL-0005 | Definir IDs del dominio | P0 | ULID o estrategia aprobada aplicada consistentemente |
| BL-0006 | Configurar testing base | P0 | `php artisan test` o Pest corriendo |

---

## EPIC-01 — Autenticación, roles y multi-tenant

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0101 | Login y logout | P0 | Usuarios activos pueden entrar; inactivos no |
| BL-0102 | Modelo `Company` | P0 | Empresas creadas con estado y zona horaria |
| BL-0103 | Contexto `CurrentCompany/TenantContext` | P0 | Todas las consultas operativas usan empresa activa |
| BL-0104 | Relación usuario-empresa | P0 | Usuario puede pertenecer a una o varias empresas |
| BL-0105 | Selector de empresa | P0 | Solo muestra empresas autorizadas |
| BL-0106 | Roles iniciales | P0 | Roles base creados por seed |
| BL-0107 | Policies multi-tenant | P0 | Usuario A no accede a datos de Empresa B |

---

## EPIC-02 — Empresas, centros y configuración

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0201 | CRUD de empresa | P0 | Crear/editar empresa y estado |
| BL-0202 | Configuración de empresa | P0 | Periodo, kiosco, conformidad y correcciones configurables |
| BL-0203 | CRUD de centros | P0 | Centros con código único por empresa |
| BL-0204 | Zona horaria por centro | P0 | Eventos usan zona horaria correcta |
| BL-0205 | Dashboard inicial | P0 | Muestra trabajadores, jornadas, alertas e incidencias básicas |

---

## EPIC-03 — Trabajadores y relaciones laborales

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0301 | CRUD de trabajadores | P0 | Código único por empresa, estado y datos básicos |
| BL-0302 | Baja no destructiva | P0 | Baja no elimina historial |
| BL-0303 | Relaciones laborales | P0 | Centro, puesto, fecha ingreso/baja e historial |
| BL-0304 | Condiciones laborales con vigencia | P0 | No permite solapamientos activos |
| BL-0305 | Credenciales kiosco | P0 | Código/NIP con NIP hasheado |
| BL-0306 | Importar trabajadores CSV | P0 | Plantilla, validación por fila y errores descargables |
| BL-0307 | Detalle de trabajador | P0 | Resumen, jornadas, alertas, incidencias y reportes |

---

## EPIC-04 — Horarios, turnos y vigencias

Nota de consolidacion WFM:

EPIC-04 debe evolucionar hacia programacion diaria publicada como fuente de verdad. Las historias actuales BL-0401 a BL-0406 quedan como base legacy ya implementada para Sprint 2, pero el desarrollo futuro debe dividirse en los bloques WFM definidos al final de este documento.

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0401 | CRUD de horarios | P0 | Horario con días, entrada, salida y tipo legal |
| BL-0402 | Pausas programadas | P0 | Pausa computable/no computable |
| BL-0403 | Horarios que cruzan medianoche | P0 | Turno nocturno 22:00-06:00 funciona |
| BL-0404 | Asignación de horario | P0 | Asignación con fecha efectiva |
| BL-0405 | Descansos obligatorios | P0 | Catalogo por fecha, tipo y alcance |
| BL-0406 | Validar vigencias | P0 | Cambios futuros no alteran jornadas pasadas |

---

## EPIC-05 — Registro electrónico y kiosco

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0501 | Modelo `time_events` | P0 | Guarda fuente, hora local, UTC, zona horaria y estado |
| BL-0502 | Registrar entrada/salida web | P0 | Crea eventos válidos y auditable |
| BL-0503 | Registrar pausas | P0 | Inicio/fin de pausa válidos |
| BL-0504 | Modo kiosco código/NIP | P0 | Trabajador registra con código y NIP |
| BL-0505 | Captura manual justificada | P0 | Requiere motivo y genera auditoría |
| BL-0506 | Anulación lógica | P0 | No elimina evento original |
| BL-0507 | Eventos fuera de orden/tardíos | P0 | Conserva hora del hecho y recepción |

---

## EPIC-06 — API base e interoperabilidad

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0601 | Configurar Sanctum | P0 | Tokens API funcionando |
| BL-0602 | Token ligado a empresa | P0 | Token resuelve `company_id` |
| BL-0603 | Scopes API | P0 | Token sin permiso recibe 403 |
| BL-0604 | Endpoint trabajadores | P0 | Crear/consultar trabajadores por API |
| BL-0605 | Endpoint relaciones laborales | P0 | Crear/consultar relaciones por API |
| BL-0606 | Endpoint eventos | P0 | Registrar evento por API |
| BL-0607 | Idempotencia API | P0 | No duplica eventos repetidos |
| BL-0608 | Endpoint jornadas | P0 | Consultar jornadas calculadas |
| BL-0609 | Endpoint alertas | P0 | Consultar alertas |
| BL-0610 | Endpoint incidencias | P0 | Crear/consultar incidencias |
| BL-0611 | Error estándar API | P0 | Respuestas con `message`, `errors`, `trace_id` |
| BL-0612 | Logs de integración | P0 | Registra operación, estado y trace ID |

---

## EPIC-07 — Motor legal y cálculos

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0701 | Catálogo de reglas legales | P0 | Reglas y versiones con vigencia |
| BL-0702 | Resolver regla vigente por fecha | P0 | Cálculo usa versión correcta |
| BL-0703 | Modelo `work_days` | P0 | Jornada única por trabajador/fecha |
| BL-0704 | Modelo `work_day_calculations` | P0 | Cálculos versionados |
| BL-0705 | Reconstruir jornada | P0 | Usa eventos válidos |
| BL-0706 | Clasificar jornada | P0 | Diurna, nocturna, mixta o pendiente |
| BL-0707 | Calcular ordinario/extra | P0 | Minutos ordinarios y extra correctos |
| BL-0708 | Calcular descansos/domingo/obligatorios | P0 | Detecta casos especiales |
| BL-0709 | Explicación del cálculo | P0 | Muestra reglas, eventos y resultado |
| BL-0710 | Snapshot de reglas | P0 | Cálculo histórico no cambia por regla futura |

---

## EPIC-08 — Alertas preventivas

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0801 | Catálogo de tipos de alerta | P0 | Tipos mínimos creados |
| BL-0802 | Evaluador de alertas | P0 | Genera alertas desde cálculo |
| BL-0803 | Alertas por jornada incompleta | P0 | Entrada/salida faltante genera alerta |
| BL-0804 | Alertas por límites excedidos | P0 | Jornada diaria/semanal y 12h |
| BL-0805 | Alertas por descanso insuficiente | P0 | Antes del cierre |
| BL-0806 | Evitar duplicados | P0 | Recalculo no duplica sin control |
| BL-0807 | Listado de alertas | P0 | Filtros por estado, severidad y centro |
| BL-0808 | Detalle y resolución | P0 | Comentarios, evidencia y estado final |
| BL-0809 | Bloqueo por alerta crítica | P0 | Cierre definitivo bloqueado |

---

## EPIC-09 — Incidencias y correcciones

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-0901 | Modelo de incidencias | P0 | Incidencia con estado, tipo y trabajador |
| BL-0902 | Crear incidencia manual | P0 | Desde trabajador/jornada |
| BL-0903 | Crear incidencia desde alerta | P0 | Alerta queda vinculada |
| BL-0904 | Comentarios y evidencias | P0 | Adjuntos y visibilidad |
| BL-0905 | Proponer corrección | P0 | Valor original/propuesto y motivo |
| BL-0906 | Aprobar corrección | P0 | Conserva original y recalcula |
| BL-0907 | Rechazar corrección | P0 | No cambia cálculo activo |
| BL-0908 | Estado de controversia | P0 | Conserva desacuerdo y evidencia |

---

## EPIC-10 — Cierre y conformidad digital

Nota de consolidacion:

El cierre deja de ser una configuracion unica por empresa. Se agregaran perfiles multiples con prioridad: relacion laboral, unidad organizacional, centro, empresa.

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-1001 | Modelo de cierres | P0 | Periodos semanales/quincenales/mensuales |
| BL-1002 | Crear cierre | P0 | Selecciona periodo y trabajadores |
| BL-1003 | Generar reportes individuales | P0 | Un reporte por trabajador |
| BL-1004 | Versionar reporte | P0 | Reporte tiene versión y hash |
| BL-1005 | Portal trabajador reporte | P0 | Trabajador ve solo su reporte |
| BL-1006 | Confirmar conforme | P0 | Guarda versión, texto, fecha y método |
| BL-1007 | Marcar no conforme | P0 | Crea incidencia |
| BL-1008 | Pendiente no es conforme | P0 | Silencio no confirma |
| BL-1009 | Nueva versión por corrección | P0 | Firma anterior no aplica a versión nueva |

---

## EPIC-11 — Reportes, expedientes y exportaciones

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-1101 | Centro de reportes | P0 | Diario, semanal, periodo, alertas, incidencias |
| BL-1102 | Exportar PDF | P0 | Archivo guardado con hash |
| BL-1103 | Exportar CSV/XLSX | P0 | Descarga con permisos |
| BL-1104 | Exportación prenómina | P0/P1 alto | Ordinarias, extras, domingo, descanso e incidencias |
| BL-1105 | Expediente por trabajador/periodo | P0 | Solo alcance solicitado |
| BL-1106 | Manifiesto de expediente | P0 | Lista de elementos y hash |
| BL-1107 | Auditoría de descarga | P0 | Registra generación/descarga |

---

## EPIC-12 — Importaciones e integraciones

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-1201 | Importar eventos CSV | P0 | Validación por fila |
| BL-1202 | Resultado de importación | P0 | Correctas, errores, saltadas |
| BL-1203 | Idempotencia en CSV | P0 | No duplica por external_id |
| BL-1204 | Exportación compatible ClickBalance | P1 alto | Formato cuando se confirme |
| BL-1205 | API directa ClickBalance | P1 condicionado | Requiere documentación y credenciales |
| BL-1206 | Logs de integración | P0 | Operación, estado, error, trace ID |

---

## EPIC-13 — Auditoría y seguridad

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-1301 | Audit log transversal | P0 | Actor, acción, entidad, antes/después |
| BL-1302 | Auditoría de eventos manuales | P0 | Motivo y usuario |
| BL-1303 | Auditoría de correcciones | P0 | Propuesta, aprobación y aplicación |
| BL-1304 | Auditoría de conformidad | P0 | Versión, trabajador, fecha y método |
| BL-1305 | Auditoría de tokens API | P0 | Uso, revocación y errores |
| BL-1306 | Protección contra acceso horizontal | P0 | Tests web/API |
| BL-1307 | Seguridad NIP | P0 | Hash, reset, bloqueo |

---

## EPIC-14 — Testing y calidad

| ID | Historia | Prioridad | Criterio de aceptación |
|---|---|---:|---|
| BL-1401 | Pruebas multi-tenant | P0 | Usuario/token no accede otra empresa |
| BL-1402 | Pruebas motor legal | P0 | Diurna, nocturna, mixta, extra, descanso |
| BL-1403 | Pruebas API | P0 | Token, scopes, idempotencia, errores |
| BL-1404 | Pruebas correcciones | P0 | Nueva versión y original conservado |
| BL-1405 | Pruebas conformidad | P0 | Firma no modifica ni se transfiere |
| BL-1406 | Checklist manual pre-piloto | P0 | RH, trabajador, nómina, jurídico |
| BL-1407 | Sin defectos bloqueantes | P0 | Sin S1 ni S2 críticos |

---

## EPIC-15 — Despliegue y operación

| ID | Historia | Prioridad | Criterio de aceptación |
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

## Sprint 0 — Base técnica

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

## Sprint 1 — Empresas y trabajadores

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

## Sprint 2 — Horarios y registro

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

Sprint 2F validado con arquitectura aprobada con observaciones corregidas y QA aprobado con S3 no bloqueantes.

No se marcaron como implementadas:
- BL-0506 Anulacion logica
- BL-0507 Eventos fuera de orden/tardios

Sprint 2B agrega schedule_assignments con historial no destructivo, restrictOnDelete, reemplazo por vigencia e inactivacion sin borrado.
Sprint 2C agrega mandatory_rest_days por fecha con type/scope separados: legal_mandatory o electoral para alcance national/subnational, y company_internal solo para alcance company. Usa country_code MX durante el MVP, usa jurisdiction_code, no usa center_id, separa source_reference visible de capture_source tecnico, conserva inactivacion no destructiva y no agrega calculos de jornada.
Sprint 2D agrega time_events como modelo interno de eventos fuente.
Sprint 2E agrega registro web basico en /time-clock para entrada, salida e inicio/fin de pausa, usando time_events y sin calculos de jornada.
Quedan pendientes anulacion logica y eventos fuera de orden/tardios.
EPIC-05 no queda completa todavia; BL-0501 a BL-0505 estan candidatas a cierre, y BL-0506/BL-0507 siguen pendientes.
No se implementaron motor legal ni calculos.
```

## Sprint 3 — API y motor legal base

```text
BL-0601, BL-0602, BL-0603, BL-0604, BL-0605, BL-0606, BL-0607
BL-0701, BL-0702, BL-0703, BL-0704
```

## Sprint 4 — Cálculos y alertas

```text
BL-0705, BL-0706, BL-0707, BL-0708, BL-0709, BL-0710
BL-0801, BL-0802, BL-0803, BL-0804, BL-0805, BL-0806
```

## Sprint 5 — Incidencias y correcciones

```text
BL-0807, BL-0808, BL-0809
BL-0901, BL-0902, BL-0903, BL-0904, BL-0905, BL-0906, BL-0907, BL-0908
BL-0506, BL-0507
```

## Sprint 5C — Dashboard operativo inicial

```text
BL-0205
```

Nota:

```text
BL-0205 Dashboard inicial se mantiene como P0, pero se reubica para implementacion en Sprint 5C,
despues de que existan jornadas calculadas, alertas e incidencias.
No se marca como completado en Sprint 1.
```

## Sprint 6 — Cierre y conformidad

```text
BL-1001, BL-1002, BL-1003, BL-1004, BL-1005
BL-1006, BL-1007, BL-1008, BL-1009
```

## Sprint 7 — Reportes, expedientes e integraciones

```text
BL-1101, BL-1102, BL-1103, BL-1104, BL-1105, BL-1106, BL-1107
BL-1201, BL-1202, BL-1203, BL-1206
BL-0608, BL-0609, BL-0610, BL-0611, BL-0612
```

## Sprint 8 — QA y despliegue

```text
BL-1301, BL-1302, BL-1303, BL-1304, BL-1305, BL-1306, BL-1307
BL-1401, BL-1402, BL-1403, BL-1404, BL-1405, BL-1406, BL-1407
BL-1501, BL-1502, BL-1503, BL-1504, BL-1505, BL-1506
```

---

# 8. Capacidades P1 no bloqueantes

| ID | Capacidad | Motivo para no bloquear |
|---|---|---|
| BL-P1-001 | Notificaciones avanzadas | MVP puede iniciar con notificación básica |
| BL-P1-002 | Administración visual completa de reglas legales | Puede iniciar con seeds controlados |
| BL-P1-003 | Confirmación digital por API | Portal trabajador cubre MVP |
| BL-P1-004 | Reconocimiento facial web | Riesgo técnico/legal y de privacidad |
| BL-P1-005 | App nativa | PWA cubre MVP |
| BL-P1-006 | ClickBalance API directa | Requiere documentación/credenciales |
| BL-P1-007 | Dashboard ejecutivo avanzado | Reportes básicos cubren MVP |
| BL-P1-008 | SSO/MFA empresarial | No bloquea piloto inicial |

---

# 9. Primer bloque recomendado para Codex

## Historias

```text
BL-0001 — Inicializar proyecto Laravel
BL-0002 — Crear estructura modular app/Domains
BL-0003 — Configurar MySQL 8 / MariaDB compatible
BL-0004 — Configurar database queue
BL-0006 — Configurar testing base
BL-0101 — Login y logout
BL-0102 — Modelo Company
BL-0103 — Contexto CurrentCompany/TenantContext
BL-0104 — Relación usuario-empresa
BL-0106 — Roles iniciales
BL-0107 — Policies multi-tenant
```

## Prompt sugerido para Codex

```text
Trabaja únicamente sobre el proyecto Laravel actual.

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
- No poner lógica de negocio en componentes Livewire.
- Preparar Actions/Services cuando aplique.
- Implementar multi-tenant por company_id.
- Agregar pruebas básicas.
- No crear funcionalidades fuera del alcance.
```

---

# 9.1 Backlog propuesto WFM y cierres

| Bloque | Objetivo | Dependencias | Archivos esperados | Pruebas | Criterio de salida | Riesgo | Orden |
|---|---|---|---|---|---|---|---|
| A. Normalizacion de roles y permisos | Unificar `rh` y definir permisos base | Sprint 0/1 | `RoleSeeder`, policies, tests de roles | Pest policies | Implementado/candidato a cierre: `rh` es clave unica y `hr` no opera como alias | Romper usuarios demo | 1 |
| B. Unidades organizacionales y responsables | Crear departamentos/areas/equipos y alcances explicitos por centro completo o unidad | Centros/trabajadores | B1: migraciones, modelos, Actions, factories, tests. B2: pantallas Livewire/Volt | multi-tenant y alcance supervisor | B1 y B2 implementados/candidatos a cierre: modelo, dominio y UI operativa listos | Acceso horizontal | 2 |

Nota Bloque A:
h queda como clave canonica de Recursos Humanos; owner, dmin y h conservan permisos empresariales base. supervisor no recibe alcance global automatico.
Nota Bloque B1:
Se implementa solo modelo organizacional, asignaciones de unidad y alcances operativos de dominio. No incluye pantallas, plantillas de turno, perfiles, programacion diaria ni cierres.

Nota Bloque B2:
Se implementan pantallas para areas/departamentos, asignaciones organizacionales, responsables/supervisores y "Mi alcance". Las escrituras usan Actions de B1. No incluye plantillas de turno, perfiles, programacion diaria, incidencias, alertas, reportes, API WFM ni CSV.

Nota Bloque C:
Se implementa `/scheduling/shifts` como catalogo de plantillas de turno por empresa con segmentos diarios. No guarda timezone, `center_id`, tipo legal ni ventanas flexibles. La vista previa distingue trabajo programado bruto, descansos fijos, descansos por duracion, trabajo efectivo programado y duracion total; solo los descansos por duracion no pagados reducen el trabajo efectivo. No asigna personas, no genera calendarios y no sincroniza con el modelo legacy. `schedules`, `schedule_days`, `schedule_breaks` y `schedule_assignments` siguen disponibles temporalmente hasta Bloque J.

Nota Bloque D1:
Se implementan `schedule_profiles`, `schedule_profile_weekly_rules` y `schedule_profile_assignments` para perfiles `pattern` con `pattern_mode = weekly` y `calendar`. Los perfiles por patron semanal usan siete reglas semanales basadas en `shift_templates`; los perfiles por calendario no tienen reglas semanales. La resolucion efectiva usa prioridad relacion laboral, unidad principal vigente, centro y empresa. `temporary_support` no cambia la herencia.

Nota Bloque D2:
Se implementan `/scheduling/profiles` y `/scheduling/profile-assignments` para administrar perfiles por patron semanal y por calendario, reglas semanales de patron, asignaciones por herencia y consulta de perfil efectivo. La navegacion queda reorganizada y oculta las entradas legacy de `/schedules` y `/schedule-assignments` sin eliminar rutas ni codigo. En este bloque no se implementan `pattern_mode = cycle`, perfiles `flexible`/`on_call`, generacion diaria, publicacion operativa, CSV/API WFM ni calculos.

Nota Bloque E1:
Se implementa dominio y pruebas para `pattern` con `pattern_mode = cycle`, perfiles `flexible` y perfiles `on_call`. Agrega reglas de ciclo, reglas flexibles, reglas bajo demanda, resolucion de regla por fecha y escenarios demo manuales. No incluye interfaz E2, programacion diaria publicada, batches, activaciones bajo demanda, CSV/API WFM ni calculos.

Nota Bloque E2:
Se implementa la interfaz de `/scheduling/profiles` para administrar Por patron con Patron semanal o Ciclo repetitivo, Por calendario, Flexible y Bajo demanda. La pantalla reutiliza Actions E1, conserva permisos existentes, exige confirmacion al cambiar de metodo y no genera programacion diaria, no publica batches, no crea activaciones, CSV/API WFM ni calculos.

Nota Bloque F1:
Se implementa el nucleo de programacion diaria sin interfaz: `schedule_batches`, `daily_schedule_assignments` y `daily_schedule_segments`. Un batch pertenece a empresa, centro, periodo y version. Las asignaciones soportan `shift`, `rest`, `flexible`, `on_call` y `unassigned`; los segmentos congelan la estructura diaria. Se agrega snapshot JSON canonico con hash SHA-256 y resolver de programacion publicada sin fallback a perfiles ni modelo legacy. No incluye generacion desde perfiles, publicacion operativa, CSV/API WFM, `work_days`, calculos, alertas, incidencias ni reportes.

Nota Bloque F2:
Se implementa generacion de programacion diaria en borrador desde perfiles con `GenerateDraftScheduleBatchFromProfilesAction`. Los modos permitidos son `missing_only` y `refresh_profile_generated`; la regeneracion solo reemplaza dias creados por `schedule_profile_generation` y preserva manual/csv/api/system ajeno. Se generan `shift`, `rest`, `flexible`, `on_call` y `unassigned` segun perfil efectivo por fecha. `calendar` y relaciones sin perfil quedan como `unassigned` con motivo. No incluye interfaz, publicacion, snapshots persistidos, CSV/API WFM, `work_days`, calculos, alertas, incidencias ni reportes.

Nota Bloque F3A:
Se implementa validacion integral y publicacion atomica de batches `draft` completos con `ValidateScheduleBatchForPublicationAction`, `PublishScheduleBatchAction` y `VerifyPublishedScheduleBatchSnapshotAction`. Publica solo `version = 1` sin `previous_batch_id`, exige cobertura completa por centro/periodo, bloquea `unassigned`, valida tipos `shift`, `rest`, `flexible` y `on_call`, detecta conflictos con programacion ya publicada por relacion laboral/fecha, persiste snapshot JSON canonico, SHA-256, `published_by` y `published_at`, y mantiene inmutabilidad desde Actions. No incluye interfaz, correcciones F4, supersede automatico, CSV/API WFM, `work_days`, calculos, alertas, incidencias ni reportes.

Nota Bloque F3B:
Se implementa `/scheduling/daily` como interfaz para administrar lotes de programacion diaria. Permite crear lotes borrador por centro/periodo, generar faltantes, actualizar desde perfiles conservando cambios manuales, revisar calendario semanal, editar dias individuales, aplicar cambio masivo basico, validar antes de publicar, publicar con confirmacion y consultar/verificar integridad de publicaciones. Supervisores solo consultan segun alcance operativo vigente. No incluye F4, versiones correctivas, importacion CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

Nota Bloque F4:
Se implementan correcciones versionadas no destructivas para programacion diaria publicada. Una publicacion vigente puede crear una nueva version en borrador con motivo obligatorio; la correccion clona dias y segmentos congelados, permite editar mediante el calendario F3B, compara contra la version anterior y publica de forma atomica dejando la anterior `superseded`. Incluye historial de versiones y seeder demo `VeraTimeCorrectedScheduleScenarioSeeder`. No incluye CSV/XLSX, API WFM, `work_days`, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

Nota Bloque F5A:
Se implementa dominio para importacion CSV de programacion diaria a lotes `draft`. Incluye `import_batches`, `import_rows`, parser CSV version 1, validacion por fila, resolucion de trabajador/relacion/fecha/turno, preview con huella para detectar cambios posteriores, aplicacion transaccional all-or-nothing y seeder demo `VeraTimeDailyScheduleCsvScenarioSeeder`. Las filas aplicadas usan `source_type = csv` y reutilizan `ReplaceDraftDailyScheduleAssignmentAction`. No incluye UI de carga, descarga de plantilla, descarga de errores, API WFM, jobs asincronos, XLSX, `work_days`, calculos legales, alertas, incidencias, cierres, conformidad ni reportes.

| C. Plantillas de turno | Reemplazar horario simple por turnos reutilizables | B opcional | shift_templates, segmentos, UI | CRUD, cruces medianoche | Implementado/candidato a cierre: catalogo disponible sin calcular jornada | Mezclar flexible con turno rigido | 3 |
| D. Perfiles pattern weekly y calendar | Crear perfiles, reglas semanales y asignaciones con herencia | C | `schedule_profiles`, `pattern_mode`, weekly rules, assignments | dominio, resolucion y UI | D1/D2 implementado/candidato a cierre: perfiles, resolutor, pantallas y navegacion disponibles | Publicacion prematura | 4 |
| E. Perfiles cycle, flexible y on_call | Agregar ciclos, ventanas/minutos y disponibilidad bajo demanda | D | cycle rules, flexible rules, on_call rules, UI perfiles | dominio, resolucion y UI | E1/E2 implementados/candidatos a cierre: dominio e interfaz cycle/flexible/on_call disponibles sin programacion diaria | Complejidad UX | 5 |
| F. Calendario diario y publicacion | Crear batches obligatorios por centro y daily schedules publicados | D/E | `schedule_batches`, `daily_schedule_assignments`, `daily_schedule_segments` | publicacion no destructiva | F1/F2/F3A/F3B/F4 implementados/candidatos a cierre: nucleo diario, generacion draft, publicacion atomica, interfaz y correcciones versionadas | Versionado incorrecto | 6 |
| G. Importacion CSV/XLSX | Importar programacion por calendario a draft | F | import batches/rows, parser, validadores | errores por fila | F5A implementado/candidato a cierre: dominio CSV disponible sin UI, sin API y sin XLSX; importacion nunca publica directo | Calidad de archivos | 7 |
| H. Perfiles multiples de cierre | Crear perfiles y excepciones | B/F | closing profiles/assignments | prioridad de resolucion | Empresa tiene default | Confusion de herencia | 8 |
| I. Generacion de periodos de cierre | Generar periodos y miembros congelados | H | closing periods/members | congelacion/versiones | Periodo congela miembros | Cambios historicos | 9 |
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

| Riesgo | Mitigación |
|---|---|
| Querer construir todo a la vez | Seguir sprints |
| Multi-tenant débil | Tests desde Sprint 0 |
| Lógica en Livewire | Actions/Services obligatorios |
| Motor legal acoplado a UI | Servicios puros |
| API duplicada | Reutilizar dominio |
| Reportes pesados en request | Jobs |
| Hosting actual limita procesos | Database queue + cron |
| ClickBalance bloquea avance | CSV primero |
| Biometría retrasa MVP | Mantener P1/OUT |
| Correcciones destructivas | Versionamiento obligatorio |

---

# 11. Criterios de aceptación del backlog

El backlog inicial queda aprobado cuando:

1. Cubre módulos P0.
2. Separa P0, P1 y fuera del MVP.
3. Incluye API-first bidireccional.
4. Incluye MySQL 8 / MariaDB compatible y hosting actual como inicio.
5. Incluye evolución futura a AWS u otra nube.
6. Incluye pruebas y despliegue.
7. Tiene primer bloque listo para Codex.
8. No mete app nativa ni biometría como bloqueo.
9. Permite llegar a piloto con evidencia, alertas y conformidad digital.

---

# 12. Siguiente paso

Después de aprobar este backlog, el siguiente paso ya es desarrollo.

El primer bloque a ejecutar será:

```text
Sprint 0 — Base técnica
```

No se recomienda crear más documentos grandes antes de iniciar código, salvo que aparezca una decisión técnica nueva que pueda bloquear el avance.
