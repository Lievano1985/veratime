---
id: AI-0003
title: Arquitectura y patrones para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - arquitectura
  - patrones
  - veratime
---

# AI-0003 — Arquitectura y patrones para Codex

## 1. Arquitectura aprobada

Vera Time usará:

```text
Monolito modular Laravel
```

No usar microservicios para el MVP.

---

## 2. Enfoque aprobado

```text
Domain-first + API-first pragmático y bidireccional
```

Significa:

- el dominio manda;
- la API es canal oficial;
- Livewire no concentra lógica;
- jobs, API, CSV y web usan las mismas Actions/Services;
- lo creado por API debe verse en web;
- lo creado por web debe estar disponible para API cuando aplique.

---

## 3. Patrón obligatorio de flujo

```text
Entrada:
Livewire / API / CSV / Job / Command

↓ llama a

Application Action / Application Service

↓ usa

Domain Service / Value Object / Policy

↓ persiste en

Eloquent Model / Repository si se justifica / Database
```

---

## 4. Estructura sugerida

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
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/
│   ├── Requests/
│   └── Resources/
├── Livewire/
└── Models/
```

La estructura exacta puede adaptarse al proyecto, pero debe conservar separación de dominio.

---

## 5. Actions sugeridas

```text
CreateCompanyAction
CreateWorkerAction
UpdateWorkerAction
AssignScheduleAction
RegisterTimeEventAction
RecalculateWorkDayAction
GeneratePreventiveAlertsAction
CreateIncidentAction
ProposeCorrectionAction
ApproveCorrectionAction
CreateClosingPeriodAction
GeneratePeriodReportsAction
ConfirmPeriodReportAction
GeneratePayrollExportAction
GenerateEvidencePackageAction
```

---

## 6. Qué NO hacer

No poner cálculo de jornada dentro de:

```text
Livewire component
Controller API
Blade
Job
Command
```

No duplicar la lógica de crear evento en:

```text
WebRegisterEvent
ApiRegisterEvent
CsvRegisterEvent
```

Crear una sola Action:

```text
RegisterTimeEventAction
```

 y consumirla desde todos los canales.

---

## 7. Eventos y jobs

Los jobs deben ser orquestadores, no dueños del dominio.

Correcto:

```text
ProcessImportJob
→ calls ImportTimeEventsAction
→ calls RegisterTimeEventAction
```

Incorrecto:

```text
ProcessImportJob contiene toda la lógica de validación legal y creación de eventos
```

---

## 8. Services vs Actions

Usar Actions para casos de uso:

```text
RegisterTimeEventAction
ApproveCorrectionAction
GeneratePeriodReportsAction
```

Usar Domain Services para reglas:

```text
WorkDayCalculator
LegalRuleResolver
AlertEvaluator
OvertimeCalculator
WorkDayClassifier
```

Usar Value Objects para conceptos:

```text
TimeRange
WorkDuration
LegalRuleSnapshot
```

---

## 9. Policies

Toda acción sensible debe pasar por policy o permiso equivalente:

- ver trabajador;
- editar trabajador;
- registrar evento manual;
- anular evento;
- aprobar corrección;
- cerrar periodo;
- descargar evidencia;
- usar API;
- administrar token.

---

## 10. Auditoría

Operaciones críticas deben registrar audit log:

- evento manual;
- anulación;
- corrección;
- aprobación;
- cierre;
- conformidad;
- exportación;
- descarga de expediente;
- token API;
- cambio de rol.


