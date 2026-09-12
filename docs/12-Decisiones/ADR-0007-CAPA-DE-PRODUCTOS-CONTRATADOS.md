---
id: ADR-0007
title: Capa minima de productos contratados para plataforma VERA
project: Vera Time
status: Accepted
date: 2026-09-11
owner: Product Architecture
tags:
  - arquitectura
  - productos
  - suscripciones
  - customer-accounts
  - vera-suite
---

# ADR-0007 - Capa minima de productos contratados para plataforma VERA

## Contexto

VERA evolucionara de un producto unico a una suite modular SaaS.

El primer producto operativo es VERA Time. A futuro podran existir VERA Payroll y VERA RH, comprables por separado pero integrables entre si.

El modelo actual ya separa:

- `customer_accounts`: cuenta cliente/comercial.
- `companies`: empresa o tenant operativo.
- `users`: identidad global VERA.
- `company_user`: membresia y rol dentro de una empresa.

Todas las funcionalidades existentes pertenecen hoy a VERA Time, pero todavia no existe una capa formal que indique que producto contrato una cuenta cliente.

## Decision

Se agregara una capa minima de productos contratados antes de crecer hacia Payroll/RH:

```text
products
customer_account_products
```

`products` sera el catalogo de productos VERA disponibles.

`customer_account_products` representara el estado actual de un producto contratado por una cuenta cliente.

No se implementara todavia billing formal, facturacion automatica, planes completos, precios ni historial comercial avanzado.

## Estado de implementacion

Implementado en codigo como capa minima de A2.1.

- Migracion: `2026_09_11_000000_create_products_and_customer_account_products_tables.php`.
- Modelos: `Product` y `CustomerAccountProduct`.
- Constantes: `ProductKey`.
- Seeder: `ProductSeeder`.
- Servicio central: `App\Domains\Products\Support\ProductAccess`.
- Middleware: `product:time`.
- Flujos nuevos de cuenta cliente aseguran VERA Time activo por defecto.
- `/customer-accounts` permite al `super_admin` ver y cambiar el estado del producto contratado.
- Rutas operativas actuales de VERA Time usan `product:time`.
- `work-days:refresh`, `work-days:auto-refresh` y `RecalculateWorkDayFromTimeEventJob` respetan el estado operativo del producto.

## Diseno de `products`

Campos objetivo:

| Campo | Tipo Laravel/MySQL | Regla |
|---|---|---|
| `id` | `id()` bigint unsigned | Llave primaria |
| `key` | `string(50)` | Unico. Ejemplos: `time`, `payroll`, `rh` |
| `name` | `string(120)` | Nombre comercial: VERA Time, VERA Payroll, VERA RH |
| `description` | `text nullable` | Descripcion corta |
| `is_addon` | `boolean default false` | Indica si depende de otro producto |
| `requires_product_id` | `foreignId nullable` a `products` | Dependencia opcional |
| `status` | `string(30) default active` | `draft`, `active`, `inactive` |
| `metadata` | `json nullable` | Datos ligeros no criticos |
| `created_at` / `updated_at` | timestamps | Auditoria tecnica basica |

Indices:

```text
unique(key)
index(status, is_addon)
```

`key` queda como string, no enum en base de datos. La validacion canonica vivira en codigo mediante constantes para evitar migraciones estructurales cada vez que se agregue un producto nuevo.

## Diseno de `customer_account_products`

Campos objetivo:

| Campo | Tipo Laravel/MySQL | Regla |
|---|---|---|
| `id` | `id()` bigint unsigned | Llave primaria |
| `customer_account_id` | `foreignId` | Cuenta cliente |
| `product_id` | `foreignId` | Producto contratado |
| `status` | `string(30) default active` | Estado operativo/comercial del producto |
| `starts_at` | `timestamp nullable` | Inicio de acceso/contratacion |
| `trial_ends_at` | `timestamp nullable` | Fin de prueba si aplica |
| `ends_at` | `timestamp nullable` | Fin efectivo si aplica |
| `metadata` | `json nullable` | Plan, limites y referencias comerciales ligeras |
| `created_at` / `updated_at` | timestamps | Auditoria tecnica basica |

Indices:

```text
unique(customer_account_id, product_id)
index(customer_account_id, status)
index(product_id, status)
index(status, ends_at)
```

La tabla conserva una sola fila por cuenta cliente y producto. Esa fila representa el estado actual.

Si un cliente cancela un producto y despues lo reactiva, se actualiza la misma fila. El historial comercial detallado se pospone a una tabla futura:

```text
customer_account_product_events
```

## Estados de producto contratado

| Estado | Significado | Acceso UI/API | Jobs de Time |
|---|---|---|---|
| `trial` | Producto en prueba | Permitido mientras aplique la prueba | Corren |
| `active` | Producto contratado y vigente | Permitido | Corren |
| `past_due` | Pago vencido o en gracia | Decision comercial posterior; inicialmente permitido o lectura | Corren |
| `suspended` | Bloqueo administrativo temporal | Bloqueado o solo lectura segun politica | No corren |
| `cancelled` | Producto cancelado | Bloqueado | No corren |

Decision inicial para implementacion simple:

```text
trial / active / past_due = producto operativo
suspended / cancelled = producto no operativo
```

`trial` es un estado, y `trial_ends_at` conserva la fecha de vencimiento de la prueba. El estado responde que ocurre; la fecha responde cuando cambia.

`active` puede tener `ends_at = null`, que significa contrato o acceso vigente sin fecha final definida.

## Alcance por cuenta cliente, no por empresa

`customer_account_products` no tendra `company_id`.

La compra del producto se resuelve a nivel cuenta cliente:

```text
customer_accounts
        -> customer_account_products
        -> companies
```

Esto permite que una cuenta cliente multiempresa tenga VERA Time activo para todas sus empresas sin duplicar licencias por tenant operativo.

Si en el futuro una cuenta multiempresa necesita productos distintos por empresa, se agregara otra tabla:

```text
company_product_entitlements
```

Esa tabla queda fuera del MVP actual.

## Verificacion central de acceso

Se agregara una verificacion central tipo:

```text
hasProduct('time')
```

La resolucion conceptual sera:

```text
Company
  -> CustomerAccount
    -> customer_account_products
      -> Product key
      -> Status operativo
```

La logica debe vivir en un servicio reusable, no directamente en Livewire ni controllers.

Nombre sugerido:

```text
App\Domains\Products\Support\ProductAccess
```

Usos:

- Middleware de ruta para bloquear modulos completos.
- Policies para acciones sensibles.
- Jobs/commands para decidir si deben procesar empresas.

Middleware sugerido:

```text
product:time
```

Implementacion actual: el middleware `product:time` esta registrado en `bootstrap/app.php` y protege las rutas operativas de VERA Time. Las rutas de plataforma (`customer-accounts`, `companies`) quedan fuera para que `super_admin` pueda corregir cuentas, empresas y estados.

## Jobs y crons

Los procesos batch de VERA Time deben respetar el estado del producto, pero sin romper continuidad por retrasos de pago.

Decision inicial:

```text
trial / active / past_due:
  jobs de Time corren.

suspended / cancelled:
  jobs de Time no generan nueva operacion automatica.
```

Razon:

- `past_due` puede ser un estado de gracia y no debe dejar jornadas incompletas por un retraso administrativo.
- `suspended` y `cancelled` representan una decision mas fuerte y deben detener operacion automatica.

Implementacion actual:

- `work-days:auto-refresh` salta empresas cuya cuenta cliente no tenga VERA Time operativo.
- `work-days:refresh` por consola devuelve error si VERA Time no esta operativo para la empresa.
- El job de recalculo por evento no procesa eventos de empresas cuyo producto Time este suspendido o cancelado.

## Plan de migracion

Orden obligatorio para despliegue:

1. Crear tabla `products`.
2. Crear tabla `customer_account_products`.
3. Seed idempotente del producto `time`.
4. Poblar `customer_account_products` con VERA Time activo para todas las cuentas existentes.
5. Validar que no existan empresas activas sin `customer_account_id`.
6. Validar que no existan cuentas cliente sin producto `time`.
7. Implementar `ProductAccess`.
8. Ejecutar pruebas automatizadas enfocadas.
9. Activar bloqueo real cuando las validaciones esten limpias.

Validaciones SQL previas:

```sql
select * from companies where customer_account_id is null;

select ca.*
from customer_accounts ca
left join customer_account_products cap
  on cap.customer_account_id = ca.id
left join products p
  on p.id = cap.product_id and p.key = 'time'
where p.id is null;

select customer_account_id, product_id, count(*)
from customer_account_products
group by customer_account_id, product_id
having count(*) > 1;
```

## Consecuencias

Positivas:

- VERA queda preparada para vender productos modulares.
- VERA Time queda formalmente marcado como producto contratado.
- Payroll/RH podran agregarse sin reinterpretar `companies.status`.
- Se evita crear billing complejo antes de necesitarlo.

Costos:

- Se agrega una capa adicional de autorizacion.
- Los jobs deberan consultar estado de producto antes de procesar.
- El historial comercial completo queda pendiente.

## Fuera de alcance

- Facturacion automatica.
- Pasarela de pago.
- Precios/planes formales.
- Historial comercial completo.
- Licenciamiento por empresa dentro de una misma cuenta cliente.
- Permisos por producto a nivel usuario.
