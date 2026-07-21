---
title: Inventario funcional del modulo de horarios
project: Vera Time
status: Draft
updated: 2026-07-20
---

# Inventario funcional del modulo de horarios

## 1. Alcance

Este inventario consolida el estado funcional real del modulo de horarios y programacion diaria hasta F5B. Se basa en rutas, Actions, policies, pruebas y seeders existentes.

## 2. Tabla de capacidades

| ID | Capacidad | Bloque | Ruta o entrada | Action principal | Policy | Prueba automatizada | Seeder | Estado | Observaciones |
|---|---|---|---|---|---|---|---|---|---|
| INV-001 | Centros | Sprint 1B | `/centers` | Actions de centros | `CenterPolicy` | `Sprint1B` | `VeraTimeDemoSeeder` | implementado | Base para programacion por centro |
| INV-002 | Trabajadores y relaciones | Sprint 1C | `/workers` | Actions de workers | `WorkerPolicy`, `EmploymentRelationshipPolicy` | `Sprint1C` | `VeraTimeDemoSeeder` | implementado | La programacion diaria usa relacion laboral |
| INV-003 | Unidades organizacionales | B1/B2 | `/organization/units` | `CreateOrganizationalUnitAction`, `UpdateOrganizationalUnitAction` | `OrganizationalUnitPolicy` | `SprintB1`, `SprintB2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | Jerarquia MVP `department -> area -> team` |
| INV-004 | Asignacion de unidad primaria | B1/B2 | `/organization/assignments` | `AssignPrimaryOrganizationalUnitAction`, `ReplacePrimaryOrganizationalUnitAction` | `EmploymentUnitAssignmentPolicy` | `SprintB1`, `SprintB2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | La unidad primaria se congela al generar programacion diaria |
| INV-005 | Apoyo temporal | B1/B2 | `/organization/assignments` | `AssignTemporarySupportAction`, `EndTemporarySupportAction` | `EmploymentUnitAssignmentPolicy` | `SprintB1`, `SprintB2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | No cambia herencia de perfil |
| INV-006 | Alcance supervisor | B1/B2 | `/organization/scopes`, `/organization/my-scope` | `AssignOperationalScopeAction`, `ResolveUserOperationalScopeAction` | `OperationalScopeAssignmentPolicy` | `SprintB1`, `SprintB2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | Supervisor requiere alcance explicito |
| INV-007 | Catalogo de turnos | C | `/scheduling/shifts` | `CreateShiftTemplateAction`, `UpdateShiftTemplateAction` | `ShiftTemplatePolicy` | `BlockC` | `VeraTimeDemoSeeder`, `VeraTimeScheduleProfileScenarioSeeder` | implementado | Usa `shift_templates` y `shift_template_segments` |
| INV-008 | Segmentos de turno | C | `/scheduling/shifts` | `ValidateShiftTemplateSegmentsAction` | `ShiftTemplatePolicy` | `BlockC` | `VeraTimeDemoSeeder` | implementado | Soporta trabajo, descansos, offsets y cruce de medianoche |
| INV-009 | Perfil patron semanal | D1/D2 | `/scheduling/profiles` | `CreateScheduleProfileAction`, `ReplaceScheduleProfileWeeklyRulesAction` | `ScheduleProfilePolicy` | `BlockD1`, `BlockD2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | `profile_type=pattern`, `pattern_mode=weekly` |
| INV-010 | Perfil ciclo | E1/E2 | `/scheduling/profiles` | `ReplaceScheduleProfileCycleRulesAction` | `ScheduleProfilePolicy` | `BlockE1`, `BlockE2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | Usa dia 1..n y `effective_from` como ancla |
| INV-011 | Perfil calendario | D1/D2 | `/scheduling/profiles` | `CreateScheduleProfileAction` | `ScheduleProfilePolicy` | `BlockD1`, `BlockD2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | Genera pendiente hasta definicion diaria o CSV |
| INV-012 | Perfil flexible | E1/E2 | `/scheduling/profiles` | `ReplaceScheduleProfileFlexibleRulesAction` | `ScheduleProfilePolicy` | `BlockE1`, `BlockE2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | Minutos requeridos y ventana opcional |
| INV-013 | Perfil bajo demanda | E1/E2 | `/scheduling/profiles` | `ReplaceScheduleProfileOnCallRulesAction` | `ScheduleProfilePolicy` | `BlockE1`, `BlockE2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | Disponibilidad no es tiempo trabajado |
| INV-014 | Asignaciones de perfiles | D1/D2 | `/scheduling/profile-assignments` | `AssignScheduleProfileAction`, `ReplaceScheduleProfileAssignmentAction` | `ScheduleProfilePolicy` | `BlockD1`, `BlockD2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | Scope empresa, centro, unidad o relacion |
| INV-015 | Resolucion jerarquica | D1/E1/F2 | Dominio | `ResolveScheduleProfileForRelationshipAction`, `ResolveScheduleProfileRuleForDateAction` | N/A | `BlockD1`, `BlockE1`, `BlockF2` | `VeraTimeScheduleProfileScenarioSeeder` | implementado | Prioridad relacion, unidad, centro, empresa |
| INV-016 | Nucleo diario | F1 | Dominio | `CreateScheduleBatchAction`, `ReplaceDraftDailyScheduleAssignmentAction` | `ScheduleBatchPolicy`, `DailyScheduleAssignmentPolicy` | `BlockF1` | `VeraTimeDailyScheduleScenarioSeeder` | implementado | Crea batches, asignaciones y segmentos |
| INV-017 | Generacion draft | F2/F3B | `/scheduling/daily` | `GenerateDraftScheduleBatchFromProfilesAction` | `ScheduleBatchPolicy` | `BlockF2`, `BlockF3B` | `VeraTimeDailyScheduleScenarioSeeder` | implementado | Modos `missing_only` y `refresh_profile_generated` |
| INV-018 | Edicion diaria individual | F3B | `/scheduling/daily` | `ReplaceDraftDailyScheduleAssignmentAction` | `DailyScheduleAssignmentPolicy` | `BlockF3B` | `VeraTimeDailyScheduleScenarioSeeder` | implementado | Solo draft |
| INV-019 | Edicion masiva basica | F3B | `/scheduling/daily` | `BulkReplaceDraftDailyScheduleAssignmentsAction` | `DailyScheduleAssignmentPolicy` | `BlockF3B` | `VeraTimeDailyScheduleScenarioSeeder` | implementado | Transaccion completa |
| INV-020 | Validacion antes de publicar | F3A/F3B | `/scheduling/daily` | `ValidateScheduleBatchForPublicationAction` | `ScheduleBatchPolicy` | `BlockF3A`, `BlockF3B` | `VeraTimePublishedScheduleScenarioSeeder` | implementado | Bloquea pendientes y conflictos |
| INV-021 | Publicacion | F3A/F3B | `/scheduling/daily` | `PublishScheduleBatchAction` | `ScheduleBatchPolicy` | `BlockF3A`, `BlockF3B` | `VeraTimePublishedScheduleScenarioSeeder` | implementado | Persiste snapshot y hash |
| INV-022 | Snapshot e integridad | F1/F3A/F3B | `/scheduling/daily` | `BuildScheduleBatchSnapshotAction`, `VerifyPublishedScheduleBatchSnapshotAction` | `ScheduleBatchPolicy` | `BlockF1`, `BlockF3A`, `BlockF3B` | `VeraTimePublishedScheduleScenarioSeeder` | implementado | Verifica JSON persistido |
| INV-023 | Correcciones versionadas | F4 | `/scheduling/daily` | `CreateCorrectiveScheduleBatchAction`, `PublishCorrectiveScheduleBatchAction` | `ScheduleBatchPolicy` | `BlockF4` | `VeraTimeCorrectedScheduleScenarioSeeder` | implementado | Version anterior queda `superseded` |
| INV-024 | Historial de versiones | F4 | `/scheduling/daily` | `ResolveScheduleBatchVersionChainAction` | `ScheduleBatchPolicy` | `BlockF4` | `VeraTimeCorrectedScheduleScenarioSeeder` | implementado | Cadena lineal |
| INV-025 | CSV dominio | F5A | Dominio | `CreateDailyScheduleCsvImportAction`, `ValidateDailyScheduleCsvImportAction`, `ApplyDailyScheduleCsvImportAction` | `ScheduleBatchPolicy` | `BlockF5A` | `VeraTimeDailyScheduleCsvScenarioSeeder` | implementado | No publica |
| INV-026 | CSV UI | F5B | `/scheduling/daily` | `StoreDailyScheduleCsvUploadAction`, `ListDailyScheduleCsvImportsAction` | `ScheduleBatchPolicy` | `BlockF5B` | `VeraTimeDailyScheduleCsvScenarioSeeder` | implementado | Carga privada, preview, aplicar, cancelar |
| INV-027 | Descarga plantilla CSV | F5B | `/scheduling/daily/csv/template` | `GenerateDailyScheduleCsvTemplateAction` | `ScheduleBatchPolicy` | `BlockF5B` | N/A | implementado | CSV version 1, 15 encabezados |
| INV-028 | Descarga errores CSV | F5B | `/scheduling/daily/imports/{importBatch}/errors` | `GenerateDailyScheduleCsvErrorReportAction` | `ScheduleBatchPolicy` | `BlockF5B` | `VeraTimeDailyScheduleCsvScenarioSeeder` | implementado | Protege formulas CSV |
| INV-029 | Horarios legacy | Sprint 2A | `/schedules` | `SaveScheduleDaysAction` | `SchedulePolicy` | `Sprint2A` | `VeraTimeDemoSeeder` | legacy | Conservado temporalmente |
| INV-030 | Asignaciones legacy | Sprint 2B | `/schedule-assignments` | `CreateScheduleAssignmentAction` | `ScheduleAssignmentPolicy` | `Sprint2B` | `VeraTimeDemoSeeder` | legacy | Sin doble escritura con modelo WFM |
| INV-031 | `work_days` | Futuro | N/A | N/A | N/A | N/A | N/A | pendiente | No existe tabla operativa |
| INV-032 | API WFM | Futuro | N/A | N/A | N/A | N/A | N/A | pendiente | No hay endpoints WFM funcionales |

## 3. Componentes legacy conservados

- `schedules`
- `schedule_days`
- `schedule_breaks`
- `schedule_assignments`
- `ResolveScheduleForWorkerDateAction`
- rutas `/schedules` y `/schedule-assignments`

Estos componentes siguen disponibles internamente por compatibilidad de desarrollo, pero el modelo WFM nuevo usa `shift_templates`, `schedule_profiles`, `schedule_batches`, `daily_schedule_assignments` y `daily_schedule_segments`.

No existe doble escritura entre legacy y WFM. Tampoco existe fallback del resolver publicado hacia perfiles o legacy.

## 4. Hallazgos documentales preliminares

| ID | Descripcion | Evidencia | Severidad estimada | Recomendacion | Requiere revision manual |
|---|---|---|---|---|---|
| HP-001 | Algunos documentos historicos usan frases genericas `CSV/XLSX` para capacidades futuras, mientras el sistema real ya tiene CSV web de programacion diaria pero no XLSX. | `UX-0001`, `ADR-0002`, `API-0001` conservan menciones generales a CSV/XLSX futuro. | S4 Menor | Mantener aclaracion por contexto: CSV diario existe, XLSX y API WFM no. | No |
| HP-002 | `CURRENT_STATE.md` conserva secciones historicas de ramas antiguas como UX-01 y Sprint 2G; pueden confundir si se leen como estado activo actual. | `docs/.ai/CURRENT_STATE.md` mezcla estado compacto y notas historicas. | S3 Media | En una limpieza posterior, separar historial de estado vigente. | Si |
| HP-003 | Las rutas legacy `/schedules` y `/schedule-assignments` siguen existiendo aunque no aparecen como flujo principal de WFM. | `routes/web.php` y sidebar actual. | S3 Media | Revisar retiro en Bloque J antes de motor legal. | Si |
