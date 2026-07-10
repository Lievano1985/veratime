# Guía funcional de pruebas manuales por sprint - Vera Time

## Objetivo

Esta guía sirve para que una persona no técnica pueda revisar manualmente el avance de Vera Time por fases.

La idea no es revisar código. La idea es entrar a la plataforma, navegar por las pantallas disponibles y confirmar que cada sprint funciona según lo construido y aprobado.

Cada sección indica:

- qué funcionalidad debe existir;
- con qué usuario probar;
- en qué pantalla probar;
- qué pasos seguir;
- qué resultado se espera;
- qué cosas todavía no deberían existir;
- observaciones o pendientes.

---

## Preparación general antes de probar

### Usuarios recomendados

| Usuario | Para qué sirve |
|---|---|
| Administrador de empresa | Probar administración de empresas, centros, trabajadores y horarios. |
| Usuario con acceso a dos empresas | Probar selector de empresa y separación de datos. |
| Usuario sin empresa activa | Validar bloqueo de acceso operativo. |
| Usuario de otra empresa | Confirmar que no vea información ajena. |
| Rol no autorizado | Validar permisos para crear, editar o inactivar información. |

### Datos recomendados

Tener disponibles, conforme avance cada sprint:

- Empresa A activa.
- Empresa B activa.
- Una empresa inactiva o suspendida.
- Al menos dos centros de trabajo.
- Al menos dos personas trabajadoras.
- Al menos un horario normal.
- Al menos un horario nocturno que cruce medianoche.
- Al menos una asignación de horario a trabajador, solo cuando Sprint 2B esté disponible.

### Regla general de prueba

En todos los sprints se debe revisar que:

- un usuario de Empresa A no vea datos de Empresa B;
- un usuario sin empresa activa no pueda operar;
- una empresa inactiva no permita operaciones normales;
- los datos dados de baja, reemplazados o inactivados no se borren físicamente;
- no aparezcan módulos futuros como terminados antes de tiempo.

---

## Criterios para reportar problemas

### Problemas críticos

Reportar como crítico si ocurre cualquiera de estos casos:

- un usuario de una empresa ve datos de otra empresa;
- un usuario sin empresa activa puede operar;
- una empresa inactiva permite crear o modificar datos;
- una baja elimina historial;
- un cambio de relación laboral borra historial;
- un cambio de condición laboral sobrescribe historial;
- el NIP queda visible en texto claro después de guardar;
- una asignación futura modifica historial pasado;
- aparecen módulos futuros como si ya estuvieran terminados;
- aparecen contadores falsos de jornadas, alertas o incidencias.

### Mejoras de UX

Reportar como mejora si ocurre cualquiera de estos casos:

- pantalla confusa;
- mensajes poco claros;
- botones poco entendibles;
- falta de confirmación visual;
- tabla sin filtros;
- pantalla saturada.

---

## Sprint 0 - Base técnica, acceso y multiempresa

**Estado:** Cerrado.

### Funcionalidad esperada

- Login y logout.
- Usuarios activos pueden entrar.
- Usuarios inactivos no deben entrar.
- Empresa activa o contexto de empresa.
- Relación usuario-empresa.
- Roles iniciales.
- Protección multi-tenant.
- Configuración base con Laravel, Livewire, Tailwind, MySQL/MariaDB y database queue.
- Registro público deshabilitado.

### Usuario o rol para probar

- Usuario activo con empresa activa.
- Usuario inactivo.
- Usuario sin empresa activa.
- Usuario con acceso a otra empresa.

### Ruta o pantalla

- `/login`
- Pantalla principal después de iniciar sesión.
- Cualquier pantalla protegida del sistema.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Login correcto | Entrar a `/login`, capturar credenciales de usuario activo y presionar iniciar sesión. | El usuario entra correctamente y trabaja bajo una empresa activa. |
| Usuario inactivo | Entrar a `/login` con credenciales de usuario inactivo. | El sistema no permite acceso. |
| Usuario sin empresa activa | Iniciar sesión con usuario sin empresa activa e intentar entrar a pantalla protegida. | El sistema bloquea el acceso operativo. |
| Protección entre empresas | Entrar como Empresa A e intentar abrir un registro de Empresa B. | El sistema bloquea el acceso. |

### No debería existir todavía

- Registro de jornada.
- Motor legal.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.
- ClickBalance.
- Biometría.
- App nativa.
- Módulos de nómina.
- API de negocio completa.

### Observaciones

Este sprint es base técnica. La mayoría de pruebas son de acceso, seguridad y separación por empresa.

---

## Sprint 1A - Empresa, selector y configuración básica

**Estado:** Cerrado.

### Funcionalidad esperada

- Selector de empresa.
- Administración básica de empresa.
- Configuración básica de empresa.
- Datos básicos de la empresa.
- Estado de la empresa.
- Cambio de empresa activa cuando el usuario tenga permiso.

### Usuario o rol para probar

- Administrador de empresa.
- Usuario con acceso a dos empresas.
- Usuario sin permiso sobre otra empresa.

### Ruta o pantalla

- Pantalla de empresa o configuración de empresa.
- Selector de empresa en encabezado o menú.
- Pantalla principal después del login.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Ver empresa activa | Iniciar sesión y revisar qué empresa aparece como activa. | La información corresponde a esa empresa. |
| Cambiar empresa activa | Usar un usuario con Empresa A y Empresa B, abrir selector y cambiar empresa. | Solo muestra empresas autorizadas y no mezcla datos. |
| Editar datos básicos | Modificar un dato permitido, guardar y recargar. | El cambio se conserva y solo afecta la empresa activa. |
| Empresa inactiva | Intentar operar con empresa inactiva. | El sistema bloquea o impide operaciones. |

### No debería existir todavía

- Centros completos si no se está probando Sprint 1B.
- Trabajadores.
- Relaciones laborales.
- Condiciones laborales.
- Credenciales de kiosco.
- Horarios.
- Registro de jornada.
- Alertas.
- Incidencias.
- Dashboard operativo completo.

### Observaciones

`BL-0205` Dashboard inicial no debe considerarse cerrado aquí. Se reubicó para una fase posterior porque depende de jornadas, alertas e incidencias.

---

## Sprint 1B - Centros de trabajo

**Estado:** Cerrado.

### Funcionalidad esperada

- Pantalla de centros de trabajo.
- Crear centro.
- Editar centro.
- Inactivar centro.
- Código único por empresa.
- Zona horaria por centro.
- Separación de centros por empresa.

### Usuario o rol para probar

- Administrador de empresa.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/centers`
- Menú Centros o Centros de trabajo.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear centro | En Empresa A, crear centro con código, nombre y zona horaria. | El centro se crea y aparece solo en Empresa A. |
| Código duplicado | Crear dos centros con el mismo código en la misma empresa. | El sistema rechaza el duplicado. |
| Mismo código en otra empresa | Cambiar a Empresa B y usar el mismo código. | El sistema lo permite porque la regla es por empresa. |
| Editar centro | Cambiar nombre, zona horaria o estado permitido. | Los cambios se guardan sin afectar otra empresa. |
| Inactivar centro | Inactivar un centro activo. | Queda inactivo, no eliminado. |
| Acceso cruzado | Intentar abrir o modificar un centro de otra empresa. | El sistema bloquea la acción. |

### No debería existir todavía

- Trabajadores ligados a centros si no se prueba Sprint 1C.
- Horarios asignados a centros.
- Registro de jornada por centro.
- Reportes por centro.
- Alertas o incidencias por centro.

### Observaciones

La zona horaria del centro se guarda, pero todavía no debe generar cálculos de jornada.

---

## Sprint 1C - Trabajadores y relaciones laborales

**Estado:** Cerrado.

### Funcionalidad esperada

- Pantalla de personas trabajadoras.
- Crear trabajador.
- Editar datos básicos.
- Baja no destructiva.
- Relación laboral con centro, puesto y fecha.
- Historial de relaciones laborales.
- Cambio de centro o puesto sin borrar historial.

### Usuario o rol para probar

- Administrador de empresa.
- Recursos humanos, si el rol existe en la instalación.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/workers`
- Menú Personas trabajadoras o Trabajadores.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear trabajador | Crear persona trabajadora con código, nombre, centro y puesto inicial. | Se crea en la empresa activa y tiene relación laboral activa. |
| Código duplicado | Usar el mismo código en la misma empresa. | El sistema rechaza el duplicado. |
| Mismo código en otra empresa | Cambiar a Empresa B y usar el mismo código. | El sistema lo permite. |
| Editar datos básicos | Cambiar teléfono o correo sin cambiar centro/puesto. | No se crea nueva relación laboral. |
| Cambiar centro o puesto | Cambiar centro, puesto o fecha de nueva relación. | Se cierra la relación anterior y se conserva historial. |
| Baja no destructiva | Dar de baja o terminar un trabajador. | Cambia estado, no se elimina, y se conserva historial. |
| Centro de otra empresa | Intentar asignar centro de Empresa B a trabajador de Empresa A. | El sistema bloquea la operación. |

### No debería existir todavía

- Condiciones laborales con vigencia si no se prueba Sprint 1D.
- Credenciales de kiosco si no se prueba Sprint 1D.
- Horarios.
- Asignación de horarios.
- Registro de jornada.
- Jornadas calculadas.
- Alertas.
- Incidencias.
- Reportes por trabajador.
- Portal del trabajador.

### Observaciones

`BL-0307` Detalle completo del trabajador y `BL-0306` Importación CSV siguen pendientes.

---

## Sprint 1D - Condiciones laborales y credenciales de kiosco

**Estado:** Cerrado.

### Funcionalidad esperada

- Condiciones laborales con vigencia.
- Reemplazo de condiciones sin destruir historial.
- Validación para evitar solapamientos activos.
- Credenciales de kiosco por trabajador.
- Código de acceso.
- NIP guardado de forma segura, no visible.
- Reset de NIP.
- Bloqueo de credencial.

### Usuario o rol para probar

- Administrador de empresa.
- Recursos humanos, si aplica.
- Usuario de otra empresa.
- Rol no autorizado.

### Ruta o pantalla

- `/workers`
- Sección de condiciones laborales dentro del trabajador.
- Sección de credenciales de kiosco dentro del trabajador.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear condición laboral | Crear condición con modalidad, horas semanales, descanso y fecha de inicio. | La condición se guarda y queda vigente. |
| Reemplazar condición | Crear nueva condición con fecha posterior. | La anterior se cierra y el historial se conserva. |
| Solapamiento | Crear condición que se empalme con una vigente. | El sistema rechaza el solapamiento. |
| Crear credencial | Crear credencial con código y NIP temporal. | La credencial se crea y el NIP no queda visible. |
| Resetear NIP | Ingresar nuevo NIP temporal. | El NIP se actualiza sin mostrarse en texto claro. |
| Bloquear credencial | Bloquear credencial activa. | Queda bloqueada, no eliminada. |

### No debería existir todavía

- Kiosco operativo real.
- Registro de entrada/salida.
- Eventos de jornada.
- Uso real del NIP para checar entrada.
- Cálculo de jornada.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.

### Observaciones

Las credenciales existen como preparación para kiosco, pero el kiosco todavía no debe operar.

---

## Sprint 2A - Horarios base y pausas programadas

**Estado:** Cerrado.

### Funcionalidad esperada

- Pantalla de horarios.
- Crear horario.
- Editar horario.
- Inactivar horario.
- Días del horario.
- Hora de entrada y salida por día.
- Tipo legal del horario.
- Pausas programadas.
- Código único por empresa.
- Separación multiempresa.

### Usuario o rol para probar

- Administrador de empresa.
- Recursos humanos, si tiene permisos.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/schedules`
- Menú Horarios.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear horario básico | Crear horario con código, nombre, tipo legal y estado activo. | El horario se crea en la empresa activa. |
| Código duplicado | Crear dos horarios con el mismo código en la misma empresa. | El sistema rechaza el duplicado. |
| Mismo código en otra empresa | Cambiar a Empresa B y usar el mismo código. | El sistema lo permite. |
| Configurar días laborales | Marcar día laboral y capturar entrada/salida. | El día se guarda y exige horarios. |
| Día no laboral | Marcar día no laboral y dejar horas vacías. | El sistema permite guardar sin calcular jornada. |
| Pausa programada | Agregar pausa con nombre, duración o rango horario. | La pausa queda asociada al día correcto. |
| Pausa inválida | Capturar duración negativa. | El sistema rechaza la pausa. |
| Inactivar horario | Inactivar horario activo. | Queda inactivo, no eliminado. |

### No debería existir todavía

- Asignación de horario a trabajador, si Sprint 2B no está cerrado.
- Validación completa de cruce de medianoche, si Sprint 2B no está cerrado.
- Descansos obligatorios.
- Registro de entrada/salida.
- Eventos de jornada.
- Kiosco operativo.
- Motor legal.
- Cálculo de horas.
- Alertas.
- Incidencias.
- Reportes.

### Observaciones

En Sprint 2A `crosses_midnight` puede existir visualmente, pero todavía no debe ejecutar lógica avanzada.

---

## Sprint 2B - Horarios con cruce de medianoche, asignaciones y vigencias

**Estado:** En revisión o pendiente de cierre. Candidato a cierre con validaciones automatizadas OK.

Esta sección solo debe usarse como guía formal cuando Sprint 2B esté en la rama de prueba correspondiente o ya cerrado en `main`.

### Funcionalidad esperada al cerrar el sprint

- Validación de horarios que cruzan medianoche.
- Asignación de horario a trabajador.
- Vigencia de asignación por fecha efectiva.
- Reemplazo de asignación sin borrar historial.
- Inactivación de asignación sin eliminarla.
- Resolución del horario vigente por trabajador y fecha.
- Pantalla simple de asignaciones.

### Usuario o rol para probar

- Administrador de empresa.
- Recursos humanos, si tiene permisos.
- Usuario de otra empresa.
- Rol no autorizado.

### Ruta o pantalla

- `/schedule-assignments`
- `/schedules` para validar horarios que cruzan medianoche.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Horario normal | Crear o editar horario con entrada `08:00`, salida `17:00` y sin cruce de medianoche. | El horario se guarda sin calcular jornada ni generar alertas. |
| Cruce válido | Crear día laboral con entrada `22:00`, salida `06:00` y cruce de medianoche activo. | El sistema permite guardar y registra el cruce. |
| Cruce inválido | Capturar `22:00` a `06:00` sin marcar cruce de medianoche. | El sistema rechaza la configuración. |
| Crear asignación | Seleccionar trabajador, horario y fecha efectiva. | La asignación se crea sin generar jornadas ni eventos. |
| Reemplazar asignación | Crear nueva asignación con otro horario y fecha posterior. | La anterior se cierra y la nueva queda vigente desde la fecha indicada. |
| Evitar solapamientos | Crear asignación que se empalme con otra activa del mismo trabajador. | El sistema rechaza el solapamiento. |
| Resolver horario por fecha | Revisar horario vigente en una fecha anterior y otra posterior. | El sistema identifica el horario correcto según la fecha. |
| Inactivar asignación | Inactivar una asignación activa. | La asignación se inactiva y no se elimina. |
| Horario de otra empresa | Intentar asignar horario de Empresa B a trabajador de Empresa A. | El sistema bloquea la operación. |
| Trabajador de otra empresa | Intentar asignar horario a trabajador de Empresa B desde Empresa A. | El sistema bloquea la operación. |

### No debería existir todavía

- Descansos obligatorios `BL-0405`.
- Registro de jornada.
- Modelo `time_events`.
- Entrada/salida web.
- Registro de pausas reales.
- Kiosco operativo.
- Captura manual justificada.
- Motor legal.
- Cálculos de jornada.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.
- API de negocio.
- Importación CSV.

### Observaciones

Este sprint prepara vigencias para que futuras jornadas usen el horario correcto.

Validaciones finales reportadas para cierre:

- `php artisan migrate:fresh --seed` OK.
- `php artisan test tests\Feature\Sprint2B\ScheduleAssignmentManagementTest.php` OK: 26 pruebas, 60 assertions.
- `php artisan test` OK: 164 pruebas, 419 assertions.
- `npm.cmd run build` OK.

Arquitectura y QA quedaron aprobados con observaciones menores S3. No hay S1 ni S2 reportados.

Todavía no debe existir cálculo de horas ni clasificación legal de jornada como diurna, nocturna o mixta.

Si una asignación futura cambia datos históricos, debe reportarse como error crítico.

---

## Sprint 2C - Descansos obligatorios

**Estado:** En revision o pendiente de cierre. Candidato a cierre con validaciones automatizadas OK.

### Funcionalidad esperada al cerrar el sprint

- Catalogo de descansos obligatorios.
- Alcance global, empresa o centro.
- Alta y edicion desde empresa para alcances empresa y centro.
- Visualizacion de descansos globales sin permitir modificarlos desde empresa.
- Inactivacion no destructiva.
- Resolucion por fecha desde dominio.

### Usuario o rol para probar

- Administrador de empresa.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/mandatory-rest-days`

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear descanso por empresa | Crear descanso con fecha, nombre y alcance Empresa. | Se guarda en la empresa activa. |
| Crear descanso por centro | Seleccionar alcance Centro y un centro activo de la empresa. | Se guarda asociado al centro correcto. |
| Editar descanso | Modificar nombre, fecha, fuente o centro de un descanso permitido. | Se actualiza sin cambiar a otra empresa ni borrar historial. |
| Duplicado mismo alcance/fecha/nombre | Intentar crear otro descanso con el mismo alcance, fecha y nombre. | El sistema rechaza el duplicado. |
| Centro de otra empresa | Intentar usar un centro ajeno. | El sistema bloquea la operacion. |
| Alcance global desde UI | Intentar forzar alcance global desde UI de empresa. | El sistema lo rechaza; los globales son solo lectura para empresa. |
| Inactivar descanso | Inactivar un descanso activo. | El registro queda inactive y no se borra. |
| Multiempresa | Entrar con Empresa A y revisar datos de Empresa B. | No aparecen descansos de Empresa B. |

### No deberia existir todavia

- Modelo `time_events`.
- Registro de entrada/salida.
- Registro de pausas reales.
- Kiosco operativo.
- Captura manual justificada.
- Motor legal.
- Calculo de horas o jornadas.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.

---
## Pendientes globales que no deben marcarse como listos

| Pendiente | Nota |
|---|---|
| `BL-0205` Dashboard inicial | Depende de jornadas, alertas e incidencias reales. |
| `BL-0306` Importación CSV de trabajadores | No debe aparecer como lista todavía. |
| `BL-0307` Detalle completo de trabajador | Faltan jornadas, alertas, incidencias y reportes. |
| `BL-0405` Descansos obligatorios | Implementado en Sprint 2C; candidato a cierre si las pruebas manuales y automatizadas pasan. |
| `BL-0501` a `BL-0505` Registro electrónico | Pendiente; no debe haber eventos, kiosco operativo ni captura manual justificada. |
| API y motor legal | Pendientes; no debe existir API de negocio completa ni cálculo legal. |
| Alertas, incidencias, cierres y reportes | Pendientes; no deben considerarse listos. |

---

## Checklist rápido por fase

Usar esta tabla para marcar validación manual. En observación anotar pantalla, usuario y caso probado.

| Fase | Punto a validar | Probado | Correcto | Falla | Observación |
|---|---|---|---|---|---|
| Sprint 0 | Login funciona |  |  |  |  |
| Sprint 0 | Logout funciona |  |  |  |  |
| Sprint 0 | Usuario inactivo no entra |  |  |  |  |
| Sprint 0 | Usuario sin empresa activa queda bloqueado |  |  |  |  |
| Sprint 0 | Usuario de Empresa A no ve Empresa B |  |  |  |  |
| Sprint 0 | Registro público no está disponible |  |  |  |  |
| Sprint 1A | Se ve empresa activa |  |  |  |  |
| Sprint 1A | Selector solo muestra empresas permitidas |  |  |  |  |
| Sprint 1A | Se puede cambiar empresa activa |  |  |  |  |
| Sprint 1A | Datos básicos de empresa se guardan |  |  |  |  |
| Sprint 1A | Empresa inactiva no permite operar |  |  |  |  |
| Sprint 1B | Se pueden crear centros |  |  |  |  |
| Sprint 1B | Código de centro es único por empresa |  |  |  |  |
| Sprint 1B | Se puede inactivar centro sin borrarlo |  |  |  |  |
| Sprint 1C | Se puede crear trabajador |  |  |  |  |
| Sprint 1C | Código de trabajador es único por empresa |  |  |  |  |
| Sprint 1C | Cambio de relación laboral conserva historial |  |  |  |  |
| Sprint 1C | Baja no borra trabajador |  |  |  |  |
| Sprint 1D | Se puede crear condición laboral |  |  |  |  |
| Sprint 1D | Reemplazo de condición conserva historial |  |  |  |  |
| Sprint 1D | NIP no se muestra en texto claro |  |  |  |  |
| Sprint 1D | Se puede bloquear credencial |  |  |  |  |
| Sprint 2A | Se puede crear horario |  |  |  |  |
| Sprint 2A | Código de horario es único por empresa |  |  |  |  |
| Sprint 2A | Día laboral requiere entrada y salida |  |  |  |  |
| Sprint 2A | Se pueden agregar pausas programadas |  |  |  |  |
| Sprint 2A | Se puede inactivar horario sin borrarlo |  |  |  |  |
| Sprint 2B | Horario `22:00` a `06:00` requiere cruce de medianoche |  |  |  |  |
| Sprint 2B | Horario normal `08:00` a `17:00` funciona |  |  |  |  |
| Sprint 2B | Se puede asignar horario a trabajador |  |  |  |  |
| Sprint 2B | Se puede reemplazar asignación |  |  |  |  |
| Sprint 2B | Se conserva historial |  |  |  |  |
| Sprint 2B | No se permiten solapamientos |  |  |  |  |
| Sprint 2B | Se puede inactivar asignacion sin borrar |  |  |  |  |
| Sprint 2C | Se puede crear descanso obligatorio por empresa |  |  |  |  |
| Sprint 2C | Se puede crear descanso obligatorio por centro |  |  |  |  |
| Sprint 2C | Se puede editar descanso obligatorio permitido |  |  |  |  |
| Sprint 2C | Duplicado por mismo alcance, fecha y nombre se rechaza |  |  |  |  |
| Sprint 2C | Centro de otra empresa se bloquea |  |  |  |  |
| Sprint 2C | No se puede crear descanso global desde UI de empresa |  |  |  |  |
| Sprint 2C | Usuario de Empresa A no ve descansos de Empresa B |  |  |  |  |
| Sprint 2C | Inactivar descanso no lo borra |  |  |  |  |
