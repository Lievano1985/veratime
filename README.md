# Vera Time

Plataforma SaaS multi-tenant para la gestion del Registro Electronico de la Jornada Laboral conforme a la legislacion mexicana.

Vera es la marca principal. Vera Time es el producto enfocado en medir, administrar y evidenciar el tiempo laboral de las personas trabajadoras.

## Base de instalación del 1 de noviembre de 2026

Las migraciones se consolidaron de 84 a 63 para la etapa previa a clientes reales. Una base nueva se instala normalmente; una base de pruebas con el historial anterior debe recrearse de forma explícita. Consultar [el procedimiento de instalación y recreación](docs/10-Deployment/DEP-0002-BASE-CONSOLIDADA-2026-11-01.md) antes de ejecutar comandos de base de datos.

Las secciones de sprints siguientes conservan el historial de desarrollo. Para el recorrido y las pruebas actuales, consultar [la guía de pruebas](docs/09-Testing/GUIA-PRUEBAS-MANUALES-POR-SPRINT.md) y el informe de auditoría del 1 de noviembre de 2026.

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


## Instalación limpia y demos opcionales

La instalación normal carga solo catálogos, sin empresas ni usuarios:

```bash
php artisan db:seed
```

Para crear el acceso de plataforma, ejecuta `php artisan veratime:create-super-admin` desde una terminal interactiva. Solicita nombre, correo, contraseña oculta y confirmación. No acepta la contraseña como argumento ni la toma de variables de entorno; solo guarda su hash. No reemplaza cuentas existentes.

Las empresas se crean vacías desde Empresas. Para cargar datos ficticios se utiliza Generar demo y se elige uno de los escenarios disponibles. La captura manual sigue disponible. Los seeders históricos se retiraron de database/seeders; sus fixtures se conservan exclusivamente bajo tests/Fixtures/Seeders y rechazan ejecutarse fuera del entorno de pruebas.

ProductSeeder solo registra el catálogo de productos: no activa productos en cuentas, no cambia sus estados ni restablece sus fechas o metadatos. El alta de cuentas y la administración de productos mantienen sus flujos específicos.

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
