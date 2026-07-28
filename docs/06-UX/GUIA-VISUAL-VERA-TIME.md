---
title: Guia visual Vera Time
project: Vera Time
status: Draft
updated: 2026-07-27
---

# Guia visual Vera Time

## Objetivo

Centralizar el uso de color en Vera Time para evitar botones negros, estilos aislados y colores arbitrarios en Blade.

El sistema usa azul Vera Time como color principal y turquesa como acento.

## Paleta

### Azul Vera Time

| Token | Valor |
|---|---|
| `vera-time-50` | `#eff7ff` |
| `vera-time-100` | `#dceeff` |
| `vera-time-200` | `#b9ddff` |
| `vera-time-300` | `#86c4ff` |
| `vera-time-400` | `#4aa5ff` |
| `vera-time-500` | `#1f84f2` |
| `vera-time-600` | `#1269d8` |
| `vera-time-700` | `#1055af` |
| `vera-time-800` | `#12498f` |
| `vera-time-900` | `#153e76` |

### Acento turquesa

| Token | Valor |
|---|---|
| `vera-accent-400` | `#2dd4c8` |
| `vera-accent-500` | `#18bdb5` |
| `vera-accent-600` | `#0f9c98` |

## Tokens semanticos

| Token | Uso |
|---|---|
| `primary` | Acciones principales |
| `primary-hover` | Hover de acciones principales |
| `primary-soft` | Fondos suaves, estados activos y superficies destacadas |
| `primary-border` | Bordes suaves de marca |
| `accent` | Acentos puntuales, no acciones principales |

## Significado de color

- Azul Vera Time: acciones principales, foco accesible, navegacion activa.
- Turquesa: acentos pequenos y controles secundarios especiales.
- Rojo: acciones destructivas y errores.
- Verde: exito.
- Ambar: advertencias.
- Gris: informacion neutral, tablas, bordes y texto secundario.

No usar azul para eliminar, cancelar o mostrar errores.

## Componentes compartidos

### Botones

Usar:

```blade
<x-ui.button variant="primary">Guardar</x-ui.button>
<x-ui.button variant="secondary">Ver detalle</x-ui.button>
<x-ui.button variant="danger">Eliminar</x-ui.button>
<x-ui.button variant="ghost">Cancelar</x-ui.button>
```

Variantes:

- `primary`: accion principal.
- `secondary`: accion secundaria visible.
- `danger`: accion destructiva.
- `ghost`: accion de bajo peso visual.

Los botones `flux:button variant="primary"` tambien quedan alineados al color principal mediante tokens globales.

### Badges

Usar:

```blade
<x-ui.badge variant="info">Info</x-ui.badge>
<x-ui.badge variant="success">Activo</x-ui.badge>
<x-ui.badge variant="warning">Pendiente</x-ui.badge>
<x-ui.badge variant="danger">Bloqueado</x-ui.badge>
<x-ui.badge variant="neutral">Inactivo</x-ui.badge>
```

### Campos

Usar componentes compartidos cuando no se use Flux:

```blade
<x-ui.input wire:model="form.name" />
<x-ui.select wire:model="form.status">...</x-ui.select>
<x-ui.textarea wire:model="form.notes" />
<x-ui.checkbox wire:model="form.enabled" />
```

Los campos deben mantener fondo blanco. El color de marca se usa solo en borde y anillo de foco.

## Navegacion

- Sidebar oscuro: fondo `vera-sidebar`.
- Opcion activa: `primary-soft` con texto `primary`.
- Hover: contraste claro sobre fondo oscuro.
- El logo no debe recibir fondo activo aunque enlace a Inicio.

## Evitar

- No usar `bg-black` como accion primaria.
- No usar `text-black` para forzar estados dentro de componentes compartidos.
- No agregar colores HEX directamente en Blade salvo excepcion revisada.
- No usar azul Vera Time para peligro, error, eliminar o cancelar.
- No duplicar estilos de botones en cada pantalla.

## Instruccion para agentes

Todo componente nuevo debe usar tokens semanticos y componentes compartidos. No utilizar `bg-black` como accion primaria ni agregar colores arbitrarios en Blade.
