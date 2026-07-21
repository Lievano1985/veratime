---
title: Plan de revision integral del modulo de horarios
project: Vera Time
status: Draft
updated: 2026-07-20
---

# Plan de revision integral del modulo de horarios

## 1. Objetivo

Confirmar de punta a punta:

- funcionalidad;
- permisos;
- aislamiento multi-tenant;
- consistencia de datos;
- trazabilidad;
- inmutabilidad;
- experiencia de usuario;
- compatibilidad movil;
- snapshots;
- versiones;
- importacion CSV.

Esta revision manual aun no ha sido ejecutada. El plan prepara la revision antes de iniciar `work_days` y motor legal.

## 2. Preparacion del entorno

Ejecutar en local:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleScenarioSeeder
php artisan db:seed --class=VeraTimePublishedScheduleScenarioSeeder
php artisan db:seed --class=VeraTimeCorrectedScheduleScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleCsvScenarioSeeder
npm.cmd run build
php artisan serve
```

Seeders verificados:

- `VeraTimeDemoSeeder`
- `VeraTimeScheduleProfileScenarioSeeder`
- `VeraTimeDailyScheduleScenarioSeeder`
- `VeraTimePublishedScheduleScenarioSeeder`
- `VeraTimeCorrectedScheduleScenarioSeeder`
- `VeraTimeDailyScheduleCsvScenarioSeeder`

Usuarios demo verificados:

| Escenario | Usuario | Contrasena |
|---|---|---|
| Demo general | `owner.demo@veratime.local` | `VeraDemo123!` |
| Demo general | `admin.demo@veratime.local` | `VeraDemo123!` |
| Demo general | `rh.demo@veratime.local` | `VeraDemo123!` |
| Demo general | `supervisor.demo@veratime.local` | `VeraDemo123!` |
| Oficina | `rh.office.demo@veratime.local` | `VeraDemo123!` |
| Tienda CSV/calendario | `rh.store.demo@veratime.local` | `VeraDemo123!` |
| Construccion supervisor | `supervisor.construction.demo@veratime.local` | `VeraDemo123!` |

NIP demo general verificado:

```text
1234
```

Empresas demo principales:

- Vera Time Demo Completo.
- Demo Oficina por Patron.
- Demo Tienda por Calendario.
- Demo Constructora con Herencia.
- Demo Sin Perfil de Horario.
- Demo Ciclo Rotativo.
- Demo Horario Flexible.
- Demo Bajo Demanda.

## 3. Estructura de cada prueba

Cada prueba manual debe registrar:

- ID.
- Objetivo.
- Rol.
- Datos previos.
- Ruta.
- Pasos.
- Resultado esperado.
- Evidencia sugerida.
- Riesgo cubierto.
- Severidad si falla.

## 4. Orden de ejecucion

### Fase 1 - Acceso y contexto

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-001 | Login valido | `rh` | `/login` | Entrar con usuario demo | Accede a dashboard | Acceso roto | S2 |
| H-002 | Empresa activa | `rh` | Dashboard | Confirmar selector/contexto | Muestra empresa activa correcta | Tenant incorrecto | S1 |
| H-003 | Usuario otra empresa | `rh.office` vs lote store | `/scheduling/daily` | Manipular IDs de lote/import | Acceso bloqueado | Mezcla de empresas | S1 |
| H-004 | Supervisor sin scope global | `supervisor` | Horarios | Intentar crear/editar/publicar | Bloqueado | Permiso excesivo | S2 |

### Fase 2 - Centros y organizacion

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-010 | Centro | `rh` | `/centers` | Revisar centro demo | Centro activo con timezone | Timezone incorrecto | S2 |
| H-011 | Jerarquia | `rh` | `/organization/units` | Revisar departamento, area, equipo | Jerarquia visible de tres niveles | Alcance mal resuelto | S2 |
| H-012 | Unidad primaria | `rh` | `/organization/assignments` | Revisar asignacion vigente | Trabajador tiene unidad principal | Perfil heredado incorrecto | S2 |
| H-013 | Apoyo temporal | `rh` | `/organization/assignments` | Revisar apoyo temporal | No cambia perfil heredado | Herencia incorrecta | S3 |
| H-014 | Alcance supervisor | `supervisor.construction` | `/organization/my-scope` | Revisar alcance | Solo trabajadores dentro de alcance | Acceso horizontal | S1 |

### Fase 3 - Plantillas de turno

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-020 | Turno diurno | `rh` | `/scheduling/shifts` | Crear 08:00-16:00 | Preview muestra 8 h | Segmentos invalidos | S2 |
| H-021 | Turno nocturno | `rh` | `/scheduling/shifts` | Crear 22:00-06:00 +1 dia | Cruza medianoche | Offset incorrecto | S2 |
| H-022 | Turno dividido | `rh` | `/scheduling/shifts` | Crear trabajo-descanso-trabajo | Duracion total y efectivo correctos | Metricas incorrectas | S2 |
| H-023 | Pausa pagada/no pagada | `rh` | `/scheduling/shifts` | Probar descansos | Pagado no reduce efectivo; no pagado por duracion si reduce | Calculo visual incorrecto | S3 |
| H-024 | Inactivar | `rh` | `/scheduling/shifts` | Inactivar/reactivar | No destruye historial | Perdida catalogo | S3 |

### Fase 4 - Perfiles

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-030 | Weekly | `rh.office` | `/scheduling/profiles` | Revisar perfil semanal | 7 reglas semanales | Regla incompleta | S2 |
| H-031 | Cycle | `rh.cycle` | `/scheduling/profiles` | Revisar ciclo | Dias consecutivos desde ancla | Ciclo mal resuelto | S2 |
| H-032 | Calendar | `rh.store` | `/scheduling/profiles` | Revisar calendario | No autogenera turno; queda pendiente | Publicacion incompleta | S2 |
| H-033 | Flexible | `rh.flex` | `/scheduling/profiles` | Revisar minutos/ventana | No crea segmentos de turno | Modelo incorrecto | S2 |
| H-034 | On call | `rh.oncall` | `/scheduling/profiles` | Revisar disponibilidad | No cuenta como trabajo real | Pago/calc inventado | S2 |
| H-035 | Herencia | `rh` | `/scheduling/profile-assignments` | Probar empresa->centro->unidad->relacion | Gana lo mas especifico | Resolucion incorrecta | S2 |

### Fase 5 - Programacion diaria

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-040 | Crear lote | `rh` | `/scheduling/daily` | Crear lote vacio | Queda `Borrador` | Batch incorrecto | S2 |
| H-041 | Crear y generar | `rh` | `/scheduling/daily` | Crear y generar desde perfiles | Dias creados en draft | Generacion rota | S2 |
| H-042 | Missing only | `rh` | `/scheduling/daily` | Ejecutar generar faltantes | Solo llena vacios | Sobrescritura | S2 |
| H-043 | Refresh | `rh` | `/scheduling/daily` | Actualizar desde perfiles | Conserva manual/csv/api externo | Perdida manual | S1 |
| H-044 | Pendiente sin perfil | `rh` | `/scheduling/daily` | Revisar empresa sin perfil | Muestra pendiente | Publicacion incompleta | S2 |
| H-045 | Edicion individual | `rh` | `/scheduling/daily` | Cambiar un dia | `source_type=manual` | Escritura directa | S2 |
| H-046 | Edicion masiva | `rh` | `/scheduling/daily` | Aplicar rango/trabajadores | Todo o nada | Parcialidad | S1 |

### Fase 6 - Publicacion

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-050 | Validacion con pendientes | `rh` | `/scheduling/daily` | Revisar lote con `unassigned` | Bloquea publicacion | Publicacion incompleta | S1 |
| H-051 | Validacion correcta | `rh` | `/scheduling/daily` | Revisar lote completo | Listo para publicar | Falso bloqueo | S2 |
| H-052 | Publicar | `rh` | `/scheduling/daily` | Confirmar publicar | Estado `Publicado`, hash visible | Evidencia incompleta | S1 |
| H-053 | Solo lectura | `rh` | `/scheduling/daily` | Intentar editar publicado | No permite editar | Modificacion de evidencia | S1 |
| H-054 | Integridad | `rh` | `/scheduling/daily` | Verificar hash | Integridad verificada | Hash inconsistente | S1 |
| H-055 | Resolver published | Tecnico | Prueba automatizada | Revisar resolver | Devuelve version publicada | Resolver incorrecto | S2 |

### Fase 7 - Correccion

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-060 | Crear v2 | `rh` | `/scheduling/daily` | Crear correccion con motivo | v2 draft clonada | Version corrupta | S1 |
| H-061 | v1 vigente | `rh` | `/scheduling/daily` | Consultar mientras v2 draft | v1 sigue publicada | Resolver version mala | S1 |
| H-062 | Modificar y comparar | `rh` | `/scheduling/daily` | Cambiar dia y comparar | Muestra diferencias | Comparacion mala | S2 |
| H-063 | Publicar v2 | `rh` | `/scheduling/daily` | Publicar correccion | v1 `superseded`, v2 `published` | Versionado roto | S1 |
| H-064 | Hashes | `rh` | `/scheduling/daily` | Revisar ambas versiones | Hashes se conservan | Evidencia perdida | S1 |
| H-065 | Bloquear rama paralela | `rh` | `/scheduling/daily` | Intentar segunda correccion paralela | Bloqueado | Versiones paralelas | S1 |

### Fase 8 - CSV

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-070 | Descargar plantilla | `rh.store` | `/scheduling/daily` | Descargar plantilla | 15 encabezados version 1 | Contrato roto | S2 |
| H-071 | CSV valido | `rh.store` | `/scheduling/daily` | Cargar CSV valido | Preview sin errores | Parser roto | S2 |
| H-072 | CSV invalido | `rh.store` | `/scheduling/daily` | Cargar trabajador/turno invalido | Errores por fila, no aplica | Aplicacion parcial | S1 |
| H-073 | Preserve | `rh.store` | `/scheduling/daily` | Usar `preserve_existing` | Omite existentes | Sobrescritura | S2 |
| H-074 | Replace | `rh.store` | `/scheduling/daily` | Usar `replace_existing` | Reemplaza existentes | No aplica cambios | S2 |
| H-075 | No-op | `rh.store` | `/scheduling/daily` | Fila sin cambio funcional | Omitida/advertencia | Duplicado innecesario | S3 |
| H-076 | Descargar errores | `rh.store` | `/scheduling/daily` | Descargar reporte | CSV sin rutas privadas | Exposicion info | S2 |
| H-077 | Formula CSV | `rh.store` | `/scheduling/daily` | Usar valores `=`, `+`, `-`, `@` | Salen protegidos | Inyeccion CSV | S2 |
| H-078 | Stale preview | `rh.store` | `/scheduling/daily` | Cambiar dia tras validar | Aplicacion bloqueada | Preview obsoleto | S1 |
| H-079 | Cancelar | `rh.store` | `/scheduling/daily` | Cancelar con motivo | Estado cancelado | Historial incompleto | S3 |
| H-080 | Corrective draft | `rh.office` | `/scheduling/daily` | Importar sobre v2 draft | No modifica v1 publicada | Evidencia perdida | S1 |
| H-081 | No publicar | `rh.store` | `/scheduling/daily` | Aplicar CSV | Lote sigue draft | Publicacion accidental | S1 |

### Fase 9 - Roles

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-090 | Owner | `owner` | Horarios | Crear/editar/publicar | Permitido en empresa | Rol roto | S2 |
| H-091 | Admin | `admin` | Horarios | Crear/editar/publicar | Permitido en empresa | Rol roto | S2 |
| H-092 | RH | `rh` | Horarios | Crear/editar/publicar | Permitido en empresa | Rol roto | S2 |
| H-093 | Supervisor | `supervisor` | Horarios | Consultar segun alcance | Solo lectura o bloqueado segun policy | Permiso excesivo | S2 |
| H-094 | Otra empresa | usuario externo | Horarios | Manipular IDs | Bloqueado | Multi-tenant | S1 |
| H-095 | Membresia inactiva | usuario preparado | Horarios | Intentar acceso | Bloqueado | Seguridad | S1 |

### Fase 10 - Responsive

| ID | Objetivo | Rol | Ruta | Pasos | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|---|---|
| H-100 | Escritorio | `rh` | `/scheduling/daily` | Revisar calendario | Tabla usable | UX bloqueante | S3 |
| H-101 | Tablet | `rh` | `/scheduling/daily` | Reducir viewport | Controles no se enciman | UX bloqueante | S3 |
| H-102 | Movil | `rh` | `/scheduling/daily` | Usar vista movil | Lista legible | UX bloqueante | S3 |
| H-103 | CSV movil | `rh` | `/scheduling/daily` | Abrir panel CSV | Carga/preview usable | UX | S3 |

### Fase 11 - Integridad tecnica

| ID | Objetivo | Metodo | Resultado esperado | Riesgo | Severidad |
|---|---|---|---|---|---|
| H-110 | UTC/timezone | Revisar pruebas F1/F2/F5 | UTC calculado con timezone del centro | Tiempo incorrecto | S2 |
| H-111 | Offsets | Revisar turno nocturno | Offsets 0/1 coherentes | Cruce malo | S2 |
| H-112 | Company_id | Pruebas multi-tenant | Sin datos cruzados | Mezcla empresas | S1 |
| H-113 | Source reference | Revisar registros | JSON estable, sin timestamps innecesarios cuando aplica | Trazabilidad mala | S3 |
| H-114 | Snapshot canonico | Verificar hash | Hash coincide | Evidencia corrupta | S1 |
| H-115 | Version chain | Historial F4 | Cadena lineal | Version corrupta | S1 |
| H-116 | Archivos privados | Revisar `import_batches.storage_path` | No esta en `public/` ni expuesto en UI | Exposicion archivo | S2 |
| H-117 | Escritura Livewire | Revision de codigo | Livewire llama Actions | Duplicacion dominio | S2 |

## 5. Caso completo guiado

Usar preferentemente `rh.office.demo@veratime.local` con contrasena `VeraDemo123!` para publicacion/correccion, y `rh.store.demo@veratime.local` para CSV.

1. Iniciar sesion.
   - Esperado: dashboard carga con empresa activa.
2. Revisar centro en `/centers`.
   - Esperado: centro activo con timezone.
3. Revisar unidad en `/organization/units`.
   - Esperado: jerarquia visible.
4. Revisar trabajadores en `/workers`.
   - Esperado: trabajadores activos con relaciones laborales.
5. Crear o revisar turno en `/scheduling/shifts`.
   - Esperado: preview muestra segmentos y metricas.
6. Crear o revisar perfil semanal en `/scheduling/profiles`.
   - Esperado: siete reglas semanales.
7. Asignar perfil en `/scheduling/profile-assignments`.
   - Esperado: perfil efectivo por jerarquia.
8. Crear lote en `/scheduling/daily`.
   - Esperado: lote `Borrador`.
9. Generar desde perfiles.
   - Esperado: dias creados; calendar/sin perfil pueden quedar pendientes.
10. Modificar un dia.
    - Esperado: fuente `Manual`.
11. Validar.
    - Esperado: si no hay pendientes, listo para publicar.
12. Publicar.
    - Esperado: estado `Publicado`, hash SHA-256.
13. Verificar integridad.
    - Esperado: integridad verificada.
14. Crear correccion.
    - Esperado: v2 `Borrador`, v1 sigue vigente.
15. Modificar v2.
    - Esperado: cambio solo en v2.
16. Comparar.
    - Esperado: diferencias claras.
17. Publicar correccion.
    - Esperado: v1 `Sustituido`, v2 `Publicado`.
18. Con `rh.store.demo@veratime.local`, descargar plantilla CSV.
    - Esperado: 15 encabezados.
19. Crear o abrir otro draft.
    - Esperado: lote editable.
20. Cargar CSV.
    - Esperado: preview muestra filas validas/invalidas.
21. Validar/aplicar.
    - Esperado: filas validas actualizan draft.
22. Confirmar que sigue draft.
    - Esperado: CSV no publica automaticamente.

## 6. Comandos de apoyo

```bash
php artisan route:list --path=scheduling
php artisan test tests/Feature/BlockC --stop-on-failure
php artisan test tests/Feature/BlockD1 --stop-on-failure
php artisan test tests/Feature/BlockD2 --stop-on-failure
php artisan test tests/Feature/BlockE1 --stop-on-failure
php artisan test tests/Feature/BlockE2 --stop-on-failure
php artisan test tests/Feature/BlockF1 --stop-on-failure
php artisan test tests/Feature/BlockF2 --stop-on-failure
php artisan test tests/Feature/BlockF3A --stop-on-failure
php artisan test tests/Feature/BlockF3B --stop-on-failure
php artisan test tests/Feature/BlockF4 --stop-on-failure
php artisan test tests/Feature/BlockF5A --stop-on-failure
php artisan test tests/Feature/BlockF5B --stop-on-failure
```
