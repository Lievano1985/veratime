# Revisión diaria sin tareas duplicadas

La evaluación genera una tarea de tiempo extra, una de duración y una de pausa cuando corresponden. Descansos, domingos y otras incidencias independientes conservan su tratamiento.

`daily_limit_exceeded` representa la revisión única de duración. Su descripción y `metadata.exceeded_limits` conservan los motivos: referencia diaria superada, más de tres horas extra diarias y duración superior a diez o doce horas. No se generan nuevas tareas `long_work_day`, `twelve_hours_exceeded` ni `daily_overtime_over_three_hours`.

Al evaluar nuevamente, los avisos antiguos abiertos se cierran mediante el mecanismo de recálculo existente y permanecen en el historial. Los dictámenes anteriores se conservan. El nuevo aviso lleva `duration_review_version`; autorizar tiempo extra no lo cierra. Se mantiene compatibilidad con el cierre conjunto de avisos antiguos que no tenían esta marca.

No cambian los cálculos, minutos trabajados, bandas, tolerancias ni reglas semanales. La tarjeta de jornadas mayores a doce horas continúa consultando los minutos calculados; la lista de tareas pendientes muestra una sola revisión de duración.

Pruebas: jornada extendida con tres tareas, retiro de avisos antiguos, independencia entre autorización y duración, conservación de cálculos e historial, aislamiento por empresa y regresión de Jornadas y dashboard.
