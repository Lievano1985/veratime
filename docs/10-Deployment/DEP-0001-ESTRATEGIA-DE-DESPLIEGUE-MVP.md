---
id: DEP-0001
title: Estrategia de despliegue del MVP
project: Vera Time
version: 1.0.0
status: Draft
owner: Product Architecture
created: 2026-07-04
updated: 2026-07-04
tags:
  - deployment
  - despliegue
  - hosting
  - godaddy
  - cpanel
  - mysql
  - laravel
  - mvp
  - aws-futuro
  - veratime
---

# DEP-0001 — Estrategia de despliegue del MVP

## 1. Objetivo

Definir la estrategia de despliegue del MVP de Vera Time considerando la infraestructura disponible actualmente:

```text
GoDaddy cPanel
+ MySQL
+ Laravel
+ Livewire
+ Database queue
+ Cron de cPanel
+ Storage local/persistente
```

El objetivo es iniciar el desarrollo y pruebas del MVP sin elevar costos, manteniendo la arquitectura portable para migrar posteriormente a AWS antes del piloto real o producción comercial.

---

## 2. Decisión principal de despliegue

Vera Time se desplegará por etapas.

```text
Fase 1 — Desarrollo / demo inicial:
GoDaddy cPanel + MySQL

Fase 2 — Pre-piloto:
AWS pagado controlado

Fase 3 — Producción comercial:
AWS formal con base separada, storage, backups, workers y monitoreo
```

## 2.1 Decisión oficial

Para el MVP inicial:

```text
Base de datos: MySQL / MariaDB compatible
Colas: database queue
Scheduler: cron de cPanel
Archivos: storage local/persistente del hosting
Cache: file/database según disponibilidad
Redis: no requerido para MVP
AWS: fase posterior
```

---

## 3. Principios de despliegue

1. **No depender del hosting actual para siempre.**
   El sistema debe poder migrar a AWS sin reescribir el núcleo.

2. **Mantener Laravel estándar.**
   Evitar hacks de hosting que rompan actualizaciones o despliegues futuros.

3. **Separar configuración del código.**
   `.env` por ambiente, sin secretos en Git.

4. **No ejecutar procesos pesados en petición web.**
   Importaciones, reportes, expedientes y recalculos grandes deben ir a jobs.

5. **Iniciar simple.**
   Database queue y cron son suficientes para el MVP inicial.

6. **Preparar migración a AWS.**
   Storage, base de datos, colas y variables deben diseñarse de forma portable.

7. **No usar datos reales sensibles en ambiente informal.**
   Datos reales de trabajadores requieren pre-piloto controlado.

8. **Proteger evidencia.**
   Archivos, reportes, hashes y auditoría deben conservarse con respaldos.

---

# 4. Ambientes

## 4.1 Local

Uso:

- Desarrollo diario.
- Pruebas unitarias.
- Pruebas feature.
- Pruebas API.
- Migraciones iniciales.

Tecnología:

```text
Laravel local
MySQL local
Node/Vite
Storage local
Queue sync/database
```

## 4.2 Staging

Uso:

- Validación interna.
- Revisión de pantallas.
- Pruebas manuales.
- Demo con datos ficticios.
- Validación de importaciones/exportaciones.

Infraestructura inicial:

```text
GoDaddy cPanel o subdominio
MySQL staging
APP_ENV=staging
APP_DEBUG=false
Datos ficticios
```

Ejemplo de dominio:

```text
staging.veratime.com
```

## 4.3 Producción inicial / MVP privado

Uso:

- Demo formal.
- Pruebas controladas.
- Validación de negocio.
- Sin datos reales sensibles o con datos muy controlados.

Infraestructura inicial posible:

```text
GoDaddy cPanel
MySQL production
APP_ENV=production
APP_DEBUG=false
```

## 4.4 Pre-piloto AWS

Uso:

- Empresa piloto real.
- Datos reales de trabajadores.
- Pruebas operativas serias.
- Reportes usados por RH/nómina.
- Evidencia laboral real.

Infraestructura futura:

```text
Servidor app
Base MySQL administrada o separada
Storage tipo S3
Backups
Workers
Monitoreo
Ambiente staging separado
```


---

# 5. Estructura de despliegue en cPanel

## 5.1 Principio

El directorio público del dominio debe apuntar a la carpeta:

```text
public/
```

de Laravel.

Nunca debe exponerse todo el proyecto como raíz pública.

## 5.2 Estructura recomendada

Ejemplo conceptual:

```text
/home/usuario/
├── veratime/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── artisan
└── public_html/
    └── apunta o contiene public/
```

## 5.3 Opciones de publicación

### Opción recomendada

Configurar el dominio o subdominio para que su document root apunte a:

```text
/home/usuario/veratime/public
```

### Opción alternativa

Si cPanel no permite cambiar document root fácilmente:

- Colocar el proyecto fuera de `public_html`.
- Copiar o enlazar el contenido de `public/` dentro del document root.
- Ajustar rutas de `index.php` a `vendor/autoload.php` y `bootstrap/app.php`.
- Documentar el ajuste para no romper despliegues futuros.

---

# 6. Requisitos del hosting

Antes del despliegue se debe validar:

## 6.1 PHP

Revisar:

```text
Versión PHP compatible con la versión de Laravel usada
Extensiones PHP requeridas
memory_limit
max_execution_time
upload_max_filesize
post_max_size
```

Extensiones comunes requeridas:

```text
openssl
pdo
pdo_mysql
mbstring
tokenizer
xml
ctype
json
fileinfo
curl
zip
intl
bcmath
```

## 6.2 Composer

Validar si el hosting permite:

```text
composer install
```

Si no lo permite, se podrá construir en local o CI y subir `vendor/`, aunque no es la opción ideal.

## 6.3 Node/Vite

Validar si el hosting permite:

```text
npm install
npm run build
```

Si no lo permite, compilar assets localmente y subir:

```text
public/build
```

## 6.4 MySQL

Validar:

- versión;
- usuario;
- contraseña;
- base de datos;
- charset;
- collation;
- permisos;
- tamaño máximo.

Recomendación:

```text
charset: utf8mb4
collation: utf8mb4_unicode_ci
```

## 6.5 Cron

Debe permitir configurar tareas programadas.

Laravel Scheduler requiere ejecutar:

```bash
php artisan schedule:run
```

periódicamente.

## 6.6 Storage

Validar:

- permisos de escritura en `storage`;
- permisos de escritura en `bootstrap/cache`;
- espacio disponible;
- política de respaldo;
- descarga segura de archivos.

---

# 7. Variables de entorno

## 7.1 Archivo `.env`

Cada ambiente tendrá su propio `.env`.

No se sube a Git.

## 7.2 Variables mínimas

```env
APP_NAME="Vera Time"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://app.veratime.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=file

FILESYSTEM_DISK=local
```

## 7.3 Staging

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.veratime.com
LOG_LEVEL=debug
```

## 7.4 Seguridad

Nunca versionar:

```text
.env
credenciales
tokens
contraseñas
llaves API
backups de base de datos
archivos de evidencia reales
```


---

# 8. Base de datos MySQL

## 8.1 Decisión

El MVP usará:

```text
MySQL / MariaDB compatible
```

en el hosting actual.

## 8.2 Migraciones

El despliegue deberá ejecutar:

```bash
php artisan migrate --force
```

Solo después de respaldo.

## 8.3 Seeds

En producción no se ejecutan seeds destructivos.

Seeds permitidos:

- roles iniciales;
- permisos;
- planes base;
- reglas legales base;
- tipos de alerta.

Comando sugerido:

```bash
php artisan db:seed --class=InitialCatalogSeeder --force
```

## 8.4 Backups antes de migrar

Antes de cualquier migración en staging o producción:

```text
crear backup de base
confirmar archivo descargable
confirmar fecha/hora
documentar responsable
```

---

# 9. Colas con database queue

## 9.1 Decisión

Para el hosting actual:

```text
QUEUE_CONNECTION=database
```

## 9.2 Tablas requeridas

Laravel requiere tablas para jobs.

Comandos conceptuales:

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

## 9.3 Ejecución en cPanel

Como en hosting compartido normalmente no hay worker permanente, se usará ejecución controlada por cron.

Opción simple:

```bash
php artisan queue:work --stop-when-empty
```

Ejecutado cada minuto o cada pocos minutos según limitación del hosting.

Para Vera Time en cPanel, el scheduler ejecuta la cola operacional con:

```bash
php artisan queue:work database --queue=work-days,default --stop-when-empty --max-time=50 --tries=3
```

La cola `work-days` procesa recalculos derivados de eventos de asistencia. `default` queda disponible para jobs generales del MVP. En hosting compartido no se requiere worker permanente: el cron de `schedule:run` levanta procesos cortos y termina cuando no hay trabajos pendientes.

## 9.4 Jobs que irán a cola

- Recalculo de jornadas por evento de asistencia.
- Importaciones CSV.
- Recalculos masivos.
- Generación de reportes.
- Generación de expedientes.
- Exportaciones.
- Notificaciones.
- Limpieza temporal.

## 9.5 Regla

Ningún proceso pesado debe ejecutarse dentro de una petición web si puede exceder tiempo o memoria del hosting.

---

# 10. Scheduler y cron

## 10.1 Decisión

Usar Laravel Scheduler.

En cPanel se configurará una tarea cron para ejecutar:

```bash
php /home/usuario/veratime/artisan schedule:run >> /dev/null 2>&1
```

La ruta exacta deberá ajustarse según el hosting.

Con este único cron, Laravel ejecuta:

- `work-days:auto-refresh` para el respaldo programado por empresa.
- `queue:work database --queue=work-days,default --stop-when-empty --max-time=50 --tries=3` para procesar jobs pendientes sin worker permanente.

## 10.2 Frecuencia

Ideal:

```text
cada minuto
```

Si el hosting limita la frecuencia, usar la mínima permitida.

## 10.3 Tareas programadas MVP

El scheduler ejecutará:

- procesar cola por lotes si aplica;
- cierres pendientes;
- recalculos programados;
- recordatorios;
- limpieza de archivos temporales;
- verificación de respaldos;
- generación diferida de reportes;
- revisión de jobs fallidos.

---

# 11. Archivos y storage

## 11.1 Decisión inicial

En GoDaddy:

```text
FILESYSTEM_DISK=local
```

o un disco local configurado para archivos privados.

## 11.2 Tipos de archivos

- Evidencias.
- Reportes PDF.
- CSV/XLSX.
- ZIP de expedientes.
- Manifiestos.
- Archivos importados.
- Errores de importación.

## 11.3 Regla de seguridad

Los archivos sensibles no deben quedar públicos por defecto.

Las descargas deben pasar por control de permisos.

## 11.4 Preparación para AWS

El modelo debe permitir migrar a:

```text
S3 compatible
```

sin cambiar entidades del dominio.

La tabla `files` conserva:

- disk;
- path;
- hash;
- owner;
- category;
- company_id.

---

# 12. Cache y sesiones

## 12.1 Decisión inicial

Para cPanel:

```env
CACHE_STORE=file
SESSION_DRIVER=file
```

o `database` si se necesita persistencia más controlada.

## 12.2 Redis

Redis no será requerido en MVP.

Se deja para:

- AWS;
- colas más robustas;
- cache;
- Horizon;
- monitoreo avanzado.


---

# 13. Logs

## 13.1 Logs de Laravel

Configuración inicial:

```env
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

En staging:

```env
LOG_LEVEL=debug
```

## 13.2 Qué revisar

- errores 500;
- jobs fallidos;
- errores de API;
- fallos de login;
- fallos de importación;
- errores de permisos;
- problemas de storage.

## 13.3 No guardar secretos

Los logs no deben incluir:

- contraseñas;
- NIP;
- tokens;
- credenciales;
- payloads sensibles completos;
- documentos personales completos.

---

# 14. Seguridad de despliegue

## 14.1 Reglas mínimas

- `APP_DEBUG=false` en staging/production.
- HTTPS activo.
- `.env` fuera del acceso público.
- `storage` protegido.
- `vendor` no navegable públicamente.
- permisos de archivos controlados.
- contraseñas fuertes.
- usuario de base con permisos mínimos.
- backups protegidos.
- tokens API revocables.
- logs sin secretos.

## 14.2 Permisos de carpetas

Carpetas escribibles:

```text
storage/
bootstrap/cache/
```

No dar permisos `777` salvo emergencia temporal y documentada.

## 14.3 Acceso administrativo

Limitar:

- cPanel;
- FTP/SFTP;
- base de datos;
- Git;
- correo de recuperación;
- usuario root o principal.

---

# 15. Comandos de despliegue Laravel

## 15.1 Instalación inicial

Comandos conceptuales:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Si `storage:link` no funciona en hosting compartido, se documentará alternativa.

## 15.2 Assets

En local o CI:

```bash
npm install
npm run build
```

Subir:

```text
public/build
```

## 15.3 Después de cada despliegue

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Nota:

No todos los comandos aplican igual en todos los ambientes. Se definirá checklist por ambiente.

---

# 16. Flujo de despliegue recomendado

## 16.1 Desarrollo

```text
Crear rama
→ desarrollar
→ pruebas locales
→ commit
→ push
→ revisión
```

## 16.2 Staging

```text
Respaldar staging
→ subir cambios
→ instalar dependencias si aplica
→ ejecutar migraciones
→ compilar/subir assets
→ limpiar/cachear configuración
→ ejecutar smoke test
→ revisar logs
```

## 16.3 Producción

```text
Confirmar ventana de despliegue
→ backup base de datos
→ backup archivos críticos
→ poner modo mantenimiento si aplica
→ subir release
→ instalar dependencias
→ ejecutar migraciones
→ limpiar/cachear configuración
→ levantar aplicación
→ smoke test
→ revisar logs
→ documentar versión desplegada
```

Comando mantenimiento:

```bash
php artisan down
php artisan up
```

En hosting compartido puede usarse solo si el despliegue lo requiere.

---

# 17. Estrategia de rollback

## 17.1 Regla

No desplegar sin posibilidad de volver atrás.

## 17.2 Rollback de código

Mantener versión anterior disponible:

```text
release anterior
backup zip
rama/tag en Git
```

## 17.3 Rollback de base de datos

Las migraciones deben diseñarse con cuidado.

Antes de migrar:

```text
backup obligatorio
```

Si una migración es riesgosa:

- dividirla;
- probar en staging;
- evitar pérdida de datos;
- no borrar columnas críticas de inmediato;
- usar migraciones reversibles cuando sea posible.

## 17.4 Rollback de archivos

Los archivos críticos no se eliminan durante despliegue.


---

# 18. Backups

## 18.1 Base de datos

Frecuencia inicial:

```text
staging: bajo demanda
production/demo: diario o antes de cambios importantes
```

Antes de piloto real:

```text
backups automáticos diarios
retención definida
prueba de restauración
```

## 18.2 Archivos

Respaldar:

- evidencias;
- reportes;
- expedientes;
- importaciones;
- manifiestos.

## 18.3 Prueba de restauración

No basta con tener backup.

Debe probarse:

```text
backup descargado
restauración en ambiente de prueba
validación de login
validación de datos
validación de archivos
```

## 18.4 Registro de backups

Mantener bitácora:

- fecha;
- responsable;
- ambiente;
- tamaño;
- resultado;
- ubicación;
- observaciones.

---

# 19. Smoke tests post-despliegue

Después de cada despliegue, validar:

## 19.1 Smoke test técnico

- sitio carga;
- login funciona;
- conexión MySQL funciona;
- storage escribe;
- cron existe;
- cola procesa al menos un job pequeño;
- assets cargan;
- logs sin error crítico.

## 19.2 Smoke test funcional

- crear trabajador de prueba;
- registrar evento de prueba;
- consultar jornada;
- generar alerta si aplica;
- crear incidencia;
- generar reporte pequeño;
- probar endpoint API básico;
- cerrar sesión.

## 19.3 Smoke test API

- token válido;
- GET `/api/v1/workers`;
- POST `/api/v1/time-events` en ambiente de prueba;
- validar respuesta estándar;
- validar trace/log.

---

# 20. Monitoreo inicial

En hosting actual el monitoreo será básico.

## 20.1 Revisar manualmente

- logs de Laravel;
- jobs fallidos;
- uso de base;
- uso de storage;
- errores 500;
- cron;
- correo/notificaciones si aplica.

## 20.2 Jobs fallidos

Debe existir forma de consultar:

```bash
php artisan queue:failed
```

y reintentar:

```bash
php artisan queue:retry all
```

según sea seguro.

## 20.3 Métricas mínimas

- cantidad de eventos registrados;
- jobs pendientes;
- jobs fallidos;
- reportes generados;
- errores API;
- tamaño de storage;
- tamaño de base de datos.

---

# 21. Configuración API en despliegue

## 21.1 Sanctum

Validar:

- tokens funcionando;
- scopes;
- revocación;
- rate limiting;
- HTTPS.

## 21.2 CORS

Si en el futuro hay PWA separada, app móvil o dominio diferente, configurar CORS.

Para MVP con Livewire en el mismo dominio, mantenerlo limitado.

## 21.3 Rate limiting

Configurar límites iniciales por token.

Ejemplo:

```text
60 requests/minuto por token
```

Ajustable según pruebas.

---

# 22. Estrategia para PWA/kiosco

## 22.1 PWA

En MVP se usará web responsiva/PWA.

Validar:

- assets;
- HTTPS;
- manifest si aplica;
- cache básica;
- no guardar datos sensibles innecesarios en cliente.

## 22.2 Kiosco

Validar:

- pantalla limpia;
- bloqueo por intentos;
- cierre automático;
- no dejar sesión abierta;
- evento registrado con fuente `kiosk`.

---

# 23. Manejo de datos reales

## 23.1 Regla

No usar datos reales de trabajadores en ambientes informales sin controles.

## 23.2 Datos ficticios

Para desarrollo y demo usar:

- nombres ficticios;
- RFC/CURP ficticios;
- horarios simulados;
- eventos de prueba.

## 23.3 Antes de piloto real

Requisitos mínimos:

- backup automático;
- HTTPS;
- usuarios y permisos;
- logs;
- política de acceso;
- prueba de restauración;
- soporte documentado;
- revisión de seguridad básica;
- plan de migración a AWS o ambiente controlado.


---

# 24. Migración posterior a AWS

## 24.1 Momento recomendado

Migrar antes de:

```text
empresa piloto real
datos reales sensibles
clientes pagando
API de terceros
expedientes laborales reales
uso de reportes para nómina
```

## 24.2 Arquitectura AWS inicial

Propuesta:

```text
Servidor app
MySQL separado o administrado
Storage tipo S3
Backups automáticos
Workers
Scheduler
Monitoreo
Staging y production separados
```

## 24.3 Migración de componentes

| Componente actual | AWS futuro |
|---|---|
| GoDaddy cPanel | EC2 / Lightsail / Laravel Cloud / alternativa |
| MySQL hosting | RDS MySQL o base administrada |
| Storage local | S3 |
| Database queue | Redis/SQS/database según fase |
| Cron cPanel | Scheduler en servidor / EventBridge futuro |
| Logs locales | CloudWatch u otra herramienta |
| Backups manuales | Backups automáticos y snapshots |

## 24.4 Preparación desde ahora

Para facilitar migración:

- no hardcodear rutas absolutas;
- usar Laravel Filesystem;
- usar `.env`;
- no depender de funciones exclusivas de cPanel;
- mantener migraciones limpias;
- mantener seeds controlados;
- usar Actions/Services;
- documentar comandos;
- respaldar archivos y base por separado.

---

# 25. Checklist de despliegue inicial en GoDaddy

## 25.1 Antes de subir

- [ ] Confirmar versión PHP.
- [ ] Confirmar extensiones PHP.
- [ ] Crear base MySQL.
- [ ] Crear usuario MySQL.
- [ ] Configurar dominio/subdominio.
- [ ] Confirmar document root a `public/`.
- [ ] Preparar `.env`.
- [ ] Generar `APP_KEY`.
- [ ] Compilar assets.
- [ ] Revisar permisos.
- [ ] Confirmar HTTPS.

## 25.2 Subida

- [ ] Subir código.
- [ ] Subir `vendor` o ejecutar composer.
- [ ] Subir `public/build`.
- [ ] Configurar `.env`.
- [ ] Ejecutar migraciones.
- [ ] Ejecutar seeds iniciales.
- [ ] Crear storage link o alternativa.
- [ ] Limpiar/cachear configuración.
- [ ] Configurar cron.
- [ ] Configurar queue database.

## 25.3 Después de subir

- [ ] Login.
- [ ] Dashboard.
- [ ] Crear empresa.
- [ ] Crear trabajador.
- [ ] Registrar evento.
- [ ] Probar job pequeño.
- [ ] Probar API básica.
- [ ] Revisar logs.
- [ ] Revisar permisos de archivos.
- [ ] Documentar versión desplegada.

---

# 26. Checklist pre-piloto

Antes de operar piloto real:

- [ ] Ambiente estable.
- [ ] HTTPS.
- [ ] Backups automáticos.
- [ ] Restauración probada.
- [ ] Roles y permisos.
- [ ] Multi-tenant probado.
- [ ] Motor legal probado.
- [ ] Cierre y conformidad probados.
- [ ] Exportaciones probadas.
- [ ] API probada.
- [ ] Jobs probados.
- [ ] Logs revisados.
- [ ] Política de datos reales.
- [ ] Plan de soporte.
- [ ] Plan de migración a AWS o ambiente equivalente.
- [ ] Sin defectos S1.
- [ ] Sin defectos S2 en cálculo, evidencia, seguridad o conformidad.

---

# 27. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| cPanel no permite workers permanentes | Usar database queue + cron |
| cron limitado | Procesar por lotes pequeños |
| composer no disponible | Construir local y subir vendor |
| node no disponible | Compilar assets localmente |
| storage limitado | Controlar archivos y limpiar temporales |
| jobs largos fallan | Dividir procesos |
| falta Redis | No requerido para MVP |
| rutas públicas mal configuradas | Document root debe apuntar a `public/` |
| APP_DEBUG activo | Checklist obligatorio |
| falta backup | No migrar sin respaldo |
| datos reales en ambiente débil | Migrar a AWS antes de piloto real |
| bloqueo por hosting | Mantener portabilidad |

---

# 28. Comandos de referencia

## 28.1 Composer

```bash
composer install --no-dev --optimize-autoloader
```

## 28.2 Laravel

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=InitialCatalogSeeder --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 28.3 Scheduler

```bash
php /home/usuario/veratime/artisan schedule:run >> /dev/null 2>&1
```

## 28.4 Queue

```bash
php artisan queue:work --stop-when-empty
php artisan queue:failed
php artisan queue:retry all
```

## 28.5 Mantenimiento

```bash
php artisan down
php artisan up
```

## 28.6 Limpieza

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

# 29. Criterios de aceptación de despliegue

La estrategia de despliegue queda aprobada cuando:

1. Define GoDaddy cPanel como fase inicial.
2. Define MySQL como base inicial.
3. Define database queue como colas iniciales.
4. Define cron de cPanel para scheduler.
5. Define storage local/persistente inicial.
6. No exige Redis ni AWS para iniciar.
7. Mantiene portabilidad a AWS.
8. Define ambientes local, staging y producción inicial.
9. Define checklist de despliegue.
10. Define smoke tests.
11. Define backups.
12. Define rollback.
13. Define riesgos del hosting.
14. Define momento de migración a AWS.
15. Protege datos reales antes del piloto.

---

# 30. Fuentes técnicas de referencia

- Laravel Deployment: https://laravel.com/docs/deployment
- Laravel Queues: https://laravel.com/docs/queues
- Laravel Task Scheduling: https://laravel.com/docs/scheduling
- Laravel Configuration: https://laravel.com/docs/configuration
- Laravel Filesystem: https://laravel.com/docs/filesystem
- Laravel Sanctum: https://laravel.com/docs/sanctum
- GoDaddy cPanel Cron Jobs: https://www.godaddy.com/help/create-cron-jobs-16086

---

# 31. Siguiente documento

Después de aprobar esta estrategia de despliegue, el siguiente paso recomendado es crear el backlog técnico inicial:

```text
docs/13-Backlog/BL-0001-BACKLOG-MVP-INICIAL.md
```

Ese documento convertirá todo lo definido en épicas, módulos, historias y prioridades para iniciar desarrollo con Codex.

