---
id: ARQ-0001
title: Arquitectura del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-01
updated: 2026-07-03
tags:
  - arquitectura
  - mvp
  - laravel
  - livewire
  - multi-tenant
  - motor-legal
  - veratime
---

# ARQ-0001 — Arquitectura del MVP

## 1. Objetivo

Definir la arquitectura técnica del MVP de Vera Time para construir una plataforma SaaS multi-tenant capaz de:

- Registrar electrónicamente la jornada laboral.
- Calcular resultados con reglas legales versionadas.
- Generar alertas preventivas.
- Administrar incidencias y correcciones no destructivas.
- Permitir revisión y conformidad digital.
- Generar reportes, expedientes y exportaciones.
- Operar con seguridad, auditoría y separación estricta entre empresas.

Este documento no define todavía el modelo físico de base de datos, pantallas ni endpoints detallados.

---

## 2. Decisión arquitectónica principal

Vera Time se construirá como:

```text
Monolito modular Laravel
+ Livewire
+ MySQL 8 / MariaDB compatible
+ Colas/jobs
+ API propia
+ Motor legal desacoplado
+ Auditoría transversal
+ Evidencia versionada
```

No se implementarán microservicios en el MVP.

## 2.1 Justificación

El dominio es complejo, pero el equipo y la fecha objetivo no justifican dividirlo en servicios independientes. El monolito modular permite avanzar rápido, mantener orden técnico y separar responsabilidades sin asumir costos operativos prematuros.

---

## 3. Stack técnico

| Capa | Decisión |
|---|---|
| Backend | Laravel |
| Frontend MVP | Livewire + Blade + Tailwind |
| Base de datos | MySQL 8 / MariaDB compatible |
| Cache / colas | Database queue inicial; Redis opcional posterior |
| Autenticación web | Sesiones Laravel |
| API | Laravel Sanctum |
| Testing | Pest |
| Archivos | Storage local/persistente inicial; S3 compatible después del MVP/piloto |
| Reportes | PDF, CSV/XLSX y ZIP |
| Deploy | Hosting actual para MVP/piloto; AWS u otra nube después del MVP/piloto |
| Monitoreo | Logs, errores, métricas básicas y alertas técnicas |

---

## 4. Principios de arquitectura

1. **Llegar al MVP antes de enero de 2027.**
2. **Monolito modular, no microservicios prematuros.**
3. **Multi-tenant estricto desde el diseño.**
4. **Reglas legales versionadas, no valores fijos en pantallas.**
5. **Eventos de jornada no destructivos.**
6. **Correcciones mediante nuevas versiones.**
7. **Alertas preventivas con lenguaje neutral.**
8. **Conformidad digital vinculada a una versión exacta del reporte.**
9. **Registro por web/PWA y kiosco con código/NIP como base del MVP.**
10. **Biometría, reconocimiento facial y app nativa fuera del P0.**
11. **Domain-first con exposición API-first pragmática y bidireccional.**
12. **Integraciones por etapas: CSV/API propia primero, APIs externas después.**
13. **Auditoría y seguridad como parte del producto, no como extras.**
14. **Evitar dependencias exclusivas del hosting actual.**

## 4.1 Domain-first con exposición API-first bidireccional

Vera Time adoptará una arquitectura **domain-first** con exposición **API-first pragmática y bidireccional**.

La lógica principal del producto deberá vivir en acciones, servicios de aplicación y servicios de dominio reutilizables. Livewire, API, CSV, jobs e integraciones serán entradas al sistema, pero ninguna de esas entradas deberá contener la lógica principal de negocio de forma aislada.

La API será una entrada oficial al sistema. No será un agregado secundario, una copia limitada de la interfaz ni un camino alterno con reglas distintas.

El patrón obligatorio será:

```text
Livewire
API
CSV
Jobs
Integraciones
        ↓
Actions / Application Services
        ↓
Domain Services
        ↓
Persistence
```

Esto significa que:

- Livewire no deberá contener lógica principal de negocio.
- Los controladores API no deberán duplicar reglas que ya existan en servicios.
- Las importaciones CSV deberán usar los mismos servicios que la interfaz y la API.
- Los jobs deberán ejecutar acciones de aplicación, no reimplementar cálculos o validaciones.
- Las integraciones deberán pasar por las mismas reglas de permisos, vigencia, auditoría, idempotencia y trazabilidad.

Una operación crítica deberá poder ejecutarse desde distintas entradas sin duplicar lógica ni cambiar el resultado esperado. Por ejemplo, registrar un evento desde kiosco, API o CSV deberá activar las mismas validaciones, el mismo recalculo, las mismas alertas y la misma auditoría.

Regla de control:

```text
Si una función crítica solo funciona desde una pantalla,
la implementación no cumple la arquitectura.
```

## 4.2 Portabilidad de hosting e infraestructura

El sistema deberá evitar dependencias exclusivas del hosting actual.

La configuración, almacenamiento, colas, logs, scheduler, variables de entorno y despliegue deberán diseñarse para poder migrar posteriormente a AWS, S3 compatible, Redis u otra infraestructura sin reescribir el dominio ni los flujos principales.

Para el MVP/piloto se priorizará:

- MySQL 8 / MariaDB compatible.
- Database queue inicial.
- Storage local o persistente del hosting.
- Backups verificables.
- Variables de entorno documentadas.
- Scheduler y jobs operando con mecanismos estándar de Laravel.

Redis, S3 compatible y AWS quedan como evolución posterior al MVP/piloto, no como dependencia obligatoria inicial.

---

## 5. Vista general del sistema

```text
Usuario web / PWA / Kiosco
        ↓
Laravel + Livewire
        ↓
Servicios de aplicación
        ↓
Dominios internos
        ↓
MySQL 8 / MariaDB compatible
        ↓
Jobs / Colas
        ↓
Reportes / Expedientes / Integraciones
```

Flujo principal:

```text
Evento de jornada
→ reconstrucción de jornada
→ motor legal
→ cálculo versionado
→ alertas preventivas
→ incidencias/correcciones
→ cierre de periodo
→ reporte individual
→ conformidad / no conformidad
→ expediente / exportación
```

---

## 6. Módulos internos

| Código | Módulo | Responsabilidad |
|---|---|---|
| MOD-001 | Tenancy | Empresas, contexto activo, límites, aislamiento |
| MOD-002 | Empresas y centros | Configuración de empresas, centros y zonas horarias |
| MOD-003 | Personas | Trabajadores, relaciones laborales y vigencias |
| MOD-004 | Horarios | Horarios, turnos, descansos y asignaciones |
| MOD-005 | Registro electrónico | Entrada, salida, pausas, kiosco, CSV y API |
| MOD-006 | Motor legal | Cálculo y explicación con reglas versionadas |
| MOD-007 | Alertas | Posibles desviaciones y estados de revisión |
| MOD-008 | Incidencias | Solicitudes, correcciones, controversias y recalculo |
| MOD-009 | Portal trabajador | Consulta, aclaraciones y conformidad |
| MOD-010 | Cierre | Periodos, reportes individuales y versiones |
| MOD-011 | Reportes | Reportes operativos y regulatorios |
| MOD-012 | Expedientes | Paquetes de evidencia delimitados |
| MOD-013 | Integraciones | CSV, API propia y conectores condicionados |
| MOD-014 | Auditoría | Bitácora transversal de operaciones sensibles |
| MOD-015 | Administración global | Reglas legales, soporte y configuración SaaS |

---

## 7. Organización sugerida del código

```text
app/
├── Domains/
│   ├── Tenancy/
│   ├── Companies/
│   ├── Workers/
│   ├── Schedules/
│   ├── TimeRecords/
│   ├── LegalRules/
│   ├── Calculations/
│   ├── Alerts/
│   ├── Incidents/
│   ├── Closures/
│   ├── Reports/
│   ├── Integrations/
│   └── Audit/
├── Livewire/
│   ├── Admin/
│   ├── Company/
│   ├── WorkerPortal/
│   └── Shared/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Policies/
├── Support/
└── Providers/
```

Cada dominio podrá tener solo las carpetas que necesite:

```text
Actions/
Enums/
Models/
Queries/
Services/
ValueObjects/
Policies/
Data/
```

Regla: no crear carpetas vacías. Solo se crean cuando exista código real.

---

## 8. Estrategia multi-tenant

## 8.1 Decisión

El MVP usará una sola base de datos compartida con separación por `company_id`.

No se usará base de datos por empresa durante el MVP.

## 8.2 Capas de protección

La separación por empresa deberá aplicarse en:

1. Middleware.
2. Contexto de empresa activa.
3. Policies.
4. Consultas controladas.
5. Validaciones de servicios.
6. Jobs.
7. Exportaciones.
8. Pruebas automatizadas.

## 8.3 Servicio de contexto

Se implementará un servicio conceptual:

```text
CurrentCompany / TenantContext
```

Responsable de:

- Resolver empresa activa.
- Validar acceso del usuario.
- Aplicar contexto a consultas.
- Pasar contexto a jobs.
- Registrar auditoría.

## 8.4 Regla obligatoria

Toda entidad operativa deberá pertenecer directa o indirectamente a una empresa.

Ejemplos:

```text
workers
employment_relationships
schedules
time_events
work_days
alerts
incidents
period_reports
exports
```

---

## 9. Autenticación y autorización

## 9.1 Web

La aplicación web usará autenticación por sesión.

Roles mínimos:

- Superadministrador.
- Administrador de empresa.
- Recursos humanos.
- Supervisor.
- Nómina.
- Jurídico.
- Persona trabajadora.
- Solo lectura.

## 9.2 API

La API usará tokens por empresa mediante Sanctum.

Cada token tendrá:

- Empresa.
- Nombre.
- Alcance.
- Estado.
- Último uso.
- Revocación.
- Rate limiting.
- Auditoría.

## 9.3 Autorización

Se usará combinación de:

- Roles.
- Permisos.
- Policies.
- Alcance por empresa.
- Alcance por centro o equipo.

Un usuario autenticado no tendrá permisos implícitos sobre todas las entidades.

---

## 10. Registro electrónico

## 10.1 Principio

El evento es la fuente primaria. El sistema no dependerá solo de totales diarios.

## 10.2 Tipos mínimos de evento

```text
entrada
salida
inicio_pausa
fin_pausa
captura_manual
correccion
anulacion_logica
```

## 10.3 Datos mínimos de evento

Cada evento deberá conservar:

- Empresa.
- Persona trabajadora.
- Relación laboral.
- Tipo de evento.
- Fecha y hora del hecho.
- Fecha y hora de recepción.
- Zona horaria.
- Fuente.
- Usuario, dispositivo o integración.
- Estado.
- Identificador externo, cuando aplique.
- Bitácora.

## 10.4 Modo kiosco con código/NIP

El MVP incluirá modo kiosco:

```text
Código o número de empleado
→ NIP
→ acción: entrada / salida / pausa
→ confirmación
→ evento registrado
```

El reconocimiento facial no será P0.

## 10.5 Captura manual

Toda captura manual deberá registrar:

- Motivo.
- Autor.
- Fecha.
- Evidencia opcional.
- Aprobación cuando aplique.
- Bitácora.
- Recalculo posterior.

---

## 11. Motor legal

## 11.1 Decisión

El motor legal será un conjunto de servicios desacoplados de Livewire, controladores y vistas.

## 11.2 Entrada

El motor recibirá:

- Persona.
- Relación laboral.
- Fecha operativa.
- Eventos.
- Horario vigente.
- Condiciones laborales vigentes.
- Reglas legales vigentes.
- Políticas aplicables.
- Correcciones aprobadas.

## 11.3 Salida

El motor devolverá:

- Jornada reconstruida.
- Tipo resultante.
- Minutos ordinarios.
- Minutos nocturnos.
- Minutos extraordinarios.
- Descansos.
- Domingos y descansos obligatorios.
- Alertas candidatas.
- Explicación del cálculo.
- Versión del cálculo.

## 11.4 Regla técnica

El motor no debe guardar directamente en base de datos.

Flujo correcto:

```text
Application Service
→ Legal Engine
→ Calculation Result
→ Persist Calculation Version
→ Generate Alerts
```

---

## 12. Reglas legales versionadas

Las reglas legales serán datos administrables, no constantes dispersas.

Ejemplos:

```text
maximum_weekly_hours
daily_limit_diurnal
daily_limit_nocturnal
daily_limit_mixed
overtime_art66_limit
overtime_art68_extra_limit
minimum_continuous_break
sunday_premium_percent
mandatory_rest_day
```

Cada regla tendrá:

- Código.
- Valor.
- Unidad.
- Fuente.
- Inicio de vigencia.
- Fin de vigencia.
- Estado.
- Descripción.
- Versión.

Esto permite actualizar la norma sin rediseñar pantallas.

---

## 13. Cálculos versionados

Cada jornada calculada deberá tener versiones.

Ejemplo:

```text
Cálculo v1
→ salida faltante
→ alerta

Corrección aprobada
→ Cálculo v2
→ salida agregada
→ resultado recalculado
```

Se generará nueva versión cuando exista:

- Evento tardío.
- Corrección aprobada.
- Reproceso autorizado.
- Cambio de configuración aplicable.
- Actualización normativa aplicable.

Cada versión conservará:

- Eventos utilizados.
- Reglas aplicadas.
- Resultado.
- Alertas.
- Usuario/proceso generador.
- Fecha.
- Motivo.

---

## 14. Alertas preventivas

Las alertas se generarán después del cálculo.

Flujo:

```text
Calculation Version
→ Alert Evaluator
→ Alert Candidates
→ Alert Upsert
```

## 14.1 Niveles

```text
informativa
advertencia
alta
critica
```

## 14.2 Estados

```text
nueva
en_revision
pendiente_informacion
justificada
corregida
cerrada
```

## 14.3 Regla de lenguaje

La interfaz usará lenguaje neutral:

```text
Posible incumplimiento
Situación pendiente de revisión
Requiere validación
Tiempo superior al programado
```

No deberá decir automáticamente:

```text
La empresa incumplió la ley
```

## 14.4 Bloqueo

Una alerta crítica pendiente puede bloquear:

- Cierre administrativo.
- Reporte limpio.
- Conformidad final.
- Exportación definitiva de cierre.

No bloquea nuevos registros.

---

## 15. Incidencias y correcciones

## 15.1 Diferencia conceptual

```text
Alerta: detectada por el sistema
Incidencia: caso gestionable
Corrección: cambio propuesto/aprobado
```

## 15.2 Corrección no destructiva

Una corrección no reemplaza silenciosamente el dato original.

Puede:

- Crear evento corregido.
- Anular lógicamente un evento.
- Relacionar evento original y corrección.
- Generar nuevo cálculo.
- Generar nueva versión de reporte.

## 15.3 Estados mínimos

```text
abierta
en_revision
aprobada
rechazada
en_controversia
cerrada
```

---

## 16. Cierre y conformidad digital

## 16.1 Periodos

La empresa podrá configurar cierres:

- Semanales.
- Quincenales.
- Mensuales.
- Por periodo de nómina.

## 16.2 Flujo

```text
Crear cierre
→ calcular jornadas
→ generar alertas
→ resolver alertas críticas
→ generar reporte individual v1
→ enviar a revisión
→ conforme / no conforme / pendiente
→ cerrar o abrir aclaración
```

## 16.3 Reporte versionado

Cada reporte individual conservará:

- Empresa.
- Persona.
- Periodo.
- Versión.
- Datos incluidos.
- Hash.
- Estado.
- Fecha de generación.

## 16.4 Confirmación MVP

La confirmación digital del MVP usará:

```text
usuario autenticado
+ acción expresa
+ NIP o código por correo cuando se configure
+ hash del reporte
+ bitácora
```

No se usará e.firma, blockchain ni firma electrónica avanzada como requisito del MVP.

## 16.5 Nueva versión

Si el reporte cambia:

```text
Reporte v1 se conserva
→ se genera Reporte v2
→ la firma de v1 no aplica a v2
→ se solicita nueva revisión
```

---

## 17. Reportes y expedientes

## 17.1 Reportes P0

- Diario.
- Semanal.
- Por periodo.
- Por persona.
- Por centro.
- Horas extra.
- Alertas.
- Incidencias.
- Conformidad digital.
- Jornadas incompletas.

## 17.2 Expediente

Un expediente debe incluir:

- Alcance.
- Periodo.
- Personas.
- Eventos fuente.
- Cálculos.
- Alertas.
- Incidencias.
- Correcciones.
- Reportes.
- Confirmaciones.
- Hash.
- Manifiesto.

## 17.3 Generación

Los reportes pesados y expedientes se generarán con jobs.

---

## 18. Importaciones e integraciones

## 18.1 CSV P0

El MVP soportará CSV para:

- Personas.
- Horarios.
- Eventos.
- Exportación de prenómina.

## 18.2 API propia P0

API mínima:

```text
POST /api/v1/workers
GET  /api/v1/workers
POST /api/v1/time-events
GET  /api/v1/work-days
GET  /api/v1/reports/periods
POST /api/v1/imports
```

## 18.3 ClickBalance

### P0/P1 alto

Exportación compatible por archivo, si se confirma el formato operativo.

### P1 condicionado

API directa con ClickBalance para:

- Leer empleados.
- Leer periodos.
- Enviar movimientos.
- Consultar recepción.

Condiciones:

- Documentación técnica.
- Credenciales.
- Ambiente de pruebas.
- Endpoints suficientes.
- Validación de seguridad.

---

## 19. Jobs y colas

Se usarán jobs para:

- Procesar importaciones.
- Recalcular jornadas.
- Generar alertas.
- Generar reportes.
- Generar expedientes.
- Enviar notificaciones.
- Sincronizar integraciones.
- Verificar respaldos.

Colas sugeridas:

```text
default
calculations
imports
reports
notifications
integrations
```

---

## 20. Archivos y evidencia

Archivos P0:

- Evidencias de incidencias.
- Reportes PDF.
- CSV/XLSX.
- ZIP de expedientes.
- Manifiestos.
- Archivos de importación.
- Errores de importación.

Cada archivo crítico deberá tener:

- Empresa.
- Tipo.
- Hash.
- Fecha.
- Relación con entidad.
- Usuario que lo generó.
- Estado.

---

## 21. Seguridad y auditoría

## 21.1 Seguridad mínima

- HTTPS.
- Sesiones seguras.
- Hash de contraseñas.
- Roles y permisos.
- Policies.
- CSRF.
- Rate limiting.
- Validación de entrada.
- Protección contra acceso horizontal.
- Revocación de tokens.
- Logs de seguridad.

## 21.2 Auditoría

Se auditarán:

- Cambios de trabajadores.
- Cambios de horario.
- Eventos manuales.
- Correcciones.
- Anulaciones lógicas.
- Cierres.
- Firmas o no conformidades.
- Exportaciones.
- Cambios de permisos.
- Acceso de soporte.
- Cambios de reglas legales.

Cada bitácora deberá incluir:

- Empresa.
- Usuario.
- Acción.
- Entidad.
- Fecha.
- IP.
- Valor anterior.
- Valor nuevo.
- Motivo.
- Fuente.

---

## 22. Frontend y UX

## 22.1 Decisión

Livewire + Blade + Tailwind.

## 22.2 Interfaces principales

- Login.
- Selector de empresa.
- Dashboard.
- Empresas y centros.
- Personas.
- Horarios y turnos.
- Registro/kiosco.
- Jornadas.
- Alertas.
- Incidencias.
- Cierre.
- Portal trabajador.
- Reportes.
- Importaciones.
- Administración global.

## 22.3 Portal trabajador

Diseño móvil primero:

- Ver mi día.
- Ver mi semana.
- Solicitar aclaración.
- Revisar reporte.
- Conforme / no conforme.

---

## 23. Deploy

Ambientes mínimos:

```text
local
staging
production
```

Producción deberá incluir:

- HTTPS.
- Base de datos respaldada.
- Jobs activos.
- Storage persistente.
- Logs.
- Backups.
- Monitoreo.
- Variables de entorno.
- Scheduler.

---

## 24. Backups y recuperación

Antes del piloto se definirán:

- RPO.
- RTO.
- Retención.
- Responsable de restauración.
- Prueba de restauración.

Mínimo:

- Respaldo diario de base de datos.
- Respaldo de archivos críticos.
- Verificación periódica.

---

## 25. Testing

Pruebas P0:

- Multi-tenant.
- Policies.
- Registro de eventos.
- Motor legal.
- Alertas.
- Correcciones.
- Versiones.
- Conformidad digital.
- Reportes.
- Exportaciones.
- API.
- Idempotencia.
- Importaciones.
- Auditoría.

Las reglas legales críticas tendrán pruebas automatizadas.

---

## 26. Decisiones explícitas del MVP

| Tema | Decisión |
|---|---|
| Arquitectura | Monolito modular Laravel |
| Enfoque de dominio | Domain-first con exposición API-first bidireccional |
| Base de datos | MySQL 8 / MariaDB compatible |
| Multi-tenant | Base compartida con `company_id` |
| Frontend | Livewire, Blade y Tailwind |
| Colas | Database queue inicial; Redis opcional posterior |
| Storage | Local/persistente inicial; S3 compatible después del MVP/piloto |
| Hosting | Evitar dependencias exclusivas; AWS después del MVP/piloto |
| Móvil | PWA responsiva |
| App nativa | Fuera del MVP |
| Kiosco | Código/NIP |
| Biometría | Fuera del P0 |
| Reconocimiento facial | P1 condicionado |
| Motor legal | Servicios desacoplados |
| Reglas legales | Versionadas |
| Eventos | No destructivos |
| Correcciones | Nueva versión de cálculo/reporte |
| Alertas | P0 |
| Conformidad digital | P0 |
| API | `/api/v1` con Sanctum |
| ClickBalance | Archivo primero; API condicionada |
| Reportes pesados | Jobs |
| Testing | Pest |

---

## 26.1 Decision WFM: programacion diaria publicada

Vera Time adoptara un modelo WFM donde `daily_schedule_assignments` publicados y `daily_schedule_segments` son la unica fuente de verdad operativa.

Reglas arquitectonicas:

- Livewire/Volt solo captura intencion y muestra estado.
- API, CSV/XLSX, jobs y vistas reutilizan Actions/Services.
- Los perfiles de horario generan borradores, no resultados operativos.
- Bloque F1 crea el nucleo de batches, asignaciones diarias, segmentos, snapshot canonico y resolver publicado, sin UI ni publicacion operativa.
- Bloque F2 genera programacion diaria en borrador desde perfiles, sin publicar ni persistir snapshots de publicacion.
- Bloque F3A publica batches completos de forma atomica desde dominio, sin UI, persistiendo snapshot JSON canonico, `published_by`, `published_at` y hash SHA-256.
- Bloque F3B agrega interfaz Livewire/Volt para administrar batches, calendario, edicion individual, cambio masivo basico, validacion, publicacion y verificacion de integridad reutilizando Actions de dominio.
- La publicacion serializa por centro con transaccion y `lockForUpdate` sobre `Center`; la unicidad efectiva publicada por relacion laboral/fecha se valida en dominio para MySQL/MariaDB.
- `ResolveDailyScheduleForRelationshipDateAction` resuelve programacion efectiva publicada por relacion laboral y fecha.
- La publicacion es inmutable. Una correccion genera nueva version y deja la anterior `superseded`.
- `daily_schedule_assignments` publicados y `daily_schedule_segments` son la unica fuente operativa.
- La seguridad por alcance se resuelve antes de consultar o mutar trabajadores.

Resolutores obligatorios:

- `ResolveUserOperationalScopeAction`
- `EnsureUserCanManageWorkerAction`
- `ResolveScheduleProfileForRelationshipAction`
- `GenerateDailySchedulesFromProfileAction`
- `PublishScheduleBatchAction`
- `ResolveDailyScheduleForRelationshipDateAction`
- `ResolveClosingProfileForRelationshipAction`
- `GenerateClosingPeriodsAction`

Responsabilidades:

| Action | Responsabilidad |
|---|---|
| `ResolveUserOperationalScopeAction` | Determina si el usuario tiene alcance completo de empresa o alcance limitado por centro/unidad. |
| `EnsureUserCanManageWorkerAction` | Bloquea operaciones sobre trabajadores fuera del alcance del usuario. |
| `ResolveScheduleProfileForRelationshipAction` | Resuelve el perfil aplicable para una relacion laboral segun asignaciones vigentes. |
| `GenerateDailySchedulesFromProfileAction` | Nombre conceptual previo. La implementacion F2 usa `GenerateDraftScheduleBatchFromProfilesAction` para crear solo borradores. |
| `ResolveScheduleProfileRuleForDateAction` | Resuelve la regla aplicable por fecha desde una asignacion efectiva: weekly, cycle, calendar, flexible u on_call. No crea dias publicados ni batches. |
| `GenerateDraftScheduleBatchFromProfilesAction` | Genera o refresca dias de un batch `draft` usando perfiles efectivos por relacion laboral y fecha. Modos: `missing_only` y `refresh_profile_generated`. Preserva dias manual/csv/api y otros system. |
| `BuildDraftDailyScheduleFromResolvedProfileAction` | Convierte una regla resuelta en payload de `daily_schedule_assignment`: `shift`, `rest`, `flexible`, `on_call` o `unassigned`. |
| `BuildDailyScheduleSegmentsFromShiftTemplateAction` | Copia segmentos de `shift_templates` y calcula UTC con timezone del centro. |
| `CreateScheduleBatchAction` | Crea batches en borrador por empresa, centro, periodo y version, sin aceptar datos de publicacion desde entrada no confiable. |
| `ReplaceDraftDailyScheduleAssignmentAction` | Reemplaza de forma atomica la programacion de un dia dentro de un batch draft. |
| `BulkReplaceDraftDailyScheduleAssignmentsAction` | Aplica cambios manuales basicos a varios trabajadores y fechas dentro de un batch `draft`; coordina transaccion y reutiliza `ReplaceDraftDailyScheduleAssignmentAction`. |
| `RemoveDraftDailyScheduleAssignmentAction` | Elimina solo dias de borrador; no aplica a batches publicados. |
| `BuildScheduleBatchSnapshotAction` | Construye snapshot JSON canonico y hash SHA-256 sin persistirlo. |
| `ResolveScheduleBatchExpectedRelationshipDatesAction` | Resuelve relaciones laborales y fechas esperadas para un batch, compartido por F2 y F3A. |
| `ValidateScheduleBatchForPublicationAction` | Valida cobertura completa, tipos de dia, segmentos UTC, ausencia de `unassigned` y conflictos con batches publicados. |
| `PublishScheduleBatchAction` | Publica un batch `draft` completo dentro de una transaccion, persiste snapshot JSON canonico, `published_by`, `published_at` y hash SHA-256. |
| `VerifyPublishedScheduleBatchSnapshotAction` | Verifica el snapshot persistido comparando JSON y hash sin reconstruir desde catalogos actuales. |
| `ResolveDailyScheduleForRelationshipDateAction` | Devuelve la programacion publicada efectiva por relacion laboral y fecha; no usa perfiles ni legacy como fallback. |
| `ResolveClosingProfileForRelationshipAction` | Resuelve cierre efectivo con prioridad relacion laboral, unidad, centro, empresa. |
| `GenerateClosingPeriodsAction` | Genera periodos y miembros congelados desde perfiles de cierre. |

Prioridades de resolucion:

- Alcance operativo: empresa completa para `owner`, `admin_empresa` y `rh`; alcance explicito por centro completo o unidad para supervisor/responsable. El rol por si solo no otorga alcance.
- Cierre efectivo: relacion laboral, unidad organizacional, centro, empresa.
- Programacion operativa: `daily_schedule_assignments` publicado; si no existe, el sistema debe devolver ausencia controlada, no calcular desde perfil en tiempo real.

Bloque A corrigio la contradiccion `hr`/`rh`; la clave oficial y operativa es `rh`.

---

## 26.2 Decision de cierres multiples

El cierre no se resolvera por una unica configuracion plana de empresa.

Toda empresa tendra un perfil predeterminado obligatorio y excepciones por centro, unidad organizacional o relacion laboral. La prioridad sera: relacion laboral, unidad, centro, empresa.

Los periodos publicados congelan miembros y configuracion para evitar cambios destructivos en reportes y conformidad.

---

## 27. Riesgos técnicos

| Riesgo | Mitigación |
|---|---|
| Mezcla de datos entre empresas | Policies, contexto tenant y pruebas |
| Motor legal acoplado a pantallas | Servicios puros y pruebas unitarias |
| Reportes lentos | Jobs y generación asíncrona |
| Correcciones destructivas | Versionamiento y auditoría |
| Cierre con alertas críticas | Bloqueo controlado |
| Biometría retrasa el MVP | Fuera del P0 |
| ClickBalance API no disponible | CSV compatible |
| Crecimiento del alcance | P0/P1/Fuera claramente separados |
| Pérdida de evidencia | Hash, storage crítico y respaldos |

---

## 28. Criterios de aceptación

La arquitectura se considerará aprobada cuando:

1. Permita construir el MVP sin microservicios.
2. Garantice separación multi-tenant.
3. Separe motor legal de interfaz.
4. Soporte reglas legales versionadas.
5. Permita correcciones no destructivas.
6. Genere nuevas versiones de cálculo y reporte.
7. Permita alertas preventivas.
8. Permita conformidad digital.
9. Permita reportes y expedientes.
10. Permita API e importaciones.
11. Tenga estrategia de auditoría.
12. Tenga estrategia de jobs.
13. Tenga estrategia de backups.
14. Pueda evolucionar a app nativa, biometría e integraciones sin reescribir el núcleo.

---

## 29. Siguiente documento

Después de aprobar esta arquitectura, se deberá crear:

```text
docs/05-BaseDatos/BD-0001-MODELO-DE-DATOS-MVP.md
```

Ese documento definirá:

- Tablas.
- Relaciones.
- Índices.
- Estados.
- Enumeraciones.
- Versionamiento.
- Auditoría.
- Archivos.
- Llaves únicas.
- Migraciones iniciales.

---

## 30. Fuentes técnicas de referencia

- Laravel Documentation: https://laravel.com/docs
- Laravel Authorization: https://laravel.com/docs/authorization
- Laravel Queues: https://laravel.com/docs/queues
- Laravel Sanctum: https://laravel.com/docs/sanctum
- Livewire Documentation: https://livewire.laravel.com/docs
- Pest Documentation: https://pestphp.com/docs

---

## Nota Bloque F4 - Correcciones versionadas

Las correcciones de programacion diaria publicada se resuelven domain-first. Livewire solo solicita motivo, muestra comparacion y confirma publicacion. La creacion, clonacion, comparacion, validacion y publicacion correctiva viven en Actions de dominio.

Reglas arquitectonicas:

- no editar publicaciones;
- no regresar publicados a borrador;
- no regenerar correcciones desde perfiles;
- no crear ramas paralelas;
- publicar correccion y sustituir version anterior en una transaccion;
- mantener snapshots anteriores sin reconstruccion;
- resolver solo la version `published` vigente.

## Nota Bloque F5A - Importacion CSV de programacion diaria

La importacion CSV de programacion diaria se resuelve domain-first y sin interfaz en este bloque. El flujo vive en Actions:

- `CreateDailyScheduleCsvImportAction`.
- `ParseDailyScheduleCsvAction`.
- `ValidateDailyScheduleCsvImportAction`.
- `ResolveDailyScheduleCsvRowAction`.
- `BuildDailyScheduleCsvAssignmentPayloadAction`.
- `ApplyDailyScheduleCsvImportAction`.
- `CancelDailyScheduleCsvImportAction`.

Reglas arquitectonicas:

- no aceptar `company_id` desde archivo;
- no escribir programacion diaria antes de validar todas las filas;
- no aplicar sobre batches publicados, sustituidos o cancelados;
- no publicar automaticamente;
- detectar preview obsoleto antes de aplicar;
- aplicar en transaccion all-or-nothing;
- reutilizar `ReplaceDraftDailyScheduleAssignmentAction`;
- mantener archivos en almacenamiento privado;
- no implementar UI, API, XLSX ni jobs asincronos en F5A.

## Nota Bloque F5B - UI de importacion CSV de programacion diaria

F5B agrega la entrada web en `/scheduling/daily` para cargar CSV sobre lotes `draft`. Livewire solo captura archivo, motivo, politica de existentes y confirmacion; la escritura sigue en Actions de dominio.

Actions agregadas para la UI:

- `StoreDailyScheduleCsvUploadAction`.
- `GenerateDailyScheduleCsvTemplateAction`.
- `GenerateDailyScheduleCsvErrorReportAction`.
- `ListDailyScheduleCsvImportsAction`.

Reglas arquitectonicas F5B:

- los controladores de descarga son delgados y delegan en Actions;
- el archivo se guarda en storage privado con ruta interna aleatoria;
- la UI no expone rutas privadas ni trazas tecnicas;
- la aplicacion de una importacion exige el hash de validacion esperado;
- los lotes publicados no pueden importarse;
- F5B no agrega API WFM, XLSX, jobs asincronos ni publicacion automatica.
