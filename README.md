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

Rutas web relevantes:

- `/companies`
- `/centers`
- `/workers`

## Sprint 2

Avance funcional inicial:

- Horarios: listado, alta/edicion, inactivacion, dias del horario y pausas programadas.
- Horarios con cruce de medianoche: `22:00` a `06:00` requiere `crosses_midnight`.
- Asignaciones de horario: trabajador, horario, vigencia, reemplazo no destructivo e inactivacion.
- Descansos obligatorios: catalogo por fecha con alcance global, empresa o centro, sin calculos de jornada.
- Eventos fuente de jornada: `time_events`, registro web basico en `/time-clock`, kiosco basico en `/kiosk` y captura manual justificada en `/time-events/manual`.
- `/time-clock`
- `/kiosk` (publica para checado con codigo/NIP) crea eventos web basicos de entrada, salida e inicio/fin de pausa; usa la hora actual del sistema y no permite editar fecha u hora.
- `/kiosk` permite registrar entrada, salida e inicio/fin de pausa con codigo/NIP, token temporal, fuente `kiosk` y sin guardar ni mostrar NIP.
- `/time-events/manual` permite a roles autorizados registrar eventos con motivo, fecha/hora explicita, fuente `admin_manual` y estado `pending_review`.
- Estos flujos solo crean `time_events`; no hay motor legal, calculos, `work_days`, alertas, incidencias, reportes, API de negocio ni CSV.

Rutas web relevantes:

- `/schedules`
- `/schedule-assignments`
- `/mandatory-rest-days`
- `/time-clock`
- `/kiosk` (publica para checado con codigo/NIP)
- `/time-events/manual`
- `/kiosk` (publica para checado con codigo/NIP)


## Seeder demo local

Para cargar datos demo locales hasta Sprint 2:

```bash
php artisan db:seed --class=VeraTimeDemoSeeder
```

Datos demo principales:

- Empresa: `Vera Time Demo Completo`.
- Usuarios: `owner.demo@veratime.local`, `admin.demo@veratime.local`, `rh.demo@veratime.local`.
- Password demo local: `VeraDemo123!`.
- NIP demo local para kiosco: `1234`.

El seeder es idempotente y solo crea datos ficticios/locales. No crea motor legal, calculos, `work_days`, alertas, incidencias, reportes, API ni CSV.

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
