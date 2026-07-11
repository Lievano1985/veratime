# Vera Time

Plataforma SaaS multi-tenant para la gestion del Registro Electronico de la Jornada Laboral conforme a la legislacion mexicana.

Vera es la marca principal. Vera Time es el producto enfocado en medir, administrar y evidenciar el tiempo laboral de las personas trabajadoras.

## Sprint 0

Base tecnica inicial:

- Laravel 12 + Livewire/Volt.
- MySQL 8 / MariaDB compatible.
- Database queue (`QUEUE_CONNECTION=database`).
- Estructura modular `app/Domains`.
- Multi-tenant base por `company_id`.
- Usuarios, empresas, roles iniciales y contexto de empresa activa.
- Registro publico deshabilitado durante Sprint 0.
- Las pantallas operativas requieren usuario activo con empresa activa asociada.
- En staging y production configurar `APP_DEBUG=false`.

## Sprint 1

Avance funcional inicial:

- Empresas: selector, listado, alta/edicion y configuracion basica.
- Centros: listado, alta/edicion, inactivacion y zona horaria por centro.
- Trabajadores: listado, alta/edicion, baja no destructiva y relacion laboral basica.
- Personas: condiciones laborales con vigencia y credenciales kiosco administrativas, sin kiosco operativo todavia.

Rutas web disponibles para usuario autenticado con empresa activa:

- `/companies`
- `/centers`
- `/workers`

## Sprint 2

Avance funcional inicial:

- Horarios: listado, alta/edicion, inactivacion, dias del horario y pausas programadas.
- Horarios con cruce de medianoche: `22:00` a `06:00` requiere `crosses_midnight`.
- Asignaciones de horario: trabajador, horario, vigencia, reemplazo no destructivo e inactivacion.
- Descansos obligatorios: catalogo por fecha con alcance global, empresa o centro, sin calculos de jornada.
- Eventos fuente de jornada: modelo interno `time_events`, sin pantalla ni registro operativo todavia.
- No existen todavia `/time-clock`, kiosco operativo, captura manual, API de negocio ni registro de jornada desde UI.
- No hay registro de jornada desde UI, motor legal ni calculos.

Rutas web disponibles para usuario autenticado con empresa activa:

- `/schedules`
- `/schedule-assignments`
- `/mandatory-rest-days`

## Comandos utiles

```bash
composer install
npm ci
php artisan migrate --seed
php artisan test
npm run build
```

Antes de cerrar Sprint 0:

```bash
php artisan migrate:fresh --seed
php artisan test
npm run build
```
