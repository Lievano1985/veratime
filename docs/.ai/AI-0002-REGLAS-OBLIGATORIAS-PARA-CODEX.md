---
id: AI-0002
title: Reglas obligatorias para Codex
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - reglas
  - arquitectura
  - veratime
---

# AI-0002 — Reglas obligatorias para Codex

## 1. Propósito

Este archivo define reglas estrictas para Codex.

Cualquier código generado debe respetar estas reglas.

---

# 2. Reglas obligatorias

## 2.1 Base de datos

Codex debe usar:

```text
MySQL 8 / MariaDB compatible
```

No debe usar:

```text
PostgreSQL
jsonb
índices parciales exclusivos de PostgreSQL
funciones SQL incompatibles con MySQL
```

Cuando se requiera JSON, usar columnas `json` compatibles con MySQL/MariaDB.

---

## 2.2 Redis

Redis no es dependencia obligatoria del MVP.

No implementar funcionalidades que dependan obligatoriamente de:

```text
Redis
Laravel Horizon
ElastiCache
SQS
```

En MVP usar:

```text
QUEUE_CONNECTION=database
```

---

## 2.3 Hosting

El sistema debe poder correr inicialmente en:

```text
Hosting actual o cPanel si aplica + MySQL 8 / MariaDB compatible
```

Codex no debe asumir:

- Docker obligatorio;
- Kubernetes;
- AWS obligatorio desde el inicio;
- workers permanentes;
- Redis;
- PostgreSQL;
- root server.

---

## 2.4 Multi-tenant

Toda entidad operativa debe estar separada por:

```text
company_id
```

Codex debe validar multi-tenant en:

- modelos;
- consultas;
- policies;
- API;
- jobs;
- exportaciones;
- reportes;
- archivos;
- auditoría.

Nunca debe devolver información de otra empresa.

---

## 2.5 Lógica de negocio

Codex no debe poner lógica crítica en:

```text
Livewire components
Controllers
API Controllers
Jobs
Blade views
```

Debe usar:

```text
Actions
Application Services
Domain Services
Value Objects
Policies
```

Patrón:

```text
Livewire / API / CSV / Job
        ↓
Action / Application Service
        ↓
Domain Service
        ↓
Model / Persistence
```

---

## 2.6 API-first pragmático

Lo que se haga por web debe poder exponerse por API cuando aplique.

Lo que se cree por API debe verse en web.

Codex no debe crear una lógica distinta para:

- web;
- API;
- importación CSV;
- jobs.

Todos deben consumir el mismo dominio.

---

## 2.7 Correcciones

Codex no debe borrar o sobreescribir información histórica laboral.

Prohibido:

```text
eliminar eventos originales
reescribir cálculos firmados
modificar reportes confirmados
transferir conformidad de una versión a otra
```

Debe hacerse:

```text
evento original conservado
corrección registrada
nuevo cálculo
nueva versión
auditoría
```

---

## 2.8 Conformidad digital

La conformidad del trabajador debe estar ligada a:

- trabajador;
- reporte;
- versión exacta;
- texto aceptado;
- fecha/hora;
- método;
- hash;
- IP/dispositivo cuando aplique.

No debe marcarse conformidad automática por silencio.

---

## 2.9 Alertas

Las alertas deben usar lenguaje neutral.

Correcto:

```text
Posible desviación
Situación pendiente de revisión
Tiempo superior al límite configurado
```

Incorrecto:

```text
Infracción
Violación legal
Incumplimiento confirmado
Empresa sancionable
```

---

## 2.10 Regla legal

Codex no debe inventar reglas legales.

Debe usar:

- documentos legales existentes;
- `legal_rules`;
- `legal_rule_versions`;
- vigencias;
- snapshots.

Si se requiere una regla no documentada, debe dejarla como pendiente o configuración, no inventarla.

---

## 2.11 Biometría y app nativa

No implementar en P0:

```text
reconocimiento facial
huella digital
app nativa iOS/Android
geolocalización obligatoria avanzada
```

El MVP usa:

```text
web responsive / PWA
kiosco con código/NIP
```

---

## 2.12 ClickBalance

No implementar API directa con ClickBalance sin:

- documentación técnica;
- credenciales;
- ambiente de pruebas;
- formato confirmado;
- endpoints confirmados.

MVP:

```text
exportación CSV/XLSX compatible
```

---

# 3. Reglas antes de modificar código

Antes de modificar código, Codex debe identificar:

1. qué historia del backlog se está implementando;
2. qué documentos aplican;
3. qué tablas/modelos toca;
4. qué Actions/Services necesita;
5. qué pruebas mínimas agregará;
6. cómo se protege `company_id`.

---

# 4. Reglas antes de terminar

Antes de dar por terminado, Codex debe verificar:

- migraciones;
- modelos;
- relaciones;
- policies;
- factories si aplican;
- seeds si aplican;
- tests;
- multi-tenant;
- MySQL;
- no lógica duplicada;
- no dependencias fuera del MVP.


