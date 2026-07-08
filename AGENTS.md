# AGENTS.md — Vera Time

## Propósito

Este archivo contiene las instrucciones globales para Codex al trabajar en el repositorio de Vera Time.

Codex debe leer este archivo antes de modificar código.

---

## Contexto del proyecto

Vera es la marca principal. Vera Time es el producto enfocado en medición, administración y evidencia del tiempo laboral.

Vera Time es un SaaS multi-tenant para registro electrónico de jornada laboral, evidencia, alertas preventivas, correcciones, conformidad digital, reportes y cumplimiento laboral en México.

El enfoque del producto es:

```text
cumplimiento + evidencia + trazabilidad
```

---

## Documentos obligatorios de referencia

Antes de trabajar, consultar cuando aplique:

```text
docs/.ai/AI-0001-CONTEXTO-GENERAL.md
docs/.ai/AI-0002-REGLAS-OBLIGATORIAS-PARA-CODEX.md
docs/.ai/AI-0003-ARQUITECTURA-Y-PATRONES.md
docs/.ai/AI-0004-MODELO-DE-DATOS-Y-MULTITENANT.md
docs/.ai/AI-0011-CHECKLIST-ANTES-DE-COMMIT.md
docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md
```

---

## Reglas técnicas obligatorias

- Usar Laravel, Livewire, Blade y Tailwind.
- Usar MySQL 8 / MariaDB compatible.
- No usar PostgreSQL.
- No usar Redis como dependencia obligatoria del MVP.
- No asumir AWS como dependencia inicial.
- Mantener compatibilidad con hosting actual/cPanel si aplica.
- Usar database queue.
- Mantener arquitectura domain-first.
- Mantener API-first pragmático y bidireccional.
- Separar datos por `company_id`.
- No duplicar lógica entre web, API, CSV y jobs.
- No meter lógica de negocio pesada en Livewire.
- No borrar historial laboral.
- No modificar reportes confirmados.
- No marcar conformidad por silencio.
- No implementar biometría ni app nativa en P0.
- No implementar ClickBalance API directa sin documentación y credenciales.

---

## Agentes controlados de trabajo

Para esta etapa usar solo estos agentes:

```text
docs/.ai/agents/AGENT-01-ARQUITECTO-REVIEWER.md
docs/.ai/agents/AGENT-02-BACKEND-LARAVEL.md
docs/.ai/agents/AGENT-03-QA-SEGURIDAD.md
docs/.ai/agents/AGENT-04-DOCUMENTACION.md
```

No crear más agentes sin instrucción explícita.

---

## Flujo recomendado

1. Leer historia del backlog.
2. Identificar documentos aplicables.
3. Diseñar solución mínima.
4. Implementar usando Actions/Services.
5. Agregar pruebas.
6. Revisar multi-tenant.
7. Revisar seguridad.
8. Actualizar documentación afectada.
9. Ejecutar checklist antes de commit.
10. Entregar resumen con archivos modificados, pruebas y documentación actualizada.

---

## Formato esperado al finalizar una tarea

Codex debe responder con:

```text
Resumen:
- ...

Archivos modificados:
- ...

Pruebas:
- ...

Documentación:
- ...

Riesgos o pendientes:
- ...
```


