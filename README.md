# RRHH — Gestión de Recursos Humanos

Monorepo con backend **Laravel 12** y frontend **Nuxt 3**, orquestados con **Docker Compose**.

> 📘 **Guía completa para levantar el proyecto (desarrollo y producción), qué poner en cada `.env`, dominio con Cloudflare e instalación de dependencias: [DEPLOY.md](DEPLOY.md).**

---

## Estructura del repositorio

```
RRHH/
├── BACK/               # API REST — Laravel 12 + Sanctum
│   ├── app/
│   ├── routes/api.php  # Endpoints de la API
│   ├── docker/nginx/   # Configuración de Nginx
│   └── Dockerfile
├── FRONT/              # SPA/SSR — Nuxt 3 + Tailwind CSS
│   ├── pages/          # login.vue · register.vue · dashboard.vue
│   ├── layouts/
│   ├── composables/    # useAuth.ts
│   └── Dockerfile
└── docker-compose.yml  # Orquestador principal
```

---

## Stack tecnológico

| Capa       | Tecnología                              |
|------------|-----------------------------------------|
| Backend    | PHP 8.5, Laravel 12, Laravel Sanctum 4  |
| Frontend   | Nuxt 3, Vue 3, Tailwind CSS 3           |
| Base datos | MySQL 8.0                               |
| Servidor   | Nginx 1.25-alpine                       |
| Runtime    | Docker + Docker Compose                 |

---

## Puertos

| Servicio        | Puerto local |
|-----------------|--------------|
| Frontend (Nuxt) | 3000         |
| API (Nginx)     | 8000         |
| MySQL           | 3306         |

---

## API Endpoints

Base URL: `http://localhost:8000/api`

| Método | Ruta         | Auth       | Descripción              |
|--------|--------------|------------|--------------------------|
| POST   | /register    | Pública    | Registrar usuario        |
| POST   | /login       | Pública    | Iniciar sesión (token)   |
| POST   | /logout      | Sanctum    | Cerrar sesión            |
| GET    | /user        | Sanctum    | Datos del usuario actual |
| GET    | /dashboard   | Sanctum    | Stats del dashboard      |
| GET    | /health      | Pública    | Health check             |

---

## Requisitos previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y corriendo
- Puertos **3000**, **8000** y **3306** libres en el host

---

## Cómo ejecutar

### 1. Clonar el repositorio

```bash
git clone <url-del-repo>
cd RRHH
```

### 2. Configurar variables de entorno del backend

```bash
cp BACK/.env.example BACK/.env
```

> Los valores por defecto del `.env.example` ya están alineados con el `docker-compose.yml` (DB, host, etc.). No es necesario modificarlos para desarrollo local.

### 3. Levantar todos los servicios

Desde la raíz del repositorio (`RRHH/`):

```bash
docker compose up -d --build
```

Esto construye las imágenes de Laravel y Nuxt, levanta MySQL, Nginx y el servidor de desarrollo de Nuxt.

### 4. Inicializar la base de datos (primera vez)

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

### 5. Acceder a la aplicación

| URL                          | Descripción           |
|------------------------------|-----------------------|
| http://localhost:3000        | Frontend (Nuxt)       |
| http://localhost:3000/login  | Página de login       |
| http://localhost:8000/api    | API REST (Laravel)    |

---

## Comandos útiles

```bash
# Ver logs en tiempo real
docker compose logs -f

# Ver logs solo del frontend
docker compose logs -f front

# Ver logs solo del backend
docker compose logs -f app

# Reiniciar un servicio
docker compose restart front
docker compose restart app

# Ejecutar comandos Artisan (siempre dentro del contenedor "app")
docker compose exec app php artisan <comando>

# Crear las carpetas de cada empresa (documents, chat, nominas/<empleado>, avatars)
docker compose exec app php artisan files:folders

# Registrar las nóminas dejadas en companies/{empresa}/nominas/{empleado}/
docker compose exec app php artisan nominas:sync

# Reordenar archivos por empresa / moverlos a S3 (probar antes con --dry-run)
docker compose exec app php artisan files:move --dry-run

# Abrir terminal en el contenedor de Laravel
docker compose exec app bash

# Detener todos los servicios
docker compose down

# Detener y borrar volúmenes (borra la base de datos)
docker compose down -v
```

---

## Archivos subidos

- Se guardan **por empresa, con su nombre** (el slug, fijo desde que se creó la empresa): `companies/nano-tech/documents|nominas|chat|avatars/...`.
- Con la configuración por defecto **no hace falta tocar el `.env`**: en el servidor están en `BACK/storage/app/private/companies/`, y las fotos en `BACK/storage/app/public/companies/`.
- Las carpetas se crean solas al crear una empresa o un empleado. Para las empresas y empleados que ya existían: `docker compose exec app php artisan files:folders`.
- **Nóminas:** cada empleado tiene `companies/{empresa}/nominas/Nombre Apellido - DNI/`. Lo que se deja ahí aparece solo en Documentos → Nóminas.
- Para usar S3, R2, MinIO o Spaces se cambian `FILES_DISK` y `PUBLIC_FILES_DISK` en el `.env`.
- Límite de 10 MB por archivo.

Detalle, migración a S3 y copias de seguridad: [DEPLOY.md → Archivos subidos](DEPLOY.md#11-archivos-subidos-dónde-se-guardan-nóminas-y-cómo-migrarlos).

---

## Ver como (solo lectura)

Desde **Empleados → ⋮ → Ver como**, quien tenga el permiso "Ver como empleado" (RRHH y Admin por defecto) abre la app como otra persona durante 30 minutos:

- **Solo lectura:** el servidor rechaza cualquier cambio.
- **Sin chat privado:** no se ven conversaciones, adjuntos ni mensajes en tiempo real.
- **Auditado:** cada sesión queda registrada con quién, a quién y cuándo.
- RRHH no puede ver como un administrador.

> Informa a los empleados de este acceso en la política de privacidad o el reglamento interno, y que lo revise un asesor legal.

---

## Desarrollo

### Frontend
Los archivos de `FRONT/` están montados como volumen en el contenedor. Cualquier cambio en los `.vue` se refleja automáticamente mediante HMR (Hot Module Replacement).

### Backend
Los archivos de `BACK/` también están montados como volumen. Los cambios en PHP se aplican en la siguiente petición (no requiere reiniciar el contenedor).

---

## Roles y super admin

La app es **multiempresa**. Hay cuatro tipos de usuario:

| Usuario | Qué hace | Dónde entra |
|---------|----------|-------------|
| **Super admin** | Dueño de la plataforma. No pertenece a ninguna empresa: crea, suspende y edita empresas (plan, país…), ve usuarios, errores y auditoría de todas, y puede **"entrar como empresa"** para dar soporte (sesión de 2 h, auditada). | Panel `/admin` |
| **Admin** (de una empresa) | Tiene todos los permisos dentro de su empresa. No se puede borrar. | App normal |
| **RRHH** | Empleados, solicitudes, reportes, documentos, festivos, configuración, "Ver como"… Sus permisos se editan en Configuración → Roles y permisos. | App normal |
| **Empleado** | Ficha, pide vacaciones o permisos, ve sus documentos y nóminas, chatea. | App normal |

- Cada empresa nueva se crea con los roles Admin, RRHH y Empleado. El admin puede crear más roles.
- Una empresa se crea desde el **registro público** (`/register`: su primer usuario es el admin) o la crea el super admin.

## Credenciales

### Desarrollo (`migrate --seed`, empresa "Empresa Demo")

| Usuario | Email | Contraseña |
|---------|-------|------------|
| Super admin | `superadmin@rrhh.test` | `SuperAdmin1234!` |
| Admin de la empresa demo | `admin@rrhh.test` | `Admin1234!` |
| RRHH de la empresa demo | `rrhh@rrhh.test` | `Admin1234!` |
| Empleados de prueba (generados) | aleatorios (ver Empleados) | `password` |

Se pueden cambiar antes de sembrar con `SEED_SUPERADMIN_EMAIL` / `SEED_SUPERADMIN_PASSWORD` y `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` (`BACK/database/seeders/`).

### Producción

- **No hay datos demo ni usuarios de prueba.** El primer arranque crea solo el **super admin**.
- Su email es `SEED_SUPERADMIN_EMAIL`, que lo escribes tú en el `.env`.
- Su contraseña es `SEED_SUPERADMIN_PASSWORD`, que la **genera `env-check`** en el primer `docker compose up`. Si arrancaste con `-d`, la ves con `docker compose logs env-check`. Pégala en el `.env`.
- Entra con esos datos y **cambia la contraseña desde la app**: el seeder no la vuelve a sobrescribir.
- Las empresas y sus admins se crean después, desde `/register` o desde el panel del super admin.

Paso a paso completo en [DEPLOY.md → Producción](DEPLOY.md#4-producción-pc-servidor).
