---
id: UX-0003
title: Plan de implementación y pruebas del cliente móvil PWA
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-09-21
updated: 2026-09-21
tags:
  - ux
  - pwa
  - movil
  - api
  - testing
---

# UX-0003 — Plan de implementación y pruebas del cliente móvil PWA

## 1. Objetivo y decisión de implementación

Este documento convierte la especificación funcional `UX-0002` en un plan ejecutable para construir el cliente móvil inicial de Vera Time.

La propuesta para el MVP es una **PWA responsive dentro del mismo proyecto Laravel**, bajo el mismo dominio de Vera Time. La interfaz puede usar Blade, Tailwind y módulos JavaScript de Vite para las partes que requieren API, almacenamiento local y service worker.

Beneficios de esta decisión:

- no requiere app nativa iOS/Android;
- no necesita CORS mientras se sirva desde el mismo dominio;
- conserva Laravel, Blade y Tailwind como tecnología de interfaz del proyecto;
- permite instalarla desde navegadores compatibles;
- el contrato personal `/api/v1/time` seguirá siendo reutilizable por una app nativa futura.

No se implementa biometría, huella, rostro ni geolocalización obligatoria.

## 2. Requisitos que ya están definidos

La aplicación básica debe incluir exactamente estas pantallas y capacidades:

| Pantalla o función | Requisito funcional | API disponible |
|---|---|---|
| Inicio de sesión | Correo y contraseña; muestra selector si hay varias empresas | `POST /api/v1/time/auth/login` |
| Selector de empresa | Sólo aparece ante `409 company_selection_required` | Reintento de login con `company_id` devuelto por la API |
| Recuperar acceso | Solicita correo y siempre muestra respuesta neutral | `POST /api/v1/time/auth/forgot-password` |
| Inicio / Mi jornada | Empresa, trabajador, estado de marcaje, acciones permitidas y jornada actual | `GET /api/v1/time/me` |
| Marcaje | Entrada, salida, inicio o fin de pausa, según acciones permitidas | `POST /api/v1/time/me/time-events` |
| Cola offline | Conserva marcajes pendientes y los sincroniza por bloques | `POST /api/v1/time/me/time-events/sync` |
| Horario | Consulta programación publicada de la relación activa | `GET /api/v1/time/me/schedule` |
| Alertas | Muestra sólo alertas propias | `GET /api/v1/time/me/alerts` |
| Cerrar sesión | Revoca token actual y borra datos de sesión locales | `DELETE /api/v1/time/me/access-token` |

El alcance no incluye incidencias personales, correcciones, reportes, conformidad, notificaciones, biometría ni administración de empresa.

## 3. Decisiones de UX pendientes, no bloqueantes

Antes de diseñar pantallas finales se debe definir:

1. Nombre visible e ícono de la PWA.
2. Copys finales de estados y errores, respetando lenguaje neutral.
3. Si la PWA vivirá en `/time/pwa` o en un subdominio futuro. Para MVP se recomienda `/time/pwa`.
4. Política de conservación local: se recomienda borrar el token al cerrar sesión y conservar sólo marcajes pendientes indispensables en IndexedDB.

Estas decisiones no cambian las rutas API ni el modelo de datos.

## 4. Arquitectura cliente recomendada

```text
Blade + Tailwind (pantallas y layout responsive)
        ↓
Módulos JavaScript de Vite
        ├── api-client      solicitudes HTTP y Bearer token
        ├── session-store   token y contexto de sesión
        ├── offline-queue   IndexedDB, client_event_id y reintentos
        ├── time-clock      botones según allowed_actions
        └── service-worker  cache del shell de la PWA
        ↓
/api/v1/time (Laravel / Sanctum / dominio existente)
```

### 4.1 Datos locales mínimos

| Dato | Ubicación recomendada | Regla |
|---|---|---|
| Bearer token | Memoria y `sessionStorage` | Nunca en logs, URL, analytics ni texto visible. |
| Contexto de sesión | Memoria; opcionalmente `sessionStorage` | Debe refrescarse desde `GET /me`; no es fuente de autoridad. |
| Cola offline | IndexedDB | Sólo eventos propios pendientes o rechazados y sus identificadores. |
| Caché visual | Cache Storage del service worker | Sólo recursos estáticos; nunca respuestas privadas de `/api`. |

El token debe borrarse cuando el usuario cierra sesión, recibe `403` por token inválido o cambia de empresa. No guardar respuestas personales en el cache compartido del service worker.

### 4.2 Modelo de evento pendiente

```ts
type PendingTimeEvent = {
  clientEventId: string;       // UUID generado una sola vez
  eventType: 'clock_in' | 'clock_out' | 'break_start' | 'break_end';
  occurredAt: string;          // ISO-8601 con instante real del dispositivo
  timezone?: string;
  createdAt: string;
  status: 'pending' | 'syncing' | 'rejected';
  error?: string;
  device?: { code?: string; name?: string };
};
```

`clientEventId` nunca cambia al reintentar. Para un marcaje individual se utiliza ese mismo valor como encabezado `Idempotency-Key`.

## 5. Plan de realización

### Fase 0 — Preparación técnica

1. Crear la ruta y vista pública de la PWA, por ejemplo `/time/pwa`.
2. Configurar manifiesto, íconos y service worker limitado al shell visual.
3. Crear módulos `api-client`, `session-store` y `offline-queue`.
4. Definir variables de entorno públicas para la URL base, inicialmente vacía para usar el mismo origen.
5. Confirmar HTTPS en ambiente de pruebas y producción: service workers e instalación PWA lo requieren.

Criterio: la página abre en móvil, puede instalarse cuando el navegador lo permite y no requiere una sesión web Laravel para mostrar login.

### Fase 1 — Acceso y sesión

1. Construir Login con correo, contraseña, carga y errores.
2. Implementar el manejo de `201` y `409 company_selection_required`.
3. Construir selector de empresa exclusivamente con la lista recibida.
4. Implementar Recuperar acceso y mostrar siempre el mensaje neutral.
5. Añadir cierre de sesión: revocar token cuando haya red y borrar sesión local siempre.

Criterio: una cuenta vinculada entra; una cuenta multiempresa selecciona; credenciales inválidas, vínculo ausente y rate limit se explican sin filtrar datos.

### Fase 2 — Inicio y marcaje en línea

1. Consumir `GET /me` al iniciar, volver a primer plano y terminar una sincronización.
2. Mostrar empresa, trabajador, reloj local y estado de jornada.
3. Renderizar sólo `current_time_record.allowed_actions`.
4. Al pulsar un botón, crear un UUID, guardar primero el evento pendiente y enviarlo con ese UUID como `Idempotency-Key`.
5. Ante éxito, eliminarlo de la cola, actualizar contexto y mostrar confirmación. Ante error de red, conservarlo pendiente.

Criterio: repetir una solicitud por incertidumbre de red no crea un segundo evento.

### Fase 3 — Sincronización offline

1. Detectar `navigator.onLine` como señal de interfaz, no como garantía de conectividad.
2. Intentar sincronización al recuperar red, abrir la app, volver a primer plano y mediante acción manual «Sincronizar ahora».
3. Tomar los primeros 25 eventos pendientes en orden de `createdAt`.
4. Enviar `client_event_id`, tipo, instante, zona horaria y datos opcionales del dispositivo.
5. Procesar cada resultado: borrar `accepted` y `already_registered`; mantener `rejected` con mensaje; no borrar nada ante `422` estructural o fallo de red.

Criterio: una cola mezclada puede completar eventos válidos sin ocultar los rechazados ni duplicar registros.

### Fase 4 — Consultas personales

1. Construir horario con rango predeterminado de 14 días y máximo de 31 días.
2. Construir alertas paginadas con estado abierto por defecto.
3. Agregar estados vacíos: sin horario publicado, sin alertas y sin jornada calculada no son errores.
4. Permitir actualización manual y al volver a primer plano, sin sondeo continuo.

Criterio: no hay campos ni filtros que permitan elegir otro trabajador o empresa.

### Fase 5 — Endurecimiento y piloto

1. Revisar accesibilidad: foco, contraste, etiquetas de botones, lectores de pantalla y tamaño táctil mínimo de 44 px.
2. Revisar uso en móvil real Android/iOS y navegadores Chrome, Safari y Edge recientes.
3. Probar conectividad intermitente y hora local correcta en la zona horaria de empresa.
4. Preparar guía de soporte para token revocado, cuenta inactiva y marcaje rechazado.
5. Ejecutar matriz de pruebas antes de piloto y registrar incidencias con `trace_id`.

## 6. Conexión con la API

### 6.1 URL base

Si la PWA vive en el mismo dominio, usar rutas relativas:

```ts
const apiBase = '/api/v1/time';
```

No se requiere CORS en ese escenario. Si se mueve la PWA a otro dominio, se debe configurar CORS explícitamente antes de desplegar; no basta con cambiar la URL en el cliente.

### 6.2 Cliente HTTP mínimo

```ts
type ApiOptions = RequestInit & { token?: string; idempotencyKey?: string };

async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');

  if (options.body) headers.set('Content-Type', 'application/json');
  if (options.token) headers.set('Authorization', `Bearer ${options.token}`);
  if (options.idempotencyKey) headers.set('Idempotency-Key', options.idempotencyKey);

  const response = await fetch(`/api/v1/time${path}`, { ...options, headers });
  const payload = response.status === 204 ? null : await response.json();

  if (!response.ok) throw { status: response.status, payload };
  return payload as T;
}
```

Nunca adjuntar `company_id`, `worker_id`, `center_id`, `source` ni `source_user_id` a rutas personales autenticadas.

### 6.3 Inicio de sesión

```ts
const login = await api('/auth/login', {
  method: 'POST',
  body: JSON.stringify({ email, password }),
});

// 201: guardar login.data.token y usar login.data.context.
// 409: mostrar login.data.companies y repetir con company_id seleccionado.
```

Una respuesta `409` no contiene token. Sólo se permite reenviar un `company_id` que vino en `data.companies` de esa respuesta.

### 6.4 Marcaje individual

```ts
const id = crypto.randomUUID();

await api('/me/time-events', {
  method: 'POST',
  token,
  idempotencyKey: id,
  body: JSON.stringify({
    event_type: 'clock_in',
    occurred_at: new Date().toISOString(),
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    device: { name: 'PWA Vera Time' },
  }),
});
```

Antes de enviar, persistir el evento con `id` en IndexedDB. Al reintentar se usa exactamente el mismo `id`.

### 6.5 Sincronización offline

```ts
await api('/me/time-events/sync', {
  method: 'POST',
  token,
  body: JSON.stringify({
    events: pendingEvents.slice(0, 25).map((event) => ({
      client_event_id: event.clientEventId,
      event_type: event.eventType,
      occurred_at: event.occurredAt,
      timezone: event.timezone,
      device: event.device,
    })),
  }),
});
```

El cliente debe actuar con base en el estado de cada elemento de `data`, no sólo en el código HTTP general.

## 7. Matriz de pruebas durante el desarrollo

### 7.1 Pruebas unitarias del cliente

- Cliente HTTP agrega `Accept`, `Content-Type`, `Authorization` e `Idempotency-Key` correctamente.
- Login interpreta `201`, `409`, `422` y `403`.
- Selector sólo permite empresas entregadas por API.
- El reloj muestra sólo acciones en `allowed_actions`.
- UUID de un evento pendiente no cambia durante reintentos.
- Cola procesa `accepted`, `already_registered`, `rejected`, `422` estructural y error de red.
- Logout elimina token, contexto y datos privados de sesión; los pendientes se manejan según la política mostrada al usuario.

### 7.2 Pruebas de integración con API local

Ejecutar en Vera Time antes de conectar el cliente:

```powershell
php artisan test --compact tests/Feature/Api/PersonalMobileAuthApiTest.php
php artisan test --compact tests/Feature/Api/PersonalTimeApiTest.php
php artisan test --compact tests/Feature/Api
```

Validar manualmente con una cuenta vinculada que tenga producto `time` activo. Probar además una cuenta sin vínculo, una empresa suspendida y una cuenta con dos empresas elegibles.

### 7.3 Pruebas end-to-end de la PWA

| Caso | Resultado esperado |
|---|---|
| Login de una empresa | Entra, muestra contexto y no expone otro trabajador. |
| Login multiempresa | Solicita empresa antes de emitir sesión. |
| Entrada en línea | Crea un evento y actualiza estado a `trabajando`. |
| Reintento de entrada | No duplica el evento. |
| Sin red al marcar | El evento queda pendiente y visible. |
| Recuperar red | La cola sincroniza y elimina sólo aceptados o ya registrados. |
| Evento rechazado | Permanece visible con explicación; no se reintenta solo. |
| Token revocado | Se borra sesión local y vuelve a login. |
| Cambio de empresa | Exige nueva autenticación; no mezcla colas ni contexto. |
| Horario / alertas | Devuelve únicamente información del trabajador vinculado. |
| Recuperar contraseña | Muestra el mismo mensaje para correo existente y no existente. |

### 7.4 Pruebas manuales de dispositivo

- Android Chrome: instalación, marcaje, offline, reingreso y sincronización.
- iPhone Safari: navegación, tamaño táctil, estado offline y cierre de sesión.
- Zona horaria de empresa distinta a la del teléfono: validar que la interfaz muestra el contexto de empresa y conserva el instante ISO del evento.
- Conexión lenta, cambio Wi-Fi/datos y respuesta duplicada del navegador.

## 8. Criterio de salida para piloto

La PWA básica puede entrar a piloto cuando:

1. Todas las pruebas API personales están aprobadas.
2. Las pruebas unitarias y end-to-end del cliente pasan en el navegador objetivo.
3. Se verificó un ciclo completo de entrada, pausa, regreso y salida.
4. Se verificó cola offline con aceptación, replay y rechazo.
5. No se observan datos de otra empresa o trabajador.
6. Token, contraseña y datos personales no aparecen en consola, logs de cliente, URL ni cache público.
7. HTTPS, manifiesto y service worker están disponibles en el ambiente de piloto.

## 9. Referencias

- `docs/06-UX/UX-0002-ESPECIFICACION-CLIENTE-MOVIL-PWA.md`
- `docs/07-API/API-0001-ESPECIFICACION-API-MVP.md`
- `docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md`
- `tests/Feature/Api/PersonalMobileAuthApiTest.php`
- `tests/Feature/Api/PersonalTimeApiTest.php`
