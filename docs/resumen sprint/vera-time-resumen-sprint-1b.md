# Vera Time - Resumen de Logros del Sprint 1B

## Resumen general

En **Sprint 1B** Vera Time agrego la administracion de centros de trabajo.

Este sprint no avanzo hacia trabajadores, horarios, jornadas ni reportes. Se enfoco solo en que cada empresa pueda tener sus propios centros, con codigo, nombre, zona horaria y estado.

En pocas palabras:

```text
Sprint 1B dejo listo el CRUD de centros de trabajo para la empresa activa,
con proteccion multiempresa y zona horaria por centro.
```

---

## 1. Se creo la base de centros de trabajo

Ya existe la tabla de centros.

Cada centro pertenece obligatoriamente a una empresa.

Esto permite que Vera Time pueda manejar estructuras como:

```text
empresa
-> centro matriz
-> sucursal
-> planta
-> oficina
```

Sin mezclar centros entre empresas diferentes.

---

## 2. Se agrego codigo unico por empresa

Cada centro tiene un codigo interno.

Ese codigo debe ser unico dentro de la misma empresa, pero puede repetirse en otra empresa.

Ejemplo:

```text
Empresa A puede tener CDMX-01
Empresa B tambien puede tener CDMX-01
```

Esto es correcto porque cada empresa tiene su propio contexto.

---

## 3. Se agrego zona horaria por centro

Cada centro puede tener su propia zona horaria.

Esto prepara al sistema para operar empresas con centros en diferentes lugares.

Todavia no se registran jornadas en Sprint 1B, pero la base ya quedo lista para que los eventos futuros usen la zona horaria correcta.

---

## 4. Se agrego pantalla de centros

Ya existe una pantalla para administrar centros desde la empresa activa.

La pantalla permite:

```text
ver centros
crear centro
editar centro
inactivar centro
ver codigo, nombre, zona horaria y estado
```

La pantalla no acepta que el usuario elija manualmente otra empresa para el centro. El sistema usa siempre la empresa activa.

---

## 5. Se protegieron los centros por empresa

Se agregaron reglas para evitar accesos incorrectos.

El sistema bloquea:

```text
centros de otra empresa
operaciones si la empresa esta inactiva
usuarios sin empresa activa
roles no autorizados
manipulacion de company_id desde formulario
```

Esto mantiene el principio principal de Vera Time:

```text
una empresa no debe ver ni modificar datos de otra empresa
```

---

## 6. Se mantuvo la logica fuera de Livewire

La pantalla de centros no carga la logica pesada dentro del componente.

Las operaciones principales quedaron separadas en Actions:

```text
crear centro
actualizar centro
inactivar centro
```

Esto deja el modulo mejor preparado para reutilizar la misma logica despues desde API, importaciones o jobs.

---

## 7. Se agregaron pruebas de seguridad y validacion

Sprint 1B agrego pruebas para confirmar que centros funciona de forma segura.

Entre lo probado:

```text
guest no entra a /centers
usuario sin empresa activa no entra
usuario ve solo centros de su empresa activa
usuario no ve ni edita centros de otra empresa
codigo unico por empresa
mismo codigo permitido en empresas diferentes
empresa inactiva bloquea operacion
roles no autorizados no crean, editan ni inactivan
company_id manipulado no cambia la empresa del centro
validaciones de codigo, nombre, zona horaria, estado y direccion JSON
```

---

## 8. Validaciones finales

Validaciones reportadas para el cierre:

```text
vendor\bin\pest.bat tests\Feature\Sprint1B\CenterManagementTest.php -> OK, 20 tests / 48 assertions
php artisan migrate:fresh --seed -> OK
php artisan test -> OK, 71 tests / 166 assertions
npm.cmd run build -> OK
```

---

## Que NO se hizo todavia

Sprint 1B no construyo:

```text
trabajadores
horarios
registro de jornada
motor legal
alertas
incidencias
reportes
conformidad digital
API de negocio
ClickBalance
biometria
app nativa
```

Eso fue correcto, porque Sprint 1B solo correspondia a centros de trabajo.

---

## Estado final del Sprint 1B

```text
Estado: Candidato a cierre
Alcance: BL-0203, BL-0204
Backend: Validado
Arquitectura: Aprobado con observaciones corregidas
QA y seguridad: Aprobado con observaciones corregidas
Pruebas: Validadas
Build frontend: Validado
Alcance fuera de Sprint 1B: No implementado
```
