---
id: NEG-0001
title: Modelo de negocio de Vera Time
project: Vera Time
version: 1.0.0
status: Draft
owner: Founder
created: 2026-07-01
updated: 2026-07-03
tags:
  - negocio
  - saas
  - multi-tenant
  - cumplimiento-laboral
  - mvp
---

# NEG-0001 — Modelo de negocio de Vera Time

## 1. Objetivo

Definir cómo Vera Time generará valor, quién pagará por la plataforma, quién la utilizará, qué se venderá durante el MVP y cómo podrá convertirse en un negocio SaaS sostenible.

Este documento transforma la investigación jurídica y el alcance aprobado del MVP en una propuesta comercial y operativa.

No define todavía pantallas, tablas, endpoints ni casos de uso detallados. Esos elementos se desarrollarán en las siguientes etapas.

---

## 2. Resumen ejecutivo

**Vera** será la marca principal para una futura familia de aplicaciones empresariales.

**Vera Time** será el primer producto de esa familia, enfocado en medir, administrar y evidenciar el tiempo laboral de las personas trabajadoras.

Vera Time será una plataforma SaaS multi-tenant especializada en el **Registro Electrónico de la Jornada Laboral en México**.

Su propósito no será únicamente registrar entradas y salidas. La plataforma deberá ayudar a las empresas a:

- Configurar jornadas, horarios y turnos.
- Registrar eventos laborales de forma electrónica.
- Calcular jornadas ordinarias, nocturnas, mixtas y extraordinarias.
- Administrar descansos, incidencias y correcciones.
- Conservar evidencia digital íntegra y trazable.
- Dar transparencia a la persona trabajadora.
- Preparar información para auditorías e inspecciones.
- Adaptarse a cambios regulatorios sin reconstruir todo el sistema.

El modelo recomendado es:

```text
Suscripción mensual por persona trabajadora activa
+ cuota mínima mensual por empresa
+ implementación inicial
+ servicios e integraciones opcionales
```

La posición comercial será:

> **Vera Time convierte el registro de asistencia en evidencia laboral explicable, trazable y preparada para el cumplimiento mexicano.**

Vera Time será una plataforma integrable, preparada para operar mediante interfaz web, importaciones, API y futuras integraciones, sin depender de un solo canal de captura.

---

## 3. Problema que resuelve

Muchas empresas registran la asistencia mediante:

- Hojas de cálculo.
- Relojes checadores aislados.
- Firmas en papel.
- Aplicaciones sin historial de cambios.
- Sistemas de nómina que solo reciben totales.
- Procedimientos manuales de autorización.
- Plataformas generales de recursos humanos que no explican el cálculo legal.

Esto genera problemas como:

- Registros incompletos o contradictorios.
- Falta de trazabilidad sobre correcciones.
- Horas extraordinarias calculadas de forma inconsistente.
- Dificultad para reconstruir una jornada.
- Información distribuida entre distintos sistemas.
- Riesgo al atender una inspección o controversia.
- Falta de claridad para la empresa y la persona trabajadora.
- Dependencia de archivos manipulables.
- Imposibilidad de conocer qué regla legal se utilizó.

El problema principal no es únicamente “checar”.

El problema es:

> **Demostrar de forma clara, íntegra y reproducible qué ocurrió durante la jornada laboral y cómo se obtuvo cada resultado.**

---

## 4. Solución propuesta

Vera Time proporcionará un núcleo especializado compuesto por:

1. Configuración de empresas, centros y relaciones laborales.
2. Administración de horarios, turnos y vigencias.
3. Registro electrónico de eventos.
4. Motor de cálculo legal versionado.
5. Motor de alertas preventivas de posibles desviaciones.
6. Administración de incidencias y correcciones.
7. Revisión y conformidad digital de la jornada por parte de la persona trabajadora.
8. Portal de consulta para personas trabajadoras.
9. Reportes operativos y regulatorios.
10. Expedientes exportables.
11. Bitácora de cambios.
12. API e importaciones básicas.
13. Seguridad, aislamiento multi-tenant y respaldos.

La plataforma registrará hechos y calculará resultados sin eliminar u ocultar información para aparentar cumplimiento.

Además, identificará posibles desviaciones antes del cierre del periodo y permitirá que la persona trabajadora revise sus registros, manifieste conformidad o solicite una aclaración.

---

## 5. Posicionamiento

### 5.1 Lo que Vera Time sí será

- Una plataforma mexicana especializada en jornada laboral.
- Un sistema de evidencia y trazabilidad.
- Un motor de reglas legales versionadas.
- Una fuente confiable para operación, prenómina e inspecciones.
- Una plataforma compatible con diferentes métodos de captura.
- Un producto que protege documentalmente a empresa y persona trabajadora.

### 5.2 Lo que no será durante el MVP

- Una nómina integral.
- Un ERP.
- Una suite completa de recursos humanos.
- Un fabricante de hardware.
- Un sistema de vigilancia.
- Una aplicación basada obligatoriamente en biometría o geolocalización.
- Un sustituto de la asesoría jurídica.
- Un sistema que declare automáticamente la existencia de una infracción.

### 5.3 Diferenciación principal

Las plataformas actuales de recursos humanos ofrecen control de asistencia, turnos, geolocalización, nómina u otros módulos amplios. Vera Time competirá mediante profundidad y especialización:

| Plataforma generalista | Vera Time |
|---|---|
| Control de asistencia como un módulo más | Jornada y evidencia como núcleo del producto |
| Resultados orientados principalmente a operación o nómina | Resultados explicables desde la regla y los eventos |
| Reporta incidencias después de ocurridas | Detecta posibles desviaciones antes del cierre |
| Correcciones administrativas | Correcciones no destructivas y trazables |
| El administrador modifica registros | Las correcciones conservan historial y versiones |
| El trabajador solo registra asistencia | El trabajador revisa, confirma o cuestiona su reporte |
| Reportes generales | Expedientes reproducibles para revisión |
| Reporte estático | Reporte versionado con evidencia de revisión |
| Reglas normalmente configuradas en la aplicación | Reglas legales versionadas por vigencia |
| Suite amplia de RH | Especialización en cumplimiento de jornada en México |

---

## 6. Mercado objetivo

Los Censos Económicos 2024 reportaron más de 5.4 millones de unidades económicas en México. La mayoría son microempresas; sin embargo, el mercado inicial de Vera Time no debe intentar abarcar a todas.

### 6.1 Segmento inicial prioritario

Empresas privadas con:

- Entre 20 y 250 personas trabajadoras.
- Uno o varios centros de trabajo.
- Turnos fijos, rotativos, nocturnos o mixtos.
- Uso de horas extraordinarias.
- Operación presencial, híbrida o distribuida.
- Procesos manuales o sistemas fragmentados.
- Necesidad de integrar información con nómina.
- Riesgo operativo o documental ante revisiones.

### 6.2 Sectores iniciales recomendados

- Servicios profesionales con varios equipos.
- Comercios y cadenas pequeñas o medianas.
- Logística y distribución.
- Seguridad privada.
- Mantenimiento y servicios de campo.
- Manufactura ligera.
- Restaurantes y hospitalidad.
- Salud privada.
- Construcción y servicios técnicos.
- Corporativos con múltiples razones sociales.
- Despachos que administran procesos laborales de varios clientes.

### 6.3 Segmentos secundarios

- Empresas de 251 a 1,000 personas, mediante venta consultiva.
- Despachos laborales, contables y de recursos humanos.
- Proveedores de nómina que necesiten una fuente confiable de jornada.
- Integradores de relojes checadores.
- Asociaciones empresariales.
- Empresas con sistemas propios que requieran el motor mediante API.

### 6.4 Segmentos no prioritarios durante el lanzamiento

- Personas físicas sin personal.
- Microempresas con muy pocos trabajadores y procesos simples.
- Sector público.
- Empresas que exijan hardware propio desde el inicio.
- Operaciones internacionales.
- Clientes que busquen reemplazar toda su nómina o ERP.

---

## 7. Personas compradoras y usuarias

### 7.1 Comprador económico

La persona que aprueba el gasto puede ser:

- Dirección general.
- Dirección administrativa.
- Dirección de recursos humanos.
- Dirección financiera.
- Socio o propietario.
- Corporativo responsable de cumplimiento.

Sus intereses principales son:

- Reducir riesgo.
- Controlar costos laborales.
- Evitar disputas.
- Tener información confiable.
- Estar preparado para inspecciones.
- Implementar antes de la entrada en vigor regulatoria.

### 7.2 Responsable funcional

Normalmente será:

- Recursos humanos.
- Relaciones laborales.
- Nómina o prenómina.
- Administración de personal.
- Operaciones.

Necesita:

- Configurar horarios.
- Revisar incidencias.
- Autorizar correcciones.
- Consultar reportes.
- Entregar información a nómina.
- Atender solicitudes de trabajadores.

### 7.3 Usuario operativo

Puede ser:

- Supervisor.
- Jefe de turno.
- Administrador de centro.
- Personal de nómina.
- Responsable de cumplimiento.

### 7.4 Persona trabajadora

Utilizará la plataforma para:

- Registrar eventos.
- Consultar su jornada.
- Revisar incidencias.
- Solicitar correcciones.
- Consultar resoluciones.
- Conocer el horario o turno aplicable.

### 7.5 Administrador de Vera Time

El equipo interno del SaaS deberá:

- Crear y administrar empresas.
- Gestionar planes.
- Dar soporte.
- Supervisar consumo y salud del servicio.
- Administrar configuraciones legales globales.
- Atender incidentes sin acceder innecesariamente a información sensible.

---

## 8. Propuesta de valor por actor

| Actor | Valor principal |
|---|---|
| Dirección | Reducción de riesgo y visibilidad sobre la operación laboral |
| Recursos humanos | Menos trabajo manual y procesos consistentes |
| Nómina | Información estructurada y explicable antes del cálculo |
| Jurídico | Evidencia íntegra, histórica y exportable |
| Operaciones | Turnos, incidencias y alertas en un mismo lugar |
| Supervisor | Revisión sencilla de excepciones |
| Persona trabajadora | Transparencia, conformidad digital y mecanismo de aclaración |
| Despacho o aliado | Administración de varias empresas desde una plataforma |
| Autoridad o auditor | Información delimitada, comprensible y verificable |

Vera Time identifica posibles desviaciones antes del cierre del periodo y permite que la persona trabajadora revise sus registros, manifieste conformidad o solicite una aclaración.

---

## 9. Modelo de ingresos

### 9.1 Suscripción recurrente

La unidad principal de cobro será:

```text
persona trabajadora activa por mes
```

Se considera activa a la persona que:

- Tiene una relación laboral vigente.
- Puede registrar jornada durante el periodo.
- Se incluye en los cálculos y reportes del servicio.

No deberán cobrarse como personas activas:

- Registros archivados.
- Bajas históricas.
- Candidatos.
- Usuarios administrativos que no registran jornada, salvo planes que incluyan licencias específicas.

### 9.2 Cuota mínima

Para cubrir infraestructura, soporte y administración, cada empresa tendrá una cuota mínima mensual aunque tenga pocos trabajadores.

### 9.3 Implementación inicial

La implementación podrá incluir:

- Configuración de empresa y centros.
- Importación de trabajadores.
- Configuración de horarios y turnos.
- Capacitación.
- Acompañamiento al arranque.
- Revisión de integración.
- Configuración de políticas.
- Soporte intensivo del piloto.

### 9.4 Ingresos adicionales

- Integraciones personalizadas.
- Conectores con nómina.
- Migración de históricos.
- Capacitación adicional.
- Soporte prioritario.
- SLA empresarial.
- Entornos dedicados.
- Almacenamiento adicional.
- Consultoría de implementación.
- Marca blanca para aliados, en una etapa posterior.
- API empresarial.
- Gestión avanzada de múltiples razones sociales.

### 9.5 Conceptos que no deben cobrarse por separado en el núcleo

Para no debilitar la propuesta principal, los siguientes elementos deberán formar parte del plan base:

- Registro de inicio y finalización.
- Consulta individual.
- Correcciones trazables.
- Cálculo legal básico.
- Reportes esenciales.
- Seguridad y respaldos mínimos.
- Actualizaciones regulatorias del núcleo.

---

## 10. Hipótesis inicial de planes y precios

Los precios actuales de plataformas de RH y asistencia en México muestran referencias aproximadas desde $45 hasta más de $100 MXN por persona al mes, dependiendo de módulos, forma de pago y alcance.

Vera Time deberá validar sus precios con empresas piloto. La siguiente estructura es una hipótesis comercial, no una tarifa definitiva.

### 10.1 Plan Cumplimiento

**Cliente:** empresa de 20 a 75 personas.

**Precio de validación:**

```text
$49 MXN por persona activa/mes
mínimo mensual de $1,490 MXN
```

Incluye:

- Una empresa.
- Hasta dos centros.
- Registro electrónico.
- Horarios y turnos básicos.
- Motor legal.
- Incidencias y correcciones.
- Portal de la persona trabajadora.
- Reportes esenciales.
- Exportación básica.
- Soporte estándar.

### 10.2 Plan Operación

**Cliente:** empresa de 50 a 250 personas o con operación más compleja.

**Precio de validación:**

```text
$69 MXN por persona activa/mes
mínimo mensual de $3,450 MXN
```

Incluye lo anterior, más:

- Múltiples centros.
- Turnos rotativos.
- Flujos de autorización.
- Reportes avanzados.
- API e importaciones.
- Exportación para prenómina.
- Expedientes de evidencia.
- Soporte prioritario.
- Retención ampliada.

### 10.3 Plan Corporativo

**Cliente:** empresa con varias razones sociales, más de 250 personas o necesidades especiales.

**Precio:**

```text
Cotización por volumen, alcance e integraciones
```

Puede incluir:

- Varias razones sociales.
- Jerarquías corporativas.
- Inicio de sesión empresarial.
- API avanzada.
- Integraciones.
- SLA.
- Soporte de implementación.
- Almacenamiento y retención personalizados.
- Entorno o arquitectura dedicada cuando se justifique.

### 10.4 Implementación sugerida

| Tipo de cliente | Implementación inicial de referencia |
|---|---:|
| Pequeño | $5,000 a $12,000 MXN |
| Mediano | $15,000 a $35,000 MXN |
| Corporativo | Desde $50,000 MXN |

La implementación podrá bonificarse parcial o totalmente mediante contratos anuales durante la etapa de lanzamiento.

### 10.5 Descuentos

- Pago anual: hasta 15 %.
- Aliado o despacho: descuento por volumen.
- Piloto: precio preferencial con compromiso de retroalimentación.
- Corporativo: escalas por volumen.

No se recomienda competir únicamente mediante el precio más bajo.

---

## 11. Modelo de adquisición de clientes

### 11.1 Venta directa

Proceso recomendado:

1. Contenido o referencia.
2. Diagnóstico de 30 minutos.
3. Demostración.
4. Levantamiento de trabajadores, centros y turnos.
5. Propuesta económica.
6. Piloto o implementación.
7. Conversión a suscripción.
8. Seguimiento de adopción.

### 11.2 Canales prioritarios

- Venta directa del fundador.
- Despachos laborales.
- Despachos contables.
- Consultores de recursos humanos.
- Proveedores de nómina.
- Integradores de asistencia.
- Cámaras y asociaciones empresariales.
- Contenido sobre la reforma y cumplimiento.
- Seminarios y demostraciones.
- Referidos de empresas piloto.

### 11.3 Canal de aliados

Un despacho podrá:

- Recomendar Vera Time.
- Revender el servicio.
- Administrar clientes con permiso.
- Ofrecer implementación.
- Recibir comisión recurrente o descuento mayorista.

El modelo de aliado deberá evitar que el despacho pueda ver datos de clientes sin autorización expresa.

### 11.4 Contenido comercial

Los contenidos deberán responder preguntas concretas:

- Qué exige el registro electrónico.
- Cómo prepararse antes de enero de 2027.
- Diferencia entre asistencia y jornada.
- Cómo conservar evidencia.
- Cómo manejar correcciones.
- Qué información solicitará una inspección.
- Cómo calcular horas extraordinarias.
- Riesgos de utilizar hojas de cálculo.

---

## 12. Proceso de implementación del cliente

### Etapa 1 — Diagnóstico

- Número de trabajadores.
- Razones sociales.
- Centros.
- Tipos de jornada.
- Turnos.
- Método actual de registro.
- Sistema de nómina.
- Políticas.
- Datos históricos.
- Necesidades de integración.

### Etapa 2 — Configuración

- Empresa.
- Centros.
- Usuarios y roles.
- Trabajadores.
- Condiciones laborales.
- Horarios y turnos.
- Políticas.
- Reglas específicas más favorables.

### Etapa 3 — Migración

- Plantillas de importación.
- Validación.
- Resultados.
- Corrección de errores.
- Evidencia de carga.

### Etapa 4 — Capacitación

- Administradores.
- Supervisores.
- Personas trabajadoras.
- Soporte.

### Etapa 5 — Piloto

- Grupo controlado.
- Registro real.
- Revisión diaria.
- Corrección de configuración.
- Aprobación.

### Etapa 6 — Operación

- Activación general.
- Seguimiento.
- Indicadores.
- Soporte.
- Revisión periódica.

---

## 13. Alcance comercial del MVP

El MVP que podrá venderse antes de enero de 2027 incluirá:

| Capacidad | Valor comercial |
|---|---|
| Multiempresa | Una plataforma SaaS segura para múltiples clientes |
| Personas trabajadoras | Base individual e histórica |
| Horarios y turnos | Planeación y referencia para el cálculo |
| Registro electrónico | Evidencia de inicio, fin y pausas |
| Motor legal | Cálculos y alertas conforme a vigencias |
| Alertas preventivas | Detecta posibles desviaciones legales y operativas |
| Incidencias | Resolución documentada |
| Correcciones | Historial no destructivo |
| Portal trabajador | Transparencia y participación |
| Conformidad digital | Permite revisar, aceptar o cuestionar el reporte del periodo |
| Reportes | Operación, prenómina y revisión |
| Expediente | Entrega delimitada de evidencia |
| API/CSV | Adopción sin depender de captura manual |
| Auditoría | Registro de cambios y accesos relevantes |
| Seguridad | Aislamiento, permisos y respaldo |

---

## 14. Principios de producto y negocio

1. **Cumplimiento sin vigilancia invasiva.**
2. **Registrar la realidad, no ocultar desviaciones.**
3. **Explicar cada resultado.**
4. **Corregir sin destruir el historial.**
5. **Proteger a empresa y persona trabajadora.**
6. **No afirmar más de lo que la norma establece.**
7. **Actualizar reglas por vigencia.**
8. **Mantener el núcleo independiente del dispositivo.**
9. **Vender el valor del respaldo documental, no el miedo.**
10. **Evitar convertir el producto en una suite de RH antes de validar el núcleo.**
11. **Cada módulo nuevo debe apoyar cumplimiento, adopción o ingreso.**
12. **La información de cada empresa permanecerá aislada.**

---

## 15. Métricas para validar el negocio

### 15.1 Adquisición

- Empresas contactadas.
- Diagnósticos realizados.
- Demostraciones.
- Propuestas enviadas.
- Conversión de propuesta a piloto.
- Conversión de piloto a pago.
- Costo de adquisición.

### 15.2 Uso

- Personas activas.
- Jornadas registradas.
- Porcentaje de jornadas completas.
- Correcciones solicitadas.
- Tiempo de resolución.
- Usuarios administradores activos.
- Centros activos.
- Exportaciones generadas.

### 15.3 Calidad

- Errores de cálculo.
- Incidentes de seguridad.
- Eventos duplicados.
- Disponibilidad.
- Tiempo de respuesta.
- Incidencias de soporte.
- Reaperturas de jornada.

### 15.4 Negocio

- Ingreso mensual recurrente.
- Ingreso promedio por empresa.
- Ingreso promedio por persona.
- Margen bruto.
- Cancelaciones.
- Expansión de cuentas.
- Meses de recuperación de adquisición.
- Flujo de efectivo.
- Consumo de infraestructura por empresa.

### 15.5 Indicadores iniciales de éxito

Durante el piloto se buscará:

- Tres empresas piloto.
- Al menos 200 personas trabajadoras registradas.
- Más del 90 % de jornadas cerradas correctamente.
- Menos del 5 % de eventos con intervención manual no planeada.
- Primer cliente de pago antes del lanzamiento general.
- Evidencia de ahorro operativo o reducción de errores.
- Validación de disposición de pago en el rango propuesto.

---

## 16. Economía unitaria inicial

### 16.1 Ejemplos de ingreso mensual

| Empresa | Personas | Plan | Ingreso mensual estimado |
|---|---:|---|---:|
| Pequeña | 30 | Cumplimiento | $1,490 mínimo |
| Pequeña/mediana | 60 | Cumplimiento | $2,940 |
| Mediana | 100 | Operación | $6,900 |
| Mediana | 200 | Operación | $13,800 |
| Corporativo | 500 | Cotización | Según volumen e integraciones |

### 16.2 Punto de referencia

Con una inversión de $1,100,000 MXN, el producto no debe prometer recuperación inmediata.

Ejemplo orientativo:

```text
50 empresas con ingreso promedio de $6,000/mes
= $300,000 de ingreso mensual recurrente
```

Este cálculo no equivale a utilidad. Deben descontarse:

- Infraestructura.
- Soporte.
- Desarrollo continuo.
- Ventas.
- Administración.
- Impuestos.
- Comisiones.
- Servicios profesionales.

La prioridad inicial será demostrar:

1. Que el producto resuelve el problema.
2. Que las empresas lo utilizan.
3. Que están dispuestas a pagar.
4. Que el costo de operar cada cuenta es menor que su ingreso.

---

## 17. Riesgos del modelo de negocio

| Riesgo | Respuesta |
|---|---|
| Entrada tardía al mercado | MVP operativo antes de enero de 2027 |
| Competidores consolidados | Especialización y profundidad regulatoria |
| Competencia por precio | Vender trazabilidad, evidencia e implementación |
| Cambio de disposiciones oficiales | Motor configurable y actualización normativa |
| Crecimiento descontrolado del alcance | Mantener lista estricta de funciones fuera del MVP |
| Venta consultiva lenta | Canales de despachos y aliados |
| Clientes pequeños con bajo ingreso | Cuota mínima mensual |
| Soporte costoso | Onboarding estandarizado y autoservicio |
| Dependencia del fundador | Procesos, equipo técnico y documentación mínima |
| Integraciones complejas | API base primero; conectores directos según demanda, documentación y viabilidad técnica |
| Manejo de datos sensibles | Seguridad, aislamiento y mínimo privilegio |
| Confusión con asesoría jurídica | Términos claros y red de especialistas |

---

## 18. Decisiones comerciales aprobadas

1. El cliente principal será una empresa privada mexicana.
2. El comprador no será necesariamente la persona que utiliza la plataforma.
3. El modelo será SaaS multi-tenant.
4. La unidad principal de cobro será la persona trabajadora activa.
5. Existirá una cuota mínima mensual.
6. La implementación inicial podrá cobrarse por separado.
7. El producto no incluirá nómina completa durante el MVP.
8. El núcleo legal no dependerá de biometría o GPS.
9. Los planes se validarán primero con clientes piloto.
10. El segmento inicial será de 20 a 250 personas.
11. Se utilizarán aliados como canal de distribución.
12. La venta se apoyará en cumplimiento y evidencia, no en mensajes alarmistas.
13. El precio no será el único diferenciador.
14. Las actualizaciones del núcleo regulatorio formarán parte de la suscripción.
15. Las integraciones especiales serán ingresos adicionales.

---

## 19. Decisiones que deberán validarse

- Precio definitivo por persona.
- Cuota mínima final.
- Duración de la prueba o piloto.
- Descuento anual.
- Comisión para aliados.
- Costo de implementación.
- Retención incluida por plan.
- Política de soporte.
- Límites de almacenamiento.
- Definición exacta de persona activa.
- Condiciones de cancelación.
- Acuerdo de nivel de servicio.
- Sectores iniciales con mejor conversión.
- Sistema de nómina prioritario para integración.

Estas decisiones deberán validarse con entrevistas, propuestas reales y empresas piloto, no únicamente mediante análisis interno.

---

## 20. Siguiente paso documental

Con este modelo de negocio aprobado, la siguiente etapa será:

```text
docs/03-Requisitos/
```

Ahí el alcance comercial del MVP se convertirá en:

- Actores del sistema.
- Módulos.
- Requisitos funcionales.
- Requisitos no funcionales.
- Casos de uso.
- Criterios de aceptación.
- Priorización P0, P1 y fuera del MVP.

---

## 21. Fuentes utilizadas

- INEGI, Censos Económicos 2024, resultados definitivos:  
  https://www.inegi.org.mx/programas/ce/2024/
- INEGI, resultados nacionales de los Censos Económicos 2024:  
  https://www.inegi.org.mx/contenidos/saladeprensa/boletines/2025/ce/CE2024_def.pdf
- Factorial México, precios y funcionalidades:  
  https://factorial.mx/plan-de-precios
- Sesame HR México, precios:  
  https://www.sesamehr.mx/precios/
- Worky México, precios de turnos y asistencia:  
  https://www.worky.mx/precios-y-planes-software-rrhh
- Ley Federal del Trabajo vigente:  
  https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf


