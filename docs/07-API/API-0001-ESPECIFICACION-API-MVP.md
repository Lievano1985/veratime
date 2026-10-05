---
id: API-0001
title: Especificación API del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-03
updated: 2026-09-18
tags:
  - api
  - mvp
  - rest
  - sanctum
  - domain-first
  - integraciones
  - veratime
---

# API-0001 — Especificación API del MVP

## 1. Objetivo

Definir la API mínima del MVP de Vera Time.

La API deberá permitir que funcionalidades clave del sistema puedan operar mediante integraciones externas, importaciones, futuras aplicaciones móviles, clientes empresariales y servicios internos, sin duplicar lógica de negocio.

Este documento parte de las decisiones aprobadas:

- Arquitectura domain-first.
- Exposición API-first pragmática y bidireccional.
- Monolito modular Laravel.
- MySQL 8 / MariaDB compatible como base inicial.
- Colas por base de datos en MVP.
- AWS u otra nube como evolución posterior al MVP/piloto.
- API REST versionada.
- Autenticación por tokens.
- Multi-tenant estricto por empresa.

---

## 2. Principio central

La API no será una copia secundaria de la interfaz.

La API será una entrada oficial al sistema.

El patrón obligatorio será:

```text
Livewire / Web
        ↓
Application Action / Service
        ↓
Domain Service
        ↓
Persistence

API /api/v1/time
        ↓
Application Action / Service
        ↓
Domain Service
        ↓
Persistence

CSV / Job / Integración
        ↓
Application Action / Service
        ↓
Domain Service
        ↓
Persistence
```

La lógica principal no deberá vivir en controladores API, componentes Livewire ni jobs.

---

## 3. Alcance API del MVP

## 3.1 API P0

La API P0 deberá permitir:

- Crear o actualizar trabajadores.
- Consultar trabajadores.
- Crear o actualizar relaciones laborales.
- Registrar eventos de jornada.
- Consultar eventos.
- Consultar jornadas calculadas.
- Consultar alertas.
- Crear incidencias.
- Consultar incidencias.
- Consultar reportes de periodo.
- Consultar exportaciones.
- Crear importaciones.
- Consultar estado de importaciones.
- Consultar logs básicos de integración.

## 3.2 API P1 / controlada

Queda para fase posterior o habilitación controlada:

- Crear centros.
- Crear horarios.
- Asignar horarios.
- Resolver alertas.
- Aprobar o rechazar correcciones.
- Generar expedientes.
- Confirmar conformidad digital vía API.
- Sincronización directa con ClickBalance.
- Webhooks salientes.
- API pública para terceros con portal de desarrolladores.

## 3.3 Estado de implementación — 18 de septiembre de 2026

La fundación de API v1 ya está incorporada, pero la API P0 completa sigue en desarrollo.

- `GET /api/v1/time/workers`, `GET /api/v1/time/workers/{workerId}`, `POST /api/v1/time/workers` y `PUT /api/v1/time/workers/{workerId}` están disponibles.
- `POST /api/v1/time/time-events` está disponible con scope `time-events:write`. Acepta `worker_id` o `employee_code`, conserva el instante ocurrido y usa `Idempotency-Key` para devolver el mismo evento en un reintento sin crear duplicados.
- `POST /api/v1/time/time-events/{eventId}/void` está disponible para anular lógicamente un evento administrativo. Requiere `time-events:write` y motivo; conserva el registro original y, cuando el evento tiene relación laboral, encola el recálculo de su jornada.
- `POST /api/v1/time/time-events/{eventId}/approve` y `POST /api/v1/time/time-events/{eventId}/reject` están disponibles para revisar únicamente capturas manuales pendientes. Ambos requieren `time-events:write`; el rechazo requiere motivo y deja el evento en estado `ignored`.
- `GET /api/v1/time/time-events`, `GET /api/v1/time/time-events/{eventId}`, `GET /api/v1/time/work-days` y `GET /api/v1/time/work-days/{workDayId}` están disponibles para consulta administrativa de solo lectura. Los listados son paginados y todos están acotados a la empresa del token.
- `GET /api/v1/time/attendance-incidents`, `GET /api/v1/time/attendance-incidents/{incidentId}`, `POST /api/v1/time/attendance-incidents` y `POST /api/v1/time/attendance-incidents/{incidentId}/cancel` están disponibles para incidencias operativas. La creación y cancelación reutilizan las Actions del dominio; cancelar conserva el registro y su trazabilidad.
- `GET /api/v1/time/attendance-periods` y `GET /api/v1/time/attendance-periods/{periodId}` están disponibles para consultar periodos de asistencia. `GET /api/v1/time/attendance-periods/{periodId}/payroll-csv` descarga el CSV de nómina de un periodo cerrado.
- `GET /api/v1/time/alerts/{alertId}` consulta el detalle de una alerta dentro de la empresa del token, con `alerts:read`.
- `GET /api/v1/time/workers/{workerId}/relationships` expone el historial de relaciones laborales del trabajador de la empresa del token, con `workers:read`.
- `GET /api/v1/time/centers` y `GET /api/v1/time/centers/{centerId}` están disponibles para consulta administrativa de centros de trabajo, con `centers:read`.
- Los tokens Sanctum se emiten con empresa y scopes inmutables; la empresa no llega como parámetro del cliente.
- El grupo operativo actual exige usuario autenticado, empresa activa, producto `time` operativo, rol administrativo de empresa (`super_admin`, `admin_empresa` o `rh_admin`), scope por endpoint y límite de 60 solicitudes por minuto por token y empresa.
- Las respuestas exitosas de estos endpoints incluyen `trace_id` y el encabezado `X-Trace-Id`.
- El alta y la actualización de trabajador reutilizan `SaveWorkerWithEmploymentRelationshipAction`, la misma Action de la interfaz web. El cambio de relación laboral exige motivo y conserva la vigencia o evidencia protegida conforme a las reglas del dominio.
- Los administradores de empresa pueden crear y revocar sus propias credenciales desde `/time/api-tokens`; el secreto se muestra una única vez. La pantalla permite emitir `centers:read` para integraciones que necesiten consultar centros de trabajo.
- La identidad personal ya usa el vínculo explícito `user_worker_links` por empresa. `POST /api/v1/time/auth/login` autentica el canal móvil/PWA, `POST /api/v1/time/auth/forgot-password` solicita recuperación sin enumerar cuentas, `GET /api/v1/time/me` devuelve su contexto resuelto, y el espacio `/api/v1/time/me` expone alertas, programación, consultas propias, marcaje individual y sincronización básica de eventos pendientes.
- Las consultas personales requieren `self:read`; el marcaje requiere además `self:write`. Ninguna ruta acepta trabajador o empresa como contexto enviado por el cliente.
- Desde una sesión web autenticada con empresa activa, `POST /time/personal-access-token` emite una credencial personal `pwa-personal` con `self:read` y `self:write`, sólo después de validar el vínculo activo con el trabajador.
- `DELETE /api/v1/time/me/access-token` permite al cliente personal revocar exclusivamente su Bearer token actual, sin desactivar la cuenta, el vínculo ni el historial laboral.

Pendiente en este bloque: endpoints específicos de relaciones laborales, importaciones, exportaciones adicionales, logs, auditoría persistente y normalización de todas las respuestas de error. Los roles de alcance parcial no pueden usar el grupo operativo administrativo; deben utilizar exclusivamente el canal personal cuando tengan vínculo y token válidos.

---

## 4. Base URL y versionamiento

## 4.1 Base URL

En producción:

```text
https://app.veratime.com/api/v1/time
```

En desarrollo/staging:

```text
https://staging.veratime.com/api/v1/time
```

o según el hosting disponible.

## 4.2 Versionamiento

Toda la API inicia en:

```text
/api/v1/time
```

No se crearán endpoints sin versión.

## 4.3 Política de compatibilidad

Mientras sea posible, los cambios serán compatibles hacia atrás.

Cambios no compatibles deberán ir a:

```text
/api/v2
```

---

## 5. Autenticación

## 5.1 Decisión

La API utilizará tokens Bearer mediante Laravel Sanctum.

Encabezado:

```http
Authorization: Bearer {token}
```

## 5.2 Token por empresa

Cada token estará ligado a una empresa.

El token define el contexto `company_id`.

Por seguridad, los endpoints externos no deberán permitir operar libremente sobre otra empresa mediante un parámetro manipulable.

## 5.3 Alcances del token

Cada token deberá tener capacidades limitadas.

Ejemplos:

```text
workers:read
workers:write
centers:read
relationships:write
time-events:write
time-events:read
work-days:read
alerts:read
incidents:read
incidents:write
reports:read
imports:write
exports:read
integrations:logs
self:read
self:write
```

## 5.4 Regla

Un token sin alcance suficiente recibirá:

```http
403 Forbidden
```

## 5.5 Acceso administrativo temporal del grupo operativo

Los endpoints operativos actuales de `/api/v1/time` están restringidos a una membresía activa con rol `super_admin`, `admin_empresa` o `rh_admin`, además de los scopes propios de cada endpoint.

Esta restricción evita que un usuario con alcance parcial reciba datos horizontales de la empresa mediante listados administrativos. No sustituye la futura identidad personal: el acceso de una persona trabajadora a sus propios datos será un bloque separado, con vínculo explícito `users` ↔ `workers`, scopes y endpoints personales dedicados. Hasta entonces, una cuenta de persona trabajadora o cualquier rol no administrativo debe recibir `403 Forbidden` en este grupo operativo.

## 5.6 Identidad personal y endpoints `/me` — implementado en esta fase

Este bloque implementa las historias `BL-0108`, `BL-0613` y `BL-1408`. Su finalidad es permitir que una cuenta humana consulte, desde el portal responsive/PWA o un cliente futuro, exclusivamente la información del trabajador que representa dentro de la empresa resuelta por su token.

### Vínculo explícito por empresa

- El vínculo entre `users` y `workers` es explícito, persistente y acotado por `company_id`; no se infiere por correo, nombre, código de empleado ni otro atributo coincidente.
- La tabla `user_worker_links` conserva `company_id`, `user_id`, `worker_id` y estado. Sus restricciones únicas permiten como máximo un trabajador por usuario y un usuario por trabajador dentro de una misma empresa.
- La cuenta, la membresía en la empresa y el trabajador vinculado deben estar activos para conceder el acceso personal.
- Un usuario puede tener vínculos en distintas empresas, pero un token personal conserva una sola empresa autorizada. Si existen varias empresas elegibles, la selección sucede antes de emitir el token mediante `company_id` en el cuerpo de `POST /api/v1/time/auth/login`. Una vez emitido, ningún endpoint Bearer personal acepta `company_id` desde query string, cuerpo o encabezado del cliente.
- La revocación del vínculo cambia su estado a `revoked`; la revocación del token elimina sólo esa credencial. Ambos bloquean el canal afectado sin borrar al trabajador, los eventos ni la evidencia histórica.
- El vínculo no otorga permisos administrativos, ni permite consultar o modificar datos de otros trabajadores.

El administrador autorizado gestiona el vínculo desde la pantalla existente `/time/users`. La pantalla muestra el trabajador vinculado, permite seleccionar un trabajador activo de la empresa y revocar el vínculo. La vinculación valida que cuenta y trabajador pertenezcan a la empresa actual y evita una segunda cuenta activa para el mismo trabajador.

### Emisión personal desde sesión web

La ruta `POST /time/personal-access-token` está protegida por sesión autenticada, empresa actual y producto `time` operativo. Resuelve el trabajador mediante el vínculo activo y emite un Bearer token Sanctum nombrado `pwa-personal`, ligado al `company_id` actual y con los scopes `self:read` y `self:write`.

El secreto viaja sólo en la respuesta `201 Created` de esa emisión y no puede volver a consultarse. El cliente responsive/PWA debe tratarlo como credencial de sesión y no enviarlo a terceros ni registrarlo en logs. La vista `/time/my-day` es una vista web inicial de consulta y actualmente lee el mismo contexto de servidor; todavía no es un cliente JavaScript que consuma este token.

### Inicio de sesión móvil personal

`POST /api/v1/time/auth/login` inicia la sesión del cliente responsive/PWA sin requerir una sesión web previa. Recibe:

```json
{
  "email": "persona@empresa.mx",
  "password": "contraseña-de-la-cuenta",
  "company_id": 123
}
```

`email` y `password` son obligatorios; `company_id` es opcional y sólo sirve para seleccionar una empresa elegible tras validar las credenciales. La ruta está limitada a cinco solicitudes por minuto por combinación de correo normalizado e IP y agrega `X-Trace-Id` a la respuesta.

Una empresa es elegible sólo cuando la cuenta está activa y pertenece a la empresa, el producto VERA Time está operativo, el vínculo `user_worker_links` está activo y su trabajador vinculado está activo y pertenece a esa empresa. No se requiere un rol administrativo para este canal personal.

- Si existe una sola empresa elegible, la API emite directamente un token Sanctum personal `pwa-personal` ligado a esa empresa, con `self:read` y `self:write`, y responde `201 Created`.
- Si existen varias empresas elegibles y no se recibe `company_id`, responde `409 Conflict`, `code: company_selection_required` y `data.companies` con únicamente `id` y `name` de las empresas disponibles. No emite token en esa respuesta. El cliente debe reenviar las mismas credenciales con uno de esos identificadores.
- Si la empresa indicada no es elegible para la cuenta, responde `422 Unprocessable Entity` sobre `company_id`; credenciales inválidas también responden `422` sobre `email`. Si no hay ningún vínculo personal elegible, responde `403 Forbidden`. Ninguno de estos casos emite token.

La respuesta exitosa contiene `data.token`, `data.token_type` (`Bearer`), `data.abilities` y `data.context`, además de `meta.trace_id`. El secreto se entrega una sola vez: el cliente debe resguardarlo como credencial de sesión y no registrarlo ni compartirlo.

### Recuperación de contraseña móvil

`POST /api/v1/time/auth/forgot-password` recibe únicamente `email`, está limitado a cinco solicitudes por minuto por correo normalizado e IP y responde `202 Accepted` con el mismo mensaje tanto para correos existentes como inexistentes. Cuando la cuenta existe, usa el flujo estándar de restablecimiento y el proveedor transaccional configurado; la respuesta no emite token ni revela si el correo está registrado. Un fallo de entrega se registra de forma segura y conserva la misma respuesta neutral.

### Contrato personal de consulta y marcaje

Los endpoints personales viven bajo el mismo producto y versión, pero con un espacio de ruta separado del grupo administrativo:

```http
POST /api/v1/time/auth/login
POST /api/v1/time/auth/forgot-password
GET /api/v1/time/me
GET /api/v1/time/me/marking-security
GET /api/v1/time/me/alerts
GET /api/v1/time/me/schedule
GET /api/v1/time/me/time-events
POST /api/v1/time/me/time-events
POST /api/v1/time/me/time-events/sync
GET /api/v1/time/me/time-events/{eventId}
GET /api/v1/time/me/work-days
GET /api/v1/time/me/work-days/{workDayId}
DELETE /api/v1/time/me/access-token
```

Requieren token Bearer personal, empresa/producto Time operativos, usuario y membresía activos y vínculo activo con un trabajador de esa empresa. Las consultas y la revocación requieren `self:read`; el marcaje requiere `self:read` y `self:write`. No requieren ni habilitan los roles administrativos del grupo operativo. `GET /me/marking-security` está disponible para resolver la política efectiva y una referencia de tiempo; la vinculación y las demás rutas de seguridad se norman en `API-0002-PROPUESTA-SEGURIDAD-MARCAJE.md` y se activarán de forma gradual por política de empresa.

#### `GET /me`

Devuelve el contexto móvil personal resuelto exclusivamente desde el Bearer token: no acepta `company_id`, `worker_id` ni parámetros de selección. Requiere `self:read`, token con empresa válida, producto VERA Time operativo, usuario activo y vínculo activo con un trabajador activo de ese tenant.

La respuesta contiene:

- `company`: `id`, `name` y `timezone` de la empresa del token;
- `worker`: `id`, `employee_code` y `full_name` del trabajador vinculado;
- `permissions.can_register_time_events`, que indica la capacidad expuesta actualmente por el contexto;
- `current_time_record`: estado actual de marcaje, acciones permitidas para ese estado, fecha y zona horaria local, y el último evento válido cuando existe. Reutiliza la misma resolución de estados que el reloj web (`sin_entrada`, `trabajando`, `en_pausa` o `jornada_cerrada`), para que la PWA pueda mostrar sólo las acciones aplicables;
- `today_work_day`: jornada de la fecha actual según la zona horaria de la empresa, o `null` si aún no existe. Cuando existe incluye identificador, fecha, estado, estado de horario, centro y los minutos trabajados de su cálculo activo;
- `meta.trace_id` y el encabezado `X-Trace-Id`.

El mismo objeto de contexto se incluye al iniciar sesión exitosamente. Por lo tanto, el cliente no debe conservar ni inferir una empresa o trabajador distintos a los emitidos por el token.

#### `GET /me/schedule`

Devuelve únicamente la programación diaria publicada de la relación laboral activa del trabajador vinculado al token. Requiere `self:read`, empresa/producto Time operativos, usuario activo y vínculo `user_worker_links` activo. Si el trabajador no tiene relación laboral activa en la empresa resuelta por el token, devuelve una colección vacía.

Admite los filtros opcionales `date_from` y `date_to` en formato `Y-m-d`. Si no se envía `date_from`, inicia en la fecha actual de la zona horaria de la empresa; si no se envía `date_to`, el intervalo concluye trece días después de la fecha inicial. Cuando se proporcionan ambas fechas, la final debe ser igual o posterior a la inicial y el rango no puede exceder 31 días; un incumplimiento responde `422 Unprocessable Entity`.

La consulta filtra por `company_id` del token, por la relación laboral activa resuelta en servidor y por lotes de programación con estado `published`. No devuelve borradores ni programaciones de otra relación laboral, aunque pertenezcan a la misma empresa. `company_id`, `worker_id` y `employment_relationship_id` están prohibidos como filtros o parámetros de sustitución y generan `422`; el cliente no puede cambiar tenant, trabajador ni relación.

Cada elemento de `data` incluye `id`, `work_date`, `day_type`, `timezone`, `required_minutes`, la plantilla de turno opcional (`shift_template.id`, `shift_template.name`) y sus `segments` ordenados, con orden, tipo, modalidad temporal, horas locales, desplazamientos de día, duración e indicador de tiempo pagado. La respuesta incluye `meta.trace_id` y el encabezado `X-Trace-Id`; no está paginada y es estrictamente de consulta.

#### `GET /me/alerts`

Devuelve una lista paginada de alertas exclusivamente del trabajador vinculado al Bearer token. Requiere `self:read`, empresa/producto Time operativos, usuario activo y vínculo `user_worker_links` activo; no requiere ni concede permisos administrativos de alertas.

Admite `date_from` y `date_to` (aplicadas a `detected_at`, con fecha final igual o posterior), `alert_type` (código), `status`, `severity` y `per_page` (mínimo 1, máximo 100; 25 por defecto). Los estados permitidos son `new`, `in_review`, `pending_information`, `justified`, `corrected` y `closed`; las severidades permitidas son `informational`, `warning`, `high` y `critical`. Si no se envía `status`, la Action aplica el estado abierto por defecto: `new`, `in_review` y `pending_information`.

El tenant se toma del token y el trabajador se resuelve desde el vínculo activo. `company_id`, `worker_id`, `center_id` y `assigned_to` están prohibidos como filtros o parámetros de sustitución y responden `422 Unprocessable Entity`; no se puede consultar alertas de otro trabajador, centro, responsable o empresa. La lista se ordena por severidad (`critical`, `high`, `warning`, `informational`) y después por fecha de detección descendente.

Cada elemento devuelve `id`, tipo, título, descripción, severidad, estado, regla, instante de detección, trabajador, jornada y centro relacionados, más resolución y fecha de resolución cuando existen. La respuesta paginada incluye `data`, `meta.current_page`, `meta.per_page`, `meta.total`, `meta.trace_id` y enlaces `first`, `last`, `prev` y `next`; conserva los filtros en los enlaces. Es un endpoint estrictamente de consulta: no asigna, resuelve, corrige ni cierra alertas.

`DELETE /me/access-token` elimina solamente la credencial Sanctum presentada en esa solicitud después de comprobar que es personal, pertenece al mismo usuario y está ligada a la empresa resuelta. Responde `204 No Content`; no altera la cuenta web, su membresía, el vínculo ni los registros de jornada.

La API personal deriva siempre `company_id` del token y `worker_id` del vínculo autenticado en el servidor. No acepta `company_id`, `worker_id`, `employee_code`, `center_id`, `user_id` ni `external_id` como filtros, cuerpo o parámetros sustitutos; enviar cualquiera produce `422 Unprocessable Entity`. Si el vínculo no existe, está inactivo o no pertenece a la empresa del token, responde `403 Forbidden` sin revelar datos de otros trabajadores.

#### `GET /me/time-events` y `GET /me/time-events/{eventId}`

- Devuelve únicamente eventos del trabajador vinculado en la empresa del token.
- El listado admite `date_from`, `date_to`, `event_type`, `status`, `page` y `per_page` (mínimo 1, máximo 100). Las fechas se aplican de forma inclusiva a `occurred_local_date`, los valores de tipo y estado se validan contra el dominio, y el orden es descendente por `occurred_at_utc`.
- La respuesta paginada devuelve `data`, `meta.current_page`, `meta.per_page`, `meta.total`, `meta.trace_id` y enlaces `first`, `last`, `prev` y `next`; conserva los filtros en esos enlaces.
- El detalle recibe un identificador numérico y busca exclusivamente dentro de los eventos del trabajador resuelto; un identificador ajeno o inexistente responde `404`.
- Es exclusivamente de lectura: no registra, anula, corrige ni recalcula eventos.

#### `POST /me/time-events`

Registra un evento de jornada desde el cliente responsive/PWA de la persona vinculada. No es una variante del endpoint administrativo `POST /time-events`: resuelve todo el contexto sensible en servidor y reutiliza el dominio de registro de eventos.

- Requiere Bearer token personal con `self:read` y `self:write`, empresa/producto Time, usuario, membresía y vínculo `user_worker_links` activos.
- El encabezado `Idempotency-Key` es obligatorio y no puede exceder 255 caracteres. Una primera solicitud válida responde `201 Created` y `meta.idempotent_replay: false`. Repetir exactamente esa llave para el mismo usuario, trabajador y tenant devuelve el mismo evento con `200 OK` y `meta.idempotent_replay: true`, sin crear un segundo marcaje. Una llave ya usada por otra persona trabajadora, fuente o usuario se rechaza con `422`.
- El cuerpo permite únicamente `event_type` (`clock_in`, `clock_out`, `break_start`, `break_end`), `occurred_at`, `timezone` opcional, `metadata` opcional y `device` opcional con `device.code` y `device.name`. `occurred_at` se valida como fecha y `timezone` como zona horaria válida.
- `company_id`, `worker_id`, `employee_code`, `center_id`, `user_id` y `external_id` están prohibidos. El centro y la relación laboral se resuelven desde la relación activa del trabajador; no pueden ser elegidos ni sustituidos por el cliente.
- El servidor fija `source` a `pwa` y registra al usuario autenticado como `source_user_id`; el cliente no puede modificar esos valores. La respuesta incluye el evento y `meta.trace_id`.
- El contrato base no acepta huellas, rostros ni otros datos biométricos. Cuando una política de seguridad móvil esté activa, el evento debe adjuntar el objeto `security` definido en `API-0002-PROPUESTA-SEGURIDAD-MARCAJE.md`; el backend valida vínculo, versión de política, evidencia de ubicación y firma sin almacenar biometría cruda.
- El marcaje no administra trabajadores, centros, incidencias, alertas ni datos de otras personas. Las correcciones, anulaciones y revisiones conservan sus flujos administrativos y de evidencia separados.

#### `POST /me/time-events/sync`

Sincroniza de uno a 25 marcajes personales pendientes. Requiere `self:read` y `self:write`; cada elemento de `events` exige `client_event_id` único dentro del lote, `event_type` y `occurred_at`, y puede incluir zona horaria, metadatos y datos de dispositivo. Cuando la política efectiva lo exija, incluye la evidencia `security` definida en API-0002. `client_event_id` se usa como la llave de idempotencia de ese evento.

El servidor conserva el tenant, trabajador, relación, centro, fuente `pwa` y usuario fuente resueltos desde el token. Prohíbe `company_id`, `worker_id`, `employee_code`, `center_id`, `user_id` y `external_id` tanto en el lote como en cada elemento. La respuesta `200` devuelve por evento `accepted`, `already_registered` o `rejected`, con evento o error según corresponda, además de los contadores `meta.accepted`, `meta.already_registered`, `meta.rejected` y `trace_id`. Un lote inválido estructuralmente responde `422`.

#### `GET /me/work-days` y `GET /me/work-days/{workDayId}`

- Devuelve únicamente jornadas del trabajador vinculado en la empresa del token.
- El listado admite `date_from`, `date_to`, `status`, `page` y `per_page` (mínimo 1, máximo 100). Las fechas se aplican de forma inclusiva a `work_date`, el estado se valida contra los estados expuestos por el dominio y el orden es descendente por `work_date`.
- La respuesta paginada devuelve `data`, `meta.current_page`, `meta.per_page`, `meta.total`, `meta.trace_id` y enlaces `first`, `last`, `prev` y `next`; conserva los filtros en esos enlaces.
- El detalle recibe un identificador numérico y se limita a las jornadas del trabajador resuelto; un identificador ajeno o inexistente responde `404`.
- Un `GET` no recalcula ni modifica jornadas, cálculos, alertas o snapshots.

### Separación obligatoria de canales

| Canal | Autenticación | Alcance permitido | No permitido |
|---|---|---|---|
| API operativa administrativa | Token de empresa con rol administrativo y scope del recurso | Listados/operaciones de la empresa, según policy | Usarse como acceso personal de trabajador sin los permisos correspondientes |
| API personal `/me` | Inicio de sesión por correo/contraseña con selección explícita de empresa cuando aplica; token personal, membresía y vínculo `user`-`worker` activos; `self:read` para consulta y `self:read` + `self:write` para marcaje | Consultar contexto, alertas, programación publicada, eventos y jornadas propios; registrar y sincronizar los propios marcajes PWA; cuando una política lo exija, enviar evidencia de seguridad definida por servidor | Elegir trabajador/empresa/centro/relación/responsable después de emitir token, ver datos ajenos, administrar empresa, asignar o resolver alertas, enviar `source` o `source_user_id`, transmitir biometría cruda o sustituir la política/vínculo emitidos por servidor |
| Kiosco | Activación técnica de empresa y código/NIP de marcaje | Identificar y registrar los eventos permitidos al trabajador | Crear sesión de portal, consultar `/me`, usar contraseña principal, ver jornadas/incidencias/reportes o administrar tokens |

El cliente Android/PWA podrá consumir este mismo contrato personal y de seguridad sin crear una autenticación ni rutas paralelas. La seguridad del dispositivo se incorpora al MVP conforme a API-0002; el desarrollo de la aplicación continúa como proyecto independiente.

---

## 6. Multi-tenant en API

## 6.1 Contexto de empresa

El contexto de empresa se resolverá por:

1. Token API.
2. Relación del token con la empresa.
3. Permisos del token.

## 6.2 Regla

La API nunca debe devolver datos de una empresa distinta a la del token.

## 6.3 URLs

No se usará `company_id` como parámetro principal en endpoints externos P0.

Ejemplo correcto:

```http
GET /api/v1/time/workers
```

Ejemplo a evitar en API externa P0:

```http
GET /api/v1/time/companies/{company_id}/workers
```

La empresa se infiere por token después de autenticarse. La única excepción es `POST /api/v1/time/auth/login`, donde `company_id` se permite exclusivamente para elegir una de las empresas elegibles antes de emitir el token; no opera recursos ni sustituye el tenant de una credencial existente.

---

## 7. Formato general

## 7.1 Content-Type

```http
Content-Type: application/json
Accept: application/json
```

## 7.2 Fechas

Fechas y horas se enviarán en ISO 8601.

Ejemplo:

```json
{
  "occurred_at": "2026-09-15T08:00:00-06:00"
}
```

## 7.3 Zona horaria

Cuando sea relevante, se deberá enviar:

```json
{
  "timezone": "America/Mexico_City"
}
```

Si no se envía, se usará la zona horaria del centro o empresa, según configuración.

## 7.4 IDs

Los IDs públicos serán ULID/string.

Ejemplo:

```json
{
  "id": "01JZ7X6QK6YPD9V0RMK9FE8YEG"
}
```

---

## 8. Respuesta estándar

## 8.1 Respuesta exitosa

```json
{
  "data": {},
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

## 8.2 Listados paginados

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 150,
    "trace_id": "trc_01JZ7X..."
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

## 8.3 Error estándar

```json
{
  "message": "La solicitud no pudo procesarse.",
  "errors": {
    "employee_code": [
      "El código de empleado ya existe."
    ]
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

---

## 9. Códigos HTTP

| Código | Uso |
|---|---|
| `200` | Consulta exitosa |
| `201` | Recurso creado |
| `202` | Proceso aceptado para ejecución asíncrona |
| `204` | Sin contenido |
| `400` | Solicitud inválida |
| `401` | No autenticado |
| `403` | Sin permisos |
| `404` | No encontrado |
| `409` | Conflicto o duplicado |
| `422` | Error de validación |
| `429` | Límite de uso excedido |
| `500` | Error interno |
| `503` | Servicio no disponible |

---

## 10. Idempotencia

## 10.1 Objetivo

Evitar duplicados cuando una integración reintente una solicitud.

## 10.2 Encabezado

```http
Idempotency-Key: evt_abc_123
```

## 10.3 Reglas

Para eventos de jornada, trabajadores e importaciones, la API deberá soportar:

```text
company_id + source + external_id
```

o:

```text
company_id + idempotency_key
```

## 10.4 Respuesta ante repetición

Si llega una solicitud repetida con la misma clave, deberá devolver el mismo recurso o indicar conflicto controlado.

```http
200 OK
```

o:

```http
409 Conflict
```

según el caso.

---

## 11. Rate limiting

La API aplicará límites por token y empresa.

Recomendación inicial:

```text
60 requests por minuto por token
```

Para endpoints críticos o de importación se podrán aplicar límites diferentes.

Respuesta:

```http
429 Too Many Requests
```

---

# 12. Recursos API P0

---

## 12.1 Workers — Personas trabajadoras

### GET `/workers`

Consulta trabajadores.

Alcance requerido:

```text
workers:read
```

Filtros:

```text
status
center_id
employee_code
search
page
per_page
```

Ejemplo:

```http
GET /api/v1/time/workers?status=active&search=juan
```

Respuesta:

```json
{
  "data": [
    {
      "id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
      "employee_code": "000123",
      "full_name": "Juan Pérez López",
      "email": "juan@example.com",
      "status": "active",
      "center": {
        "id": "01JZ7X8KZ...",
        "name": "Planta Villahermosa"
      },
      "source": "api",
      "external_id": "EMP-123"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1,
    "trace_id": "trc_01JZ7X..."
  }
}
```

### POST `/workers`

Crea trabajador.

Alcance requerido:

```text
workers:write
```

Request:

```json
{
  "employee_code": "000123",
  "full_name": "Juan Pérez López",
  "email": "juan@example.com",
  "phone": "9930000000",
  "rfc": "PELJ900101XXX",
  "curp": "PELJ900101HTCRPN01",
  "center_id": "01JZ7X8KZ...",
  "position_name": "Operador",
  "started_at": "2026-09-01",
  "work_modality": "onsite",
  "external_id": "EMP-123"
}
```

Respuesta:

```http
201 Created
```

```json
{
  "data": {
    "id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
    "employee_code": "000123",
    "full_name": "Juan Pérez López",
    "status": "active"
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/workers/{worker_id}`

Consulta detalle del trabajador.

Alcance requerido:

```text
workers:read
```

Estado de implementación: disponible.

- Requiere el grupo administrativo operativo: token válido, usuario, membresía y empresa activos, producto `time` operativo y rol `super_admin`, `admin_empresa` o `rh_admin`.
- El trabajador se busca desde la relación de la empresa resuelta por el token. Un `worker_id` que pertenezca a otra empresa, o que no exista, responde `404 Not Found` sin revelar su existencia.
- Devuelve la representación `WorkerResource` junto con `meta.trace_id`; la respuesta también incluye el encabezado `X-Trace-Id`.

### PUT `/workers/{workerId}`

Actualiza los datos operativos del trabajador y, cuando corresponda, aplica el cambio controlado de su relación laboral vigente.

Alcance requerido:

```text
workers:write
```

Estado de implementación: disponible.

Requiere el grupo administrativo operativo: token válido ligado a la empresa, usuario, membresía y empresa activos, producto `time` operativo y rol `super_admin`, `admin_empresa` o `rh_admin`.

Request:

```json
{
  "employee_code": "000123",
  "full_name": "Juan Pérez López",
  "email": "juan@example.com",
  "phone": "9930000000",
  "rfc": "PELJ900101XXX",
  "curp": "PELJ900101HTCRPN01",
  "center_id": "01JZ7X8KZ...",
  "position_name": "Operador de línea",
  "started_at": "2026-09-01",
  "status": "active",
  "relationship_change_reason": "Cambio de centro autorizado por RH"
}
```

Reglas de seguridad y trazabilidad:

- El trabajador se resuelve exclusivamente desde la empresa del token. Un `{workerId}` inexistente o perteneciente a otra empresa responde `404 Not Found` sin revelar su existencia.
- `center_id` debe pertenecer a la misma empresa del token y estar activo; un centro ajeno, inactivo o inexistente se rechaza con validación `422`.
- Si cambian el centro, puesto o fecha de inicio de la relación laboral activa, `relationship_change_reason` es obligatorio. La ausencia del motivo responde `422`.
- La ruta reutiliza `SaveWorkerWithEmploymentRelationshipAction`, igual que la interfaz web. Si aún no existe evidencia protegida, registra una corrección administrativa con valores previos, nuevos, motivo, actor y fecha. Si existe evidencia protegida, no sobrescribe la relación histórica: sólo admite abrir una nueva vigencia hacia adelante cuando no corta horarios publicados ni asistencias existentes.
- La respuesta exitosa devuelve `WorkerResource`, `meta.trace_id` y el encabezado `X-Trace-Id`.

Respuesta:

```http
200 OK
```

---

## 12.2 Employment Relationships — Relaciones laborales

### GET `/workers/{worker_id}/relationships`

Consulta el historial de relaciones laborales de un trabajador. Es exclusivamente de lectura y no crea, corrige, finaliza ni reemplaza relaciones.

Alcance requerido:

```text
workers:read
```

El endpoint requiere el mismo grupo administrativo de Vera Time: token Bearer con empresa resuelta, usuario, membresía y empresa activos, producto `time` operativo y rol `super_admin`, `admin_empresa` o `rh_admin`. La Policy de trabajadores autoriza la consulta.

El trabajador se busca únicamente entre los de la empresa del token. Un `{worker_id}` inexistente o de otro tenant responde `404 Not Found` sin revelar su existencia.

La respuesta devuelve `data` como colección ordenada de la relación más reciente a la más antigua (`started_at` descendente y luego identificador). Cada registro incluye:

- `id`, `status`, `position_name`, `started_at` y `ended_at`;
- `source` y `external_id` cuando existen;
- el centro vinculado (`id` y `name`).

Incluye `meta.trace_id` y el encabezado `X-Trace-Id`. No está paginado en este contrato inicial y no altera vigencias ni evidencia laboral.

La creación, modificación, baja o corrección de relaciones laborales no está expuesta por rutas `POST` o `PATCH` específicas en esta fase. Cualquier cambio autorizado de trabajador continúa pasando por la Action de dominio vigente y sus reglas de preservación histórica.

---

## 12.3 Time Events — Eventos de jornada

### POST `/time-events`

Registra evento de jornada.

Alcance requerido:

```text
time-events:write
```

Headers recomendados:

```http
Idempotency-Key: evt_000123_20260915_080000
```

Request:

```json
{
  "worker_id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
  "employee_code": "000123",
  "event_type": "clock_in",
  "occurred_at": "2026-09-15T08:00:00-06:00",
  "timezone": "America/Mexico_City",
  "center_id": "01JZ7X8KZ...",
  "source": "api",
  "external_id": "CLK-998877",
  "device": {
    "code": "CLOCK-01",
    "name": "Reloj entrada principal"
  },
  "metadata": {
    "raw_payload_id": "abc-123"
  }
}
```

Reglas:

- `worker_id` o `employee_code` debe identificar al trabajador.
- `event_type` debe ser válido.
- Se debe conservar hora del hecho y hora de recepción.
- El evento deberá poder disparar recalculo y alertas.
- La API no debe permitir crear eventos para trabajadores de otra empresa.

Respuesta:

```http
201 Created
```

```json
{
  "data": {
    "id": "01JZ7XEVT...",
    "worker_id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
    "event_type": "clock_in",
    "occurred_at": "2026-09-15T08:00:00-06:00",
    "status": "valid",
    "source": "api"
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/time-events`

Consulta eventos.

Alcance requerido:

```text
time-events:read
```

Filtros:

```text
worker_id
employee_code
center_id
date_from
date_to
event_type
source
status
page
per_page
```

Reglas de consulta:

- `date_from` y `date_to` filtran por la fecha local conservada en el evento (`occurred_local_date`), con límites inclusivos.
- Los filtros `worker_id`, `employee_code` y `center_id` sólo pueden devolver registros de la empresa resuelta por el token; nunca amplían el contexto de empresa.
- `event_type`, `source` y `status` se validan contra los valores admitidos por el dominio. Un valor de filtro inválido debe rechazarse con `422`.
- La respuesta es paginada, con `per_page` de `25` por defecto y máximo de `100`.
- El orden es descendente por instante UTC del evento (`occurred_at_utc`) para ofrecer paginación estable.

Ejemplo:

```http
GET /api/v1/time/time-events?employee_code=000123&date_from=2026-09-01&date_to=2026-09-15&status=valid&page=1&per_page=25
```

Respuesta mínima esperada:

```json
{
  "data": [
    {
      "id": "01JZ7XEVT...",
      "worker_id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
      "event_type": "clock_in",
      "occurred_at": "2026-09-15T08:00:00-06:00",
      "timezone": "America/Mexico_City",
      "status": "valid",
      "source": "api"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1,
    "trace_id": "trc_01JZ7X..."
  }
}
```

Seguridad y aislamiento:

- Requiere el scope `time-events:read`, además de token válido, usuario/membresía/empresa activos, producto `time` operativo y rol `super_admin`, `admin_empresa` o `rh_admin`.
- La consulta base debe iniciar por `company_id` resuelto desde el token. Un identificador de evento, trabajador o centro de otra empresa no debe revelar su existencia; los detalles administrativos disponibles responden `404 Not Found` cuando el registro no pertenece a ese tenant.
- Este endpoint es únicamente de consulta: no anula, corrige ni recalcula eventos.

### GET `/time-events/{event_id}`

Consulta detalle de evento.

Alcance requerido:

```text
time-events:read
```

Estado de implementación: disponible.

- Requiere token válido, usuario, membresía y empresa activos, producto `time` operativo y rol `super_admin`, `admin_empresa` o `rh_admin`.
- El evento se resuelve únicamente desde la empresa del token. Un `event_id` de otra empresa, o inexistente, responde `404 Not Found`.
- Devuelve `TimeEventResource` y `meta.trace_id`; la respuesta también incluye `X-Trace-Id`.

### POST `/time-events/{event_id}/void`

Anulación lógica de evento.

Estado de implementación: disponible.

Alcance requerido:

```text
time-events:write
```

El endpoint pertenece al grupo administrativo de Vera Time. Requiere Bearer token Sanctum válido, empresa resuelta por el token, usuario, membresía y empresa activos, producto `time` operativo y rol `super_admin`, `admin_empresa` o `rh_admin`. No está disponible para el canal personal `/me` ni para kiosco.

Request:

```json
{
  "reason": "Captura duplicada confirmada."
}
```

Reglas:

- `reason` es obligatorio, texto de 5 a 500 caracteres.
- El evento se busca exclusivamente dentro de la empresa resuelta por el token. Un `event_id` inexistente o de otra empresa responde `404 Not Found`, sin revelar su existencia.
- La operación no elimina ni reemplaza la fuente: conserva el evento y cambia su estado a `voided`; registra motivo, actor, instante UTC de anulación y el estado previo en su evidencia técnica.
- Un evento ya anulado se rechaza con validación; no se vuelve a modificar ni se crea un segundo registro.
- Cuando el evento tiene relación laboral, se encola en la cola `work-days` el recálculo de la jornada afectada después de confirmar la transacción. El endpoint no recalcula síncronamente.

Respuesta: `200 OK` con `TimeEventResource`, estado `voided`, `meta.trace_id` y encabezado `X-Trace-Id`.

### POST `/time-events/{event_id}/approve`

Aprueba una captura manual pendiente de revisión.

Estado de implementación: disponible. Requiere el mismo token, empresa, producto, rol administrativo y scope `time-events:write` que la anulación.

- Resuelve el evento únicamente en el tenant del token; un identificador ajeno o inexistente responde `404 Not Found`.
- Sólo acepta eventos con `source` `admin_manual` y estado `pending_review`. No crea ni reemplaza la fuente de evidencia.
- Conserva en la evidencia técnica la decisión, actor, instante UTC, estado previo y resultado; cambia el evento a `valid`.
- Si existe relación laboral, encola el recálculo de la jornada después de confirmar la transacción.
- Responde `200 OK` con `TimeEventResource`, `meta.trace_id` y `X-Trace-Id`.

### POST `/time-events/{event_id}/reject`

Rechaza una captura manual pendiente de revisión.

Estado de implementación: disponible. Requiere el mismo token, empresa, producto, rol administrativo y scope `time-events:write` que la anulación.

Request:

```json
{
  "reason": "Registro manual duplicado."
}
```

- `reason` es obligatorio, texto de 5 a 500 caracteres.
- Resuelve el evento únicamente en el tenant del token; un identificador ajeno o inexistente responde `404 Not Found`.
- Sólo acepta eventos con `source` `admin_manual` y estado `pending_review`. Conserva el evento y su evidencia, junto con decisión, motivo, actor, instante UTC y estado previo; cambia el estado a `ignored`.
- Responde `200 OK` con `TimeEventResource`, `meta.trace_id` y `X-Trace-Id`. No borra, sustituye ni encola recálculo del evento.

---

## 12.4 Work Days — Jornadas calculadas

### GET `/work-days`

Consulta jornadas calculadas.

Alcance requerido:

```text
work-days:read
```

Filtros:

```text
worker_id
employee_code
center_id
date_from
date_to
status
page
per_page
```

Reglas de consulta:

- `date_from` y `date_to` filtran por `work_date`, con límites inclusivos y en el contexto de zona horaria de la empresa.
- `worker_id`, `employee_code` y `center_id` son filtros dentro de la empresa del token. No se acepta `company_id` en la URL, query string ni cuerpo.
- `status` se valida contra los estados de jornada expuestos por el dominio.
- No se devuelven jornadas futuras; la visibilidad de la fecha actual conserva las reglas del listado de dominio.
- La respuesta es paginada, con `per_page` de `25` por defecto y máximo de `100`; se ordena por `work_date` descendente y después por trabajador. El controlador reutiliza el listado del dominio, sin reproducir reglas de cálculo en API.

Ejemplo:

```http
GET /api/v1/time/work-days?center_id=01JZ7X8KZ...&date_from=2026-09-01&date_to=2026-09-15&status=with_alerts&page=1&per_page=25
```

Respuesta:

```json
{
  "data": [
    {
      "id": "01JZ7XWD...",
      "work_date": "2026-09-15",
      "worker": {
        "id": "01JZ7X6QK6YPD9V0RMK9FE8YEG",
        "employee_code": "000123",
        "full_name": "Juan Pérez López"
      },
      "status": "with_alerts",
      "schedule_status": "scheduled",
      "center": {
        "id": "01JZ7X8KZ...",
        "name": "Planta Villahermosa"
      },
      "active_calculation": {
        "id": "01JZ7XCALC...",
        "version": 2,
        "classification": "diurnal",
        "total_work_minutes": 540,
        "ordinary_minutes": 480,
        "overtime_minutes": 60,
        "late_arrival_minutes": 0,
        "early_departure_minutes": 0
      },
      "alerts_count": 1
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1,
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/work-days/{work_day_id}`

Consulta detalle de jornada.

La implementación actual devuelve la representación administrativa vigente de la jornada: trabajador, centro, cálculo activo y contador de alertas. La carga de eventos, incidencias y versiones como detalle ampliado permanece fuera de este endpoint en esta fase.

Seguridad y aislamiento para listado y detalle:

- Requieren el scope `work-days:read`, además de token válido, usuario/membresía/empresa activos, producto `time` operativo y rol `super_admin`, `admin_empresa` o `rh_admin`.
- Toda jornada, trabajador, centro, cálculo y alerta cargados deben pertenecer al `company_id` del token.
- La API expone el resultado calculado vigente y su contexto de consulta; no recalcula, altera snapshots ni resuelve alertas durante un `GET`.
- Una jornada de otra empresa, o inexistente, responde `404 Not Found` sin revelar su existencia.
- La respuesta de detalle incluye `meta.trace_id` y el encabezado `X-Trace-Id`.

Estado de implementación:

`GET /workers/{worker_id}`, `GET /time-events/{event_id}` y `GET /work-days/{work_day_id}` están disponibles como detalles de solo lectura para roles administrativos, con los scopes `workers:read`, `time-events:read` y `work-days:read`, respectivamente. Todos resuelven el recurso desde el tenant del token, retornan `404 Not Found` para un identificador ajeno y adjuntan `trace_id`. El canal personal separado dispone de listados y detalles propios con `self:read`, así como de `POST /api/v1/time/me/time-events` con `self:read` y `self:write` para la persona trabajadora vinculada.

### POST `/work-days/{work_day_id}/recalculate`

Recalcula jornada.

Prioridad:

```text
P1 controlado
```

Regla:

Solo sistemas o usuarios con permisos fuertes podrán ejecutar recalculo manual por API.

---

## 12.5 Alerts — Alertas preventivas

### GET `/alerts`

Consulta alertas.

Alcance requerido:

```text
alerts:read
```

Filtros:

```text
worker_id
center_id
severity
status
date_from
date_to
alert_type
assigned_to
```

Respuesta:

```json
{
  "data": [
    {
      "id": "01JZ7XALT...",
      "type": "daily_limit_exceeded",
      "title": "Tiempo superior al límite diario configurado",
      "severity": "high",
      "status": "new",
      "worker": {
        "employee_code": "000123",
        "full_name": "Juan Pérez López"
      },
      "work_date": "2026-09-15",
      "detected_at": "2026-09-15T19:00:00-06:00"
    }
  ],
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

Estado de implementación: disponible en `GET /api/v1/time/alerts`.

El endpoint exige `alerts:read`, token ligado a empresa, producto `time` operativo y rol administrativo. Reutiliza `ListAlertsAction`, la misma consulta de la interfaz web; filtra siempre desde `company_id` del token, es paginado y exclusivamente de lectura. Soporta los filtros listados, incluidos `worker_id`, `center_id`, `alert_type` y `assigned_to`, sin recalcular ni resolver alertas.

### GET `/alerts/{alert_id}`

Consulta el detalle de una alerta preventiva. Estado de implementación: disponible.

Requiere:

```text
alerts:read
```

Además requiere token Bearer ligado a empresa, producto `time` operativo, usuario/membresía/empresa activos y rol administrativo (`super_admin`, `admin_empresa` o `rh_admin`). La alerta se resuelve desde `company_id` del token y pasa por la Policy `view`; un `{alert_id}` ajeno o inexistente responde `404 Not Found`.

La respuesta usa `AlertResource` e incluye identificador, tipo, título, descripción, severidad, estado, regla, fecha de detección, trabajador, jornada y su centro cuando aplican, así como resolución y fecha de resolución. Incluye `meta.trace_id` y el encabezado `X-Trace-Id`.

Es sólo de lectura: no recalcula, asigna, resuelve, cierra ni cambia el estado de la alerta.

### PATCH `/alerts/{alert_id}`

Resolver o cambiar estado.

Prioridad:

```text
P1 controlado
```

En el MVP puede resolverse desde la interfaz web.

---

## 12.6 Attendance incidents — Incidencias de asistencia

Estado de implementación: disponible bajo el prefijo administrativo `https://app.veratime.com/api/v1/time`.

Los tres endpoints requieren Bearer token Sanctum ligado a una empresa, producto `time` operativo, usuario/membresía activos, rol `super_admin`, `admin_empresa` o `rh_admin`, rate limit del grupo administrativo y el scope indicado. La empresa se deriva exclusivamente del token: no se acepta `company_id` en request ni se consulta fuera de ese tenant. Las respuestas incluyen `meta.trace_id` y el encabezado `X-Trace-Id`.

### GET `/attendance-incidents`

Consulta incidencias operativas de la empresa del token. Requiere:

```text
incidents:read
```

Filtros opcionales:

```text
worker_id         entero
status            approved | cancelled
incident_type     tipo admitido por el catálogo de incidencias
date_from         fecha de inicio mínima
date_to           fecha de fin máxima; no puede ser menor que date_from
per_page          1 a 100; predeterminado 25
```

La respuesta es paginada y cada elemento contiene trabajador, rango de fechas, tipo, estado de pago, estado operativo, referencia, notas y `cancelled_at`. Este endpoint sólo consulta: no recalcula jornadas ni cambia incidencias.

### GET `/attendance-incidents/{incidentId}`

Consulta una incidencia operativa individual de la empresa del token. Requiere:

```text
incidents:read
```

El recurso se busca junto con su trabajador aplicando primero el `company_id` resuelto por el Bearer token y después se autoriza mediante la Policy `view` de `AttendanceIncident`. Un `incidentId` inexistente o perteneciente a otra empresa responde `404 Not Found` sin revelar su existencia.

La respuesta devuelve `AttendanceIncidentResource` y `meta.trace_id`; también adjunta el encabezado `X-Trace-Id`. Es una lectura exclusiva: no recalcula jornadas, no actualiza la incidencia y no altera eventos, evidencia ni historial.

### POST `/attendance-incidents`

Crea una incidencia operativa aprobada para un trabajador activo de la empresa del token. Requiere:

```text
incidents:write
```

Request:

```json
{
  "worker_id": 42,
  "start_date": "2026-09-15",
  "end_date": "2026-09-16",
  "incident_type": "vacation",
  "payment_status": "paid",
  "reference": "VAC-01",
  "notes": "Vacaciones autorizadas."
}
```

`worker_id`, las fechas, el tipo y el estado de pago son obligatorios; `reference` y `notes` son opcionales. La operación reutiliza `CreateAttendanceIncidentAction`, igual que el canal web. Valida trabajador y relación laboral dentro del tenant, tipos y estado de pago admitidos, rango de fechas y traslape con otra incidencia aprobada. Al crearla actualiza la marca de recálculo de las jornadas ya existentes dentro del rango, sin alterar eventos ni historial.

Respuesta exitosa:

```http
201 Created
```

### POST `/attendance-incidents/{incidentId}/cancel`

Cancela de forma no destructiva una incidencia de la empresa del token. Requiere:

```text
incidents:write
```

Request:

```json
{
  "reason": "Vacaciones capturadas por error."
}
```

`reason` es obligatorio y debe tener entre 5 y 500 caracteres. La operación reutiliza `CancelAttendanceIncidentAction`: mantiene la incidencia original, cambia su estado a `cancelled`, conserva en sus metadatos el motivo y registra `cancelled_by` y `cancelled_at` en UTC. También marca para recálculo las jornadas existentes del trabajador en el rango afectado. Un identificador de otra empresa no revela datos y responde `404 Not Found`.

La cancelación de una incidencia que ya está cancelada devuelve su estado actual; no elimina ni crea una segunda incidencia.

No están implementados aún comentarios, adjuntos, propuestas de corrección ni su aprobación por API. Esas capacidades no deben inferirse de estas rutas.

---

## 12.6.1 Attendance Periods — Periodos de asistencia

Estado de implementación: disponible bajo el prefijo administrativo `https://app.veratime.com/api/v1/time`.

Estos endpoints requieren token Bearer Sanctum ligado a una empresa, usuario, membresía y empresa activos, producto `time` operativo y rol administrativo de tenant (`super_admin`, `admin_empresa` o `rh_admin`). La empresa se obtiene exclusivamente del token y las Policies de periodo validan el acceso al recurso. Un identificador de otro tenant responde `404 Not Found` sin revelar su existencia.

### GET `/attendance-periods`

Consulta periodos de asistencia de la empresa del token. Requiere:

```text
work-days:read
```

Es un listado paginado, reutiliza `ListAttendancePeriodsAction` del flujo web y admite estos filtros opcionales:

```text
center_id       entero del centro dentro del tenant
status          estado permitido del periodo
date_from       incluye periodos cuyo final sea igual o posterior a esta fecha
date_to         incluye periodos cuyo inicio sea igual o anterior a esta fecha; no puede ser menor que date_from
per_page        entero entre 1 y 100; predeterminado 25
```

Cada elemento incluye identificador, nombre, inicio, fin, zona horaria, estado, centro, resumen de validación y resumen de reporte. La respuesta incluye `data`, `meta.current_page`, `meta.per_page`, `meta.total` y `meta.trace_id`. La consulta no valida, cierra, cancela ni modifica periodos.

### GET `/attendance-periods/{periodId}`

Consulta el detalle de un periodo de asistencia de la empresa del token. Requiere:

```text
work-days:read
```

La respuesta usa la misma representación del listado, incluido el centro. El alcance organizacional se resuelve internamente con el periodo, pero no se expone como una colección independiente en este contrato inicial. Incluye `meta.trace_id` y el encabezado `X-Trace-Id`. Es exclusivamente de lectura y no altera jornadas, incidencias, alertas ni el estado del periodo.

### GET `/attendance-periods/{periodId}/payroll-csv`

Descarga el CSV de asistencia para nómina de un periodo de la empresa del token. Requiere:

```text
exports:read
```

La Policy `exportPayrollCsv` exige además que el periodo esté en estado `closed`; un periodo abierto, listo o cancelado no se exporta. El endpoint reutiliza `ExportAttendancePeriodPayrollCsvAction`, igual que la descarga web, y responde mediante streaming `text/csv; charset=UTF-8` con descarga adjunta y `Cache-Control: no-store, no-cache, must-revalidate`.

El CSV se genera por bloques desde las jornadas incluidas en el alcance del periodo; no crea una copia nueva del periodo, no cambia cálculos ni altera evidencia. El token, la consulta por `company_id` y la Policy se aplican antes de iniciar el stream.

---

## 12.7 Closing Periods — Cierres de periodo

### GET `/closing-periods`

Consulta periodos.

Alcance requerido:

```text
reports:read
```

Filtros:

```text
period_start
period_end
status
center_id
```

### GET `/closing-periods/{period_id}`

Consulta detalle del periodo.

Incluye:

- Estado.
- Trabajadores incluidos.
- Alertas.
- Reportes individuales.
- Totales.
- Exportaciones.

### POST `/closing-periods`

Crear cierre.

Prioridad:

```text
P1 controlado
```

En MVP puede ser principalmente web.

---

## 12.8 Period Reports — Reportes de periodo

### GET `/period-reports`

Consulta reportes individuales.

Alcance requerido:

```text
reports:read
```

Filtros:

```text
closing_period_id
worker_id
employee_code
status
```

### GET `/period-reports/{report_id}`

Consulta reporte individual.

Respuesta:

```json
{
  "data": {
    "id": "01JZ7XRPT...",
    "worker": {
      "employee_code": "000123",
      "full_name": "Juan Pérez López"
    },
    "period": {
      "start": "2026-09-15",
      "end": "2026-09-21"
    },
    "status": "available",
    "active_version": {
      "id": "01JZ7XVER...",
      "version": 1,
      "hash": "sha256:...",
      "summary": {
        "ordinary_minutes": 2400,
        "overtime_minutes": 120,
        "alerts_count": 2
      }
    }
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### POST `/period-reports/{report_id}/confirm`

Confirmar conformidad o no conformidad.

Prioridad:

```text
P1 controlado
```

En MVP, la confirmación principal ocurre desde el portal trabajador. La API se prepara para futura app móvil.

---

## 12.9 Imports — Importaciones

### POST `/imports`

Crea lote de importación.

Alcance requerido:

```text
imports:write
```

Tipos:

```text
workers
schedules
time_events
```

Para archivos reales, el endpoint podrá usar `multipart/form-data` o flujo de subida definido por storage.

Request conceptual JSON:

```json
{
  "type": "time_events",
  "source": "csv",
  "file_id": "01JZ7XFILE..."
}
```

Respuesta:

```http
202 Accepted
```

```json
{
  "data": {
    "id": "01JZ7XIMP...",
    "type": "time_events",
    "status": "queued"
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/imports/{import_id}`

Consulta estado del lote.

### GET `/imports/{import_id}/rows`

Consulta errores o resultados por fila.

---

## 12.10 Exports — Exportaciones

### POST `/exports/payroll`

Genera exportación de prenómina.

Alcance requerido:

```text
exports:write
```

Prioridad:

```text
P0/P1 alto
```

Request:

```json
{
  "closing_period_id": "01JZ7XPER...",
  "center_id": "01JZ7X8KZ...",
  "format": "xlsx",
  "concepts": [
    "ordinary_hours",
    "overtime",
    "sunday_work",
    "mandatory_rest_work",
    "incidents"
  ]
}
```

Respuesta:

```http
202 Accepted
```

```json
{
  "data": {
    "id": "01JZ7XEXP...",
    "status": "queued"
  },
  "meta": {
    "trace_id": "trc_01JZ7X..."
  }
}
```

### GET `/exports/{export_id}`

Consulta estado y archivo.

---

## 12.11 Integration Logs — Logs de integración

### GET `/integration-logs`

Consulta logs técnicos.

Alcance requerido:

```text
integrations:logs
```

Filtros:

```text
operation
status
date_from
date_to
trace_id
```

Respuesta:

```json
{
  "data": [
    {
      "id": "01JZ7XLOG...",
      "operation": "time-events.create",
      "status": "success",
      "direction": "inbound",
      "trace_id": "trc_01JZ7X...",
      "created_at": "2026-09-15T08:00:01-06:00"
    }
  ]
}
```

---

# 13. Endpoints administrativos P1

Salvo las consultas de centros documentadas a continuación, estos endpoints se diseñan, pero no necesariamente se liberan en el MVP público.

## 13.1 Centers — consulta disponible

```http
GET    /centers
GET    /centers/{center_id}
```

Los dos endpoints de consulta están disponibles bajo `/api/v1/time` y requieren:

```text
centers:read
```

También requieren token Bearer ligado a empresa, producto `time` operativo, usuario, membresía y empresa activos y rol administrativo de tenant (`super_admin`, `admin_empresa` o `rh_admin`). El token se emite desde la pantalla web **Credenciales API** (`/time/api-tokens`), donde `centers:read` aparece como capacidad seleccionable.

### GET `/centers`

Lista los centros de la empresa resuelta por el token. Es paginado, ordena por nombre y admite filtros opcionales:

```text
search       texto para nombre o código
status       estado del centro
per_page     entero entre 1 y 100; predeterminado 25
```

La Policy `viewAny` autoriza la consulta dentro del tenant. La respuesta incluye `data`, `meta.current_page`, `meta.per_page`, `meta.total`, `meta.trace_id` y enlaces `first`, `last`, `prev` y `next`, conservando los filtros de la solicitud.

Cada elemento usa `CenterResource` y expone únicamente `id`, `code`, `name`, `timezone`, `status` y `address`; no expone el contexto interno de otra empresa ni información de credenciales.

### GET `/centers/{centerId}`

Consulta un centro de la empresa del token. El recurso se busca desde la relación de la empresa y se autoriza mediante la Policy `view`; un identificador inexistente o perteneciente a otro tenant responde `404 Not Found` sin revelar su existencia. Devuelve el mismo `CenterResource`, `meta.trace_id` y el encabezado `X-Trace-Id`.

Ambas rutas son exclusivamente de lectura: no crean, actualizan, suspenden ni eliminan centros. Esas operaciones permanecen fuera de este contrato API actual.

## 13.2 WFM scheduling

```http
GET    /shift-templates
POST   /shift-templates
GET    /schedule-profiles
POST   /schedule-profiles
POST   /schedule-profile-assignments
POST   /schedule-batches
POST   /schedule-batches/{batch_id}/import
POST   /schedule-batches/{batch_id}/publish
GET    /daily-schedule-assignments
```

Los endpoints legacy `/schedules` y `/schedule-assignments` pertenecen al modelo Sprint 2A/2B y deberan reemplazarse cuando se implemente la programacion diaria publicada.

Nota WFM F5B: el nucleo interno ya cuenta con batches, asignaciones diarias, segmentos, snapshots canonicos, resolucion de programacion publicada, generacion interna de borradores desde perfiles, publicacion atomica de batches completos desde dominio, interfaz web `/scheduling/daily`, correcciones versionadas e importacion CSV web a lotes `draft`. No existe todavia API funcional para administrar perfiles avanzados, disparar generacion, publicar programacion diaria, cargar calendarios por endpoint ni crear activaciones bajo demanda.

## 13.3 Evidence Packages

```http
POST   /evidence-packages
GET    /evidence-packages
GET    /evidence-packages/{package_id}
```

## 13.3.1 Closing profiles

```http
GET    /closing-period-profiles
POST   /closing-period-profiles
POST   /closing-profile-assignments
POST   /closing-periods/generate
GET    /closing-periods
GET    /closing-periods/{period_id}/members
```

La API debera mostrar el perfil efectivo y su origen: empresa, centro, unidad organizacional o relacion laboral.

## 13.4 API Tokens

La administración de tokens podrá iniciar desde interfaz web.

Endpoints futuros:

```http
GET    /api-tokens
POST   /api-tokens
DELETE /api-tokens/{token_id}
```

---

# 14. Webhooks futuros

No son P0.

Fase posterior:

```text
work_day.calculated
alert.created
incident.created
period_report.available
period_report.confirmed
export.ready
```

Reglas futuras:

- Firma HMAC.
- Reintentos.
- Logs.
- Secret por empresa.
- Desactivación por fallos.

---

# 15. Seguridad

## 15.1 Reglas mínimas

- HTTPS obligatorio.
- Tokens Bearer.
- Alcances por token.
- Rate limiting.
- Validación estricta.
- Contexto de empresa por token.
- Auditoría de operaciones sensibles.
- Logs de integración.
- No exponer secretos.
- No devolver datos de otra empresa.
- No exponer campos internos innecesarios.

## 15.2 Datos sensibles

La API deberá limitar datos personales y evidencia según el alcance del token.

Ejemplo:

Un token de reloj checador no necesita consultar reportes ni evidencias.

---

# 16. Auditoría API

Operaciones auditables:

- Crear trabajador.
- Actualizar trabajador.
- Crear relación laboral.
- Registrar evento.
- Crear incidencia.
- Proponer corrección.
- Aprobar corrección.
- Generar exportación.
- Consultar expediente.
- Revocar token.
- Fallos repetidos.

Cada operación deberá registrar:

- Empresa.
- Token o actor.
- Operación.
- Entidad.
- IP.
- User-Agent.
- Trace ID.
- Resultado.
- Fecha.

---

# 17. Procesos asíncronos

Usan colas por base de datos en el MVP.

Procesos asíncronos:

- Importaciones.
- Recalculos masivos.
- Generación de reportes.
- Exportaciones.
- Expedientes.
- Notificaciones.
- Sincronizaciones futuras.

Respuesta estándar:

```http
202 Accepted
```

con estado consultable:

```http
GET /api/v1/time/imports/{id}
GET /api/v1/time/exports/{id}
```

---

# 18. ClickBalance

## 18.1 Decisión MVP

Para ClickBalance se priorizará exportación compatible por archivo.

La API directa queda como P1 condicionado.

## 18.2 API futura

La integración futura deberá usar servicios del dominio, no lógica separada.

Flujo:

```text
ClickBalance / Sistema externo
→ Vera Time API
→ Actions/Services
→ Motor legal / Reportes
→ Exportación o API de salida
→ ClickBalance / Sistema externo
```

## 18.3 Datos necesarios

Antes de desarrollar API directa se debe confirmar:

- Documentación.
- Credenciales.
- Ambiente de pruebas.
- Empleados.
- Periodos.
- Conceptos.
- Movimientos.
- Formatos.
- Manejo de errores.

---

# 19. Estructura sugerida en Laravel

## 19.1 Rutas

```text
routes/api.php
```

Grupos:

```php
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'api.tenant', 'throttle:api'])
    ->group(function () {
        // endpoints
    });
```

## 19.2 Controladores

Controladores delgados:

```text
app/Http/Controllers/Api/V1/
```

Ejemplos:

```text
WorkerController
TimeEventController
WorkDayController
AlertController
IncidentController
PeriodReportController
ImportController
ExportController
IntegrationLogController
```

## 19.3 Requests

Validaciones:

```text
app/Http/Requests/Api/V1/
```

## 19.4 Resources

Transformación de respuesta:

```text
app/Http/Resources/Api/V1/
```

## 19.5 Actions/Services

Toda lógica va a:

```text
app/Domains/*/Actions
app/Domains/*/Services
```

Ejemplos:

```text
CreateWorkerAction
UpdateWorkerAction
RegisterTimeEventAction
RecalculateWorkDayAction
GeneratePreventiveAlertsAction
CreateIncidentAction
GeneratePayrollExportAction
```

---

# 20. Pruebas API

## 20.1 Pruebas P0

- Token válido.
- Token inválido.
- Token sin alcance.
- No acceso entre empresas.
- Crear trabajador.
- Crear evento.
- Idempotencia de evento.
- Consultar jornada.
- Consultar alerta.
- Crear incidencia.
- Exportación asíncrona.
- Rate limit.
- Vínculo explícito usuario-trabajador por empresa.
- Token personal sin vínculo, vínculo inactivo o vínculo de otra empresa recibe `403` sin filtrar información.
- Consulta personal de eventos y jornadas devuelve sólo el trabajador vinculado, aunque el cliente intente enviar otro identificador.
- Revocación independiente de vínculo, membresía y token personal.
- Errores de validación.
- Logs de integración.

## 20.2 Prueba crítica multi-tenant

Escenario:

```text
Empresa A tiene trabajador A.
Empresa B tiene token B.

Token B intenta consultar trabajador A.
Resultado esperado: 404 o 403.
```

No debe revelar si el recurso existe en otra empresa.

---

# 21. Priorización final API

## P0

```text
GET    /centers
GET    /centers/{id}

GET    /workers
POST   /workers
GET    /workers/{id}
PATCH  /workers/{id}

GET    /workers/{id}/relationships

POST   /time-events
GET    /time-events
GET    /time-events/{id}
POST   /time-events/{id}/void
POST   /time-events/{id}/approve
POST   /time-events/{id}/reject

GET    /work-days
GET    /work-days/{id}

POST   /auth/login
POST   /auth/forgot-password
GET    /me
GET    /me/alerts
GET    /me/schedule
GET    /me/time-events
POST   /me/time-events
POST   /me/time-events/sync
GET    /me/time-events/{id}
GET    /me/work-days
GET    /me/work-days/{id}

GET    /alerts
GET    /alerts/{id}

GET    /attendance-incidents
POST   /attendance-incidents
GET    /attendance-incidents/{id}
POST   /attendance-incidents/{id}/cancel

GET    /closing-periods
GET    /closing-periods/{id}

GET    /period-reports
GET    /period-reports/{id}

POST   /imports
GET    /imports/{id}
GET    /imports/{id}/rows

POST   /exports/payroll
GET    /exports/{id}

GET    /integration-logs
```

## P1 controlado

```text
POST   /centers
PATCH  /centers/{id}

POST   /shift-templates
PATCH  /shift-templates/{id}
POST   /schedule-profiles
PATCH  /schedule-profiles/{id}
POST   /schedule-profile-assignments
POST   /schedule-batches
POST   /schedule-batches/{id}/import
POST   /schedule-batches/{id}/publish
GET    /daily-schedule-assignments

POST   /alerts/{id}/resolve
POST   /incidents/{id}/corrections
POST   /corrections/{id}/approve
POST   /work-days/{id}/recalculate

POST   /period-reports/{id}/confirm
POST   /evidence-packages
GET    /evidence-packages/{id}

Webhooks
ClickBalance API directa
```

Nota WFM: CSV/XLSX/API de programacion por calendario deberan crear batches en `draft` para revision. Ningun endpoint de importacion publica automaticamente programacion diaria.

Cada `schedule_batch` pertenece obligatoriamente a una empresa, un centro y un rango de fechas. Una operacion de empresa completa debe crear un batch por centro.

La publicacion API debe generar version consecutiva por centro y periodo, snapshot JSON canonico, hash SHA-256, `published_by` y `published_at`. Una correccion no edita una publicacion existente: crea nueva version y deja la anterior `superseded`.

---

# 22. Criterios de aceptación

La API del MVP se considera aceptada cuando:

1. Usa `/api/v1/time`.
2. Requiere token Bearer.
3. Resuelve empresa por token.
4. Aplica alcances por token.
5. Impide acceso cruzado entre empresas.
6. Permite crear y consultar trabajadores.
7. Permite registrar eventos de jornada.
8. Soporta idempotencia en eventos.
9. Dispara el mismo flujo de cálculo que la interfaz.
10. Permite consultar jornadas calculadas.
11. Permite consultar alertas.
12. Permite crear incidencias.
13. Permite consultar reportes de periodo.
14. Permite generar importaciones/exportaciones asíncronas.
15. Devuelve errores estándar.
16. Registra logs de integración.
17. Registra auditoría en operaciones sensibles.
18. No duplica lógica de negocio.
19. Puede convivir con Livewire, CSV, jobs e integraciones.
20. Está preparada para ClickBalance y futuras integraciones.

---

# 23. Siguiente documento

Después de aprobar esta especificación API, el siguiente documento recomendado será:

```text
docs/09-Testing/TEST-0001-ESTRATEGIA-DE-PRUEBAS-MVP.md
```

Ahí se definirán:

- Pruebas funcionales.
- Pruebas legales del motor.
- Pruebas multi-tenant.
- Pruebas API.
- Pruebas de seguridad.
- Pruebas de cierre y conformidad.
- Pruebas de importaciones/exportaciones.
- Criterios mínimos para piloto.

---

## Nota Bloque F4

F4 implementa correcciones versionadas desde dominio e interfaz web. No agrega endpoints API WFM. Cuando se exponga por API, debera reutilizar las mismas Actions de dominio: crear correccion, comparar versiones, validar y publicar correccion.

---

## Decision MVP - identidad humana, movil y kiosco

Una cuenta humana de `users` es la identidad unica para el portal web y el cliente movil responsive/PWA. Al autenticarse para API, el token personal conserva el usuario, la empresa autorizada y sus scopes. Si tiene membresias en varias empresas, la empresa se selecciona por un flujo autorizado antes de emitir o renovar el token; nunca por un `company_id` libre enviado en cada solicitud. Este flujo personal opera separado del grupo operativo administrativo actual.

La vinculacion entre la cuenta y `workers` es explicita y acotada por empresa. En esta fase permite consultar solo eventos y jornadas propios; horarios, incidencias y reportes personales quedan para incrementos posteriores autorizados. Revocar un token movil no desactiva automaticamente la cuenta web ni la relacion laboral.

El kiosco es un canal distinto: cada terminal se autoriza mediante QR o código temporal y después usa una credencial de marcaje código/NIP ligada al trabajador y, cuando exista, a la misma cuenta humana. El NIP se conserva hasheado y la contraseña principal nunca se captura ni se reutiliza en la terminal. Un kiosco solo puede identificar y registrar los eventos permitidos; no obtiene una sesión de portal ni acceso a datos personales, incidencias, reportes o administración.

El cliente Android/PWA usará este mismo contrato API y no una autenticación paralela. La implementación de la aplicación permanece separada del backend.

## Convencion de rutas por producto

La API publica se versiona antes de segmentarse por producto. Vera Time usara `/api/v1/time/...`; Payroll y HR reservaran `/api/v1/payroll/...` y `/api/v1/hr/...`. El prefijo identifica el producto, pero no sustituye autenticacion, scopes ni resolucion de empresa por token.

La interfaz web sigue la misma convencion: `/time/...`, `/payroll/...` y `/hr/...`. Login, seleccion de empresa y administracion SaaS son rutas globales y no pertenecen a un producto.
