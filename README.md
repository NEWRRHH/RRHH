# RRHH — Gestión de Recursos Humanos

Monorepo con backend **Laravel 12** y frontend **Nuxt 3**, orquestados con **Docker Compose**.

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
| Backend    | PHP 8.2, Laravel 12, Laravel Sanctum 4  |
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

# Ejecutar comandos Artisan
docker compose exec app php artisan <comando>

# Abrir terminal en el contenedor de Laravel
docker compose exec app bash

# Detener todos los servicios
docker compose down

# Detener y borrar volúmenes (borra la base de datos)
docker compose down -v
```

---

## Desarrollo

### Frontend
Los archivos de `FRONT/` están montados como volumen en el contenedor. Cualquier cambio en los `.vue` se refleja automáticamente mediante HMR (Hot Module Replacement).

### Backend
Los archivos de `BACK/` también están montados como volumen. Los cambios en PHP se aplican en la siguiente petición (no requiere reiniciar el contenedor).

---

## Credenciales por defecto (seeder)

Si el seeder genera un usuario de prueba, las credenciales son:

| Campo    | Valor              |
|----------|--------------------|
| Email    | `admin@rrhh.test`  |
| Password | `password`         |

> Revisar `BACK/database/seeders/` para confirmar los datos del seeder.
