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

## Roles canonicos

### `super_admin`

Administrador de plataforma.

- Opera fuera del contexto normal de empresa.
- Administra catalogos globales cuando aplique.
- No representa a un usuario operativo de una empresa cliente.

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

- Opera solo centros o unidades asignadas.
- Puede consultar y operar trabajadores, jornadas, incidencias, ausencias y programacion dentro de su alcance.
- No administra configuracion global de empresa.
- No crea administradores generales.

### `supervisor`

Responsable operativo de consulta.

- Solo consulta dentro de centros o unidades asignadas.
- No obtiene permisos solo por tener el rol.
- No administra datos maestros.
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

## Matriz base

| Funcion | admin_empresa | rh_admin | rh_operativo | supervisor |
|---|---:|---:|---:|---:|
| Configuracion global de empresa | Si | Limitado / no critico | No | No |
| Administrar usuarios admin_empresa | Si | No | No | No |
| Administrar usuarios RH | Si | Si | No | No |
| Administrar supervisores | Si | Si | No | No |
| Asignar alcances operativos | Si | Si | No | No |
| Centros | Si | Si | Solo asignados | Solo consulta asignados |
| Unidades organizacionales | Si | Si | Solo asignadas | Solo consulta asignadas |
| Trabajadores | Si | Si | Solo asignados | Solo consulta asignados |
| Horarios y programacion | Si | Si | Solo asignados | Solo consulta asignados |
| Jornadas | Si | Si | Solo asignadas | Solo consulta asignadas |
| Incidencias y ausencias | Si | Si | Dictamina dentro de alcance | Solo consulta |
| Periodos de asistencia | Si | Si | Solo si el alcance cubre el periodo | No |
| Exportacion CSV | Si | Si | Solo alcance asignado | No |

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
