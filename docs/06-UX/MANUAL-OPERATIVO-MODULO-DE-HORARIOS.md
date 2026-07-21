---
title: Manual operativo del modulo de horarios
project: Vera Time
status: Draft
updated: 2026-07-20
---

# Manual operativo del modulo de horarios

## 1. Proposito

El modulo de horarios de Vera Time permite definir la programacion esperada antes del registro real de jornada. Sirve para preparar, revisar, publicar y conservar evidencia de lo que debia trabajarse en cada fecha.

Puntos clave:

- Programacion no es tiempo trabajado.
- Perfil de horario no es programacion publicada.
- Una guardia bajo llamada define disponibilidad; no equivale automaticamente a trabajo efectivo.
- Publicar congela evidencia mediante snapshot y hash SHA-256.
- Una correccion genera una nueva version; no modifica la version anterior.
- Una importacion CSV no publica automaticamente.
- El modulo aun no calcula `work_days`, horas legales, alertas, incidencias, cierres ni reportes.

## 2. Usuarios y permisos

| Rol | Puede consultar | Puede crear/editar | Puede publicar | No puede hacer |
|---|---|---|---|---|
| `owner` | Todo lo de su empresa activa | Empresas, centros, organizacion, turnos, perfiles, lotes, programacion diaria, CSV y correcciones | Programacion diaria y correcciones | Ver datos de otra empresa |
| `admin` | Todo lo de su empresa activa | Igual que owner para operacion empresarial | Programacion diaria y correcciones | Ver datos de otra empresa |
| `rh` | Todo lo operativo de su empresa activa | Igual que admin para el modulo de horarios | Programacion diaria y correcciones | Ver datos de otra empresa |
| `supervisor` | Solo dentro de alcance operativo vigente, cuando la policy lo permite | No administra globalmente turnos, perfiles, lotes ni CSV | No publica | No obtiene permisos solo por tener el rol; requiere alcance explicito |

Los alcances de supervisor se definen por centro completo o por unidad organizacional. El rol por si solo no concede acceso operativo global.

## 3. Glosario

| Termino | Significado operativo |
|---|---|
| Empresa | Tenant principal. Toda informacion operativa se separa por `company_id`. |
| Centro | Lugar o sede de trabajo. Los lotes diarios se crean por centro. |
| Unidad organizacional | Departamento, area o equipo dentro de un centro. La jerarquia visible MVP es `department -> area -> team`. |
| Relacion laboral | Vinculo vigente entre trabajador, empresa y centro. La programacion diaria se resuelve por relacion laboral. |
| Alcance operativo | Permiso explicito para que un supervisor consulte o administre segun centro o unidad. |
| Plantilla de turno | Catalogo reutilizable de un dia tipo. Contiene segmentos de trabajo y descansos. |
| Segmento | Bloque dentro de una plantilla o programacion diaria: trabajo o descanso. |
| Perfil de horario | Regla habitual que puede generar borradores de programacion diaria. |
| Asignacion de perfil | Vigencia que indica a quien aplica un perfil: empresa, centro, unidad o relacion laboral. |
| Patron semanal | Perfil que usa lunes a domingo, se reinicia cada semana. |
| Ciclo repetitivo | Perfil por dia 1, dia 2, dia 3, etc.; `effective_from` define el dia 1. |
| Calendario | Perfil que requiere definicion por fecha; puede quedar pendiente hasta captura diaria o CSV. |
| Flexible | Perfil con minutos requeridos y ventana opcional, sin hora fija exacta. |
| Guardia bajo llamada | Perfil de disponibilidad; el trabajo activado real esta pendiente fuera del modulo actual. |
| Lote de programacion | `schedule_batch` de una empresa, centro, periodo y version. |
| Programacion diaria | Dia esperado para una relacion laboral en una fecha. |
| Dia pendiente | Dia `unassigned`; bloquea publicacion hasta resolverse. |
| Borrador | Estado editable de un lote. |
| Publicado | Estado inmutable y operativo. |
| Sustituido | Version publicada anterior reemplazada por una correccion. |
| Version | Consecutivo por centro y periodo. |
| Snapshot | JSON canonico congelado al publicar. |
| Hash SHA-256 | Huella de integridad del snapshot publicado. |
| Correccion | Nueva version borrador basada en una publicacion previa. |
| Importacion CSV | Carga de archivo version 1 para modificar un lote borrador. |
| `preserve_existing` | Politica CSV que conserva dias existentes y omite esas filas. |
| `replace_existing` | Politica CSV que reemplaza dias existentes del borrador. |

## 4. Mapa de navegacion real

| Grupo | Pantalla | Ruta |
|---|---|---|
| Organizacion | Centros | `/centers` |
| Organizacion | Trabajadores | `/workers` |
| Organizacion | Areas y departamentos | `/organization/units` |
| Organizacion | Asignaciones organizacionales | `/organization/assignments` |
| Organizacion | Responsables y supervisores | `/organization/scopes` |
| Organizacion | Mi alcance | `/organization/my-scope` |
| Horarios | Catalogo de turnos | `/scheduling/shifts` |
| Horarios | Perfiles de horario | `/scheduling/profiles` |
| Horarios | Asignaciones de perfiles | `/scheduling/profile-assignments` |
| Horarios | Programacion diaria | `/scheduling/daily` |
| Horarios | Plantilla CSV programacion diaria | `/scheduling/daily/csv/template` |
| Horarios | Errores CSV de importacion | `/scheduling/daily/imports/{importBatch}/errors` |
| Horarios | Descansos obligatorios | `/mandatory-rest-days` |
| Legacy interno | Horarios legacy | `/schedules` |
| Legacy interno | Asignacion de horarios legacy | `/schedule-assignments` |

Las rutas legacy siguen existiendo, pero no deben confundirse con el nuevo modelo WFM. No hay doble escritura ni fallback entre legacy y programacion diaria publicada.

## 5. Orden recomendado de configuracion

1. Crear o confirmar centro.
2. Crear unidades organizacionales si aplican.
3. Registrar trabajadores y relaciones laborales.
4. Asignar unidad principal vigente.
5. Crear plantillas de turno.
6. Crear perfiles de horario.
7. Asignar perfiles por empresa, centro, unidad o relacion laboral.
8. Crear lote de programacion diaria.
9. Generar desde perfiles.
10. Revisar pendientes.
11. Editar individual o masivamente.
12. Validar antes de publicar.
13. Publicar.
14. Consultar snapshot e integridad.
15. Crear correccion cuando corresponda.
16. Importar CSV cuando corresponda sobre un lote borrador.

## 6. Plantillas de turno

Una plantilla de turno describe una expectativa de un dia concreto. Pertenece a una empresa, no a un centro, y no guarda timezone. Las horas son horas locales de reloj. Al copiarse a programacion diaria, sus segmentos quedan congelados con la zona horaria del centro.

Segmentos:

- `work`: trabajo, siempre con horario fijo.
- `break`: descanso, fijo o por duracion.
- `start_day_offset` y `end_day_offset`: solo 0 o 1 para indicar mismo dia o dia siguiente.

Metricas visibles:

- Trabajo programado bruto: suma de segmentos `work`.
- Descanso fijo pagado/no pagado: pausas con hora de inicio y fin.
- Descanso por duracion pagado/no pagado: pausas expresadas en minutos.
- Trabajo efectivo programado: trabajo bruto menos descansos por duracion no pagados.
- Duracion total: desde el primer inicio fijo hasta el ultimo fin fijo.

Ejemplos:

| Caso | Segmentos | Resultado esperado |
|---|---|---|
| 08:00-16:00 | Trabajo 08:00-16:00 | 8 h de trabajo bruto |
| 22:00-06:00 | Trabajo 22:00-06:00 +1 dia | Cruza medianoche |
| Dividido | Trabajo 08:00-13:00, descanso 13:00-15:00, trabajo 15:00-18:00 | 8 h trabajo bruto, 10 h duracion total |
| Pausa sin pago por duracion | Descanso 30 min no pagado | Reduce trabajo efectivo programado |
| Pausa pagada por duracion | Descanso 30 min pagado | No reduce trabajo efectivo programado |

El modulo no evalua limites legales de jornada en plantillas; eso queda para motor legal futuro.

## 7. Perfiles de horario

### Patron semanal

Usa reglas de lunes a domingo. Se reinicia semanalmente. Ejemplo: oficina de lunes a viernes con descanso sabado y domingo.

### Ciclo repetitivo

Usa dias consecutivos: dia 1, dia 2, dia 3. No depende del nombre del dia de semana. La fecha `effective_from` de la asignacion marca el inicio del ciclo. Ejemplo: 2 dias de dia, 2 de tarde, 2 de noche y 2 de descanso.

### Calendario

No genera turno automaticamente por regla semanal. Crea dias pendientes o requiere definicion por fecha desde programacion diaria o CSV.

### Flexible

Define minutos requeridos y ventana opcional. No fija una hora exacta de entrada y no crea segmentos de turno.

### Guardia bajo llamada

Define disponibilidad y `max_work_minutes` programado. La disponibilidad no cuenta automaticamente como tiempo trabajado. Las activaciones reales no existen todavia.

## 8. Jerarquia de asignacion de perfiles

El resolutor usa esta prioridad:

1. Relacion laboral.
2. Unidad organizacional primaria vigente.
3. Centro.
4. Empresa.

La asignacion mas especifica manda. Las vigencias determinan si una asignacion aplica en la fecha consultada. Si no hay perfil, la generacion diaria crea un dia pendiente con motivo controlado. La unidad principal se congela en la programacion diaria para conservar contexto historico.

## 9. Programacion diaria

Un lote de programacion diaria pertenece a empresa, centro, periodo y version. Sus estados son:

- `draft`: editable.
- `published`: publicado e inmutable.
- `superseded`: sustituido por una correccion.
- `cancelled`: cancelado.

Acciones reales en `/scheduling/daily`:

- Crear lote vacio.
- Crear y generar desde perfiles.
- Generar faltantes (`missing_only`).
- Actualizar desde perfiles (`refresh_profile_generated`).
- Editar dia individual.
- Aplicar cambio masivo basico.
- Revisar antes de publicar.
- Publicar.
- Verificar integridad.
- Crear correccion.
- Comparar versiones.
- Ver historial de versiones.
- Importar CSV sobre draft.

Tipos de dia:

| Tipo visible | Valor interno | Campos principales |
|---|---|---|
| Turno | `shift` | Plantilla activa y segmentos copiados |
| Descanso | `rest` | Fecha, motivo/fuente |
| Flexible | `flexible` | Minutos requeridos, ventana opcional |
| Guardia | `on_call` | Disponibilidad, offsets, maximo al activarse |
| Pendiente | `unassigned` | Motivo; bloquea publicacion |

## 10. Fuentes de programacion

| Fuente visible | Valor interno | Estado operativo |
|---|---|---|
| Perfil | `profile` | Operativo desde generacion F2/F3B |
| Manual | `manual` | Operativo desde editor individual y masivo |
| Archivo | `csv` | Operativo desde F5A/F5B |
| Integracion | `api` | Preparado internamente como valor, sin API WFM operativa |
| Sistema | `system` | Operativo para pendientes o reglas internas generadas |

## 11. Publicacion

La publicacion exige revision previa y validacion de cobertura. Un lote no se publica si tiene dias faltantes, pendientes, datos incompatibles o conflictos con otra publicacion.

Al publicar:

- la operacion es atomica;
- se guarda `published_by` y `published_at`;
- se persiste `snapshot_canonical_json`;
- se calcula `snapshot_sha256`;
- el lote queda solo lectura;
- la verificacion de integridad compara JSON persistido contra el hash.

La publicacion no calcula tiempo trabajado y no genera `work_days`.

## 12. Correcciones versionadas

Flujo:

```text
v1 publicada
-> crear correccion
-> v2 borrador
-> editar
-> comparar
-> publicar
-> v1 sustituida
-> v2 publicada
```

Reglas:

- La version anterior no se modifica.
- El motivo general es obligatorio.
- La correccion debe tener al menos un cambio funcional.
- No hay ramas paralelas.
- Mientras v2 esta en borrador, v1 sigue vigente.
- Los hashes de versiones anteriores se conservan.
- Las versiones anteriores permanecen consultables.

## 13. Importacion CSV

La importacion CSV se usa en `/scheduling/daily` sobre un lote `draft`.

Flujo:

1. Descargar plantilla.
2. Llenar CSV version 1.
3. Seleccionar politica: `preserve_existing` o `replace_existing`.
4. Capturar motivo general.
5. Cargar y validar.
6. Revisar preview paginado.
7. Descargar errores si existen.
8. Aplicar con confirmacion.
9. Cancelar si no se desea aplicar.

Encabezados exactos, en este orden:

```text
clave_empleado,fecha,tipo_dia,codigo_turno,minutos_requeridos,inicio_ventana,fin_ventana,offset_inicio_ventana,offset_fin_ventana,inicio_disponibilidad,fin_disponibilidad,offset_inicio_disponibilidad,offset_fin_disponibilidad,maximo_minutos_trabajo,motivo
```

Tipos aceptados en CSV:

- `turno`
- `descanso`
- `flexible`
- `guardia`
- `pendiente`

Ejemplos con 15 columnas:

```csv
clave_empleado,fecha,tipo_dia,codigo_turno,minutos_requeridos,inicio_ventana,fin_ventana,offset_inicio_ventana,offset_fin_ventana,inicio_disponibilidad,fin_disponibilidad,offset_inicio_disponibilidad,offset_fin_disponibilidad,maximo_minutos_trabajo,motivo
STR-001,2026-08-03,turno,OPEN-08-16,,,,,,,,,,,Turno de apertura
STR-002,2026-08-03,descanso,,,,,,,,,,,,Descanso programado
STR-003,2026-08-03,flexible,,420,09:00,18:00,0,0,,,,,,Flexible del dia
STR-004,2026-08-03,guardia,,,,,,,08:00,20:00,0,0,240,Guardia bajo llamada
STR-005,2026-08-03,pendiente,,,,,,,,,,,,Pendiente de definicion
```

Reglas importantes:

- CSV no publica automaticamente.
- La aplicacion es transaccional: todo o nada.
- Las filas invalidas bloquean la aplicacion.
- La vista previa puede quedar obsoleta; si cambia el calendario despues de validar, se debe revalidar.
- El reporte de errores protege valores que empiezan con `=`, `+`, `-` o `@`.
- Una correccion en borrador puede recibir CSV sin modificar la version publicada anterior.

## 14. Errores comunes

| Error | Causa probable | Donde revisar | Como resolver | Que no debe hacerse |
|---|---|---|---|---|
| No existe relacion laboral para la fecha | Trabajador sin relacion vigente en ese centro | Trabajadores y relaciones laborales | Crear o corregir vigencia | Forzar `company_id` o editar DB |
| Relacion en otro centro | El lote es de un centro distinto | Centro del lote y relacion laboral | Usar el lote correcto | Mezclar trabajadores de centros distintos |
| Fecha fuera del periodo | CSV o edicion usa fecha externa | Periodo del lote | Ajustar fecha o crear otro lote | Ampliar lote publicado |
| Perfil no asignado | No hay perfil efectivo | Asignaciones de perfiles | Asignar perfil vigente | Publicar pendiente |
| Dia pendiente | `unassigned` por calendario o falta de perfil | Calendario diario | Definir turno/descanso/flexible/guardia | Ignorar pendiente |
| Turno inactivo | Plantilla no disponible | Catalogo de turnos | Reactivar o usar otra plantilla | Editar publicado |
| Calendario requiere definicion | Perfil calendar no autogenera turno | Programacion diaria o CSV | Capturar por fecha | Asumir horario default |
| Correccion sin cambios | La comparacion no detecta cambios | Comparar con version anterior | Hacer cambio real o cancelar | Publicar solo por cambiar motivo |
| Importacion duplicada | Mismo trabajador/fecha repetido en CSV | Preview de importacion | Dejar una sola fila | Aplicar parcialmente |
| Encabezados incorrectos | CSV no respeta schema v1 | Plantilla descargada | Descargar plantilla nueva | Renombrar columnas al azar |
| Preview desactualizado | Cambio manual despues de validar | Revalidar importacion | Validar de nuevo | Forzar aplicacion |
| Lote publicado no editable | Estado `published` | Listado de lotes | Crear correccion | Reabrir publicado |
| Supervisor sin edicion | No tiene rol manager ni permiso | Alcances y roles | Usar owner/admin/rh | Dar acceso global por rol |

## 15. Lo que no hace todavia

- `work_days`.
- Registro real de entrada/salida dentro del modulo de horarios.
- Activacion real de guardias.
- Motor legal.
- Alertas.
- Incidencias laborales.
- Cierres.
- Conformidad digital.
- Reportes.
- API WFM.
- XLSX.
- Optimizacion automatica de turnos.

Estos pendientes no son defectos del modulo actual; pertenecen a fases posteriores.
