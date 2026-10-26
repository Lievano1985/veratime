---
id: UX-0002
title: Dashboard operativo Vera Time
project: Vera Time
status: Approved for MVP implementation
owner: Product
updated: 2026-10-10
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
- KPI operativos de presencia en tiempo real;
- incidencias pendientes acumuladas, agrupadas por tipo;
- cumplimiento laboral preventivo de la última semana cerrada;
- tendencia semanal anual de incidencias por tipo;
- enlaces de sólo consulta a Jornadas, con los filtros correspondientes.

## Composición visual

La pantalla organiza el contenido en bloques operativos: encabezado con filtros y hora de actualización, panorama de jornada con KPI de presencia, incidencias pendientes, cumplimiento laboral y gráficas de incidencias. El panorama usa tarjetas blancas de borde suave con cuatro KPI en una cuadrícula de dos por dos y una tarjeta amplia de cobertura con medidor semicircular azul. Los KPI son trabajadores activos, trabajando ahora, personal por ingresar y jornadas completadas. Una jornada completada tiene cálculo activo y minutos trabajados mayores a cero en la fecha consultada; puede conservar alertas preventivas sin dejar de contarse como completada. Las incidencias pendientes muestran los conteos acumulados de alertas abiertas por tipo, sin filtrarse por la fecha del tablero; las tarjetas con conteo cero no se muestran. El bloque **Cumplimiento laboral** siempre toma la última semana completa anterior a la fecha consultada. Considera cumplida a una persona sólo si respeta el límite ordinario semanal, el límite semanal de tiempo extra y el máximo de doce horas por jornada. Su porcentaje se presenta a la izquierda como un medidor semicircular verde de éxito Vera Time (`#16B26A`), con la altura de dos tarjetas, acompañado por personas evaluadas, que cumplen y por revisar. A la derecha, las cuatro tarjetas se organizan en dos filas de dos: personas sobre el límite ordinario semanal, sobre el límite semanal de tiempo extra, con una jornada superior a doce horas y alertas abiertas vinculadas a una fecha previa al inicio de la semana analizada. Cada una abre la bandeja de jornadas con el filtro de incidencias correspondiente y sin imponer fechas. Informa el límite vigente y el siguiente cambio del catálogo legal, sin adelantar su aplicación. El azul suave comunica estado operativo, no una sanción.

Quedan fuera: sanciones, aprobación formal de incidencias, cálculo legal nuevo, reportes oficiales, nómina, API pública del dashboard y modificar eventos desde esta pantalla.

## Fuentes de datos

El resumen se construye fuera de Livewire mediante `BuildOperationalDashboardAction`, con datos ya persistidos:

- `workers` y relaciones laborales activas;
- `daily_schedule_assignments`;
- `time_events` válidos;
- `work_days`, cálculos activos y alertas abiertas, agrupadas por la fecha de jornada afectada cuando existe;
- versiones de `maximum_weekly_hours` para resolver el límite vigente y el siguiente cambio programado.

La vista no calcula jornadas ni crea alertas. Las alertas existentes son la fuente para retrasos, jornadas incompletas, tiempo extra, domingo, descansos y situaciones equivalentes. Las incidencias pendientes muestran todas las alertas abiertas de tipo diario acumuladas para el alcance visible, sin usar la fecha del tablero. Sus enlaces abren Jornadas sin fechas en ambos controles, conservando el filtro de atención y, cuando aplica, el tipo de incidencia. Ninguno se basa en la fecha en que una alerta fue recalculada y no modifican evidencia.

## Gráficas de incidencias

La gráfica de barras muestra el número semanal de los cuatro tipos de incidencia más repetidos del año calendario en curso. Inicia en la primera semana que tenga incidencias dentro del año y conserva las semanas posteriores, incluso si alguna no tuvo alertas, para representar la continuidad de la tendencia. Las barras se agrupan por semana y usan tonos azules de Vera Time para distinguir cada tipo. No se ancla a una fecha histórica elegida para los indicadores operativos. Se renderiza con Chart.js, sólo consulta alertas históricas ya persistidas y aplica la misma empresa, centro y alcance operativo del tablero.

## Indicadores preventivos de jornada

El dominio evalúa y conserva alertas por jornada para: límite diario aplicable, tiempo extra diario mayor a tres horas, jornada mayor a diez horas, pausa registrada menor a treinta minutos en jornadas de al menos seis horas, domingo, descanso obligatorio, descanso asignado y registro incompleto. Cuando existe fecha de nacimiento registrada, también evalúa revisión de jornada mayor a seis horas para persona menor de dieciséis años y tiempo extra o clasificación nocturna para persona menor de dieciocho años.

Por persona trabajadora y semana natural, el dominio concentra: horas totales contra el límite semanal que quedó en el snapshot legal, tiempo extra superior a nueve horas, más de tres días con tiempo extra, semana sin descanso y domingos trabajados. Cada alerta semanal usa una huella por empresa, relación laboral, persona y semana; un recálculo la actualiza o la cierra automáticamente si deja de aplicar.

Las nuevas reglas leen los límites diario y semanal ya versionados en `result_snapshot.ordinary_overtime`; no crean configuraciones de empresa ni alteran marcajes, horarios publicados o cálculos históricos. La fecha de nacimiento es opcional y no se infiere de CURP: sin ella, las reglas de edad no generan una alerta.

## Reglas iniciales

### Trabajando ahora

Este KPI sólo se calcula para la fecha actual de la empresa. Se toma el último evento válido de dicha fecha; el evento de la fecha previa sólo se considera cuando pertenece a un turno nocturno publicado cuyo segmento de trabajo sigue vigente. `clock_in` y `break_end` cuentan como trabajando; `break_start` cuenta como pausa. Para fechas históricas, los KPI de presencia en tiempo real (trabajando, pausa y personal por ingresar) se muestran en cero; permanecen disponibles las jornadas completadas y los indicadores cerrados.

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

## Correcciones de integridad del resumen (19-10-2026)

- La población histórica se obtiene de las jornadas de la semana analizada, incluso si la persona o la relación laboral ya terminó. Se conservan `company_id`, centro y alcance autorizado del usuario.
- Una persona queda pendiente si alguna jornada relevante no tiene cálculo válido, conserva incidencias de secuencia, clasificación pendiente o minutos ordinarios/extra sin resolver. No se descarta únicamente el día incompleto para aparentar una semana válida.
- El resumen distingue total de personas con jornadas, evaluadas, que cumplen los límites revisados, por revisar y pendientes. El porcentaje divide personas que cumplen entre el total, incluidos los pendientes; si nadie puede evaluarse, muestra un guion en lugar de un resultado de cumplimiento.
- El límite de la semana histórica se presenta separado del vigente en la fecha consultada. El próximo ajuste se busca estrictamente después de esa fecha, sin volver a anunciar ajustes pasados.
- Un superadministrador sin empresa activa se dirige a Empresas para crear o seleccionar una, sin abrir un dashboard sin contexto.
- En permisos por unidad organizacional, el análisis histórico comprueba la asignación de la persona en la fecha de cada jornada. No amplía el alcance al centro completo ni a fechas en que la persona pertenecía a otra unidad.

## Detalle de personas en indicadores de cumplimiento

Las tarjetas de límite ordinario semanal, tiempo extra, jornadas mayores a 12 horas y alertas abiertas anteriores despliegan personas cuando su contador es positivo. Los indicadores en cero no muestran desplegable. Seleccionar un nombre abre un diálogo dentro del dashboard con período, horas, límite, exceso y jornadas por fecha/centro. En jornadas largas se compara la jornada de mayor duración con 12 horas; no se suman jornadas para este exceso. Los cálculos pendientes se señalan como parciales.

Las alertas anteriores mantienen el contador de alertas; la lista agrupa por persona y el diálogo muestra las alertas abiertas con sus fechas. Las alertas sin persona conservan un grupo explícito. Datos de detalle y contadores se construyen desde las mismas consultas filtradas por empresa, centro y alcance organizacional. El diálogo se cierra con su botón, Escape o clic en el fondo, y no navega a otra página.

## Flujo unificado de Jornadas, Incidencias y Prevención (20-10-2026)

La pantalla /time/work-days tiene tres pestañas. Jornadas atiende faltas, retardos, salidas anticipadas y marcajes incompletos. El estado de asistencia es derivado: un cálculo activo con registros útiles o una ausencia documentada, sin errores de secuencia ni alertas abiertas de asistencia. Los excesos y las horas extra no impiden finalizar la asistencia. Los descansos sin actividad no entran como jornadas trabajadas pendientes.

Incidencias muestra alertas calculadas de jornadas finalizadas; las semanales son un caso persistido por relación laboral, semana y regla, incluso cuando la fecha inicial representativa queda fuera del día seleccionado pero dentro de su semana. Se señalan como provisionales si la semana sigue abierta o hay jornadas sin completar. La revisión administrativa del tiempo extra no modifica el tiempo trabajado ni determina por sí sola el pago. Los otros resultados permiten registrar revisión y seguimiento, conservando el hecho calculado. Los marcajes incompletos requieren corrección con evidencia y recálculo: un comentario no los completa. Confirmar una falta produce una ausencia injustificada documentada, con cero horas trabajadas; justificarla produce la ausencia seleccionada.

Prevención usa la semana de la fecha Hasta, no todo el rango como un único acumulado. Identifica proximidad desde el 80 % de los umbrales versionados o riesgo de carga total proyectada según programación publicada. Muestra ordinarias, extra, márgenes, días futuros programados y proyección total. Excluye ausencias aprobadas en esos días. No estima horas extra futuras ni confirma incumplimientos. Si faltan duraciones programadas, no muestra una proyección cerrada. Quien ya superó un umbral se atiende en Incidencias. La información respeta empresa, centro y alcance organizacional por fecha.

El dashboard incluye accesos a las tres etapas y obtiene sus contadores de BuildWorkDayWorkspaceAction; el filtro de estado solo afecta el listado, manteniendo visibles los contadores del rango. La cobertura de asistencia mide las personas esperadas con entrada válida del día sobre las personas programadas en lotes publicados, descontando ausencias autorizadas. Una falta injustificada sigue siendo asistencia esperada. Se cuentan personas únicas, incluyendo quienes ya finalizaron, y se excluyen marcajes futuros o anulados. Sin personal esperado se muestra un guion. El porcentaje respeta empresa, centro y alcance por fecha; la finalización se presenta por separado.

El recálculo conserva una revisión si no cambian los datos que originaron la alerta. Si cambian, reabre el pendiente y conserva la resolución anterior, su autor y fecha en resolution_history. Los estados históricos de alertas se mantienen compatibles con las interfaces existentes; asistencia finalizada y revisión pendiente son conceptos distintos.


## Seguimiento simple de incidencias por jornada

Incidencias muestra una fila por jornada y persona con sus avisos vigentes, incluidos los revisados por RH. Los avisos cerrados automáticamente porque dejaron de aplicar se consultan en Ver historial, con la etiqueta Ya no aplica tras recálculo, tanto en la lista como en el panel lateral. El historial no ofrece acciones de dictamen. Si solo queda historial, el caso indica Sin avisos vigentes y puede consultarse con el filtro Revisadas. Un caso con avisos abiertos aparece en Pendientes; solo pasa a Revisadas cuando todos están atendidos. Los casos semanales permanecen separados. El detalle ofrece Corregir marcajes (con evidencia y recálculo posterior) y Cerrar seguimiento diario con comentario obligatorio. El cierre registra autor, fecha y comentario en cada aviso diario pendiente; conserva el cálculo, las revisiones previas y el historial. Las horas extra y el trabajo en descanso asignado se evalúan individualmente; los casos semanales mantienen su revisión propia. Las horas extra mantienen su acción individual y aprobarlas deja el panel abierto para atender el resto. Si cambian los datos del cálculo, el mecanismo existente de evaluación puede reabrir los avisos afectados.


Los enlaces Corregir marcajes y Eventos del panel de jornada abren el listado de Eventos con Desde/Hasta iguales a la fecha de la jornada y el identificador exacto del trabajador. Se muestra también su código en el buscador. La búsqueda manual de otro trabajador o Limpiar retira el filtro exacto heredado. Se conservan los límites de empresa y alcance del listado.


## Recálculo de una jornada desde el panel lateral

El panel de jornada ofrece Recalcular esta jornada a los roles que ya pueden ejecutar el proceso general (administración de empresa y RH administrador). Muestra empleado y fecha antes del botón, se deshabilita durante la ejecución y actualiza el panel sin esperar la cola. Genera una nueva versión del cálculo de ese día con autor y motivo; actualiza la distribución y avisos semanales de la misma relación laboral. No ejecuta la generación general del centro ni procesa otros trabajadores. Un error de secuencia mantiene la asistencia pendiente y se informa en el panel. Las jornadas no programadas sin marcajes válidos se conservan para revisión, sin borrar historial.


El encabezado de Cumplimiento laboral presenta el número de semana ISO junto al rango de resultados, calculado desde el inicio del período mostrado (lunes a domingo). Por ejemplo, del 12 al 18 de octubre de 2026 corresponde a Semana 42.


## Selector de semanas en Cumplimiento laboral

El bloque incorpora un selector discreto con número ISO y rango completo: Semana 42 · 12/10/2026 – 18/10/2026. Solo admite semanas calendario terminadas. Al cambiarlo, actualiza los indicadores, personas y detalle del período de cumplimiento; conserva la fecha operativa y el centro del tablero. El retorno queda anclado al mismo bloque. Ofrece al menos las últimas doce semanas cerradas y todo el historial de jornadas visible por empresa, centro y alcance. Los intervalos sin datos muestran los estados vacíos existentes. Cambiar los filtros generales conserva una selección semanal explícita.


El resumen semanal muestra Cumplen, Con excesos confirmados y Pendientes de evaluación. El porcentaje se etiqueta Cumplimiento confirmado: divide quienes cumplen entre el total de personas con jornadas en la semana. Una persona pendiente no se considera con cumplimiento confirmado, pero conserva los excesos ya detectados por los cálculos activos (límite ordinario semanal, extra semanal o jornada mayor a doce horas). Puede aparecer en ambos contadores; el texto aclara cuántas personas comparten esta condición. No se deben sumar los tres contadores como grupos excluyentes. Cada persona cuenta una sola vez en Con excesos confirmados aunque tenga varios tipos de exceso. Por ejemplo, 8 que cumplen, 0 con excesos confirmados y 2 pendientes producen 80 %.


## Evaluación del trabajo en descanso asignado

El aviso Trabajo en día de descanso asignado permite confirmar si estaba autorizado, confirmar que no tenía autorización o registrar revisión y seguimiento. Requiere un comentario y muestra Guardar evaluación. Conserva actor, fecha, decisión e historial de cambios; la decisión se identifica también en el estado del aviso. No cambia las horas ni el descanso programado y no determina por sí sola el pago. El cierre agrupado de seguimiento excluye este aviso, al igual que el de horas extra, para que RH lo evalúe individualmente.


La tarjeta Jornadas mayores a 12 h cuenta jornadas individuales del período, no personas. Su descripción muestra el número de personas involucradas y el desplegable conserva el detalle agrupado por trabajador. Cuatro jornadas de la misma persona se muestran como 4 y 1 persona involucrada; exactamente 12 horas no supera el umbral. Los indicadores de cumplimiento por persona mantienen su unidad original.

El popup de esta tarjeta también filtra sus filas por duración estrictamente mayor a 720 minutos. Su tabla se titula Jornadas mayores a 12 horas de la semana y coincide con el alcance del botón Ver esas jornadas. Los detalles de acumulados ordinarios y extra conservan todas las jornadas que contribuyen a la semana.


En Jornadas, las incidencias de asistencia atendidas conservan una etiqueta explícita: Retardo confirmado, Retardo justificado, Salida anticipada confirmada, etc. Las confirmadas se distinguen en ámbar y las justificadas en azul, tanto en el listado como en el panel. Una asistencia completa que fue aclarada se presenta como Finalizada con aclaración. Ver jornada es una acción visualmente neutra. Estos textos y colores describen el dictamen existente y no cambian el tiempo ni reabren la asistencia.


En los diálogos de detalle semanal del dashboard, Ver esas jornadas aparece junto a Cerrar. Abre Jornadas con el trabajador exacto y el período consultado, conservando el centro si estaba seleccionado. En Jornadas mayores a 12 h agrega un filtro sobre el cálculo activo para mostrar únicamente las jornadas superiores a doce horas, independientemente de si sus avisos ya fueron revisados. El listado informa ese filtro y permite quitarlo. Buscar otro trabajador o Limpiar retira el identificador exacto heredado; cambiar de pestaña retira el filtro específico de doce horas. Los resúmenes de excesos semanales abren las jornadas del trabajador que contribuyen al período completo.


Pendientes de evaluación es un acceso a Jornadas cuando su contador es positivo. Conserva la semana analizada, el centro y el alcance del usuario, y filtra los días que impiden evaluar el cumplimiento: falta de cálculo, registros incompletos, clasificación o distribución de horas pendiente. El listado informa el filtro y permite retirarlo. El contador representa personas; el destino puede mostrar varias jornadas de una misma persona. Si faltan las reglas semanales necesarias, todas las jornadas relevantes del período quedan pendientes hasta completar la configuración.

La evaluación semanal también espera la resolución de faltas, retardos, salidas anticipadas y marcajes incompletos: tener horas calculadas no completa el dictamen de asistencia. Una ausencia aprobada incluida en el cálculo activo (vacaciones, permiso, incapacidad o falta ya dictaminada), sin eventos válidos, sin errores de secuencia y con cero minutos trabajados, ordinarios y extra, se considera resuelta para este indicador. No requiere inventar marcajes ni clasificar una jornada sin trabajo. Las revisiones de horas extra y demás avisos calculados mantienen su flujo independiente y no bloquean por sí solas este contador. Estas condiciones se comparten entre el resumen y el filtro de jornadas.

Autorizar Tiempo extra detectado cierra también el seguimiento pendiente de Jornada excedida correspondiente a la misma jornada y cálculo activo. Se conserva el exceso, el cálculo y la auditoría (usuario, fecha, motivo y referencia al aviso autorizado). No se cierran jornadas mayores a doce horas, pausas, excesos diarios superiores a tres horas ni casos semanales. Rechazar o registrar únicamente seguimiento de las horas extra no cierra el aviso relacionado. Las autorizaciones anteriores pueden guardarse nuevamente para aplicar este seguimiento conjunto; no se cambian dictámenes históricos de forma masiva.

En Configuración de empresa → Configuración legal → Parámetros internos, el Margen de revisión del tiempo adicional admite 0–30 minutos, con motivo y vigencia por empresa. Cero mantiene la revisión desde el primer minuto. Una jornada con cálculo completo y tiempo extra diario positivo igual al exceso sobre su límite diario, hasta el margen inclusive, no genera las solicitudes diarias Tiempo extra detectado y Jornada excedida al evaluar/recalcular. El detalle muestra Tiempo adicional dentro del margen, los minutos reales y que no existe autorización automática. Superar el margen solicita revisión de todos los minutos adicionales, sin restar la tolerancia. El margen no modifica cálculos, marcajes, distribución de horas, indicadores ni acumulados semanales; tampoco oculta secuencias incompletas u horas extra derivadas del acumulado semanal dentro del límite diario. Las revisiones ya resueltas se conservan. Cambiar la vigencia requiere evaluar/recalcular los días afectados para actualizar sus avisos.

Los casos semanales separan Exceso del acumulado semanal de jornada (`weekly_hours_exceeded`, base de días laborables anterior a reclasificación semanal, excluyendo descanso) de Horas extra semanales superiores al límite (`weekly_overtime_exceeded`, suma de extra diario y de origen semanal). El detalle muestra los dos orígenes del extra y descanso trabajado por separado. Cumplir el máximo diario no elimina el eventual exceso ordinario semanal: seis jornadas laborables de 8 h en 2027 contienen 2 h de origen semanal frente a una referencia de 46 h. Una jornada base en descanso no se convierte automáticamente en extra. No se suman nuevamente esos conceptos al total físico. El aviso de más de tres horas extra diarias compara únicamente el origen diario. El margen de revisión interna no suprime el origen semanal. Los acumulados del dashboard y Prevención utilizan la base de días laborables; los descansos conservan sus propios avisos. La actualización histórica utiliza nuevas versiones por trabajador y semana, conserva resultados previos y no modifica reportes confirmados.
