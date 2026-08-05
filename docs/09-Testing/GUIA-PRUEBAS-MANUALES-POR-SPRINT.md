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
- Perfiles `pattern`, `calendar`, `flexible` y `on_call`; `pattern_mode = cycle` ya cuenta con dominio e interfaz de configuracion, sin publicacion diaria.
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

## Bloque 5 - Eventos de tiempo completos

**Estado:** Implementado/candidato a cierre. No incluye `work_days`, motor legal, alertas, incidencias, reportes ni API.

### Preparacion

Usar una empresa activa con usuario `owner`, `admin` o `rh`, trabajadores activos y eventos de jornada existentes o capturados desde `/time-clock`, `/kiosk` o `/time-events/manual`.

### Validacion manual

| Caso | Ruta | Resultado esperado |
|---|---|---|
| Anular evento | `/time-events/manual` | Permite elegir Anular, exige motivo y cambia estado a `Anulado`. |
| Motivo obligatorio | `/time-events/manual` | No permite confirmar anulacion sin motivo valido. |
| Segunda anulacion | `/time-events/manual` | Un evento ya anulado muestra estado `Anulado` y no permite volver a anularlo. |
| Trazabilidad | `/time-events/manual` | El evento conserva fecha/hora del hecho, fuente y trabajador; muestra actor y fecha de anulacion. |
| Permisos | `/time-events/manual` | `owner`, `admin` y `rh` pueden anular; `supervisor` no accede ni anula. |
| Multi-tenant | `/time-events/manual` | No aparecen eventos de otra empresa y no se puede anular un evento ajeno manipulando IDs. |
| Evento tardio | Captura manual o prueba tecnica | `occurred_at` conserva la hora real del hecho y `received_at` conserva la recepcion. |
| Evento fuera de orden | Prueba tecnica | Los eventos validos se resuelven por `occurred_at`, no por orden de captura. |

### Confirmaciones

- Los eventos anulados no se eliminan fisicamente.
- Los eventos anulados no participan en resoluciones futuras de eventos validos.
- `received_at` es el campo explicito de recepcion/captura tecnica.
- Pendiente siguiente bloque: agregar paginacion y filtros por fuente/estado en `/time-events/manual`, porque el listado actual muestra solo los 10 eventos mas recientes.
- No se crean tablas de `work_days`, `work_day_calculations`, `alerts`, `incidents` ni `reports`.
- Siguiente bloque pendiente: `work_days`.

---

## Bloque A - Regla de evidencia operativa

**Estado:** Documentado/candidato a cierre. No cambia codigo operativo.

Objetivo:

Validar que el criterio de producto quede claro antes de modificar relaciones laborales, asignaciones organizacionales, perfiles y `work_days`: Vera Time protege el resultado final publicado o registrado, no cada dato intermedio usado para construirlo.

### Casos de validacion documental

| Caso | Accion | Resultado esperado |
|---|---|---|
| Relacion laboral sin evidencia | Revisar el criterio para una relacion laboral capturada con fecha o centro incorrecto antes de publicar horarios o registrar asistencia. | Debe permitirse correccion administrativa futura con motivo y actor. |
| Relacion laboral con horario publicado | Revisar el criterio para una relacion laboral usada en un batch publicado. | El horario publicado no se modifica desde trabajadores; cualquier cambio de fecha publicada requiere correccion versionada. |
| Asignacion organizacional con perfil de empresa | Revisar una empresa con perfil asignado a toda la empresa y una unidad organizacional incorrecta. | La UI futura debe aclarar que la unidad no define ese perfil; el cambio afecta contexto operativo, filtros y futuras publicaciones, no horarios ya publicados. |
| Asignacion organizacional con perfil por unidad | Revisar una unidad que si participa en la resolucion de perfil antes de publicar. | La correccion debe afectar borradores o publicaciones futuras; si ya hay publicacion, se conserva el resultado publicado. |
| Asignacion de perfil posterior a publicacion | Revisar un perfil o asignacion de perfil modificado despues de publicar. | La publicacion existente queda intacta; para cambiar el resultado se usa correccion versionada. |
| Evento de asistencia | Revisar un evento capturado por web, kiosco o captura manual. | No debe existir borrado fisico operativo; si fue error se anula logicamente con motivo, actor y fecha. |
| work_days sin eventos | Revisar el criterio del futuro `work_days` para una fecha con horario publicado y sin checadas. | Debe existir jornada esperada desde el horario publicado aunque no haya eventos. |
| work_days sin horario | Revisar el criterio del futuro `work_days` para eventos validos sin horario publicado. | Debe identificarse como jornada no programada, sin inventar horario desde perfiles actuales. |

### Pruebas automatizadas relacionadas

Estos comandos no prueban codigo nuevo de Bloque A, pero protegen los comportamientos ya implementados que sostienen la regla:

```bash
php artisan test tests/Feature/BlockF4/VersionedScheduleCorrectionsDomainTest.php --stop-on-failure
php artisan test tests/Feature/Sprint2D/TimeEventModelTest.php --stop-on-failure
php artisan test tests/Feature/Sprint2F/ManualTimeEventCaptureTest.php --stop-on-failure
```

### No incluido

- Cambios de UI en trabajadores.
- Cambios de UI en asignaciones organizacionales.
- Cambios de UI en asignaciones de perfiles.
- Nuevas migraciones.
- `work_days`.
- Motor legal, calculos, alertas, incidencias, cierres, conformidad, reportes o API.

---

## Bloque B - Correccion de relaciones laborales

**Estado:** Implementado/candidato a cierre. No incluye asignaciones organizacionales, perfiles, `work_days`, calculos, reportes ni API.

### Preparacion

Usar `/workers` con un usuario `owner`, `admin` o `rh` de una empresa activa.

### Pruebas manuales

| Caso | Accion | Resultado esperado |
|---|---|---|
| Correccion sin evidencia | Editar un trabajador cuya relacion no tenga horarios publicados ni eventos; cambiar centro, puesto o fecha de ingreso; capturar motivo. | Guarda sobre la misma relacion laboral, no crea una relacion nueva y muestra `Trabajador actualizado.` |
| Motivo obligatorio | Cambiar centro, puesto o fecha de ingreso sin llenar `Motivo del cambio laboral`. | Bloquea el guardado y pide motivo. |
| Retroceder fecha sin evidencia | Cambiar fecha de ingreso a una fecha anterior en relacion sin evidencia y capturar motivo. | Guarda la fecha anterior en la misma relacion. |
| Relacion con horario publicado | Editar un trabajador cuya relacion ya tenga programacion diaria publicada; intentar cambiar centro o fecha hacia atras. | Bloquea la sobrescritura y explica que debe usarse correccion versionada del horario publicado. |
| Relacion con asistencia | Editar un trabajador con eventos `time_events`; intentar cambiar datos historicos hacia atras. | Bloquea la sobrescritura de la relacion historica. |
| Nueva vigencia futura | En relacion con evidencia, cambiar centro o puesto con una fecha posterior a la ultima fecha con horario/asistencia y motivo. | Cierra la relacion anterior el dia previo y crea una nueva relacion activa. |
| Vigencia que corta evidencia | En relacion con evidencia, usar una fecha nueva que deje horarios/asistencias fuera de la relacion anterior. | Bloquea el cambio y conserva la relacion original. |
| Multi-tenant y permisos | Intentar operar como supervisor, empresa inactiva o con centro ajeno. | Bloquea por permisos o validacion de empresa/centro. |

Pendiente UI siguiente bloque:

- Revisar que al cerrar, cancelar, guardar o cambiar de trabajador en el panel de edicion se limpien todos los inputs del formulario y no queden valores arrastrados.

### Pruebas automatizadas

```bash
php artisan test tests/Feature/Sprint1C/WorkerManagementTest.php --stop-on-failure
```

### No incluido

- Correccion de asignaciones organizacionales.
- Correccion de asignaciones de perfiles.
- Cambios a horarios publicados desde trabajadores.
- `work_days`.
- Motor legal, calculos, alertas, incidencias, cierres, conformidad, reportes o API.

---

## Bloque C - Asignaciones organizacionales y pendientes UI

**Estado:** Cerrado. No incluye perfiles, `work_days`, calculos, reportes ni API.

### Pruebas manuales

| Caso | Accion | Resultado esperado |
|---|---|---|
| Correccion de unidad | En `/organization/assignments`, reemplazar unidad principal y capturar motivo. | Corrige la segmentacion activa sobre el mismo registro y no crea historial adicional. |
| Publicacion congelada | Corregir una asignacion organizacional usada para generar/publicar un horario. | El horario publicado conserva su unidad congelada; el cambio aplica al dato organizacional/futuras publicaciones. |
| Eventos paginados | En `/time-events/manual`, crear o consultar mas de 10 eventos. | La tabla muestra paginacion y permite llegar a eventos que antes quedaban ocultos. |
| Filtros de eventos | Filtrar `/time-events/manual` por fuente o estado. | La tabla muestra solo los eventos que coinciden y mantiene paginacion. |
| Reset formulario trabajador | En `/workers`, editar un trabajador, escribir motivo o NIP temporal, cancelar y abrir nuevo trabajador. | Los campos quedan limpios, sin valores arrastrados. |

### Pruebas automatizadas

```bash
php artisan test tests/Feature/SprintB2/OrganizationalOperationsUiTest.php --stop-on-failure
php artisan test tests/Feature/Sprint2F/ManualTimeEventCaptureTest.php --stop-on-failure
php artisan test tests/Feature/Sprint1C/WorkerManagementTest.php --stop-on-failure
```

### No incluido

- Correccion de asignaciones de perfiles.
- Cambios a horarios publicados desde asignaciones organizacionales.
- `work_days`.
- Motor legal, calculos, alertas, incidencias, cierres, conformidad, reportes o API.

---

## Bloque D - Simplificacion de vigencia laboral y asignacion organizacional

**Estado:** Implementado/candidato a validacion. No incluye `work_days`, motor legal, calculos, reportes ni API.

### Pruebas manuales

| Caso | Accion | Resultado esperado |
|---|---|---|
| Sin fecha en asignacion | Abrir `/organization/assignments` y entrar a Cambiar unidad. | El formulario no muestra campos de fecha para asignacion organizacional. |
| Cambio de unidad activa | Seleccionar trabajador activo, unidad del mismo centro, motivo y guardar. | Se actualiza la unidad principal actual sobre el mismo registro. |
| Trabajador con fecha de ingreso futura | Seleccionar un trabajador activo cuya relacion empieza despues de hoy. | El combo de unidades se llena por su centro activo y no aparece error por fecha seleccionada. |
| Trabajador dado de baja/inactivo | Intentar seleccionar o asignar trabajador no activo. | No aparece como seleccionable o queda bloqueado por relacion/estado laboral. |
| Apoyo temporal fuera de UI | Revisar `/organization/assignments`. | No hay boton ni formulario para crear apoyo temporal. |
| Perfil por unidad | Generar programacion draft desde perfiles para una relacion con unidad principal activa. | La unidad activa actual participa en la resolucion de perfil; apoyos temporales legados no cambian la herencia. |
| Publicacion congelada | Cambiar unidad despues de publicar un horario. | El horario publicado conserva su unidad congelada y no se recalcula. |
| Tab del navegador | Abrir varias vistas principales. | El tab muestra el nombre de la vista actual junto al nombre de la app. |
| Scroll del menu | Reducir altura de la ventana hasta que el menu lateral tenga scroll. | El scrollbar del menu se ve delgado y discreto. |

### Pruebas automatizadas

```bash
php artisan test tests/Feature/SprintB1/OrganizationalScopeTest.php --stop-on-failure
php artisan test tests/Feature/SprintB2/OrganizationalOperationsUiTest.php --stop-on-failure
php artisan test tests/Feature/BlockD1/ScheduleProfileDomainTest.php --stop-on-failure
php artisan test tests/Feature/BlockF2/DraftScheduleGenerationDomainTest.php --stop-on-failure
```

### No incluido

- `work_days`.
- Motor legal, calculos, alertas e incidencias.
- Usuarios y roles.
- Sistema visual general.
- Reportes, API o despliegue.

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

### Cubierto por Bloque B2

- Alcances explicitos por centro completo o unidad organizacional para supervisores/responsables.
- El rol `supervisor` no otorga acceso automatico sin scope vigente.
---

## Bloque B1 - modelo organizacional y alcances

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

### Validaciones manuales B2

| Caso | Accion | Resultado esperado |
|---|---|---|
| Areas y departamentos | Abrir `/organization/units`. | Se listan unidades por centro con filtros y jerarquia visible. |
| Jerarquia | Crear departamento, area y equipo. | La jerarquia visible queda limitada a tres niveles. |
| Supervisor en unidades | Entrar como supervisor y abrir unidades. | Puede consultar, pero no ve botones administrativos. |
| Unidad con hijos | Intentar inactivar unidad con hijos activos. | El sistema bloquea la inactivacion y muestra motivo. |
| Unidad con asignacion vigente | Intentar inactivar unidad con trabajador vigente. | El sistema bloquea la inactivacion y muestra motivo. |
| Unidad principal | Abrir `/organization/assignments` y asignar unidad principal. | Se crea asignacion vigente sin borrar historial. |
| Reemplazo principal | Reemplazar unidad principal con fecha efectiva. | La anterior queda reemplazada y la nueva vigente. |
| Apoyo temporal | Crear apoyo temporal en otro centro de la misma empresa. | Se guarda como apoyo temporal con vigencia. |
| Finalizar apoyo | Finalizar apoyo con fecha y motivo. | Queda finalizado sin eliminar registro. |
| Responsables | Abrir `/organization/scopes` y asignar supervisor a centro. | El supervisor recibe alcance por centro. |
| Alcance por unidad | Asignar supervisor a departamento o area. | Incluye descendientes segun jerarquia. |
| No supervisor | Intentar asignar scope a owner/admin/rh. | El sistema bloquea porque no requieren scope. |
| Mi alcance | Entrar como supervisor en `/organization/my-scope`. | Ve centros, unidades y trabajadores autorizados. |
| Supervisor sin alcance | Entrar como supervisor sin scope. | Se muestra "Sin alcance operativo". |
| Aislamiento tenant | Intentar usar unidad, centro, trabajador o usuario de otra empresa. | El sistema bloquea el acceso horizontal. |

### No incluido todavia

- Plantillas de turno.
- Perfiles de horario.
- Programacion diaria publicada.
- Perfiles de cierre.
- Incidencias, alertas, reportes, API o CSV.

---

## Bloque C - catalogo de plantillas de turno

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

### Ruta

- `/scheduling/shifts`

### Validaciones manuales sugeridas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Abrir catalogo | Entrar a `/scheduling/shifts` con owner/admin/rh. | Carga el listado y muestra "Catálogo de turnos". |
| Crear turno simple | Crear plantilla con trabajo `08:00` a `16:00`. | Se guarda y la vista previa muestra 8 h de trabajo. |
| Turno nocturno | Crear trabajo `22:00` a `06:00` con dia final siguiente. | Se guarda y muestra `+1 dia` / cruza medianoche. |
| Jornada partida | Crear trabajo, descanso fijo y segundo trabajo. | Se guardan varios segmentos sin solaparse. |
| Descanso por duracion | Agregar descanso de 30 minutos sin hora fija. | Se guarda como pausa requerida por duracion. |
| Metricas de jornada normal | Crear trabajo `08:00` a `16:00` sin descansos. | Muestra trabajo programado bruto 8 h, trabajo efectivo programado 8 h y duracion total 8 h. |
| Metricas de jornada partida | Crear trabajo `08:00` a `13:00`, descanso fijo no pagado `13:00` a `15:00`, trabajo `15:00` a `18:00` y descanso por duracion no pagado de 30 min. | Muestra trabajo bruto 8 h, descanso fijo no pagado 2 h, descanso por duracion no pagado 30 min, trabajo efectivo 7 h 30 min y duracion total 10 h. |
| Descanso por duracion pagado | Agregar un descanso por duracion pagado de 30 min dentro de una plantilla con 8 h de trabajo. | El descanso se muestra como pagado y no reduce el trabajo efectivo programado. |
| Solapamiento | Intentar segmentos fijos traslapados. | El sistema rechaza la plantilla. |
| Orden duplicado | Repetir `sort_order`. | El sistema rechaza la plantilla. |
| Inactivar | Inactivar plantilla activa. | Cambia a inactiva sin borrar segmentos. |
| Reactivar | Reactivar plantilla valida. | Vuelve a activa. |
| Supervisor con alcance | Entrar como supervisor con scope vigente. | Consulta plantillas activas sin controles administrativos. |
| Supervisor sin alcance | Entrar como supervisor sin scope. | Acceso bloqueado. |
| Rol no autorizado | Entrar con rol sin permiso. | No ve enlace y la ruta queda bloqueada. |
| Legacy separado | Revisar `/schedules`. | Sigue disponible como "Horarios legacy"; no hay doble escritura. |

### No incluido todavia

- Asignacion de turnos a trabajadores.
- Perfiles `pattern`, `calendar`, `flexible` u `on_call`.
- Generacion de programacion diaria desde perfiles.
- Publicacion operativa desde UI.
- Importacion CSV/XLSX.
- Calculos legales.
- Alertas, incidencias, reportes o API WFM.

---

## Bloque D1 - perfiles pattern weekly y calendar

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

### Validacion manual

D1 no agrega pantallas. La validacion manual se limita a revisar que no aparezcan nuevas entradas de perfiles en la navegacion y que el catalogo de turnos siga funcionando.

| Caso | Accion | Resultado esperado |
|---|---|---|
| Sin pantalla D1 | Revisar sidebar y rutas visibles. | No aparece pantalla de perfiles de horario todavia. |
| Catalogo de turnos | Abrir `/scheduling/shifts`. | Sigue operativo y no genera programacion diaria. |
| Seeder demo | Ejecutar `php artisan db:seed --class=VeraTimeDemoSeeder`. | Crea perfiles demo y asignaciones sin duplicar registros. |
| Resolucion por dominio | Ejecutar pruebas automatizadas D1. | Se validan prioridad relacion laboral, unidad principal, centro y empresa. |

### No incluido todavia

- Generacion de programacion diaria desde perfiles.
- Publicacion operativa desde UI.
- Publicacion.
- CSV/XLSX o API WFM.

---

## Bloque D2 - UI de perfiles y asignaciones

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

### Rutas

- `/scheduling/profiles`.
- `/scheduling/profile-assignments`.

### Validacion manual

Preparacion opcional de escenarios:

```bash
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
```

Credencial comun demo: `VeraDemo123!`.

| Empresa demo | Usuario sugerido | Escenario | Perfil esperado | Origen esperado |
|---|---|---|---|---|
| Demo Oficina por Patron | `owner.office.demo@veratime.local` | Trabajadores de oficina con perfil semanal. | `OFFICE-WEEKLY` - Por patron semanal | Empresa |
| Demo Tienda por Calendario | `owner.store.demo@veratime.local` | Operacion por calendario sin programacion diaria publicada. | `STORE-CALENDAR` - Por calendario | Empresa |
| Demo Constructora con Herencia | `owner.construction.demo@veratime.local` | Administracion. | `CONST-BASE` - Por patron semanal | Empresa |
| Demo Constructora con Herencia | `admin.construction.demo@veratime.local` | Trabajador de Construccion en Obra Norte. | `CONST-CALENDAR` - Por calendario | Centro |
| Demo Constructora con Herencia | `rh.construction.demo@veratime.local` | Trabajador de Almacen. | `CONST-WAREHOUSE` - Por patron semanal | Unidad organizacional |
| Demo Constructora con Herencia | `supervisor.construction.demo@veratime.local` | Supervisor con alcance limitado a Area Construccion. | Consulta/operacion limitada a su alcance | Unidad organizacional |
| Demo Constructora con Herencia | `owner.construction.demo@veratime.local` | Trabajador con excepcion directa. | `CONST-DIRECT-CAL` - Por calendario | Relacion laboral |
| Demo Sin Perfil de Horario | `owner.noprofile.demo@veratime.local` | Empresa sin asignaciones de perfil. | Sin perfil | Sin origen |

| Caso | Accion | Resultado esperado |
|---|---|---|
| Navegacion | Abrir dashboard con owner/admin/rh. | En Horarios aparecen Catalogo de turnos, Perfiles de horario, Asignaciones de perfiles y Descansos obligatorios. |
| Legacy oculto | Revisar sidebar. | No aparecen Horarios legacy ni Asignacion de Horarios legacy. Las rutas legacy siguen existiendo internamente. |
| Crear perfil por patron | Abrir `/scheduling/profiles`, crear perfil por patron semanal y seleccionar turnos de lunes a viernes con descanso sabado/domingo. | Se guarda con siete reglas semanales y vista previa. |
| Crear perfil por calendario | Crear perfil por calendario. | No muestra editor semanal ni crea reglas semanales. |
| Plantillas disponibles | Editar perfil por patron semanal. | Solo aparecen plantillas activas de la empresa actual. |
| Asignar por empresa | Abrir `/scheduling/profile-assignments` y crear asignacion de alcance empresa. | Se guarda vigencia con fuente manual desde servidor. |
| Asignar por centro | Crear asignacion de alcance centro. | Solo permite centros activos de la empresa actual. |
| Asignar por unidad | Seleccionar centro y despues unidad. | La unidad pertenece al centro seleccionado y a la empresa activa. |
| Asignar por relacion laboral | Buscar trabajador por clave o nombre. | El selector limita resultados y resuelve relacion laboral vigente. |
| Resolver perfil efectivo | Seleccionar trabajador y fecha. | Muestra perfil efectivo, tipo, origen, unidad principal y centro. |
| Reemplazar asignacion | Reemplazar una asignacion vigente con nueva fecha y motivo. | La anterior queda reemplazada y se conserva historial. |
| Finalizar excepcion | Finalizar una asignacion directa. | La asignacion queda finalizada y vuelve a aplicar la herencia superior. |
| Supervisor con alcance | Entrar como supervisor con scope vigente. | No administra perfiles y solo puede asignar relacion laboral dentro de su alcance. |
| Supervisor sin alcance | Entrar como supervisor sin scope. | No obtiene acceso operativo por solo tener rol. |

### No incluido todavia

- Programacion semanal o por periodo.
- Publicacion operativa desde UI.
- Generacion de programacion diaria desde perfiles.
- CSV/XLSX o API WFM.
- Perfiles de cierre.
- Calculos legales.
- Incidencias, alertas o reportes.

---

## Bloque E1 - dominio de perfiles avanzados

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

E1 no agrega pantallas. La validacion manual se limita a ejecutar seeders y confirmar que las pantallas D2 existentes no muestren todavia opciones avanzadas como flujo operativo.

Preparacion opcional de escenarios:

```bash
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
```

Credencial comun demo: `VeraDemo123!`.

| Empresa demo | Escenario | Resultado esperado |
|---|---|---|
| Demo Ciclo Rotativo | Perfil `pattern` con `pattern_mode = cycle` y ciclo 2 manana, 2 tarde, 2 noche, 2 descanso. | Existe dominio y resolucion por fecha; no genera programacion diaria publicada. |
| Demo Horario Flexible | Perfil `flexible` con lunes a viernes de 480 minutos y ventana 07:00-20:00. | Existe dominio de reglas flexibles; no muestra horario fijo ni calcula jornada. |
| Demo Bajo Demanda | Perfil `on_call` con disponibilidad 06:00-22:00 y maximo 480 minutos. | Existe dominio de disponibilidad; no crea activaciones, alertas ni eventos automaticos. |

### No incluido todavia

- Generacion de programacion diaria desde perfiles.
- Publicacion operativa desde UI.
- Activaciones on-call.
- Publicacion.
- Snapshots.
- CSV/XLSX o API WFM.
- Calculos legales.

---

## Bloque E2 - interfaz de perfiles avanzados

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

### Ruta

- `/scheduling/profiles`.

### Validaciones manuales sugeridas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Opciones visibles | Abrir Nuevo perfil. | Muestra Por patron, Por calendario, Flexible y Bajo demanda. |
| Patron semanal | Crear perfil Por patron / Patron semanal. | Conserva editor lunes a domingo y guarda siete reglas semanales. |
| Ciclo repetitivo | Crear perfil Por patron / Ciclo repetitivo. | Permite agregar, quitar y ordenar dias; la numeracion queda consecutiva desde 1. |
| Ciclo menor a 2 dias | Intentar guardar ciclo con un solo dia. | Muestra error y no guarda reglas parciales. |
| Flexible con ventana | Crear perfil Flexible con 480 minutos y ventana 07:00-20:00. | Guarda reglas flexibles; muestra minutos como 8 h. |
| Flexible sin ventana | Crear perfil Flexible sin marcar ventana. | Guarda minutos requeridos sin horas fijas. |
| Flexible descanso | Cambiar un dia a Descanso. | Limpia minutos y ventana del dia. |
| Bajo demanda | Crear perfil Bajo demanda con disponibilidad 06:00-22:00 y maximo 480 minutos. | Guarda disponibilidad; no la muestra como trabajo. |
| Bajo demanda descanso | Cambiar un dia a Descanso. | Limpia disponibilidad y maximo. |
| Cambio de metodo | Editar un perfil y cambiar metodo. | Requiere confirmar reemplazo de reglas antes de guardar. |
| Supervisor | Entrar como supervisor con alcance. | Puede consultar perfiles activos, pero no ve controles de creacion o edicion. |
| Alcance prohibido | Intentar consultar ID de otro tenant. | Acceso bloqueado. |

### No incluido todavia

- Generacion de programacion diaria desde perfiles.
- Publicacion operativa desde UI.
- Publicacion.
- Snapshots.
- Activaciones on-call.
- Alertas o notificaciones.
- CSV/XLSX o API WFM.
- Calculos legales.

---

## Bloque F1 - nucleo de programacion diaria

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

F1 no agrega pantalla nueva para probar manualmente. La validacion principal es automatizada y confirma que el dominio pueda preparar batches, dias, segmentos, snapshots y resolucion publicada sin activar generacion desde perfiles ni calculos.

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Batch draft | Ejecutar pruebas F1. | Se crea batch por empresa, centro y periodo; queda sin version publicada mientras sea borrador. |
| Dia shift | Ejecutar pruebas F1. | Se guarda asignacion diaria con segmentos congelados. |
| Dia rest | Ejecutar pruebas F1. | Se guarda dia de descanso sin segmentos incompatibles. |
| Flexible | Ejecutar pruebas F1. | Guarda minutos requeridos y ventana opcional sin crear turno fijo. |
| Bajo demanda | Ejecutar pruebas F1. | Guarda disponibilidad y maximo sin crear activacion. |
| Snapshot | Ejecutar pruebas F1. | JSON canonico estable y hash SHA-256 de 64 caracteres. |
| Resolver publicado | Ejecutar pruebas F1. | Solo devuelve batches `published`; no usa perfiles ni legacy como fallback. |
| Inmutabilidad | Ejecutar pruebas F1. | Batches no draft no se modifican por Actions de borrador. |

Comando sugerido:

```bash
php artisan test tests/Feature/BlockF1/DailyScheduleCoreDomainTest.php --stop-on-failure
```

### No incluido todavia

- Pantalla de calendario diario.
- Generacion desde perfiles.
- Publicacion operativa desde UI.
- Importacion CSV/XLSX.
- API WFM.
- `work_days`.
- `work_day_calculations`.
- Calculos legales.
- Alertas, incidencias y reportes.

---

## Bloque F2 - generacion draft desde perfiles

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

F2 no agrega pantalla nueva. La revision manual consiste en preparar escenarios demo y verificar que los batches quedan en borrador, sin publicar ni calcular jornadas.

Preparacion opcional:

```bash
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleScenarioSeeder
```

El periodo demo usado por F2 es:

```text
2026-08-03 a 2026-08-09
```

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Seeder F2 | Ejecutar `VeraTimeDailyScheduleScenarioSeeder` dos veces. | No duplica batches ni dias. |
| Oficina por patron | Revisar datos generados por pruebas/seeder. | Lunes a viernes quedan `shift`; sabado y domingo `rest`. |
| Ciclo rotativo | Revisar escenario ciclo. | El ciclo respeta `effective_from` de la asignacion, no el inicio del batch. |
| Flexible | Revisar escenario flexible. | Dias laborales quedan `flexible` con minutos y ventana; descansos quedan `rest`. |
| Bajo demanda | Revisar escenario on_call. | Dias aplicables quedan `on_call` con disponibilidad y maximo; no crean trabajo real. |
| Calendario | Revisar tienda por calendario. | Dias quedan `unassigned` con motivo `calendar_requires_daily_definition`. |
| Sin perfil | Revisar empresa sin perfil. | Dias quedan `unassigned` con motivo `no_effective_schedule_profile`. |
| Regeneracion missing_only | Ejecutar generacion dos veces. | La segunda ejecucion crea 0 dias. |
| Regeneracion refresh | Cambiar perfil y refrescar. | Solo se reemplazan dias generados por perfiles. |
| Proteccion manual/csv/api | Preparar dia manual/csv/api y refrescar. | El dia se conserva sin cambios. |

Comando sugerido:

```bash
php artisan test tests/Feature/BlockF2/DraftScheduleGenerationDomainTest.php --stop-on-failure
```

### No incluido todavia

- Interfaz de calendario diario.
- Boton visual para generar.
- Publicacion de batch.
- Snapshots persistidos de publicacion.
- CSV/XLSX o API WFM.
- `work_days`.
- `work_day_calculations`.
- Activaciones on-call.
- Motor legal, calculos, alertas, incidencias y reportes.

## Bloque Work Days base

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

Este bloque no agrega pantalla nueva. La validacion manual, si aplica, se hace por comandos y revision de base de datos.

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Jornada esperada sin eventos | Publicar una semana y refrescar `work_days` para el rango. | Se crea una jornada `pending` con `schedule_status = scheduled`, aunque no existan eventos. |
| Evento anulado | Crear eventos validos, anular uno y refrescar la jornada. | Solo los eventos validos no anulados quedan en `valid_time_event_ids`. |
| Evento sin horario | Registrar un evento valido en una fecha sin programacion publicada y refrescar. | Se crea jornada `schedule_status = unscheduled`. |
| Multi-tenant | Refrescar una empresa con eventos en otra empresa. | Solo se crean jornadas de la empresa indicada. |

Comando sugerido:

```bash
php artisan test tests/Feature/WorkDays/WorkDayFoundationTest.php --stop-on-failure
```

### No incluido todavia

- UI de jornadas.
- `work_day_calculations`.
- Motor legal.
- Horas extra.
- Alertas.
- Incidencias.
- Cierres.
- Conformidad.
- Reportes.
- API.

---

## Bloque Work Days refresco operativo

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

Este bloque agrega la forma operativa de actualizar `work_days`: manual desde `/work-days`, manual por comando y automatico por hora configurada en empresa. No agrega calculos legales.

### Ruta

- `/companies`
- `/work-days`

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Hora automatica | Entrar a `/companies`, editar configuracion y guardar una hora en `Hora automatica de jornadas`. | La hora se conserva al recargar y no aparece boton manual de actualizar jornadas. |
| Refresco manual UI | En `/work-days`, presionar `Actualizar jornadas`, seleccionar rango en el panel lateral y confirmar. | Se muestra mensaje con total, programadas y no programadas. |
| Jornada esperada | Publicar una semana, refrescar el rango y revisar base/pruebas. | Se crean jornadas desde horarios publicados aunque no existan eventos. |
| Evento sin horario | Registrar evento valido en fecha sin horario publicado y refrescar. | Se crea jornada `unscheduled`. |
| Automatico | Configurar hora local, ejecutar `php artisan work-days:auto-refresh` en esa hora. | Procesa solo empresas activas vencidas y guarda ultimo resumen. |
| Idempotencia diaria auto | Ejecutar dos veces el automatico en la misma hora local. | No duplica jornadas ni repite empresa ya procesada automaticamente ese dia. |
| Comando manual | Ejecutar `php artisan work-days:refresh --company=ID --from=YYYY-MM-DD --to=YYYY-MM-DD`. | Refresca solo esa empresa/rango. |
| Multi-tenant | Ejecutar refresco para Empresa A teniendo eventos/lotes de Empresa B. | Solo se crean o actualizan jornadas de Empresa A. |

Comando sugerido:

```bash
php artisan test tests/Feature/WorkDays/WorkDayOperationalRefreshTest.php --stop-on-failure
```

### No incluido todavia

- UI/listado de jornadas.
- `work_day_calculations`.
- Motor legal.
- Horas extra.
- Alertas.
- Incidencias.
- Cierres.
- Conformidad.
- Reportes.
- API.

---

## Bloque Work Days consulta operativa

**Estado:** En progreso.

Este bloque permite ver las jornadas ya generadas por `work_days`. Es una consulta operativa de lectura; no calcula horas, no genera alertas y no abre incidencias.

### Ruta

- `/work-days`

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Acceso manager | Entrar con owner/admin/rh y abrir `/work-days`. | Carga el listado de jornadas de la empresa activa. |
| Actualizar jornadas | En `/work-days`, presionar `Actualizar jornadas`. | Abre panel lateral con rango; no aparece en configuracion de empresa. |
| Filtros | Usar fecha, centro, tipo de horario o busqueda por trabajador. | La tabla se filtra sin mezclar datos de otras empresas. |
| Jornada programada | Refrescar jornadas desde una semana publicada y abrir `/work-days`. | Aparece con badge `Programada`, tipo de dia y minutos esperados. |
| Jornada no programada | Refrescar eventos validos sin horario publicado y abrir `/work-days`. | Aparece con badge `No programada` y conteo de eventos. |
| Supervisor | Entrar como supervisor. | La ruta queda bloqueada en este bloque inicial. |

Comando sugerido:

```bash
php artisan test tests/Feature/WorkDays/WorkDayOperationalListTest.php --stop-on-failure
```

### No incluido todavia

- Calculo de horas trabajadas.
- Motor legal.
- Horas extra.
- Alertas.
- Incidencias.
- Cierres.
- Conformidad.
- Reportes.
- API.

---

## Bloque Work Day Calculations base

**Estado:** En progreso.

Este bloque calcula una version operativa inicial de cada jornada con eventos validos. La clasificacion legal diaria se aplica desde el bloque Legal Rules versionado; horas extra, alertas e incidencias siguen pendientes.

### Ruta

- `/work-days`

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Calculo manual | En `/work-days`, presionar `Calcular jornadas`, seleccionar rango y confirmar. | Se muestra resumen de calculadas, en revision y sin eventos validos. |
| Jornada completa | Tener entrada y salida validas en el mismo dia, actualizar jornadas y calcular. | La fila muestra estado `Calculada` y minutos trabajados. |
| Pausa completa | Registrar entrada, inicio de pausa, fin de pausa y salida; calcular. | Los minutos trabajados descuentan la pausa completa. |
| Secuencia incompleta | Registrar solo entrada o dejar pausa abierta; calcular. | La jornada queda `En revision`, sin borrar eventos. |
| Sin eventos | Calcular una jornada programada sin eventos. | Permanece `Pendiente` y sin calculo activo. |
| Evento fuera de orden | Capturar eventos tardios o fuera de orden y calcular. | El resultado se arma por hora real del evento, no por orden de captura. |
| Cruce de medianoche | Registrar entrada 22:00, pausa 02:00-02:30 y salida 06:00 del dia siguiente; actualizar y calcular. | Se crea una sola jornada en la fecha de entrada con 7 h 30 min trabajadas; no aparece otra jornada no programada por la madrugada. |
| Recalculo | Calcular, corregir/anular un evento y volver a calcular. | Se crea nueva version activa y la anterior queda historica. |
| Multi-tenant | Cambiar de empresa o manipular datos de otra empresa. | Solo calcula jornadas de la empresa activa. |

Comando sugerido:

```bash
php artisan test tests/Feature/WorkDays/WorkDayCalculationFoundationTest.php --stop-on-failure
```

### No incluido todavia

- Horas extra.
- Alertas.
- Incidencias.
- Cierres.
- Conformidad.
- Reportes.
- API.

---

## Bloque Legal Rules versionado

**Estado:** Implementado/candidato a cierre.

Este bloque crea la base tecnica para reglas legales versionadas, aplica clasificacion visible en Jornadas, agrega configuracion legal segura por empresa en `/companies` y calcula minutos ordinarios/extra diarios y semanales. Todavia no genera alertas ni incidencias.

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Migraciones | Ejecutar migraciones en entorno de prueba. | Existen `legal_rules`, `legal_rule_versions` y `legal_parameters`. |
| Regla por fecha | Crear dos versiones activas de una regla con vigencias distintas desde prueba automatizada. | El resolver devuelve la version vigente para la fecha trabajada. |
| Version no activa | Crear regla inactiva o version `draft`. | No participa en resolucion. |
| Parametro empresa | Crear parametro global y parametro de empresa para el mismo codigo/fecha. | Se resuelve el de empresa; sin empresa se usa global. |
| Clasificacion diurna | Registrar entrada/salida dentro de 06:00-20:00, actualizar y calcular jornadas. | La columna `Legal` muestra `Diurna` y sin minutos nocturnos. |
| Clasificacion mixta | Registrar una jornada que combine minutos diurnos y nocturnos bajo el umbral nocturno, actualizar y calcular. | La columna `Legal` muestra `Mixta` y muestra minutos nocturnos. |
| Clasificacion nocturna | Registrar una jornada nocturna o con minutos nocturnos suficientes, actualizar y calcular. | La columna `Legal` muestra `Nocturna` y conserva snapshot de reglas. |
| Secuencia incompleta | Registrar solo entrada o dejar pausa abierta y calcular. | La columna `Legal` queda `Pendiente`. |
| Ver reglas pais | Entrar a `/companies` como owner/admin/rh. | Se muestra `Configuracion legal`, reglas base Mexico en lectura y badge `MX`. |
| Editar parametro permitido | Cambiar un limite interno a un valor igual o menor al limite protegido, capturar motivo y guardar. | Se crea `legal_parameters` con `company_id`, vigencia, motivo y actor. |
| Bloquear limite menos favorable | Intentar guardar limite diurno mayor a 480 minutos. | El sistema rechaza el valor y no crea parametro. |
| Supervisor | Entrar como supervisor. | No puede modificar configuracion legal de empresa. |
| Extra diaria | Registrar una jornada diurna de mas de 8 horas, actualizar y calcular. | La tabla muestra `Ordinario` hasta 8 h y el excedente en `Extra`. |
| Parametro mas favorable | En `/companies`, bajar el limite interno diurno a 450 minutos y recalcular una jornada de 480 minutos. | La jornada queda con 450 minutos ordinarios y 30 extra. |
| Extra semanal | Calcular siete jornadas de 8 h en la misma semana natural. | La septima jornada queda como extra cuando se supera el limite semanal. |
| Pendiente legal | Dejar una jornada con clasificacion `Pendiente`. | Ordinario y extra quedan pendientes o en 0 hasta resolver la clasificacion. |

Comando sugerido:

```bash
php artisan test tests/Feature/LegalRules --stop-on-failure
```

### No incluido todavia

- Administracion global avanzada de reglas legales por pais.
- Alertas.
- Incidencias.
- Cierres.
- Reportes.
- API.

---

## Bloque revision de capturas manuales

**Estado:** En progreso.

Este bloque permite decidir si una captura manual justificada entra o no a Jornadas. La captura nace como `En revision`; solo al aprobar pasa a `Valido` y actualiza `work_days`.

### Ruta

- `/time-events/manual`
- `/work-days`

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Captura manual | Crear una entrada o salida manual justificada. | Se guarda como `En revision` y todavia no aparece en Jornadas como evento valido. |
| Aprobar | En la tabla de eventos, presionar `Aprobar` sobre una captura pendiente. | Cambia a `Valido`, registra metadata de revision y actualiza la jornada de esa fecha. |
| Ver en Jornadas | Abrir `/work-days` en el rango de la fecha aprobada. | Aparece jornada `No programada` si no habia horario publicado, con conteo de eventos. |
| Rechazar | Crear otra captura manual, presionar `Rechazar`, capturar motivo y confirmar. | Cambia a `Ignorado`, conserva motivo y no entra a Jornadas. |
| Doble revision | Intentar aprobar o rechazar un evento ya revisado. | La accion queda bloqueada. |
| Supervisor | Entrar como supervisor. | No puede revisar capturas manuales. |
| Otra empresa | Manipular IDs o cambiar empresa. | No puede revisar eventos de otro tenant. |

Comando sugerido:

```bash
php artisan test tests/Feature/Sprint2F/ManualTimeEventCaptureTest.php --stop-on-failure
```

### No incluido todavia

- Calculo de horas trabajadas.
- Motor legal.
- Horas extra.
- Alertas.
- Incidencias.
- Cierres.
- Conformidad.
- Reportes.
- API.

---

## Limpieza de catalogos sin uso

### Objetivo

Validar que Vera Time permita limpiar catalogos capturados por error sin eliminar horario, cumplimiento, asistencia, reportes, evidencias ni auditoria.

### Casos manuales

| Pantalla | Caso | Resultado esperado |
|---|---|---|
| Centros | Eliminar centro sin unidades, trabajadores, eventos, lotes ni asignaciones. | Se elimina y desaparece del listado. |
| Centros | Eliminar centro con lote de horario o historial operativo. | Se bloquea y sugiere inactivar. |
| Unidades | Eliminar unidad sin hijos, trabajadores, alcances ni horarios. | Se elimina y desaparece del listado. |
| Unidades | Eliminar unidad usada por asignacion o horario diario. | Se bloquea y sugiere inactivar. |
| Trabajadores | Eliminar trabajador sin horarios, asistencias ni asignaciones. | Se elimina junto con relacion/credencial libre. |
| Trabajadores | Eliminar trabajador con asistencias, horarios o asignaciones. | Se bloquea y sugiere baja/inactivacion. |
| Horarios legacy | Eliminar horario sin asignaciones ni condiciones laborales. | Se elimina. |
| Asignaciones legacy | Eliminar asignacion sin asistencias en su vigencia. | Se elimina. |
| Turnos | Eliminar plantilla sin reglas ni horarios diarios. | Se elimina junto con sus segmentos. |
| Perfiles | Eliminar perfil sin asignaciones ni horarios generados. | Se elimina junto con reglas libres. |
| Asignacion de perfiles | Eliminar asignacion que no genero horarios y no fue reemplazada. | Se elimina. |
| Descansos obligatorios | Eliminar descanso interno de empresa capturado por error. | Se elimina si pertenece a la empresa activa. |

---

## Bloque F4 - correcciones versionadas de programacion diaria

Estado: implementado/candidato a cierre.

Preparacion sugerida:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleScenarioSeeder
php artisan db:seed --class=VeraTimePublishedScheduleScenarioSeeder
php artisan db:seed --class=VeraTimeCorrectedScheduleScenarioSeeder
```

Pruebas manuales:

| Area | Paso | Resultado esperado |
| --- | --- | --- |
| Crear correccion | Entrar a `/scheduling/daily`, abrir un lote `Publicado` y elegir `Crear correccion`. | Solicita motivo general y crea un borrador correctivo sin numero de version publicada. |
| Clonacion | Abrir la version correctiva. | Muestra mismos trabajadores y fechas que la version publicada. |
| Edicion | Cambiar un dia individual o con cambio masivo. | El cambio queda `Manual` y no modifica la version publicada. |
| Comparacion | Usar `Comparar con version anterior`. | Muestra dias modificados, antes y despues. |
| Validacion | Usar `Revisar antes de publicar`. | Bloquea si no hay cambios o existen pendientes. |
| Publicacion | Confirmar publicacion correctiva. | Version anterior queda `Sustituido`; la correccion recibe el siguiente numero de version y queda `Publicado`. |
| Historial | Abrir `Historial de versiones`. | Muestra versiones cronologicas, hash, motivo y estado. |
| Integridad | Verificar integridad en versiones publicadas/sustituidas. | Hash valido; una alteracion debe detectarse. |

No incluido:

- CSV/XLSX.
- API WFM.
- `work_days` y calculos legales.
- Alertas, incidencias, cierres, conformidad y reportes.

---

## Bloque F5A - dominio de importacion CSV de programacion diaria

Estado: implementado/candidato a cierre.

F5A no agrega pantalla de carga. La validacion manual se realiza con comandos y revisando registros `import_batches`, `import_rows` y `daily_schedule_assignments` en entorno local.

Preparacion sugerida:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleCsvScenarioSeeder
```

El escenario demo usa:

```text
Empresa: Demo Tienda por Calendario
Periodo: 2026-08-03 a 2026-08-16
Seeder: VeraTimeDailyScheduleCsvScenarioSeeder
```

Validaciones esperadas:

| Caso | Accion | Resultado esperado |
|---|---|---|
| Seeder F5A | Ejecutar `VeraTimeDailyScheduleCsvScenarioSeeder` dos veces. | No duplica imports por idempotencia. |
| Import valido | Revisar `import_batches` aplicado. | Queda `applied` con filas `applied` o `skipped`. |
| Import invalido | Revisar `import_batches` invalido. | Queda `invalid` con errores por fila. |
| Programacion diaria | Revisar dias del lote demo. | Filas aplicadas quedan con `source_type = csv`. |
| Borrador requerido | Intentar aplicar sobre batch no draft desde prueba automatizada. | Se bloquea por estado. |
| Preview obsoleto | Cambiar un dia despues de validar y antes de aplicar desde prueba automatizada. | La aplicacion se bloquea por huella stale. |
| Correccion draft | Aplicar CSV a correccion versionada desde prueba automatizada. | Solo modifica cobertura ya clonada. |

Comando sugerido:

```bash
php artisan test tests/Feature/BlockF5A/DailyScheduleCsvImportDomainTest.php --stop-on-failure
```

No incluido:

- Pantalla de carga CSV.
- Descarga de plantilla.
- Descarga de archivo de errores.
- XLSX.
- API WFM.
- Jobs asincronos.
- Publicacion automatica.
- `work_days` y calculos legales.
- Alertas, incidencias, cierres, conformidad y reportes.

---

## Bloque F5B - interfaz de importacion CSV de programacion diaria

Estado: implementado/candidato a cierre.

F5B agrega la importacion CSV dentro de `/scheduling/daily`. La importacion solo aplica a lotes `draft`; no publica programacion y no modifica lotes publicados.

Preparacion sugerida:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleCsvScenarioSeeder
```

Usuario recomendado:

```text
rh.store.demo@veratime.local
```

Flujo manual:

| Caso | Accion | Resultado esperado |
|---|---|---|
| Abrir pantalla | Entrar a `/scheduling/daily`, abrir un lote `Borrador`. | Muestra accion compacta `Importar CSV` dentro del lote. |
| Plantilla | Abrir `Importar CSV` y elegir `Descargar plantilla`. | Descarga CSV version 1 con trabajadores/dias del contexto del lote cuando aplica. |
| Carga valida | Cargar archivo CSV valido. | Muestra preview paginado sin errores bloqueantes. |
| Aplicar | Confirmar que se reviso la vista previa y aplicar. | Filas validas modifican el lote draft con `source_type = csv`. |
| Errores | Cargar CSV con trabajador o turno inexistente. | Queda `Con errores` y no permite aplicar. |
| Reporte de errores | Descargar errores. | Descarga CSV sin rutas privadas ni trazas tecnicas. |
| Cerrar panel | Cerrar el panel de importacion. | Regresa al calendario sin dejar bloques abiertos innecesarios. |
| Publicado | Abrir lote `Publicado`. | No aparece carga CSV editable. |
| Supervisor | Entrar con supervisor. | No puede cargar, aplicar ni descargar importaciones fuera de su permiso. |
| Otra empresa | Manipular IDs de importacion o lote. | Acceso bloqueado. |

Comando sugerido:

```bash
php artisan test tests/Feature/BlockF5B/DailyScheduleCsvImportUiTest.php --stop-on-failure
```

No incluido:

- XLSX.
- API WFM.
- Jobs asincronos.
- Publicacion automatica.
- `work_days` y calculos legales.
- Alertas, incidencias, cierres, conformidad y reportes.

---

## Bloque F3B - interfaz de programacion diaria

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

F3B agrega la pantalla `/scheduling/daily` para administrar, revisar y publicar programacion diaria desde la interfaz web. La pantalla reutiliza las Actions F1/F2/F3A y no implementa correcciones versionadas F4, importacion CSV/XLSX, API WFM ni calculos legales.

Preparacion opcional:

```bash
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleScenarioSeeder
php artisan db:seed --class=VeraTimePublishedScheduleScenarioSeeder
```

Periodo demo:

```text
2026-08-03 a 2026-08-16
```

### Flujo manual recomendado

| Caso | Accion | Resultado esperado |
|---|---|---|
| Navegacion | Entrar con `rh.office.demo@veratime.local` y abrir Horarios -> Programacion semanal. | Carga `/scheduling/daily` y muestra lotes de la empresa activa en tabla compacta. |
| Filtros | Usar filtros principales y abrir `+ Filtros`. | Los filtros avanzados aparecen solo cuando se despliegan y la pantalla mantiene espacio para el calendario. |
| Periodo del listado | Revisar la vista inicial y luego cambiar `Periodo` a `Historicas` o `Todas`. | La vista inicial muestra lotes actuales/futuros; los lotes pasados aparecen solo al pedirlos. |
| Crear lote vacio | Crear lote para un centro usando cualquier fecha dentro de una semana. | Queda en `Borrador`, normalizado a lunes-domingo; no se publica ni genera automaticamente. |
| Crear y generar | Crear lote y elegir generar desde perfiles. | Se crean dias en borrador dentro de la semana natural; dias antes del alta/baja del trabajador quedan fuera de vigencia. |
| Crear varias semanas | En `Nueva programacion semanal`, elegir 2 a 4 semanas y crear/generar. | Se crean lotes semanales separados, consecutivos, en `draft`; se abre la ultima semana creada. |
| Generar faltantes | En un lote `draft`, ejecutar Generar faltantes. | Solo completa dias sin programacion. |
| Actualizar desde perfiles | Ejecutar Actualizar desde perfiles. | Actualiza dias generados por perfil y conserva dias manuales. |
| Preparar semanas futuras | Abrir un lote, usar `Preparar semanas` y elegir 1 a 4 semanas. | Crea solo las semanas faltantes en `draft`, salta semanas existentes, genera desde modelos y abre la ultima semana preparada. |
| Clonar semana publicada | Abrir un lote `Publicado`, usar `Clonar semana`, elegir una fecha destino sin lote existente y confirmar. | `Clonar a borrador` crea una nueva semana `draft`; `Clonar y publicar` crea y publica directamente con snapshot/hash. |
| Calendario | Abrir calendario del lote. | Muestra la semana natural del lote con trabajador, clave, unidad, fecha y tipo de dia con colores por turno, descanso y pendiente. |
| Navegar semanas | Usar `Semana anterior` o `Semana siguiente` desde un lote abierto. | Abre el lote semanal existente del mismo centro. Si la semana siguiente no existe, muestra mensaje para generarla o prepararla sin pintar fechas vacias. |
| Semana lunes-domingo | Crear un lote eligiendo una fecha a media semana, por ejemplo miercoles. | El lote se guarda de lunes a domingo; los dias fuera de vigencia del trabajador aparecen bloqueados/desactivados. |
| Ocultar calendario | Usar `Ocultar calendario`. | Regresa a la lista sin dejar abierto el lote. |
| Edicion individual | Cambiar un dia a Turno, Descanso, Flexible, Guardia o Pendiente con motivo. | Guarda con `source_type = manual` usando Action de dominio. |
| Cambio masivo | Seleccionar trabajadores y rango dentro del lote; confirmar motivo. | Aplica el cambio de forma atomica o revierte todo si falla. |
| Borrar borrador | Abrir un lote `Borrador` y usar `Borrar`. | El lote se elimina definitivamente junto con sus dias/importaciones; no queda como cancelado. |
| Publicado protegido | Abrir un lote `Publicado`. | No aparece accion para borrar; solo consulta, integridad o correccion segun permisos. |
| Revisar antes de publicar | Ejecutar la revision. | Muestra bloqueos, advertencias y resumen alineado con dominio; el panel puede ocultarse. |
| Publicar | Confirmar publicacion de un lote completo. | Persiste `published_at`, `published_by`, SHA-256 y cambia a solo lectura. |
| Historial e integridad | En un lote publicado, abrir Historial o Integridad. | Los paneles aparecen solo al pedirlos y se pueden ocultar; el hash no queda como aviso permanente. |
| Supervisor con alcance | Entrar con supervisor demo con alcance vigente. | Puede consultar segun alcance; no crea, genera, edita masivo ni publica. |
| Otra empresa | Cambiar a otra empresa o manipular IDs. | No muestra ni opera lotes ajenos. |
| Responsive | Reducir ancho de pantalla. | El calendario ofrece vista en lista usable, sin depender solo de tabla ancha. |

Comando sugerido:

```bash
php artisan test tests/Feature/BlockF3B/DailyScheduleCalendarUiTest.php --stop-on-failure
```

### No incluido todavia

- Correcciones versionadas F4.
- Version 2 o supersede automatico desde UI.
- Importacion CSV/XLSX.
- API WFM.
- Activaciones on-call.
- `work_days`.
- `work_day_calculations`.
- Motor legal, calculos, alertas, incidencias, cierres, conformidad y reportes.

## Bloque F3A - publicacion atomica de batches diarios

**Estado:** Implementado/candidato a cierre si la suite automatizada permanece verde.

F3A no agrega pantalla nueva. La revision manual se limita a preparar escenarios demo y verificar por comandos que los batches validos se publican con snapshot persistido, mientras que los escenarios incompletos permanecen en borrador.

Preparacion opcional:

```bash
php artisan db:seed --class=VeraTimeScheduleProfileScenarioSeeder
php artisan db:seed --class=VeraTimeDailyScheduleScenarioSeeder
php artisan db:seed --class=VeraTimePublishedScheduleScenarioSeeder
```

El periodo demo usado por F3A es:

```text
2026-08-03 a 2026-08-16
```

### Validaciones esperadas

| Caso | Accion | Resultado esperado |
|---|---|---|
| Seeder F3A | Ejecutar `VeraTimePublishedScheduleScenarioSeeder` dos veces. | No duplica batches, no crea version 2 y conserva hashes. |
| Oficina por patron | Revisar batch demo. | Queda `published` con `snapshot_sha256`, `published_by` y `published_at`. |
| Ciclo rotativo | Revisar batch demo. | Queda `published` y conserva segmentos nocturnos con offsets. |
| Flexible | Revisar batch demo. | Queda `published` con dias `flexible` y descansos. |
| Bajo demanda | Revisar batch demo. | Queda `published` con dias `on_call`; no crea tiempo trabajado real. |
| Tienda por calendario | Revisar batch demo. | Permanece `draft` porque contiene `unassigned`. |
| Sin perfil | Revisar batch demo. | Permanece `draft` porque contiene `unassigned`. |
| Snapshot | Verificar con prueba automatizada. | JSON decodificable y hash SHA-256 coincidente. |
| Resolver diario | Verificar despues de publicar. | Devuelve `resolution_status = published` y `snapshot_sha256`. |

Comando sugerido:

```bash
php artisan test tests/Feature/BlockF3A/ScheduleBatchPublicationDomainTest.php --stop-on-failure
```

### No incluido todavia

- Interfaz de calendario diario.
- Boton visual para publicar.
- Correcciones F4 o supersede automatico.
- CSV/XLSX o API WFM.
- `work_days`.
- `work_day_calculations`.
- Activaciones on-call.
- Motor legal, calculos, alertas, incidencias y reportes.
