# Editar el acceso del administrador de empresa

El superadministrador puede abrir Empresas → Editar → Acceso del administrador. Se modifica la identidad existente: nombre, correo y contraseña opcional. Si hay varios administradores, debe elegir cuál editar. Una contraseña vacía conserva la actual; no se muestra ni recupera el hash. No se crean cuentas nuevas ni se cambian roles.

Solo el superadministrador puede ejecutar UpdateCompanyAdministratorAction. La cuenta objetivo debe pertenecer a la empresa seleccionada con rol admin_empresa y no tener rol global. El servidor vuelve a comprobar estas condiciones al guardar. Se normaliza el correo y se rechazan direcciones ya ocupadas.

Al cambiar correo o contraseña se eliminan sesiones, tokens de acceso y solicitudes previas de recuperación de esa cuenta; el cambio de correo retira su verificación anterior. La identidad es global: si administra otras empresas, deberá utilizar los mismos datos nuevos en ellas. No se envía una contraseña por correo automáticamente.

Pruebas: cambio de correo por el panel conservando contraseña y pertenencia, cambio de contraseña y revocación de tokens, correo duplicado sin cambios parciales, rechazo de actor no autorizado y objetivo ajeno a la empresa. Se ejecutan en SQLite en memoria, protegidas por tests/bootstrap.php y TestCase.
