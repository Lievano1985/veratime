# Clave personal de kiosco

En **Mi resumen → Mi clave de kiosco**, la persona trabajadora puede generar o renovar su NIP confirmando la contraseña de su cuenta. El código de acceso corresponde al número de empleado registrado por la empresa; no se puede editar desde esta opción.

El NIP tiene seis dígitos, se genera aleatoriamente y se muestra una sola vez. Se oculta al cerrar el panel o cambiar de pestaña. Se guarda únicamente su hash; no se incluye en registros ni en propiedades públicas persistentes del componente. Renovarlo invalida el NIP anterior para nuevas identificaciones. Los tokens de identificación ya emitidos por el kiosco conservan su vencimiento actual.

Solo se administra el trabajador activo vinculado a la cuenta en la empresa activa. Las credenciales bloqueadas o inactivas requieren revisión de RH. Las que requieren restablecimiento pueden renovarse. Se requieren cuenta, empresa y pertenencia activas. Un código ocupado por otro trabajador se rechaza sin modificarlo.

Se permiten cinco intentos de contraseña durante quince minutos y hasta cinco generaciones por hora por cuenta y empresa. Los kioscos conservan sus controles de autorización y centro. No cambia la contraseña de la cuenta ni requiere migraciones.

Pruebas: `tests/Feature/Personal/PersonalKioskCredentialTest.php`, ejecutadas con la conexión SQLite aislada del entorno de pruebas.
