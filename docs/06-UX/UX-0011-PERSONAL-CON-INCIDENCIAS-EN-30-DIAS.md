# Personal con más incidencias en 30 días

La gráfica de tipos por semana se sustituye visualmente por un ranking de trabajadores. Cada barra representa el total de alertas de una persona, ordenado de mayor a menor. El tooltip muestra nombre, clave, total y cantidades por tipo. El mismo desglose está disponible como texto desplegable, también útil en móvil.

Las barras son verticales y delgadas, con pendientes en índigo y revisadas en celeste, apiladas desde cero. Los dos segmentos suman el total del trabajador; no se representan cantidades negativas. Si hay muchas personas, la gráfica permite desplazamiento horizontal. Los iconos de tarjetas del dashboard usan tonos Tailwind por categoría, con rosa para alertas de prioridad alta y antecedentes de riesgo, y variantes para modo oscuro.

La ventana incluye la fecha consultada y los 29 días previos, sin extenderse al futuro. Se usa la fecha de la jornada, no la fecha de recálculo. Incluye pendientes y revisadas; excluye cierres automáticos por recálculo y tipos retirados que duplicaban tareas. Cada aviso semanal vigente cuenta como un aviso, independientemente de cuántos días contenga su semana. No representa un índice de incumplimiento ni solo pendientes.

Se conserva el alcance de empresa, centro y permisos operativos del dashboard. Trabajadores sin alertas no generan barras. Se muestra el rango exacto de fechas y un estado vacío cuando corresponde. Los indicadores de cumplimiento semanal siguen usando su selector propio.

Se retira el bloque «Atención de jornadas» del dashboard por repetición, sin retirar las vistas de Jornadas, Incidencias o Prevención ni alterar sus cálculos.
