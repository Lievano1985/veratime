---
title: Matriz de resultados de revision del modulo de horarios
project: Vera Time
status: Approved
updated: 2026-07-28
---

# Matriz de resultados de revision del modulo de horarios

## 0. Resultado de cierre

Revision manual ejecutada y aprobada el 2026-07-28.

Criterios aprobados:

- Horarios F1-F5B pasan flujo completo manual.
- CSV valido/invalido funciona sin publicar automaticamente.
- Correcciones versionadas conservan historial.
- Supervisor no puede modificar ni publicar.
- Responsive no bloquea uso real.
- Empresas no activas no generan 404 en `/companies`.
- No aparecen fallos S1/S2.

Hallazgo corregido durante la revision:

- Los borradores correctivos conservan trabajadores dados de baja como historial y los marcan con badge `Baja historica`; esos dias quedan preparados para no generar jornadas calculables en `work_days`.

## 1. Estados permitidos

- No ejecutado.
- Aprobado.
- Fallo.
- Bloqueado.
- No aplica.

## 2. Severidades

### S1 Bloqueante

Usar S1 cuando exista:

- perdida o modificacion de evidencia;
- mezcla de empresas;
- corrupcion de version;
- publicacion parcial;
- hash inconsistente;
- modificacion de publicado;
- aplicacion parcial CSV.

### S2 Importante

Usar S2 cuando exista:

- permiso incorrecto;
- resolucion erronea de perfil;
- UTC incorrecto;
- cobertura incompleta no detectada;
- correccion que no sustituye correctamente;
- archivo privado expuesto.

### S3 Media

Usar S3 cuando exista:

- validacion incompleta;
- mensajes confusos;
- filtros incorrectos;
- navegacion inconsistente.

### S4 Menor

Usar S4 cuando exista:

- detalle visual;
- texto menor;
- espaciado;
- problema responsive menor.

## 3. Matriz reusable

| ID | Area | Escenario | Rol | Resultado esperado | Resultado real | Estado | Severidad | Evidencia | Hallazgo relacionado |
|---|---|---|---|---|---|---|---|---|---|
| H-001 | Acceso | Login valido | rh | Accede a dashboard | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-002 | Acceso | Empresa activa | rh | Empresa correcta | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-003 | Multi-tenant | Manipular IDs de otra empresa | rh | Acceso bloqueado | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-010 | Organizacion | Centro activo con timezone | rh | Centro correcto | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-011 | Organizacion | Jerarquia department-area-team | rh | Jerarquia visible | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-014 | Supervisor | Mi alcance | supervisor | Solo alcance vigente | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-020 | Turnos | Turno diurno | rh | Preview correcto | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-021 | Turnos | Turno nocturno | rh | Cruce +1 dia correcto | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-022 | Turnos | Turno dividido | rh | Metricas correctas | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-030 | Perfiles | Patron semanal | rh | 7 reglas | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-031 | Perfiles | Ciclo repetitivo | rh | Ciclo desde ancla | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-032 | Perfiles | Calendario | rh | Queda pendiente | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-033 | Perfiles | Flexible | rh | Minutos/ventana sin segmentos | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-034 | Perfiles | Guardia bajo llamada | rh | Disponibilidad no es trabajo real | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-035 | Perfiles | Jerarquia de asignacion | rh | Gana mas especifico | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-040 | Programacion diaria | Crear lote | rh | Borrador por centro/periodo | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-042 | Programacion diaria | Missing only | rh | Llena solo vacios | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-043 | Programacion diaria | Refresh conserva manual/csv/api | rh | No sobrescribe fuentes externas | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-045 | Programacion diaria | Edicion individual | rh | Fuente manual | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-046 | Programacion diaria | Cambio masivo | rh | Todo o nada | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-050 | Publicacion | Bloquea pendientes | rh | No publica incompleto | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-052 | Publicacion | Publicar | rh | Hash y solo lectura | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-054 | Publicacion | Verificar integridad | rh | Hash coincide | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-060 | Correccion | Crear v2 | rh | Clona desde v1 | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-063 | Correccion | Publicar v2 | rh | v1 sustituida, v2 publicada | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-065 | Correccion | Bloquear rama paralela | rh | Bloqueado | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-070 | CSV | Descargar plantilla | rh | 15 encabezados | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-071 | CSV | Cargar CSV valido | rh | Preview valido | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-072 | CSV | CSV invalido no modifica | rh | No aplica cambios | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-077 | CSV | Proteccion formula | rh | Protege `=`, `+`, `-`, `@` | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-078 | CSV | Stale preview | rh | Bloquea aplicacion | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-080 | CSV | Corrective draft | rh | No modifica v1 | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-081 | CSV | CSV no publica | rh | Lote sigue draft | Pendiente | No ejecutado | S1 Bloqueante | Pendiente | Pendiente |
| H-093 | Roles | Supervisor | supervisor | No edita/publica | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-100 | Responsive | Calendario escritorio | rh | Usable | Pendiente | No ejecutado | S3 Media | Pendiente | Pendiente |
| H-102 | Responsive | Calendario movil | rh | Lista legible | Pendiente | No ejecutado | S3 Media | Pendiente | Pendiente |
| H-116 | Integridad tecnica | Archivos privados | tecnico | No expone storage path | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |
| H-117 | Integridad tecnica | Escritura por Actions | tecnico | Sin reglas en Livewire | Pendiente | No ejecutado | S2 Importante | Pendiente | Pendiente |

## 4. Registro de hallazgos

| ID | Fecha | Area | Descripcion | Severidad | Evidencia | Estado | Responsable |
|---|---|---|---|---|---|---|---|
| PEND-001 | Pendiente | Pendiente | Registrar aqui hallazgos de la revision manual. | S4 Menor | Pendiente | No ejecutado | Pendiente |
| REV-001 | 2026-07-28 | Horarios F1-F5B | Revision manual aprobada sin S1/S2 abiertos. | No aplica | Confirmacion manual del usuario | Cerrado | Usuario/Codex |
