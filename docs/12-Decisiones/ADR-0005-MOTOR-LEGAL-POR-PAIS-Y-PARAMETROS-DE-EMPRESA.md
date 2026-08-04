# ADR-0005 - Motor legal por pais y parametros de empresa

## Estado

Aceptado.

## Contexto

Vera Time debe servir a empresas con reglas laborales distintas por pais y con politicas internas propias. Al mismo tiempo, el producto no debe permitir que una configuracion operativa debilite silenciosamente el cumplimiento minimo aplicable.

La decision de producto es que Vera entregue reglas base preconfiguradas por pais, empezando por Mexico, y permita configuraciones de empresa solo bajo reglas controladas, versionadas y trazables.

## Decision

El motor legal de Vera Time se compone de tres capas:

1. Reglas base por pais.
2. Parametros configurables por empresa.
3. Snapshot de reglas aplicadas en cada calculo historico.

### Reglas base por pais

Las reglas base representan el marco legal minimo que Vera conoce para un pais.

Para Mexico, Vera debe incluir reglas base como:

- ventana de jornada diurna;
- jornada nocturna;
- jornada mixta;
- limites diarios;
- limite semanal;
- reglas de horas extra;
- descanso semanal;
- domingo;
- descansos obligatorios.

Estas reglas se guardan como `legal_rules` y `legal_rule_versions`, con vigencia, fuente, estado y version.

### Parametros configurables por empresa

Cada empresa puede configurar politicas internas o condiciones mas favorables usando `legal_parameters`.

Ejemplos:

- tolerancias operativas;
- redondeos;
- politica interna de retardos;
- pausa pagada o no pagada;
- ventanas internas de captura;
- limite interno mas favorable;
- hora de corte operativo;
- reglas internas de revision.

Estos parametros pueden tener `company_id`, vigencia y estado. Si existe parametro activo de empresa para la fecha, tiene prioridad sobre el parametro global compatible.

### Reglas protegidas

Las reglas legales minimas no deben editarse libremente desde una empresa.

Una empresa no debe poder configurar valores menos favorables para la persona trabajadora sin que Vera lo bloquee o lo marque como fuera de cumplimiento, segun el tipo de parametro.

Para MVP:

- las reglas base de pais se pueden consultar;
- solo se editan parametros de empresa permitidos;
- los overrides legales avanzados quedan fuera de la UI ordinaria;
- cualquier override futuro debe ser versionado, auditable y visible.

### Snapshot historico

Cada `work_day_calculation` debe guardar snapshot de:

- regla o parametro usado;
- version;
- valor;
- fuente;
- vigencia;
- si se uso fallback;
- explicacion resumida.

Un cambio futuro en reglas o parametros no debe modificar calculos historicos ya guardados. Para reflejar cambios se requiere recalculo versionado.

## Consecuencias

- El motor legal no es solo codigo interno; debe tener salida visible y explicable.
- La UI legal debe separar "reglas pais protegidas" de "parametros de empresa editables".
- Los calculos deben consultar reglas por fecha trabajada, no por fecha actual.
- Los jobs, comandos, UI, API y pruebas deben reutilizar las mismas Actions de dominio.
- La administracion visual completa de reglas legales puede quedar fuera de P0, pero la configuracion segura por empresa debe existir antes del piloto si impacta calculos.

## Plan de implementacion

### Bloque L1 - Fundacion Legal Rules

Objetivo: crear reglas versionadas y clasificacion diaria inicial.

Incluye:

- `legal_rules`;
- `legal_rule_versions`;
- `legal_parameters`;
- resolvers por fecha;
- seeder base Mexico;
- clasificacion diaria visible en Jornadas.

No incluye:

- UI de configuracion legal;
- horas extra;
- alertas;
- incidencias.

Estado: en curso en `feature/legal-calculation-foundation`.

### Bloque L2 - Configuracion legal por empresa

Objetivo: permitir que una empresa consulte reglas pais y configure parametros internos permitidos.

Incluye:

- definicion de parametros editables;
- validacion de parametros protegidos;
- UI en configuracion de empresa o pantalla legal compacta;
- vigencia, motivo y actor;
- pruebas de bloqueo de valores no permitidos.

No incluye:

- calculo completo de horas extra;
- alertas;
- incidencias;
- administracion global avanzada de paises.

### Bloque L3 - Ordinario y extra

Objetivo: calcular minutos ordinarios y extra con base en reglas versionadas.

Incluye:

- limite diario por clasificacion;
- limite semanal;
- snapshot de reglas;
- explicacion visible.

No incluye:

- alertas preventivas;
- incidencias;
- cierres.

### Bloque L4 - Casos especiales

Objetivo: calcular domingo, descanso semanal y descanso obligatorio.

Incluye:

- dominical;
- descanso obligatorio;
- descanso semanal;
- reglas por pais/empresa donde aplique.

No incluye:

- cierre de periodo;
- conformidad;
- reportes finales.

## Casos de prueba obligatorios

1. Una empresa nueva en Mexico recibe reglas base preconfiguradas.
2. Una empresa puede configurar un parametro interno permitido con vigencia.
3. Un parametro de empresa tiene prioridad sobre el global solo para esa empresa y fecha.
4. Un valor menos favorable protegido queda bloqueado o marcado fuera de cumplimiento.
5. Un calculo guarda snapshot de reglas y parametros usados.
6. Cambiar un parametro futuro no modifica calculos historicos existentes.
7. Recalcular una jornada crea nueva version con snapshot actualizado.
8. Usuarios sin permisos no pueden modificar parametros legales de empresa.
