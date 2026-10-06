# API-0002 — Seguridad de marcaje móvil

**Estado:** alcance aprobado para el MVP; BL-0614 y BL-0615 están implementadas. BL-0616 está implementada para marcaje en línea con referencia vigente; la conciliación offline segura continúa pendiente.

**Fecha de decisión:** 2026-10-05.

Este documento complementa [API-0001](API-0001-ESPECIFICACION-API-MVP.md). Define el incremento de seguridad aplicable al marcaje personal desde PWA o cliente Android. La política efectiva, la vinculación supervisada y la evidencia firmada para marcaje en línea ya están disponibles; la conciliación offline segura continúa como contrato objetivo.

## Decisión de alcance

El MVP incluirá marcaje móvil con política de ubicación, vinculación supervisada de dispositivo y prueba criptográfica de posesión. La activación será gradual por empresa y política: una persona no queda bloqueada mientras su empresa no haya configurado y activado este mecanismo.

La aplicación Android se desarrolla en un proyecto independiente; no habrá una autenticación ni una lógica de jornada paralelas. Consumirá el mismo contrato personal `/api/v1/time/me` y las mismas Actions de dominio que el portal/PWA.

No se almacenarán ni transmitirán huellas, rostros, plantillas biométricas, IMEI, MAC ni números de serie como evidencia. Si el dispositivo usa su biometría o bloqueo local, sólo será para autorizar el uso de una clave privada protegida; el servidor recibirá y validará una prueba criptográfica, nunca el dato biométrico.

## Capacidades obligatorias del MVP

1. **Política efectiva de marcaje.** `GET /api/v1/time/me/marking-security` devolverá exclusivamente la política aplicable al usuario y trabajador resueltos desde el Bearer token. No aceptará `company_id`, `worker_id` ni centro elegible enviados por el cliente.
2. **Vinculación supervisada.** RH o un administrador autorizado inicia una autorización de un solo uso con vigencia. La persona completa el vínculo en su dispositivo mediante desafío, clave pública y prueba de posesión. Cada instalación recibe un vínculo propio; cambiar de teléfono no reutiliza el anterior.
3. **Revocación administrativa.** RH puede revocar un vínculo o autorizar su sustitución. La revocación bloquea nuevos marcajes con ese vínculo; los eventos existentes y sus evidencias se conservan.
4. **Evidencia de cada marcaje.** Cuando la política esté activa, cada evento individual o sincronizado conserva de forma inmutable el vínculo, versión de política, ubicación declarada, referencia de tiempo, versión de esquema y firma. La captura se encola localmente desde el momento del marcaje; un reintento no puede reconstruirla con la política actual.
5. **Ubicación con política explícita.** La política puede ser `free` o `circle`. Sólo `circle` expone centro y radio. Se validan precisión, antigüedad de ubicación y vigencia configuradas por la empresa. Los valores numéricos no quedan fijados en este documento: cada política debe estar versionada y ser auditable.
6. **Conciliación sin pérdida.** La sincronización responde `accepted`, `already_registered`, `rejected` o `conflict`. Cada UUID aceptado queda ligado a una huella del contenido; un UUID repetido con contenido distinto es conflicto, no una repetición válida. Errores de red, 429, 5xx o respuesta desconocida no eliminan la fila local. Los rechazos y conflictos indican `retain_local: true` para que la app los conserve para revisión; una referencia vencida se reporta como `time_unverifiable`, no debe reconstruirse ni reenviarse automáticamente. La emisión y conciliación de evidencia offline que pueda seguir siendo válida después de vencer la referencia continúa pendiente.

## Contrato objetivo

### Política efectiva personal

```http
GET /api/v1/time/me/marking-security
```

Respuesta conceptual:

```json
{
  "data": {
    "security_version": 1,
    "binding": null,
    "policy": {
      "id": "opaque-policy",
      "version": 2,
      "mode": "circle",
      "center": {"latitude": 19.4, "longitude": -99.1},
      "radius_meters": 100,
      "valid_from": "2026-10-05T00:00:00Z",
      "offline_valid_until": "2026-10-06T00:00:00Z",
      "max_accuracy_meters": 20,
      "max_location_age_seconds": 30
    },
    "time_reference": {
      "id": "opaque-time-reference",
      "server_time": "2026-10-05T12:00:00Z",
      "expires_at": "2026-10-05T12:05:00Z"
    }
  },
  "meta": {"trace_id": "trc_example"}
}
```

Los valores de radio, precisión y antigüedad son ilustrativos, no límites aprobados. `mode: free` omite centro y radio, pero no elimina los demás requisitos que tenga la política. Mientras BL-0615 no esté implementada, `binding` es `null` y la política sólo informa requisitos, sin bloquear el marcaje actual.

BL-0614 resuelve primero una política activa específica de la unidad organizacional principal del trabajador (departamento, área o equipo), después una política de su centro y finalmente la política general de la empresa. Esto permite varios radios dentro del mismo centro. Si no hay política vigente, `policy` y `binding` son `null`. La respuesta crea una referencia de tiempo opaca, separada por empresa, usuario y trabajador, que vence en cinco minutos. Esta referencia prueba que el servidor emitió ese contexto; por sí sola no convierte el reloj local del cliente en hora confiable.

### Administración web de políticas

RH o el administrador de empresa configura estas políticas desde **Configuración de empresa > Marcaje móvil**. Puede crear borradores para toda la empresa, un centro o una unidad organizacional. La unidad siempre se valida dentro de la empresa y conserva el centro al que pertenece; el cliente móvil no recibe ni puede elegir ese alcance.

Una política se edita sólo mientras sea borrador. Al activarla se publica como una nueva versión y la política activa anterior del mismo alcance pasa a `inactive`; no se borra. Desactivarla tampoco borra filas ni evidencias. El alcance más específico continúa resolviéndose primero: unidad, centro y empresa. Esta pantalla no habilita marcaje offline ni establece por sí sola una fecha de retención de ubicación.

### Vinculación de dispositivo

```http
POST /api/v1/time/me/device-binding/challenge
POST /api/v1/time/me/device-binding/complete
```

El primer endpoint consume la autorización de RH de un solo uso y devuelve un desafío aleatorio, un identificador y vencimiento. El segundo recibe la clave pública y la prueba de posesión. El backend valida la autorización, vigencia, firma, curva y unicidad antes de activar el vínculo.

RH o un administrador genera la autorización únicamente para una cuenta que ya esté vinculada a su trabajador dentro de la empresa. El código se muestra una sola vez, dura quince minutos y al crear uno nuevo se revoca cualquier autorización pendiente anterior de esa misma identidad.

`POST /device-binding/challenge` recibe el código y el nombre declarado del dispositivo. Responde un desafío de un solo uso con vigencia de cinco minutos, `algorithm: ES256`, `signature_format: der` y el `payload` UTF-8 exacto a firmar. El cliente Android genera una clave P-256 en Android Keystore y firma ese payload mediante `SHA256withECDSA`; la API nunca recibe una clave privada ni un dato biométrico.

`POST /device-binding/complete` recibe `authorization_id`, `public_key_spki` y `signature`, estos dos últimos codificados en Base64URL sin relleno. El servidor acepta exclusivamente una clave pública SPKI P-256 (`prime256v1`), recompone el mismo `payload` emitido por el desafío y valida una firma ECDSA SHA-256 en formato DER. Una verificación correcta crea un vínculo `active`, consume la autorización y borra el desafío recuperable; la clave pública no puede volver a vincularse dentro de la misma empresa.

La app debe generar la clave en Android Keystore, conservar sólo el identificador local de esa clave y enviar su SPKI pública. No se aceptará una firma sobre JSON no canónico. La attestation de hardware, si el dispositivo la ofrece, queda fuera de este primer incremento y no debe ser simulada como garantía de seguridad.

`GET /me/device-bindings` muestra exclusivamente los vínculos del usuario y trabajador resueltos por el token; no devuelve la clave pública ni su huella. RH o un administrador gestionan la lista completa de esa identidad desde **Usuarios > Vínculo con trabajador** y pueden revocar un dispositivo activo. La revocación conserva la fila, actor y fecha, pero impide que el vínculo satisfaga políticas que exijan dispositivo autorizado.

### Evidencia en `POST /me/time-events` y `POST /me/time-events/sync`

Se mantienen `Idempotency-Key` y `client_event_id`. Cuando exista una política activa, el objeto `security` por evento incluye una `time_reference_id` vigente. Cuando la política requiere dispositivo, también incluye `binding_id` y `signature`. Cuando la política define círculo, precisión o antigüedad de ubicación, incluye además `location`.

La implementación valida la política vigente, la referencia de tiempo de cinco minutos, el vínculo activo, la firma, la precisión, antigüedad, indicador de ubicación simulada y distancia al círculo. Persiste una evidencia inmutable separada del evento con la versión y snapshot de política, la referencia, el vínculo, firma y ubicación. No se insertan coordenadas ni firmas en metadatos o logs de aplicación; las respuestas de conflicto tampoco devuelven el contenido original.

La firma ECDSA se genera sobre este payload UTF-8, separado con saltos de línea `\n`, sin espacios adicionales:

```text
VERA-MOBILE-EVENT-V1
{idempotency_key o client_event_id}
{event_type}
{occurred_at enviado}
{timezone o vacío}
{binding_id}
{policy_id}
{policy_version}
{time_reference_id}
{latitude o vacío}
{longitude o vacío}
{accuracy_meters o vacío}
{location.captured_at o vacío}
{1 si is_mocked; 0 si no}
```

`latitude`, `longitude` y `accuracy_meters` se envían como cadenas decimales para no alterar su representación antes de validar la firma. `occurred_at` y `location.captured_at` deben ser exactamente los textos firmados. El servidor no acepta que el cliente envíe o sustituya empresa, trabajador, usuario o política.

El cliente no podrá enviar `company_id`, `worker_id`, `center_id`, `user_id` ni identificadores que sustituyan el contexto. La API no imprimirá coordenadas, firmas ni datos de seguridad en logs de aplicación o mensajes de soporte.

## Estados y errores

Los motivos de negocio se devuelven en una respuesta estructurada y diferenciada de una sesión inválida: `binding_inactive`, `outside_area`, `location_unverifiable`, `biometric_key_invalid`, `time_unverifiable` e `idempotency_conflict`. En el lote se reportan por elemento como `error_code`, `retryable` y `retain_local`; el marcaje individual usa el objeto `error` con los mismos campos. `idempotency_conflict` y `time_unverifiable` deben conservarse localmente para conciliación y no deben provocar que la app elimine o reescriba la captura.

No se usarán indiscriminadamente 401 o 403 para estos motivos, porque el cliente puede invalidar su sesión. Un `422` estructural tampoco autoriza borrar la cola. La implementación definirá el estado HTTP y el esquema final de error con pruebas de compatibilidad del cliente.

Los eventos pendientes después de una revocación o de un conflicto con marcaje asistido se conservarán para revisión de RH. La resolución será auditada y no provocará doble cómputo de jornada.

## Privacidad, auditoría y límites

- Toda entidad operativa de este incremento estará aislada por `company_id` y se resolverá desde el token y el vínculo del servidor.
- La política, el vínculo, su revocación y la evidencia aceptada deberán registrar actor, fecha UTC y versión.
- La configuración de retención, aviso de privacidad y base jurídica de coordenadas debe ser aprobada por negocio/legal antes de activar ubicación para una empresa piloto. No se debe inventar una vigencia de conservación.
- La evidencia original no se sobrescribe al actualizar una política o revocar un dispositivo.

## Criterio de listo para piloto

Antes de activar la seguridad para una empresa se deben completar: migraciones MySQL/MariaDB, Actions y Policies, autorización y revocación desde administración, contrato Retrofit/PWA, pruebas de aislamiento multi-tenant, idempotencia, firma inválida, política vencida, ubicación fuera de área, flujo offline y revisión manual de privacidad.

## Relación con el cliente Android

El cliente puede mantener modelos locales, almacenamiento cifrado y una interfaz de firma mientras se implementa el backend. No debe activar el flujo ni declarar un reloj local como confiable hasta que este contrato, sus algoritmos y las rutas estén implementados y probados en un ambiente controlado.
