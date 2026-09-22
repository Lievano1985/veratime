---
id: BL-1108
title: Plantillas configurables de CSV de periodos
project: Vera Time
status: In review
priority: P1 alto
owner: Product
---

# BL-1108 — Plantillas configurables de CSV de periodos

## Estado de implementación

El incremento actual implementa la plantilla base **CSV de períodos** por empresa, la pantalla de Configuración con pizarra drag-and-drop, duplicación, edición de plantillas propias, encabezados personalizados, orden de columnas, delimitador y selección de plantilla predeterminada. La descarga existente de un período cerrado reutiliza esa plantilla predeterminada o acepta una plantilla propia por `template_id`.

Quedan para un incremento posterior la vista previa con filas reales del período dentro de la pantalla, el historial persistente de descargas con snapshot/hash por archivo y los endpoints API para administrar plantillas. Estos pendientes no impiden configurar ni descargar el CSV personalizado por web.

## Objetivo

Permitir que cada empresa entregue su información de asistencia a distintos proveedores de nómina mediante un archivo CSV con los encabezados, orden y formatos que el proveedor solicite.

Vera Time no calcula nómina ni se integra directamente con el proveedor. Produce un archivo de asistencia trazable a partir de un período cerrado.

## Ubicación y experiencia

La funcionalidad estará en:

```text
Configuración > CSV de períodos
```

La pantalla tendrá dos zonas principales:

```text
Columnas disponibles                 Pizarra del archivo de salida
─────────────────────                ─────────────────────────────
[Código de empleado]       ───────▶  1. Código empleado      [×]
[Nombre completo]          ───────▶  2. Nombre trabajador    [×]
[Horas ordinarias]         ───────▶  3. HORAS NORMALES       [×]
[Horas extra]              ───────▶  4. HE                  [×]
[Incidencias]              ───────▶  5. INCIDENCIAS         [×]
```

- La persona administradora arrastra una columna disponible hacia la pizarra.
- Puede reordenar las tarjetas ya agregadas.
- Cada tarjeta permite cambiar únicamente el encabezado que verá el proveedor de nómina.
- Un botón de vista previa muestra filas reales y limitadas del período seleccionado antes de descargar.
- La interfaz también ofrecerá controles accesibles de agregar, quitar, subir y bajar; el uso no dependerá exclusivamente de arrastrar con mouse.

## Plantilla predeterminada

Cada empresa recibe la plantilla de sistema **CSV de períodos**, equivalente al archivo CSV de período que Vera Time ya genera actualmente.

- Será la selección predeterminada al exportar.
- Se podrá duplicar para crear una plantilla propia, por ejemplo `Nómina Contpaq`, `Layout despacho` o `Archivo quincenal`.
- La plantilla de sistema no se altera; así siempre existe una salida base conocida.
- Una plantilla propia puede marcarse como predeterminada de esa empresa sin afectar a otras empresas.

## Alcance funcional

Una plantilla propia define:

- nombre, descripción y estado;
- columnas provenientes de un catálogo controlado de campos de asistencia;
- encabezado de salida por columna;
- orden de columnas;
- delimitador permitido y codificación soportada;
- formatos permitidos de fecha y duración;
- condición de ser plantilla predeterminada de la empresa.

El catálogo inicial considerará identificadores del trabajador, período, centro, horas ordinarias, horas extra, domingo, descansos obligatorios e incidencias que ya estén disponibles en la exportación base. Agregar conceptos nuevos requiere una historia separada de dominio; una plantilla no inventa ni calcula datos.

Al descargar, Vera Time conserva un snapshot inmutable de la plantilla utilizada junto con período, empresa, usuario, fecha, hash y archivo generado. Modificar una plantilla sólo aplica a exportaciones futuras; nunca modifica un período cerrado ni un archivo ya generado.

## Seguridad y límites

- Alcance estricto por `company_id`; una empresa nunca consulta ni reutiliza plantillas de otra.
- Pueden administrar plantillas `super_admin`, `admin_empresa` y `rh_admin` autorizados en la empresa.
- Sólo roles con permiso de exportación pueden descargar un período usando una plantilla.
- No se permiten fórmulas, macros, SQL libre, código ni transformaciones arbitrarias.
- Se protege el contenido CSV frente a inyección de fórmulas.
- No incluye salarios, percepciones, deducciones, impuestos, timbrado ni integración directa con nómina.

## Flujo de uso

1. La persona administradora abre **Configuración > CSV de períodos**.
2. Conserva la plantilla **CSV de períodos** o la duplica.
3. Arrastra campos a la pizarra, los ordena y renombra sus encabezados.
4. Guarda y activa la plantilla.
5. Desde un período cerrado selecciona la plantilla, revisa la vista previa y descarga el CSV.
6. El sistema registra la exportación y el snapshot de la plantilla usada.

## Criterios de aceptación

1. La plantilla base actual está disponible automáticamente como **CSV de períodos**.
2. Una empresa puede crear una plantilla propia sin alterar la plantilla base ni plantillas de otra empresa.
3. La pizarra permite agregar, quitar, ordenar y renombrar encabezados de columnas permitidas.
4. No se puede guardar una plantilla con encabezados vacíos/duplicados ni sin un identificador de trabajador.
5. La vista previa y el CSV final respetan exactamente el orden y los encabezados configurados.
6. Sólo se exportan períodos cerrados y el archivo guarda el snapshot/hash de la plantilla utilizada.
7. Cambiar una plantilla no cambia archivos ya generados ni información laboral histórica.
8. Se prueban permisos, aislamiento multi-tenant, validación, inyección CSV, vista previa y exactitud del archivo.

## Dependencias y secuencia

Depende de la exportación CSV base de períodos y reutilizará la misma Action de exportación; no se creará una lógica distinta para web, API, jobs o descarga CSV.

Secuencia recomendada:

1. Modelo, migraciones, Policies y Actions de plantilla.
2. Generación del snapshot y aplicación de plantilla sobre la exportación base.
3. Pantalla de Configuración con pizarra drag-and-drop y alternativa accesible.
4. Selector, vista previa y descarga desde el período cerrado.
5. Pruebas automáticas y validación con archivos reales de proveedores de nómina.
