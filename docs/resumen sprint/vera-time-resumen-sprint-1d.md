# Vera Time - Resumen de Logros del Sprint 1D

## Resumen general

En **Sprint 1D** Vera Time agrego condiciones laborales con vigencia y credenciales administrativas para futuro uso de kiosco.

Este sprint no construyo horarios, registro de jornada, kiosco operativo, eventos, portal trabajador, motor legal, alertas, incidencias ni reportes. Se enfoco en completar la base de personas trabajadoras antes de avanzar a horarios y registro.

En pocas palabras:

```text
Sprint 1D dejo lista la base de condiciones laborales con vigencia
y credenciales kiosco seguras, sin activar todavia el flujo operativo de kiosco.
```

---

## 1. Se creo la base de condiciones laborales

Ya existe la tabla de condiciones laborales.

Cada condicion pertenece obligatoriamente a:

```text
empresa
relacion laboral
```

La condicion guarda informacion como:

```text
modalidad de trabajo
horas semanales
dia de descanso
fecha de inicio de vigencia
fecha de fin opcional
estado
```

Esto prepara el sistema para saber que condiciones aplicaban a una persona trabajadora en una fecha determinada.

---

## 2. Se protegio el historial de condiciones

Sprint 1D no sobrescribe condiciones anteriores.

Cuando se crea una nueva condicion activa:

```text
la condicion anterior se conserva
la condicion anterior pasa a replaced
su vigencia termina el dia anterior a la nueva condicion
se crea una nueva condicion activa
```

Tambien se bloquean condiciones activas solapadas para la misma relacion laboral.

Esto respeta el principio de Vera Time:

```text
no borrar ni pisar historial laboral
```

---

## 3. Se dejo preparado schedule_id sin crear horarios

La condicion laboral incluye `schedule_id` como campo nullable para uso futuro.

Pero Sprint 1D no creo:

```text
horarios
asignacion de horarios
schedule_assignments
```

Eso queda para Sprint 2.

---

## 4. Se agregaron credenciales kiosco administrativas

Ya existe la tabla de credenciales de trabajador.

Cada credencial pertenece obligatoriamente a:

```text
empresa
trabajador
```

La credencial permite guardar:

```text
codigo de acceso
NIP hasheado
estado
intentos fallidos
fecha de ultimo cambio
```

El codigo de acceso es unico dentro de una empresa, pero puede repetirse en empresas distintas.

---

## 5. Se protegieron los NIP

El NIP temporal no se guarda en texto plano.

El sistema usa hash de Laravel para guardar:

```text
pin_hash
```

Tambien se corrigio la observacion de QA:

```text
temporal_pin se limpia despues de crear credencial
temporal_pin se limpia despues de resetear NIP
temporal_pin se limpia tambien cuando hay errores de validacion
pin_hash no se muestra en la interfaz
```

---

## 6. Se agrego integracion minima en trabajadores

La pantalla:

```text
/workers
```

ahora permite administrar, dentro del panel de trabajador:

```text
condicion laboral vigente
historial simple de condiciones
crear o reemplazar condicion
crear credencial
resetear NIP
bloquear credencial
ver estado de credencial
```

No se agrego pantalla de kiosco ni registro de jornada.

---

## 7. Se protegieron condiciones y credenciales por empresa

Se agregaron policies y validaciones para evitar acceso incorrecto.

El sistema bloquea:

```text
relaciones laborales de otra empresa
trabajadores de otra empresa
credenciales de otra empresa
operaciones con empresa inactiva
roles no autorizados
manipulacion de company_id desde formulario
```

---

## 8. Se mantuvo la logica fuera de Livewire

La pantalla coordina la interfaz.

La logica principal vive en Actions:

```text
crear o reemplazar condicion laboral
actualizar condicion sin pisar historial
crear o actualizar credencial
resetear NIP
bloquear credencial
```

Esto deja el modulo preparado para reutilizar estas reglas despues desde API, CSV o jobs.

---

## 9. Se agregaron pruebas de seguridad, vigencia y NIP

Sprint 1D agrego pruebas para confirmar que condiciones y credenciales funcionan de forma segura.

Entre lo probado:

```text
crear condicion inicial
reemplazar condicion vigente sin borrar la anterior
conservar datos historicos de la condicion anterior
bloquear solapamientos activos
bloquear relacion laboral de otra empresa
bloquear empresa inactiva
validar modalidad, horas, descanso, fechas y estado
crear credencial para trabajador propio
bloquear credencial para trabajador ajeno
access_code unico por empresa
access_code repetible en otra empresa
NIP requerido y con longitud minima
pin_hash no es igual al NIP temporal
pin_hash no aparece en UI
temporal_pin se limpia en exito y error
reset de NIP actualiza last_changed_at
bloqueo de credencial cambia status a blocked
```

---

## 10. Validaciones finales

Validaciones reportadas para el cierre:

```text
php artisan migrate:fresh --seed -> OK
php artisan test tests\Feature\Sprint1D\WorkerConditionsAndCredentialsTest.php -> OK, 22 tests / 82 assertions
php artisan test -> OK, 117 tests / 315 assertions
npm.cmd run build -> OK
```

---

## 11. Pendientes S3 no bloqueantes

Quedaron pendientes recomendados para seguimiento posterior:

```text
mover las secciones de condicion y credencial a detalle de trabajador en BL-0307
agregar pruebas unitarias directas para LaborConditionPolicy
agregar pruebas unitarias directas para WorkerCredentialPolicy
reforzar pruebas de concurrencia para vigencias si luego entran API o jobs
revisar cascadeOnDelete antes de datos reales
```

No bloquean el cierre de Sprint 1D.

---

## Que NO se hizo todavia

Sprint 1D no construyo:

```text
horarios
asignacion de horarios
registro de jornada
kiosco operativo
eventos de entrada o salida
portal trabajador
motor legal
alertas
incidencias
reportes
conformidad digital
API de negocio
importacion CSV
ClickBalance
biometria
app nativa
```

Eso fue correcto, porque Sprint 1D solo correspondia a condiciones laborales con vigencia y credenciales kiosco administrativas.

---

## Estado final del Sprint 1D

```text
Estado: Candidato a cierre
Alcance: BL-0304, BL-0305
Backend: Validado
Arquitectura: Aprobado con observaciones no bloqueantes
QA y seguridad: Aprobado con observacion S2 corregida
Pruebas: Validadas
Build frontend: Validado
Alcance fuera de Sprint 1D: No implementado
```
