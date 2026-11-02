# Alta de trabajador con cuenta vinculada

Desde el 1 de noviembre de 2026, `CreateWorkerAction` crea el trabajador, su cuenta con rol `trabajador`, la pertenencia a la empresa y el vínculo personal en una transacción. Aplica a web, API, Excel y generación de demos que utilizan esta acción. No modifica trabajadores existentes ni sus cuentas al editar.

El correo es obligatorio, válido y no puede pertenecer ya a una cuenta. Será el acceso del trabajador. La contraseña inicial es opcional: si no se captura, se genera una aleatoria. El alta manual muestra las credenciales en la sesión y permite ocultarlas. Las contraseñas solo se guardan como hash; no se envían por correo automáticamente. No se generan identificadores ni correos de sustitución para altas reales.

Si el correo ya tiene cuenta, el alta se rechaza sin crear registros parciales ni apropiarse de una identidad existente. Se conserva la vinculación explícita desde Usuarios para cuentas existentes.

La plantilla Excel tiene seis columnas, incluyendo Correo electrónico obligatorio. La vista previa rechaza correos faltantes, inválidos, ya registrados o repetidos dentro del archivo. Las plantillas antiguas deben reemplazarse por la nueva descarga. Sus contraseñas se establecen desde Usuarios antes de entregarlas. En API el correo es obligatorio y puede enviarse `account_password` (mínimo 8 caracteres); la respuesta de creación agrega `meta.account_login`, nunca devuelve contraseñas. Si se omite contraseña en API, se establece posteriormente desde Usuarios. Los escenarios demo utilizan direcciones de prueba únicas bajo example.test y no envían mensajes.

La cuenta no otorga permisos administrativos ni autoriza dispositivos automáticamente. Se conservan las políticas de ubicación, autorización de teléfono y controles actuales de marcaje móvil. Los trabajadores inactivos nacen con cuenta y pertenencia inactivas. La eliminación de trabajadores sin uso elimina el vínculo y desactiva su pertenencia con rol trabajador, conservando la identidad de usuario; los dispositivos vinculados bloquean la eliminación.

Validar: alta manual con y sin correo, hash y rol, duplicado entre empresas sin apropiación, importación, API, eliminación sin uso, generación y reinicio de demos. No requiere migraciones adicionales.
