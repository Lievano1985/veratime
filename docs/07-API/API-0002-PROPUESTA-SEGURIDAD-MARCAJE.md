# API-0002 — Seguridad de marcaje móvil

**Estado:** alcance aprobado para el MVP; BL-0614 implementada. Vinculación, firma y validación de marcajes continúan pendientes.

**Fecha de decisión:** 2026-10-05.

Este documento complementa [API-0001](API-0001-ESPECIFICACION-API-MVP.md). Define el incremento de seguridad aplicable al marcaje personal desde PWA o cliente Android. `GET /api/v1/time/me/marking-security` está disponible con BL-0614; las rutas de vinculación y la evidencia firmada siguen siendo contrato objetivo hasta su implementación con pruebas.

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
6. **Conciliación sin pérdida.** Se conserva el comportamiento de sincronización `accepted`, `already_registered` y `rejected`. Un UUID repetido con contenido distinto es conflicto, no una repetición válida. Errores de red, 429, 5xx o respuesta desconocida no eliminan la fila local.

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

BL-0614 resuelve primero una política activa específica del centro de la relación laboral del trabajador y, si no existe, una política activa general de la empresa. Si no hay política vigente, `policy` y `binding` son `null`. La respuesta crea una referencia de tiempo opaca, separada por empresa, usuario y trabajador, que vence en cinco minutos. Esta referencia prueba que el servidor emitió ese contexto; por sí sola no convierte el reloj local del cliente en hora confiable.

### Vinculación de dispositivo

```http
POST /api/v1/time/me/device-binding/challenge
POST /api/v1/time/me/device-binding/complete
```

El primer endpoint consume la autorización de RH de un solo uso y devuelve un desafío aleatorio, un identificador y vencimiento. El segundo recibe la clave pública, algoritmo, desafío y prueba de posesión. El backend valida la autorización, vigencia, firma y unicidad antes de activar el vínculo.

La elección final de algoritmo, curvas, codificación, attestation disponible y representación canónica de la firma deberá quedar documentada antes de generar claves reales. No se aceptará una firma sobre JSON no canónico.

### Evidencia en `POST /me/time-events` y `POST /me/time-events/sync`

Se mantienen `Idempotency-Key` y `client_event_id`. Cuando la política efectiva lo exija, el objeto `security` por evento incluirá:

- `binding_id`, `policy_id`, `policy_version` y versión de esquema;
- ubicación: latitud, longitud, precisión, antigüedad y señal de ubicación simulada reportada por el sistema operativo;
- `time_reference_id` y la evidencia de tiempo aprobada;
- firma sobre el UUID, tipo, instante, vínculo, política, ubicación y demás campos relevantes.

El cliente no podrá enviar `company_id`, `worker_id`, `center_id`, `user_id` ni identificadores que sustituyan el contexto. La API no imprimirá coordenadas, firmas ni datos de seguridad en logs de aplicación o mensajes de soporte.

## Estados y errores

Los motivos de negocio se devolverán en una respuesta estructurada y diferenciada de una sesión inválida: `binding_inactive`, `policy_expired`, `outside_area`, `location_unverifiable`, `biometric_key_invalid`, `time_unverifiable` y `assisted_event_conflict`.

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
