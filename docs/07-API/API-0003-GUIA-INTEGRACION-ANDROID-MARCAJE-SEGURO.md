---
id: API-0003
title: Guia unica de integracion Android - marcaje seguro y offline
project: Vera Time
version: 1.1.0
status: Ready for integration
owner: Product Architecture
created: 2026-10-06
updated: 2026-10-06
tags: [android, movil, geolocalizacion, vinculacion-dispositivo, offline, api]
---

# API-0003 - Guia unica de integracion Android: vinculacion, ubicacion y jornada offline

## 1. Proposito y limite de confianza

Este es el contrato unico para Android. Cubre autenticacion, vinculacion de clave, ubicacion, marcaje en linea y jornada offline.

```text
https://gotvera.com/api/v1/time
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

El backend resuelve empresa, trabajador, centro y politica desde el token. La app nunca envia `company_id`, `worker_id`, `user_id`, `center_id`, `source` o `source_user_id`.

La hora modificable del telefono no es una prueba suficiente. Para offline, Vera relaciona la hora UTC emitida por servidor con `SystemClock.elapsedRealtime()` de Android. La hora declarada por Android se conserva como evidencia, pero solo se acepta automaticamente si coincide con esa referencia monotona dentro de la tolerancia que emite el backend.

## 2. Preparacion en Vera Time

Antes de habilitar offline, RH debe confirmar:

1. La cuenta esta vinculada a su trabajador activo en **Usuarios > Vinculo con trabajador**.
2. La empresa y Vera Time estan activos.
3. Existe politica activa en **Configuracion de empresa > Marcaje movil**.
4. La politica requiere dispositivo autorizado y tiene una **Vigencia de autorizacion offline**. Vacio significa que offline esta deshabilitado.
5. RH autorizo y el trabajador completo el vinculo del dispositivo.

La vigencia la define el backend por politica y puede cubrir turnos nocturnos. Android no debe suponer horas: siempre usa `expires_at`.

## 3. Inicio de sesion, contexto y politica

```http
POST /auth/login
```

```json
{"email":"persona@empresa.com","password":"contrasena-del-usuario","company_id":3}
```

`company_id` solo se envia al iniciar sesion si la API pide seleccionar empresa. La respuesta `201` entrega una vez el token con `self:read` y `self:write`. Guardarlo solo en almacenamiento seguro, nunca en logs, analytics o preferencias sin cifrar.

Al abrir la app consultar:

```http
GET /me
GET /me/marking-security
GET /me/device-bindings
```

`GET /me` contiene las acciones permitidas de la jornada. `GET /me/marking-security` mantiene la `time_reference` de cinco minutos para marcaje **en linea**; no sirve para una jornada offline.

Ejemplo relevante de politica:

```json
{
  "data": {
    "policy": {
      "id": "2f7c...",
      "version": 4,
      "mode": "circle",
      "device_binding_required": true,
      "biometric_required": true,
      "radius_meters": 150,
      "max_accuracy_meters": 25,
      "max_location_age_seconds": 60,
      "offline_authorization_duration_minutes": 720
    },
    "time_reference": {"id":"...","server_time":"2026-10-06T15:00:00Z","expires_at":"2026-10-06T15:05:00Z"}
  }
}
```

## 4. Vinculacion del dispositivo

RH entrega un codigo de un solo uso. Android genera una clave P-256/secp256r1 en Android Keystore; la privada no sale del equipo. Si aplica, configurar la clave para requerir desbloqueo biometric local antes de firmar.

```http
POST /me/device-binding/challenge
{"authorization_code":"codigo-de-24-caracteres","device_name":"Telefono de Eduardo"}

POST /me/device-binding/complete
{"authorization_id":"uuid","public_key_spki":"base64url-sin-igual","signature":"firma-der-base64url"}
```

Firmar literalmente el `payload` del desafio con `SHA256withECDSA`. Las firmas son DER codificadas Base64URL sin `=`. Vera no recibe huellas, rostro, IMEI, MAC ni la clave privada. Guardar solo `binding.id`, alias del Keystore y nombre de UI. No persistir codigo RH ni desafio.

## 5. Marcaje en linea

Para conectividad inmediata se conserva el contrato:

```http
POST /me/time-events
Idempotency-Key: {uuid-nuevo}
```

Si hay politica activa, usar `security.time_reference_id` vigente. Si exige vinculo, firmar este texto UTF-8 separado solo por `\n`:

```text
VERA-MOBILE-EVENT-V1
{idempotency_key o client_event_id}
{event_type}
{occurred_at exacto}
{timezone o vacio}
{binding_id}
{policy.id}
{policy.version}
{time_reference.id}
{latitude o vacio}
{longitude o vacio}
{accuracy_meters o vacio}
{location.captured_at o vacio}
{1 si is_mocked; 0 si no}
```

Las coordenadas, precision y timestamps se mandan como los textos exactos que se firmaron. `is_mocked: true`, radio invalido, precision insuficiente o ubicacion antigua no se aceptan automaticamente.

## 6. Autorizacion de jornada offline

Solicitar solo con red, vinculo activo y el valor actual de Android:

```http
POST /me/offline-marking-authorizations
```

```json
{
  "binding_id": "uuid-del-vinculo-activo",
  "issued_monotonic_milliseconds": 124581230
}
```

`issued_monotonic_milliseconds` es el valor exacto de `SystemClock.elapsedRealtime()` tomado inmediatamente antes de enviar. No es `currentTimeMillis()`. No se reinicia al apagar pantalla, pero si al reiniciar el equipo.

Respuesta `201`:

```json
{
  "data": {
    "id": "uuid-autorizacion-offline",
    "status": "active",
    "issued_at": "2026-10-06T12:00:00Z",
    "expires_at": "2026-10-07T00:00:00Z",
    "issued_monotonic_milliseconds": 124581230,
    "max_clock_drift_seconds": 120,
    "policy": {"id":"uuid-politica","version":4},
    "binding_id": "uuid-del-vinculo-activo",
    "signature_version": "VERA-MOBILE-OFFLINE-EVENT-V1"
  },
  "meta": {"trace_id":"trc_x"}
}
```

La app almacena cifrados e inmutables la respuesta, UUID, hora ISO, zona, valor monotono, ubicacion, firma y payload. Una autorizacion puede cubrir entrada, pausa, regreso y salida hasta `expires_at`.

## 7. Captura offline y firma

En cada captura tomar juntos: `elapsedRealtime()`, hora UTC ISO-8601, zona horaria, ubicacion si aplica y firma. El UUID se crea una vez. No volver a generar firma, UUID, hora o ubicacion al sincronizar.

Firmar exactamente:

```text
VERA-MOBILE-OFFLINE-EVENT-V1
{client_event_id}
{event_type}
{occurred_at exacto}
{timezone o vacio}
{binding_id}
{policy_id}
{policy_version}
{offline_authorization_id}
{monotonic_elapsed_milliseconds}
{latitude o vacio}
{longitude o vacio}
{accuracy_meters o vacio}
{location.captured_at o vacio}
{1 si is_mocked; 0 si no}
```

Ejemplo de fila inmutable:

```json
{
  "client_event_id":"8e88b6de-1a6c-4eab-b895-7f63d1a59d54",
  "event_type":"clock_in",
  "occurred_at":"2026-10-06T12:01:08Z",
  "timezone":"America/Mexico_City",
  "security": {
    "offline_authorization_id":"uuid-autorizacion-offline",
    "binding_id":"uuid-del-vinculo",
    "policy_id":"uuid-politica",
    "policy_version":4,
    "monotonic_elapsed_milliseconds":124649230,
    "signature":"firma-der-base64url",
    "location": {
      "latitude":"19.4326080",
      "longitude":"-99.1332090",
      "accuracy_meters":"8.50",
      "captured_at":"2026-10-06T12:01:06Z",
      "is_mocked":false
    }
  }
}
```

## 8. Sincronizacion de la cola

```http
POST /me/time-events/sync
```

Enviar de 1 a 25 filas originales. El backend calcula:

```text
hora_estimada = issued_at + (monotonic_capture - monotonic_emitido)
```

Valida autorizacion, vinculo, politica y ubicacion que aplicaban **al momento estimado de captura**. No exige que la autorizacion siga vigente al recibir el lote. Una jornada cerrada offline puede sincronizarse despues de `expires_at` si cada captura ocurrio antes.

Respuesta de jornada aceptada:

```json
{
  "data": [
    {"client_event_id":"...in","status":"accepted","event":{"id":501}},
    {"client_event_id":"...pause","status":"accepted","event":{"id":502}},
    {"client_event_id":"...return","status":"accepted","event":{"id":503}},
    {"client_event_id":"...out","status":"accepted","event":{"id":504}}
  ],
  "meta":{"accepted":4,"already_registered":0,"pending_review":0,"trace_id":"trc_x"}
}
```

Reenviar el mismo lote devuelve `already_registered`; no crea duplicados.

## 9. Casos limite obligatorios

| Situacion | Accion Android | Resultado backend |
|---|---|---|
| Equipo reiniciado | No mezclar nuevas capturas bajo la autorizacion anterior; pedir red y nueva autorizacion. | La fila se conserva `pending_review` con `monotonic_restarted`; no crea asistencia. |
| Captura despues de vencimiento | No generar una nueva captura offline; conservar para RH o recuperar red. | `pending_review` / `offline_authorization_expired_at_capture`. |
| Autorizacion vencida al enviar | Enviar exactamente la fila original. | Se acepta si fue capturada antes de vencer. |
| Cambio de zona | No reconstruir `occurred_at` ni `timezone`; ambos ya estan firmados. | Se valida el instante UTC y se conserva zona original. |
| Vinculo o politica revocados | Detener nuevas capturas, conservar las existentes. | Captura anterior a revocacion puede aceptarse; posterior queda `pending_review`. |
| Hora manual alterada | No corregir localmente. | Si excede `max_clock_drift_seconds`, `pending_review` / `clock_drift_exceeded`. |
| Firma, autorizacion o ubicacion no verificable | No re-firmar ni modificar. | Evidencia preservada y `pending_review`, sin `time_event`. |
| Marcaje asistido en mismo instante | No eliminar evidencia offline. | `pending_review` / `assisted_marking_exists`, sin doble asistencia automatica. |

`pending_review` significa recepcion y conservacion segura, no aceptacion de asistencia. El servidor preserva UUID, firma, tiempos, ubicacion y referencias originales. La app mantiene la fila cifrada sin sustituirla.

## 10. Estados por fila

| Estado | Accion local |
|---|---|
| `accepted` | Entregado; puede archivarse segun politica local. |
| `already_registered` | Entregado: mismo UUID y mismo contenido. |
| `pending_review` | Conservar inmutable para RH; no reintentar cambiando datos. |
| `conflict` | Conservar: el UUID se uso con contenido distinto. |
| `rejected` con `retain_local: true` | Conservar para revision. |
| Red, 429 o 5xx | Conservar y reintentar mismo payload con backoff. |

`401` o `403` detienen envio hasta nueva sesion o atencion administrativa, pero nunca borran evidencia local.

## 11. Pruebas de aceptacion Android

1. Vincular clave P-256 supervisada y verificar `active`.
2. Configurar politica con vinculo y vigencia suficiente.
3. Solicitar autorizacion offline guardando `elapsedRealtime()` emitido.
4. Desconectar y capturar entrada, pausa, regreso y salida con cuatro UUID/payloads firmados.
5. Recuperar red despues de `expires_at` y sincronizar una sola vez.
6. Verificar cuatro `accepted`, cuatro eventos y evidencia original conservada.
7. Reenviar lote: cuatro `already_registered` y ningun evento adicional.
8. Reiniciar antes de una captura: `pending_review/monotonic_restarted`, sin duplicado.
9. Revocar despues de una captura: la captura previa puede aceptarse; una posterior queda en revision.
10. Alterar reloj de pared: `pending_review/clock_drift_exceeded`.
11. Confirmar que token, firmas, coordenadas, codigo RH y claves no aparecen en logs, analytics ni crash reporting.

## 12. Privacidad

- Usar Keystore y almacenamiento cifrado para token, autorizacion y cola.
- No almacenar ni transmitir biometria; solo firma de clave protegida localmente.
- No mostrar coordenadas o firmas en errores, notificaciones o soporte.
- Aviso de privacidad y retencion de coordenadas deben aprobarse antes del piloto.
- `DELETE /me/access-token` revoca solo token actual; no borra evidencia pendiente.

## 13. Referencias

- `API-0001-ESPECIFICACION-API-MVP.md`
- `API-0002-PROPUESTA-SEGURIDAD-MARCAJE.md`
- `ADR-0010-SEGURIDAD-DE-MARCAJE-MOVIL.md`
