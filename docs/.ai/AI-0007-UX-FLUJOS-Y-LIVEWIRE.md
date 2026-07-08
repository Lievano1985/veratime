---
id: AI-0007
title: UX, flujos y Livewire para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - ux
  - livewire
  - flujos
  - veratime
---

# AI-0007 — UX, flujos y Livewire para Codex

## 1. Principio UX

La interfaz debe ser simple, operativa y clara.

No debe saturar al usuario con datos técnicos, pero debe permitir trazabilidad cuando sea necesario.

---

## 2. Roles principales

```text
super_admin
admin_empresa
rh
supervisor
nomina
juridico
trabajador
solo_lectura
```

---

## 3. Navegación administrativa

```text
Dashboard
Empresas / Centros
Personas trabajadoras
Horarios y turnos
Registro de jornada
Jornadas calculadas
Alertas
Incidencias y correcciones
Cierres de periodo
Reportes
Expedientes
Importaciones / Exportaciones
Integraciones / API
Configuración
Auditoría
```

---

## 4. Portal trabajador

```text
Mi jornada de hoy
Mi semana
Mis reportes
Mis aclaraciones
Mi perfil / acceso
```

---

## 5. Kiosco

```text
Código / NIP
Registrar entrada
Registrar salida
Iniciar pausa
Terminar pausa
Confirmación
```

---

## 6. Livewire

Livewire debe encargarse de:

- formularios;
- tablas;
- modales;
- filtros;
- llamadas a Actions;
- mostrar estados;
- interacción del usuario.

Livewire no debe contener:

- cálculo legal;
- lógica multi-tenant profunda;
- lógica de correcciones;
- lógica de reportes;
- generación de archivos;
- reglas legales.

---

## 7. Componentes sugeridos

```text
CompanySelector
DashboardCards
WorkersTable
WorkerForm
WorkerDetail
ScheduleForm
TimeEventsTable
KioskTerminal
WorkDaysTable
WorkDayDetail
AlertsTable
AlertDetail
IncidentsTable
IncidentDetail
CorrectionForm
ClosingPeriodsTable
ClosingPeriodDetail
WorkerReportReview
ReportsCenter
ImportsPanel
ApiTokensPanel
AuditLogTable
```

---

## 8. Estados visuales

Alertas:

```text
Nueva
En revisión
Pendiente información
Justificada
Corregida
Cerrada
```

Reportes:

```text
Borrador
Disponible
Conforme
No conforme
Pendiente
En aclaración
Cerrado
```

Cierre:

```text
Borrador
En cálculo
Con alertas
Revisión administrativa
Disponible para trabajador
Cerrado
Cancelado
```

---

## 9. Portal trabajador

El trabajador debe poder:

- ver jornada de hoy;
- ver registros del periodo;
- ver reporte individual;
- confirmar conforme;
- marcar no conforme;
- crear aclaración.

El trabajador no debe ver datos de otros trabajadores.

---

## 10. Kiosco

El kiosco debe:

- pedir código/NIP;
- no dejar sesión abierta;
- bloquear intentos;
- registrar fuente `kiosk`;
- mostrar confirmación;
- regresar al inicio.

---

## 11. Textos

Usar lenguaje claro y neutral.

Ejemplo correcto:

```text
Este registro requiere revisión.
```

Ejemplo incorrecto:

```text
El trabajador incumplió.
```

---

## 12. Criterio para Codex

Antes de crear un componente Livewire, Codex debe identificar:

1. qué Action/Service consumirá;
2. qué policy aplica;
3. qué estado muestra;
4. qué validación necesita;
5. qué prueba mínima agregará.


