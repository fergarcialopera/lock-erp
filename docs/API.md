# Documentación de la API – Lock ERP

**URL base:** `http://localhost:8000/api/v1`  
**Autenticación:** Todos los endpoints excepto `POST /auth/login` requieren el header `Authorization: Bearer <access_token>`.

La API está orientada a **casos de uso**: las respuestas de listados y detalles vienen **enriquecidas** (relaciones resueltas) para que el frontend pueda pintar vistas con una sola llamada.

---

## Migración Frontend: Endpoints antiguos → nuevos

Usa esta tabla para actualizar las llamadas en el frontend.

| Antes (endpoint antiguo) | Ahora (endpoint nuevo) | Notas |
|--------------------------|------------------------|--------|
| `GET /open-orders` | **`GET /orders`** | Misma función. **Respuesta cambia:** cada ítem incluye `product`, `locker`, `compartment`, `requested_by` (objetos con id, nombre, etc.) en lugar de solo IDs. |
| — | **`GET /orders/{id}`** | **Nuevo.** Detalle de una orden con las mismas relaciones enriquecidas. Usar para vista de detalle sin llamadas extra. |
| `POST /open-orders/{id}/confirm-read` | **`POST /orders/{id}/confirm-read`** | Misma acción. Solo cambia la ruta (de `open-orders` a `orders`). Body y respuestas igual. |
| `GET /inventory` | **`GET /inventory`** | Misma ruta. **Respuesta cambia:** cada ítem incluye `product`, `compartment` y `locker` (objetos con id, nombre, código, etc.) en lugar de solo `compartment_id` y `product_id`. |
| `GET /lockers/{id}/compartments` | **`GET /lockers/{id}`** | **Eliminado** `/lockers/{id}/compartments`. El detalle del locker **`GET /lockers/{id}`** ya devuelve el array `compartments[]`. Usar solo esta ruta. |
| `GET /dashboard` | **`GET /dashboard`** | Misma ruta. **Respuesta cambia:** `latest_orders` pasa a ser un array de órdenes **enriquecidas** (misma estructura que los ítems de `GET /orders`), no objetos con solo IDs. |

### Resumen de cambios por recurso

- **Órdenes:** Sustituir todas las referencias a `/open-orders` por **`/orders`**. Añadir uso de **`GET /orders/{id}`** para la vista de detalle.
- **Inventario:** No cambiar la URL de `GET /inventory`; adaptar el código a la nueva forma de la respuesta (objetos `product`, `compartment`, `locker`).
- **Lockers:** Dejar de llamar a `GET /lockers/{id}/compartments`; usar solo **`GET /lockers/{id}`** (ya incluye `compartments`).
- **Dashboard:** No cambiar la URL; adaptar el uso de `latest_orders` a la nueva estructura enriquecida.

---

## Endpoints actuales (v1)

### Auth
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/auth/login` | Login (público). Body: `{ "email", "password" }`. Devuelve `access_token`. |
| POST | `/auth/logout` | Cerrar sesión (Bearer). |

### Clinic
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/clinic` | Datos de la clínica del usuario. |
| PATCH | `/clinic/settings` | Actualizar configuración (ADMIN). |

### Dashboard
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/dashboard` | Resumen: contadores, `has_low_stock`, `latest_orders` (enriquecidos). |

### Orders (órdenes de retiro)
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/orders` | Listado de órdenes para vista. Query: `?status=PENDING` \| `RETIRED`. Respuesta enriquecida. |
| GET | `/orders/{id}` | Detalle de una orden. Respuesta enriquecida. |
| POST | `/orders/{id}/confirm-read` | Confirmar lectura/retiro. Body opcional: `{ "occurred_at": "ISO8601" }`. |

*Las órdenes se crean al retirar desde inventario: `POST /inventory/remove`.*

### Inventory
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/inventory` | Listado para vista. Query opcional: `?compartment_id=<ULID>`. Respuesta enriquecida. |
| POST | `/inventory/adjust` | Ajustar stock (ADMIN/RESPONSABLE). |
| POST | `/inventory/add` | Añadir unidades (ADMIN/RESPONSABLE). |
| POST | `/inventory/remove` | Retirar unidades y crear orden PENDING (ADMIN/RESPONSABLE). |
| DELETE | `/inventory/{id}` | Eliminar entrada de inventario (ADMIN/RESPONSABLE). |

### Lockers
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/lockers` | Listado. Query: `?active_only=true` (por defecto). |
| GET | `/lockers/{id}` | Detalle del locker **incluyendo** `compartments[]`. |
| POST | `/lockers` | Crear locker (ADMIN/RESPONSABLE). |
| PATCH | `/lockers/{id}` | Actualizar locker. |
| DELETE | `/lockers/{id}` | Desactivar locker. |

### Compartments, Products, Users, Audit
Sin cambios de rutas respecto a la versión anterior.  
CRUD estándar: `GET/POST /compartments`, `GET/PATCH/DELETE /compartments/{id}`; igual para `products` y `users`.  
Auditoría: `GET /audit-logs`.

---

## Formato de respuestas enriquecidas

### GET /orders (cada ítem del array)
```json
{
  "id": "<ULID>",
  "status": "PENDING",
  "quantity": 2,
  "requested_at": "2024-01-15T10:00:00.000000Z",
  "read_at": null,
  "external_ref": null,
  "created_at": "2024-01-15T10:00:00.000000Z",
  "product": { "id": "<ULID>", "sku": "SKU-001", "name": "Producto A", "barcode": "123" },
  "locker": { "id": "<ULID>", "code": "L1", "name": "Locker Norte" },
  "compartment": { "id": "<ULID>", "code": "A01" },
  "requested_by": { "id": "<ULID>", "name": "Usuario", "email": "user@example.com" }
}
```

### GET /orders/{id}
Misma estructura que un ítem de la lista, con campos adicionales si los hay (p. ej. `meta`, `updated_at`).

### GET /inventory (cada ítem del array)
```json
{
  "id": "<ULID>",
  "qty_available": 10,
  "qty_reserved": 2,
  "updated_at": "2024-01-15T10:00:00.000000Z",
  "product": { "id": "<ULID>", "sku": "SKU-001", "name": "Producto A", "barcode": "123" },
  "compartment": { "id": "<ULID>", "code": "A01" },
  "locker": { "id": "<ULID>", "code": "L1", "name": "Locker Norte" }
}
```

### GET /lockers/{id}
```json
{
  "id": "<ULID>",
  "clinic_id": "<ULID>",
  "code": "L1",
  "name": "Locker Norte",
  "location": null,
  "is_active": true,
  "created_at": "...",
  "updated_at": "...",
  "compartments": [
    { "id": "<ULID>", "locker_id": "<ULID>", "code": "A01", "status": "AVAILABLE", "is_active": true, "created_at": "...", "updated_at": "..." }
  ]
}
```

### GET /dashboard — latest_orders
Cada elemento de `latest_orders` tiene la misma forma que un ítem de **GET /orders** (con `product`, `locker`, `compartment`, `requested_by`).

---

Para el contrato completo en formato OpenAPI (tipos, códigos de respuesta, etc.), ver **`docs/openapi.yaml`**.
