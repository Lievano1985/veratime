# GuÃ­a funcional de pruebas manuales por sprint - Vera Time

## Objetivo

Esta guÃ­a sirve para que una persona no tÃ©cnica pueda revisar manualmente el avance de Vera Time por fases.

La idea no es revisar cÃ³digo. La idea es entrar a la plataforma, navegar por las pantallas disponibles y confirmar que cada sprint funciona segÃºn lo construido y aprobado.

Cada secciÃ³n indica:

- quÃ© funcionalidad debe existir;
- con quÃ© usuario probar;
- en quÃ© pantalla probar;
- quÃ© pasos seguir;
- quÃ© resultado se espera;
- quÃ© cosas todavÃ­a no deberÃ­an existir;
- observaciones o pendientes.

---

## PreparaciÃ³n general antes de probar

### Usuarios recomendados

| Usuario | Para quÃ© sirve |
|---|---|
| Administrador de empresa | Probar administraciÃ³n de empresas, centros, trabajadores y horarios. |
| Usuario con acceso a dos empresas | Probar selector de empresa y separaciÃ³n de datos. |
| Usuario sin empresa activa | Validar bloqueo de acceso operativo. |
| Usuario de otra empresa | Confirmar que no vea informaciÃ³n ajena. |
| Rol no autorizado | Validar permisos para crear, editar o inactivar informaciÃ³n. |

### Datos recomendados

Tener disponibles, conforme avance cada sprint:

- Empresa A activa.
- Empresa B activa.
- Una empresa inactiva o suspendida.
- Al menos dos centros de trabajo.
- Al menos dos personas trabajadoras.
- Al menos un horario normal.
- Al menos un horario nocturno que cruce medianoche.
- Al menos una asignaciÃ³n de horario a trabajador, solo cuando Sprint 2B estÃ© disponible.

### Regla general de prueba

En todos los sprints se debe revisar que:

- un usuario de Empresa A no vea datos de Empresa B;
- un usuario sin empresa activa no pueda operar;
- una empresa inactiva no permita operaciones normales;
- los datos dados de baja, reemplazados o inactivados no se borren fÃ­sicamente;
- no aparezcan mÃ³dulos futuros como terminados antes de tiempo.

---

## Criterios para reportar problemas

### Problemas crÃ­ticos

Reportar como crÃ­tico si ocurre cualquiera de estos casos:

- un usuario de una empresa ve datos de otra empresa;
- un usuario sin empresa activa puede operar;
- una empresa inactiva permite crear o modificar datos;
- una baja elimina historial;
- un cambio de relaciÃ³n laboral borra historial;
- un cambio de condiciÃ³n laboral sobrescribe historial;
- el NIP queda visible en texto claro despuÃ©s de guardar;
- una asignaciÃ³n futura modifica historial pasado;
- aparecen mÃ³dulos futuros como si ya estuvieran terminados;
- aparecen contadores falsos de jornadas, alertas o incidencias.

### Mejoras de UX

Reportar como mejora si ocurre cualquiera de estos casos:

- pantalla confusa;
- mensajes poco claros;
- botones poco entendibles;
- falta de confirmaciÃ³n visual;
- tabla sin filtros;
- pantalla saturada.

---

## Sprint 0 - Base tÃ©cnica, acceso y multiempresa

**Estado:** Cerrado.

### Funcionalidad esperada

- Login y logout.
- Usuarios activos pueden entrar.
- Usuarios inactivos no deben entrar.
- Empresa activa o contexto de empresa.
- RelaciÃ³n usuario-empresa.
- Roles iniciales.
- ProtecciÃ³n multi-tenant.
- ConfiguraciÃ³n base con Laravel, Livewire, Tailwind, MySQL/MariaDB y database queue.
- Registro pÃºblico deshabilitado.

### Usuario o rol para probar

- Usuario activo con empresa activa.
- Usuario inactivo.
- Usuario sin empresa activa.
- Usuario con acceso a otra empresa.

### Ruta o pantalla

- `/login`
- Pantalla principal despuÃ©s de iniciar sesiÃ³n.
- Cualquier pantalla protegida del sistema.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Login correcto | Entrar a `/login`, capturar credenciales de usuario activo y presionar iniciar sesiÃ³n. | El usuario entra correctamente y trabaja bajo una empresa activa. |
| Usuario inactivo | Entrar a `/login` con credenciales de usuario inactivo. | El sistema no permite acceso. |
| Usuario sin empresa activa | Iniciar sesiÃ³n con usuario sin empresa activa e intentar entrar a pantalla protegida. | El sistema bloquea el acceso operativo. |
| ProtecciÃ³n entre empresas | Entrar como Empresa A e intentar abrir un registro de Empresa B. | El sistema bloquea el acceso. |

### No deberÃ­a existir todavÃ­a

- Registro de jornada.
- Motor legal.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.
- ClickBalance.
- BiometrÃ­a.
- App nativa.
- MÃ³dulos de nÃ³mina.
- API de negocio completa.

### Observaciones

Este sprint es base tÃ©cnica. La mayorÃ­a de pruebas son de acceso, seguridad y separaciÃ³n por empresa.

---

## Sprint 1A - Empresa, selector y configuraciÃ³n bÃ¡sica

**Estado:** Cerrado.

### Funcionalidad esperada

- Selector de empresa.
- AdministraciÃ³n bÃ¡sica de empresa.
- ConfiguraciÃ³n bÃ¡sica de empresa.
- Datos bÃ¡sicos de la empresa.
- Estado de la empresa.
- Cambio de empresa activa cuando el usuario tenga permiso.

### Usuario o rol para probar

- Administrador de empresa.
- Usuario con acceso a dos empresas.
- Usuario sin permiso sobre otra empresa.

### Ruta o pantalla

- Pantalla de empresa o configuraciÃ³n de empresa.
- Selector de empresa en encabezado o menÃº.
- Pantalla principal despuÃ©s del login.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Ver empresa activa | Iniciar sesiÃ³n y revisar quÃ© empresa aparece como activa. | La informaciÃ³n corresponde a esa empresa. |
| Cambiar empresa activa | Usar un usuario con Empresa A y Empresa B, abrir selector y cambiar empresa. | Solo muestra empresas autorizadas y no mezcla datos. |
| Editar datos bÃ¡sicos | Modificar un dato permitido, guardar y recargar. | El cambio se conserva y solo afecta la empresa activa. |
| Empresa inactiva | Intentar operar con empresa inactiva. | El sistema bloquea o impide operaciones. |

### No deberÃ­a existir todavÃ­a

- Centros completos si no se estÃ¡ probando Sprint 1B.
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

`BL-0205` Dashboard inicial no debe considerarse cerrado aquÃ­. Se reubicÃ³ para una fase posterior porque depende de jornadas, alertas e incidencias.

---

## Sprint 1B - Centros de trabajo

**Estado:** Cerrado.

### Funcionalidad esperada

- Pantalla de centros de trabajo.
- Crear centro.
- Editar centro.
- Inactivar centro.
- CÃ³digo Ãºnico por empresa.
- Zona horaria por centro.
- SeparaciÃ³n de centros por empresa.

### Usuario o rol para probar

- Administrador de empresa.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/centers`
- MenÃº Centros o Centros de trabajo.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear centro | En Empresa A, crear centro con cÃ³digo, nombre y zona horaria. | El centro se crea y aparece solo en Empresa A. |
| CÃ³digo duplicado | Crear dos centros con el mismo cÃ³digo en la misma empresa. | El sistema rechaza el duplicado. |
| Mismo cÃ³digo en otra empresa | Cambiar a Empresa B y usar el mismo cÃ³digo. | El sistema lo permite porque la regla es por empresa. |
| Editar centro | Cambiar nombre, zona horaria o estado permitido. | Los cambios se guardan sin afectar otra empresa. |
| Inactivar centro | Inactivar un centro activo. | Queda inactivo, no eliminado. |
| Acceso cruzado | Intentar abrir o modificar un centro de otra empresa. | El sistema bloquea la acciÃ³n. |

### No deberÃ­a existir todavÃ­a

- Trabajadores ligados a centros si no se prueba Sprint 1C.
- Horarios asignados a centros.
- Registro de jornada por centro.
- Reportes por centro.
- Alertas o incidencias por centro.

### Observaciones

La zona horaria del centro se guarda, pero todavÃ­a no debe generar cÃ¡lculos de jornada.

---

## Sprint 1C - Trabajadores y relaciones laborales

**Estado:** Cerrado.

### Funcionalidad esperada

- Pantalla de personas trabajadoras.
- Crear trabajador.
- Editar datos bÃ¡sicos.
- Baja no destructiva.
- RelaciÃ³n laboral con centro, puesto y fecha.
- Historial de relaciones laborales.
- Cambio de centro o puesto sin borrar historial.

### Usuario o rol para probar

- Administrador de empresa.
- Recursos humanos, si el rol existe en la instalaciÃ³n.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/workers`
- MenÃº Personas trabajadoras o Trabajadores.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear trabajador | Crear persona trabajadora con cÃ³digo, nombre, centro y puesto inicial. | Se crea en la empresa activa y tiene relaciÃ³n laboral activa. |
| CÃ³digo duplicado | Usar el mismo cÃ³digo en la misma empresa. | El sistema rechaza el duplicado. |
| Mismo cÃ³digo en otra empresa | Cambiar a Empresa B y usar el mismo cÃ³digo. | El sistema lo permite. |
| Editar datos bÃ¡sicos | Cambiar telÃ©fono o correo sin cambiar centro/puesto. | No se crea nueva relaciÃ³n laboral. |
| Cambiar centro o puesto | Cambiar centro, puesto o fecha de nueva relaciÃ³n. | Se cierra la relaciÃ³n anterior y se conserva historial. |
| Baja no destructiva | Dar de baja o terminar un trabajador. | Cambia estado, no se elimina, y se conserva historial. |
| Centro de otra empresa | Intentar asignar centro de Empresa B a trabajador de Empresa A. | El sistema bloquea la operaciÃ³n. |

### No deberÃ­a existir todavÃ­a

- Condiciones laborales con vigencia si no se prueba Sprint 1D.
- Credenciales de kiosco si no se prueba Sprint 1D.
- Horarios.
- AsignaciÃ³n de horarios.
- Registro de jornada.
- Jornadas calculadas.
- Alertas.
- Incidencias.
- Reportes por trabajador.
- Portal del trabajador.

### Observaciones

`BL-0307` Detalle completo del trabajador y `BL-0306` ImportaciÃ³n CSV siguen pendientes.

---

## Sprint 1D - Condiciones laborales y credenciales de kiosco

**Estado:** Cerrado.

### Funcionalidad esperada

- Condiciones laborales con vigencia.
- Reemplazo de condiciones sin destruir historial.
- ValidaciÃ³n para evitar solapamientos activos.
- Credenciales de kiosco por trabajador.
- CÃ³digo de acceso.
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
- SecciÃ³n de condiciones laborales dentro del trabajador.
- SecciÃ³n de credenciales de kiosco dentro del trabajador.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear condiciÃ³n laboral | Crear condiciÃ³n con modalidad, horas semanales, descanso y fecha de inicio. | La condiciÃ³n se guarda y queda vigente. |
| Reemplazar condiciÃ³n | Crear nueva condiciÃ³n con fecha posterior. | La anterior se cierra y el historial se conserva. |
| Solapamiento | Crear condiciÃ³n que se empalme con una vigente. | El sistema rechaza el solapamiento. |
| Crear credencial | Crear credencial con cÃ³digo y NIP temporal. | La credencial se crea y el NIP no queda visible. |
| Resetear NIP | Ingresar nuevo NIP temporal. | El NIP se actualiza sin mostrarse en texto claro. |
| Bloquear credencial | Bloquear credencial activa. | Queda bloqueada, no eliminada. |

### No deberÃ­a existir todavÃ­a

- Kiosco operativo real.
- Registro de entrada/salida.
- Eventos de jornada.
- Uso real del NIP para checar entrada.
- CÃ¡lculo de jornada.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.

### Observaciones

Las credenciales existen como preparaciÃ³n para kiosco, pero el kiosco todavÃ­a no debe operar.

---

## Sprint 2A - Horarios base y pausas programadas

**Estado:** Cerrado.

### Funcionalidad esperada

- Pantalla de horarios.
- Crear horario.
- Editar horario.
- Inactivar horario.
- DÃ­as del horario.
- Hora de entrada y salida por dÃ­a.
- Tipo legal del horario.
- Pausas programadas.
- CÃ³digo Ãºnico por empresa.
- SeparaciÃ³n multiempresa.

### Usuario o rol para probar

- Administrador de empresa.
- Recursos humanos, si tiene permisos.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/schedules`
- MenÃº Horarios.

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear horario bÃ¡sico | Crear horario con cÃ³digo, nombre, tipo legal y estado activo. | El horario se crea en la empresa activa. |
| CÃ³digo duplicado | Crear dos horarios con el mismo cÃ³digo en la misma empresa. | El sistema rechaza el duplicado. |
| Mismo cÃ³digo en otra empresa | Cambiar a Empresa B y usar el mismo cÃ³digo. | El sistema lo permite. |
| Configurar dÃ­as laborales | Marcar dÃ­a laboral y capturar entrada/salida. | El dÃ­a se guarda y exige horarios. |
| DÃ­a no laboral | Marcar dÃ­a no laboral y dejar horas vacÃ­as. | El sistema permite guardar sin calcular jornada. |
| Pausa programada | Agregar pausa con nombre, duraciÃ³n o rango horario. | La pausa queda asociada al dÃ­a correcto. |
| Pausa invÃ¡lida | Capturar duraciÃ³n negativa. | El sistema rechaza la pausa. |
| Inactivar horario | Inactivar horario activo. | Queda inactivo, no eliminado. |

### No deberÃ­a existir todavÃ­a

- AsignaciÃ³n de horario a trabajador, si Sprint 2B no estÃ¡ cerrado.
- ValidaciÃ³n completa de cruce de medianoche, si Sprint 2B no estÃ¡ cerrado.
- Descansos obligatorios.
- Registro de entrada/salida.
- Eventos de jornada.
- Kiosco operativo.
- Motor legal.
- CÃ¡lculo de horas.
- Alertas.
- Incidencias.
- Reportes.

### Observaciones

En Sprint 2A `crosses_midnight` puede existir visualmente, pero todavÃ­a no debe ejecutar lÃ³gica avanzada.

---

## Sprint 2B - Horarios con cruce de medianoche, asignaciones y vigencias

**Estado:** En revisiÃ³n o pendiente de cierre. Candidato a cierre con validaciones automatizadas OK.

Esta secciÃ³n solo debe usarse como guÃ­a formal cuando Sprint 2B estÃ© en la rama de prueba correspondiente o ya cerrado en `main`.

### Funcionalidad esperada al cerrar el sprint

- ValidaciÃ³n de horarios que cruzan medianoche.
- AsignaciÃ³n de horario a trabajador.
- Vigencia de asignaciÃ³n por fecha efectiva.
- Reemplazo de asignaciÃ³n sin borrar historial.
- InactivaciÃ³n de asignaciÃ³n sin eliminarla.
- ResoluciÃ³n del horario vigente por trabajador y fecha.
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
| Cruce vÃ¡lido | Crear dÃ­a laboral con entrada `22:00`, salida `06:00` y cruce de medianoche activo. | El sistema permite guardar y registra el cruce. |
| Cruce invÃ¡lido | Capturar `22:00` a `06:00` sin marcar cruce de medianoche. | El sistema rechaza la configuraciÃ³n. |
| Crear asignaciÃ³n | Seleccionar trabajador, horario y fecha efectiva. | La asignaciÃ³n se crea sin generar jornadas ni eventos. |
| Reemplazar asignaciÃ³n | Crear nueva asignaciÃ³n con otro horario y fecha posterior. | La anterior se cierra y la nueva queda vigente desde la fecha indicada. |
| Evitar solapamientos | Crear asignaciÃ³n que se empalme con otra activa del mismo trabajador. | El sistema rechaza el solapamiento. |
| Resolver horario por fecha | Revisar horario vigente en una fecha anterior y otra posterior. | El sistema identifica el horario correcto segÃºn la fecha. |
| Inactivar asignaciÃ³n | Inactivar una asignaciÃ³n activa. | La asignaciÃ³n se inactiva y no se elimina. |
| Horario de otra empresa | Intentar asignar horario de Empresa B a trabajador de Empresa A. | El sistema bloquea la operaciÃ³n. |
| Trabajador de otra empresa | Intentar asignar horario a trabajador de Empresa B desde Empresa A. | El sistema bloquea la operaciÃ³n. |

### No deberÃ­a existir todavÃ­a

- Descansos obligatorios `BL-0405`.
- Registro de jornada.
- Modelo `time_events`.
- Entrada/salida web.
- Registro de pausas reales.
- Kiosco operativo.
- Captura manual justificada.
- Motor legal.
- CÃ¡lculos de jornada.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.
- API de negocio.
- ImportaciÃ³n CSV.

### Observaciones

Este sprint prepara vigencias para que futuras jornadas usen el horario correcto.

Validaciones finales reportadas para cierre:

- `php artisan migrate:fresh --seed` OK.
- `php artisan test tests\Feature\Sprint2B\ScheduleAssignmentManagementTest.php` OK: 26 pruebas, 60 assertions.
- `php artisan test` OK: 164 pruebas, 419 assertions.
- `npm.cmd run build` OK.

Arquitectura y QA quedaron aprobados con observaciones menores S3. No hay S1 ni S2 reportados.

TodavÃ­a no debe existir cÃ¡lculo de horas ni clasificaciÃ³n legal de jornada como diurna, nocturna o mixta.

Si una asignaciÃ³n futura cambia datos histÃ³ricos, debe reportarse como error crÃ­tico.

---

## Sprint 2C - Descansos obligatorios

**Estado:** En revision o pendiente de cierre. Candidato a cierre con validaciones automatizadas OK.

### Funcionalidad esperada al cerrar el sprint

- Catalogo de descansos obligatorios.
- Separacion entre tipo y alcance.
- Tipos permitidos: `legal_mandatory`, `electoral`, `company_internal`.
- Alcances permitidos: `national`, `subnational`, `company`.
- Pais operativo fijo en Mexico (`MX`) durante el MVP.
- Alta y edicion de descansos internos de empresa.
- Administracion global de descansos nacionales, subnacionales o electorales solo por `super_admin`.
- Visualizacion controlada de descansos globales sin permitir modificarlos desde usuarios de empresa.
- Inactivacion no destructiva.
- Resolucion por fecha desde dominio.

### Usuario o rol para probar

- Administrador de empresa.
- Super admin.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/mandatory-rest-days`

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Crear descanso interno de empresa | Crear descanso con tipo `company_internal`, alcance `company`, fecha, nombre y fundamento o referencia opcional. | Se guarda en la empresa activa, con `jurisdiction_code` vacio y `capture_source` tecnico manual. |
| Rechazar tipo interno con alcance nacional | Intentar crear `company_internal` con alcance `national`. | El sistema rechaza la combinacion. |
| Rechazar tipo interno con alcance subnacional | Intentar crear `company_internal` con alcance `subnational`. | El sistema rechaza la combinacion. |
| Crear descanso legal nacional como super_admin | Crear `legal_mandatory` con alcance `national`. | Se guarda con `country_code = MX`, sin `company_id` y sin `jurisdiction_code`. |
| Crear descanso electoral subnacional como super_admin | Crear `electoral` con alcance `subnational` y codigo `MX-NLE`. | Se guarda sin `company_id` y con `jurisdiction_code` normalizado. |
| Rechazar region libre | Intentar capturar nombre libre de estado o region en vez de codigo normalizado. | El sistema rechaza el valor. |
| Usuario de empresa no administra globales | Intentar crear o editar `national`, `subnational` o `electoral` global. | El sistema bloquea la operacion. |
| Editar descanso permitido | Modificar nombre, fecha o fundamento/referencia de un descanso interno de la empresa. | Se actualiza sin cambiar a otra empresa ni borrar historial. |
| Referencia visible | Dejar el fundamento vacio y revisar la tabla. | La tabla muestra "Sin referencia" y no muestra `capture_source`. |
| Duplicado mismo type/scope/fecha/nombre | Intentar crear otro descanso con la misma identidad de alcance, fecha y nombre. | El sistema rechaza el duplicado. |
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

### Observaciones

`mandatory_rest_days` ya no usa `center_id`. Para resolver descansos subnacionales por fecha, el dominio obtiene `country_code` y `jurisdiction_code` desde `centers.address`. No se deben usar nombres libres de estados o regiones.

El campo visible es "Fundamento o referencia". `capture_source` es tecnico y no debe mostrarse en la tabla principal.

Durante el MVP no hay selector internacional de pais, calendarios extranjeros ni reglas laborales fuera de Mexico.

---
## Sprint 2D - Modelo interno time_events

**Estado:** En revision o pendiente de cierre. Candidato a cierre con validaciones automatizadas OK.

### Funcionalidad esperada al cerrar el sprint

- Tabla interna `time_events`.
- Modelo de eventos fuente con empresa, trabajador, fecha/hora UTC, fecha/hora local, zona horaria, fuente, estado e idempotencia.
- Creacion solo desde dominio interno mediante Action.
- Sin pantalla ni registro operativo para usuarios finales.

### Prueba manual

Sprint 2D no tiene prueba manual operativa todavia porque no agrega pantalla de registro de jornada.

QA manual solo debe verificar que no aparezcan rutas, botones o pantallas nuevas para checar entrada/salida.

Validacion tecnica automatizada cubierta:

- Migracion `time_events`.
- Modelo y relaciones con empresa, trabajador, relacion laboral, centro y usuario fuente.
- Multi-tenant y bloqueo de empresa inactiva.
- Conversion local a UTC y UTC a local.
- Evento nocturno que cruza medianoche sin perder fecha local operativa.
- Idempotencia por empresa con `idempotency_key` y `source` + `external_id`.
- `occurred_local_time` en formato `H:i:s`.
- Confirmacion de que no se crean `work_days`, `work_day_calculations`, `alerts`, `incidents` ni `reports`.

### No deberia existir todavia

- Botones de entrada/salida.
- Pantalla de checado.
- Kiosco operativo.
- Captura manual justificada.
- Validacion de NIP para checar.
- Modelo `work_days`.
- Modelo `work_day_calculations`.
- Motor legal.
- Calculo de horas o jornadas.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.

---
## Sprint 2E - Registro web basico de jornada y pausas

**Estado:** En revision o pendiente de cierre. Candidato a cierre con validaciones automatizadas OK.

### Funcionalidad esperada al cerrar el sprint

- Pantalla `/time-clock`.
- Selector administrativo de trabajador activo de la empresa activa.
- Registro de entrada.
- Registro de salida.
- Inicio de pausa.
- Fin de pausa.
- Estado simple del dia: sin entrada, trabajando, en pausa o jornada cerrada.
- Listado minimo de eventos validos del dia.
- Eventos creados con fuente `web` y estado `valid`.

### Usuario o rol para probar

- Administrador de empresa.
- Recursos humanos, si tiene permisos.
- Usuario de otra empresa.
- Usuario sin empresa activa.
- Rol no autorizado.

### Ruta o pantalla

- `/time-clock`

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Acceso protegido | Entrar a `/time-clock` sin iniciar sesion. | El sistema redirige a login. |
| Rol autorizado | Verificar sidebar con owner/admin/rh. | El enlace Registro de jornada aparece y permite entrar a `/time-clock`. |
| Rol no autorizado | Verificar sidebar y acceso directo con rol sin permiso. | El enlace no aparece y el acceso directo queda bloqueado. |
| Empresa activa | Entrar con usuario sin empresa activa o empresa inactiva. | El sistema bloquea el acceso operativo. |
| Seleccionar trabajador | Entrar con rol autorizado y seleccionar trabajador activo. | Solo aparecen trabajadores de la empresa activa. |
| Registrar entrada | Presionar Registrar entrada. | Se crea evento `clock_in`, fuente `web`, estado `valid`. |
| Evitar doble entrada | Intentar registrar entrada dos veces seguidas. | El sistema no permite la segunda entrada abierta. |
| Iniciar pausa | Despues de entrada, presionar Iniciar pausa. | Se crea evento `break_start`. |
| Evitar doble pausa | Intentar iniciar pausa dos veces seguidas. | El sistema bloquea la segunda pausa abierta. |
| Terminar pausa | Despues de iniciar pausa, presionar Terminar pausa. | Se crea evento `break_end`. |
| Registrar salida | Despues de entrada, o despues de terminar pausa, presionar Registrar salida. | Se crea evento `clock_out` y la jornada queda cerrada a nivel operativo simple. |
| Multiempresa | Intentar ver o registrar trabajador de otra empresa. | El sistema bloquea el acceso horizontal. |
| Eventos del dia | Completar entrada, pausa, fin de pausa y salida. | La tabla muestra los eventos del dia con tipo, hora local, fuente `web` y estado `valid`. |
| Hora no editable | Revisar formulario antes de registrar evento. | No existe campo para editar fecha u hora; el sistema usa la hora actual. |
| Captura manual pendiente | Buscar opcion para registrar evento manual con motivo. | No existe captura manual justificada todavia. |

### No deberia existir todavia

- Kiosco operativo.
- Uso de codigo/NIP para checar.
- Captura manual justificada.
- Edicion de fecha u hora.
- Anulacion logica operativa.
- Eventos fuera de orden o tardios como flujo.
- Modelo `work_days`.
- Modelo `work_day_calculations`.
- Motor legal.
- Calculo de horas o jornadas.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.
- API de negocio.

### Observaciones

La pantalla es administrativa porque todavia no existe vinculo seguro usuario-trabajador. El portal trabajador dedicado queda pendiente.

S3 documentados para seguimiento:

- `ResolveCurrentTimeRecordStateAction` usa consulta tolerante con `whereDate(...)` y `LIKE`; funciona, pero conviene normalizarla en revision futura para mejor uso de indices.
- Mantener prueba manual de roles: owner/admin/rh ven y usan `/time-clock`; roles no autorizados no ven el enlace ni acceden.
- `/time-clock` registra eventos reales en `time_events`, pero todavia no calcula jornadas, no genera alertas, no crea incidencias y no aplica motor legal.
---

---

## Preparacion demo local Sprint 2G

**Estado:** En progreso.

Para cargar datos ficticios locales hasta Sprint 2, ejecutar:

```bash
php artisan db:seed --class=VeraTimeDemoSeeder
```

Datos disponibles para pruebas manuales:

- Empresa: `Vera Time Demo Completo`.
- Usuarios: `owner.demo@veratime.local`, `admin.demo@veratime.local`, `rh.demo@veratime.local`.
- Password demo local: `VeraDemo123!`.
- NIP demo local de kiosco: `1234`.
- Centros demo: Matriz Demo y Planta Demo Norte.
- Trabajadores demo: Ana Demo Lopez, Bruno Demo Perez y Carla Demo Ruiz.
- Horarios demo: diurno y nocturno con cruce de medianoche.
- Eventos demo: web, kiosk y admin_manual.

---

## Fase de consolidacion WFM y cierres

**Estado:** Diseno documental. No hay funcionalidad nueva para probar todavia.

Cuando se implemente, la guia manual debera cubrir:

- Unidades organizacionales por centro.
- Responsables por centro o unidad.
- Supervisor/responsable sin alcance explicito no puede operar.
- Alcance por centro completo.
- Alcance por una o varias unidades organizacionales.
- Jerarquia visible `department` -> `area` -> `team`.
- Supervisor con acceso limitado a su alcance.
- Empresas sin unidades operando solo por centro.
- Catalogo de turnos.
- Perfiles `fixed`, `variable`, `rotating` y `flexible`.
- Batches en borrador.
- Importacion CSV/XLSX que genera borrador, no publicacion directa.
- Publicacion diaria con version consecutiva por centro/periodo, snapshot JSON canonico, `published_by`, `published_at` y hash SHA-256.
- Correccion de publicacion creando nueva version y dejando la anterior `superseded`.
- Resolucion de programacion diaria por trabajador y fecha.
- Perfiles multiples de cierre.
- Pantalla con:

```text
Perfil efectivo: [nombre]
Origen: Empresa / Centro / Area / Relacion laboral
```

No debe probarse todavia como implementado:

- Programacion diaria publicada.
- Importacion CSV/XLSX de horarios.
- Cierres multiples.
- Periodos y miembros congelados.

Usar estos datos solo en entorno local/demo. No representan datos reales ni deben usarse en produccion.

El seeder no crea anulacion logica, eventos tardios/fuera de orden como flujo, motor legal, calculos, alertas, incidencias, reportes, conformidad, API ni CSV.
## Sprint 2F - Kiosco basico y captura manual justificada

**Estado:** En cierre o pendiente de cierre. Candidato a cierre con validaciones automatizadas OK.

### Funcionalidad esperada al cerrar el sprint

- Kiosco basico en `/kiosk`.
- Identificacion por codigo/NIP usando `worker_credentials`.
- NIP validado con `Hash::check`.
- NIP no visible y `pin_hash` no expuesto.
- Token temporal de kiosco con expiracion.
- Registro de entrada, salida, inicio de pausa y fin de pausa desde kiosco.
- Eventos creados en `time_events` con fuente `kiosk`.
- Captura manual justificada en `/time-events/manual`.
- Motivo obligatorio.
- Eventos creados con fuente `admin_manual` y usuario capturista.
- Conversion local a UTC y conservacion de fecha, hora local, timezone y `received_at`.

### Usuario o rol para probar

- Administrador de empresa.
- Recursos humanos, si tiene permisos.
- Rol no autorizado.
- Trabajador con credencial de kiosco activa.
- Trabajador con credencial bloqueada o en reset, si existe dato de prueba.

### Rutas o pantallas

- `/kiosk`
- `/time-events/manual`

### Pruebas manuales

| Prueba | Pasos | Resultado esperado |
|---|---|---|
| Abrir kiosco | Entrar a `/kiosk`. | La pantalla carga sin requerir sesion de administrador. |
| Codigo/NIP correcto | Capturar codigo y NIP validos. | El trabajador se identifica y ve acciones disponibles. |
| NIP incorrecto | Capturar codigo valido y NIP incorrecto. | El sistema muestra mensaje neutral y no revela si el codigo existe. |
| NIP no visible | Revisar pantalla despues de identificar y registrar. | El NIP no queda visible. |
| Registrar entrada | Identificarse y registrar entrada. | Se crea evento `clock_in` con fuente `kiosk`. |
| Iniciar pausa | Despues de entrada, iniciar pausa. | Se crea evento `break_start`. |
| Terminar pausa | Despues de iniciar pausa, terminar pausa. | Se crea evento `break_end`. |
| Registrar salida | Despues de entrada o pausa terminada, registrar salida. | Se crea evento `clock_out`. |
| Evitar duplicados simples | Intentar doble entrada o doble pausa. | El sistema bloquea la accion no permitida por estado actual. |
| Token temporal | Identificarse y dejar pasar la ventana de expiracion. | El sistema pide volver a identificarse antes de registrar. |
| Abrir captura manual | Entrar a `/time-events/manual` con rol autorizado. | La pantalla carga y muestra trabajadores de la empresa activa. |
| Rol no autorizado | Entrar a `/time-events/manual` con rol sin permiso. | El acceso queda bloqueado. |
| Crear captura manual | Seleccionar trabajador, tipo de evento, fecha, hora y motivo. | Se guarda evento con fuente `admin_manual` y motivo. |
| Motivo obligatorio | Intentar guardar sin motivo. | El sistema rechaza la captura. |
| Verificar evento creado | Revisar ultimas capturas manuales. | El evento aparece con trabajador, tipo, hora local y estado. |

### No deberia existir todavia

- Anulacion logica operativa.
- Eventos fuera de orden o tardios como flujo avanzado.
- Modelo `work_days`.
- Modelo `work_day_calculations`.
- Motor legal.
- Calculo de horas o jornadas.
- Alertas.
- Incidencias.
- Reportes.
- Conformidad digital.
- API de negocio.
- CSV.

### Validacion automatizada registrada

- `php artisan migrate:fresh --seed`: OK.
- `php artisan test tests\Feature\Sprint2F`: OK, 28 passed / 136 assertions.
- `php artisan test`: OK, 259 passed / 783 assertions.
- `npm.cmd run build`: OK, con warning no bloqueante `Generated an empty chunk: "app"`.

### Pendientes S3 no bloqueantes

- Agregar prueba futura para codigo/NIP duplicado entre empresas.
- Agregar prueba futura para credencial cuyo `worker_id` no corresponda a la misma empresa.
- Revisar warning no bloqueante de Vite: `Generated an empty chunk: "app"`.
- Endurecer en futura revision la estrategia de timezone de captura manual si se expone fuera de la UI actual.
---
## Pendientes globales que no deben marcarse como listos

| Pendiente | Nota |
|---|---|
| `BL-0205` Dashboard inicial | Depende de jornadas, alertas e incidencias reales. |
| `BL-0306` ImportaciÃ³n CSV de trabajadores | No debe aparecer como lista todavÃ­a. |
| `BL-0307` Detalle completo de trabajador | Faltan jornadas, alertas, incidencias y reportes. |
| `BL-0405` Descansos obligatorios | Implementado en Sprint 2C con type/scope separados; candidato a cierre si las pruebas manuales y automatizadas pasan. |
| `BL-0501` Modelo `time_events` | Implementado en Sprint 2D como modelo interno; sin UI operativa. |
| `BL-0502` y `BL-0503` Registro web basico | Implementados en Sprint 2E como flujo administrativo `/time-clock`, sin calculos. |
| `BL-0504` y `BL-0505` Kiosco y captura manual | Implementados en Sprint 2F como kiosco basico y captura manual justificada, sin calculos. |
| `BL-0506` y `BL-0507` Flujos posteriores de eventos | Pendientes; no debe haber anulacion logica operativa ni eventos fuera de orden/tardios como flujo. |
| API y motor legal | Pendientes; no debe existir API de negocio completa ni cÃ¡lculo legal. |
| Alertas, incidencias, cierres y reportes | Pendientes; no deben considerarse listos. |

---

## Checklist rÃ¡pido por fase

Usar esta tabla para marcar validaciÃ³n manual. En observaciÃ³n anotar pantalla, usuario y caso probado.

| Fase | Punto a validar | Probado | Correcto | Falla | ObservaciÃ³n |
|---|---|---|---|---|---|
| Sprint 0 | Login funciona |  |  |  |  |
| Sprint 0 | Logout funciona |  |  |  |  |
| Sprint 0 | Usuario inactivo no entra |  |  |  |  |
| Sprint 0 | Usuario sin empresa activa queda bloqueado |  |  |  |  |
| Sprint 0 | Usuario de Empresa A no ve Empresa B |  |  |  |  |
| Sprint 0 | Registro pÃºblico no estÃ¡ disponible |  |  |  |  |
| Sprint 1A | Se ve empresa activa |  |  |  |  |
| Sprint 1A | Selector solo muestra empresas permitidas |  |  |  |  |
| Sprint 1A | Se puede cambiar empresa activa |  |  |  |  |
| Sprint 1A | Datos bÃ¡sicos de empresa se guardan |  |  |  |  |
| Sprint 1A | Empresa inactiva no permite operar |  |  |  |  |
| Sprint 1B | Se pueden crear centros |  |  |  |  |
| Sprint 1B | CÃ³digo de centro es Ãºnico por empresa |  |  |  |  |
| Sprint 1B | Se puede inactivar centro sin borrarlo |  |  |  |  |
| Sprint 1C | Se puede crear trabajador |  |  |  |  |
| Sprint 1C | CÃ³digo de trabajador es Ãºnico por empresa |  |  |  |  |
| Sprint 1C | Cambio de relaciÃ³n laboral conserva historial |  |  |  |  |
| Sprint 1C | Baja no borra trabajador |  |  |  |  |
| Sprint 1D | Se puede crear condiciÃ³n laboral |  |  |  |  |
| Sprint 1D | Reemplazo de condiciÃ³n conserva historial |  |  |  |  |
| Sprint 1D | NIP no se muestra en texto claro |  |  |  |  |
| Sprint 1D | Se puede bloquear credencial |  |  |  |  |
| Sprint 2A | Se puede crear horario |  |  |  |  |
| Sprint 2A | CÃ³digo de horario es Ãºnico por empresa |  |  |  |  |
| Sprint 2A | DÃ­a laboral requiere entrada y salida |  |  |  |  |
| Sprint 2A | Se pueden agregar pausas programadas |  |  |  |  |
| Sprint 2A | Se puede inactivar horario sin borrarlo |  |  |  |  |
| Sprint 2B | Horario `22:00` a `06:00` requiere cruce de medianoche |  |  |  |  |
| Sprint 2B | Horario normal `08:00` a `17:00` funciona |  |  |  |  |
| Sprint 2B | Se puede asignar horario a trabajador |  |  |  |  |
| Sprint 2B | Se puede reemplazar asignaciÃ³n |  |  |  |  |
| Sprint 2B | Se conserva historial |  |  |  |  |
| Sprint 2B | No se permiten solapamientos |  |  |  |  |
| Sprint 2B | Se puede inactivar asignacion sin borrar |  |  |  |  |
| Sprint 2C | Se puede crear descanso interno de empresa |  |  |  |  |
| Sprint 2C | Super admin puede crear descanso legal/electoral nacional o subnacional |  |  |  |  |
| Sprint 2C | Usuario de empresa no puede administrar globales nacionales, subnacionales o electorales |  |  |  |  |
| Sprint 2C | Se puede editar descanso obligatorio permitido |  |  |  |  |
| Sprint 2C | Duplicado por mismo type/scope, fecha y nombre se rechaza |  |  |  |  |
| Sprint 2C | `jurisdiction_code` requiere formato normalizado para alcance subnacional |  |  |  |  |
| Sprint 2C | No existe alcance por centro en descansos obligatorios |  |  |  |  |
| Sprint 2C | Usuario de Empresa A no ve descansos de Empresa B |  |  |  |  |
| Sprint 2C | Inactivar descanso no lo borra |  |  |  |  |
| Sprint 2D | No aparecen botones ni pantalla de checado |  |  |  |  |
| Sprint 2E | `/time-clock` registra entrada y salida web |  |  |  |  |
| Sprint 2E | `/time-clock` registra inicio y fin de pausa |  |  |  |  |
| Sprint 2E | Rol no autorizado no ve enlace ni accede a `/time-clock` |  |  |  |  |
| Sprint 2E | No se puede editar fecha u hora en registro web |  |  |  |  |
| Sprint 2E | No existe kiosco operativo ni captura manual |  |  |  |  |
| Sprint 2F | `/kiosk` registra eventos con codigo/NIP |  |  |  |  |
| Sprint 2F | `/time-events/manual` registra captura con motivo |  |  |  |  |
| Sprint 2F | No existe anulacion logica ni eventos tardios avanzados |  |  |  |  |
| Sprint 2D | No existe kiosco operativo |  |  |  |  |
---

## UX-01 - Localizacion al espanol de Mexico

**Estado:** En revision. No corresponde a un sprint funcional nuevo.

### Preparacion

- Ejecutar `php artisan optimize:clear`.
- Iniciar sesion con usuario demo o usuario autorizado.
- Revisar tambien la pantalla publica `/`, login y `/kiosk`.

### Pruebas manuales

| Caso | Accion | Resultado esperado |
|---|---|---|
| Pantalla publica | Abrir `/`. | Se muestra Vera Time en espanol, sin textos del starter kit Laravel en ingles. |
| Login | Abrir `/login`. | Titulos, campos, botones y enlaces se muestran en espanol. |
| Recuperacion de contraseña | Abrir flujo de recuperacion. | Textos y mensajes se muestran en espanol. |
| Perfil | Abrir configuracion de perfil, contraseña y apariencia. | Navegacion, formularios y botones se muestran en espanol. |
| Navegacion | Revisar sidebar/header. | Menus visibles estan en espanol. |
| Kiosco | Abrir `/kiosk`. | Instrucciones, errores y acciones visibles estan en espanol. |
| Validaciones | Provocar campos requeridos en formularios principales. | Los mensajes de validacion se muestran en espanol. |
| Alcance | Revisar que no aparezca selector de idioma. | No hay funciones nuevas ni cambios de flujo. |

### Textos que pueden permanecer en ingles

- Nombres tecnicos internos: clases, metodos, variables, rutas, tablas, columnas, enums y valores internos.
- Claves de traduccion usadas por Laravel, por ejemplo `__('Password')`, siempre que se resuelvan en pantalla mediante `lang/es.json`.
- Nombres de librerias, assets o conceptos tecnicos no visibles para usuario final.

---

## Bloque A - roles y autorizacion base

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

### Validaciones manuales sugeridas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Rol RH canonico | Revisar usuarios/seeders demo con rol `rh`. | Recursos Humanos opera con clave `rh`; no existe rol inicial `hr`. |
| Alias no permitido | Crear o simular un usuario con rol `hr`. | No obtiene permisos empresariales ni aparece como rol de sistema. |
| Owner y admin | Entrar como owner/admin y abrir pantallas empresariales actuales. | Conservan acceso esperado. |
| RH | Entrar como `rh` y abrir pantallas empresariales actuales. | Conserva acceso empresarial completo del MVP actual. |
| Supervisor | Entrar como supervisor sin alcance explicito. | No obtiene acceso global a empresa, centros, trabajadores, horarios ni eventos administrativos. |
| Multi-tenant | Intentar operar empresa ajena, membresia inactiva o empresa inactiva. | El sistema bloquea la accion. |

### Pendiente para Bloque B

- Implementar alcances explicitos por centro completo o unidad organizacional para supervisores/responsables.
- No otorgar acceso automatico por poseer el rol `supervisor`.
---

## Bloque B1 - modelo organizacional y alcances

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

### Validaciones manuales futuras cuando exista UI B2

| Caso | Accion | Resultado esperado |
|---|---|---|
| Empresa sin unidades | Operar trabajadores solo con centro. | La operacion sigue funcionando sin obligar unidades. |
| Jerarquia | Crear departamento, area y equipo. | La jerarquia visible queda limitada a tres niveles. |
| Unidad inactiva | Intentar asignar trabajador o alcance a unidad inactiva. | El sistema bloquea la asignacion. |
| Unidad con hijos | Intentar inactivar unidad con hijos activos. | El sistema bloquea la inactivacion. |
| Unidad con asignacion vigente | Intentar inactivar unidad con trabajador vigente. | El sistema bloquea la inactivacion. |
| Supervisor sin alcance | Entrar con supervisor sin scope. | No obtiene acceso global. |
| Supervisor por centro | Asignar alcance por centro. | Puede gestionar trabajadores aplicables de ese centro. |
| Supervisor por unidad | Asignar alcance a departamento o area. | Incluye descendientes segun jerarquia. |
| Apoyo temporal | Asignar apoyo temporal a unidad bajo supervisor. | El acceso aplica solo durante la vigencia. |
| Aislamiento tenant | Intentar usar unidad, centro o trabajador de otra empresa. | El sistema bloquea el acceso horizontal. |

### No incluido todavia

- Pantallas Livewire/Volt para unidades o responsables.
- Plantillas de turno.
- Perfiles de horario.
- Programacion diaria publicada.
- Perfiles de cierre.
- Incidencias, alertas, reportes, API o CSV.