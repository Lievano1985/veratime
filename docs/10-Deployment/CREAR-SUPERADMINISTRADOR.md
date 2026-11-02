# Crear un superadministrador desde cPanel o SSH

Entra a una terminal interactiva del servidor y ubícate en la carpeta del proyecto que contiene artisan. Usa la versión PHP configurada para la aplicación.

```bash
php artisan veratime:create-super-admin
```

Captura nombre y correo, y escribe dos veces la contraseña. La terminal no muestra la contraseña al escribir. Debe tener al menos 12 caracteres, mayúsculas, minúsculas y números. Al terminar solo se confirma el correo creado. No pases contraseñas como argumentos ni las escribas en código, .env o documentación. Si la terminal no permite entrada oculta, no se utiliza captura visible como alternativa.

El comando crea una cuenta activa con rol global super_admin y guarda únicamente el hash. No crea empresas ni datos demo. Un correo ocupado se rechaza y no se cambia su contraseña ni sus permisos. Los superadministradores existentes conservan sus credenciales. La contraseña no se recupera ni se muestra en el panel web.

El seeder principal carga solo catálogos. El antiguo SuperAdminSeeder únicamente indica que se utilice el comando. VERA_TIME_SUPER_ADMIN_PASSWORD ya no se utiliza; puede retirarse de configuraciones antiguas. Este cambio no incorpora autenticación de dos factores.
