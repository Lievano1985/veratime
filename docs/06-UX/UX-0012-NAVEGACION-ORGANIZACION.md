# Navegación de organización

El menú concentra los catálogos en dos entradas:

- **Organización**: Empresa, Centros y Áreas y departamentos.
- **Trabajadores**: Trabajadores y Asignación organizacional.

Cada pantalla incluye los botones de su grupo con el estilo del flujo de Jornadas. El botón activo aparece azul y utiliza `aria-current="page"`. La navegación usa las rutas existentes y `wire:navigate`; no cambia los formularios, datos, políticas ni acciones de dominio. El componente compartido `organization.navigation` filtra las opciones por los permisos de la empresa activa.

Responsables y supervisores y Mi alcance conservan sus entradas actuales.

Verificación manual: recorrer las tres opciones de Organización y las dos de Trabajadores, comprobar el botón activo, abrir los formularios actuales y revisar que los botones se acomoden en pantallas estrechas. Validar con un usuario de permisos limitados que solo aparezcan destinos autorizados.
