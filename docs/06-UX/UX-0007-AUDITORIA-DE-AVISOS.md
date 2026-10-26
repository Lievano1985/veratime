# Auditoría de duplicidad de avisos

Revisión del catálogo y de todas las condiciones del evaluador de jornadas, 25 de octubre de 2026. Criterio: una tarea debe responder una pregunta distinta; compartir fecha o minutos no basta para considerarla duplicada. No se cambian reglas de cálculo ni se determina el pago mediante esta revisión.

| Aviso o conjunto | Resultado |
| --- | --- |
| Falta (`scheduled_absence`) | Conservar. Se genera sin cálculo ni marcajes válidos en un turno esperado; no equivale a una secuencia incompleta. |
| Jornada incompleta (`incomplete_work_day`) | Conservar. Requiere aclarar la secuencia antes de dar por terminado el cálculo. |
| Retardo y salida anticipada | Conservar. Son hechos distintos y pueden coexistir; la duración total no los compensa automáticamente. |
| Tiempo extra detectado | Conservar para autorización. No sustituye revisar la duración. |
| Duración de jornada por revisar | Una tarea con motivos en detalle. Sustituye las tareas repetidas de jornada larga, más de doce horas y más de tres horas extra diarias. |
| Pausa mínima no identificada | Conservar. Requiere revisar la evidencia de descanso, no autorizar horas extra. |
| Domingo diario y domingo semanal | Duplicidad confirmada. No generar `weekly_sunday_work`; conservar el domingo diario. El número de domingos permanece en metadatos de los otros avisos semanales cuando estos correspondan. |
| Descanso asignado, obligatorio y domingo | Conservar los conceptos en un mismo caso diario. La programación de descanso, el calendario obligatorio y la condición de domingo son hechos distintos; no implican multiplicar horas ni pagos automáticamente. |
| Acumulado semanal de jornada y horas extra semanales | Conservar. Uno compara base de jornada en días laborables y otro tiempo extra acumulado; se muestran las magnitudes por separado. |
| Más de tres días con tiempo extra | Conservar. Revisa distribución por días, no cantidad total de horas. Puede ocurrir sin superar el umbral de horas semanales. |
| Semana sin descanso | Conservar. Revisa la semana completa, no la autorización para trabajar un día de descanso. |
| Persona menor: duración y trabajo restringido | Conservar. Revisan duración por edad y características de trabajo respectivamente. Autorizar horas extra no resuelve esas revisiones. |
| Seis días consecutivos, legado | Mantener el cierre de legado existente; la revisión vigente es semana sin descanso. |

Los tipos retirados permanecen en el catálogo para conservar historia y permitir el cierre de sus avisos anteriores. Al reevaluar una semana, los domingos semanales abiertos pasan al historial mediante cierre automático, sin alterar el domingo diario ni sus dictámenes. Los dictámenes humanos anteriores permanecen conservados.

Verificación: pruebas del evaluador, Jornadas y dashboard; prueba específica de retiro del domingo semanal con conservación de domingo diario y descanso asignado; comprobación local de Diego, semana del 12 al 18 de octubre, sin cambios en los cálculos.
