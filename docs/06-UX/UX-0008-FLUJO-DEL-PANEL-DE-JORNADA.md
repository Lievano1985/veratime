# Atención de una jornada

El panel tiene cuatro pasos:

El resumen usa cuatro indicadores en una fila en pantallas amplias y dos por fila en móvil. Horario y marcajes se comparan en dos columnas, con etiquetas y horas alineadas; en móvil se apilan. Los estilos se compilan con Vite para que la distribución esté disponible en la instalación local.

1. Revisar: persona y fecha, horas programadas y calculadas, pausa calculada, horario asignado y marcajes válidos actuales. Los marcajes se resuelven con la Action existente para la relación laboral, empresa y fecha, incluyendo turnos que cruzan medianoche. Se aclara que una modificación requiere recalcular.
2. Corregir y actualizar si es necesario: enlace a Eventos filtrado por persona y fecha, seguido del recálculo individual sujeto a su permiso existente. El recálculo también actualiza su semana.
3. Atender cada pendiente: selección de un aviso y una decisión individual con comentario. Para pausas se distingue corregir registros de documentar que no hubo descanso. Una decisión no completa otros asuntos.
4. Consultar antecedentes: historial y detalle técnico plegados al final.

Se retira el formulario y el método público de cierre general del componente. La Action de dominio anterior se conserva por compatibilidad, sin acceso desde este panel. No se alteran cálculos ni reglas de autorización o pago.

Al guardar correctamente una revisión, el comentario se vacía y el selector vuelve a su primera opción válida. La decisión guardada sigue visible en el aviso. Los errores de validación conservan lo capturado para corregirlo.

Prueba de regresión: el panel presenta los pasos en orden; resolver la pausa conserva pendientes duración, autorización de extra y caso semanal, sin alterar minutos. Se mantienen las pruebas de alcance por empresa y permisos del panel y recálculo.
