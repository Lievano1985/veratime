---
id: ADR-0009
title: Terminales autorizadas de kiosco
project: Vera Time
status: Accepted
owner: Product Architecture
created: 2026-10-01
updated: 2026-10-05
tags:
  - kiosco
  - seguridad
  - terminales
  - multitenant
---

# ADR-0009 - Terminales autorizadas de kiosco

## Contexto

El kiosco inicial se activaba con una clave tecnica de empresa y despues solicitaba codigo/NIP de la persona trabajadora. Si esos tres datos se conocen, una persona podria intentar registrar desde otra computadora fuera de las instalaciones.

La direccion IP, MAC, navegador o numero de serie no son una identidad confiable de terminal: cambian, pueden compartirse o falsificarse. Se requiere una credencial independiente por computadora o tableta, revocable y separada de la credencial del trabajador.

## Decision

Se agrega `kiosk_devices`, siempre separado por `company_id`, para registrar terminales de kiosco.

1. Un administrador de empresa o RH admin crea una autorizacion desde **Configuracion de empresa > Terminales de kiosco**.
2. Vera genera un codigo aleatorio de un solo uso valido por una hora. Se puede escanear como QR o pegar manualmente en `/time/kiosk/authorize`; por ello funciona tambien en una PC sin camara.
3. El servidor guarda exclusivamente hashes SHA-256 del codigo de emparejamiento y de la credencial de terminal. El secreto de terminal se entrega una vez en una cookie `HttpOnly`, `Secure` en produccion y `SameSite=Strict`, limitada a `/time/kiosk`.
4. Una terminal activa conserva empresa y, opcionalmente, centro. Si tiene centro, solo acepta credenciales con relacion laboral activa en ese centro.
5. Revocar o eliminar una terminal bloquea su uso desde la siguiente solicitud. Eliminar la oculta de la operacion sin borrar su identidad tecnica, para conservar la referencia de eventos historicos.
6. Toda terminal debe estar autorizada. El mecanismo anterior de clave compartida se elimina de la interfaz, del flujo de kiosco y de la configuracion de empresa; abrir `/time/kiosk` sin una credencial de terminal valida redirige a `/time/kiosk/authorize`.
7. Cada solicitud de una terminal actualiza `last_seen_at`, IP y agente de navegador para soporte. “Conectada” significa vista en los ultimos tres minutos; no significa presencia fisica garantizada.

## Consecuencias

- Conocer codigo y NIP no basta: se requiere la cookie secreta emitida al equipo autorizado.
- Borrar cookies, cambiar de navegador o revocar la terminal exige emparejar de nuevo.
- El QR no usa proveedores externos; se genera localmente dentro de Vera.
- La cookie reduce el riesgo, pero no sustituye controles fisicos de la empresa. Para instalaciones de mayor riesgo, el siguiente incremento debera agregar red corporativa/VPN permitida o codigo visual rotativo en pantalla.

## Fuera de alcance

- Reconocimiento de MAC, serial o biometria.
- Geolocalizacion obligatoria.
- API para administrar terminales.
- Restriccion de red, VPN o codigo visual rotativo.
