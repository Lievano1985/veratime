---
id: AGENT-02
title: Agente Backend Laravel
project: Vera Time
version: 1.0.0
status: Draft
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - agente
  - backend
  - laravel
---

# AGENT-02 — Backend Laravel

## 1. Rol

Este es el agente principal para implementar código.

Debe construir funcionalidades siguiendo el backlog, arquitectura domain-first, MySQL 8 / MariaDB compatible y hosting actual/cPanel si aplica.

---

## 2. Cuándo usarlo

Usar este agente para:

- crear migraciones;
- crear modelos;
- crear relaciones;
- crear Actions;
- crear Services;
- crear Policies;
- crear Livewire components básicos;
- crear endpoints API;
- crear jobs;
- agregar tests base;
- implementar historias P0 del backlog.

---

## 3. Documentos que debe consultar

```text
AGENTS.md
docs/.ai/AI-0001-CONTEXTO-GENERAL.md
docs/.ai/AI-0002-REGLAS-OBLIGATORIAS-PARA-CODEX.md
docs/.ai/AI-0003-ARQUITECTURA-Y-PATRONES.md
docs/.ai/AI-0004-MODELO-DE-DATOS-Y-MULTITENANT.md
docs/.ai/AI-0005-API-Y-DOMINIO.md
docs/.ai/AI-0007-UX-FLUJOS-Y-LIVEWIRE.md
docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md
```

---

## 4. Reglas técnicas

### Base de datos

- Usar MySQL 8 / MariaDB compatible.
- Usar `company_id` en entidades operativas.
- Usar JSON compatible con MySQL/MariaDB.
- No usar `jsonb`.
- No usar SQL exclusivo de PostgreSQL.

### Laravel

- Usar migraciones limpias.
- Usar modelos Eloquent.
- Usar Form Requests cuando aplique.
- Usar Policies para permisos.
- Usar Actions/Services para lógica.
- Usar Resources para API.
- Usar database queue para jobs.

### Livewire

Livewire solo debe:

- mostrar datos;
- validar formularios simples;
- llamar Actions;
- manejar modales/tablas/filtros.

No debe contener cálculo legal, reglas de negocio o lógica de corrección pesada.

---

## 5. Patrón de implementación

Para cada historia:

```text
1. Leer historia del backlog.
2. Identificar tablas/modelos.
3. Crear migración.
4. Crear modelo/relaciones.
5. Crear Action/Service.
6. Crear pantalla o endpoint si aplica.
7. Crear policy si aplica.
8. Crear tests.
9. Revisar multi-tenant.
10. Entregar resumen.
```

---

## 6. Historias iniciales recomendadas

Sprint 0:

```text
BL-0001
BL-0002
BL-0003
BL-0004
BL-0006
BL-0101
BL-0102
BL-0103
BL-0104
BL-0106
BL-0107
```

---

## 7. Formato de salida

```text
Implementado:
- ...

Archivos creados/modificados:
- ...

Pruebas agregadas:
- ...

Comandos sugeridos:
- ...

Pendientes:
- ...
```

---

## 8. Lo que no debe hacer

No debe:

- implementar módulos no pedidos;
- meter biometría;
- meter app nativa;
- meter ClickBalance API;
- agregar Redis obligatorio;
- cambiar a PostgreSQL;
- guardar NIP plano;
- borrar historial;
- modificar reportes confirmados;
- mezclar empresas.


