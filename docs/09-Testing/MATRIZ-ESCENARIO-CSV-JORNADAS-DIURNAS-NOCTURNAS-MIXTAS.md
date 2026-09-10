# Matriz de escenario CSV - Jornadas diurnas, nocturnas y mixtas

Estado: borrador de prueba manual.

Objetivo: validar que Vera Time clasifica correctamente jornadas diurnas, nocturnas y mixtas, sin mezclar esa clasificacion con horas extra, retardos, salidas anticipadas, descansos trabajados o incidencias.

Este escenario debe ejecutarse como un segundo escenario dentro de la misma empresa demo, pero en un centro nuevo e independiente. La matriz anterior de periodo de tres semanas se conserva como escenario base de jornadas diurnas; esta matriz agrega el caso especializado de turnos nocturnos y mixtos en el mismo rango operativo.

## Resumen operativo

Esta matriz es el archivo base para la siguiente prueba manual de CSV y calculo de jornadas. Debe usarse para preparar un escenario controlado donde se puedan comparar los resultados reales de Vera Time contra un resultado esperado previamente definido.

El escenario contempla:

- Un centro nuevo e independiente para no mezclar resultados con pruebas anteriores.
- Cinco trabajadores nuevos.
- Turnos diurnos, nocturnos, mixtos y jornada partida.
- Programacion diaria publicada antes de cargar eventos.
- Eventos reales de prueba cargados conforme a esta matriz.
- Incidencias y ausencias operativas cuando correspondan.
- Recalculo de jornadas.
- Dictamen de incidencias.
- Generacion del periodo de asistencia.
- Exportacion CSV final para comparar contra la matriz.

Casos cubiertos:

- Retardo.
- Salida anticipada.
- Tiempo extra.
- Falta real.
- Falta justificada pagada.
- Vacaciones.
- Incapacidad.
- Domingo trabajado.
- Descanso programado.
- Descanso trabajado cuando el dia publicado era descanso.
- Jornada incompleta.
- Jornadas nocturnas y mixtas con cruce de medianoche.

Resultado esperado:

- Cada trabajador debe reflejar en el CSV los dias, eventos, incidencias y acumulados esperados.
- Los dias sin eventos solo deben generar falta cuando el dia ya paso y estaba programado.
- Las ausencias aprobadas deben sustituir la falta operativa cuando apliquen.
- Los domingos trabajados deben salir como domingo trabajado y, si el dia publicado era descanso, tambien como descanso trabajado.
- Retardo y salida anticipada deben aparecer como incidencias separadas cuando superen la tolerancia configurada.

## Alcance del escenario

- Empresa: misma empresa demo usada para las pruebas de CSV.
- Empresa sugerida si se ejecuta aislado: `Empresa Demo CSV Vera Time`.
- Centro sugerido: `Centro Mixto Nocturno Demo`.
- Periodo sugerido: mismo rango de la matriz principal de pruebas, 2026-08-03 a 2026-08-23.
- Ventana interna de este escenario: 2026-08-10 a 2026-08-23.
- Duracion: 2 semanas.
- Inicio de semana operativo: lunes.
- Trabajadores: 5 nuevos, asignados unicamente al centro `Centro Mixto Nocturno Demo`.
- Programacion esperada: publicada antes de cargar eventos.
- Tolerancia sugerida:
  - Retardo: 10 minutos.
  - Salida anticipada: 10 minutos.
- No incluir festivo obligatorio en esta matriz, salvo que se cree una variante separada.

Separacion contra el escenario base:

- No reutilizar trabajadores del escenario diurno/base.
- No reutilizar centros existentes como `Matriz Demo` o `Planta Demo Norte`.
- No borrar ni alterar eventos del escenario base al preparar esta prueba.
- El CSV final puede exportarse por centro para comparar cada escenario por separado, o por empresa para validar que ambos conviven en el mismo periodo.

## Guia paso a paso para preparar la prueba

Esta seccion esta pensada para la persona que va a capturar el escenario manualmente. El objetivo es probar jornadas diurnas, nocturnas, mixtas y partidas sin mezclar estos trabajadores con el escenario base.

### 1. Crear o seleccionar empresa

Usar la misma empresa del escenario base o crear una empresa aislada.

| Campo | Valor |
|---|---|
| Nombre comercial | Empresa Demo CSV Vera Time |
| Zona horaria | America/Mexico_City |
| Estado | Activa |
| Retardo permitido | 10 minutos |
| Salida anticipada permitida | 10 minutos |

### 2. Crear centro

Crear este centro activo:

| Centro | Zona horaria | Uso en la prueba |
|---|---|---|
| Centro Mixto Nocturno Demo | America/Mexico_City | Escenario especializado de jornadas diurnas, nocturnas y mixtas |

### 3. Crear areas o unidades

Crear estas unidades si se quiere probar tambien alcance organizacional. Si no se necesita validar unidades en esta corrida, los trabajadores pueden quedar solo en el centro.

| Centro | Unidad sugerida | Trabajadores sugeridos |
|---|---|---|
| Centro Mixto Nocturno Demo | Equipo Nocturno Demo | VT-N02 |
| Centro Mixto Nocturno Demo | Equipo Mixto Demo | VT-N03 |
| Centro Mixto Nocturno Demo | Equipo Diurno Demo | VT-N01, VT-N04, VT-N05 |

### 4. Crear trabajadores

Crear cinco trabajadores activos con relacion laboral vigente desde antes del 2026-08-10.

| Codigo | Nombre | Centro | Unidad sugerida |
|---|---|---|---|
| VT-N01 | Daniel Diurno Demo | Centro Mixto Nocturno Demo | Equipo Diurno Demo |
| VT-N02 | Nadia Nocturna Demo | Centro Mixto Nocturno Demo | Equipo Nocturno Demo |
| VT-N03 | Mateo Mixto Demo | Centro Mixto Nocturno Demo | Equipo Mixto Demo |
| VT-N04 | Paula Partida Demo | Centro Mixto Nocturno Demo | Equipo Diurno Demo |
| VT-N05 | Ivan Incidencias Demo | Centro Mixto Nocturno Demo | Equipo Diurno Demo |

### 5. Crear turnos base

Crear las plantillas de turno de esta tabla antes de publicar la programacion diaria.

| Codigo turno | Nombre | Segmentos | Uso |
|---|---|---|---|
| TD-0800-1600 | Diurno 8 a 16 | Trabajo 08:00-16:00 | Jornada diurna |
| TN-2200-0600 | Nocturno 22 a 06 | Trabajo 22:00-06:00 +1 dia | Jornada nocturna |
| TM-1600-0000 | Mixto tarde 16 a 00 | Trabajo 16:00-00:00 +1 dia | Jornada mixta |
| TM-1800-0200 | Mixto noche 18 a 02 | Trabajo 18:00-02:00 +1 dia | Mixta con minutos nocturnos relevantes |
| TP-0800-1800 | Jornada partida | Trabajo 08:00-13:00, descanso 13:00-15:00, trabajo 15:00-18:00 | Jornada partida |

### 6. Publicar programacion diaria

Publicar la programacion diaria para el centro `Centro Mixto Nocturno Demo` en el rango 2026-08-10 a 2026-08-23.

Usar la tabla `Matriz diaria esperada` como fuente de verdad:

- `D` debe publicarse como turno `TD-0800-1600`.
- `NOC` debe publicarse como turno `TN-2200-0600`.
- `MIX` debe publicarse como turno `TM-1600-0000`.
- `MIX-NOC` debe publicarse como turno `TM-1800-0200`.
- `DES` debe publicarse como descanso.
- `DOM+NOC` debe estar publicado como descanso, pero se capturaran eventos nocturnos.
- `DOM+MIX` debe estar publicado como descanso, pero se capturaran eventos mixtos.
- `INCPL` debe estar publicado como turno diurno para generar una jornada incompleta al capturar solo entrada.

### 7. Capturar incidencias y ausencias previas

Antes de recalcular jornadas, crear incidencias/ausencias aprobadas para estos casos:

| Codigo | Tipo operativo esperado | Pago esperado | Evento de asistencia |
|---|---|---|---|
| FJP | Falta justificada pagada | Pagada | No |
| VAC | Vacaciones | Pagada | No |
| INC | Incapacidad | Pagada | No |

### 8. Capturar eventos

Usar la tabla `Horarios diarios esperados para captura`.

Reglas de captura:

- Cuando la celda tenga horario, capturar entrada y salida.
- Cuando la salida indique `+1 dia`, la salida debe capturarse en la fecha calendario siguiente.
- Cuando la celda sea `08:00-`, capturar solo entrada.
- Cuando la celda sea `-`, no capturar eventos.
- No capturar eventos para faltas, vacaciones, incapacidad ni descansos sin trabajo.

### 9. Recalcular jornadas

Generar/recalcular jornadas para:

| Parametro | Valor |
|---|---|
| Fecha inicial | 2026-08-10 |
| Fecha final | 2026-08-23 |
| Alcance | Centro Mixto Nocturno Demo |

### 10. Revisar y dictaminar

Despues del recalculo, revisar la vista de Jornadas.

| Caso | Dictamen esperado |
|---|---|
| F | Confirmar falta o dejar como falta real segun el flujo de prueba |
| FJP | Debe quedar como ausencia aprobada |
| VAC | Debe quedar como vacaciones aprobadas |
| INC | Debe quedar como incapacidad aprobada |
| EXT | Confirmar tiempo extra |
| RET | Confirmar o justificar retardo |
| SA | Confirmar o justificar salida anticipada |
| INCPL | Corregir evento o dictaminar jornada incompleta antes de cerrar |
| DOM+NOC | Aprobar domingo trabajado y descanso trabajado |
| DOM+MIX | Aprobar domingo trabajado y descanso trabajado |

### 11. Generar periodo y CSV

Crear un periodo de asistencia con:

| Parametro | Valor |
|---|---|
| Rango | 2026-08-10 a 2026-08-23 |
| Centro | Centro Mixto Nocturno Demo |
| Entregable | CSV final de asistencia |

### 12. Comparar resultado

Comparar el CSV contra:

- `Matriz diaria esperada`.
- `Horarios diarios esperados para captura`.
- `Eventos esperados por codigo`.
- `Resultado esperado por trabajador`.
- `Campos CSV a validar`.

Si el CSV no coincide, revisar en este orden:

1. Programacion diaria publicada.
2. Eventos capturados y fechas con `+1 dia`.
3. Incidencias/ausencias aprobadas.
4. Recalculo de jornadas.
5. Dictamenes pendientes.
6. Configuracion de tolerancias.
7. Clasificacion legal diurna/nocturna/mixta.

## Leyenda

| Codigo | Significado esperado |
|---|---|
| D | Jornada diurna normal |
| NOC | Jornada nocturna normal |
| MIX | Jornada mixta normal |
| MIX-NOC | Jornada mixta que debe clasificar como nocturna por minutos nocturnos |
| EXT | Tiempo extra |
| RET | Retardo |
| SA | Salida anticipada |
| F | Falta real no justificada |
| FJP | Falta justificada pagada |
| VAC | Vacaciones |
| INC | Incapacidad |
| DOM | Domingo trabajado; si el dia publicado era descanso, tambien cuenta como descanso trabajado |
| DES | Descanso programado |
| INCPL | Jornada incompleta |

## Turnos base para publicar

| Codigo turno | Nombre | Segmentos esperados | Clasificacion esperada |
|---|---|---|---|
| TD-0800-1600 | Diurno 8 a 16 | Trabajo 08:00-16:00 | Diurna |
| TN-2200-0600 | Nocturno 22 a 06 | Trabajo 22:00-06:00 +1 dia | Nocturna |
| TM-1600-0000 | Mixto tarde 16 a 00 | Trabajo 16:00-00:00 | Mixta o nocturna segun regla legal vigente |
| TM-1800-0200 | Mixto noche 18 a 02 | Trabajo 18:00-02:00 +1 dia | Nocturna si supera limite nocturno |
| TP-0800-1800 | Jornada partida | Trabajo 08:00-13:00, descanso 13:00-15:00, trabajo 15:00-18:00 | Diurna |

## Trabajadores base

| Codigo | Trabajador sugerido | Perfil del escenario |
|---|---|---|
| VT-N01 | Daniel Diurno Demo | Diurno con retardo, extra y falta |
| VT-N02 | Nadia Nocturna Demo | Nocturno con retardo, salida anticipada y domingo |
| VT-N03 | Mateo Mixto Demo | Mixto y mixto-nocturno |
| VT-N04 | Paula Partida Demo | Jornada partida con salida anticipada e incapacidad |
| VT-N05 | Ivan Incidencias Demo | Combinacion de incidencias operativas |

## Matriz diaria esperada

| Fecha | Dia | VT-N01 Daniel | VT-N02 Nadia | VT-N03 Mateo | VT-N04 Paula | VT-N05 Ivan |
|---|---|---|---|---|---|---|
| 2026-08-10 | Lun | D | NOC | MIX | D | D |
| 2026-08-11 | Mar | D+RET | NOC | MIX-NOC | D | FJP |
| 2026-08-12 | Mie | D+EXT | NOC+SA | MIX | D+SA | D |
| 2026-08-13 | Jue | D | NOC+RET | MIX-NOC | INC | INCPL |
| 2026-08-14 | Vie | F | NOC | MIX+EXT | D | VAC |
| 2026-08-15 | Sab | DES | DES | DES | DES | DES |
| 2026-08-16 | Dom | DES | DOM+NOC | DES | DES | DES |
| 2026-08-17 | Lun | D | NOC | MIX | D | D+RET |
| 2026-08-18 | Mar | D | NOC+EXT | MIX-NOC+SA | D | D |
| 2026-08-19 | Mie | D+SA | NOC | MIX | D+EXT | F |
| 2026-08-20 | Jue | D | NOC+RET+SA | MIX-NOC | D | D |
| 2026-08-21 | Vie | D | NOC | MIX | D | D+EXT |
| 2026-08-22 | Sab | DES | DES | DES | DES | DES |
| 2026-08-23 | Dom | DES | DES | DOM+MIX | DES | DES |

## Horarios diarios esperados para captura

Esta tabla traduce la matriz anterior a horarios concretos de captura. Cuando el escenario sea falta, ausencia, vacaciones, incapacidad o descanso sin trabajo, se usa `-` porque no debe capturarse evento de asistencia para ese trabajador y fecha.

| Fecha | Dia | VT-N01 Daniel | VT-N02 Nadia | VT-N03 Mateo | VT-N04 Paula | VT-N05 Ivan |
|---|---|---|---|---|---|---|
| 2026-08-10 | Lun | 08:00-16:00 | 22:00-06:00 +1 dia | 16:00-00:00 +1 dia | 08:00-16:00 | 08:00-16:00 |
| 2026-08-11 | Mar | 08:25-16:00 | 22:00-06:00 +1 dia | 18:00-02:00 +1 dia | 08:00-16:00 | - |
| 2026-08-12 | Mie | 08:00-18:00 | 22:00-05:35 +1 dia | 16:00-00:00 +1 dia | 08:00-15:35 | 08:00-16:00 |
| 2026-08-13 | Jue | 08:00-16:00 | 22:25-06:00 +1 dia | 18:00-02:00 +1 dia | - | 08:00- |
| 2026-08-14 | Vie | - | 22:00-06:00 +1 dia | 16:00-02:00 +1 dia | 08:00-16:00 | - |
| 2026-08-15 | Sab | - | - | - | - | - |
| 2026-08-16 | Dom | - | 22:00-06:00 +1 dia | - | - | - |
| 2026-08-17 | Lun | 08:00-16:00 | 22:00-06:00 +1 dia | 16:00-00:00 +1 dia | 08:00-16:00 | 08:25-16:00 |
| 2026-08-18 | Mar | 08:00-16:00 | 22:00-08:00 +1 dia | 18:00-01:35 +1 dia | 08:00-16:00 | 08:00-16:00 |
| 2026-08-19 | Mie | 08:00-15:35 | 22:00-06:00 +1 dia | 16:00-00:00 +1 dia | 08:00-18:00 | - |
| 2026-08-20 | Jue | 08:00-16:00 | 22:25-05:35 +1 dia | 18:00-02:00 +1 dia | 08:00-16:00 | 08:00-16:00 |
| 2026-08-21 | Vie | 08:00-16:00 | 22:00-06:00 +1 dia | 16:00-00:00 +1 dia | 08:00-16:00 | 08:00-18:00 |
| 2026-08-22 | Sab | - | - | - | - | - |
| 2026-08-23 | Dom | - | - | 16:00-00:00 +1 dia | - | - |

Notas de captura:

- `08:00-16:00` representa jornada diurna completa.
- `22:00-06:00 +1 dia` representa jornada nocturna con salida al dia siguiente.
- `16:00-00:00 +1 dia` representa jornada mixta base con cierre a medianoche.
- `18:00-02:00 +1 dia` representa jornada mixta/nocturna que debe validar minutos nocturnos.
- `08:00-18:00`, `22:00-08:00 +1 dia` y `16:00-02:00 +1 dia` representan tiempo extra.
- `08:25-16:00` y `22:25-06:00 +1 dia` representan retardo.
- `08:00-15:35`, `22:00-05:35 +1 dia` y `18:00-01:35 +1 dia` representan salida anticipada.
- `22:25-05:35 +1 dia` representa retardo y salida anticipada en la misma jornada nocturna.
- `08:00-` representa jornada incompleta con entrada sin salida.
- `-` significa no capturar eventos de asistencia para ese dia.

## Eventos esperados por codigo

Estos horarios son la verdad de captura sugerida para cargar eventos.

| Codigo | Entrada | Salida | Notas |
|---|---|---|---|
| D | 08:00 | 16:00 | Jornada diurna completa |
| D+RET | 08:25 | 16:00 | Retardo despues de tolerancia |
| D+SA | 08:00 | 15:35 | Salida anticipada despues de tolerancia |
| D+EXT | 08:00 | 18:00 | Dos horas extra sobre turno diurno |
| NOC | 22:00 | 06:00 +1 dia | Jornada nocturna completa |
| NOC+RET | 22:25 | 06:00 +1 dia | Retardo nocturno |
| NOC+SA | 22:00 | 05:35 +1 dia | Salida anticipada nocturna |
| NOC+EXT | 22:00 | 08:00 +1 dia | Extra en turno nocturno |
| NOC+RET+SA | 22:25 | 05:35 +1 dia | Retardo y salida anticipada |
| MIX | 16:00 | 00:00 +1 dia | Jornada mixta base |
| MIX+EXT | 16:00 | 02:00 +1 dia | Mixta con extra |
| MIX-NOC | 18:00 | 02:00 +1 dia | Debe validar limite nocturno |
| MIX-NOC+SA | 18:00 | 01:35 +1 dia | Mixta/nocturna con salida anticipada |
| DOM+NOC | 22:00 | 06:00 +1 dia | Domingo trabajado nocturno sobre descanso |
| DOM+MIX | 16:00 | 00:00 +1 dia | Domingo trabajado mixto sobre descanso |
| F | Sin eventos | Sin eventos | Falta real pendiente o confirmada |
| FJP | Sin eventos | Sin eventos | Debe crearse incidencia/ausencia pagada |
| VAC | Sin eventos | Sin eventos | Debe crearse ausencia por vacaciones |
| INC | Sin eventos | Sin eventos | Debe crearse ausencia por incapacidad |
| INCPL | 08:00 | Sin salida | Jornada incompleta |
| DES | Sin eventos | Sin eventos | Descanso programado |

## Resultado esperado por trabajador

| Trabajador | Diurnas normales | Nocturnas normales | Mixtas normales | Mixtas clasificadas nocturnas | Faltas reales | Justificadas pagadas | Vacaciones | Incapacidades | Domingos trabajados | Dias con extra | Dias con retardo | Dias con salida anticipada | Jornadas incompletas |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| VT-N01 Daniel | 8 | 0 | 0 | 0 | 1 | 0 | 0 | 0 | 0 | 1 | 1 | 1 | 0 |
| VT-N02 Nadia | 0 | 8 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 1 | 2 | 2 | 0 |
| VT-N03 Mateo | 0 | 0 | 5 | 5 | 0 | 0 | 0 | 0 | 1 | 1 | 0 | 1 | 0 |
| VT-N04 Paula | 8 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 0 | 1 | 0 | 1 | 0 |
| VT-N05 Ivan | 8 | 0 | 0 | 0 | 1 | 1 | 1 | 0 | 0 | 1 | 1 | 0 | 1 |

Notas:

- Las columnas de clasificacion legal deben validarse contra el resultado vigente del motor legal.
- `horas_nocturnas` puede existir en jornadas mixtas aunque la clasificacion final no sea nocturna.
- Las horas extra dobles/triples se validan aparte de la clasificacion diurna/nocturna/mixta.
- `DOM+NOC` y `DOM+MIX` deben marcar domingo trabajado y descanso trabajado si el dia publicado era descanso.
- `FJP`, `VAC` e `INC` requieren crear incidencias/ausencias antes del cierre.
- `RET` y `SA` deben aparecer con minutos y dictamen separado en el CSV.

## Campos CSV a validar

| Campo CSV | Validacion esperada |
|---|---|
| `fecha` | Una fila por trabajador y dia del periodo |
| `numero_empleado` | Coincide con trabajador |
| `tipo_dia` | Turno, descanso o ausencia segun aplique |
| `hora_entrada` / `hora_salida` | Respeta eventos cargados y cruce de medianoche |
| `horas_ordinarias` | Respeta limites ordinarios vigentes |
| `horas_extra_totales` | Mayor a cero solo en dias EXT |
| `horas_extra_dobles` | Minutos extra dentro del limite doble semanal |
| `horas_extra_triples` | Minutos extra que excedan el limite doble semanal |
| `horas_nocturnas` | Mayor a cero en NOC, MIX y MIX-NOC segun horas reales |
| `minutos_retardo` | Mayor a cero solo en RET |
| `dictamen_retardo` | Pendiente, confirmada, justificada o no_procede |
| `minutos_salida_anticipada` | Mayor a cero solo en SA |
| `dictamen_salida_anticipada` | Pendiente, confirmada, justificada o no_procede |
| `domingo_trabajado` | Si en DOM+NOC y DOM+MIX |
| `descanso_trabajado` | Si el dia publicado era descanso y hubo trabajo |
| `tiene_incidencia` | Si hay alerta o ausencia/incidencia aplicable |
| `tipo_incidencia` | Refleja falta, ausencia, retardo, salida anticipada, extra o incompleta |
| `estatus_incidencia` | Estado operativo vigente |

## Flujo de prueba propuesto

1. Crear o seleccionar empresa demo.
2. Crear el centro `Centro Mixto Nocturno Demo`.
3. Crear cinco trabajadores nuevos del escenario dentro de ese centro.
4. Crear plantillas de turno diurno, nocturno, mixto, mixto-nocturno y partida.
5. Crear perfiles y programacion diaria publicada del 2026-08-10 al 2026-08-23 para el centro nuevo.
6. Vaciar datos operativos de prueba solo para esos cinco trabajadores y ese rango.
7. Cargar eventos segun la tabla de eventos esperados.
8. Crear incidencias/ausencias para `FJP`, `VAC` e `INC`.
9. Recalcular jornadas.
10. Revisar alertas de retardo, salida anticipada, extra e incompleta.
11. Dictaminar lo necesario.
12. Recalcular si el dictamen modifica la jornada.
13. Generar o regenerar el periodo de asistencia que cubre el rango.
14. Descargar CSV del centro nuevo, o CSV empresarial si se desea validar convivencia con el escenario base.
15. Comparar CSV contra esta matriz.

## Preparacion esperada antes de cargar eventos

Antes de insertar eventos se debe confirmar:

- La empresa demo tiene configurada tolerancia de retardo y salida anticipada.
- Existe el centro `Centro Mixto Nocturno Demo`.
- Los trabajadores `VT-N01` a `VT-N05` pertenecen solo a ese centro.
- Los turnos base existen en el catalogo de turnos.
- Los perfiles/modelos necesarios existen para publicar la programacion.
- El lote de programacion diaria esta publicado para el rango 2026-08-10 a 2026-08-23.
- No existen eventos previos para esos cinco trabajadores en ese rango.
- No existen incidencias/ausencias previas para esos cinco trabajadores en ese rango.
- No existe un periodo de asistencia cerrado previo que impida comparar limpiamente el resultado.

## Siguiente accion tecnica

Una vez aprobada esta matriz, el siguiente paso es preparar el escenario de datos:

1. Crear centro, trabajadores, turnos, perfiles y programacion publicada.
2. Crear las incidencias/ausencias requeridas por la matriz: `FJP`, `VAC` e `INC`.
3. Crear un query o seeder temporal de prueba para insertar eventos conforme a la tabla `Eventos esperados por codigo`.
4. Ejecutar recalculo de jornadas.
5. Dictaminar las incidencias que queden pendientes.
6. Generar el periodo de asistencia.
7. Descargar el CSV.
8. Comparar el CSV contra esta matriz.

Este archivo no debe reemplazar las pruebas automatizadas. Su funcion es servir como matriz manual de validacion integral contra el CSV real.

## Pendientes antes de ejecutar

- Confirmar las reglas legales exactas para clasificar `MIX` y `MIX-NOC`.
- Crear trabajadores nuevos `VT-N01` a `VT-N05` en el centro `Centro Mixto Nocturno Demo`.
- Crear query/script de eventos una vez aprobada esta matriz.
- Definir si se necesita una variante con descanso obligatorio nocturno o mixto.
