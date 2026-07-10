---
id: UX-0001
title: Mapa de pantallas del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-03
updated: 2026-07-03
tags:
  - ux
  - pantallas
  - mvp
  - livewire
  - veratime
---

# UX-0001 — Mapa de pantallas del MVP

## 1. Objetivo

Definir el mapa de pantallas, flujos principales y estructura de navegación del MVP de Vera Time.

Este documento convierte los requisitos, arquitectura y modelo de datos en una guía clara para diseñar las interfaces Livewire, el portal de la persona trabajadora, el modo kiosco y los módulos administrativos.

No define todavía diseño visual final, colores, componentes HTML ni código.

---

## 2. Principios UX del MVP

1. **Operación rápida.**
   El usuario debe poder registrar, revisar, corregir y cerrar sin pasos innecesarios.

2. **Claridad legal sin lenguaje intimidante.**
   Las alertas deben hablar de posibles desviaciones, no de infracciones confirmadas.

3. **Trazabilidad visible.**
   Cuando algo cambie, la pantalla debe mostrar qué cambió, quién lo hizo y por qué.

4. **Móvil primero para trabajadores.**
   El portal trabajador y kiosco deben funcionar bien desde celular.

5. **No saturar al usuario.**
   Los datos técnicos, hashes y auditoría profunda deben estar disponibles, pero no invadir la operación diaria.

6. **Mismo dominio para web, API y CSV.**
   Lo que se vea en interfaz debe ser consistente con lo que se consulte por API o se importe por archivo.

7. **Separación por rol.**
   Cada rol ve solo lo necesario.

8. **Acción guiada.**
   Las pantallas deben mostrar siguiente paso claro: revisar, justificar, corregir, cerrar, confirmar o exportar.

9. **Estados visibles.**
   Jornadas, alertas, incidencias, periodos y reportes deben mostrar estado de forma clara.

10. **Evitar edición destructiva.**
    La interfaz debe guiar a correcciones versionadas, no a sobrescribir datos históricos.

---

## 3. Roles considerados

| Rol | Necesidad principal |
|---|---|
| Superadministrador | Administrar empresas, planes, soporte y reglas globales |
| Administrador de empresa | Configurar empresa, centros, usuarios y políticas |
| Recursos humanos | Operar trabajadores, horarios, incidencias, cierres y reportes |
| Supervisor | Revisar equipo, alertas e incidencias |
| Nómina / Prenómina | Consultar y exportar horas y conceptos |
| Jurídico / Cumplimiento | Consultar evidencia, reportes, auditoría y expedientes |
| Persona trabajadora | Registrar jornada, consultar reporte y manifestar conformidad/no conformidad |
| Soporte Vera Time | Revisar incidencias técnicas con acceso controlado |

---

## 4. Estructura general de navegación

## 4.1 Navegación administrativa

```text
Dashboard
Empresas / Centros
Personas trabajadoras
Horarios y turnos
Registro de jornada
Jornadas calculadas
Alertas
Incidencias y correcciones
Cierres de periodo
Reportes
Expedientes
Importaciones / Exportaciones
Integraciones / API
Configuración
Auditoría
```

## 4.2 Portal trabajador

```text
Mi jornada de hoy
Mi semana
Mis reportes
Mis aclaraciones
Mi perfil / acceso
```

## 4.3 Kiosco

```text
Código / NIP
Registrar entrada
Registrar salida
Iniciar pausa
Terminar pausa
Confirmación
```

## 4.4 Administración global SaaS

```text
Empresas
Planes
Reglas legales
Usuarios soporte
Accesos de soporte
Estado del sistema
```

---

# 5. Pantallas transversales

## 5.1 Login

**Prioridad:** P0

Usuarios:

- Administradores.
- RH.
- Supervisores.
- Nómina.
- Jurídico.
- Soporte.
- Personas trabajadoras si tienen cuenta completa.

Incluye:

- Email o usuario.
- Contraseña.
- Recuperar contraseña.
- Mensaje de error.
- Estado de cuenta bloqueada o inactiva.

No incluye MVP:

- SSO.
- Login social.
- MFA obligatorio global.

---

## 5.2 Selector de empresa

**Prioridad:** P0

Se muestra cuando un usuario tiene acceso a más de una empresa.

Incluye:

- Lista de empresas autorizadas.
- Estado de empresa.
- Última empresa usada.
- Cambio de empresa activa.

Criterio:

Un usuario no debe ver empresas no autorizadas.

---

## 5.3 Layout administrativo

**Prioridad:** P0

Incluye:

- Sidebar.
- Empresa activa.
- Usuario actual.
- Notificaciones.
- Buscador básico.
- Breadcrumb.
- Área principal.
- Estados de carga.
- Mensajes de éxito/error.

---

## 5.4 Dashboard administrativo

**Prioridad:** P0

Objetivo:

Mostrar estado operativo del periodo actual.

Indicadores mínimos:

- Trabajadores activos.
- Jornadas registradas hoy.
- Jornadas incompletas.
- Alertas nuevas.
- Alertas críticas.
- Incidencias abiertas.
- Reportes pendientes de conformidad.
- Periodo actual.
- Próximo cierre.

Acciones rápidas:

- Ver alertas.
- Ver incidencias.
- Importar eventos.
- Iniciar cierre.
- Generar reporte.

---

# 6. Administración global SaaS

## 6.1 Empresas

**Prioridad:** P0 para superadministrador.

Pantalla:

```text
Admin Global → Empresas
```

Incluye:

- Listado de empresas.
- Alta de empresa.
- Editar empresa.
- Estado: trial, activa, suspendida, cancelada.
- Plan asignado.
- Fecha de activación.
- Acceso a configuración.

Acciones:

- Crear.
- Editar.
- Suspender.
- Reactivar.
- Cancelar.
- Entrar con acceso soporte controlado.

---

## 6.2 Planes

**Prioridad:** P1

Incluye:

- Plan.
- Precio por trabajador activo.
- Cuota mínima.
- Límites.
- Funciones incluidas.
- Estado.

Para MVP puede estar precargado y solo consultarse.

---

## 6.3 Reglas legales globales

**Prioridad:** P0 técnico / P1 visual según avance.

Incluye:

- Catálogo de reglas.
- Versiones.
- Vigencia.
- Fuente.
- Estado.
- Publicación controlada.

Estados:

```text
borrador
revisada
programada
vigente
sustituida
inactiva
```

Nota:

En el MVP puede empezar como pantalla básica de solo lectura y administración controlada.

---

# 7. Empresas y centros

## 7.1 Configuración de empresa

**Prioridad:** P0

Ruta sugerida:

```text
Configuración → Empresa
```

Incluye:

- Razón social.
- Nombre comercial.
- RFC.
- Zona horaria principal.
- Estado.
- Periodo de cierre por defecto.
- Día de cierre.
- Configuración de conformidad digital.
- Configuración de kiosco.
- Configuración de correcciones.

---

## 7.2 Centros de trabajo

**Prioridad:** P0

Incluye:

- Listado de centros.
- Código.
- Nombre.
- Zona horaria.
- Estado.
- Dirección opcional.
- Trabajadores activos por centro.

Acciones:

- Crear centro.
- Editar centro.
- Inactivar centro.
- Ver trabajadores.
- Ver reportes por centro.

Nota de implementacion Sprint 1B:

```text
La primera pantalla disponible de centros esta en /centers y cubre:
- listado de centros de la empresa activa
- codigo, nombre, zona horaria y estado
- crear centro
- editar centro
- inactivar centro
- direccion opcional como JSON

Trabajadores activos por centro, ver trabajadores y ver reportes por centro quedan pendientes para los sprints correspondientes.
```

---

# 8. Usuarios, roles y permisos

## 8.1 Usuarios de empresa

**Prioridad:** P0

Incluye:

- Nombre.
- Email.
- Rol.
- Empresa.
- Centros permitidos.
- Estado.
- Último acceso.

Acciones:

- Invitar usuario.
- Editar rol.
- Limitar por centro.
- Desactivar.
- Reenviar invitación.

---

## 8.2 Roles y permisos

**Prioridad:** P1 para pantalla avanzada.

En MVP puede manejarse con roles predefinidos:

```text
admin_empresa
rh
supervisor
nomina
juridico
trabajador
solo_lectura
```

La pantalla avanzada de permisos puede dejarse para fase posterior.

---

# 9. Personas trabajadoras

## 9.1 Listado de trabajadores

**Prioridad:** P0

Incluye:

- Código.
- Nombre.
- Centro.
- Puesto.
- Estado.
- Horario actual.
- Modalidad.
- Último registro.
- Alertas abiertas.
- Pendiente de conformidad.

Filtros:

- Centro.
- Estado.
- Horario.
- Modalidad.
- Búsqueda por nombre/código/RFC.

Acciones:

- Crear trabajador.
- Importar CSV.
- Ver detalle.
- Editar.
- Dar de baja.
- Resetear NIP.
- Ver jornada.

---

## 9.2 Alta/edición de trabajador

**Prioridad:** P0

Incluye:

- Código interno.
- Nombre completo.
- Email.
- Teléfono.
- RFC/CURP opcional.
- Centro.
- Puesto.
- Fecha de ingreso.
- Estado.
- Modalidad.
- Horario.
- Día de descanso.
- NIP/código de acceso.
- Vigencia de condiciones.

Regla:

Cambios de centro, horario o condiciones deben tener fecha efectiva.

---

## 9.3 Detalle de trabajador

**Prioridad:** P0

Tabs sugeridos:

```text
Resumen
Relación laboral
Condiciones
Jornadas
Alertas
Incidencias
Reportes
Conformidades
Auditoría
```

Indicadores:

- Estado actual.
- Horario actual.
- Centro actual.
- Última jornada.
- Alertas abiertas.
- Reportes pendientes.
- Incidencias abiertas.

---

## 9.4 Baja de trabajador

**Prioridad:** P0

Incluye:

- Fecha de baja.
- Motivo.
- Confirmación.
- Conservación de registros.
- Bloqueo de nuevo registro.
- Mantener acceso a evidencia según política.

No debe eliminar información histórica.

Nota de implementacion Sprint 1C:

```text
La primera pantalla disponible de trabajadores esta en /workers y cubre:
- listado de trabajadores de la empresa activa
- codigo, nombre, centro actual, puesto y estado
- crear trabajador
- editar datos basicos
- dar de baja sin eliminar historial
- crear relacion laboral inicial con centro, puesto y fecha de ingreso
- conservar historial de relaciones laborales cuando cambia centro, puesto o fecha de ingreso

Sprint 1C usa started_at como fecha efectiva del cambio de relacion laboral porque BL-0304 todavia no existe.

Quedan pendientes para sprints posteriores:
- horarios
- detalle de trabajador
- importacion CSV
- jornadas, alertas, reportes y portal trabajador
```

Nota de implementacion Sprint 1D:

```text
La pantalla /workers integra administracion minima de:
- condicion laboral vigente
- historial simple de condiciones laborales
- modalidad de trabajo, horas semanales, dia de descanso y vigencia
- credencial kiosco administrativa
- codigo de acceso
- NIP temporal para crear o resetear credencial
- bloqueo de credencial

Reglas UX/seguridad aplicadas:
- no se muestra pin_hash
- el NIP temporal se captura como password y se limpia despues de exito o error
- no hay pantalla de kiosco operativo
- no hay registro de entrada, salida o pausas
- no hay horarios ni asignacion de horarios

Quedan pendientes para sprints posteriores:
- mover estas secciones a detalle de trabajador cuando se implemente BL-0307
- horarios
- importacion CSV
- jornadas, alertas, reportes y portal trabajador
```

---

# 10. Horarios y turnos

## 10.1 Listado de horarios

**Prioridad:** P0

Incluye:

- Código.
- Nombre.
- Tipo legal programado.
- Días aplicables.
- Hora entrada.
- Hora salida.
- Cruza medianoche.
- Estado.
- Trabajadores asignados.

Acciones:

- Crear horario.
- Editar.
- Inactivar.
- Ver asignados.

---

## 10.2 Crear/editar horario

**Prioridad:** P0

Incluye:

- Nombre.
- Código.
- Tipo legal programado.
- Zona horaria opcional.
- Días laborales.
- Entrada.
- Salida.
- Cruza medianoche.
- Pausas.
- Descanso computable/no computable.
- Vigencia.

---

## 10.3 Asignación de horario

**Prioridad:** P0

Incluye:

- Trabajador o grupo.
- Horario.
- Fecha de inicio.
- Fecha de fin opcional.
- Motivo.

Regla:

No debe solapar asignaciones activas incompatibles.

---

Nota de implementacion Sprint 2A:

```text
La primera pantalla disponible de horarios esta en /schedules y cubre:
- listado de horarios de la empresa activa
- codigo, nombre, tipo legal, zona horaria, estado y vigencia simple
- alta, edicion e inactivacion de horarios
- dias del horario con entrada, salida, dia laboral y crosses_midnight visible
- pausas programadas por dia con nombre, hora inicio, hora fin, duracion, computable/pagada y requerida

Reglas UX/seguridad aplicadas:
- solo se muestran horarios de la empresa activa
- no se acepta company_id manipulable
- las pausas solo se muestran y guardan para dias del horario actualmente editado
- crosses_midnight valida horarios que terminan al dia siguiente, sin calcular jornada

Sprint 2B agrega la pantalla simple /schedule-assignments para:
- asignar horario a trabajador
- definir fecha efectiva
- reemplazar asignaciones conservando historial
- inactivar asignaciones sin borrarlas
- resolver vigencia por fecha desde dominio, sin calculos de jornada

Quedan pendientes para sprints posteriores:
- descansos obligatorios
- registro de jornada, time_events, calculos, alertas y reportes
```

## 10.4 Calendario de descansos obligatorios

**Prioridad:** P0 basico

Ruta implementada en Sprint 2C:

```text
/mandatory-rest-days
```

Incluye:

- Fecha.
- Nombre.
- Alcance global, empresa o centro.
- Estado activo o inactivo.
- Fuente opcional.
- Filtros por fecha, alcance, estado y centro.

Nota:

Esta pantalla administra configuracion. No registra jornada, no calcula horas y no genera alertas.

---

# 11. Registro electrónico

## 11.1 Panel de registros

**Prioridad:** P0

Incluye:

- Eventos del día.
- Trabajador.
- Tipo de evento.
- Hora local.
- Fuente.
- Estado.
- Centro.
- Dispositivo.
- Indicador de tardío/fuera de orden.

Filtros:

- Fecha.
- Centro.
- Trabajador.
- Fuente.
- Estado.
- Tipo.

Acciones:

- Ver evento.
- Captura manual justificada.
- Solicitar corrección.
- Anulación lógica con permiso.

---

## 11.2 Detalle de evento

**Prioridad:** P0

Incluye:

- Datos del evento.
- Fuente.
- Fecha/hora del hecho.
- Fecha/hora de recepción.
- Zona horaria.
- Usuario/dispositivo/integración.
- Estado.
- Evento relacionado si fue corrección/anulación.
- Auditoría.

---

## 11.3 Captura manual

**Prioridad:** P0

Incluye:

- Trabajador.
- Tipo de evento.
- Fecha.
- Hora.
- Motivo.
- Evidencia opcional.
- Aprobación requerida según política.

Debe generar evento con fuente:

```text
admin_manual
```

---

# 12. Kiosco

## 12.1 Pantalla de identificación

**Prioridad:** P0

Incluye:

- Código o número de empleado.
- NIP.
- Botón continuar.
- Mensaje de error.
- Bloqueo por intentos.

---

## 12.2 Pantalla de acción

**Prioridad:** P0

Después de autenticar:

- Nombre del trabajador.
- Centro.
- Hora actual.
- Acciones disponibles:

```text
Registrar entrada
Registrar salida
Iniciar pausa
Terminar pausa
```

La pantalla debe evitar acciones incoherentes cuando sea posible.

Ejemplo:

- Si no hay entrada abierta, no mostrar salida.
- Si ya está en pausa, mostrar terminar pausa.

---

## 12.3 Confirmación de registro

**Prioridad:** P0

Incluye:

- Acción realizada.
- Fecha y hora.
- Mensaje claro.
- Regresar a inicio automáticamente.

---

# 13. Jornadas calculadas

## 13.1 Listado de jornadas

**Prioridad:** P0

Incluye:

- Fecha.
- Trabajador.
- Centro.
- Horario.
- Entrada.
- Salida.
- Total trabajado.
- Ordinario.
- Extra.
- Descanso.
- Estado.
- Alertas.

Filtros:

- Periodo.
- Centro.
- Trabajador.
- Estado.
- Con alertas.
- Con incidencias.

Acciones:

- Ver detalle.
- Recalcular con permiso.
- Crear incidencia.
- Ir a cierre.

---

## 13.2 Detalle de jornada

**Prioridad:** P0

Secciones:

```text
Resumen
Eventos
Cálculo
Alertas
Incidencias
Correcciones
Historial de versiones
Auditoría
```

Debe mostrar:

- Eventos considerados.
- Eventos ignorados.
- Regla aplicada.
- Explicación del cálculo.
- Versión activa.
- Alertas generadas.

---

# 14. Alertas preventivas

## 14.1 Listado de alertas

**Prioridad:** P0

Incluye:

- Tipo.
- Nivel.
- Estado.
- Trabajador.
- Fecha.
- Periodo.
- Responsable.
- Fecha objetivo.
- Origen.
- Acción recomendada.

Filtros:

- Nivel.
- Estado.
- Centro.
- Responsable.
- Tipo.
- Periodo.

Acciones:

- Revisar.
- Asignar.
- Justificar.
- Crear incidencia.
- Cerrar.
- Ir a jornada.

---

## 14.2 Detalle de alerta

**Prioridad:** P0

Incluye:

- Título neutral.
- Descripción.
- Regla relacionada.
- Datos que originaron la alerta.
- Jornada/cálculo.
- Trabajador.
- Comentarios.
- Evidencia.
- Historial.
- Resolución.

Acciones:

```text
Marcar en revisión
Solicitar información
Justificar
Crear incidencia
Cerrar
```

---

# 15. Incidencias y correcciones

## 15.1 Listado de incidencias

**Prioridad:** P0

Incluye:

- Folio.
- Tipo.
- Estado.
- Trabajador.
- Jornada.
- Origen.
- Responsable.
- Fecha de apertura.
- Último movimiento.

Filtros:

- Estado.
- Tipo.
- Centro.
- Trabajador.
- Responsable.
- Origen.

---

## 15.2 Detalle de incidencia

**Prioridad:** P0

Secciones:

```text
Resumen
Comentarios
Evidencias
Corrección propuesta
Resolución
Historial
```

Acciones:

- Agregar comentario.
- Adjuntar evidencia.
- Proponer corrección.
- Aprobar.
- Rechazar.
- Marcar controversia.
- Cerrar.

---

## 15.3 Proponer corrección

**Prioridad:** P0

Casos mínimos:

- Agregar evento faltante.
- Anular lógicamente evento.
- Reemplazar evento.
- Cambiar horario aplicado.
- Ajustar resultado operativo con justificación.

Incluye:

- Valor original.
- Valor propuesto.
- Motivo.
- Evidencia.
- Vista previa del impacto.

---

## 15.4 Aprobar corrección

**Prioridad:** P0

Al aprobar:

```text
se aplica corrección
→ se recalcula jornada
→ se genera nueva versión
→ se actualizan alertas
→ si hay reporte, se genera nueva versión
```

La pantalla debe mostrar advertencia:

```text
Esta acción no eliminará el historial. Se generará una nueva versión.
```

---

# 16. Cierres de periodo

## 16.1 Listado de periodos

**Prioridad:** P0

Incluye:

- Periodo.
- Tipo.
- Centro.
- Estado.
- Trabajadores incluidos.
- Alertas críticas.
- Reportes generados.
- Conformes.
- No conformes.
- Pendientes.

Acciones:

- Crear cierre.
- Ver detalle.
- Recalcular.
- Enviar a revisión.
- Cerrar.

---

## 16.2 Crear cierre

**Prioridad:** P0

Incluye:

- Tipo de periodo.
- Fecha inicio.
- Fecha fin.
- Centro opcional.
- Trabajadores incluidos.
- Confirmar generación.

---

## 16.3 Detalle de cierre

**Prioridad:** P0

Tabs:

```text
Resumen
Trabajadores
Alertas
Incidencias
Reportes individuales
Conformidades
Exportaciones
Auditoría
```

Indicadores:

- Total trabajadores.
- Alertas críticas pendientes.
- Reportes disponibles.
- Reportes conformes.
- No conformes.
- Pendientes.
- En aclaración.

Acciones:

- Calcular/recalcular.
- Resolver alertas.
- Generar reportes.
- Enviar a revisión.
- Exportar.
- Cerrar.

Regla:

No se permite cierre final con alertas críticas pendientes.

---

# 17. Conformidad digital

## 17.1 Reporte individual administrativo

**Prioridad:** P0

Vista RH/Jurídico.

Incluye:

- Trabajador.
- Periodo.
- Versión activa.
- Totales.
- Alertas.
- Incidencias.
- Estado de conformidad.
- Hash.
- Historial de versiones.
- Archivo PDF.

Acciones:

- Enviar a revisión.
- Ver versión.
- Descargar.
- Ver confirmación.
- Crear incidencia.

---

## 17.2 Vista de revisión del trabajador

**Prioridad:** P0

Incluye:

- Periodo.
- Entradas/salidas.
- Pausas.
- Horas ordinarias.
- Posibles horas extra.
- Incidencias.
- Alertas visibles.
- Totales.
- Texto de revisión.
- Botones:

```text
Estoy conforme
No estoy conforme / solicitar aclaración
Revisar después
```

---

## 17.3 Confirmación conforme

**Prioridad:** P0

Incluye:

- Texto sin renuncia de derechos.
- Confirmación expresa.
- NIP o código si aplica.
- Botón confirmar.
- Registro de fecha/hora.

---

## 17.4 No conformidad

**Prioridad:** P0

Incluye:

- Seleccionar jornada o registro.
- Motivo.
- Comentario.
- Evidencia opcional.
- Crear aclaración/incidencia.

Resultado:

```text
period_report = non_conformant
incident = open
```

---

# 18. Portal de la persona trabajadora

## 18.1 Inicio trabajador

**Prioridad:** P0

Incluye:

- Jornada de hoy.
- Último registro.
- Próxima acción sugerida.
- Alertas visibles.
- Reportes pendientes.
- Aclaraciones abiertas.

---

## 18.2 Mi jornada de hoy

**Prioridad:** P0

Incluye:

- Entrada.
- Salida.
- Pausas.
- Total estimado.
- Estado.
- Solicitar aclaración.

---

## 18.3 Mi semana / periodo

**Prioridad:** P0

Incluye:

- Días del periodo.
- Horas por día.
- Incidencias.
- Pendientes.
- Ir a reporte.

---

## 18.4 Mis reportes

**Prioridad:** P0

Incluye:

- Periodo.
- Estado.
- Fecha disponible.
- Fecha confirmado.
- Resultado.
- Acción pendiente.

---

## 18.5 Mis aclaraciones

**Prioridad:** P0

Incluye:

- Folio.
- Fecha.
- Tipo.
- Estado.
- Última respuesta.
- Detalle.

---

# 19. Reportes

## 19.1 Centro de reportes

**Prioridad:** P0

Reportes mínimos:

- Diario.
- Semanal.
- Por periodo.
- Por persona.
- Por centro.
- Horas extra.
- Alertas.
- Incidencias.
- Conformidad.
- Jornadas incompletas.

Filtros:

- Empresa.
- Centro.
- Periodo.
- Trabajador.
- Estado.
- Tipo.

Acciones:

- Generar.
- Descargar PDF.
- Descargar CSV/XLSX.
- Programar generación si es pesado.

---

## 19.2 Reporte generado

**Prioridad:** P0

Incluye:

- Parámetros usados.
- Estado.
- Fecha de generación.
- Usuario.
- Archivo.
- Hash si aplica.
- Descargar.

---

# 20. Expedientes

## 20.1 Generar expediente

**Prioridad:** P0

Incluye:

- Tipo de alcance:

```text
Trabajador
Centro
Periodo
Solicitud específica
```

- Fechas.
- Personas incluidas.
- Elementos a incluir.
- Confirmación.

---

## 20.2 Listado de expedientes

**Prioridad:** P0

Incluye:

- Folio.
- Alcance.
- Estado.
- Solicitante.
- Fecha.
- Expiración.
- Hash/manifiesto.
- Archivo.

Acciones:

- Ver.
- Descargar.
- Regenerar si falla.
- Expirar.

---

## 20.3 Detalle de expediente

**Prioridad:** P0

Incluye:

- Alcance.
- Elementos incluidos.
- Archivos.
- Manifiesto.
- Hash.
- Auditoría de generación.
- Descarga.

---

# 21. Importaciones y exportaciones

## 21.1 Importaciones

**Prioridad:** P0

Tipos:

- Personas.
- Horarios.
- Eventos.

Incluye:

- Descargar plantilla.
- Subir archivo.
- Validar.
- Resultado por fila.
- Errores descargables.
- Estado de lote.

---

## 21.2 Detalle de importación

**Prioridad:** P0

Incluye:

- Archivo.
- Total filas.
- Correctas.
- Errores.
- Saltadas.
- Tabla de errores.
- Entidades creadas.
- Reintentar corregido.

---

## 21.3 Exportaciones de prenómina

**Prioridad:** P0/P1 alto

Incluye:

- Periodo.
- Centro.
- Trabajadores.
- Conceptos:

```text
Horas ordinarias
Horas extra
Domingo
Descanso obligatorio
Incidencias
```

- Formato:

```text
CSV
XLSX
Archivo compatible ClickBalance cuando se confirme formato
```

---

# 22. Integraciones y API

## 22.1 Tokens API

**Prioridad:** P0

Incluye:

- Nombre.
- Empresa.
- Alcance.
- Fecha creación.
- Último uso.
- Estado.
- Revocar.

Acciones:

- Crear token.
- Copiar token una sola vez.
- Revocar.
- Ver logs.

---

## 22.2 Logs de integración

**Prioridad:** P0

Incluye:

- Fecha.
- Origen.
- Operación.
- Estado.
- Trace ID.
- Error.
- Payload sanitizado con permisos.

---

## 22.3 Conexiones externas

**Prioridad:** P1

Incluye:

- Proveedor.
- Dirección.
- Credenciales.
- Estado.
- Última sincronización.
- Errores.

ClickBalance queda como P1 condicionado para API directa.

---

# 23. Auditoría

## 23.1 Bitácora

**Prioridad:** P0

Incluye:

- Fecha.
- Usuario.
- Acción.
- Entidad.
- Valor anterior.
- Valor nuevo.
- Motivo.
- IP.
- Fuente.

Filtros:

- Usuario.
- Acción.
- Entidad.
- Fecha.
- Trabajador.
- Empresa.

---

## 23.2 Accesos

**Prioridad:** P0

Incluye:

- Login.
- Logout.
- Fallos.
- Uso de API token.
- Acceso soporte.

---

# 24. Configuración

## 24.1 Configuración de cierre

**Prioridad:** P0

Incluye:

- Tipo de periodo.
- Día de cierre.
- Envío automático a revisión.
- Bloqueo por alertas críticas.
- Recordatorios.

---

## 24.2 Configuración de kiosco

**Prioridad:** P0

Incluye:

- Requerir NIP.
- Intentos máximos.
- Centros permitidos.
- Acciones habilitadas.
- Tiempo de sesión.

---

## 24.3 Configuración de conformidad

**Prioridad:** P0

Incluye:

- Requerir NIP.
- Texto de revisión.
- Plazo de respuesta.
- Recordatorios.
- Permitir no conformidad.
- Adjuntos.

---

# 25. Flujos principales

## 25.1 Configuración inicial

```text
Crear empresa
→ crear centros
→ crear usuarios
→ importar trabajadores
→ crear horarios
→ asignar horarios
→ configurar kiosco
→ activar registro
```

Pantallas involucradas:

- Empresa.
- Centros.
- Usuarios.
- Trabajadores.
- Horarios.
- Kiosco.
- Importaciones.

---

## 25.2 Registro desde kiosco

```text
Código/NIP
→ seleccionar acción
→ registrar evento
→ confirmar
→ calcular jornada
→ generar alerta si aplica
```

Pantallas:

- Kiosco identificación.
- Kiosco acción.
- Confirmación.
- Jornadas.
- Alertas.

---

## 25.3 Corrección de jornada

```text
Alerta o solicitud
→ incidencia
→ propuesta de corrección
→ aprobación
→ nuevo evento/corrección
→ recalculo
→ nueva versión
→ actualización de alertas
```

Pantallas:

- Alertas.
- Incidencias.
- Detalle jornada.
- Corrección.
- Historial.

---

## 25.4 Cierre y conformidad

```text
Crear periodo
→ calcular jornadas
→ resolver alertas críticas
→ generar reportes
→ enviar a trabajador
→ conforme / no conforme / pendiente
→ cerrar o abrir aclaración
```

Pantallas:

- Cierres.
- Reportes individuales.
- Portal trabajador.
- Incidencias.
- Expedientes.

---

## 25.5 Expediente

```text
Seleccionar alcance
→ generar expediente
→ procesar job
→ generar manifiesto
→ descargar
→ auditar entrega
```

Pantallas:

- Generar expediente.
- Listado expedientes.
- Detalle expediente.
- Auditoría.

---

# 26. Componentes reutilizables sugeridos

## 26.1 Componentes de datos

- Tabla con filtros.
- Buscador.
- Selector de periodo.
- Selector de centro.
- Selector de trabajador.
- Badge de estado.
- Badge de severidad.
- Timeline de eventos.
- Timeline de auditoría.
- Card de resumen.
- Modal de confirmación.
- Uploader de evidencia.
- Descarga de archivo.
- Panel de errores de importación.

## 26.2 Componentes de dominio

- Resumen de jornada.
- Lista de eventos.
- Explicación de cálculo.
- Panel de alertas.
- Panel de incidencias.
- Historial de versiones.
- Confirmación digital.
- Vista de reporte individual.
- Manifiesto de expediente.
- Resultado de importación.

---

# 27. Estados visuales recomendados

## 27.1 Alertas

| Estado | Significado |
|---|---|
| Nueva | Detectada sin revisar |
| En revisión | Alguien la está atendiendo |
| Pendiente información | Falta dato o evidencia |
| Justificada | Se documentó motivo |
| Corregida | Se resolvió con corrección |
| Cerrada | Finalizada |

## 27.2 Periodo

| Estado | Significado |
|---|---|
| En cálculo | Procesando jornadas |
| Con alertas | Hay alertas pendientes |
| Revisión administrativa | RH revisa antes de enviar |
| Disponible para trabajador | Reportes disponibles |
| Cerrado | Periodo cerrado |
| Cancelado | Cierre anulado |

## 27.3 Reporte individual

| Estado | Significado |
|---|---|
| Borrador | Todavía no visible |
| Disponible | Trabajador puede revisar |
| Conforme | Confirmado |
| No conforme | Hay aclaración |
| Pendiente | Sin respuesta |
| En aclaración | Incidencia abierta |
| Cerrado | Finalizado |

---

# 28. Pantallas P0 del MVP

Las pantallas obligatorias para un piloto funcional son:

```text
Login
Selector de empresa
Dashboard
Empresa
Centros
Usuarios
Trabajadores
Detalle trabajador
Horarios
Asignación de horario
Registros
Captura manual
Kiosco
Jornadas
Detalle jornada
Alertas
Detalle alerta
Incidencias
Detalle incidencia
Proponer corrección
Cierres
Detalle cierre
Reporte individual administrativo
Portal trabajador inicio
Portal trabajador jornada
Portal trabajador reportes
Conformidad / No conformidad
Reportes
Importaciones
Exportaciones
Expedientes
Tokens API
Logs de integración
Auditoría
Configuración básica
```

---

# 29. Pantallas P1

```text
Planes comerciales
Permisos avanzados
Reglas legales editables visualmente
Conexiones externas avanzadas
ClickBalance API directa
Notificaciones avanzadas
Dashboard ejecutivo
Reportes personalizados
Gestión documental laboral
App nativa
Reconocimiento facial
```

---

# 30. Criterios de aceptación UX

El mapa de pantallas se considera aprobado cuando:

1. Cada requisito P0 tiene al menos una pantalla o flujo que lo soporta.
2. Cada rol puede identificar dónde realiza sus tareas principales.
3. El trabajador puede registrar, consultar y manifestar conformidad/no conformidad.
4. RH puede configurar, revisar, corregir y cerrar.
5. El supervisor puede revisar alertas e incidencias de su equipo.
6. Nómina puede exportar información de periodo.
7. Jurídico puede consultar evidencia y expedientes.
8. Soporte puede operar sin acceso libre no auditado.
9. Las correcciones no se muestran como edición destructiva.
10. Las alertas usan lenguaje neutral.
11. El cierre no permite ignorar alertas críticas sin revisión.
12. La interfaz puede convivir con API, CSV y jobs sin lógica duplicada.

---

# 31. Siguiente documento

Después de aprobar este mapa de pantallas, el siguiente documento recomendado es:

```text
docs/07-API/API-0001-ESPECIFICACION-API-MVP.md
```

Ahí se definirá:

- Recursos.
- Endpoints.
- Autenticación.
- Permisos.
- Versionamiento.
- Idempotencia.
- Errores estándar.
- Ejemplos request/response.
- Webhooks o integraciones futuras.
