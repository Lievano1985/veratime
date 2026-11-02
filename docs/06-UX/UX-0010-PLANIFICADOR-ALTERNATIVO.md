# Planificador alternativo de horarios

Fecha: 2026-10-29. Estado: implementado para evaluación; pendiente de validación visual con el usuario.

## Objetivo y alcance aprobado

Extiende el caso de uso de asignación de horarios del MVP por petición explícita del usuario: preparar horarios habituales, rotaciones y semanas variables sin recorrer catálogo, modelos, aplicaciones y programación como cuatro pasos independientes. El bloque anterior se conserva para comparar ambos flujos antes de decidir su retiro.

Entrada: **Horarios → Planificador de horarios**, `/time/scheduling/planner`.

## Operación

Actualización: el calendario reutiliza las clases visuales del anterior (`cell-head`, `emp-*`, `shift-*`, `code-tag`). El ajuste limitado a `.vt-planner-calendar` evita que las reglas genéricas de tablas cambien su espaciado y mayúsculas. Conserva las selecciones y muestra conteos de la semana.

Ambas pantallas utilizan ahora los mismos componentes Blade: `scheduling.day-card`, `scheduling.worker-heading` y `scheduling.day-heading`. El calendario anterior también los utiliza en su vista móvil. La tarjeta comparte formato de código, descripción, rango de horas, colores y contenido centrado; no muestra la palabra Turno ni el origen Manual. La acción de clic se recibe desde cada pantalla: el planificador conserva la selección y el anterior abre el editor individual. Se conserva la marca de baja y la información avanzada del calendario anterior.

Excel `.xlsx` es la opción principal del importador: horarios actuales, listas de turnos y revisión antes de aplicar. CSV sigue disponible. Ver `UX-0011-EXCEL-PROGRAMACION-SEMANAL.md`.

- Elegir centro y semana; el calendario se abre directamente, priorizando un borrador existente y después la publicación vigente.
- El selector principal muestra número ISO, año ISO y rango lunes-domingo, con flechas para avanzar o retroceder. Al desplegarlo se busca por año y se elige una semana con su rango, sin usar un calendario de fechas.
- Una semana nueva puede empezar vacía o desde los perfiles habituales existentes.
- Seleccionar personas de una página o varias páginas, buscar por nombre/clave y filtrar por unidad vigente durante la semana.
- Asignar una semana al grupo, crear un horario semanal recurrente, construir una rotación por bloques de varios días o reutilizar cualquier perfil activo existente, incluidos flexible y guardia.
- En el patrón semanal, elegir varios días y aplicar un turno en una sola operación; los siete selectores permiten excepciones antes de guardar.
- Crear turnos sin salir del planificador mediante el mismo componente y panel lateral del catálogo actual, incluido código, estado, segmentos, pausas, turnos partidos, cruce de medianoche y vista previa. Se retiró el formulario simplificado independiente. El catálogo se monta en modo incrustado y notifica al planificador al guardar; un turno activo queda seleccionado sin perder la configuración del grupo.
- Seleccionar varias celdas y aplicar turno o descanso sin abrir un editor por cada día.
- Seleccionar días completos pulsando sus encabezados: incluye a todas las personas de los filtros, incluso en otras páginas, y excluye fechas fuera de su vigencia. Se pueden combinar días y celdas; volver a pulsar un día completamente seleccionado quita su selección. El cambio rápido reemplaza los días seleccionados del borrador al pulsar Aplicar; no modifica horarios habituales. Máximo 1,400 celdas por operación.
- Todo el encabezado de una persona es seleccionable, con cursor de mano y sin casillas visibles; el fondo azul indica selección. Al pulsar una fila con celdas seleccionadas se excluyen esas celdas, incluso si venían de seleccionar una columna, conservando al resto del grupo. Al pulsarla otra vez se seleccionan todos sus días vigentes. Filas, columnas y celdas comparten la misma selección; seleccionar una página incluye los días vigentes de sus personas. El botón para copiar una fila actúa de forma independiente.
- Los encabezados de columnas usan el mismo diseño y comportamiento que los de personas: área completa clicable, cursor de mano, título, subtítulo y fondo azul al seleccionar. También se activan con Enter o Espacio. Su selección funciona en el navegador y comprende todas las páginas de los filtros actuales.
- Al pie derecho del calendario, **Restablecer horarios** deja todos los días del borrador como pendientes y elimina sus segmentos. Confirma explícitamente que afecta toda la semana, sin limitarse a filtros o selección. Conserva el lote, los turnos y perfiles habituales; en una corrección conserva las filas para mantener cobertura y la publicación anterior no se modifica. Solo se permite en borrador y exige una nueva revisión antes de publicar.
- La confirmación de restablecimiento utiliza `flux:modal`, igual que las confirmaciones integradas de empresa: título, período afectado, alcance, Cancelar y acción de confirmación con indicador de proceso. No utiliza el diálogo nativo del navegador.
- Elegir una fila origen y copiar sus días y segmentos al grupo seleccionado. La copia conserva los horarios del origen para las mismas fechas y resuelve la unidad de cada destinatario.
- Importar CSV con el componente compartido existente y sus controles de validación.
- El componente CSV se monta en la fila principal de acciones del borrador: su botón abre directamente el panel lateral, sin tarjeta ni botón intermedio bajo el calendario. Cambiar los filtros actualiza el contexto del importador.
- Al consultar una publicación se muestran únicamente dos tarjetas: **Cambiar publicación** y **Clonar semana**. Se retiró del planificador el desplegable separado de preparación futura.
- Clonar de una a doce semanas consecutivas desde una semana publicada, incluida una corrección publicada vigente, eligiendo semana inicial y resultado: borradores o publicación directa. Se copia la publicación seleccionada, no se regenera desde los horarios habituales. Las semanas destino existentes generan conflicto y se conservan.
- La primera semana destino usa el mismo diseño de navegación semanal: número ISO, año y rango lunes-domingo, flechas y desplegable de búsqueda por año/semana. Cambiar ese selector no cambia la semana origen ni la cantidad de copias.
- La clonación múltiple es transaccional: si falla la creación o validación de cualquier semana, ninguna de las nuevas semanas queda creada o publicada. La publicación directa usa las mismas validaciones de cobertura, permisos, snapshots y conflictos que la publicación normal.
- Revisar la cobertura de todo el centro y confirmar la publicación; crear una corrección con motivo para cambiar una publicación.
- Eliminar un borrador con confirmación, usando la Action y policy existentes. Se eliminan sus días, segmentos e importaciones; se conservan turnos, modelos y asignaciones habituales. Si se elimina una corrección en borrador, el calendario vuelve a la publicación vigente anterior.

## Datos y compatibilidad

No agrega tablas ni migraciones. Usa `shift_templates`, `shift_template_segments`, `schedule_profiles`, sus reglas y asignaciones, `schedule_batches`, `daily_schedule_assignments` y `daily_schedule_segments`.

Los componentes anteriores conservan su funcionamiento y se habilitan al elegir el flujo anterior. Ambos flujos ven los mismos datos. Un horario recurrente nuevo crea un perfil compartido por el grupo seleccionado y asignaciones individuales, respetando la prioridad individual → unidad → centro → empresa del resolutor existente.

No genera semanas publicadas automáticamente. Las asignaciones recurrentes nuevas se reflejan al preparar semanas futuras; no regeneran borradores futuros que ya existían ni publicaciones previas.

Por defecto se completan días vacíos o pendientes y se conservan días definidos. El reemplazo de días del borrador y el reemplazo del horario habitual son decisiones independientes y explícitas. Un reemplazo habitual debe comenzar después de la fecha inicial de la asignación anterior, según el contrato de la Action existente. Si hay asignaciones futuras incompatibles, se bloquea toda la operación en lugar de borrar vigencias.

Una corrección solo admite cambios de esa semana; la configuración habitual se hace en una semana normal. Mientras se edita la corrección, la versión publicada anterior sigue vigente. La publicación usa los validadores, snapshots y hashes actuales.

## Arquitectura y permisos

`ApplyPlannerScheduleAction` orquesta perfiles, vigencias y borradores en una transacción. `CopyPlannerRowAction` copia una fila en una transacción. El componente Livewire orquesta navegación y acciones de dominio existentes.

Las consultas se limitan a la empresa activa y el centro autorizado; las mutaciones vuelven a autorizar el lote. Solo se muestran centros en los que el usuario puede operar el centro completo, igual que la operación semanal existente. Los permisos de cada rol se conservan; solo se muestran las entradas del modelo activo. Las selecciones y IDs enviados por el cliente se validan nuevamente al guardar.

## Verificación manual pendiente

Validación automatizada ejecutada: 15 pruebas del planificador (68 aserciones) y 144 pruebas de regresión del módulo existente (1,024 aserciones), sin fallos. Compilación Vite, compilación de vistas y formato PHP correctos. El entorno muestra un aviso previo de PHP 8.5 por `PDO::MYSQL_ATTR_SSL_CA` en `config/database.php` y un aviso de Node por `module.register()`; ambos procesos terminan con código 0.

No se ejecutaron migraciones ni se modificaron datos operativos para esta validación: las pruebas usan SQLite en memoria. La herramienta de computadora bloqueó la inspección de Chrome al no poder determinar su URL con certeza, por lo que no se considera validada la apariencia ni la interacción Alpine en navegador.

1. Como superadministrador, abrir Configuración de empresa → Operación y alternar el modelo. Confirmar que solo aparece el modelo activo en el menú y que el inactivo responde 403 incluso con una pestaña Livewire ya abierta. Como administrador de empresa, confirmar que el selector no aparece y no se puede invocar su acción.
2. Preparar una semana vacía de un centro de prueba; seleccionar varias personas.
3. Crear un turno, asignarlo de lunes a viernes y revisar descansos antes de aplicar.
4. Guardar un horario recurrente y preparar la semana siguiente; confirmar que aparece sin capturar reglas otra vez.
5. Crear una rotación por bloques y comprobar el Día 1 según su vigencia, incluyendo el cambio de semana.
6. Editar varias celdas y copiar una fila al grupo. Comprobar que los días definidos se conservan salvo reemplazo explícito.
7. Importar CSV y revisar pendientes de todo el centro, incluso con filtros activos.
8. Publicar únicamente datos de prueba; copiar a otra semana y crear una corrección. Verificar que el snapshot anterior se conserva.
9. Revisar pantallas estrechas, desplazamiento del calendario, paneles superpuestos al crear un turno desde la asignación y permisos de un operador con alcance completo y otro con alcance parcial.

## Pendientes antes de retirar el bloque anterior

Evaluar el recorrido con el usuario y medir tiempos con sus volúmenes reales. La creación completa de turnos ya reutiliza el catálogo actual dentro del planificador; administración de perfiles flexibles/guardias y detalle técnico del historial siguen disponibles en las pantallas actuales. No retirar ese bloque hasta cubrir y validar las operaciones que el usuario necesite en el nuevo flujo.

## Activación por empresa

En Configuración de empresa → Operación, «Forma de crear horarios» permite elegir Planificador de horarios o Modelos de Horarios. Solo un superadministrador activo puede ver y ejecutar este control. Las empresas sin selección explícita usan el nuevo planificador, incluidas las empresas nuevas.

La selección se guarda en `company_settings.metadata.scheduling_interface` y registra el último actor y fecha del cambio. No requiere migraciones. Guardar los parámetros operativos conserva esta selección y el resto de los metadatos. El cambio solo afecta a la empresa activa.

El menú muestra el flujo activo y el middleware protege las rutas del modelo inactivo, incluyendo las actualizaciones de sesiones Livewire abiertas antes del cambio. El catálogo de turnos y los datos de horarios, borradores, publicaciones e historial se conservan. Ambos flujos utilizan los mismos datos; cambiar el modelo no los transforma ni los elimina.
