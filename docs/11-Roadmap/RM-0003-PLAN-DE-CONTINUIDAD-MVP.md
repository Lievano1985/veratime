---
id: RM-0003
title: Plan de continuidad y estabilizacion del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-09-16
updated: 2026-09-16
tags:
  - roadmap
  - mvp
  - continuidad
  - qa
  - piloto
---

# RM-0003 — Plan de continuidad y estabilización del MVP

## 1. Propósito

Ordenar el trabajo restante para llevar Vera Time desde su estado actual a un piloto controlado, sin ampliar el alcance P0 aprobado. Este documento es una guía de ejecución; no reemplaza el backlog ni modifica las reglas de arquitectura, legales o multi-tenant.

## 2. Punto de partida al 16 de septiembre de 2026

El núcleo operativo está implementado o en candidato a cierre: multi-tenant, acceso, empresas, trabajadores, organización, captura de jornada, horarios y programación diaria, cálculos de jornada, motor legal inicial, alertas, incidencias operativas, periodos de asistencia e intercambio CSV básico.

Las brechas que impiden declarar el MVP listo para piloto son:

1. La suite automatizada y la matriz de pruebas manuales todavía no tienen una evidencia completa de cierre.
2. Falta la API P0 bajo `/api/v1` con tokens por empresa y pruebas de aislamiento.
3. Faltan correcciones laborales versionadas para eventos y jornadas, así como auditoría persistente de operaciones críticas.
4. Faltan reporte de cierre versionado, conformidad explícita de la persona trabajadora y expediente de evidencia.
5. Falta validar la operación real en hosting compatible con cPanel: cron, cola `database`, almacenamiento, respaldos y recuperación.

El avance funcional estimado es orientativo y no sustituye criterios de aceptación: el núcleo está avanzado, pero el flujo regulatorio completo aún no está cerrado.

### Verificación inicial registrada

El 16 de septiembre de 2026 se ejecutaron los siguientes subconjuntos como inicio del Bloque 1:

| Alcance | Resultado |
|---|---|
| `tests/Feature/Sprint0` | 29 pruebas aprobadas, 121 aserciones |
| Jornadas, reglas legales, alertas y periodos de asistencia | 109 pruebas aprobadas, 500 aserciones |

Durante la verificación se corrigió una prueba de recálculo nocturno que invocaba el Job sin la dependencia `ProductAccess` añadida al flujo de productos contratados. Esta evidencia no equivale al cierre de la suite completa ni sustituye la ejecución de los escenarios manuales críticos.

### Regresión automatizada completa

La regresión completa se ejecutó en el worktree de continuidad el 16 de septiembre de 2026, con dependencias y configuración locales propias del worktree:

| Comando | Resultado |
|---|---|
| `php artisan test --compact` | 652 pruebas aprobadas, 2,998 aserciones, 444.48 segundos |

En esta ejecución se ajustó el fixture de asignaciones legacy para que la relación laboral exista desde antes de la fecha efectiva probada. El dominio ya resolvía correctamente la relación vigente; el dato aleatorio del fixture podía crear una relación posterior y volver la prueba no determinista.

El Bloque 1 permanece **en revisión** hasta ejecutar y registrar la matriz de pruebas manuales críticas.

## 3. Principios de ejecución

- No iniciar funciones P1 ni integraciones directas sin cerrar primero los bloques P0 de este plan.
- Toda nueva entidad operativa usa `company_id` y se prueba contra acceso cruzado.
- Web, API, CSV y jobs consumen las mismas Actions o Services de dominio.
- No se elimina ni sobrescribe evidencia laboral; las correcciones crean una nueva versión trazable.
- Los reportes confirmados son inmutables. Una modificación posterior produce una nueva versión pendiente de revisión.
- La cola sigue siendo `database`; no se añade Redis, AWS ni un worker permanente como requisito.

## 4. Secuencia priorizada

### Decisión de prioridad — 16 de septiembre de 2026

Se prioriza la API v1 antes del flujo completo de auditoría y correcciones. La API no espera el expediente ni la interfaz de auditoría, pero toda operación mutable debe conservar como mínimo el contexto de empresa, token o actor, operación, resultado y fecha técnica. El detalle de correcciones versionadas permanece como el bloque siguiente a la fundación API.

| Orden | Bloque | Resultado verificable | Dependencia |
|---:|---|---|---|
| 1 | Estabilización y QA | Suite automatizada reproducible y escenarios críticos ejecutados | Ninguna |
| 2 | API v1 base | Endpoints P0 seguros reutilizando el dominio existente | Bloque 1 |
| 3 | Auditoría y correcciones | Historial no destructivo para operaciones sensibles | Bloque 2 |
| 4 | Cierre, reporte y conformidad | Periodo cerrable, reporte versionado y postura explícita del trabajador | Bloques 2 y 3 |
| 5 | Evidencia, operación y piloto | Expediente exportable y operación validada en entorno de piloto | Bloques 1 a 4 |

## 5. Bloque 1 — Estabilización y QA

### Objetivo

Convertir el código ya implementado en una base verificable antes de incorporar capacidades nuevas.

### Actividades

- Ejecutar Pest por grupos funcionales y registrar el resultado, duración y causa de cada fallo.
- Corregir pruebas fallidas, dependencias de orden y pruebas lentas o bloqueadas.
- Ejecutar la matriz manual crítica para acceso, cambio de empresa, alcance operativo, programación diaria, publicación, eventos, cálculos y periodos.
- Revisar policies, consultas, exports y jobs para confirmar el filtro por `company_id`.
- Validar migraciones y seeds en MySQL/MariaDB compatible y compilar los recursos con `npm run build`.

### Criterio de salida

- `php artisan test` termina con resultado verde y reproducible.
- Los escenarios críticos de la guía manual tienen resultado registrado.
- No existen fallos abiertos de aislamiento multi-tenant, historial destructivo, autorización o cálculo que bloqueen operación.

## 6. Bloque 2 — Auditoría y correcciones versionadas

### Objetivo

Garantizar que una operación sensible pueda explicarse y reconstruirse sin alterar sus fuentes originales.

### Alcance

- Crear auditoría persistente para eventos manuales, anulaciones, decisiones de revisión, alertas, cierres, exportaciones, tokens y cambios de acceso.
- Implementar solicitudes de corrección para eventos y jornadas con motivo, actor, fecha UTC, estado, versión y vínculo al original.
- Recalcular de forma controlada después de aprobar una corrección, conservando snapshots previos.
- Añadir policies y pruebas de autorización, tenant y no destrucción de historial.

### Criterio de salida

- Una corrección no modifica ni elimina la fuente original.
- La auditoría permite conocer qué cambió, quién lo hizo, cuándo y por qué.
- El resultado recalculado conserva relación con sus versiones y reglas aplicadas.

## 7. Bloque 3 — API v1 base

### Objetivo

Cumplir el compromiso API-first del MVP sin crear una segunda lógica de negocio.

### Alcance inicial

- Crear `routes/api.php` y el prefijo `/api/v1`.
- Autenticación por token, resolución de empresa, permisos y rate limiting.
- Endpoints iniciales para centros, trabajadores, eventos de jornada, jornadas y periodos de asistencia.
- Resources, Requests, errores consistentes, idempotencia para recepción de eventos y auditoría de operaciones sensibles.
- Documentación del contrato y pruebas de token, permisos, validación e intento de acceso cruzado.

### Criterio de salida

- Las operaciones expuestas reutilizan las mismas Actions que la interfaz web.
- Un token de una empresa no puede leer ni modificar datos de otra empresa.
- El contrato API y ejemplos mínimos están documentados.

## 8. Bloque 4 — Cierre, reporte y conformidad

### Objetivo

Completar el flujo de periodo: calcular, revisar, cerrar, presentar y conservar la postura de la persona trabajadora.

### Alcance

- Consolidar reglas de bloqueo de cierre para jornadas pendientes, alertas críticas y correcciones abiertas.
- Crear reportes y versiones por periodo y trabajador.
- Implementar portal de revisión y conformidad explícita: texto aceptado, versión exacta, hash, método, fecha UTC e información técnica aplicable.
- Permitir `conforme`, `no conforme` y solicitud de aclaración; nunca conformidad por silencio.
- Si un reporte confirmado cambia, generar una nueva versión y conservar la confirmación anterior ligada a su versión.

### Criterio de salida

- No es posible cerrar un periodo con bloqueantes definidos.
- Una confirmación siempre referencia una única versión inmutable.
- La corrección posterior no transfiere confirmaciones ni altera el reporte anterior.

## 9. Bloque 5 — Evidencia, operación y piloto

### Objetivo

Entregar un flujo usable, recuperable y verificable para una empresa piloto.

### Alcance

- Generar expediente por periodo con eventos, cálculos, reglas/snapshots, alertas, correcciones, auditoría, reportes y conformidad.
- Consolidar exportación CSV operativa. Las integraciones directas requieren documentación, credenciales y ambiente de prueba.
- Documentar y probar cron de cPanel, `schedule:run`, cola `database`, almacenamiento privado, respaldos y recuperación.
- Definir datos de prueba, capacitación mínima, soporte de salida y criterios de piloto.

### Criterio de salida

- El expediente se puede generar y descargar sólo por usuarios autorizados de la empresa correcta.
- Un entorno compatible con cPanel procesa jobs y tareas programadas sin worker permanente.
- Se ejecutó un periodo controlado de piloto con resultados y hallazgos registrados.

## 10. Control de avance

Cada bloque se considera **en revisión** hasta cumplir simultáneamente:

1. Historia y alcance identificados en el backlog.
2. Código, migraciones, policies y documentación actualizados.
3. Pruebas automatizadas relevantes verdes.
4. Pruebas manuales críticas registradas cuando exista interfaz.
5. Revisión de multi-tenant, seguridad e historial no destructivo.
6. Checklist `AI-0011` revisado antes del commit.

## 11. Decision de continuidad - identidad unica y acceso por canal

El Bloque 3 de API v1 incorporara como parte de P0 la vinculacion segura entre usuario y trabajador por empresa. Una cuenta humana servira para portal web y cliente movil responsive/PWA; sus tokens personales se limitaran al usuario, empresa y scopes autorizados. Vera Time operara bajo el prefijo web `/time/...` y API `/api/v1/time/...`, reservando `/payroll/...` y `/hr/...` para productos futuros.

El kiosco conserva el mecanismo actual de activacion tecnica por empresa y codigo/NIP de marcaje. La credencial se podra vincular a la misma cuenta, pero no compartira ni solicitara la contrasena principal y no creara una sesion de portal. La implementacion incluira pruebas de aislamiento, estado y revocacion independiente para cada canal.

La entrega de una aplicacion nativa permanece fuera de P0. La prioridad inmediata es el modulo de identidad y la API reutilizable que permitan al portal responsive/PWA y a futuros clientes operar sin duplicar dominio.

## 12. Fuera de esta secuencia

Permanecen fuera de este plan inmediato: biometría, aplicación nativa, nómina integral, integraciones directas sin contrato técnico confirmado, ClickBalance API directa, Redis obligatorio y AWS como dependencia inicial.
