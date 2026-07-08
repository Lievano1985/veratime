---
id: AI-0014
title: Primer paso para iniciar desarrollo con agentes
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-05
updated: 2026-07-05
tags:
  - ai
  - codex
  - sprint-0
  - agentes
  - desarrollo
  - veratime
---

# AI-0014 — Primer paso para iniciar desarrollo con agentes

## 1. Objetivo

Este documento guía el primer uso de Codex con los agentes controlados de Vera Time.

El objetivo es iniciar el desarrollo con:

```text
Sprint 0 — Base técnica
```

Sin adelantar módulos que aún no corresponden.

---

## 2. Antes de iniciar

Verifica que existan estos archivos:

```text
AGENTS.md

docs/.ai/AI-0001-CONTEXTO-GENERAL.md
docs/.ai/AI-0002-REGLAS-OBLIGATORIAS-PARA-CODEX.md
docs/.ai/AI-0003-ARQUITECTURA-Y-PATRONES.md
docs/.ai/AI-0004-MODELO-DE-DATOS-Y-MULTITENANT.md
docs/.ai/AI-0010-PROMPT-SPRINT-0.md
docs/.ai/AI-0011-CHECKLIST-ANTES-DE-COMMIT.md
docs/.ai/AI-0013-MODO-DE-USO-AGENTES-MINIMOS.md

docs/.ai/agents/AGENT-01-ARQUITECTO-REVIEWER.md
docs/.ai/agents/AGENT-02-BACKEND-LARAVEL.md
docs/.ai/agents/AGENT-03-QA-SEGURIDAD.md
docs/.ai/agents/AGENT-04-DOCUMENTACION.md

docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md
```

---

## 3. Rama recomendada

Antes de pedirle trabajo a Codex, crear rama:

```bash
git checkout -b sprint-0-base-tecnica
```

---

# 4. Primer prompt para Codex

Este es el primer prompt que debes pegar en Codex.

```text
Codex, lee primero AGENTS.md.

Después lee estos documentos:

- docs/.ai/AI-0001-CONTEXTO-GENERAL.md
- docs/.ai/AI-0002-REGLAS-OBLIGATORIAS-PARA-CODEX.md
- docs/.ai/AI-0003-ARQUITECTURA-Y-PATRONES.md
- docs/.ai/AI-0004-MODELO-DE-DATOS-Y-MULTITENANT.md
- docs/.ai/AI-0010-PROMPT-SPRINT-0.md
- docs/.ai/agents/AGENT-02-BACKEND-LARAVEL.md
- docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md

Objetivo:
Implementar únicamente Sprint 0 — Base técnica de Vera Time.

Historias permitidas:
- BL-0001 — Inicializar proyecto Laravel
- BL-0002 — Crear estructura modular app/Domains
- BL-0003 — Configurar MySQL/MariaDB
- BL-0004 — Configurar database queue
- BL-0006 — Configurar testing base
- BL-0101 — Login y logout
- BL-0102 — Modelo Company
- BL-0103 — Contexto CurrentCompany/TenantContext
- BL-0104 — Relación usuario-empresa
- BL-0106 — Roles iniciales
- BL-0107 — Policies multi-tenant

Restricciones obligatorias:
- No implementes motor legal todavía.
- No implementes registro de jornada todavía.
- No implementes reportes todavía.
- No implementes API de negocio todavía salvo estructura mínima si es necesaria.
- No uses PostgreSQL.
- No uses Redis como dependencia obligatoria.
- No asumas AWS.
- Mantén compatibilidad con GoDaddy cPanel.
- Usa MySQL/MariaDB.
- Usa database queue.
- Mantén arquitectura domain-first.
- No pongas lógica de negocio pesada en Livewire.
- Implementa multi-tenant por company_id.
- Agrega pruebas básicas.

Antes de modificar código:
1. Resume el alcance que vas a implementar.
2. Lista archivos que esperas tocar.
3. Identifica riesgos.

Después implementa.

Cuando termines:
- No hagas commit.
- Entrega resumen de cambios.
- Lista archivos modificados.
- Lista migraciones creadas.
- Lista pruebas agregadas.
- Indica comandos que debo ejecutar.
- Señala pendientes o decisiones necesarias.
```

---

# 5. Segundo prompt — Revisión de arquitectura

Cuando Codex termine de implementar, pegar este prompt.

```text
Codex, no implementes nuevas funcionalidades.

Ahora usa las instrucciones de:

- docs/.ai/agents/AGENT-01-ARQUITECTO-REVIEWER.md
- docs/.ai/AI-0002-REGLAS-OBLIGATORIAS-PARA-CODEX.md
- docs/.ai/AI-0003-ARQUITECTURA-Y-PATRONES.md
- docs/.ai/AI-0004-MODELO-DE-DATOS-Y-MULTITENANT.md

Revisa los cambios realizados en Sprint 0.

Valida:
- que se respete domain-first;
- que la estructura modular tenga sentido;
- que no haya lógica de negocio pesada en Livewire;
- que los controllers sean delgados;
- que el multi-tenant por company_id esté bien planteado;
- que MySQL/MariaDB sea compatible;
- que no se haya agregado PostgreSQL, Redis obligatorio ni AWS obligatorio;
- que no se haya implementado funcionalidad fuera del Sprint 0.

Entrega:
- resultado de revisión;
- hallazgos críticos;
- hallazgos medios;
- recomendaciones;
- archivos que requieren ajuste;
- conclusión.
```

---

# 6. Tercer prompt — Revisión QA y seguridad

Después de la revisión de arquitectura, pegar este prompt.

```text
Codex, no implementes nuevas funcionalidades.

Ahora usa las instrucciones de:

- docs/.ai/agents/AGENT-03-QA-SEGURIDAD.md
- docs/.ai/AI-0008-TESTING-Y-CALIDAD.md
- docs/.ai/AI-0011-CHECKLIST-ANTES-DE-COMMIT.md
- docs/09-Testing/TEST-0001-ESTRATEGIA-DE-PRUEBAS-MVP.md

Revisa los cambios de Sprint 0 desde QA y seguridad.

Valida:
- autenticación;
- roles;
- relación usuario-empresa;
- company_id;
- policies;
- acceso horizontal;
- pruebas multi-tenant;
- pruebas de autenticación;
- que no existan rutas expuestas sin protección;
- que no haya secretos en archivos versionados;
- que no se haya introducido dependencia obligatoria de Redis, PostgreSQL o AWS.

Entrega:
- resultado QA;
- S1 bloqueantes;
- S2 importantes;
- pruebas faltantes;
- riesgos;
- recomendación final.
```

---

# 7. Cuarto prompt — Correcciones

Si Arquitectura o QA detectan problemas, pegar este prompt.

```text
Codex, corrige únicamente los hallazgos reportados por Arquitectura y QA/Seguridad.

No agregues nuevas funcionalidades.
No avances a Sprint 1.
No implementes motor legal, jornadas, reportes ni API de negocio.

Al terminar:
- lista archivos modificados;
- lista pruebas agregadas o ajustadas;
- explica cómo corregiste cada hallazgo;
- indica comandos de prueba.
```

---

# 8. Quinto prompt — Documentación

Cuando implementación y revisión estén correctas, pegar este prompt.

```text
Codex, usa las instrucciones de:

- docs/.ai/agents/AGENT-04-DOCUMENTACION.md

Actualiza únicamente la documentación afectada por Sprint 0.

Puedes actualizar:
- README.md si cambian comandos de instalación o ejecución;
- .env.example si hay variables necesarias;
- docs/.ai si se requiere nota operativa;
- docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md solo si hay que marcar notas o ajustes;
- changelog o notas si existen.

No cambies arquitectura.
No cambies alcance.
No cambies prioridades.
No inventes requisitos.

Entrega:
- documentación revisada;
- archivos actualizados;
- contradicciones detectadas;
- pendientes;
- mensaje de commit sugerido.
```

---

# 9. Comandos esperados

Codex debería dejarte comandos parecidos a estos:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
php artisan test
npm install
npm run build
```

No todos aplican si el proyecto ya existe.

---

# 10. Antes del commit

Ejecutar o pedir a Codex que confirme:

```bash
php artisan test
```

Si hay frontend:

```bash
npm run build
```

Revisar:

```bash
git status
git diff
```

---

# 11. Commit sugerido

Cuando todo esté aprobado:

```bash
git add .
git commit -m "sprint-0: add base technical foundation"
```

---

# 12. Qué NO debe pasar en este primer paso

No aceptar cambios que incluyan:

```text
motor legal completo
registro de jornada
alertas
incidencias
cierres
reportes
conformidad digital
ClickBalance
biometría
app nativa
Redis obligatorio
PostgreSQL
AWS obligatorio
```

Sprint 0 solo debe dejar la base técnica lista.

---

# 13. Resultado esperado de Sprint 0

Al terminar Sprint 0 deberíamos tener:

```text
Laravel listo
MySQL configurado
database queue preparada
testing base
estructura modular
login/logout
modelo Company
relación usuario-empresa
roles iniciales
contexto tenant
policies multi-tenant básicas
pruebas base
```

Con esto ya se puede avanzar después a Sprint 1:

```text
Empresas, centros y trabajadores
```


