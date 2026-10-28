# Demo de dos semanas cerradas

El superadministrador puede elegir en Empresas > Editar empresa > Generar escenario demo:

- Operación y revisión: conserva el escenario original con pendientes.
- Dos semanas terminadas para exportar: dos últimas semanas completas consecutivas, cada una de lunes a domingo, cinco trabajadores (tres diurnos y dos nocturnos), dos centros, programación publicada, marcajes completos y vacaciones, permiso pagado, falta injustificada e incapacidad aprobados. No agrega actividad de la semana actual.

Se genera una opción por empresa vacía. El superadministrador puede borrar o reemplazar un demo existente desde Administrar demo > Borrar o reemplazar demo. Para comparar dos escenarios simultáneamente se usan empresas distintas.

La segunda opción procesa jornadas, registra las revisiones ficticias mediante ResolveAlertAction y cierra ambos períodos mediante CloseAttendancePeriodAction, con validación e instantánea verificable. Cualquier fallo revierte los datos operativos del escenario. Las revisiones son exclusivamente demostrativas, no evidencia de una operación real.

La selección se valida en servidor: un administrador de empresa no puede solicitar closed_week aunque manipule el formulario. La actualización del día actual no modifica esta variante. El campo scenario_type se agrega con valor operacional predeterminado para conservar demos existentes; no elimina datos.

Pruebas: CompanyDemoScenarioTest cubre escenarios originales, exclusividad de la opción, permisos, semana anterior, períodos cerrados, lectura histórica de exportación e idempotencia.

Validación manual: crear una empresa vacía como superadmin, elegir Dos semanas terminadas, comprobar el intervalo de catorce días anterior y dos períodos cerrados en CSV de períodos. En el creador guardar un layout con vacaciones o permiso pagado habilitados, seleccionar uno de esos períodos y exportar. Los conceptos deshabilitados no se incluyen. El generador CSV existente sigue independiente.

## Limpieza y reemplazo (27 de octubre)

Solo un superadministrador activo puede ejecutar ResetCompanyDemoScenarioAction. Debe escribir el nombre exacto de la empresa y elegir dejarla vacía, operación y revisión, o semana terminada. La autorización expresa permite borrar todos los registros operativos de esa empresa, incluidos los agregados después del demo, credenciales de trabajadores, dispositivos y períodos cerrados. No se permite borrar durante preparación pendiente/en proceso ni sin un registro demo previo.

Se conservan empresa, cuenta cliente, usuarios y membresías, configuración, reglas y parámetros legales, días obligatorios y layouts/plantillas de exportación. Se limpia únicamente el resumen de último recálculo, que ya no corresponde a jornadas existentes. Las otras empresas no se modifican. Los usuarios conservan su acceso de administración; sus vínculos a trabajadores y credenciales de marcaje se eliminan con la operación.

Antes de limpiar se guarda una copia de las filas operativas, el escenario anterior y configuración en company_demo_reset_logs, con actor, fecha, opción elegida y SHA-256 del JSON. Es una copia interna para recuperación técnica, no un botón de restauración automática. No se desactivan claves foráneas: se eliminan primero las dependencias; referencias anulables se separan después de guardar la copia. El borrado y eventual regeneración son una transacción: si falla la sustitución se conserva íntegro el escenario anterior. Un fallo por dependencias también revierte todo.

La actualización del día actual bloquea la empresa y comprueba que el escenario aún existe; una instancia antigua no recrea el demo borrado. Los jobs de eventos eliminados terminan sin procesarlos al no encontrar el evento.

Pruebas adicionales: reemplazo semana cerrada por operación y limpieza posterior; aislamiento entre empresas; conservación de accesos/configuración; copia con hash e instantánea del cierre; eliminación de datos añadidos/credenciales/kioscos; permiso exclusivo de superadmin; confirmación y estado; rollback si falla el nuevo escenario; formulario y limpieza de campos. La migración nueva solo crea la tabla de copias: no borra datos por sí misma.

## Alineación de semanas y cinco trabajadores

La variante para exportar usa las dos últimas semanas ISO terminadas, sin incluir la semana en curso. Genera 5 trabajadores desde el alta (no crea diez para borrar cinco después), 4 lotes publicados (dos centros por dos semanas) y 2 períodos cerrados, uno por centro, abarcando los 14 días. Cada semana tiene incidencias aprobadas para probar su inclusión en layouts. Los descansos de sábado y domingo conservan su programación; semana completa no significa trabajar los siete días.

El resumen conserva número de semana ISO, año ISO y límites de cada semana; el banner los muestra junto a los cinco trabajadores. Ejemplo al 27/10/2026: semanas 42 y 43, del 12/10 al 25/10. Al 11/01/2027: semanas 53/2026 y 1/2027, del 28/12/2026 al 10/01/2027. No se utilizan días sueltos ni una quincena que corte semanas.

Se conserva la clave interna closed_week por compatibilidad con demos anteriores. La nueva versión de resumen es closed_weeks_export_v2. Los demos ya generados no se modifican solos: el superadministrador puede reemplazarlos mediante la opción existente. No requiere una migración adicional.

## Acceso visible a la limpieza

El botón Borrar datos de empresa usa el estilo existente btn-danger y aparece junto a la leyenda del demo, tanto en el aviso superior como al editar la empresa. openDemoReset valida superadministrador, carga la empresa elegida y abre la confirmación directamente. No borra datos al abrir. Se eliminó el uso de btn-secondary (sin definición visual) en este control. Las pruebas verifican apertura directa y bloqueo del administrador de empresa.
