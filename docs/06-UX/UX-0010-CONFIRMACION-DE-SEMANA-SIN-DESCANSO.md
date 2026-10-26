# Confirmación de semana sin descanso

El aviso semanal permanece como hecho detectado y requiere una confirmación expresa. El panel muestra las revisiones de descanso trabajado y domingo de la misma empresa, relación laboral y semana. No pide otro comentario.

El caso semanal no muestra el resumen, los marcajes, el recálculo ni el detalle técnico del día usado como referencia. Para semana sin descanso se muestran periodo, días trabajados con cálculo concluyente, días sin trabajo registrado, revisiones de respaldo y estado semanal. Los días sin cálculo concluyente se informan por separado y no se presumen descanso. El acceso a las jornadas de la semana permite consultar cada día. El panel diario conserva sus cuatro pasos y su detalle técnico.

La Action `ConfirmWeeklyRestReviewAction` permite confirmar cuando la semana terminó, tiene siete jornadas con cálculo activo y sin problemas de secuencia, existen revisiones diarias humanas de respaldo y no quedan avisos abiertos de descanso trabajado o domingo. Revalida las condiciones en el servidor y exige el permiso de resolución del aviso. Un aviso ya resuelto no se confirma de nuevo.

La confirmación guarda estado cerrado, actor, fecha, explicación del alcance y copia de los identificadores y decisiones diarias bajo `metadata.resolution.daily_reviews`. Queda fuera de pendientes y visible como «Revisado: semana sin descanso». No acredita descanso disfrutado, cumplimiento ni pago. No modifica minutos. Los cambios posteriores de resultado siguen sujetos al mecanismo de reapertura del evaluador.

La semana de Diego del 12 al 18 de octubre se verificó solo en lectura: tres revisiones diarias de respaldo, ninguna pendiente de descanso/domingo, confirmación disponible. No se confirmó en nombre del usuario.

## Fecha de nacimiento opcional

Las Actions de crear y actualizar trabajadores normalizan fechas de nacimiento vacías a `NULL`, evitando el error 1292 de MariaDB al recibir `''`. Las fechas capturadas se conservan. Se verificó el caso contra MariaDB dentro de una transacción revertida, con comprobación de que el trabajador original quedó sin cambios.
