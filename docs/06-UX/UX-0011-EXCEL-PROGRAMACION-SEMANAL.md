# Excel para programación semanal

## Alcance aprobado

El planificador y la programación anterior comparten el importador. Excel `.xlsx` es la opción principal para descargar y editar horarios; CSV sigue disponible. Se reutiliza PhpSpreadsheet, ya instalado. No agrega tablas ni migraciones.

## Recorrido

1. En un borrador, pulsar **Importar Excel / CSV** y **Descargar Excel**.
2. El archivo contiene las personas de los filtros actuales, las fechas y los horarios actuales, no solo pendientes.
3. En **Programación**, elegir un turno o `DESCANSO` en cada lista. La hoja **Turnos** muestra códigos, nombres, horas y descripciones.
4. Guardar el mismo archivo `.xlsx`, subir y validar.
5. Revisar por persona y fecha la acción, horario solicitado, horario actual y errores o advertencias.
6. Confirmar revisión y aplicar al borrador. Publicar sigue siendo una acción separada.

**Plantilla CSV** conserva la descarga horizontal anterior de pendientes. No se aceptan `.xls` ni `.xlsm`.

## Conservación de datos

Los códigos de empleado son texto y conservan ceros iniciales. Fechas e identificadores están bloqueados contra cambios accidentales; los días vigentes son editables.

Vacíos, **Conservar horario actual** y celdas iguales a las descargadas se omiten. Así, subir sin editar no sobrescribe cambios posteriores realizados en el sistema. La hoja interna guarda contexto y valores originales; no sustituye la autorización ni la validación del servidor.

Flexibles, guardias y turnos inactivos existentes aparecen como `[ACTUAL] ... · conservar`. Se mantienen al cargar. Elegir un turno activo o descanso permite sustituir ese día; la configuración avanzada sigue en el sistema.

Los rangos actuales se toman de los segmentos diarios guardados y las opciones de turno del catálogo activo.

## Arquitectura y límites

`GenerateDailyScheduleXlsxAction` construye el archivo; `DailyScheduleXlsxTemplateController` exige permiso de edición del borrador en la empresa activa. Ruta: `/time/scheduling/daily/excel/template?schedule_batch_id=...`.

El almacenamiento privado conserva el archivo original y su SHA-256. `metadata.source_format` distingue CSV y XLSX. `ReadDailyScheduleXlsxAction` verifica plantilla, empresa, centro, lote y fechas; lee valores sin calcular fórmulas y produce el stream canónico para el parser, resolutor, vista previa y aplicación transaccional existentes.

Se rechazan fórmulas en datos, contexto ajeno, archivos corruptos y macros. Límites: 10 MB de archivo, 10,000 registros trabajador/fecha, nueve columnas de datos, 2,000 entradas ZIP y 64 MB descomprimidos. Publicación, separación por `company_id`, historial y detección de vistas previas obsoletas conservan las reglas actuales.

## Validación

Las pruebas serializan y vuelven a leer el XLSX, verifican listas y referencias, ceros iniciales, horarios actuales, carga/revisión/aplicación, errores por persona/día, horarios avanzados, cambios posteriores a la descarga, contexto ajeno, fórmulas, corrupción y permisos. Las pruebas CSV existentes verifican el flujo alternativo.

Para aceptación visual: abrir la descarga en Excel, comprobar listas, cambiar un día y subir para revisar/aplicar. El servidor no necesita Microsoft Excel instalado.
