# Punto de control previo a publicación — 28/10/2026

## Recuperación del código

- Base anterior publicada: 0bbe3e589c3cf9aec47a8a396c308de1752c8bbf.
- Rama local de base: backup/before-push-2026-10-28.
- Copia local completa del código pendiente, incluidos archivos nuevos: backup/checkpoint-2026-10-28, commit 355aa26.
- La copia corresponde a código versionable. No contiene .env, base de datos, archivos de almacenamiento ignorados ni credenciales de producción.

## Alcance de la entrega

Simplificación de trabajadores y retiro de condición laboral con archivo reversible, diseño compartido de paneles, dos variantes demo con limpieza exclusiva de superadministrador, creador independiente de layouts y catálogo de incidencias, plantilla predeterminada por empresa, nuevo flujo Exportar períodos y retiro del editor CSV anterior. Las descargas API anteriores y los cierres conservados mantienen compatibilidad.

## Migraciones para producción

Después de actualizar el código se ejecuta php artisan migrate --force, con respaldo normal de la base de producción antes del despliegue. Hay cinco migraciones nuevas desde la base publicada:

1. 2026_10_26_000000_retire_labor_conditions_table: renombra labor_conditions a retired_labor_conditions para conservar sus datos; no elimina sus filas. Detiene el proceso si ya existe una tabla de destino incompatible.
2. 2026_10_26_010000_create_export_layouts_table: crea la tabla del nuevo editor.
3. 2026_10_27_000000_add_scenario_type_to_company_demo_scenarios_table: agrega tipo de demo con valor compatible para registros existentes.
4. 2026_10_27_010000_create_company_demo_reset_logs_table: crea copias internas de limpiezas demo; no ejecuta limpiezas al migrar.
5. 2026_10_27_020000_add_is_default_to_export_layouts_table: agrega la preferencia de layout; los existentes se seleccionan explícitamente desde el editor.

No usar migrate:fresh ni seeders en producción. Los demos solo se generan mediante una acción explícita de interfaz. La limpieza operativa exige superadministrador, confirmación por nombre y respaldo interno previo.

Se debe compilar el frontend y limpiar las cachés de aplicación/rutas/vistas según el procedimiento normal del servidor. No basta revertir archivos PHP tras una migración: la recuperación debe coordinar la versión de código con el esquema y el respaldo de datos.

## Verificación

La entrega se revisa con suite completa, sintaxis PHP de archivos modificados, compilación frontend, revisión de diferencias y estado de migraciones local. El resultado final y el commit publicado se informan al completar el push.


Resultado final previo al commit: 932 pruebas aprobadas, 4692 assertions, suite completa (1396.23 s); sintaxis PHP sin errores; Pint --test --dirty aprobado; npm run build aprobado; git diff --check aprobado. Las cinco migraciones nuevas constan aplicadas localmente. La etiqueta checkpoint-2026-10-28 identifica la entrega revisada; las ramas de respaldo anteriores permanecen locales.
