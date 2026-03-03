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
*   **Estructura DDD**: Dominios separados en `src/` (`Identity`, `Lockers`, `Inventory`, `OpenOrders`, `Audit`)
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

**Contrato API (OpenAPI/Swagger):** La especificación completa de la API está en **`docs/openapi.yaml`**. Sirve como contrato entre backend y frontend; hay que mantenerla actualizada al añadir o modificar endpoints (ver `docs/README.md`).

Todos los endpoints (excepto el login) están bajo protección de acceso **JWT Bearer** y bajo el guard de acceso de Roles mediante **Policies (Gates)** definidos en el `AppServiceProvider`.

**URL Base:** `http://localhost:8000/api/v1`

### 1. Autenticación (Identity)
---

**Iniciar Sesión**
- **Método:** `POST`
- **Ruta:** `/auth/login`
- **Acceso:** Público
- **Body:**
```json
{
  "email": "admin@lockerp.com",
  "password": "password123"
}
```
- **Respuesta Exitosa (200):** Devuelve un objeto con el `access_token`.

### 2. Clínicas (Lockers/Clinic)
---

**Obtener Información de la Clínica**
- **Método:** `GET`
- **Ruta:** `/clinic`
- **Acceso:** Todos los usuarios autenticados.
- **Descripción:** Retorna la información de la clínica asignada al usuario actual.

**Actualizar Configuración de la Clínica**
- **Método:** `PATCH`
- **Ruta:** `/clinic/settings`
- **Acceso:** `ADMIN`
- **Body:** Parámetros variables de configuración de la clínica (ej. properties extra de configuración).

### 3. Inventario (Inventory)
---

**Listar Inventario**
- **Método:** `GET`
- **Ruta:** `/inventory`
- **Acceso:** Todos los usuarios autenticados.
- **Query Params (Opcionales):** `?compartment_id=<ULID>`
- **Descripción:** Obtiene los niveles actuales de inventario incluyendo los campos `qty_available` (cantidad disponible) y `qty_reserved` (cantidad reservada).

**Ajustar Producto en Compartimento**
- **Método:** `POST`
- **Ruta:** `/inventory/adjust`
- **Acceso:** `ADMIN`, `RESPONSABLE`
- **Body:**
```json
{
  "compartment_id": "<ULID>",
  "product_id": "<ULID>",
  "qty_available": 10
}
```
- **Descripción:** Ajusta la cantidad total de un producto específico, disponible dentro de un compartimento. Si no existe, lo crea.

### 4. Órdenes (Open Orders)
---

**Listar Órdenes Pendientes/Completadas**
- **Método:** `GET`
- **Ruta:** `/open-orders`
- **Acceso:** Todos los usuarios autenticados.
- **Query Params (Opcionales):** `?status=PENDING`

**Solicitar Orden (Retirar Producto)**
- **Método:** `POST`
- **Ruta:** `/open-orders`
- **Acceso:** `ADMIN`, `RESPONSABLE`
- **Headers:** `Idempotency-Key: <unique-key>` (Opcional, para evitar doble retiro)
- **Body:**
```json
{
  "compartment_id": "<ULID>",
  "product_id": "<ULID>",
  "quantity": 1,
  "external_ref": "mi-llave-unica" 
}
```
> **Nota de Idempotencia**: Si se envía `external_ref` en el body o en la cabecera `Idempotency-Key`, la API devuelve la orden original en lugar de crear un duplicado, si la orden ya fue procesada.
- **Descripción:** Endpoint transaccional e idempotente para sustraer la cantidad solicitada del stock disponible (`qty_available`) y moverlo a stock reservado (`qty_reserved`).

**Confirmar Lectura de Orden (Retiro Efectivo)**
- **Método:** `POST`
- **Ruta:** `/open-orders/{id}/confirm-read`
- **Acceso:** Todos los usuarios autenticados.
- **Body (Opcional):**
```json
{
  "occurred_at": "2024-11-20T10:00:00Z"
}
```
- **Descripción:** Concluye el flujo reduciendo lo reservado por la orden de inventario y marcando el estado final de la orden como `RETIRED`.

### 5. Auditoría (Audit)
---

**Listar Logs de Auditoría**
- **Método:** `GET`
- **Ruta:** `/audit-logs`
- **Acceso:** `ADMIN`, `RESPONSABLE`
- **Descripción:** Lista los logs de auditoría generados automáticamente por acciones del sistema o de los usuarios (ej. solicitar retiros, confirmaciones de órdenes).
