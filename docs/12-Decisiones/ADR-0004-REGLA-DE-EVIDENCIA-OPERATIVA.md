# ADR-0004 - Regla de evidencia operativa

## Estado

Aceptado.

## Contexto

Vera Time debe conservar trazabilidad del horario y del cumplimiento, pero no debe ensuciar la operacion diaria acumulando catalogos, vigencias o asignaciones intermedias capturadas por error.

La decision de producto es que lo importante para auditoria y cumplimiento es el resultado operativo:

- el horario diario publicado que tuvo una persona en una semana, quincena o mes;
- los eventos de asistencia registrados;
- las correcciones versionadas del horario publicado;
- las futuras jornadas calculadas, cierres, conformidad, reportes y evidencias.

Los catalogos, relaciones, areas y asignaciones son medios para generar ese resultado. No deben tratarse con la misma rigidez si todavia no afectaron evidencia protegida.

## Decision

La evidencia protegida de Vera Time es el resultado operativo publicado o registrado, no cada paso intermedio usado para construirlo.

### Evidencia protegida

Se considera evidencia protegida:

- `schedule_batches` publicados, reemplazados o cancelados.
- `daily_schedule_assignments` y `daily_schedule_segments` de batches publicados.
- snapshots canonicos, hashes, autor y fecha de publicacion.
- correcciones versionadas de programacion diaria publicada.
- `time_events` registrados por web, kiosco o captura manual.
- anulaciones logicas de `time_events`.
- futuros `work_days`, calculos, alertas, incidencias, cierres, conformidad, reportes y expedientes.

Esta informacion no se borra fisicamente ni se sobrescribe en operacion ordinaria.

### Datos intermedios corregibles

Se consideran datos intermedios:

- empresas, centros, unidades organizacionales y otros catalogos administrativos;
- trabajadores y relaciones laborales antes de generar evidencia protegida;
- asignaciones organizacionales;
- plantillas de turno;
- perfiles de horario;
- asignaciones de perfiles;
- descansos internos de empresa;
- cualquier configuracion usada solo para generar borradores o futuras publicaciones.

Estos datos pueden corregirse, finalizarse, inactivarse o eliminarse cuando no exista uso en evidencia protegida.

Cuando ya exista evidencia protegida, la correccion del dato intermedio no debe reescribir el horario publicado ni la asistencia. Debe aplicar hacia adelante o exigir una correccion versionada del resultado.

## Reglas operativas

1. Un horario publicado nunca se recalcula automaticamente por cambios posteriores en catalogos, perfiles, unidades o relaciones laborales.
2. Si se necesita cambiar lo que realmente cuenta para una fecha ya publicada, se usa correccion versionada de programacion diaria.
3. Si una relacion laboral, unidad o asignacion fue capturada mal antes de generar evidencia protegida, debe poder corregirse con motivo y actor.
4. Si el dato intermedio ya participo en horario publicado, asistencia, cierre, reporte o evidencia, la UI debe explicar que el resultado historico no se modifica desde ese catalogo.
5. `work_days` debe generarse desde horarios publicados aunque no existan eventos.
6. `work_days` debe identificar eventos validos sin horario publicado como jornada no programada.
7. Los eventos de tiempo no se eliminan; se anulan logicamente con motivo, actor y fecha.
8. La UI debe hablar en terminos de efecto operativo: "afecta publicaciones futuras", "no cambia horarios ya publicados" o "requiere correccion de horario".

## Consecuencias

- El modelo queda preparado para limpiar errores de captura sin perder confianza en la evidencia.
- Las pantallas de trabajadores, asignacion organizacional y asignacion de perfiles deben separar correccion administrativa de correccion de horario publicado.
- Las validaciones futuras no deben bloquear cambios intermedios por simple historial si ese historial no es evidencia protegida.
- Los siguientes bloques deben implementar permisos, mensajes y pruebas siguiendo esta regla.

## Casos de prueba obligatorios

1. Corregir relacion laboral sin horario publicado ni asistencia debe permitir ajustar el dato con motivo.
2. Corregir relacion laboral con horario publicado debe conservar intacto el horario publicado.
3. Cambiar asignacion organizacional con perfil de empresa debe aclarar que la unidad no define ese perfil.
4. Cambiar asignacion organizacional que si resuelve un perfil por unidad debe afectar solo borradores o publicaciones futuras.
5. Cambiar asignacion de perfil despues de publicar debe conservar la publicacion y requerir correccion versionada si se quiere modificar el resultado.
6. Anular `time_events` debe conservar el evento original y excluirlo de resoluciones futuras.
7. Crear `work_days` debe tomar horarios publicados aunque no existan eventos.
8. Crear `work_days` debe detectar eventos validos sin horario como jornada no programada.
