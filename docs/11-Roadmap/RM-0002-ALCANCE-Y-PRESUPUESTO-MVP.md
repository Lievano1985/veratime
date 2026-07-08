---
id: RM-0002
title: Alcance y presupuesto preliminar del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Founder
created: 2026-07-01
updated: 2026-07-03
tags:
  - mvp
  - presupuesto
  - inversion
  - roadmap
---

# RM-0002 — Alcance y presupuesto preliminar del MVP

## 1. Decisión ejecutiva

Vera Time deberá tener un MVP operativo y listo para producción **a más tardar el 31 de diciembre de 2026**.

El presupuesto preliminar recomendado para presentar al posible socio es:

> **$1,100,000 MXN**

Este monto financia seis meses de trabajo, incluye una compensación mínima para la dedicación del fundador, un desarrollador adicional, apoyo concentrado de UX, QA y DevOps, aspectos legales, infraestructura, piloto y una contingencia del 18 %.

La cifra es preliminar, pero suficientemente concreta para iniciar una conversación con un inversionista. Las cotizaciones posteriores podrán redistribuir las partidas sin dejar nuevamente el monto de inversión indefinido.

## 2. Alcance indispensable del MVP

### Incluido antes de enero de 2027

1. Plataforma multiempresa, usuarios, roles y aislamiento de datos.
2. Administración de empresas, centros y personas trabajadoras.
3. Condiciones laborales, horarios, turnos y vigencias.
4. Registro electrónico de entrada, salida y pausas.
5. Captura mediante web responsiva/PWA y kiosco.
6. Motor de cálculo para jornadas diurnas, nocturnas y mixtas.
7. Cálculo de límites diarios, semanales, horas extraordinarias y descansos.
8. Incidencias, correcciones, aprobaciones y trazabilidad.
9. Portal de la persona trabajadora.
10. Reportes y expediente exportable para autoridad.
11. Importación por CSV.
12. Arquitectura domain-first con servicios reutilizables.
13. API REST `/api/v1` para operaciones críticas.
14. Tokens por empresa.
15. Idempotencia para recepción de eventos.
16. Trazabilidad de fuente: web, kiosco, API, CSV, job o integración.
17. Seguridad, auditoría, respaldos y monitoreo.
18. Implementación piloto, capacitación y soporte de salida.

### Capacidades integradas al alcance presupuestado

Las siguientes capacidades forman parte del MVP, pero no se tratan como módulos nuevos ni como partidas adicionales de presupuesto. Se integran dentro de capacidades ya contempladas:

```text
Motor legal
├── Detección de alertas

Incidencias y correcciones
├── Revisión y resolución de alertas

Reportes
├── Generación del reporte de cierre

Portal del trabajador
├── Conforme
├── No conforme
└── Pendiente de revisión

Auditoría y evidencia
├── Hash
├── Versión
├── Confirmación
└── Historial
```

Estas capacidades son necesarias para que el flujo regulatorio quede completo: detectar una alerta, revisarla, resolverla, dejar evidencia de la resolución, permitir la postura de la persona trabajadora y generar un cierre verificable.

La API base forma parte del MVP como capacidad técnica presupuestada. Esto no significa construir desde el inicio todas las integraciones avanzadas con sistemas externos específicos.

### Incluido solo si no pone en riesgo la fecha

- Configuración básica de teletrabajo y desconexión.
- Exportación simple de horas y conceptos hacia nómina.
- Notificaciones operativas.
- Integraciones directas por API con sistemas externos específicos, condicionadas a documentación, credenciales y ambiente de pruebas.

La integración directa con ClickBalance por API queda como P1 condicionado. Para el MVP se priorizará archivo compatible o intercambio mínimo cuando la información técnica disponible no permita una integración directa segura.

### Fuera del MVP

- Aplicaciones móviles nativas.
- Biometría propia, reconocimiento facial o hardware propio.
- Nómina integral.
- Inteligencia artificial.
- Analítica avanzada.
- Integraciones múltiples con relojes checadores.
- Integraciones directas avanzadas no documentadas o sin ambiente de pruebas.
- Módulo completo de seguridad y salud de teletrabajo.
- Operación en otros países.

## 3. Cronograma acelerado

| Fase | Periodo | Entregable |
|---|---|---|
| Alcance, presupuesto e inversión | 1–15 julio | Monto, alcance y acuerdo aprobados |
| Producto, UX y arquitectura | 1 julio–15 agosto | Flujos, prototipo y diseño técnico |
| Núcleo de plataforma | 16 julio–15 septiembre | Empresas, trabajadores, horarios y eventos |
| Motor legal e incidencias | 15 agosto–15 octubre | Cálculos, correcciones y trazabilidad |
| Portal, reportes e interoperabilidad | 15 septiembre–10 noviembre | Portal, exportaciones, CSV y API |
| QA, seguridad y piloto | 15 octubre–30 noviembre | Versión candidata estable |
| Piloto y producción | 1–31 diciembre | MVP operativo antes de 2027 |

Las fases se traslapan deliberadamente. Un flujo estrictamente secuencial no permitiría llegar a la fecha.

## 4. Escenarios de inversión

| Escenario | Costo calculado | Monto sugerido | Riesgo |
|---|---:|---:|---|
| Austero | $552,000 | $600,000 | Alto: depende demasiado del fundador |
| Recomendado | $1,053,150 | **$1,100,000** | Controlable con seguimiento semanal |
| Acelerado | $1,884,600 | $1,900,000 | Menor riesgo de fecha, mayor capital |

## 5. Escenario recomendado

### Personal

| Perfil | Dedicación | Costo estimado |
|---|---:|---:|
| Dedicación del fundador | 6 meses | $150,000 |
| Desarrollador principal | 5.5 meses | $357,500 |
| UX/UI | 1.25 meses | $50,000 |
| QA funcional | 2.5 meses | $100,000 |
| DevOps y seguridad | 1 mes | $55,000 |
| **Total personal** |  | **$712,500** |

### Otros costos

| Partida | Costo estimado |
|---|---:|
| Legal corporativo, acuerdo de socios e propiedad intelectual | $40,000 |
| Validación laboral y privacidad | $30,000 |
| Infraestructura y respaldos | $30,000 |
| Herramientas y licencias | $20,000 |
| Implementación y soporte del piloto | $30,000 |
| Material comercial y sitio | $20,000 |
| Operación, traslados y capacitación | $10,000 |
| **Total otros costos** | **$180,000** |

### Cálculo

```text
Personal                         $712,500
Otros costos                     $180,000
Subtotal                         $892,500
Contingencia 18 %                $160,650
Costo estimado                 $1,053,150
Monto redondeado solicitado    $1,100,000
```

## 6. Desembolso recomendado

El capital no debe entregarse necesariamente en una sola exhibición.

| Hito | Porcentaje | Monto |
|---|---:|---:|
| Firma, alcance y arranque | 25 % | $275,000 |
| Núcleo operativo comprobable | 30 % | $330,000 |
| Beta regulatoria | 30 % | $330,000 |
| Piloto aprobado y producción | 15 % | $165,000 |
| **Total** | **100 %** | **$1,100,000** |

Cada liberación deberá vincularse con entregables verificables y un reporte simple de uso de recursos.

## 7. Supuestos

1. El fundador seguirá participando directamente en producto, arquitectura y desarrollo.
2. Se incorpora al menos un desarrollador adicional.
3. UX, QA y DevOps se contratan en periodos concentrados.
4. Los costos son brutos y preliminares; deben revisarse según el esquema de contratación.
5. Los costos legales requieren cotizaciones locales.
6. No se contempla compra de hardware.
7. El piloto iniciará con pocas empresas y un número controlado de trabajadores.
8. La participación del socio no se define únicamente dividiendo inversión entre costo del proyecto.
9. La contingencia no debe comprometerse desde el inicio.
10. La publicación de nuevas reglas de la STPS puede obligar a reordenar trabajo, no a ampliar ilimitadamente el MVP.

## 8. Riesgos principales

| Riesgo | Respuesta |
|---|---|
| Nuevos lineamientos regulatorios | Motor configurable y reserva de contingencia |
| Retraso al contratar | Iniciar búsqueda durante negociación del socio |
| Crecimiento descontrolado del alcance | Congelar P0 y enviar mejoras a fases posteriores |
| Dependencia del fundador | Desarrollador adicional y documentación mínima útil |
| Errores en cálculos | Casos legales automatizados y QA concentrado |
| Fallas durante el piloto | Staging, respaldos, monitoreo y soporte diario |
| Falta de adopción | Piloto temprano y capacitación sencilla |

## 9. Decisiones pendientes antes de presentar la propuesta

- Confirmar cuánto tiempo semanal dedicará el fundador.
- Definir si su compensación será pagada, diferida o combinada.
- Obtener al menos una referencia de contratación para desarrollo.
- Obtener cotización legal para acuerdo de socios, propiedad intelectual y contratos.
- Seleccionar empresas candidatas al piloto.
- Definir la estructura que se ofrecerá al socio: capital, préstamo convertible o esquema mixto.

Estas decisiones pueden ajustar partidas, pero la propuesta al inversionista deberá presentar desde el inicio un monto objetivo de **$1,100,000 MXN**.

## 10. Fuentes para los supuestos

- Reforma de la Ley Federal del Trabajo, publicada el 1 de mayo de 2026:  
  https://www.diputados.gob.mx/LeyesBiblio/ref/lft/LFT_ref52_01may26.pdf
- Publicación oficial en el Diario Oficial de la Federación:  
  https://www.dof.gob.mx/nota_to_pdf.php?edicion=VES&fecha=01%2F05%2F2026
- Rangos de desarrollo de software en México 2026:  
  https://www.axented.com/blog-posts/mexico-software-developer-salaries-2026
- Rangos UX/UI en México:  
  https://uiuxjobsboard.com/salary/ui-ux-designer/mexico
- Referencias de QA en México:  
  https://talently.tech/en/tools/mexico/salary/developer/qa-engineer
- Precios de infraestructura utilizados como referencia:  
  https://www.digitalocean.com/pricing


