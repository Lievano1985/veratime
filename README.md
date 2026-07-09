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

## Sprint 1B

Avance funcional de centros:

- Centros: listado, alta/edicion, inactivacion y zona horaria por centro.

Ruta web disponible para usuario autenticado con empresa activa:

- `/centers`

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

