# Diseño compartido de paneles laterales

Actualización: 26/10/2026.

Los 35 paneles laterales de 20 vistas utilizan el mismo patrón visual aprobado en Trabajadores: fondo suave, tarjetas redondeadas, encabezados numerados y separación de acciones. La navegación principal y los diálogos centrados (como el mapa) mantienen su diseño.

## Componentes

- `x-side-panel`: marco, encabezado, cierre, animación, ancho y contenedor desplazable. El ancho predeterminado es `max-w-xl`; los anchos especiales de calendarios, importación y editores se conservan.
- `x-ui.panel-section`: tarjeta completa con título, descripción opcional y contenido.
- `x-ui.panel-heading`: encabezado para tarjetas existentes, con variantes azul, índigo y esmeralda.
- La numeración se reinicia por panel y utiliza contadores CSS. Las secciones condicionales se numeran según aparecen. El número es decorativo; los lectores de pantalla reciben el título.

No agregar numeración manual al texto del título ni duplicar el encabezado del componente. Mantener los avisos y confirmaciones de riesgo con sus colores de estado.

## Pantallas incluidas

Trabajadores, usuarios (alta/edición/contraseña/vínculo), centros, empresas, cuentas cliente, unidades y asignaciones organizacionales, alcances operativos, horarios legacy y asignaciones, descansos, plantillas de turno, modelos de horario y su aplicación, programación semanal y correcciones, importación CSV, marcajes manuales, incidencias, periodos de asistencia y atención/recálculo de jornadas.

## Protección funcional y revisión

Se conservan campos, enlaces, acciones Livewire/Alpine, tipos de entrada, validaciones, confirmaciones, permisos y formularios existentes. Las agrupaciones visuales no cruzan condiciones Blade ni incluyen formularios anidados. El cierre por botón, fondo y Escape mantiene su implementación.

Se creó una copia de las vistas/componentes/CSS antes del rediseño en el workspace de la tarea (`work/before-panel-design`), que incluye los cambios previos del trabajador. La comparación de atributos funcionales contra esta copia confirmó que permanecen iguales.

La compilación de Blade y los assets se verifican junto a las pruebas funcionales existentes de los módulos afectados. La revisión visual del componente compartido se realiza con una muestra local sin datos reales; no equivale a una sesión autenticada de cada pantalla.

Comprobación manual: abrir paneles cortos y extensos, cambiar opciones condicionales, provocar un error de validación, comprobar scroll, cierre, cancelación y guardado; revisar especialmente publicación/corrección de horarios, importación CSV y seguimiento de jornadas.

## Resultado de validación

- 336 pruebas funcionales correctas, 1,571 aserciones, en los 20 archivos de prueba seleccionados para los módulos afectados.
- Compilación de todas las vistas Blade y `npm run build` correctos.
- Pint y revisión de espacios del diff correctos.
- Comparación contra la copia previa: 35 paneles, sin diferencias en enlaces, acciones, bindings ni tipos de entrada.
- Agrupaciones nuevas con etiquetas y condiciones Blade balanceadas.
- Muestra visual local revisada con los componentes y CSS reales, sin iniciar sesión ni modificar datos operativos.

Este rediseño no agrega migraciones ni modifica lógica de dominio o datos.
