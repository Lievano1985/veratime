---
id: AI-0008
title: Testing y calidad para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - testing
  - calidad
  - pest
  - veratime
---

# AI-0008 — Testing y calidad para Codex

## 1. Principio

Vera Time maneja evidencia laboral.

No basta con que la pantalla funcione.

Cada módulo crítico debe tener pruebas.

---

## 2. Herramienta

Usar:

```text
Pest
```

sobre Laravel testing.

---

## 3. Base de pruebas

Las pruebas críticas deben correr contra:

```text
MySQL 8 / MariaDB compatible
```

SQLite puede ocultar diferencias y no debe ser la única base para pruebas de dominio crítico.

---

## 4. Pruebas obligatorias por categoría

## Multi-tenant

- usuario Empresa A no ve Empresa B;
- token Empresa A no ve Empresa B;
- exportación no mezcla empresas;
- job respeta empresa.

## API

- token válido;
- token inválido;
- scope insuficiente;
- idempotencia;
- error estándar;
- logs de integración.

## Motor legal

- diurna;
- nocturna;
- mixta;
- cruce medianoche;
- horas extra;
- descanso insuficiente;
- regla por vigencia.

## Correcciones

- no borra original;
- genera nueva versión;
- recalcula;
- auditoría.

## Conformidad

- reporte confirmado no cambia;
- firma ligada a versión;
- no conformidad crea incidencia;
- pendiente no es conforme.

## Seguridad

- NIP hasheado;
- rutas protegidas;
- acceso horizontal bloqueado;
- tokens revocados no funcionan.

---

## 5. Defectos bloqueantes

No puede considerarse listo si hay:

```text
S1 abiertos
S2 en cálculo, evidencia, multi-tenant, seguridad o conformidad
```

---

## 6. Comandos

```bash
php artisan test
```

o:

```bash
./vendor/bin/pest
```

---

## 7. Reglas para Codex

Cuando Codex implemente una historia P0 debe agregar al menos:

- prueba de creación;
- prueba de permisos;
- prueba multi-tenant si toca datos de empresa;
- prueba de validación si tiene formulario/API;
- prueba de dominio si calcula o cambia estado.

---

## 8. No aceptar

No aceptar código que:

- no tenga pruebas en módulos críticos;
- rompa MySQL;
- dependa de Redis;
- mezcle empresas;
- borre historial;
- duplique lógica;
- deje `APP_DEBUG=true` en configuración de despliegue.


