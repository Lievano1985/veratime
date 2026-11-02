# Selección individual de días en el planificador

Se corrigió la interpolación de la clave de selección en los atributos Alpine de x-scheduling.day-card. Los atributos del componente contenían @js($key) dentro del texto de la expresión; ahora la clave se serializa en x-data de la celda HTML y la tarjeta recibe expresiones simples que utilizan cellKey. Cada celda tiene además una wire:key propia por lote, persona y fecha para no reutilizar estado de otra celda al navegar o filtrar.

La selección individual sigue utilizando toggleCells, como los encabezados, por lo que actualiza tanto las celdas como las personas seleccionadas. Los botones fuera de vigencia y los calendarios publicados continúan deshabilitados.

PlannerScheduleTest comprueba el HTML del enlace de selección y la exclusión/reinclusión de una celda en una columna previamente seleccionada. Comprobación manual: en un borrador, pulsar una tarjeta debe marcarla; pulsarla de nuevo debe desmarcarla. Seleccionar una columna y desmarcar una tarjeta debe conservar seleccionadas las demás; el contador debe reflejar el cambio antes de aplicar. No se modifican asignaciones hasta pulsar Aplicar.

El selector superior tiene una wire:key por centro y semana para refrescar su estado visual al abrir la última semana clonada. resolveWeek sincroniza año y número ISO y emite planner-week-changed para cerrar el desplegable en todos los cambios de semana. Las pruebas de clonación comprueban número de semana, rango visible e identidad del selector tanto al clonar borradores como al publicar directamente; el caso de búsqueda comprueba el límite entre años ISO.
