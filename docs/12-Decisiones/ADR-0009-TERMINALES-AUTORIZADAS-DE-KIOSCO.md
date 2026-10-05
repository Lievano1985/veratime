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

Se agregan `kiosk_devices` y `kiosk_terminal_access_requests`, ambos separados por `company_id`. Las solicitudes representan un equipo que espera aprobacion; los dispositivos representan exclusivamente terminales que ya reclamaron su credencial.

1. Un administrador configura desde **Configuracion de empresa > Terminales de kiosco** una clave de solicitud de minimo ocho caracteres. Vera genera tambien un codigo publico corto de empresa (`VT-` y seis caracteres). La clave se guarda con hash y nunca se muestra despues de guardarla.
2. Un equipo nuevo abre `/time/kiosk`, captura su nombre, el codigo de empresa y la clave de solicitud. Esto crea una solicitud temporal pendiente; conocer la clave no permite registrar asistencias ni recibir una credencial de terminal.
3. El administrador ve la bandeja de solicitudes de su propia empresa, revisa nombre, IP y navegador, y puede elegir un centro opcional antes de aceptar o rechazar.
4. Aceptar una solicitud no crea todavia una terminal activa. El mismo navegador solicitante reclama la aprobacion usando un secreto aleatorio propio, de un solo uso y valido por quince minutos. Solo al reclamarlo se crea `kiosk_devices` y se emite el secreto de terminal.
5. El servidor conserva hashes SHA-256 del secreto de solicitud y de la credencial de terminal. La credencial se entrega una vez en una cookie `HttpOnly`, `Secure` en produccion y `SameSite=Strict`, limitada a `/time/kiosk`.
6. Una terminal activa conserva empresa y, opcionalmente, centro. Si tiene centro, solo acepta credenciales con relacion laboral activa en ese centro. No tiene una sesion administrativa ni cierre por inactividad: el navegador vuelve directamente al kiosco mientras conserve la cookie de terminal; borrarla, cambiar de navegador o revocar el equipo exige una nueva solicitud.
7. Revocar o eliminar una terminal bloquea su uso desde la siguiente solicitud. Eliminar la oculta de la operacion sin borrar su identidad tecnica, para conservar la referencia de eventos historicos.
8. Rotar la clave invalida solicitudes pendientes, pero no revoca terminales ya autorizadas. Las solicitudes tienen limite por IP, codigo de empresa y cantidad pendiente para evitar abuso.
9. Cada solicitud de una terminal y cada terminal activa actualiza los datos tecnicos minimos requeridos para soporte. "Conectada" significa vista en los ultimos tres minutos; no significa presencia fisica garantizada.

## Consecuencias

- Conocer el codigo de empresa, la clave, el codigo y NIP de una persona trabajadora no basta: se requiere aprobacion humana y la cookie secreta emitida al equipo autorizado.
- Borrar cookies, cambiar de navegador o revocar la terminal exige una nueva solicitud de terminal.
- El flujo no depende de QR, camara, MAC, numero de serie ni proveedor externo.
- La cookie reduce el riesgo, pero no sustituye controles fisicos de la empresa. Para instalaciones de mayor riesgo, el siguiente incremento debera agregar red corporativa/VPN permitida o codigo visual rotativo en pantalla.

## Fuera de alcance

- Reconocimiento de MAC, serial o biometria.
- Geolocalizacion obligatoria.
- API para administrar terminales.
- Restriccion de red, VPN o codigo visual rotativo.
