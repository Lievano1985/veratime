# Creador independiente de layouts de exportación

Fecha de implementación: 26/10/2026. Primera versión funcional; pendiente de validación de importación en la instalación destino.

## Acceso y alcance

Configuración → **Layouts de exportación** (`time/configuration/export-layouts`). Administradores de empresa y RH administrador pueden crear, editar, duplicar, inactivar y utilizar layouts de su empresa. Se conserva íntegro el módulo anterior **CSV de períodos**, sus tablas, rutas y exportadores.

Dominio nuevo: `App/Domains/ExportLayouts`. Almacenamiento nuevo: `export_layouts`, separado por `company_id`; configuración con `schema_version = 1`, revisión incremental y control de edición concurrente. La revisión indica la edición actual; esta primera versión no mantiene un archivo de todas las configuraciones anteriores.

## Flujo del diseñador

1. Crear desde cero, usar el ejemplo editable NomiPAQ DYH o abrir/duplicar un layout guardado.
2. **Distribución**: arrastrar Trabajadores/Fechas a Filas/Columnas o elegir los botones equivalentes. Las fechas se ordenan cronológicamente y los trabajadores por su identificación.
3. **Incidencias a mostrar**: activar o excluir conceptos. El orden del catálogo puede modificarse por arrastre o con botones accesibles; no representa prioridad ni resuelve coincidencias.
4. **Nomenclaturas**: asignar una clave única, mayúscula y de hasta 20 caracteres por concepto activo. Para tiempo, seleccionar horas o minutos; para conceptos diarios, días.
5. **Vista previa y exportación**: elegir un período cerrado de Vera Time, ver sus datos, guardar el layout y descargar. Los cambios posteriores a una vista previa real vuelven a mostrar el ejemplo ficticio; volver a pulsar Ver período para actualizarla.

Formatos: Excel `.xlsx`, CSV y TXT delimitados en UTF-8. Separadores configurables: tabulador, coma, punto y coma, barra vertical. CSV incluye BOM UTF-8; TXT no. No se implementan aún TXT de ancho fijo, codificaciones alternativas, fórmulas, macros ni hojas oficiales `.xls`.

El contenido de cada celda admite cantidad+código, código+cantidad o solo código. Se eligen 0–4 decimales; una cantidad positiva que se redondearía a cero bloquea la exportación. Se conserva el valor capturado del cierre; si el cierre ya almacenó horas redondeadas, el layout no recupera precisión perdida.

## Ejemplo NomiPAQ DYH

Trabajadores en filas, fechas en columnas; A1 = Código Empleado, fechas reales desde B1 y empleados desde A2. Se exporta únicamente el bloque desde las fechas hacia abajo, sin título, controles, parámetros ni macros de la hoja oficial.

Ejemplos editables del documento suministrado por el usuario:

| Origen Vera Time | Clave de ejemplo | Unidad inicial |
|---|---|---|
| Falta injustificada aprobada | FINJ | Días |
| Permiso con goce aprobado | PCS | Días |
| Permiso sin goce aprobado | PSS | Días |
| Retardo calculado | RET | Horas, configurable a minutos |
| Extra doble calculada | HE2 | Horas |
| Extra triple calculada | HE3 | Horas |

También pueden activarse días trabajados (TRAB), vacaciones y ausencias justificadas con/sin goce; los últimos conceptos requieren definir su código. El ejemplo no activa TRAB para evitar que coincida automáticamente con las demás incidencias del día.

CAST, HE1, HE4, HE5 y subtipos de incapacidad ENFG/MAT/ATRB/ATRY se presentan en **Conceptos de referencia pendientes de integración**. La incapacidad general registrada en Vera Time sí puede seleccionarse; solo debe activarse si el destino admite ese tipo por el medio elegido. Maternidad/paternidad se conserva como tipo combinado y no se convierte automáticamente en MAT.

Las claves son ejemplos de la revisión suministrada, no una certificación contra la versión instalada. Confirmar RET (horas/minutos), claves y códigos de empleado antes de copiar el bloque a la hoja oficial como valores. Se usa el código del trabajador guardado en el cierre; aún no hay tabla de equivalencias de códigos de empleados externos. Excel conserva códigos como texto; al importar CSV/TXT en Excel se debe elegir texto para esa columna.

## Fuente e integridad

La vista inicial utiliza seis incidencias ficticias, claramente identificadas y descargables como ejemplo. La exportación real lee únicamente el detalle verificado de un cierre `attendance_period.v2` mediante el lector de asistencia existente; no llama a los generadores CSV/Excel anteriores ni recalcula jornadas.

La autorización del cierre, empresa, esquema e integridad SHA-256 se verifican antes de generar. Un período cerrado de asistencia no acredita que el período de nómina esté abierto o el empleado activo en CONTPAQi; esta versión no se conecta al sistema destino.

Múltiples incidencias en una celda, registros duplicados, identificación ambigua, fuentes numéricas faltantes o cantidades inválidas bloquean la descarga. Nunca se elige silenciosamente una incidencia ganadora. Los conceptos aprobados fuera de la selección se informan en la vista previa. Excluir un concepto no modifica sus datos originales.

Vista previa limitada a 20 filas y 16 columnas; descarga completa. Límites de generación: 366 días, 100,000 registros de origen, 10,000 trabajadores y 250,000 celdas. XLSX escribe las fechas como fechas reales y todos los demás valores como texto; CSV/TXT protegen valores que podrían interpretarse como fórmulas.

## Despliegue y comprobación

Nueva migración aditiva: `2026_10_26_010000_create_export_layouts_table.php`. Ejecutar `php artisan migrate --force` al desplegar el código y compilar assets. La migración se aplicó localmente sin alterar trabajadores, marcajes, jornadas ni plantillas previas. No se ha desplegado a producción.

Pruebas: transposición, nomenclaturas/unidades, filtros, conflictos, identificación duplicada, guardado/duplicado, revisión concurrente, permisos/aislamiento, cierres inmutables, integridad, Excel y archivos de texto. Se compararon los hashes de los cuatro archivos del generador anterior para confirmar que siguen idénticos.


### Resultado de verificación local

- 48 pruebas correctas / 204 aserciones en el nuevo creador, exportaciones históricas anteriores y gestión de empresas.
- Una prueba adicional de descanso trabajado: correcta / 2 aserciones. Total de casos distintos verificados: 49.
- Build de assets, compilación de vistas, Pint y revisión del diff correctos.
- Revisión visual de las vistas reales del diseñador renderizadas con los datos ficticios del ejemplo; las interacciones de guardado, filtros, menús, transposición y descargas se comprobaron con Livewire.
- Los cuatro archivos principales del generador anterior conservan exactamente sus hashes previos.


## Ampliación del catálogo — 27/10/2026

El creador ofrece los nueve tipos registrados en `AttendanceIncident::types()` y todas las cantidades/indicadores de incidencia disponibles en el cierre. El catálogo incorpora domingo trabajado, prima dominical aplicable, descanso asignado/semanal trabajado, domingo en descanso, festivo/descanso obligatorio, descanso programado, horas ordinarias, extra total, tiempo nocturno, componentes de horas de festivo, pausas computables/no computables, salida anticipada, tiempo de ausencia, día con incidencia/aviso e incidencias aprobadas con/sin goce.

Los indicadores duplicados de descanso asignado/semanal usan un concepto canónico; los componentes de total y desglose se ofrecen por separado para configurar el destino, no para sumarlos nuevamente.

Todos los conceptos agregados están desactivados inicialmente. Las claves de los conceptos nuevos se dejan vacías para que cada empresa configure sus equivalencias, sin inventar claves de NomiPAQ. La selección inicial del ejemplo continúa con las seis incidencias anteriores.

Los layouts existentes reciben las reglas faltantes desactivadas al abrirse o validarse, respetando su orden, claves y selección. No se modifica la configuración almacenada hasta que el usuario guarda; no requiere migración adicional. Las descargas existentes comparan configuraciones normalizadas para que la actualización del catálogo por sí sola no obligue a guardar.

Domingo y festivo pueden expresar días, horas o minutos cuando el cierre conserva esas horas. Prima dominical expresa un día con aplicación de prima: no es porcentaje ni importe monetario. Las incidencias registradas solo generan días cuando su estado es aprobado. Faltas de indicadores/horas en cierres antiguos producen un error; nunca se completan con los datos actuales ni se interpretan automáticamente como cero.

La búsqueda por nombre/código funciona en Incidencias y Nomenclaturas. Las selecciones y unidades son independientes por layout. Se conserva la regla actual de una incidencia por celda: domingo y prima activados simultáneamente para el mismo día generan un conflicto explícito; esta ampliación no presupone soporte de múltiples códigos en NomiPAQ.

Los avisos operativos de revisión no se fabrican como cantidades de nómina. El indicador “Día con incidencia o aviso” marca lo registrado en el cierre, sin pretender contener el detalle histórico completo de todos sus avisos.


Validación de la ampliación: 31 conceptos seleccionables (9 tipos registrados y 22 conceptos/indicadores del cierre); 8 referencias externas sin origen específico permanecen pendientes. Se ejecutaron 34 pruebas / 224 aserciones correctas, incluyendo compatibilidad de layouts anteriores, cobertura exhaustiva de tipos registrados, unidades de domingos/festivos, prima sin importe, datos históricos faltantes, búsqueda y exportación CSV histórica. Build de assets, Pint y diff correctos. Sin migración adicional ni reescritura de configuraciones existentes.


## Exportación de períodos y layout predeterminado (27/10/2026)

La acción principal de Períodos de asistencia ahora es Exportar con layout. Abre un panel que selecciona el layout activo predeterminado de la empresa y permite elegir otro para esa descarga. Descargar con layout utiliza el formato guardado (XLSX, CSV o TXT), orientación, incidencias y nomenclaturas del nuevo creador. No modifica las jornadas, sus cálculos ni el cierre histórico.

En Configuración > Layouts de exportación > Mis layouts, Usar como predeterminado cambia la preferencia de esa empresa. Solo hay uno; se serializan guardados y cambios mediante bloqueo de empresa. El primer layout creado activo en una empresa sin layouts queda predeterminado automáticamente. Los layouts anteriores a la migración requieren elegir explícitamente uno. Inactivar el predeterminado elimina esa preferencia; no se sustituye silenciosamente por otro formato. Elegir otro layout en una descarga no cambia el predeterminado.

La columna export_layouts.is_default es independiente de la configuración y sus revisiones. Se agrega con false y no modifica ningún layout existente. La autorización para cambiarla usa ExportLayoutPolicy. La descarga vuelve a comprobar empresa, permisos de período cerrado, layout activo y la instantánea histórica. No se exportan registros actuales en lugar del cierre ni se usa el formato anterior como respaldo ante un error. Los conflictos del nuevo escritor bloquean la descarga.

El generador CSV anterior permanece intacto: su módulo Configuración > CSV de períodos, sus plantillas, escritores y rutas de descarga siguen funcionando. En el panel de exportación se accede desde el desplegable Exportador CSV anterior, con CSV anterior y Excel anterior. Las API anteriores conservan sus rutas y contratos; esta sustitución corresponde al flujo principal de la interfaz de períodos.

Comprobación manual: guardar o abrir NomiPAQ, marcar Usar como predeterminado, abrir un período cerrado > Exportar con layout, verificar que aparezca seleccionado y descargar. Cambiar de layout en el panel solo afecta esa descarga. Comprobar el desplegable del exportador anterior. Las empresas sin layouts reciben acceso a configuración; no se inventa un formato.


### Limpieza de la pantalla del creador — 28/10/2026

El creador conserva diseño, incidencias disponibles, nomenclaturas, vista previa y descarga del reporte ficticio de prueba. Se retiraron el texto conversacional sobre la revisión de CONTPAQi, el bloque de exportación desde períodos cerrados y el listado de conceptos de referencia pendientes de integración. La exportación real se realiza desde Períodos de asistencia > Exportar con layout. Los conceptos no disponibles siguen bloqueados en la validación del dominio; ocultar la sección informativa no agrega equivalencias ni modifica reglas o layouts guardados.


### Revisión de textos ajenos al creador — 28/10/2026

Se retiraron el aviso lateral del generador CSV anterior, la explicación sobre conservación de cálculos de asistencia y la referencia a copiar en una hoja oficial. Se conservan las instrucciones de distribución, claves, unidades, selección de incidencias, datos ficticios y límites de vista previa. Los errores de validación siguen visibles; el error del predeterminado se presenta una sola vez en el resumen general. Las notas de cada incidencia se conservan porque explican qué dato exporta y evitan equivalencias incorrectas.


### Descarga directa con el predeterminado — 28/10/2026

Exportar períodos utiliza directamente el layout activo predeterminado de la empresa. Se retiraron del panel el selector de formato, el enlace de configuración permanente y el desplegable del exportador CSV anterior. La descarga resuelve nuevamente la preferencia en servidor: no acepta un layout seleccionado desde ese panel. Solo cuando falta un predeterminado se muestra un aviso y acceso al creador. El generador CSV anterior conserva su módulo, plantillas y rutas; no se eliminan.


## Retiro del editor CSV anterior — 28/10/2026

El único editor visible es Layouts de exportación. Se eliminan el componente Livewire anterior, su entrada CSV de períodos del menú y su policy exclusiva de edición. La URL antigua /time/configuration/csv-periodos redirige al nuevo creador para mantener marcadores. Entrar a esa dirección ya no genera plantillas antiguas automáticamente.

Exportar períodos mantiene el uso del layout predeterminado nuevo. No se borran tablas, plantillas guardadas ni instantáneas de cierres. Las Actions y modelos requeridos por las descargas web/API anteriores y el formato del cierre se conservan como compatibilidad; no habilitan el editor retirado. No requiere migración.

Se comprueba redirección, ausencia del componente y del acceso lateral, acceso al nuevo creador, descargas heredadas y aislamiento de plantillas por empresa.
