# Prueba local ADMS del ZKTeco MB10-VL

## Diagnóstico y desvinculación

Después de desplegar, ejecuta `php artisan config:cache` en cPanel. Los rechazos de importación se escriben en `storage/logs/adms-AAAA-MM-DD.log`, con rotación diaria y retención configurada de siete archivos. Busca `adms.importacion_rechazada`. Se registra el motivo, la primera fila rechazada (numerada desde 1) y, cuando ayuda a corregir el problema, el ID numérico del reloj o el código de estado. El archivo no contiene cuerpos de solicitudes, rostros ni huellas.

En **Configuración de empresa → Relojes de asistencia → Relojes registrados**, usa **Desvincular** junto al ID exacto. Si `1` y `3` apuntan al mismo trabajador, eliminar uno conserva el otro y el historial.

La migración `2026_11_13_000000_create_attendance_clock_pending_events.php` agrega un registro para checadas de IDs sin vínculo activo o sin relación laboral vigente en el centro. Se guardan el ID del reloj, la fecha, la hora, el tipo y el motivo, sin guardar el cuerpo de la solicitud. El receptor confirma el lote después de guardar los eventos válidos y los pendientes en la misma transacción. Los reintentos no duplican registros. Las filas con formato, estado u hora inválidos siguen rechazando el lote.

Los códigos numéricos de estado sin mapeo, como `255`, también se conservan como pendientes. Se guarda el código original y se confirma el lote junto con las checadas válidas. Un estado con formato inválido sigue rechazando el lote.

En **Relojes de asistencia → Checadas pendientes de identificar o clasificar** se muestran las 50 más recientes de la empresa. Estas checadas quedan fuera de la jornada. Dar de alta un vínculo afecta marcajes futuros. La reclasificación de pendientes históricos requiere un flujo posterior.

## Piloto directo en producción

El piloto viene desactivado. Despliega el código y aplica las migraciones pendientes revisadas en el servidor con el procedimiento habitual de Vera Time. Registra el reloj y vincula al trabajador en la empresa correcta. En el `.env` del servidor configura `VERA_ADMS_PILOT_SERIAL` con el número de serie y `VERA_ADMS_PILOT_IP` con la IP pública fija de salida de la red del reloj. Habilita con `VERA_ADMS_ENABLED=true` y reconstruye la caché con `php artisan config:cache`. Para desactivar, cambia el valor a `false` y reconstruye la caché.

El piloto exige HTTPS y comprueba la dirección remota que ve PHP. El número de serie identifica al equipo, y la IP limita el acceso a la red de origen autorizada. Este piloto es para un reloj en una red de confianza con IP fija. Si el alojamiento presenta la IP de un proxy como dirección remota, deja el piloto desactivado hasta verificar la configuración del alojamiento. Una solicitud sin autorización recibe 404.

Una vez habilitado, configura el reloj con dominio `gotvera.com`, puerto 443, ADMS y HTTPS. Verifica primero que el firmware pueda usar el certificado del sitio. Luego valida una entrada y una salida en el historial de la empresa y comprueba que cada una aparezca una sola vez. MySQL permanece privado y el reloj inicia la conexión desde la sucursal.

## Importación local de checadas

Para guardar eventos en `time_events`, inicia el script como administrador con `-ImportAttendance` y MySQL local activo. Sin ese parámetro el receptor sigue en modo diagnóstico. En el modo de importación el equipo debe tener el número de serie registrado, su centro activo y el usuario vinculado con una relación laboral de ese centro que cubra la fecha del evento.

El estado ADMS es el tercer campo de cada fila. Se mapea `0` a entrada, `1` a salida, `4` a inicio de pausa y `5` a fin de pausa. Se interpreta la hora en la zona del centro y se registra con fuente `integration` y canal `adms`. La clave de deduplicación combina el equipo, ID de usuario en el reloj, hora y estado. Se confirma el lote al reloj después de guardarlo. Si el reloj reenvía eventos anteriores válidos, también pueden aparecer en el historial.

Verificación local del 12 de noviembre de 2026. MySQL registró cinco checadas distintas del reloj para `DEMO-DIA-001`, entre ellas una entrada a las 21:28:35 y una salida a las 20:40:47. Los cuatro estados se probaron también con datos simulados, incluidos los dos estados de pausa y el reenvío sin duplicados.

## Registro de equipos y vínculos en Vera Time

En **Configuración de empresa → Relojes de asistencia**, selecciona la empresa correspondiente, registra el número de serie que aparece en Información del dispositivo y elige un centro activo. En **Vincular trabajador**, selecciona el equipo y captura el ID del reloj (por ejemplo `1`) y el código del trabajador en Vera Time (por ejemplo `DEMO-DIA-001`).

La migración `2026_11_12_000000_create_attendance_clocks.php` agrega `attendance_clocks` y `attendance_clock_workers`. El número de serie se normaliza a mayúsculas y es único en Vera Time. El ID de usuario se guarda como texto y es único por reloj. La pantalla usa el permiso de configuración de la empresa. El registro queda disponible para una futura importación. El receptor de diagnóstico sigue funcionando por separado y aún se debe verificar cómo distinguir entradas de salidas antes de guardar eventos.

La migración quedó aplicada en `vera_time` local. El primer intento excedió el límite de longitud del nombre de índice en MySQL. Se corrigió el nombre y se recrearon únicamente las dos tablas nuevas tras confirmar que estaban vacías.

Esta prueba verifica el envío de una checada del reloj a Vera Time en el entorno local de XAMPP. Usa PHP de XAMPP. El script arranca un servidor temporal con acceso exclusivo a las rutas ADMS desde la IP del reloj. Apache y MySQL pueden permanecer apagados.

## Archivos

- `bootstrap/app.php` registra las rutas `/iclock/cdata` y `/iclock/getrequest` de Laravel.
- `app/Http/Controllers/Testing/AdmsProbeController.php` valida el entorno y el origen, responde al saludo y registra únicamente el conteo y las horas recientes de las filas `ATTLOG` válidas. Se usa la zona `America/Mexico_City` al interpretar la hora del reloj.
- `scripts/adms-local-router.php` limita el servidor temporal a las rutas ADMS y a la dirección de origen configurada.
- `scripts/adms-local.ps1` arranca PHP y, en la prueba con el reloj, agrega una regla de Windows para `192.168.100.82` hacia `192.168.100.81:18081`. Al terminar el plazo, cierra PHP y quita la regla.

## Prueba con el reloj

1. Verifica que la PC y el reloj sigan en `192.168.100.81` y `192.168.100.82`. Desde PowerShell como administrador, en la raíz de Vera Time, ejecuta `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\adms-local.ps1 -Minutes 5`.
2. Anota la configuración ADMS del reloj. Cambia temporalmente la dirección a `192.168.100.81`, el puerto a `18081` y apaga HTTPS. Mantén dominio y proxy apagados durante la prueba en la LAN.
3. Cuando aparezca una línea `inicio` nueva en `storage/logs/adms-local.jsonl`, realiza una checada con un usuario de prueba y anota la hora que muestra el reloj.
4. Busca una línea `ATTLOG` nueva con `filas` mayor que cero y una hora en `horas_recientes` que coincida con la checada. Las horas conservadas están a diez minutos o menos de la hora de la PC. El receptor responde HTTP 503 a las cargas para evitar confirmarlas como entregadas. Pueden aparecer reintentos. Solo se solicita asistencia, se rechazan las demás tablas y se guardan únicamente metadatos de diagnóstico. La prueba no inserta filas en `time_events`.
5. Restaura los valores anotados y deja que el receptor cierre al vencer el plazo. Si se termina el script a la fuerza, revisa y quita la regla de Windows `Vera Time ADMS local temporal` correspondiente a esta ejecución.

## Autoprueba

`-SelfTest` ejecuta el mismo flujo en `127.0.0.1:18082` sin regla de firewall. La evidencia se escribe en `storage/logs/adms-simulado.jsonl`. La verificación inicial comprobó saludo HTTP 200, una fila simulada con su hora y HTTP 503, rechazo HTTP 403 de `BIODATA` y HTTP 404 para `/`.

## Resultado en el equipo

El 12 de noviembre de 2026 a las 15:51:02 de Ciudad de México, Laravel registró el saludo del MB10-VL desde la red local. La validación de una checada real quedó pendiente porque el reloj no tenía un usuario de prueba disponible al iniciar la sesión.

En esa ejecución el receptor permaneció abierto más allá del plazo previsto. Se cambió el arranque para comprobar la hora límite cada segundo. Este ajuste se debe verificar en la siguiente ejecución.

En una ejecución posterior el reloj envió `ATTLOG` con una fila válida del 12 de noviembre de 2026 a las 20:38:38 (Ciudad de México). El archivo `storage/logs/adms-local.jsonl` muestra varias recepciones de la misma hora, coherentes con los reintentos por HTTP 503. La prueba confirma recepción en Laravel. Quedan pendientes la vinculación por dispositivo y trabajador, la deduplicación y el registro en `time_events`. Se detectaron dos procesos escuchando en el puerto de prueba al coincidir dos ejecuciones.
