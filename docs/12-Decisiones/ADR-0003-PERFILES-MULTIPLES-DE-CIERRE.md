# ADR-0003 - Perfiles multiples de cierre

## Estado

Aceptado.

## Problema

El cierre unico por empresa no cubre operaciones reales donde distintos centros, areas, equipos o relaciones laborales requieren calendarios de cierre diferentes.

Una empresa puede tener nomina semanal para operaciones, quincenal para administrativos, catorcenal para ciertas plantas o periodos especiales por contrato.

## Decision

Vera Time soportara multiples perfiles de cierre.

Toda empresa tendra un perfil predeterminado obligatorio. Centro, unidad organizacional o relacion laboral solo definiran excepciones.

La prioridad para resolver el perfil efectivo sera:

1. Relacion laboral.
2. Unidad organizacional.
3. Centro.
4. Empresa.

Esta prioridad queda aceptada como regla definitiva para cierres, reportes y conformidad.

## Catalogo de Perfiles

Entidad propuesta: `closing_period_profiles`.

Campos principales:

- `company_id`
- `code`
- `name`
- `frequency`: `weekly`, `fourteen_day`, `semimonthly`, `monthly`, `custom`
- `timezone`
- `anchor_date`
- `cutoff_day`
- `payment_lag_days`
- `status`
- `metadata`

Quincenal y catorcenal son frecuencias diferentes.

## Perfil Predeterminado

La empresa debe tener un perfil de cierre predeterminado activo. Si no existe excepcion aplicable, ese perfil se usa para generar periodos.

## Herencia y Excepciones

Entidad propuesta: `closing_profile_assignments`.

No se recomienda una relacion polimorfica opaca para el MVP. Se recomienda usar columnas explicitas nullable:

- `center_id`
- `organizational_unit_id`
- `employment_relationship_id`

Reglas:

- Solo una columna de alcance debe estar presente por asignacion.
- La pertenencia al tenant se valida contra `company_id`.
- Las vigencias no deben solaparse para el mismo alcance.

## Vigencias

Cada excepcion tiene:

- `effective_from`
- `effective_to nullable`
- `status`: `active`, `replaced`, `inactive`

Los cambios no modifican periodos ya publicados.

## Generacion de Periodos

Entidad propuesta: `closing_periods`.

Los periodos se generan desde el perfil efectivo, con:

- rango de fechas;
- perfil aplicado;
- version;
- estado;
- snapshot de configuracion;
- hash;
- autor y fecha de publicacion cuando aplique.

## Congelacion de Miembros

Entidad propuesta: `closing_period_members`.

Al publicar o iniciar un cierre operativo se congelan:

- trabajador;
- relacion laboral;
- centro;
- unidad organizacional;
- perfil efectivo;
- origen del perfil: empresa, centro, unidad o relacion laboral;
- version de configuracion.

Esto impide que cambios posteriores alteren reportes y conformidad historica.

## Reportes y Conformidad por Version

Los reportes y conformidad digital deben referenciar version de cierre y miembros congelados. Si se corrige informacion posterior, se genera nueva version.

## Consecuencias

- Mayor precision para operaciones con calendarios distintos.
- Mejor trazabilidad para reportes y conformidad.
- Requiere resolutor central `ResolveClosingProfileForRelationshipAction`.
- Requiere generador `GenerateClosingPeriodsAction`.

## Riesgos

- Confusion UX si no se muestra claramente "Perfil efectivo" y "Origen".
- Solapamientos de excepciones si no se validan transaccionalmente.
- Cambios de unidad o relacion laboral deben considerar fecha efectiva.

## Criterios de Aceptacion

- Toda empresa tiene perfil predeterminado.
- Se pueden crear excepciones por centro, unidad o relacion laboral.
- La prioridad se respeta: relacion laboral, unidad, centro, empresa.
- Los periodos publicados congelan miembros, configuracion y versiones.
- El usuario ve: `Perfil efectivo: [nombre]` y `Origen: Empresa / Centro / Area / Relacion laboral`.
