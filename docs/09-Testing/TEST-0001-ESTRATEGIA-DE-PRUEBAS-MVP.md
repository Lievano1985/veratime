---
id: TEST-0001
title: Estrategia de pruebas del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-04
updated: 2026-07-04
tags:
  - testing
  - calidad
  - mvp
  - pest
  - laravel
  - api
  - motor-legal
  - multi-tenant
  - veratime
---

# TEST-0001 — Estrategia de pruebas del MVP

## 1. Objetivo

Definir la estrategia mínima de pruebas para liberar el MVP de Vera Time con un nivel aceptable de confianza técnica, operativa y legal.

El objetivo no es probar absolutamente todo, sino cubrir los riesgos críticos del producto:

- Separación de datos entre empresas.
- Registro electrónico correcto.
- Motor legal confiable.
- Reglas legales versionadas.
- Alertas preventivas.
- Correcciones no destructivas.
- Cálculos y reportes versionados.
- Conformidad digital.
- API bidireccional.
- Importaciones y exportaciones.
- Auditoría.
- Seguridad básica.
- Operación en hosting actual con MySQL 8 / MariaDB compatible.

---

## 2. Alcance

Esta estrategia aplica a:

```text
docs/03-Requisitos/REQ-0001-ESPECIFICACION-REQUISITOS-MVP.md
docs/04-Arquitectura/ARQ-0001-ARQUITECTURA-DEL-MVP.md
docs/05-BaseDatos/BD-0001-MODELO-DE-DATOS-MVP.md
docs/06-UX/UX-0001-MAPA-DE-PANTALLAS-MVP.md
docs/07-API/API-0001-ESPECIFICACION-API-MVP.md
```

---

## 3. Principio de calidad

Vera Time no puede tratarse como una app administrativa común.

El sistema genera evidencia laboral, cálculos, alertas, reportes y confirmaciones que pueden usarse para operación, nómina, auditoría o defensa documental.

Por eso, las pruebas deben priorizar:

1. Correctitud del cálculo.
2. Integridad de evidencia.
3. No destrucción de historial.
4. Separación multi-tenant.
5. Consistencia entre web, API, CSV y jobs.
6. Trazabilidad.

---

## 4. Herramientas

## 4.1 Framework de pruebas

Se usará:

```text
Pest
```

sobre el ecosistema de pruebas de Laravel.

## 4.2 Base de datos de pruebas

La base de pruebas deberá ser compatible con el motor real del MVP:

```text
MySQL 8 / MariaDB compatible
```

No se recomienda depender únicamente de SQLite para pruebas críticas, porque puede ocultar diferencias en:

- Tipos JSON.
- Índices.
- Restricciones.
- Fechas.
- Relaciones.
- Comportamiento SQL.

SQLite puede usarse para pruebas muy simples, pero las pruebas críticas del dominio deberán correr contra MySQL/MariaDB.

## 4.3 Colas

En MVP se probarán con:

```text
database queue
```

Redis queda como mejora posterior.

## 4.4 Archivos

Los archivos se probarán con storage fake/local cuando aplique, validando:

- creación;
- hash;
- relación con entidad;
- permisos;
- descarga.

---

# 5. Tipos de pruebas

## 5.1 Unitarias

Prueban clases pequeñas y reglas aisladas.

Aplican principalmente a:

- Motor legal.
- Clasificación de jornada.
- Cálculo de minutos.
- Reglas de alertas.
- Validadores de vigencia.
- Value Objects.
- Generación de fingerprints.
- Cálculo de hashes.

## 5.2 Feature tests

Prueban flujos completos de Laravel.

Aplican a:

- Alta de trabajador.
- Registro de evento.
- Recalculo de jornada.
- Generación de alerta.
- Creación de incidencia.
- Corrección aprobada.
- Cierre de periodo.
- Conformidad digital.
- Exportaciones.
- Importaciones.

## 5.3 API tests

Prueban endpoints `/api/v1`.

Cubren:

- Autenticación.
- Alcances.
- Multi-tenant.
- Idempotencia.
- Validación.
- Errores estándar.
- Respuestas.
- Logs de integración.

## 5.4 Browser/UI tests

Para MVP serán limitadas.

Cubren flujos críticos:

- Login.
- Selector de empresa.
- Kiosco.
- Portal trabajador.
- Conformidad/no conformidad.
- Cierre de periodo.

Si el tiempo no permite pruebas browser completas, se priorizarán feature tests y pruebas manuales guiadas.

## 5.5 Pruebas manuales guiadas

Se usarán checklists para validar:

- Pantallas administrativas.
- UX del trabajador.
- Kiosco.
- Reportes.
- Expedientes.
- Importaciones.

---

# 6. Pirámide de pruebas recomendada

```text
Muchas pruebas unitarias del motor legal
+ pruebas feature de flujos críticos
+ pruebas API para interoperabilidad
+ pocas pruebas UI de caminos esenciales
+ pruebas manuales guiadas antes de piloto
```

No se buscará automatizar toda la interfaz en el MVP.

---

# 7. Datos de prueba

## 7.1 Empresas de prueba

Crear fixtures mínimos:

```text
Empresa A
Empresa B
```

Objetivo:

Validar que no exista acceso cruzado.

## 7.2 Centros

```text
Centro A1
Centro A2
Centro B1
```

## 7.3 Personas trabajadoras

Casos mínimos:

```text
Trabajador diurno
Trabajador nocturno
Trabajador mixto
Trabajador con turno que cruza medianoche
Trabajador con descanso dominical
Trabajador con baja
Trabajador con relación laboral histórica
```

## 7.4 Horarios

Casos mínimos:

```text
Diurno 08:00-17:00
Nocturno 22:00-06:00
Mixto 14:00-21:30
Turno cruzando medianoche
Horario con pausa
Horario sin pausa
Horario con vigencia anterior
Horario con vigencia nueva
```

## 7.5 Eventos

Casos mínimos:

```text
Entrada/salida normal
Salida faltante
Entrada faltante
Pausa completa
Pausa incompleta
Evento duplicado
Evento tardío
Evento fuera de orden
Evento manual
Evento anulado lógicamente
Evento reemplazado
Evento API con external_id
Evento API repetido por idempotencia
```

---

# 8. Matriz de riesgos y prioridad de pruebas

| Riesgo | Impacto | Prueba obligatoria |
|---|---|---|
| Mezcla de datos entre empresas | Crítico | Multi-tenant |
| Cálculo legal incorrecto | Crítico | Motor legal |
| Corrección borra historial | Crítico | Versionamiento |
| Reporte firmado cambia | Crítico | Conformidad digital |
| API duplica eventos | Alto | Idempotencia |
| Cierre ignora alertas críticas | Alto | Cierre |
| CSV importa mal datos | Alto | Importaciones |
| Error de zona horaria | Alto | Eventos y jornadas |
| Reporte no coincide con cálculo | Alto | Reportes |
| Falta auditoría | Alto | Audit logs |
| Permisos débiles | Alto | Policies |
| Jobs no terminan | Medio | Colas |
| UI confusa | Medio | Prueba manual guiada |

---

# 9. Pruebas multi-tenant

## TEST-MT-001 — Usuario no ve trabajadores de otra empresa

Dado:

```text
Empresa A tiene Trabajador A
Empresa B tiene Trabajador B
Usuario A pertenece a Empresa A
```

Cuando:

```text
Usuario A consulta trabajadores
```

Entonces:

```text
Solo ve Trabajador A
```

## TEST-MT-002 — Manipulación de URL

Cuando un usuario de Empresa A intenta abrir un ID de Empresa B:

```text
/workers/{worker_b_id}
```

Entonces:

```text
Debe recibir 403 o 404
```

No debe revelar información.

## TEST-MT-003 — Token API no accede a otra empresa

Dado:

```text
Token B pertenece a Empresa B
Trabajador A pertenece a Empresa A
```

Cuando:

```text
Token B consulta Trabajador A
```

Entonces:

```text
Debe recibir 403 o 404
```

## TEST-MT-004 — Exportación delimitada

Una exportación de Empresa A no debe contener datos de Empresa B.

## TEST-MT-005 — Job con contexto de empresa

Un job de recalculo, importación o reporte debe ejecutar únicamente el contexto de su empresa.

---

# 10. Pruebas del registro electrónico

## TEST-REG-001 — Registrar entrada web/PWA

Debe crear evento válido con:

- trabajador;
- empresa;
- hora del hecho;
- hora de recepción;
- zona horaria;
- fuente;
- estado;
- auditoría.

## TEST-REG-002 — Registrar salida

Debe cerrar jornada operativa y permitir cálculo.

## TEST-REG-003 — Registrar desde kiosco

Debe validar:

- código de empleado;
- NIP;
- estado activo;
- empresa;
- fuente `kiosk`.

## TEST-REG-004 — NIP incorrecto

Debe rechazar el registro y auditar el intento.

## TEST-REG-005 — Evento API

Debe crear el mismo tipo de evento que la interfaz y activar el mismo flujo posterior.

## TEST-REG-006 — Idempotencia

Dos solicitudes con el mismo:

```text
company_id + source + external_id
```

o:

```text
Idempotency-Key
```

no deben duplicar evento.

## TEST-REG-007 — Evento fuera de orden

Debe aceptarse o marcarse para revisión, sin romper la jornada.

## TEST-REG-008 — Evento tardío

Debe conservar:

- hora del hecho;
- hora de recepción;
- fuente.

## TEST-REG-009 — Captura manual

Debe requerir:

- motivo;
- autor;
- auditoría;
- fuente `admin_manual`.

---

# 11. Pruebas del motor legal

El motor legal debe poder probarse sin interfaz.

Las pruebas deben ejecutarse directamente contra servicios del dominio.

## TEST-CAL-001 — Jornada diurna normal

Entrada:

```text
08:00-16:00
```

Resultado esperado:

```text
clasificación diurna
tiempo ordinario correcto
sin alerta crítica
```

## TEST-CAL-002 — Jornada nocturna

Entrada:

```text
22:00-06:00
```

Resultado esperado:

```text
clasificación nocturna
manejo correcto de cruce de medianoche
alerta si rebasa límite configurado
```

## TEST-CAL-003 — Jornada mixta

Debe calcular minutos diurnos/nocturnos y clasificar según regla aplicable.

## TEST-CAL-004 — Mixta que se convierte en nocturna

Si el tiempo nocturno rebasa el umbral configurado, debe clasificarse conforme a la regla legal vigente.

## TEST-CAL-005 — Horas extra

Debe separar:

- ordinario;
- extraordinario;
- excedente;
- alertas.

## TEST-CAL-006 — Más de doce horas

Debe generar alerta crítica o alta según configuración.

## TEST-CAL-007 — Descanso insuficiente

Debe generar alerta preventiva antes del cierre.

## TEST-CAL-008 — Pausa computable

Si la pausa debe contarse como tiempo efectivo, el cálculo debe incluirla.

## TEST-CAL-009 — Domingo

Debe marcar tiempo trabajado en domingo.

## TEST-CAL-010 — Descanso obligatorio

Debe identificar trabajo en descanso obligatorio.

## TEST-CAL-011 — Más de seis días consecutivos

Debe generar alerta.

## TEST-CAL-012 — Regla legal por vigencia

Una jornada de 2027 debe usar reglas 2027.

Una jornada de 2028 debe usar reglas 2028.

## TEST-CAL-013 — Condición más favorable

Si la empresa configura una condición más favorable, el motor debe aplicar la condición correspondiente.

---

# 12. Pruebas de reglas legales versionadas

## TEST-RULE-001 — Regla vigente por fecha

Dada una regla con vigencias diferentes, el cálculo usa la versión correspondiente a la fecha de la jornada.

## TEST-RULE-002 — Snapshot de reglas

El cálculo debe guardar un snapshot de las reglas aplicadas.

## TEST-RULE-003 — Cambio posterior de regla

Si cambia una regla legal futura, los cálculos históricos no deben cambiar automáticamente.

## TEST-RULE-004 — Reproceso controlado

Un reproceso debe generar nueva versión y dejar motivo.

---

# 13. Pruebas de alertas preventivas

## TEST-ALT-001 — Salida faltante

Debe generar alerta de jornada incompleta.

## TEST-ALT-002 — Jornada diaria excedida

Debe generar alerta con lenguaje neutral.

## TEST-ALT-003 — Jornada semanal excedida

Debe generar alerta en el acumulado semanal.

## TEST-ALT-004 — Descanso insuficiente

Debe generar alerta antes del cierre.

## TEST-ALT-005 — Duplicado

Debe detectar evento duplicado o repetido.

## TEST-ALT-006 — Alerta no duplicada

Un recalculo no debe duplicar alertas existentes innecesariamente.

## TEST-ALT-007 — Cierre bloqueado

Una alerta crítica pendiente debe bloquear cierre definitivo.

## TEST-ALT-008 — Resolver alerta

Resolver alerta debe guardar:

- usuario;
- fecha;
- comentario;
- evidencia opcional;
- estado final.

---

# 14. Pruebas de incidencias y correcciones

## TEST-INC-001 — Crear incidencia manual

Debe crear incidencia vinculada a trabajador y jornada.

## TEST-INC-002 — Crear incidencia desde alerta

Debe relacionar alerta con incidencia.

## TEST-INC-003 — Proponer corrección

Debe guardar:

- valor original;
- valor propuesto;
- motivo;
- solicitante;
- estado.

## TEST-INC-004 — Aprobar corrección

Debe:

```text
aplicar corrección
→ conservar original
→ recalcular
→ generar nueva versión
→ auditar
```

## TEST-INC-005 — Rechazar corrección

No debe modificar cálculo activo.

## TEST-INC-006 — Controversia

Debe conservar solicitud, respuesta, evidencia y estado.

## TEST-INC-007 — No eliminación destructiva

Corregir evento no debe borrar físicamente el evento original.

---

# 15. Pruebas de cálculos y reportes versionados

## TEST-VER-001 — Nueva versión por corrección

Una corrección aprobada debe generar nueva versión de cálculo.

## TEST-VER-002 — Versión anterior conservada

La versión anterior debe seguir consultable.

## TEST-VER-003 — Reporte v1 firmado no cambia

Si un reporte fue confirmado, no debe modificarse.

## TEST-VER-004 — Reporte v2 después de corrección

Una corrección posterior genera nueva versión del reporte.

## TEST-VER-005 — Firma no se transfiere

La confirmación de v1 no aplica a v2.

---

# 16. Pruebas de cierre y conformidad digital

## TEST-CON-001 — Crear periodo

Debe crear periodo con fechas y estado correcto.

## TEST-CON-002 — Generar reportes individuales

Debe generar un reporte por trabajador incluido.

## TEST-CON-003 — Alertas críticas bloquean cierre

No se debe permitir cierre definitivo con alertas críticas pendientes.

## TEST-CON-004 — Reporte disponible para trabajador

El trabajador solo ve su propio reporte.

## TEST-CON-005 — Confirmar conforme

Debe guardar:

- trabajador;
- versión;
- fecha;
- texto aceptado;
- método;
- hash;
- IP/dispositivo cuando aplique.

## TEST-CON-006 — No conforme

Debe:

```text
marcar reporte no conforme
crear incidencia
vincular comentario/evidencia
```

## TEST-CON-007 — Pendiente no equivale a conforme

La falta de respuesta no debe marcar conformidad automática.

## TEST-CON-008 — Texto sin renuncia

El texto de conformidad no debe implicar renuncia de derechos.

---

# 17. Pruebas API

## TEST-API-001 — Token válido

Permite acceso al endpoint autorizado.

## TEST-API-002 — Token inválido

Devuelve 401.

## TEST-API-003 — Token sin alcance

Devuelve 403.

## TEST-API-004 — Crear trabajador

Crea trabajador y relación mínima según payload.

## TEST-API-005 — Trabajador creado por API visible en web

Valida bidireccionalidad.

## TEST-API-006 — Evento creado por API genera cálculo

Debe ejecutar el mismo flujo que evento creado por interfaz.

## TEST-API-007 — Evento creado por API genera alerta

Si corresponde, debe crear alerta.

## TEST-API-008 — Idempotencia

Dos requests repetidos no duplican evento.

## TEST-API-009 — Error estándar

Validaciones devuelven estructura:

```json
{
  "message": "...",
  "errors": {},
  "meta": {
    "trace_id": "..."
  }
}
```

## TEST-API-010 — Logs de integración

Cada operación relevante queda registrada.

## TEST-API-011 — Rate limit

Exceso de solicitudes devuelve 429.

## TEST-API-012 — Multi-tenant API

Token de Empresa B no accede a Empresa A.

---

# 18. Pruebas de importaciones

## TEST-IMP-001 — Importar trabajadores

CSV válido crea trabajadores.

## TEST-IMP-002 — Errores por fila

CSV inválido genera errores descargables.

## TEST-IMP-003 — Importar eventos

Eventos importados generan registros fuente.

## TEST-IMP-004 — Idempotencia en importación

Reimportar mismo archivo con mismos external IDs no duplica eventos.

## TEST-IMP-005 — Resultado del lote

Debe mostrar:

- total filas;
- correctas;
- errores;
- saltadas;
- estado.

---

# 19. Pruebas de exportaciones y expedientes

## TEST-EXP-001 — Exportación prenómina

Debe generar archivo con:

- trabajador;
- periodo;
- horas ordinarias;
- horas extra;
- domingos;
- descansos obligatorios;
- incidencias.

## TEST-EXP-002 — Exportación delimitada

No incluye trabajadores fuera del centro/periodo seleccionado.

## TEST-EXP-003 — Archivo con hash

El archivo generado debe registrar hash y metadatos.

## TEST-EVD-001 — Expediente por trabajador

Debe incluir solo información del trabajador y periodo solicitado.

## TEST-EVD-002 — Manifiesto

Debe generar manifiesto con elementos incluidos.

## TEST-EVD-003 — Auditoría de descarga

Debe registrar generación y descarga.

---

# 20. Pruebas de seguridad

## TEST-SEC-001 — Rutas protegidas

Una ruta administrativa requiere autenticación.

## TEST-SEC-002 — Permisos por rol

Un trabajador no accede a panel RH.

## TEST-SEC-003 — Acceso horizontal

Usuario no puede abrir recursos de otro centro/empresa sin permiso.

## TEST-SEC-004 — Token revocado

Un token revocado no accede API.

## TEST-SEC-005 — Campos sensibles

La API no expone información innecesaria.

## TEST-SEC-006 — Auditoría de operaciones sensibles

Operaciones críticas generan audit log.

## TEST-SEC-007 — NIP no se guarda plano

El NIP debe guardarse hasheado.

---

# 21. Pruebas de UX/manuales

## 21.1 Checklist administrativo

Antes de piloto, validar manualmente:

- Crear empresa.
- Crear centro.
- Crear usuario RH.
- Crear trabajador.
- Crear horario.
- Asignar horario.
- Registrar evento.
- Ver jornada.
- Ver alerta.
- Crear incidencia.
- Corregir.
- Recalcular.
- Cerrar periodo.
- Enviar reporte.
- Ver conformidad.
- Exportar.

## 21.2 Checklist trabajador

Validar:

- Acceso.
- Registro desde kiosco.
- Ver jornada de hoy.
- Ver semana.
- Ver reporte.
- Confirmar conforme.
- Marcar no conforme.
- Adjuntar evidencia.
- Consultar aclaración.

## 21.3 Checklist nómina

Validar:

- Consultar periodo.
- Ver horas.
- Exportar archivo.
- Revisar incidencias.
- Descargar reporte.

## 21.4 Checklist jurídico/cumplimiento

Validar:

- Consultar expediente.
- Ver versiones.
- Ver conformidades.
- Ver auditoría.
- Descargar evidencia.

---

# 22. Pruebas de rendimiento mínimo

## 22.1 Objetivos MVP

| Acción | Objetivo inicial |
|---|---|
| Login | Menos de 2 segundos |
| Listado común | Menos de 3 segundos |
| Registrar evento | Menos de 3 segundos |
| Consultar jornada | Menos de 3 segundos |
| Generar reporte pequeño | Menos de 10 segundos |
| Importar CSV pequeño | Asíncrono y trazable |
| Exportar periodo | Asíncrono si tarda |

## 22.2 Casos mínimos

- 1 empresa con 50 trabajadores.
- 1 empresa con 250 trabajadores.
- 30 días de eventos.
- Cierre semanal.
- Exportación de periodo.
- Reporte por centro.

---

# 23. Pruebas en hosting actual

Como el desarrollo inicia en el hosting actual con MySQL 8 / MariaDB compatible, se deberá validar:

- PHP compatible.
- Extensiones requeridas.
- Conexión MySQL/MariaDB.
- Migraciones.
- Storage.
- Cron de Laravel Scheduler.
- Database queue.
- Permisos de carpetas.
- HTTPS.
- Variables `.env`.
- Jobs mediante cron o ejecución controlada.
- Tamaño máximo de archivos.
- Tiempo máximo de ejecución.
- Límite de memoria.

## 23.1 Riesgos del hosting

| Riesgo | Mitigación |
|---|---|
| Jobs largos fallan | Procesos por lotes pequeños |
| Cron limitado | Scheduler simple |
| Sin workers permanentes | Database queue + cron |
| Storage limitado | Archivos moderados |
| Sin Redis | No requerido en MVP |
| Límites de memoria | Reportes asíncronos y paginados |

---

# 24. Estrategia de pruebas por etapa

## 24.1 Desarrollo local

Debe correr:

- Unitarias del motor.
- Feature tests críticos.
- API tests.
- Migraciones.

## 24.2 Staging

Debe correr:

- Flujos manuales.
- Importaciones.
- Exportaciones.
- Jobs.
- Cierre de periodo.
- Portal trabajador.
- Kiosco.

## 24.3 Pre-piloto

Debe correr la suite mínima completa:

- Multi-tenant.
- Motor legal.
- API.
- Correcciones.
- Conformidad.
- Exportaciones.
- Seguridad.
- Backups básicos.

## 24.4 Producción inicial

No se ejecutan pruebas destructivas en producción.

Solo:

- smoke tests;
- monitoreo;
- revisión de logs;
- prueba de login;
- prueba de registro controlado;
- verificación de scheduler;
- verificación de backups.

---

# 25. Definición de Done por módulo

## 25.1 Registro electrónico

Se considera terminado cuando:

- registra entrada/salida;
- conserva fuente;
- soporta kiosco;
- soporta API;
- soporta idempotencia;
- genera auditoría;
- dispara cálculo.

## 25.2 Motor legal

Terminado cuando:

- clasifica jornada;
- calcula minutos;
- aplica vigencias;
- genera explicación;
- tiene pruebas unitarias de casos críticos.

## 25.3 Alertas

Terminadas cuando:

- se generan automáticamente;
- no se duplican sin control;
- tienen estado;
- tienen responsable;
- bloquean cierre si son críticas;
- usan lenguaje neutral.

## 25.4 Correcciones

Terminadas cuando:

- no borran original;
- requieren motivo;
- generan nueva versión;
- recalculan;
- auditan.

## 25.5 Conformidad digital

Terminada cuando:

- reporte se versiona;
- trabajador ve solo el propio;
- puede confirmar/no confirmar;
- guarda evidencia;
- no transfiere firma a nueva versión.

## 25.6 API

Terminada cuando:

- usa `/api/v1`;
- usa token;
- respeta empresa;
- usa alcances;
- tiene errores estándar;
- prueba idempotencia;
- no duplica lógica.

---

# 26. Pruebas mínimas antes de piloto

Antes de operar una empresa piloto real, deben pasar como mínimo:

```text
TEST-MT-001 a TEST-MT-005
TEST-REG-001 a TEST-REG-009
TEST-CAL-001 a TEST-CAL-013
TEST-ALT-001 a TEST-ALT-008
TEST-INC-001 a TEST-INC-007
TEST-VER-001 a TEST-VER-005
TEST-CON-001 a TEST-CON-008
TEST-API-001 a TEST-API-012
TEST-SEC-001 a TEST-SEC-007
```

Además, debe completarse el checklist manual de:

- RH.
- Trabajador.
- Nómina.
- Jurídico.

---

# 27. Registro de defectos

Todo defecto detectado deberá clasificarse por severidad.

## 27.1 Severidades

| Severidad | Descripción | Ejemplo |
|---|---|---|
| S1 Crítico | Bloquea operación o compromete evidencia | Mezcla datos entre empresas |
| S2 Alto | Afecta cálculo, cierre o reporte | Horas extra incorrectas |
| S3 Medio | Afecta operación con solución alterna | Filtro no funciona |
| S4 Bajo | Visual o menor | Texto mal alineado |

## 27.2 Regla de salida a piloto

No puede iniciar piloto con:

```text
S1 abiertos
S2 abiertos relacionados con cálculo, evidencia, seguridad o conformidad
```

---

# 28. Métricas de calidad

Durante desarrollo y piloto se medirán:

- Defectos por módulo.
- Tiempo de resolución.
- Errores por importación.
- Fallos de jobs.
- Alertas generadas.
- Alertas corregidas.
- Reportes conformes.
- Reportes no conformes.
- Errores API.
- Intentos fallidos de login/kiosco.
- Tiempo de generación de reportes.

---

# 29. Automatización mínima

## 29.1 Suite mínima

Comandos esperados:

```bash
php artisan test
```

o:

```bash
./vendor/bin/pest
```

## 29.2 Grupos sugeridos

```text
Unit
Feature
Api
Legal
Tenant
Security
Integration
```

## 29.3 CI futuro

Cuando el repositorio esté estable, se recomienda configurar GitHub Actions o un flujo equivalente para ejecutar:

- instalación;
- migraciones;
- pruebas;
- análisis básico;
- build de assets.

No es obligatorio para el primer avance local, pero sí recomendable antes del piloto.

---

# 30. Criterios de aceptación de la estrategia

La estrategia se considera aprobada cuando:

1. Cubre multi-tenant.
2. Cubre motor legal.
3. Cubre API.
4. Cubre registro electrónico.
5. Cubre alertas.
6. Cubre correcciones.
7. Cubre conformidad digital.
8. Cubre reportes.
9. Cubre exportaciones.
10. Cubre seguridad.
11. Define pruebas mínimas antes de piloto.
12. Define qué defectos bloquean salida.
13. Considera hosting actual con MySQL 8 / MariaDB compatible.
14. No exige infraestructura AWS para iniciar.
15. Permite evolucionar a pruebas más robustas antes de producción comercial.

---

# 31. Siguiente documento

Después de aprobar esta estrategia, el siguiente documento recomendado será:

```text
docs/10-Deployment/DEP-0001-ESTRATEGIA-DE-DESPLIEGUE-MVP.md
```

Ahí se definirá:

- Desarrollo en hosting actual.
- Configuración del hosting actual o cPanel si aplica.
- MySQL 8 / MariaDB compatible.
- Cron.
- Database queue.
- Storage.
- Backups.
- Ambientes.
- Variables de entorno.
- AWS u otra nube como evolución posterior al MVP/piloto.
- Checklist de despliegue.


