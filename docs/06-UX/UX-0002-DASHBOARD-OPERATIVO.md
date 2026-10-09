---
id: UX-0002
title: Dashboard operativo Vera Time
project: Vera Time
status: Approved for MVP implementation
owner: Product
updated: 2026-10-09
---

# UX-0002 - Dashboard operativo

## Objetivo

El Dashboard operativo es la vista diaria de administradores, RH y usuarios con alcance operativo. Permite conocer el estado de la jornada, detectar situaciones que requieren revisión y abrir el detalle ya existente de Jornadas. No declara incumplimientos definitivos ni genera, modifica o elimina evidencia.

La ruta vigente respeta la segmentación de producto: `/time/dashboard` (nombre de ruta `dashboard`).

## Lenguaje y alcance

El lenguaje será preventivo y neutral: **ausencias por validar**, **revisión requerida**, **alerta preventiva**, **registro incompleto** y **riesgo de jornada**. No se usarán multas, infracciones, faltas definitivas ni incumplimientos confirmados.

La primera versión incluye:

- fecha consultada y filtro opcional por centro;
- trabajadores activos;
- trabajadores trabajando ahora y en pausa;
- ausencias por validar y personal por ingresar, conforme al horario publicado y tolerancia de entrada de la empresa;
- KPI operativos de asistencia y puntualidad;
- indicadores diarios de jornada y descansos;
- indicadores semanales de acumulación;
- distribución visual de las alertas por segmento;
- enlaces de sólo consulta a Jornadas, con los filtros correspondientes.

## Composición visual

La pantalla organiza el contenido en bloques operativos: encabezado con filtros y hora de actualización, panorama de jornada con indicador circular de actividad y KPI de asistencia/puntualidad, indicadores diarios, indicadores semanales y una sección final de distribución con barras de progreso y recordatorio de uso. Las tarjetas utilizan gradientes suaves por estado y la paleta de Vera Time; el color comunica prioridad, no una sanción.

Quedan fuera: sanciones, aprobación formal de incidencias, cálculo legal nuevo, reportes oficiales, nómina, API pública del dashboard y modificar eventos desde esta pantalla.

## Fuentes de datos

El resumen se construye fuera de Livewire mediante `BuildOperationalDashboardAction`, con datos ya persistidos:

- `workers` y relaciones laborales activas;
- `daily_schedule_assignments`;
- `time_events` válidos;
- `work_days` y `alerts` abiertas.

La vista no calcula jornadas ni crea alertas. Las alertas existentes son la fuente para retrasos, jornadas incompletas, tiempo extra, domingo, descansos y situaciones equivalentes. Los indicadores diarios muestran únicamente reglas ya calculadas por el dominio. Los indicadores semanales consultan alertas abiertas de la semana y el promedio de horas se deriva de cálculos activos, sin modificar evidencia.

## Indicadores preventivos de jornada

El dominio evalúa y conserva alertas por jornada para: límite diario aplicable, tiempo extra diario mayor a tres horas, jornada mayor a diez horas, pausa registrada menor a treinta minutos en jornadas de al menos seis horas, domingo, descanso obligatorio, descanso asignado y registro incompleto. Cuando existe fecha de nacimiento registrada, también evalúa revisión de jornada mayor a seis horas para persona menor de dieciséis años y tiempo extra o clasificación nocturna para persona menor de dieciocho años.

Por persona trabajadora y semana natural, el dominio concentra: horas totales contra el límite semanal que quedó en el snapshot legal, tiempo extra superior a nueve horas, más de tres días con tiempo extra, semana sin descanso y domingos trabajados. Cada alerta semanal usa una huella por empresa, relación laboral, persona y semana; un recálculo la actualiza o la cierra automáticamente si deja de aplicar.

Las nuevas reglas leen los límites diario y semanal ya versionados en `result_snapshot.ordinary_overtime`; no crean configuraciones de empresa ni alteran marcajes, horarios publicados o cálculos históricos. La fecha de nacimiento es opcional y no se infiere de CURP: sin ella, las reglas de edad no generan una alerta.

## Reglas iniciales

### Trabajando ahora

Se toma el último evento válido de la persona para la fecha consultada y la fecha previa, para contemplar turnos nocturnos. `clock_in` y `break_end` cuentan como trabajando; `break_start` cuenta como pausa.

### Ausencia por validar y personal por ingresar

Se parte de una asignación diaria publicada de tipo turno, flexible o bajo demanda. Si no hay una entrada válida:

- después de `hora programada + tolerancia`: ausencia por validar;
- hasta ese instante: personal por ingresar.

La ausencia no se convierte automáticamente en falta. Un evento válido posterior o una captura manual justificada se ve en Jornadas y modifica el resultado de los procesos operativos existentes.

## Seguridad y multi-tenant

La pantalla exige sesión, empresa activa, producto Vera Time y la misma autorización de consulta de Jornadas. Todos los conteos filtran `company_id`. Los roles con alcance limitado sólo reciben centros y relaciones laborales comprendidos en su alcance; no se muestran ni cuentan datos fuera de éste.

## Pruebas mínimas

- invitado o usuario sin empresa activa no puede abrirla;
- no se cuentan trabajadores, eventos ni alertas de otra empresa;
- una entrada sin salida se muestra como personal trabajando;
- una pausa abierta se muestra como pausa;
- una programación sin entrada cambia de personal por ingresar a ausencia por validar al terminar la tolerancia;
- domingo, descanso obligatorio y captura manual se agrupan sin lenguaje sancionatorio;
- el dashboard no persiste datos.
