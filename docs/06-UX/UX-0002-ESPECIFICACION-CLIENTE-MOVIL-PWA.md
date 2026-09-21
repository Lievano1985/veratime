---
id: UX-0002
title: Especificación del cliente móvil PWA
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-09-20
updated: 2026-09-20
tags:
  - ux
  - pwa
  - movil
  - api
  - veratime
---

# UX-0002 — Especificación del cliente móvil PWA

## 1. Propósito

Definir el alcance funcional básico del cliente móvil responsive/PWA de Vera Time para la persona trabajadora. El cliente usa la API personal existente y el mismo dominio de jornada que los canales web, administrativo y kiosco.

No es una aplicación nativa iOS/Android. Es una experiencia web instalable cuando el navegador lo permita, orientada a consultar el contexto propio y registrar eventos de jornada con trazabilidad, aislamiento por empresa e idempotencia.

El objetivo del incremento es:

```text
consulta propia + marcaje confiable + tolerancia básica a conectividad intermitente
```

Estado del incremento: los contratos API personales descritos en este documento están disponibles. La interfaz PWA instalable, su manifiesto, service worker y almacenamiento local visual aún deben implementarse en el cliente; esta especificación define su alcance básico para que consuma dichos contratos sin crear reglas de negocio paralelas.

## 2. Personas y canales

La persona trabajadora usa una cuenta humana `users` vinculada explícitamente a un único `workers` por empresa mediante `user_worker_links`. Una misma persona puede tener vínculos en distintas empresas, pero cada token móvil representa una sola empresa y un solo trabajador.

El cliente PWA no sustituye al kiosco:

| Canal | Credencial | Uso |
|---|---|---|
| PWA personal | Correo/contraseña y Bearer token personal | Consultar y registrar la propia jornada |
| Kiosco | Código/NIP de trabajador | Marcaje compartido, sin sesión de portal |
| Portal web | Sesión web | Consultas y gestiones disponibles en la interfaz web |

La contraseña principal nunca se reutiliza como NIP de kiosco.

## 3. Alcance básico disponible

El cliente PWA básico utiliza los siguientes contratos. Todos incluyen `trace_id` en respuesta cuando aplica.

| Flujo | Endpoint | Resultado esperado |
|---|---|---|
| Inicio de sesión | `POST /api/v1/time/auth/login` | Token `pwa-personal`, scopes y contexto inicial |
| Selección de empresa | Reintento de `POST /auth/login` con `company_id` | Token de la empresa elegida cuando la cuenta tiene más de una empresa elegible |
| Recuperación | `POST /api/v1/time/auth/forgot-password` | Instrucciones de restablecimiento sin revelar si el correo existe |
| Contexto actualizado | `GET /api/v1/time/me` | Empresa, persona trabajadora, estado actual, acciones permitidas y jornada de hoy |
| Marcaje en línea | `POST /api/v1/time/me/time-events` | Registro individual idempotente |
| Sincronización pendiente | `POST /api/v1/time/me/time-events/sync` | Resultado individual de uno a 25 eventos locales |
| Horario | `GET /api/v1/time/me/schedule` | Programación publicada de la relación laboral activa |
| Alertas | `GET /api/v1/time/me/alerts` | Alertas propias paginadas |
| Cierre de sesión | `DELETE /api/v1/time/me/access-token` | Revoca sólo el token Bearer actual |

Las rutas bajo `/api/v1/time/me` requieren `self:read`. El marcaje individual y la sincronización requieren adicionalmente `self:write`.

## 4. Flujos de experiencia

### 4.1 Inicio de sesión y selección de empresa

La pantalla solicita correo y contraseña. Si la cuenta tiene una sola empresa elegible, recibe `201 Created`, el Bearer token personal y el contexto inicial.

Cuando la cuenta tiene varias empresas elegibles, la API responde `409 Conflict` con `code: company_selection_required` y una lista limitada a `id` y `name`. La PWA muestra esas opciones y repite el inicio de sesión con el `company_id` seleccionado. No debe almacenar ni permitir escribir un identificador de otra empresa.

Una empresa es elegible sólo si cuenta, empresa, producto VERA Time, vínculo usuario-trabajador y trabajador están activos. El login y la recuperación están limitados a cinco solicitudes por minuto por combinación de correo normalizado e IP.

### 4.2 Recuperación de contraseña

La pantalla de recuperación solicita sólo el correo y llama `POST /api/v1/time/auth/forgot-password`. Siempre muestra el mismo mensaje de confirmación y la API responde `202 Accepted`, exista o no la cuenta; esto evita enumeración de cuentas.

La PWA no restablece contraseñas localmente. La persona continúa con las instrucciones y flujo de restablecimiento emitidos por el sistema.

### 4.3 Contexto y jornada actual

Después de autenticar y al volver a primer plano, la PWA consulta `GET /api/v1/time/me`. Debe mostrar:

- empresa y zona horaria del token;
- persona trabajadora vinculada;
- estado actual de marcaje (`sin_entrada`, `trabajando`, `en_pausa` o `jornada_cerrada`);
- acciones permitidas, fecha y zona horaria local, y último evento cuando exista;
- jornada actual y cálculo disponible, si ya existe.

La interfaz debe ofrecer únicamente las acciones permitidas por `current_time_record.allowed_actions`. El cierre de jornada se registra como un evento `clock_out`; no es una corrección ni elimina registros anteriores.

### 4.4 Marcaje individual en línea

Para entrada, salida, inicio o fin de pausa, la PWA envía `event_type`, `occurred_at`, zona horaria opcional, metadatos opcionales y datos opcionales del dispositivo a `POST /api/v1/time/me/time-events`.

Cada envío individual exige el encabezado `Idempotency-Key`. La PWA debe generar una llave estable por intento de marcaje, conservarla mientras reintenta y no crear una nueva por un error de red. La primera respuesta válida es `201` con `idempotent_replay: false`; un reintento de la misma llave devuelve el mismo evento con `200` e `idempotent_replay: true`.

La PWA no envía ni puede seleccionar `company_id`, `worker_id`, `employee_code`, `center_id`, `user_id`, `external_id`, `source` o `source_user_id`. El servidor resuelve el tenant, el trabajador, la relación y el centro; fija `source` a `pwa` y conserva el usuario origen.

### 4.5 Sincronización offline básica

Cuando no hay conexión, la PWA puede conservar una cola local persistente de eventos pendientes. Al recuperar conectividad, envía de uno a 25 eventos en cada llamada a `POST /api/v1/time/me/time-events/sync`.

Cada elemento requiere `client_event_id`, `event_type` y `occurred_at`; puede incluir `timezone`, `metadata` y `device`. `client_event_id` es único estrictamente dentro del lote, tiene un máximo de 255 caracteres y se convierte en la llave de idempotencia del evento. En la sincronización no se envía un encabezado `Idempotency-Key` por elemento: la equivalencia la establece `client_event_id`.

La PWA debe interpretar el resultado por evento y actualizar su cola así:

| Estado | Significado | Acción de la PWA |
|---|---|---|
| `accepted` | El servidor registró el evento por primera vez. | Marcar como sincronizado y conservar el identificador de servidor si se requiere para la interfaz. |
| `already_registered` | El mismo `client_event_id` ya había sido aceptado para ese usuario, trabajador y empresa. | Marcar como sincronizado; no reenviar como un nuevo marcaje. |
| `rejected` | El dominio rechazó ese evento; la respuesta incluye `error`. | Conservarlo como rechazado para informar a la persona; no reintentar automáticamente sin una nueva acción válida. |

Los errores estructurales del lote —por ejemplo, cero eventos, más de 25, identificadores repetidos, fecha/tipo inválidos o campos de contexto prohibidos— responden `422` y deben dejar la cola local sin marcar como sincronizada. Un resultado `200` puede contener una mezcla de `accepted`, `already_registered` y `rejected`; los contadores `meta.accepted`, `meta.already_registered` y `meta.rejected` permiten mostrar el resumen.

La sincronización no permite enviar en el lote ni en sus eventos `company_id`, `worker_id`, `employee_code`, `center_id`, `user_id` o `external_id`. Cualquier intento se rechaza con `422`; no existe sincronización entre empresas o para otras personas trabajadoras.

### 4.6 Horario y alertas

La pantalla de horario consulta programación publicada de la relación laboral activa mediante `GET /api/v1/time/me/schedule`. Puede filtrar por fechas, hasta 31 días, y no muestra borradores.

La pantalla de alertas usa `GET /api/v1/time/me/alerts`. Muestra únicamente alertas propias, paginadas, y por defecto las de estado abierto. Es una vista informativa: no permite asignar, resolver, corregir ni cerrar alertas.

### 4.7 Cierre de sesión

La acción «Cerrar sesión» llama `DELETE /api/v1/time/me/access-token`, elimina el token local y confirma el cierre. Sólo revoca la credencial presentada; no desactiva la cuenta, el vínculo usuario-trabajador, el kiosco ni el historial laboral.

## 5. Estados visibles y mensajes

La PWA debe distinguir con claridad:

- cargando contexto, sin conexión y sincronización pendiente;
- marcaje enviado, reintento idempotente, sincronizado y rechazado;
- selección de empresa requerida (`409`), credenciales inválidas (`422`), vínculo no disponible (`403`) y token revocado o inválido (`403`);
- ausencia de horario publicado, de jornada actual o de alertas abiertas sin presentarlo como error;
- recuperación solicitada sin confirmar la existencia de una cuenta.

Los mensajes de alertas se mantienen neutrales, por ejemplo «Situación pendiente de revisión», y no declaran infracciones ni conclusiones legales.

## 6. Seguridad y aislamiento

- El token está ligado a una empresa. La PWA debe tratar el cambio de empresa como una nueva autenticación y nuevo token.
- Las rutas personales autenticadas están limitadas a 60 solicitudes por minuto por token y empresa. La PWA debe evitar sondeos innecesarios y conservar el `trace_id` al reportar una incidencia técnica.
- La API deriva empresa desde el token y trabajador desde `user_worker_links`; la PWA nunca infiere ni sobreescribe ese contexto.
- El secreto Bearer se muestra sólo al emitirse y no debe escribirse en logs, enviarse a terceros ni exponerse en la interfaz.
- El contexto y las consultas personales dejan de estar disponibles si se revoca el token o vínculo, se inactiva el usuario/trabajador/empresa o el producto VERA Time deja de ser operativo.
- Los reintentos de red reutilizan la misma llave de idempotencia. La PWA no debe duplicar marcajes para compensar incertidumbre de conectividad.

## 7. Límites del alcance básico

Implementado en este alcance:

- autenticación, selección de empresa y recuperación de contraseña;
- contexto propio, marcaje individual, cola offline básica y sincronización por lote;
- horario publicado, alertas propias y cierre de la sesión API actual.

Pospuesto explícitamente:

- correcciones de jornada e incidencias personales;
- reportes personales, conformidad digital y solicitudes de aclaración;
- notificaciones push, correo o recordatorios de marcaje;
- listado, revocación remota, renovación o administración avanzada de sesiones/dispositivos;
- biometría, reconocimiento facial, huella digital y geolocalización;
- aplicación nativa iOS/Android;
- administración de empresa, personas trabajadoras, centros, horarios o alertas.

## 8. Criterios básicos de aceptación

- Una persona vinculada puede iniciar sesión y sólo ve la empresa y trabajador derivados por servidor.
- Una persona con varias empresas elegibles debe elegir una antes de recibir un token.
- La recuperación no revela si existe la cuenta.
- La PWA muestra las acciones de marcaje permitidas por el contexto actual.
- Un marcaje individual reintentado conserva su `Idempotency-Key` y no duplica el evento.
- La sincronización acepta hasta 25 eventos, informa el resultado de cada uno y no permite contexto ajeno.
- El horario sólo muestra programación publicada de la relación activa y las alertas sólo pertenecen al trabajador vinculado.
- Cerrar sesión revoca únicamente el token móvil actual.
- No se presenta biometría, geolocalización ni app nativa como funcionalidad disponible.
