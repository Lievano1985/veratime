# Verificación previa al commit del 1 de noviembre de 2026

Se revisó el conjunto acumulado de cambios de planificador, importación Excel, organización, pantalla de bienvenida, instalación limpia, migraciones consolidadas y portal personal del trabajador.

- La ejecución completa en base aislada recorrió 1,012 pruebas: 1,008 aprobadas y cuatro fallos, con 5,232 aserciones.
- Se corrigió un problema real del filtro de alertas: los límites de fecha locales ahora se convierten a UTC antes de consultar detected_at. La regresión incluye una alerta del lunes por la noche que ya corresponde al martes UTC. Pasaron las 26 pruebas del módulo y su API (151 aserciones).
- Se actualizaron tres expectativas antiguas: creación automática de la empresa demo, creación de superadministrador mediante seeder y prohibición del inicio administrativo del trabajador. El comportamiento actual es instalación sin usuarios/empresas y redirección al dashboard personal. Las cuatro pruebas de instalación y navegación afectadas pasaron con 27 aserciones.
- La suite completa no se repitió después de estas correcciones; se repitieron los módulos y casos afectados. No se atribuye una segunda ejecución completa aprobada.
- Se comprobaron 124 archivos PHP preparados para el commit, sin errores de sintaxis, y se revisó el contenido preparado sin coincidencias de los patrones de claves privadas y tokens comprobados. No se incluyen archivos .env, respaldos SQL, bases locales ni logs.
- La compilación de assets terminó correctamente durante la entrega del dashboard personal. git diff --check no encontró errores de espacios.

Antes del commit se creó y subió la etiqueta backup/pre-cambios-veratime-2026-11-01 sobre d6fdcec. Esa etiqueta es un punto de retorno del código, no un respaldo de base de datos. La base de migraciones consolidada está destinada a instalaciones nuevas o a la recreación expresamente autorizada de bases de prueba; consultar DEP-0002 antes de desplegar sobre una base anterior.
