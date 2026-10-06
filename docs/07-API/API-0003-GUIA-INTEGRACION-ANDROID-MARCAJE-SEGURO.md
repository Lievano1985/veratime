---
id: API-0003
title: Guía única de integración Android — marcaje seguro
project: Vera Time
version: 1.0.0
status: Ready for integration
owner: Product Architecture
created: 2026-10-06
tags:
  - android
  - pwa
  - movil
  - geolocalizacion
  - vinculacion-dispositivo
  - api
---

# API-0003 — Guía única de integración Android: vinculación y georreferenciación

## 1. Propósito

Este es el documento de entrega para el equipo que desarrolla la aplicación Android/PWA de Vera Time. Consolida únicamente lo necesario para integrar la vinculación de dispositivo, la georreferenciación y el marcaje seguro ya disponibles en el backend.

No se deben crear rutas, credenciales ni reglas paralelas en la app. La aplicación consume la API personal y el backend siempre resuelve empresa, trabajador, centro y política desde el token.

## 2. Ambiente y URL base

Producción:

```text
https://gotvera.com/api/v1/time
```

Todas las rutas protegidas usan:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

El token se recibe una sola vez al iniciar sesión. Guardarlo en almacenamiento seguro del dispositivo; nunca en logs, capturas, preferencias sin cifrar ni analytics.

## 3. Requisitos previos en Vera Time

Antes de probar la app, el administrador/RH debe confirmar:

1. La cuenta de usuario está vinculada a su trabajador en **Usuarios > Vínculo con trabajador**.
2. La empresa y VERA Time están activos.
3. Si se desea exigir ubicación o dispositivo, existe una política activa en **Configuración de empresa > Marcaje móvil**.
4. Si la política exige dispositivo, RH genera una autorización de vinculación para ese usuario-trabajador. El código se muestra una sola vez y vence en quince minutos.

Sin política activa, la app puede usar el marcaje personal básico. Con política activa, la app debe respetar exactamente lo que responda `GET /me/marking-security`.

## 4. Inicio de sesión y contexto

### 4.1 Iniciar sesión

```http
POST /auth/login
```

```json
{
  "email": "persona@empresa.com",
  "password": "contraseña-del-usuario",
  "company_id": 3
}
```

`company_id` es opcional sólo cuando la cuenta tiene una sola empresa elegible. Si responde `409` con `code: company_selection_required`, mostrar las empresas recibidas y reenviar el mismo inicio de sesión con el `company_id` elegido.

Respuesta exitosa (`201`):

```json
{
  "data": {
    "token": "{token-solo-se-muestra-una-vez}",
    "token_type": "Bearer",
    "abilities": ["self:read", "self:write"],
    "context": {
      "company": {"id": "3", "name": "Empresa", "timezone": "America/Mexico_City"},
      "worker": {"id": "9", "employee_code": "RG004", "full_name": "Persona trabajadora"}
    }
  },
  "meta": {"trace_id": "trc_x"}
}
```

La app no debe permitir seleccionar ni sustituir trabajador, centro o empresa después de recibir el token.

### 4.2 Consultar contexto y política

Al iniciar la aplicación y justo antes de cada marcaje, consultar:

```http
GET /me
GET /me/marking-security
```

`GET /me` entrega el estado actual de jornada y las acciones permitidas. Mostrar sólo las acciones de `current_time_record.allowed_actions`.

`GET /me/marking-security` entrega una `time_reference` válida durante cinco minutos y la política efectiva. Ejemplo:

```json
{
  "data": {
    "security_version": 1,
    "policy": {
      "id": "uuid-de-politica",
      "version": 2,
      "mode": "circle",
      "device_binding_required": true,
      "biometric_required": true,
      "center": {"latitude": 19.432608, "longitude": -99.133209},
      "radius_meters": 150,
      "max_accuracy_meters": 25,
      "max_location_age_seconds": 60
    },
    "time_reference": {
      "id": "uuid-de-referencia",
      "server_time": "2026-10-06T15:00:00Z",
      "expires_at": "2026-10-06T15:05:00Z"
    }
  }
}
```

Si `policy` es `null`, no se envía el objeto `security` y no se requiere vinculación. Si existe una política, siempre se incluye `security.time_reference_id` vigente.

## 5. Vinculación supervisada del dispositivo

La vinculación se hace una vez por instalación o al cambiar de teléfono. No se reutilizan claves entre dispositivos.

### 5.1 Generar la clave local

Android debe crear una clave EC P-256/secp256r1 dentro de Android Keystore:

- algoritmo de firma: `SHA256withECDSA`;
- curva: P-256 / `prime256v1`;
- formato de firma enviado: DER;
- clave pública enviada: SPKI/X.509 codificada Base64URL sin `=`;
- clave privada: nunca sale del Keystore;
- si la política requiere biometría, configurar la clave para requerir autenticación local del usuario antes de firmar.

Vera no recibe ni almacena huellas, rostro, IMEI, MAC ni serie del dispositivo.

### 5.2 Obtener desafío

RH muestra al trabajador el código de autorización de 24 caracteres. La app solicita el desafío:

```http
POST /me/device-binding/challenge
```

```json
{
  "authorization_code": "codigo-de-24-caracteres",
  "device_name": "Samsung de Eduardo"
}
```

Respuesta:

```json
{
  "data": {
    "authorization_id": "uuid",
    "challenge": "reto-aleatorio",
    "expires_at": "2026-10-06T15:05:00Z",
    "algorithm": "ES256",
    "signature_format": "der",
    "payload": "VERA-MOBILE-BINDING-V1\n..."
  }
}
```

Firmar exactamente el campo `payload` UTF-8 recibido. No reconstruirlo, no serializar JSON y no cambiar saltos de línea.

### 5.3 Completar la vinculación

Codificar la firma DER y la clave pública SPKI como Base64URL sin relleno, después enviar:

```http
POST /me/device-binding/complete
```

```json
{
  "authorization_id": "uuid-recibido",
  "public_key_spki": "base64url-sin-relleno",
  "signature": "firma-der-base64url-sin-relleno"
}
```

Una respuesta `201` con `status: active` confirma que el dispositivo está autorizado. Guardar localmente únicamente:

- el `binding.id` público;
- el alias de la clave en Keystore;
- nombre declarado del dispositivo, si la app lo necesita para UI.

No guardar el código de autorización ni el desafío. Para consultar vínculos propios:

```http
GET /me/device-bindings
```

Si RH revoca el vínculo, la app debe detener los marcajes que requieran dispositivo y solicitar una nueva autorización.

## 6. Captura de georreferenciación

Cuando la política indique `mode: circle`, `max_accuracy_meters` o `max_location_age_seconds`, solicitar ubicación antes de firmar.

La aplicación debe:

1. Solicitar permiso de ubicación al usuario y explicar el propósito laboral.
2. Obtener una ubicación reciente; no reutilizar una ubicación vieja de caché.
3. Enviar latitud, longitud y precisión como **cadenas decimales**. Ejemplo: `"19.4326080"`, `"8.50"`.
4. Enviar `captured_at` como el texto ISO-8601 exacto que se firmará.
5. Enviar `is_mocked: true` si Android detecta proveedor simulado. El backend la rechazará.
6. No redondear, reformatear ni reemplazar los valores entre firma y envío.

Para `circle`, la app puede anticipar que está fuera del radio y mostrar una explicación, pero el backend es la validación definitiva. No debe ocultar un evento ni afirmar que fue aceptado hasta recibir la respuesta de API.

## 7. Marcaje individual seguro

Ruta:

```http
POST /me/time-events
Idempotency-Key: {uuid-nuevo-por-marcaje}
```

Usar un UUID nuevo por captura y conservarlo junto con el payload local. Un reintento debe usar el mismo UUID y exactamente el mismo contenido.

### 7.1 Construcción de la firma de marcaje

Sólo cuando `policy.device_binding_required` es `true`, firmar este texto UTF-8, separado exclusivamente por `\n`:

```text
VERA-MOBILE-EVENT-V1
{Idempotency-Key o client_event_id}
{event_type}
{occurred_at exacto}
{timezone o vacío}
{binding_id}
{policy.id}
{policy.version}
{time_reference.id}
{latitude o vacío}
{longitude o vacío}
{accuracy_meters o vacío}
{location.captured_at o vacío}
{1 si is_mocked; 0 si no}
```

Firmar con `SHA256withECDSA`, obtener DER y convertir a Base64URL sin `=`. El dispositivo debe pedir biometría/desbloqueo local si la clave fue configurada para ello; esa biometría jamás se transmite.

### 7.2 Ejemplo de envío con perímetro y dispositivo

```json
{
  "event_type": "clock_in",
  "occurred_at": "2026-10-06T15:02:10Z",
  "timezone": "America/Mexico_City",
  "device": {"name": "Samsung de Eduardo"},
  "security": {
    "time_reference_id": "uuid-de-referencia",
    "binding_id": "uuid-de-vinculo",
    "signature": "firma-der-base64url",
    "location": {
      "latitude": "19.4326080",
      "longitude": "-99.1332090",
      "accuracy_meters": "8.50",
      "captured_at": "2026-10-06T15:02:08Z",
      "is_mocked": false
    }
  }
}
```

No enviar `company_id`, `worker_id`, `user_id`, `center_id`, `policy_id`, `source` ni `source_user_id`; son campos prohibidos.

## 8. Sincronización de cola local

Ruta:

```http
POST /me/time-events/sync
```

Enviar de 1 a 25 eventos. La estructura de cada elemento es igual al marcaje individual, salvo que `client_event_id` sustituye el encabezado `Idempotency-Key` y es el primer valor de la firma.

```json
{
  "events": [
    {
      "client_event_id": "uuid-local-del-evento",
      "event_type": "clock_in",
      "occurred_at": "2026-10-06T15:02:10Z",
      "timezone": "America/Mexico_City",
      "security": {"time_reference_id": "uuid", "binding_id": "uuid", "signature": "...", "location": {"latitude": "19.4326080", "longitude": "-99.1332090", "accuracy_meters": "8.50", "captured_at": "2026-10-06T15:02:08Z", "is_mocked": false}}
    }
  ]
}
```

Tratar la respuesta por evento:

| Estado | Acción local |
|---|---|
| `accepted` | Marcar como entregado; se puede depurar de la cola según la política local. |
| `already_registered` | Marcar como entregado; es un reintento confirmado y no un duplicado. |
| `conflict` | Conservar. Nunca cambiar el UUID ni sobrescribir el payload. Mostrar o enviar a revisión. |
| `rejected` con `retain_local: true` | Conservar con el código de error para revisión; no borrar ni reconstruir la evidencia. |
| Error de red, 429, 5xx o respuesta desconocida | Conservar sin cambios y reintentar con backoff. |

Una referencia de tiempo vencida responde `error_code: time_unverifiable`, `retryable: false`, `retain_local: true`. No debe reenviarse automáticamente ni reconstruirse con una referencia nueva: queda pendiente de conciliación.

## 9. Códigos de error de seguridad

| Código | Significado para la app |
|---|---|
| `binding_inactive` | El dispositivo fue revocado o no corresponde al usuario. Conservar el evento y pedir nueva vinculación. |
| `biometric_key_invalid` | La firma no se pudo verificar. Conservar para revisión; no generar una firma nueva sobre el mismo evento. |
| `outside_area` | La ubicación está fuera del radio permitido. Conservar y explicar al usuario. |
| `location_unverifiable` | Falta ubicación, es simulada, antigua o supera la precisión permitida. Conservar y mostrar el motivo. |
| `time_unverifiable` | La referencia de cinco minutos venció o no corresponde a la política. Conservar para conciliación. |
| `idempotency_conflict` | El mismo UUID se usó con contenido distinto. Conservar ambos datos locales para revisión; no reintentar cambiando datos. |

Una respuesta `401` significa token ausente o inválido; `403` significa que la cuenta, producto, membresía o vínculo ya no permiten operar. En esos casos detener la cola y solicitar inicio de sesión o atención administrativa, sin borrar la evidencia local.

## 10. Reglas de almacenamiento local

- Guardar token en almacenamiento cifrado.
- Guardar cola y payload original de manera cifrada si el dispositivo lo permite.
- Guardar coordenadas y firma sólo dentro de la fila local del evento pendiente; no enviarlas a logs, crash reporting o analytics.
- No modificar un evento ya firmado. Si se corrige información, crear una nueva captura con un UUID nuevo.
- Borrar el token al cerrar sesión. Usar `DELETE /me/access-token` cuando el usuario cierre sesión desde la app.
- No declarar como aceptado un evento mientras no exista respuesta `accepted` o `already_registered`.

## 11. Lista de pruebas de aceptación para Android

1. Iniciar sesión con una empresa y consultar `/me`.
2. Si el usuario tiene varias empresas, manejar `409 company_selection_required`.
3. Sin política activa, marcar entrada y verificar `201`.
4. Activar una política de radio y comprobar que falta de ubicación produce rechazo seguro.
5. Vincular dispositivo mediante código de RH, desafío P-256 y firma DER.
6. Marcar dentro del radio con precisión válida y comprobar `201`.
7. Intentar marcar fuera del radio y comprobar `outside_area` sin que se cree evento.
8. Revocar el dispositivo desde Vera Time y comprobar `binding_inactive`.
9. Reenviar exactamente el mismo marcaje y comprobar `already_registered` o `idempotent_replay`.
10. Reutilizar el UUID con datos distintos y comprobar `idempotency_conflict`.
11. Simular falta de red: conservar la fila local; al recuperar conectividad, sincronizarla una sola vez.
12. Enviar una referencia vencida: comprobar `time_unverifiable` y conservarla, sin reconstruirla.
13. Confirmar que logs locales y remotos no contienen token, firma, coordenadas, código de autorización ni secreto.

## 12. Pendiente explícito

El backend no acepta todavía evidencia que se capturó totalmente offline después de vencer la referencia de tiempo. Ese comportamiento requiere una decisión posterior de producto/legal sobre validez offline, retención de ubicación y conciliación. La app ya debe conservar el evento pendiente, pero no intentar modificarlo ni forzar su aceptación.

## 13. Referencias técnicas

- `API-0001-ESPECIFICACION-API-MVP.md`: contrato completo de API personal.
- `API-0002-PROPUESTA-SEGURIDAD-MARCAJE.md`: decisión y reglas de seguridad móvil.
- `ADR-0010-SEGURIDAD-DE-MARCAJE-MOVIL.md`: límites de privacidad y arquitectura.
