# Base de migraciones consolidada del 1 de noviembre de 2026

## Alcance y decisión

El responsable confirmó que solo hay empresas de prueba, que todavía no hay un cliente real y que acepta recrear esas bases. Se establece una nueva base de instalación: **63 migraciones en lugar de 84**. Los cambios que solo agregaban campos se incorporan a las migraciones que crean las tablas.

Esta entrega está destinada a una **base nueva o a la recreación explícita de una base de pruebas**. No es un paquete de actualización incremental para una base instalada con el historial anterior. Los documentos de despliegue de fechas anteriores describen el historial anterior y no sustituyen esta instrucción.

No se ejecutó `migrate:fresh` contra la base de la aplicación ni contra una base remota. La verificación utiliza SQLite aislado y bases MariaDB de auditoría con nombre propio.

## Campos integrados

| Tabla | Cambios que ahora se crean desde el inicio |
|---|---|
| users | Estado y rol global, con sus índices. |
| companies | Relación con cuenta de cliente y ciclo de prueba. |
| company_settings | Actualización de jornadas, tolerancias, imagen y claves del flujo actual de autorización de kiosco. Se omiten los campos retirados del flujo antiguo. |
| workers | Fecha de nacimiento opcional. |
| time_events | Anulación, responsable, motivo e índice por empresa y fecha de anulación. |
| schedule_batches | Motivo de corrección. |
| work_day_calculations | Bandas de extra doble/triple y diferencias de puntualidad. |
| legal_parameters | Motivo, usuarios de auditoría y metadatos. |
| attendance_periods | Validación, cierre, resúmenes y snapshot histórico con hash. |
| kiosk_devices | Borrado lógico. |
| mobile_marking_policies | Unidad organizacional y duración de autorización offline. |
| mobile_device_binding_authorizations | Nombre solicitado del dispositivo y reto cifrado. |
| company_demo_scenarios | Tipo de escenario. |
| export_layouts | Preferencia predeterminada e índice. |

La creación de `customer_accounts` se adelanta al 5 de julio de 2026, antes de `companies`, para que la clave foránea pueda crearse en el mismo archivo de empresas. La antigua conversión de empresas ya existentes a cuentas individuales no es necesaria en una base nueva.

Las pruebas reales en MariaDB detectaron que `mobile_marking_policies.valid_from`, `valid_until` y `offline_valid_until` usaban `timestamp` y rechazaban vigencias posteriores a su rango. Se corrigen a `datetime` en su creación, con la misma nulabilidad. Esta ampliación de rango es una corrección intencional adicional a la consolidación; no modifica límites de negocio ni las validaciones de ubicación.

Se retira la migración redundante de ampliación de fechas de marcaje: sus migraciones de creación ya definen esas columnas como `datetime`. Se conserva la cobertura que comprueba fechas posteriores a 2038 y campos opcionales nulos.

## Migraciones que se conservan separadas

Se mantiene el retiro de `labor_conditions` a `retired_labor_conditions`, con sus comprobaciones de archivo y rollback, y la actualización versionada del catálogo legal. Sus objetivos son preservar o transformar datos, no agregar un campo aislado. También se conserva la creación de la relación circular entre jornadas y su cálculo activo en el momento en que ambas tablas ya existen.

No se introduce un archivo SQL opaco ni un volcado dependiente de un servidor concreto: el esquema sigue expresado en las migraciones de Laravel.

## Instalación en una base vacía

Configurar la conexión con la base vacía destinada a esta instalación y ejecutar:

```bash
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=ProductSeeder --force
php artisan db:seed --class=LegalRuleSeeder --force
php artisan db:seed --class=AlertTypeSeeder --force
```

`DatabaseSeeder` carga únicamente catálogos: no crea empresas, usuarios ni superadministradores. Para crear el acceso de plataforma, ejecutar `php artisan veratime:create-super-admin` desde una terminal interactiva de cPanel o SSH, en la carpeta que contiene artisan. Solicita nombre, correo y contraseña oculta con confirmación; requiere al menos 12 caracteres con mayúsculas, minúsculas y números. No acepta contraseñas como argumentos, no las toma de variables de entorno ni las imprime. Guarda el hash mediante el modelo User y rechaza correos ocupados sin alterar cuentas existentes. `SuperAdminSeeder` queda como aviso de compatibilidad y no crea accesos. Las empresas se crean desde la interfaz, vacías, con demos opcionales y captura manual disponible.

## Recreación de una base de pruebas con el historial anterior

1. Conservar un respaldo si se quieren recuperar las demos, usuarios o archivos actuales.
2. Poner la aplicación en mantenimiento y verificar que la conexión apunta exactamente a la base de pruebas que se desea reemplazar.
3. Desplegar el código consolidado.
4. Ejecutar de forma explícita:

```bash
php artisan migrate:fresh --force
```

Este comando elimina las tablas de la base seleccionada. No se ejecuta como parte automática de esta entrega. Volver a cargar catálogos y, si se desea, demos. Los archivos de almacenamiento no se migran ni se borran con este comando; los adjuntos anteriores pueden quedar sin referencia y su limpieza debe planificarse por separado.

5. Compilar assets y limpiar/regenerar las cachés del despliegue. Verificar acceso administrativo, empresa activa, publicación de una semana, registro y cálculo, cierre y descarga antes de habilitar la operación.

No ejecutar únicamente `migrate --force` sobre la base anterior esperando que se reconstruya: las migraciones ya aplicadas no se vuelven a ejecutar y el archivo adelantado de cuentas de cliente no pertenece a su historial anterior.

## Regla a partir del primer cliente real

Conservar esta base como historial de instalación y volver a cambios incrementales para cualquier modificación posterior. No editar una migración ya desplegada para evitar una migración nueva cuando existan datos que deban preservarse.

## Evidencia de validación

La auditoría compara el esquema anterior y el consolidado en SQLite y MariaDB/MySQL, incluyendo tipos, valores predeterminados, nulabilidad, índices y relaciones. La comparación ignora el orden físico de las columnas y la tabla administrativa `migrations`. La consolidación conserva las mismas 70 tablas y sus definiciones. Tras la corrección de vigencias, las únicas diferencias intencionales en MariaDB son los tres campos de política móvil ampliados a `datetime`; SQLite ya representa ambos tipos como datetime. No se pierden columnas, índices ni relaciones.

`MigrationLifecycleTest` instala, revierte el lote completo y reinstala, comparando nuevamente columnas e índices y relaciones. `CompanyToPayrollExportTest` recorre altas, estructura, publicación, turnos de día y noche, revisión de una salida faltante, resultados, cierre, exportaciones históricas y aislamiento entre empresas. El informe de pruebas del 1 de noviembre contiene los resultados finales de las suites.
