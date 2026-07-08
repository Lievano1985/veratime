---
id: AI-0011
title: Checklist antes de commit para código generado por IA
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - checklist
  - review
  - veratime
---

# AI-0011 — Checklist antes de commit

## 1. Propósito

Usar esta lista antes de aceptar código generado por Codex.

---

# 2. Checklist general

- [ ] La historia del backlog está identificada.
- [ ] No se implementó funcionalidad fuera del alcance.
- [ ] El código corre en MySQL/MariaDB.
- [ ] No se agregó PostgreSQL.
- [ ] No se agregó Redis como obligatorio.
- [ ] No se asumió AWS como dependencia inicial.
- [ ] No se introdujo Docker/Kubernetes obligatorio.
- [ ] No se metió biometría.
- [ ] No se metió app nativa.
- [ ] No se implementó ClickBalance API sin documentación.

---

# 3. Arquitectura

- [ ] La lógica de negocio está en Actions/Services.
- [ ] Livewire solo orquesta interfaz.
- [ ] API controllers son delgados.
- [ ] Jobs no contienen lógica de dominio pesada.
- [ ] No hay duplicación de lógica entre web/API/CSV/jobs.
- [ ] Se respeta estructura modular.

---

# 4. Multi-tenant

- [ ] La entidad tiene `company_id` si aplica.
- [ ] Las consultas filtran por empresa.
- [ ] Las policies validan empresa.
- [ ] Los jobs conservan contexto de empresa.
- [ ] La API resuelve empresa por token.
- [ ] Hay prueba de acceso cruzado.

---

# 5. Seguridad

- [ ] Rutas protegidas.
- [ ] Roles/permisos aplicados.
- [ ] NIP hasheado si aplica.
- [ ] Tokens no se registran en logs.
- [ ] Datos sensibles no se exponen.
- [ ] `APP_DEBUG=false` considerado para despliegue.

---

# 6. Datos e historial

- [ ] No se borra historial laboral.
- [ ] Correcciones no son destructivas.
- [ ] Reportes confirmados no se modifican.
- [ ] Cambios relevantes generan auditoría.
- [ ] Vigencias se respetan.

---

# 7. API

- [ ] Endpoint bajo `/api/v1`.
- [ ] Usa Bearer token/Sanctum si aplica.
- [ ] Valida scopes.
- [ ] Devuelve error estándar.
- [ ] Registra trace/log si aplica.
- [ ] Soporta idempotencia cuando aplica.

---

# 8. Testing

- [ ] Hay pruebas unitarias/feature según corresponda.
- [ ] Hay prueba multi-tenant si toca datos de empresa.
- [ ] Hay prueba API si toca endpoint.
- [ ] Hay prueba de validación.
- [ ] Las pruebas corren o queda indicado cómo correrlas.

---

# 9. Despliegue

- [ ] Migraciones son seguras.
- [ ] Seeds no son destructivos.
- [ ] No requiere worker permanente.
- [ ] No requiere Redis.
- [ ] No requiere S3.
- [ ] No rompe hosting actual/cPanel si aplica.
- [ ] Usa `.env` para configuración.

---

# 10. Antes del commit

Comandos sugeridos:

```bash
php artisan test
php artisan migrate:fresh --seed
npm run build
```

Ajustar según etapa y ambiente.

---

# 11. Mensaje de commit sugerido

Formato:

```text
<area>: <acción breve>
```

Ejemplos:

```text
auth: add company-aware login context
tenancy: add company user relationship
queue: configure database queue
api: add worker endpoints
```


