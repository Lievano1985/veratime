# Aislamiento obligatorio de la base de pruebas

El 1 de noviembre de 2026 una ejecución de ShiftTemplateCatalogTest cargó la configuración local cacheada por artisan config:cache. RefreshDatabase reinicializó las tablas de vera_time. Se detuvo el proceso y se restauró el respaldo SQL tomado ese día a las 10:49. La restauración recuperó una empresa, cinco trabajadores y dos usuarios. No se encontraron binlogs habilitados para recuperar capturas posteriores al respaldo; esas capturas no se consideran recuperadas.

El esquema del respaldo había sido comparado durante la auditoría con el consolidado. Se alineó el historial administrativo a las 63 migraciones y se ampliaron a datetime las tres vigencias de políticas móviles. No se debe repetir esta intervención como un procedimiento ordinario.

Controles agregados:

- phpunit.xml utiliza tests/bootstrap.php, que desvía APP_CONFIG_CACHE a una ruta exclusiva de pruebas antes del arranque de Laravel.
- TestCase::createApplication verifica la base resuelta antes de que RefreshDatabase pueda actuar. Solo permite SQLite :memory: o bases de auditoría con prefijo vt_audit_. Cualquier otra conexión produce una excepción.
- No se debe limpiar la caché de la aplicación para ejecutar pruebas: estas deben permanecer aisladas aunque la aplicación tenga configuración cacheada.

La prueba del catálogo se volvió a ejecutar con la caché local presente. Se verificó que la base de la aplicación mantuviera una empresa, cinco trabajadores y dos usuarios.
