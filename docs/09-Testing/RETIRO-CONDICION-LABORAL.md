# Retiro de condición laboral — 26/10/2026

Se retira el formulario, columna, historial visible, acciones, modelo, política y factory de condición laboral. Los seeders ya no la generan. No participa en cálculos de jornadas, horas extra, descanso o credenciales.

Se conservan fecha de alta, empresa, centro, estado del trabajador, relación laboral, historial operativo, horarios y credenciales. Los horarios con asignaciones de trabajadores conservan protección contra eliminación, incluso si la asignación es histórica. Un trabajador activo sin relación con el horario no bloquea su eliminación.

## Recuperación y despliegue

Punto previo: rama `backup/before-labor-condition-removal-2026-10-26`, commit `0bbe3e589c3cf9aec47a8a396c308de1752c8bbf`.

La nueva migración `2026_10_26_000000_retire_labor_conditions_table` renombra la tabla a `retired_labor_conditions`. Conserva sus filas y restricciones exclusivamente para recuperación; la aplicación no las consulta ni modifica en sus flujos normales. Al eliminar explícitamente una empresa, su archivo también se elimina como sus demás datos. Se conserva la migración original para instalaciones nuevas y rollback.

Desplegar código y ejecutar `php artisan migrate --force`. No usar `migrate:fresh`. Antes de revertir, identificar el lote de esta migración con `migrate:status`; revertir únicamente esta migración y restaurar el código del punto previo durante mantenimiento. No revertir a ciegas un lote con otras migraciones. La reversión restaura el nombre original sin reconstruir registros. Se rechaza sobrescribir una tabla existente.

## Comprobación manual

1. Abrir y editar trabajador: fecha de alta, empresa/centro y estado siguen disponibles; no aparece condición laboral.
2. Guardar cambios del trabajador, bloquear/restablecer credencial y confirmar aislamiento entre empresas.
3. Consultar programación, marcajes, jornadas y tablero: conservan sus datos.
4. Eliminar horario sin asignaciones; confirmar bloqueo de otro horario con asignaciones.
5. Ejecutar demo dos veces: mantiene trabajadores, credenciales y horarios sin crear condiciones.

Pruebas automáticas cubren la reversión con datos, rechazo de sobrescritura, pantalla de trabajadores, credenciales, acceso cruzado, demo y protección de horarios.

## Validación realizada

- Primera tanda: 59 pruebas / 241 aserciones correctas (trabajadores, credenciales, demo, horarios y migración).
- Tanda ampliada: 157 pruebas / 700 aserciones correctas (API de trabajadores, empresas, horarios/asignaciones, cálculos, atención de jornadas, tablero y kiosco).
- Comprobación final de recuperación y dependencias: 4 pruebas / 14 aserciones correctas.
- `npm run build`, Pint y `git diff --check` correctos.
- Migración y reversión verificadas en MySQL/MariaDB local: 0 filas de condiciones; sin cambios en los conteos de 10 trabajadores, 10 relaciones, 601 eventos, 200 jornadas y 165 cálculos. Los casos con filas archivadas se verificaron en pruebas aisladas.
- Producción no se modificó; falta desplegar código y ejecutar la nueva migración allí.

## Simplificación del formulario del trabajador

Se retiraron correo y teléfono de la captura web: no intervienen en asistencia, horarios, cálculos ni notificaciones de usuarios. Los datos existentes y los campos de API siguen disponibles. Al editar desde web se conservan ambos valores; la API puede actualizarlos o limpiarlos explícitamente.

Se mantienen fecha de nacimiento (alertas por edad), RFC y CURP (exportación de nómina), además de código, nombre, centro, puesto, fecha de ingreso y estado. No se elimina ninguna columna ni se requiere otra migración por esta simplificación. Verificar que editar el nombre conserve contactos y datos operativos.

### Ajuste posterior del panel

Por decisión del usuario, también se retiran CURP y fecha de nacimiento del formulario, y Puesto del formulario/listado. Se conservan columnas, valores existentes e integración API. Guardar desde web no borra estos valores ni cambia el puesto de la relación existente. Las alertas por edad siguen usando la fecha existente; los nuevos trabajadores sin fecha no generan estas alertas.

El panel se divide en Identificación, Datos laborales y Credencial kiosco, con mayor ancho adaptable, campos agrupados y acciones de trabajador en un pie separado. El motivo laboral aparece solamente al cambiar centro o fecha de ingreso; se conserva la exigencia y auditoría del servidor. Al restaurar los valores originales, vuelve a ocultarse.

Comprobar: motivo inicialmente oculto, visible al cambiar centro/ingreso, oculto al restaurar; edición de nombre conserva contactos, CURP, nacimiento y puesto; creación sin esos campos; permisos y credenciales intactos.
