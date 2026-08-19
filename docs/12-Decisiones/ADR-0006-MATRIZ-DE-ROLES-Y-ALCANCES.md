# ADR-0006 - Matriz de roles y alcances empresariales

## Estado

Aceptado.

## Contexto

Vera Time debe operar en empresas pequeñas y tambien en empresas grandes con muchos centros, areas, departamentos, supervisores y equipos de RH.

El modelo anterior trataba `owner`, `admin` y `rh` como roles amplios con alcance completo de empresa. Ese enfoque funciona para una empresa chica, pero se vuelve insuficiente cuando una sola persona de RH no puede revisar toda la operacion.

## Decision

Para el MVP, `owner` deja de ser un rol operativo diferenciado. Si un usuario administra toda la empresa, su rol canonico sera `admin_empresa`.

La autorizacion se definira con dos dimensiones:

1. Rol del usuario.
2. Alcance operativo explicito.

Tener un rol operativo no concede por si solo acceso a todos los centros o unidades, salvo en roles definidos como globales de empresa.

`super_admin` queda fuera de la matriz operativa empresarial. Sus permisos pertenecen a la capa de plataforma y no dependen de `company_user`, empresa activa ni alcance operativo.

## Roles canonicos

### `super_admin`

Administrador de plataforma.

- Opera fuera del contexto normal de empresa.
- Administra catalogos globales cuando aplique.
- No representa a un usuario operativo de una empresa cliente.
- No necesita pertenecer a una empresa para administrar la plataforma.
- Puede ver el listado completo de empresas registradas.
- Puede crear nuevas empresas.
- Puede modificar datos administrativos de cualquier empresa cuando sea necesario.
- Puede crear usuarios administradores empresariales iniciales.
- No debe usarse para operar jornadas, horarios, incidencias o periodos como si fuera personal interno de la empresa.

### `admin_empresa`

Administrador general de la empresa.

- Control total dentro de la empresa.
- Administra configuracion de empresa.
- Administra usuarios y roles empresariales.
- Administra centros, unidades, trabajadores, horarios, programacion, incidencias, jornadas, periodos y exportaciones.

### `rh_admin`

Responsable general de RH.

- Administra operacion de asistencia y tiempo.
- Puede crear o administrar usuarios `rh_operativo` y `supervisor`.
- Puede asignar alcances operativos a RH operativo y supervisores.
- Puede operar jornadas, incidencias, ausencias, periodos y exportaciones.
- No modifica configuracion global critica de la empresa salvo decision explicita posterior.

### `rh_operativo`

Usuario de RH con alcance limitado.

- Opera solo centros completos asignados.
- No recibe alcance por unidad organizacional.
- Puede consultar y operar trabajadores, jornadas, incidencias, ausencias y programacion dentro de sus centros asignados.
- Puede administrar el catalogo de turnos de la empresa.
- Puede crear modelos de horario, pero la asignacion de modelos queda limitada a sus centros asignados y a las unidades o relaciones laborales dentro de esos centros.
- Puede preparar, editar y publicar programacion semanal cuando su alcance cubre el centro completo del lote.
- No administra configuracion global de empresa.
- No crea administradores generales.

### `supervisor`

Responsable operativo de consulta.

- Solo consulta dentro de centros o unidades asignadas.
- Puede ver trabajadores bajo su alcance.
- Puede consultar programacion semanal actual, pasada o futura solo cuando ya este publicada.
- Puede consultar jornadas de trabajadores bajo su alcance.
- No consulta borradores de programacion.
- No obtiene permisos solo por tener el rol.
- No administra datos maestros.
- No crea, edita, publica ni elimina programacion.
- No dictamina de forma final en el MVP.
- Puede participar en flujos futuros de preaprobacion o comentarios, sin sustituir a RH.

### `trabajador`

Persona trabajadora.

- Portal propio futuro.
- Consulta y operacion solo de su propia informacion.
- No participa en administracion empresarial.

## Alcance operativo

Los alcances se asignan explicitamente mediante registros por:

- centro completo;
- una o varias unidades organizacionales.

Una empresa sin unidades organizacionales puede operar por centro.

Un usuario puede tener varios alcances vigentes.

Reglas por rol:

- `rh_operativo` solo puede recibir alcances por centro completo.
- `supervisor` puede recibir alcance por centro completo o por una o varias unidades organizacionales.
- Un alcance por unidad incluye sus descendientes para consulta del supervisor.
- Un alcance por centro permite operar o consultar trabajadores y unidades dentro de ese centro, segun el rol.

## Matriz base

Esta matriz debe mantenerse como fuente viva de permisos funcionales. Cada vez que se agregue un modulo, pantalla o nuevo alcance, se debe actualizar esta seccion antes del commit de cierre correspondiente.

Reglas de mantenimiento:

- No usar permisos implicitos por nombre de pantalla.
- Todo permiso debe indicar rol, alcance y limite operativo.
- `rh_operativo` solo opera centros completos asignados.
- `supervisor` solo consulta dentro de centro o unidad asignada.
- Si una pantalla mezcla consulta y escritura, documentar ambas por separado.
- Si una regla todavia no esta implementada, marcarla como pendiente y no presentarla como vigente.
- No agregar `super_admin` como columna de operacion diaria empresarial; documentarlo en la matriz de plataforma.

## Matriz de plataforma

| Funcion de plataforma | super_admin | Notas |
|---|---:|---|
| Ver listado de todas las empresas | Si | No requiere pertenecer a la empresa. |
| Crear empresa | Si | Puede crear tenant/empresa inicial. |
| Editar empresa | Si | Para soporte, correccion administrativa o configuracion inicial. |
| Activar o inactivar empresa | Si | No equivale a operar dentro de la empresa como RH. |
| Crear administrador de empresa | Si | Crea o asigna `admin_empresa` inicial. |
| Administrar catalogos globales | Si | Solo catalogos realmente globales, por ejemplo legales/plataforma. |
| Entrar a operacion diaria empresarial | No por defecto | Si requiere soporte operativo, debe quedar auditado y no sustituye roles empresariales. |
| Dictaminar jornadas de una empresa | No | Corresponde a `admin_empresa`, `rh_admin` o `rh_operativo` con alcance. |
| Publicar programacion semanal de una empresa | No | Corresponde a roles empresariales. |
| Capturar eventos de trabajadores | No | Corresponde a roles empresariales o flujos de trabajador/kiosco. |

| Funcion | admin_empresa | rh_admin | rh_operativo | supervisor |
|---|---:|---:|---:|---:|
| Configuracion global de empresa | Si | Limitado / no critico | No | No |
| Administrar usuarios admin_empresa | Si | No | No | No |
| Administrar usuarios RH | Si | Si | No | No |
| Administrar supervisores | Si | Si | Solo supervisores dentro de sus centros | No |
| Asignar alcances operativos | Si | Si | Solo a supervisores dentro de sus centros | No |
| Centros | Si | Si | Centros completos asignados | Solo consulta asignados |
| Unidades organizacionales | Si | Si | Administra unidades dentro de sus centros | Solo consulta asignadas |
| Trabajadores | Si | Si | Administra trabajadores dentro de sus centros | Solo consulta asignados |
| Catalogo de turnos | Si | Si | Si | No |
| Modelos de horario | Si | Si | Crea y consulta; asigna dentro de sus centros | No |
| Programacion semanal borrador | Si | Si | Crea/edita/genera si el lote es de centro asignado completo | No |
| Publicar programacion semanal | Si | Si | Si, solo de centro asignado completo | No |
| Programacion publicada | Si | Si | Consulta dentro de sus centros | Solo consulta publicada dentro de alcance |
| Jornadas | Si | Si | Opera dentro de sus centros | Solo consulta asignadas |
| Incidencias y ausencias | Si | Si | Dictamina dentro de sus centros | Solo consulta |
| Periodos de asistencia | Si | Si | Solo si el alcance cubre el periodo | No |
| Exportacion CSV | Si | Si | Solo alcance asignado | No |

## Matriz operativa por pantalla

| Pantalla / modulo | Ruta | admin_empresa | rh_admin | rh_operativo | supervisor | Notas de alcance |
|---|---|---:|---:|---:|---:|---|
| Empresas | `/companies` | Administra | Consulta/configuracion no critica segun pantalla | No | No | La empresa inactiva no debe aparecer en selector operativo. |
| Configuracion de empresa | `/company-settings` | Administra | Limitado / no critico | No | No | Configuracion global critica queda fuera de RH operativo. |
| Usuarios | `/users` | Administra empresa | Administra RH operativo y supervisores | No | No | `admin_empresa` controla administradores; `rh_admin` controla roles operativos. |
| Centros | `/centers` | Administra | Administra | Consulta/operacion dentro de centros asignados cuando aplique | Consulta asignados | RH operativo no administra centros globalmente. |
| Areas y departamentos | `/organization/units` | Administra | Administra | Administra dentro de centros asignados | Consulta asignadas | Empresas sin areas operan por centro. |
| Asignaciones organizacionales | `/organization/assignments` | Administra | Administra | Administra trabajadores de sus centros | No | RH operativo no opera trabajadores fuera de sus centros. |
| Responsables y supervisores | `/organization/scopes` | Administra | Administra RH operativo y supervisores | Asigna supervisores dentro de sus centros | No | RH operativo no asigna otros RH operativo ni centros fuera de su alcance. |
| Mi alcance | `/organization/my-scope` | No aplica | No aplica | Consulta su alcance | Consulta su alcance | Vista informativa para roles con scope explicito. |
| Catalogo de turnos | `/scheduling/shifts` | Administra | Administra | Administra catalogo | No | El catalogo pertenece a empresa; el supervisor no lo consulta en el MVP. |
| Modelos de horario | `/scheduling/profiles` | Administra | Administra | Crea y administra | No | La asignacion se limita por centro para RH operativo. |
| Aplicacion de modelos | `/scheduling/profile-assignments` | Administra | Administra | Asigna dentro de sus centros | Consulta si aplica | RH operativo no asigna a empresa completa si no tiene alcance global. |
| Programacion semanal | `/scheduling/daily` | Crea/edita/publica | Crea/edita/publica | Crea/edita/publica lotes de sus centros completos | Consulta publicados dentro de alcance | Supervisor no ve borradores. |
| Descansos obligatorios | `/mandatory-rest-days` | Administra internos empresa | Administra internos empresa | No | No | Catalogos globales corresponden a `super_admin`. |
| Registro asistido | `/time-clock` | Opera | Opera | Opera trabajadores de sus centros | No | No acepta hora explicita. |
| Kiosco | `/kiosk` | Uso operativo | Uso operativo | Uso operativo | Uso operativo | Identificacion por credencial de trabajador. |
| Eventos / captura justificada | `/time-events/manual` | Captura y revisa | Captura y revisa | Captura y revisa dentro de sus centros | No | RH operativo no puede capturar para centros ajenos. |
| Jornadas | `/work-days` | Ver y dictaminar | Ver y dictaminar | Ver y dictaminar dentro de sus centros | Solo consulta asignadas | Faltas del dia actual se ocultan hasta cierre del dia. |
| Incidencias y ausencias | `/attendance-incidents` | Administra | Administra | Opera dentro de sus centros cuando la policy lo permita | Consulta futura/pendiente | Mantener separado de alertas automaticas de jornada. |
| Periodos de asistencia | `/attendance-periods` | Genera/cierra | Genera/cierra | Genera/cierra si el alcance cubre el periodo | No | No calcula nomina; entrega asistencia del periodo. |
| Eventos rapidos de prueba | `/testing/quick-events` | Super admin / demo segun entorno | No recomendado | No | No | Herramienta provisional de pruebas, no flujo operativo final. |

## Matriz por tipo de accion

| Accion | admin_empresa | rh_admin | rh_operativo | supervisor |
|---|---:|---:|---:|---:|
| Crear datos maestros de empresa | Si | Parcial | No | No |
| Crear datos operativos dentro de centro | Si | Si | Si, centro asignado completo | No |
| Editar datos operativos dentro de centro | Si | Si | Si, centro asignado completo | No |
| Consultar datos publicados | Si | Si | Si, centro asignado | Si, centro/unidad asignada |
| Consultar borradores | Si | Si | Si, centro asignado completo | No |
| Publicar programacion | Si | Si | Si, centro asignado completo | No |
| Dictaminar jornadas/alertas | Si | Si | Si, centro asignado completo | No |
| Capturar eventos justificados | Si | Si | Si, centro asignado completo | No |
| Asignar supervisores | Si | Si | Si, solo dentro de centros asignados | No |
| Asignar RH operativo | Si | Si | No | No |
| Exportar informacion de periodo | Si | Si | Si, alcance cubierto | No |

## Consecuencias

- `owner` no debe seguir usandose como rol operativo nuevo.
- `admin` debe normalizarse a `admin_empresa`.
- `rh` debe dividirse en `rh_admin` y `rh_operativo`.
- `supervisor` requiere alcance explicito para ver informacion.
- Las policies deben evaluar rol, empresa activa, membresia activa, empresa activa y alcance cuando aplique.
- El modulo de usuarios debe permitir asignar rol y alcance operativo.

## Pendiente tecnico

La base esta en desarrollo local, por lo que el refactor puede hacerse sin mantener alias historicos cuando se ejecute el bloque tecnico de normalizacion.

El cambio tecnico posterior debera actualizar:

- `RoleKey`.
- `RoleSeeder`.
- seeders demo.
- policies.
- Actions.
- vistas.
- pruebas.
- documentacion operativa.

No se implementa en este ADR el refactor tecnico.
