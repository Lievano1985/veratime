# Matriz de escenario CSV - Periodo de 3 semanas

Estado: borrador de prueba manual.

Objetivo: definir la verdad esperada antes de cargar eventos, recalcular jornadas, dictaminar incidencias, cerrar periodo y comparar el CSV final.

## Resumen operativo

Esta matriz es el escenario principal de validacion manual para el CSV de asistencia del MVP. Define, antes de cargar datos, que debe pasar con cada trabajador durante tres semanas completas.

El escenario contempla:

- Cinco trabajadores activos.
- Dos centros de trabajo.
- Programacion diaria publicada antes de cargar eventos.
- Tres semanas operativas completas.
- Eventos de asistencia controlados.
- Ausencias e incidencias aprobadas cuando correspondan.
- Recalculo de jornadas.
- Dictamen de incidencias.
- Generacion y cierre de periodo de asistencia.
- Exportacion CSV final para comparar contra esta matriz.

Casos cubiertos:

- Jornada normal.
- Falta real.
- Falta justificada pagada.
- Falta justificada no pagada.
- Vacaciones.
- Incapacidad.
- Permiso pagado.
- Permiso no pagado.
- Tiempo extra.
- Domingo trabajado.
- Descanso trabajado.
- Descanso dominical trabajado.
- Descanso obligatorio trabajado.
- Retardo.
- Salida anticipada.
- Jornada incompleta.
- Descanso programado.

Resultado esperado:

- Cada trabajador debe tener una fila diaria comparable contra esta matriz.
- Las ausencias aprobadas deben reemplazar la falta operativa en el resultado.
- Las faltas reales deben permanecer como falta si no se justifican.
- Los retardos y salidas anticipadas deben mostrarse solo cuando excedan la tolerancia configurada.
- Los domingos trabajados deben identificarse aunque tambien sean descanso programado.
- Los descansos obligatorios trabajados deben reflejarse como festivo/descanso obligatorio trabajado.
- Las jornadas incompletas deben bloquear el cierre hasta corregirse o dictaminarse segun el flujo operativo.

## Alcance del escenario

- Empresa sugerida: `Empresa Demo CSV Vera Time`.
- Periodo de prueba: 2026-08-03 a 2026-08-23.
- Semana 1: 2026-08-03 a 2026-08-09.
- Semana 2: 2026-08-10 a 2026-08-16.
- Semana 3: 2026-08-17 a 2026-08-23.
- Inicio de semana operativo: lunes.
- Trabajadores: 5.
- Programacion esperada: publicada antes de cargar eventos.
- Tolerancia sugerida para la prueba:
  - Retardo: 10 minutos.
  - Salida anticipada: 10 minutos.

## Guia paso a paso para preparar la prueba

Esta seccion esta pensada para la persona que va a capturar el escenario manualmente. El objetivo es que pueda crear los datos, cargar eventos, recalcular, cerrar el periodo y comparar el CSV sin interpretar la matriz desde cero.

### 1. Crear empresa

Crear o seleccionar esta empresa de prueba:

| Campo | Valor |
|---|---|
| Nombre comercial | Empresa Demo CSV Vera Time |
| Zona horaria | America/Mexico_City |
| Estado | Activa |
| Retardo permitido | 10 minutos |
| Salida anticipada permitida | 10 minutos |

### 2. Crear centros

Crear estos centros activos:

| Centro | Zona horaria | Uso en la prueba |
|---|---|---|
| Matriz Demo | America/Mexico_City | Trabajadores administrativos y control normal |
| Planta Demo Norte | America/Mexico_City | Operacion, domingos, festivo y permisos |

### 3. Crear areas o unidades

Si el ambiente de prueba usa unidades organizacionales, crear estas areas. Si el modulo de unidades no se usara en esta corrida, se pueden omitir sin cambiar el resultado del CSV.

| Centro | Unidad sugerida | Trabajadores sugeridos |
|---|---|---|
| Matriz Demo | Administracion Demo | VT-001, VT-003, VT-005 |
| Planta Demo Norte | Operacion Demo | VT-002, VT-004 |

### 4. Crear trabajadores

Crear cinco trabajadores activos con relacion laboral vigente desde antes del 2026-08-03.

| Codigo | Nombre | Centro | Unidad sugerida |
|---|---|---|---|
| VT-001 | Ana Demo Lopez | Matriz Demo | Administracion Demo |
| VT-002 | Bruno Demo Perez | Planta Demo Norte | Operacion Demo |
| VT-003 | Carla Demo Ruiz | Matriz Demo | Administracion Demo |
| VT-004 | Diego Demo Santos | Planta Demo Norte | Operacion Demo |
| VT-005 | Elena Demo Torres | Matriz Demo | Administracion Demo |

### 5. Crear turnos base

Para esta matriz base basta con un turno diurno sencillo.

| Codigo turno | Nombre | Segmentos | Uso |
|---|---|---|---|
| TD-0800-1600 | Diurno 8 a 16 | Trabajo 08:00-16:00 | Jornada normal, faltas, permisos, retardo, salida anticipada, domingo y festivo |

### 6. Publicar programacion diaria

Publicar la programacion diaria para el rango completo 2026-08-03 a 2026-08-23.

| Dia | Programacion esperada |
|---|---|
| Lunes a viernes | Turno TD-0800-1600 |
| Sabado | Descanso programado |
| Domingo | Descanso programado |

Excepciones:

- El 2026-08-13 debe estar registrado como descanso obligatorio demo si se quiere probar `FEST`.
- Aunque el 2026-08-13 sea descanso obligatorio, se capturan eventos en los trabajadores indicados por la matriz para validar descanso obligatorio trabajado.

### 7. Capturar incidencias y ausencias previas

Antes de recalcular jornadas, crear incidencias/ausencias aprobadas para estos casos:

| Codigo | Tipo operativo esperado | Pago esperado | Evento de asistencia |
|---|---|---|---|
| FJP | Falta justificada pagada | Pagada | No |
| FJNP | Falta justificada no pagada | No pagada | No |
| VAC | Vacaciones | Pagada | No |
| INC | Incapacidad | Pagada | No |
| PP | Permiso con goce | Pagada | No |
| PNP | Permiso sin goce | No pagada | No |

### 8. Capturar eventos

Usar la tabla `Horarios diarios esperados para captura`.

Reglas de captura:

- Cuando la celda tenga horario, capturar entrada y salida.
- Cuando la celda sea `08:00-`, capturar solo entrada.
- Cuando la celda sea `-`, no capturar eventos.
- No capturar eventos para vacaciones, incapacidad, permisos, faltas justificadas ni descansos sin trabajo.

### 9. Recalcular jornadas

Generar/recalcular jornadas para:

| Parametro | Valor |
|---|---|
| Fecha inicial | 2026-08-03 |
| Fecha final | 2026-08-23 |
| Alcance | Empresa completa o los dos centros del escenario |

### 10. Revisar y dictaminar

Despues del recalculo, revisar la vista de Jornadas.

| Caso | Dictamen esperado |
|---|---|
| F | Confirmar falta o dejar como falta real segun el flujo de prueba |
| EXT | Confirmar tiempo extra |
| RET | Confirmar o justificar retardo |
| SA | Confirmar o justificar salida anticipada |
| INCPL | Corregir evento o dictaminar jornada incompleta antes de cerrar |
| DOM | Aprobar domingo trabajado |
| FEST | Aprobar descanso obligatorio trabajado |

### 11. Generar periodo y CSV

Crear un periodo de asistencia con:

| Parametro | Valor |
|---|---|
| Rango | 2026-08-03 a 2026-08-23 |
| Centro | Todos, o generar por centro si se quiere comparar separado |
| Entregable | CSV final de asistencia |

### 12. Comparar resultado

Comparar el CSV contra:

- `Matriz diaria esperada`.
- `Horarios diarios esperados para captura`.
- `Resultado esperado por trabajador`.
- `Resultado esperado para CSV`.

Si el CSV no coincide, revisar en este orden:

1. Programacion diaria publicada.
2. Eventos capturados.
3. Incidencias/ausencias aprobadas.
4. Recalculo de jornadas.
5. Dictamenes pendientes.
6. Configuracion de tolerancias.

## Ejecucion recomendada

Para que la prueba no sea pesada, se puede ejecutar en dos fases:

| Fase | Rango | Objetivo |
|---|---|---|
| Fase A | 2026-08-03 a 2026-08-16 | Validar flujo principal con faltas, ausencias, festivo, domingo, retardo, salida anticipada y extra |
| Fase B | 2026-08-17 a 2026-08-23 | Validar repeticion del comportamiento y casos adicionales |

La comparacion completa del CSV se considera cerrada cuando ambas fases coinciden con esta matriz.

## Leyenda

| Codigo | Significado esperado |
|---|---|
| N | Jornada normal completa |
| F | Falta real no justificada |
| FJP | Falta justificada pagada |
| FJNP | Falta justificada no pagada |
| VAC | Vacaciones |
| INC | Incapacidad |
| PP | Permiso pagado |
| PNP | Permiso no pagado |
| EXT | Tiempo extra |
| DOM | Domingo trabajado; si el dia publicado era descanso, tambien cuenta como descanso trabajado |
| FEST | Descanso obligatorio trabajado |
| RET | Retardo |
| SA | Salida anticipada |
| INCPL | Jornada incompleta |
| DES | Descanso programado |

## Trabajadores base

| Codigo | Trabajador | Centro sugerido | Perfil del escenario |
|---|---|---|---|
| VT-001 | Ana Demo Lopez | Matriz Demo | Faltas y justificaciones |
| VT-002 | Bruno Demo Perez | Planta Demo Norte | Domingo, festivo y tiempo extra |
| VT-003 | Carla Demo Ruiz | Matriz Demo | Retardos y salidas anticipadas |
| VT-004 | Diego Demo Santos | Planta Demo Norte | Vacaciones, incapacidad y permisos |
| VT-005 | Elena Demo Torres | Matriz Demo | Control normal y una jornada incompleta |

## Matriz diaria esperada

| Fecha | Dia | VT-001 Ana | VT-002 Bruno | VT-003 Carla | VT-004 Diego | VT-005 Elena |
|---|---|---|---|---|---|---|
| 2026-08-03 | Lun | N | N | N | VAC | N |
| 2026-08-04 | Mar | FJP | N | RET | VAC | N |
| 2026-08-05 | Mie | N | EXT | SA | VAC | N |
| 2026-08-06 | Jue | N | N | RET+SA | INC | N |
| 2026-08-07 | Vie | F | N | N | INC | INCPL |
| 2026-08-08 | Sab | DES | DES | DES | DES | DES |
| 2026-08-09 | Dom | DES | DOM | DES | DES | DES |
| 2026-08-10 | Lun | N | N | N | INC | N |
| 2026-08-11 | Mar | FJNP | N | RET | PP | N |
| 2026-08-12 | Mie | N | EXT | N | PP | N |
| 2026-08-13 | Jue | FEST | FEST | FEST+SA | FEST | FEST |
| 2026-08-14 | Vie | N | N | N | N | N |
| 2026-08-15 | Sab | DES | DES | DES | DES | DES |
| 2026-08-16 | Dom | DES | DOM+EXT | DES | DES | DES |
| 2026-08-17 | Lun | N | N | N | N | N |
| 2026-08-18 | Mar | FJP | N | RET | PNP | N |
| 2026-08-19 | Mie | N | N | SA | PNP | N |
| 2026-08-20 | Jue | N | EXT | RET+SA | N | N |
| 2026-08-21 | Vie | N | N | N | N | N |
| 2026-08-22 | Sab | DES | DES | DES | DES | DES |
| 2026-08-23 | Dom | DES | DES | DES | DES | DES |

## Horarios diarios esperados para captura

Esta tabla traduce la matriz anterior a horarios concretos de captura. Cuando el escenario sea falta, ausencia, permiso, vacaciones, incapacidad o descanso sin trabajo, se usa `-` porque no debe capturarse evento de asistencia para ese trabajador y fecha.

| Fecha | Dia | VT-001 Ana | VT-002 Bruno | VT-003 Carla | VT-004 Diego | VT-005 Elena |
|---|---|---|---|---|---|---|
| 2026-08-03 | Lun | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 | - | 08:00-16:00 |
| 2026-08-04 | Mar | - | 08:00-16:00 | 08:25-16:00 | - | 08:00-16:00 |
| 2026-08-05 | Mie | 08:00-16:00 | 08:00-18:00 | 08:00-15:35 | - | 08:00-16:00 |
| 2026-08-06 | Jue | 08:00-16:00 | 08:00-16:00 | 08:25-15:35 | - | 08:00-16:00 |
| 2026-08-07 | Vie | - | 08:00-16:00 | 08:00-16:00 | - | 08:00- |
| 2026-08-08 | Sab | - | - | - | - | - |
| 2026-08-09 | Dom | - | 08:00-16:00 | - | - | - |
| 2026-08-10 | Lun | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 | - | 08:00-16:00 |
| 2026-08-11 | Mar | - | 08:00-16:00 | 08:25-16:00 | - | 08:00-16:00 |
| 2026-08-12 | Mie | 08:00-16:00 | 08:00-18:00 | 08:00-16:00 | - | 08:00-16:00 |
| 2026-08-13 | Jue | 08:00-16:00 | 08:00-16:00 | 08:00-15:35 | 08:00-16:00 | 08:00-16:00 |
| 2026-08-14 | Vie | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 |
| 2026-08-15 | Sab | - | - | - | - | - |
| 2026-08-16 | Dom | - | 08:00-18:00 | - | - | - |
| 2026-08-17 | Lun | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 |
| 2026-08-18 | Mar | - | 08:00-16:00 | 08:25-16:00 | - | 08:00-16:00 |
| 2026-08-19 | Mie | 08:00-16:00 | 08:00-16:00 | 08:00-15:35 | - | 08:00-16:00 |
| 2026-08-20 | Jue | 08:00-16:00 | 08:00-18:00 | 08:25-15:35 | 08:00-16:00 | 08:00-16:00 |
| 2026-08-21 | Vie | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 | 08:00-16:00 |
| 2026-08-22 | Sab | - | - | - | - | - |
| 2026-08-23 | Dom | - | - | - | - | - |

Notas de captura:

- `08:00-16:00` representa entrada y salida normales.
- `08:00-18:00` representa tiempo extra.
- `08:25-16:00` representa retardo.
- `08:00-15:35` representa salida anticipada.
- `08:25-15:35` representa retardo y salida anticipada en la misma jornada.
- `08:00-` representa jornada incompleta con entrada sin salida.
- `-` significa no capturar eventos de asistencia para ese dia.

## Eventos esperados por codigo

Esta tabla explica que debe capturarse cuando aparezca cada codigo en la matriz diaria.

| Codigo | Entrada | Salida | Incidencia/ausencia previa | Dictamen esperado |
|---|---|---|---|---|
| N | 08:00 | 16:00 | No | Sin incidencia |
| F | - | - | No | Falta real o falta confirmada |
| FJP | - | - | Falta justificada pagada | Aprobada |
| FJNP | - | - | Falta justificada no pagada | Aprobada |
| VAC | - | - | Vacaciones | Aprobada |
| INC | - | - | Incapacidad | Aprobada |
| PP | - | - | Permiso con goce | Aprobada |
| PNP | - | - | Permiso sin goce | Aprobada |
| EXT | 08:00 | 18:00 | No | Confirmar tiempo extra |
| DOM | 08:00 | 16:00 | No | Aprobar domingo trabajado y descanso trabajado |
| DOM+EXT | 08:00 | 18:00 | No | Aprobar domingo trabajado, descanso trabajado y tiempo extra |
| FEST | 08:00 | 16:00 | No | Aprobar descanso obligatorio trabajado |
| FEST+SA | 08:00 | 15:35 | No | Aprobar descanso obligatorio trabajado y confirmar salida anticipada |
| RET | 08:25 | 16:00 | No | Confirmar o justificar retardo |
| SA | 08:00 | 15:35 | No | Confirmar o justificar salida anticipada |
| RET+SA | 08:25 | 15:35 | No | Confirmar o justificar retardo y salida anticipada |
| INCPL | 08:00 | - | No | Corregir evento o dictaminar jornada incompleta |
| DES | - | - | No | Descanso programado sin incidencia |

## Resultado esperado por trabajador

| Trabajador | Jornadas normales | Faltas reales | Justificadas pagadas | Justificadas no pagadas | Vacaciones | Incapacidades | Permisos pagados | Permisos no pagados | Domingos trabajados | Descansos trabajados | Descansos dominicales trabajados | Festivos trabajados | Dias con extra | Dias con retardo | Dias con salida anticipada | Jornadas incompletas |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| VT-001 Ana | 10 | 1 | 2 | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 0 | 0 | 0 | 0 |
| VT-002 Bruno | 11 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 2 | 2 | 2 | 1 | 4 | 0 | 0 | 0 |
| VT-003 Carla | 11 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 0 | 4 | 3 | 0 |
| VT-004 Diego | 6 | 0 | 0 | 0 | 3 | 3 | 2 | 2 | 0 | 0 | 0 | 1 | 0 | 0 | 0 | 0 |
| VT-005 Elena | 13 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 0 | 0 | 0 | 1 |

Notas:

- Las jornadas normales excluyen descansos programados y dias cubiertos por ausencia/incidencia aprobada.
- `INCPL` debe bloquear cierre hasta dictaminar o corregir.
- `F` debe permanecer como falta real si no se envia a incidencia/ausencia.
- `FJP`, `FJNP`, `VAC`, `INC`, `PP` y `PNP` deben existir como `attendance_incidents` aprobadas antes de cerrar el periodo.
- `RET` y `SA` deben aparecer como alertas cuando superen tolerancia configurada.
- Para probar `FEST`, el 2026-08-13 se captura como descanso obligatorio interno de empresa. Por alcance, afecta a todos los trabajadores de la empresa que trabajaron ese dia, no solo a un centro o trabajador.

## Resultado esperado para CSV

El CSV final debe permitir validar, como minimo:

| Campo CSV | Esperado |
|---|---|
| `empleado_id` / codigo | Coincide con el trabajador |
| `fecha` | Una fila por trabajador y dia aplicable del periodo |
| `tipo_dia` | Trabajo, descanso, domingo, descanso obligatorio o ausencia segun corresponda |
| `horas_normales` | Minutos/horas ordinarias calculadas |
| `horas_extra_dobles` | Minutos/horas extra dobles cuando aplique |
| `horas_extra_triples` | Minutos/horas extra triples cuando aplique |
| `minutos_retardo` | Mayor a cero solo en dias RET despues de tolerancia |
| `minutos_salida_anticipada` | Mayor a cero solo en dias SA despues de tolerancia |
| `domingo_trabajado` / `horas_domingo_trabajado` | Si y mayor a cero en DOM |
| `descanso_programado` | Si cuando el dia publicado era descanso |
| `descanso_trabajado` | Si cuando el dia publicado era descanso y hubo tiempo trabajado |
| `descanso_domingo_trabajado` | Si cuando el dia publicado era descanso, fue domingo y hubo tiempo trabajado |
| `descanso_obligatorio_trabajado` / `horas_festivo_trabajado` | Si y mayor a cero en FEST |
| `tiene_incidencia` | Si cuando existe alerta o ausencia/incidencia aplicable |
| `tipo_incidencia` | Falta, ausencia, extra, domingo, festivo, retardo, salida anticipada, incompleta segun aplique |
| `estatus_incidencia` | Pendiente, dictaminada, justificada, cerrada o equivalente operativo |

## Flujo de prueba propuesto

1. Preparar empresa, centros, trabajadores, turnos, perfiles y programacion diaria publicada para las tres semanas.
2. Vaciar datos operativos de prueba: eventos, jornadas, calculos, alertas y periodos.
3. Cargar eventos controlados segun esta matriz.
4. Crear incidencias/ausencias aprobadas para FJP, FJNP, VAC, INC, PP y PNP.
5. Ejecutar recalculo de jornadas.
6. Revisar Jornadas y confirmar alertas esperadas.
7. Dictaminar lo necesario.
8. Recalcular si hubo cambios por dictamen o incidencia/ausencia.
9. Generar periodo de asistencia del 2026-08-03 al 2026-08-23.
10. Validar bloqueantes.
11. Cerrar periodo.
12. Descargar CSV.
13. Comparar CSV contra esta matriz.

## Preparacion esperada antes de cargar eventos

Antes de insertar eventos se debe confirmar:

- La empresa demo tiene configurada tolerancia de retardo y salida anticipada.
- Existen los centros usados por la matriz: `Matriz Demo` y `Planta Demo Norte`, o sus equivalentes en el ambiente de prueba.
- Los trabajadores `VT-001` a `VT-005` existen y estan activos.
- Cada trabajador tiene relacion laboral activa en el centro que corresponde.
- Existen turnos y perfiles suficientes para publicar las tres semanas.
- La programacion diaria esta publicada del 2026-08-03 al 2026-08-23.
- Sabados y domingos estan publicados como descanso cuando la matriz indica `DES`.
- El dia `2026-08-13` esta configurado como descanso obligatorio demo si se va a probar `FEST`.
- No existen eventos previos que contaminen el escenario.
- No existen jornadas, calculos, alertas o periodos anteriores para el mismo rango si se busca una comparacion limpia.

## Carga de datos esperada

La carga de datos debe hacerse en este orden:

1. Limpiar eventos, jornadas, calculos, alertas y periodos del rango de prueba.
2. Crear eventos de entrada/salida para los codigos `N`, `EXT`, `DOM`, `FEST`, `RET`, `SA` e `INCPL`.
3. No crear eventos para `F`, `FJP`, `FJNP`, `VAC`, `INC`, `PP`, `PNP` ni `DES`, salvo que el escenario indique lo contrario.
4. Crear incidencias/ausencias aprobadas para `FJP`, `FJNP`, `VAC`, `INC`, `PP` y `PNP`.
5. Recalcular jornadas.
6. Dictaminar las alertas pendientes.
7. Recalcular nuevamente si el dictamen modifica el resultado operativo.

## Siguiente accion tecnica

Una vez validada esta matriz, el siguiente paso es preparar o ajustar el query/seeder temporal de prueba que:

- Inserte eventos exactamente conforme a la matriz.
- Cree las incidencias/ausencias aprobadas esperadas.
- No cree datos reales.
- No modifique trabajadores o centros fuera del escenario.
- Permita limpiar y repetir la prueba sin dejar datos duplicados.

Despues de ejecutar la carga, el CSV descargado debe compararse contra las tablas de resultado esperado de este documento.

Este archivo no sustituye las pruebas automatizadas. Su funcion es servir como contrato manual de validacion integral contra el CSV real.

## Pendiente para siguiente paso

- Crear query/script de insercion de eventos cuando el escenario sea aprobado.
- Definir horas exactas por evento para cada codigo de escenario.
- Definir que dias FEST usaremos como descanso obligatorio demo.
- Confirmar si `VT-005 INCPL` se corregira o se dictaminara antes de cierre.
