# Vera Time Revisión integral del proyecto

Fecha de cierre de revisión: 1 de noviembre de 2026.

## Resultado

Se completó la revisión automatizada del código local, desde la creación de empresas hasta el resultado de jornadas, cierre y exportación. Las migraciones pasaron de **84 a 63**, integrando **21 archivos redundantes** en la creación de sus tablas. Se mantuvieron las 70 tablas y sus relaciones.

| Validación final | Pruebas | Aserciones | Fallos o errores |
|---|---:|---:|---:|
| Suite completa con SQLite aislado | 982 | 5101 | 0 |
| Selección crítica con MariaDB/MySQL real | 128 | 716 | 0 |
| Revisión enfocada posterior a la corrección de fechas en SQLite | 28 | 180 | 0 |

Las pruebas de MariaDB y la revisión enfocada repiten casos de la suite, no funcionalidades distintas que deban sumarse como casos únicos. Se verificaron también 702 archivos PHP sin errores de sintaxis, formato de los archivos modificados, compilación de assets, compilación de vistas y revisión de diferencias.

## Hallazgos y correcciones

### Vigencias futuras de políticas móviles

La ejecución real en MariaDB descubrió que una política con vigencia en 2099 no podía guardarse por usar columnas timestamp. Se ampliaron `valid_from`, `valid_until` y `offline_valid_until` a datetime, conservando nulabilidad. La corrección se integró en la migración base y se comprobó con las pruebas móviles y del esquema.

No se modificaron por este cambio las reglas de georreferenciación, precisión, área autorizada, firma ni dispositivo vinculado.

### Historial de migraciones fragmentado

Se integraron estados y roles de usuarios, tolerancias, actualización de jornadas, datos de anulación, campos de cierre, bandas de tiempo extra, fecha de nacimiento, campos de dispositivos y políticas móviles, imagen de empresa, escenarios demo y preferencia de exportación.

La cuenta de cliente ahora se crea antes de empresas para definir su clave foránea desde el inicio. Se retiraron los campos obsoletos del flujo anterior de activación de kiosco desde su creación. Se conservaron las migraciones con responsabilidad de datos e historial, como el archivo de condiciones laborales retiradas y las versiones del catálogo legal.

### Compatibilidad con PHP 8.5

Se ajustó el uso de la constante del controlador MySQL para evitar avisos de deprecación en PHP 8.5, manteniendo una alternativa para versiones anteriores compatibles con el proyecto. Los avisos ya no enmascaran el resultado final de las pruebas.

### Prueba de navegación desactualizada

La primera suite detectó una prueba que aún suponía que el flujo anterior de horarios estaba activo por defecto y que esperaba el nombre antiguo de días inhábiles. Se corrigió su preparación para activar explícitamente el modelo anterior y comprobar los nombres actuales. No se ampliaron permisos ni se retiró la protección del modelo inactivo.

## Cobertura por módulo

| Módulo | Comprobaciones incluidas |
|---|---|
| Acceso y usuarios | Autenticación, estado, roles, cuenta de cliente, producto y selección de empresa. |
| Organización | Creación y edición de empresas y centros, estructura, asignaciones y alcances operativos. |
| Trabajadores | Altas, relaciones laborales, cambios y bajas, credenciales, importación XLS/XLSX y duplicados. |
| Catálogo y horarios | Turnos, tramos, perfiles, asignación semanal, planificador y selección del modelo por empresa. |
| Publicación | Borradores, validaciones, correcciones versionadas, clonación e integridad del snapshot. |
| Archivos de programación | Plantillas, lectura, vista previa, contexto de empresa, fórmulas y cambios concurrentes. |
| Marcajes | Eventos web, kiosco, captura justificada, anulación, aprobaciones y secuencias válidas o incompletas. |
| Seguridad móvil | Políticas, dispositivo, firma, ubicación y área autorizada; rechazo antes de crear eventos inválidos. |
| Incidencias y ausencias | Tipos, referencias, vigencias, cancelación y aplicación a jornadas. |
| Jornadas y reglas | Generación, pausas, puntualidad, turnos nocturnos, clasificación, ordinario/extra, descansos y versiones. |
| Revisión y prevención | Alertas, atención, recálculo, vista operativa, tablero y límites por alcance. |
| Períodos | Validación de bloqueantes, alcance, cierre y snapshot histórico. |
| Exportaciones | CSV y Excel, layouts, conceptos, unidades, valores históricos, conflictos y permisos. |
| API y aislamiento | Tokens, permisos, idempotencia y rechazo de información ajena a la empresa activa. |
| Instalación | Instalación limpia, rollback completo y reinstalación; comparación de columnas, índices y relaciones. |

## Recorrido nuevo de principio a fin

Se agregó `CompanyToPayrollExportTest`, que utiliza las Actions reales de la aplicación para:

1. Crear una empresa, centro, departamento y dos trabajadores con sus relaciones laborales y asignaciones.
2. Crear turnos de día y noche, preparar la semana en el planificador y publicarla.
3. Registrar un permiso con goce y eventos de entrada, pausa y salida.
4. Comprobar que una salida nocturna faltante impide cerrar el período.
5. Completar la salida y recalcular: 480 minutos de trabajo y 60 de pausa en el turno diurno; 450 minutos y 30 de pausa en el nocturno, con su clasificación y desglose.
6. Documentar los pendientes, validar y cerrar el período con hash de integridad.
7. Descargar CSV y Excel y demostrar que no cambian al editar después la identidad del trabajador.
8. Cambiar a otra empresa y comprobar que no se puede descargar el cierre anterior desde ese contexto.

Las pruebas existentes complementan ese caso con importaciones, kiosco, móvil, variantes de perfiles, errores y exportaciones configurables. El caso integral no reemplaza dichas pruebas ni fabrica cálculos o cierres para saltarse el flujo real.

## Comparación de esquemas

Antes y después se generaron bases aisladas con ambos motores. La consolidación produjo el mismo esquema lógico en las 70 tablas. Tras detectar el problema de fechas futuras, las únicas diferencias deliberadas en MariaDB son los tres campos de política móvil ampliados de timestamp a datetime.

Se compararon tipos, valores por defecto, nulabilidad, índices y claves foráneas. Se excluyen el orden físico de columnas y las filas administrativas de la tabla migrations, porque el historial se reinició como se acordó. No se descartaron campos de operación ni relaciones.

`MigrationLifecycleTest` comprueba instalación, reversión y reinstalación. La prueba de fechas verifica las columnas de seguridad y las fechas fuera del rango antiguo.

## Despliegue de la base consolidada

Esta versión se instala en una base vacía o recreando expresamente una base de pruebas. **No basta ejecutar migrate sobre el historial anterior.** El responsable confirmó que no existen clientes reales y aceptó la recreación de las bases de prueba.

La base utilizada por la aplicación no se recreó durante esta revisión, ni se accedió a producción remota. Las pruebas destructivas de instalación y rollback se ejecutaron únicamente en esquemas aislados de auditoría.

El procedimiento se entrega por separado en `Vera_Time_Recrear_Bases_de_Prueba.md` y queda en el repositorio como `docs/10-Deployment/DEP-0002-BASE-CONSOLIDADA-2026-11-01.md`. Incluye la diferencia entre instalación vacía, recreación de pruebas y cambios incrementales a partir del primer cliente real.

## Límites de esta revisión

La revisión valida código y flujos automatizados del repositorio y su base de datos. No constituye una certificación legal ni una prueba de carga con volúmenes reales. No se inspeccionó el binario o código de la app móvil externa, ni se probó GPS físico en un teléfono. Sí se probaron los controles del servidor que recibe sus marcajes. Tampoco se validó la importación final dentro de un proveedor de nómina externo o la configuración real de cron, correo y respaldo de un hosting remoto.

No se hizo commit, push ni despliegue remoto como parte de esta tarea.
