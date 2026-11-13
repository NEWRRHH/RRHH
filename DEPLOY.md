# Guía de despliegue — RRHH

Paso a paso para levantar el proyecto **en desarrollo** (tu PC) y **en producción** (la PC servidor), qué poner en cada `.env` y cómo mantenerlo.

---

## Índice

1. [Requisitos](#1-requisitos)
2. [Cómo está montado](#2-cómo-está-montado)
3. [Desarrollo (tu PC)](#3-desarrollo-tu-pc)
4. [Producción (PC servidor)](#4-producción-pc-servidor)
5. [Variables del `.env` de producción](#5-variables-del-env-de-producción)
6. [Dominio propio (p. ej. `rrhh.nanodev.com`)](#6-dominio-propio-p-ej-rrhhnanodevcom)
7. [Actualizar el servidor a una nueva versión](#7-actualizar-el-servidor-a-una-nueva-versión)
8. [Dependencias: instalar paquetes en front y back](#8-dependencias-instalar-paquetes-en-front-y-back)
9. [Comandos útiles](#9-comandos-útiles)
10. [Problemas frecuentes](#10-problemas-frecuentes)

---

## 1. Requisitos

| Qué | Notas |
|-----|-------|
| **Docker Desktop** (Windows/Mac) o **Docker Engine + Compose** (Linux) | Compose **v2.24 o superior** (`docker compose version`). En Windows, con el backend WSL2. |
| **Git** | Para clonar y actualizar el repo. |
| Cuenta de **Cloudflare** (gratis) | Solo si quieres acceso desde internet con dominio propio. Sin cuenta se puede usar el túnel rápido. |

No hace falta instalar PHP, Node, MySQL ni npm en la máquina: todo corre dentro de Docker.

---

## 2. Cómo está montado

```
 Internet ──► Cloudflare ──► tunnel ──► gateway (nginx) ─┬─► front  (Nuxt)        /  y  /api/*
                                                          ├─► back_reverb (WS)    /app/*
                                                          └─► back_nginx ─► app   /storage/*
                                                                  app ◄──► back_db (MySQL)
                                                                  queue (jobs)
```

| Servicio | Qué hace |
|----------|----------|
| `app` | Laravel (PHP-FPM). |
| `back_nginx` | Sirve la API de Laravel (puerto local 8000). |
| `back_db` | MySQL 8. Los datos viven en el volumen `back_dbdata`. |
| `back_reverb` | WebSockets (notificaciones y mensajes en tiempo real). |
| `queue` | Procesa los jobs en cola (mails, etc.). |
| `front` | Nuxt. En desarrollo: servidor dev con recarga en caliente. En producción: build compilado. |
| `gateway` | Punto de entrada único: une front + WebSocket + `/storage` en la misma URL (local: `http://localhost:8080`). |
| `tunnel` / `tunnel-domain` | Cloudflare Tunnel: publica el `gateway` en internet sin abrir puertos del router. |
| `env-check` | **Solo producción.** Revisa el `.env` y te muestra las credenciales que faltan. |
| `migrate` | **Solo producción.** Instala dependencias de Composer y ejecuta las migraciones en cada arranque. |

Archivos de Docker en la raíz:

| Archivo | Para qué |
|---------|----------|
| `docker-compose.yml` | Configuración base (desarrollo). |
| `docker-compose.prod.yml` | Cambios para producción (front compilado, `env-check`, `migrate`). Se activa desde el `.env`. |
| `.env.production.example` | Plantilla del `.env` de producción. |
| `docker/gateway/default.conf` | Configuración del gateway. |
| `docker/env-check.sh` | Script que revisa el `.env` de producción. |

---

## 3. Desarrollo (tu PC)

En desarrollo hay **tres** `.env` (ninguno se sube a git):

| Archivo | Obligatorio | Contenido |
|---------|-------------|-----------|
| `BACK/.env` | Sí | Configuración de Laravel. Se copia de `BACK/.env.example`. |
| `FRONT/.env` | Sí | Claves de Reverb para el navegador (ver abajo). |
| `.env` (raíz) | No | Solo para activar el túnel o `APP_DEBUG`. |

### Paso a paso

**1. Clonar**

```powershell
git clone <url-del-repo> RRHH
cd RRHH
```

**2. `.env` del backend**

```powershell
cp BACK/.env.example BACK/.env
```

Revisa/añade en `BACK/.env`:

```dotenv
APP_TIMEZONE=Europe/Madrid
DB_CONNECTION=mysql
DB_HOST=back_db
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=rrhh
REVERB_APP_KEY=<cualquier cadena aleatoria>
REVERB_APP_SECRET=<cualquier cadena aleatoria>
REVERB_HOST=back_reverb
REVERB_PORT=6001
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=6001
```

**3. `.env` del front** (`FRONT/.env`)

```dotenv
NUXT_PUBLIC_REVERB_APP_ID=rrhh
NUXT_PUBLIC_REVERB_APP_KEY=<el mismo REVERB_APP_KEY del BACK/.env>
```

**4. `.env` de la raíz (opcional)**

```dotenv
# Muestra trazas de error de Laravel (solo en tu PC)
APP_DEBUG=true

# Túnel rápido de Cloudflare para enseñar la app desde fuera (URL aleatoria)
COMPOSE_PROFILES=tunnel
```

**5. Levantar**

```powershell
docker compose up -d --build
```

La primera vez tarda (descarga imágenes, `composer install` y `npm install`).

**6. Base de datos (solo la primera vez)**

```powershell
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

`--seed` carga la **empresa demo y datos de prueba**. El super admin de pruebas es `superadmin@rrhh.test` / `SuperAdmin1234!`.

**7. Abrir**

| URL | Qué es |
|-----|--------|
| http://localhost:3000 | Front (acceso directo, recarga en caliente) |
| http://localhost:8080 | Front a través del gateway (igual que en producción) |
| http://localhost:8000/api | API de Laravel |
| URL `*.trycloudflare.com` | Si activaste el túnel: `docker compose logs tunnel \| Select-String trycloudflare` |

---

## 4. Producción (PC servidor)

En producción **solo existe un `.env`, el de la raíz**. No crees `BACK/.env` ni `FRONT/.env`.

### Paso a paso

**1. Clonar el repo en el servidor**

```powershell
git clone <url-del-repo> RRHH
cd RRHH
```

**2. Crear el `.env` desde la plantilla**

```powershell
cp .env.production.example .env
```

**3. Primer arranque (sin `-d`, para ver las credenciales)**

```powershell
docker compose up --build
```

El servicio `env-check` se ejecuta primero. Como el `.env` está vacío, **para el arranque** y muestra algo así:

```
==================================================================
 RRHH - el .env de produccion esta incompleto
==================================================================

Copia estas lineas en el .env (valores aleatorios generados ahora):

APP_KEY=base64:If8Ly9ZuCVZ+On/cXYirjWrX3xIrj77PjhzmWdfuzAc=
DB_PASSWORD=320d65bd8b51ebdc57ba49d678cd653517b94f3a9e6bc964
DB_ROOT_PASSWORD=c69c0b025717760e05883acd4a4b850698018e37c0b2d18f
REVERB_APP_KEY=396f712a77d6c8509f25970da2724be7
REVERB_APP_SECRET=67f8609e82ccf59476633776d5f943d2...
SEED_SUPERADMIN_PASSWORD=8c1f0e5a9b2d4c7e6f3a1b0c

Completa a mano:
  - SEED_SUPERADMIN_EMAIL
  - CLOUDFLARE_TUNNEL_TOKEN
==================================================================
```

> Si lo lanzaste con `-d` solo verás `service "env-check" didn't complete successfully`. Las credenciales se ven con `docker compose logs env-check`.

**4. Pegar las credenciales en el `.env`**

- Copia las líneas generadas y **sustituye** las vacías del `.env` (`APP_KEY=`, `DB_PASSWORD=`, …).
- Rellena a mano lo que pida (email del super admin y, si usas dominio, el token del túnel; ver [sección 6](#6-dominio-propio-p-ej-rrhhnanodevcom)).
- Si todavía **no tienes dominio**, usa el túnel rápido. En el `.env` deja:

  ```dotenv
  COMPOSE_PROFILES=tunnel
  PUBLIC_HOSTS=.trycloudflare.com
  APP_URL=
  ```

  Y borra la línea `CLOUDFLARE_TUNNEL_TOKEN=`.

> **Guarda una copia del `.env`** en un gestor de contraseñas. Si lo pierdes, no podrás descifrar sesiones ni entrar a la base de datos.

**5. Arrancar de verdad**

```powershell
docker compose up -d --build
```

Orden en el que arranca todo:

1. `env-check`: comprueba el `.env` (ahora sale `.env de produccion OK`).
2. `back_db`: MySQL. La primera vez crea la base con tus contraseñas (tarda ~30 s).
3. `migrate`: hace `composer install`, ejecuta las migraciones y, **solo la primera vez**, crea el super admin y los catálogos iniciales (festivos nacionales, tipos de documento). **Nunca carga datos demo.**
4. `app`, `back_reverb`, `queue`, `front`, `gateway`, `tunnel`.

**6. Comprobar**

```powershell
docker compose ps                 # todo "Up"; env-check y migrate "Exited (0)"
docker compose logs migrate       # migraciones aplicadas / "super admin created"
```

**7. Entrar**

- Con dominio: `https://rrhh.nanodev.com`
- Con túnel rápido: `docker compose logs tunnel | Select-String trycloudflare`
- Desde el propio servidor: http://localhost:8080

Inicia sesión con `SEED_SUPERADMIN_EMAIL` / `SEED_SUPERADMIN_PASSWORD` y **cambia la contraseña desde la app**. No se vuelve a sobrescribir en los siguientes arranques.

---

## 5. Variables del `.env` de producción

Las marcadas con ⚙️ las genera `env-check`. Las marcadas con ✍️ las pones tú.

### Docker / túnel

| Variable | Valor | Notas |
|----------|-------|-------|
| `COMPOSE_FILE` | `docker-compose.yml:docker-compose.prod.yml` | Activa el modo producción. No tocar. |
| `COMPOSE_PATH_SEPARATOR` | `:` | Hace que la línea anterior funcione en Windows y Linux. No tocar. |
| `COMPOSE_PROFILES` | `tunnel-domain` o `tunnel` | `tunnel-domain` = dominio propio (URL fija). `tunnel` = URL aleatoria `*.trycloudflare.com`. |
| ✍️ `CLOUDFLARE_TUNNEL_TOKEN` | `eyJ...` | Solo con `tunnel-domain`. Ver [sección 6](#6-dominio-propio-p-ej-rrhhnanodevcom). |
| ✍️ `PUBLIC_HOSTS` | `rrhh.nanodev.com` | Dominio(s) públicos, separados por coma. |

### Laravel

| Variable | Valor | Notas |
|----------|-------|-------|
| `APP_NAME` | `RRHH` | |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **Nunca** `true` en producción: expone trazas y configuración. |
| ✍️ `APP_URL` | `https://rrhh.nanodev.com` | URL pública. |
| ⚙️ `APP_KEY` | `base64:...` | Cifra sesiones y datos. **No cambiarla** una vez en uso. |
| `APP_TIMEZONE` | `Europe/Madrid` | |
| `APP_LOCALE` | `es` | |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `warning` | Logs en `BACK/storage/logs`. |
| `SESSION_ENCRYPT` | `true` | |
| `CORS_ALLOWED_ORIGINS` | `https://rrhh.nanodev.com` | |

### Super admin inicial

| Variable | Notas |
|----------|-------|
| ✍️ `SEED_SUPERADMIN_EMAIL` | Tu email de acceso. |
| ⚙️ `SEED_SUPERADMIN_PASSWORD` | Contraseña inicial. Solo se usa en el primer arranque. |

### Base de datos

| Variable | Notas |
|----------|-------|
| `DB_DATABASE` / `DB_USERNAME` | `laravel` / `laravel` (se pueden dejar). |
| ⚙️ `DB_PASSWORD` | Contraseña del usuario de la app. |
| ⚙️ `DB_ROOT_PASSWORD` | Contraseña de root de MySQL. |

> ⚠️ MySQL guarda las contraseñas **al crear el volumen** (primer arranque). Si las cambias después en el `.env`, la app dejará de conectar. Ver [Problemas frecuentes](#10-problemas-frecuentes).

### WebSockets (Reverb)

| Variable | Notas |
|----------|-------|
| `BROADCAST_CONNECTION` | `reverb` |
| `REVERB_APP_ID` | `rrhh` |
| ⚙️ `REVERB_APP_KEY` | Clave pública (la usa el navegador). |
| ⚙️ `REVERB_APP_SECRET` | Secreto del servidor. |
| `NUXT_PUBLIC_REVERB_APP_ID` / `NUXT_PUBLIC_REVERB_APP_KEY` | Ya apuntan a `${REVERB_APP_ID}` / `${REVERB_APP_KEY}`. No tocar. |

### Front

| Variable | Notas |
|----------|-------|
| `NUXT_COOKIE_SECURE` | `true` (se sirve por HTTPS). |

### Mail

| Variable | Notas |
|----------|-------|
| `MAIL_MAILER` | `log` (los mails se escriben en el log) hasta tener proveedor. Luego `smtp`. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | Datos de tu proveedor SMTP. |
| `MAIL_FROM_ADDRESS` | p. ej. `no-reply@nanodev.com` |

---

## 6. Dominio propio (p. ej. `rrhh.nanodev.com`)

Con un túnel con nombre, la URL es fija, tienes HTTPS automático y **no hace falta abrir puertos** en el router del servidor.

1. **Compra el dominio** (`nanodev.com`) en cualquier registrador.
2. **Añádelo a Cloudflare** (plan gratis): *Add a site* → sigue los pasos y cambia los **nameservers** en tu registrador por los que te da Cloudflare. Puede tardar unas horas en activarse.
3. **Crea el túnel**: Cloudflare → *Zero Trust* → *Networks* → *Tunnels* → *Create a tunnel* → tipo **Cloudflared** → nombre `rrhh`.
4. En la pantalla de instalación, **copia el token**: el texto largo que empieza por `eyJ` dentro del comando que te muestra. No hace falta instalar nada, ya va en Docker.
5. **Public hostname** del túnel:
   - Subdomain: `rrhh` · Domain: `nanodev.com`
   - Service: **HTTP** · URL: `gateway:80`
6. En el `.env` del servidor:

   ```dotenv
   COMPOSE_PROFILES=tunnel-domain
   CLOUDFLARE_TUNNEL_TOKEN=eyJ...
   PUBLIC_HOSTS=rrhh.nanodev.com
   APP_URL=https://rrhh.nanodev.com
   CORS_ALLOWED_ORIGINS=https://rrhh.nanodev.com
   ```

7. `docker compose up -d --build` y abre `https://rrhh.nanodev.com`.

Para más proyectos en el mismo dominio, crea otro *Public hostname* (`otro.nanodev.com`) en su propio túnel o en el mismo.

---

## 7. Actualizar el servidor a una nueva versión

```powershell
cd RRHH
git pull
docker compose up -d --build
```

Con eso basta:

- Se reconstruye el front compilado (con las dependencias nuevas de `package-lock.json`).
- `migrate` instala las dependencias nuevas de `composer.lock` y **ejecuta las migraciones pendientes solo**.

Antes de una actualización con migraciones delicadas, haz una copia de la base de datos (ver [Comandos útiles](#9-comandos-útiles)).

---

## 8. Dependencias: instalar paquetes en front y back

### Front (Nuxt)

> El proyecto usa **npm**, no pnpm: el lockfile es `FRONT/package-lock.json` y la imagen de Docker instala con `npm ci`. Usar `pnpm install` crearía un `pnpm-lock.yaml` distinto que Docker ignora, y las versiones no coincidirían entre tu PC y el servidor.

**Añadir un paquete (en tu PC de desarrollo):**

```powershell
docker compose exec front npm install <paquete>
# o, si tienes Node instalado en tu PC:
cd FRONT; npm install <paquete>
```

Después:

1. Haz commit de `FRONT/package.json` **y** `FRONT/package-lock.json`.
2. Si el contenedor de desarrollo no ve el paquete, reinícialo: `docker compose restart front`. Al arrancar ejecuta `npm install`.

**Tras clonar o hacer `git pull` con paquetes nuevos:**

- Desarrollo: `docker compose restart front`. Ejecuta `npm install` al arrancar.
- Producción: `docker compose up -d --build`. La imagen se reconstruye con `npm ci`.

> Si aparece un error raro de módulos tras cambiar de rama, reinstala desde cero: `docker compose down; docker volume rm rrhh_front_node_modules; docker compose up -d`.

### Back (Laravel)

```powershell
docker compose exec app composer require <paquete>
```

Haz commit de `BACK/composer.json` **y** `BACK/composer.lock`.

- Desarrollo: tras un `git pull` con paquetes nuevos, ejecuta `docker compose exec app composer install`.
- Producción: no hace falta nada, `migrate` ejecuta `composer install` en cada arranque.

---

## 9. Comandos útiles

```powershell
# Estado y logs
docker compose ps
docker compose logs -f                 # todo
docker compose logs -f app front       # algunos servicios
docker compose logs env-check          # credenciales que faltan (producción)
docker compose logs migrate            # resultado de las migraciones (producción)
docker compose logs tunnel | Select-String trycloudflare   # URL del túnel rápido

# Artisan
docker compose exec app php artisan <comando>
docker compose exec app php artisan migrate:status

# Reiniciar / parar
docker compose restart front
docker compose down                    # para todo (los datos se conservan)

# Copia de seguridad de la base de datos (PowerShell)
docker compose exec -T back_db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' > backup.sql

# Restaurar una copia
Get-Content backup.sql | docker compose exec -T back_db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

> ⚠️ `docker compose down -v` **borra la base de datos** (volumen `back_dbdata`). No lo uses en el servidor salvo que quieras empezar de cero.

---

## 10. Problemas frecuentes

| Síntoma | Causa / solución |
|---------|------------------|
| `service "env-check" didn't complete successfully` | Falta algo en el `.env`. `docker compose logs env-check` te dice qué. |
| `service "migrate" didn't complete successfully` | `docker compose logs migrate`. Lo normal es un error de migración o de conexión a la base de datos. |
| `Access denied for user 'laravel'` | Cambiaste `DB_PASSWORD` después del primer arranque. Vuelve a poner la contraseña original o, **solo si no hay datos que conservar**, ejecuta `docker compose down -v` y arranca de nuevo. |
| La web carga pero no llegan notificaciones en tiempo real | Revisa que `REVERB_APP_KEY` sea el mismo para back y front (en producción ya lo es) y `docker compose logs back_reverb`. |
| `Blocked request. This host is not allowed` (desarrollo) | Añade el dominio a `PUBLIC_HOSTS` en el `.env` raíz y ejecuta `docker compose up -d front`. |
| Error 502 en el gateway | Algún servicio no arrancó: `docker compose ps` y los logs del que esté caído. |
| Las fotos o documentos no cargan | Falta el enlace de `storage`. En producción lo crea `migrate`. En desarrollo: `docker compose exec app php artisan storage:link`. |
| La URL `trycloudflare` dejó de funcionar | Cambia en cada reinicio del túnel. Vuelve a consultarla en los logs, o usa dominio propio. |
