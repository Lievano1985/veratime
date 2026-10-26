# Cierres y exportaciones históricas

Al cerrar un periodo, Vera Time conserva el resumen y todas las filas de exportación en una misma copia histórica con hash SHA-256. CSV y Excel usan esos valores, tanto desde la web como desde la API. No vuelven a consultar jornadas, cálculos, nombres, centros, datos fiscales, marcajes ni dictámenes actuales para construir sus filas.

Las plantillas permiten seleccionar y ordenar las columnas históricas y cambiar encabezados; no recalculan sus valores. Se conserva el formato de columnas existente, los identificadores con ceros iniciales y la protección de CSV frente a fórmulas. Excel escribe los valores como texto.

## Datos y arquitectura

- `CloseAttendancePeriodAction` captura el reporte y las filas dentro de la transacción del cierre.
- `BuildAttendancePeriodPayrollRowsAction` reutiliza la consulta por empresa, centro, rango y unidades; solo captura periodos abiertos o listos. La transformación de filas se comparte con todos los formatos.
- El cierre nuevo usa `attendance_period.v2`. Su JSON canónico incluye `company_id`, `period_id`, `report` y `payroll_export`; este último tiene esquema `payroll_rows.v1`, columnas y filas por clave.
- `ResolveAttendancePeriodPayrollRowsAction` comprueba estado cerrado, integridad, esquema, identidad de empresa/periodo y estructura de valores. No tiene una alternativa basada en datos actuales.
- Los exportadores verifican la copia antes de iniciar la respuesta de descarga; la API informa errores de validación y la web los muestra en Periodos.
- Se reutilizan las columnas de almacenamiento existentes; no se requieren migraciones ni regenerar cierres.

## Cierres anteriores

Un cierre anterior que solo guarda resumen no permite reconstruir las filas originales con certeza. Se conserva sin reabrirlo, reescribirlo ni recalcular su hash. La pantalla permite consultar el resumen y explica que no hay detalle histórico exportable; las descargas se rechazan antes de emitir un archivo. No se presenta una exportación de datos actuales como si fuera la original.

## Verificación

Las pruebas cierran un periodo, descargan CSV y Excel, cambian la identidad del trabajador, empresa, centro, horas, marcajes y jornadas incluidas, y comprueban que las filas permanezcan iguales y que el cierre no cambie. También cubren plantillas personalizadas, integridad, separación entre empresas, cierres anteriores y permisos web/API.

Validación del 24 de octubre de 2026: suite completa con 882 pruebas y 4325 aserciones aprobadas. Verificación adicional en MariaDB mediante cierre transaccional, comprobación del hash y generación de CSV/Excel, seguida de rollback verificado. La base local contiene dos periodos abiertos y ningún cierre anterior pendiente de tratamiento; no se modificaron esos periodos.
