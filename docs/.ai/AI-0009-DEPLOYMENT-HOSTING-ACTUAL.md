---
id: AI-0009
title: Despliegue en hosting actual para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - deployment
  - godaddy
  - cpanel
  - mysql
  - veratime
---

# AI-0009 — Despliegue en hosting actual para Codex

## 1. Infraestructura inicial

El MVP inicia en:

```text
Hosting actual o cPanel si aplica
MySQL
Laravel
Livewire
Database queue
Cron de cPanel
Storage local/persistente
```

---

## 2. No asumir

Codex no debe asumir que existen:

- Redis;
- Horizon;
- PostgreSQL;
- Docker obligatorio;
- Kubernetes;
- workers permanentes;
- AWS obligatorio desde el inicio;
- S3 desde el inicio.

---

## 3. Variables base

```env
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=file
FILESYSTEM_DISK=local
APP_DEBUG=false
```

---

## 4. Scheduler

Usar Laravel Scheduler con cron:

```bash
php /home/usuario/veratime/artisan schedule:run >> /dev/null 2>&1
```

La ruta se ajustará al hosting real.

---

## 5. Queue

Usar:

```bash
php artisan queue:work --stop-when-empty
```

por cron o ejecución controlada.

En Vera Time, el scheduler ejecuta la cola operacional con:

```bash
php artisan queue:work database --queue=work-days,default --stop-when-empty --max-time=50 --tries=3
```

La cola `work-days` atiende recalculos de jornada derivados de eventos. `default` queda para jobs generales. En cPanel debe bastar con un cron a `schedule:run`; no se requiere worker permanente.

---

## 6. Storage

Los archivos sensibles no deben quedar públicos por defecto.

Descargas deben pasar por permisos.

El código debe estar preparado para migrar a S3 en el futuro usando Laravel Filesystem.

---

## 7. Document root

El dominio debe apuntar a:

```text
public/
```

No exponer todo el proyecto.

---

## 8. Backups

Antes de migraciones en staging/producción:

- backup de base;
- backup de archivos críticos;
- registro de fecha;
- prueba de restauración antes de piloto real.

---

## 9. Evolución futura a AWS u otra nube

Migrar antes de:

- empresa piloto real;
- datos reales de trabajadores;
- clientes pagando;
- API de terceros;
- expedientes laborales reales;
- reportes usados para nómina.

---

## 10. Reglas para Codex

No introducir dependencia técnica que impida correr en cPanel durante el MVP.

Si una mejora requiere AWS/Redis/S3, marcarla como futura o configurable por `.env`.

