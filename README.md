# Lock ERP Backend API

Este proyecto es el backend 100% API para un ERP de lockers clínicos. Está diseñado aplicando principios de **Clean Architecture** y **Domain-Driven Design (DDD)** (Arquitectura Hexagonal). 

Al ser una API Pura orientada a negocio transaccional, descarta el monolito tradicional MVC con vistas (Blade) y abstrae toda su lógica de infraestructura.

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-316192?style=for-the-badge&logo=postgresql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![JWT](https://img.shields.io/badge/JWT-Auth-black?style=for-the-badge&logo=JSON%20web%20tokens)

## Tecnologías y Prácticas 🛠️
*   **Laravel 11** (Soporte API Pura)
*   **PostgreSQL 15**
*   **Autenticación JWT** (Stateless Auth - `php-open-source-saver/jwt-auth`)
*   **Docker & Docker Compose** (Nginx + PHP-FPM 8.4 + Postgres)
*   **Estructura DDD**: Dominios separados en `src/` (`Identity`, `Lockers`, `Inventory`, `Dispenses`, `Audit`)
*   **Seguridad y Concurrencia**: Transacciones ACID con bloqueo de fila de datos (`lockForUpdate()`).
*   **Idempotencia**: Claves de idempotencia paramétricas soportadas para endpoints que alteren el Stock.
*   **Identificadores**: Control de Identidad mediante **ULIDs**.

## Guía de Instalación Rápida 🚀

Primero asegurate de tener **Docker Desktop** (o Docker Engine + Compose) instalado y activo.

1. **Levantar contenedores de infraestructura:**
```bash
docker compose up -d --build
```

2. **Instalar Dependencias y Generar Autoload:**
```bash
docker exec lockerp_app composer install
docker exec lockerp_app composer dump-autoload
docker exec lockerp_app php artisan jwt:secret
```

3. **Migrar base de datos y cargar Seeders Iniciales:**
```bash
docker exec lockerp_app php artisan migrate:fresh --seed
```

> **Nota:** El comando de seeder creará automáticamente las entidades iniciales (Clínica, Locker, Compartimentos, Inventario y el usuario de Administración).

### Credenciales de Admin (Generadas por Seeder)
*   **Usuario/Email**: `admin@lockerp.com`
*   **Contraseña**: `password123`

---

## Ejecución de Tests ✅

La suite de pruebas en `tests/Feature/` valida la concurrencia, idempotencia de órdenes y roles:

```bash
docker exec lockerp_app php artisan test
```

---

## Documentación de Endpoints 📜

- **Referencia completa y mapeo para el frontend:** **[`docs/API.md`](docs/API.md)** — Incluye la tabla **antiguos → nuevos endpoints** para migrar las llamadas del frontend y el formato de las respuestas enriquecidas.
- **Contrato OpenAPI:** **`docs/openapi.yaml`** — Especificación OpenAPI 3.0; mantenerla actualizada al cambiar endpoints (ver `docs/README.md`).

Todos los endpoints (excepto el login) requieren **JWT Bearer** y están sujetos a **Policies (Gates)** por rol.

**URL Base:** `http://localhost:8000/api/v1`

### Resumen de rutas principales

| Área | Rutas | Nota |
|------|--------|------|
| **Auth** | `POST /auth/login`, `POST /auth/logout` | Login público; resto con Bearer. |
| **Clinic** | `GET /clinic`, `PATCH /clinic/settings` | Configuración de la clínica. |
| **Dashboard** | `GET /dashboard` | Resumen y `latest_dispenses` enriquecidos (`pending_dispenses_count`). |
| **Dispenses** | `GET /dispenses`, `GET /dispenses/{id}`, `POST /dispenses/{id}/confirm-read` | Dispensaciones/retiradas desde locker listas para vista. Se crean con `POST /inventory/remove`. |
| **Inventory** | `GET /inventory`, `POST /inventory/adjust`, `add`, `remove`, `DELETE /inventory/{id}` | Listado enriquecido (product, compartment, locker). |
| **Lockers** | `GET /lockers`, `GET /lockers/{id}` (incluye `compartments`), CRUD | No usar `/lockers/{id}/compartments` (eliminado). |
| **Products, Users, Compartments** | CRUD estándar | Sin cambios. |
| **Audit** | `GET /audit-logs` | Logs de auditoría. |

Para **mapeo de endpoints y respuestas enriquecidas** (p. ej. `/dispenses`, `latest_dispenses`), ver **[`docs/API.md`](docs/API.md)**.
