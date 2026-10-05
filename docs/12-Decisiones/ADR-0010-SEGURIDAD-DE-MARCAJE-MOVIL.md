---
id: ADR-0010
title: Seguridad de marcaje móvil para el MVP
project: Vera Time
status: Accepted
owner: Product Architecture
created: 2026-10-05
updated: 2026-10-05
tags:
  - api
  - movil
  - seguridad
  - privacidad
  - multitenant
---

# ADR-0010 — Seguridad de marcaje móvil para el MVP

## Contexto

El contrato personal existente permite al trabajador registrar y sincronizar sus propios eventos desde PWA. Para el piloto se requiere elevar la evidencia de marcajes móviles mediante políticas de ubicación, dispositivos vinculados y una prueba criptográfica que no dependa de identificadores débiles del teléfono.

## Decisión

Se incorpora al MVP el incremento definido en `API-0002-PROPUESTA-SEGURIDAD-MARCAJE.md`.

1. El backend expone una política de seguridad efectiva resuelta desde el token y el vínculo usuario-trabajador.
2. Cada instalación móvil se vincula bajo autorización supervisada de RH y puede ser revocada desde administración.
3. Un marcaje sujeto a política conserva evidencia inmutable y firmada, junto con versión de política y contexto de seguridad.
4. La biometría del dispositivo, cuando sea requerida, desbloquea localmente una clave criptográfica; Vera no captura, almacena ni verifica huella, rostro o plantilla biométrica.
5. La activación es gradual por política y empresa. El canal personal actual continúa disponible para empresas sin una política activa, evitando bloquear el piloto por configuración incompleta.
6. El nuevo canal reutiliza las mismas Actions de registro de eventos, cálculo y auditoría. No crea otra autenticación, otra fuente de verdad ni otro modelo de jornada.

## Consecuencias

- Se agregan rutas, modelos, Actions, Policies, migraciones y pruebas antes de su uso en producción.
- Las coordenadas y firmas pasan a ser datos de evidencia sensibles: no se registran en logs ni se exponen en errores de soporte.
- La selección final de algoritmos y el aviso/retención de ubicación requieren definición previa a la activación productiva.
- La aplicación Android puede usar el contrato, pero su desarrollo sigue siendo un proyecto separado. La decisión no convierte en requisito una integración de biometría de proveedor ni el almacenamiento de datos biométricos.

## Fuera de alcance

- Reconocimiento facial, captura de huella o almacenamiento de plantillas biométricas.
- IMEI, MAC, serial u otras huellas de hardware como prueba de identidad.
- Geolocalización obligatoria para empresas que no hayan activado una política.
- Integración directa con fabricantes de dispositivos biométricos.
