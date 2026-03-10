# Documentación de la API – Lock ERP

**URL base:** `http://localhost:8000/api/v1`  
**Autenticación:** Todos los endpoints excepto `POST /auth/login` requieren el header `Authorization: Bearer <access_token>`.

La API está orientada a **casos de uso**: las respuestas de listados y detalles vienen **enriquecidas** (relaciones resueltas) para que el frontend pueda pintar vistas con una sola llamada.

---

## Migración Frontend: Endpoints antiguos → actuales

| Antes | Ahora | Notas |
|-------|--------|--------|
| `GET /open-orders` o `GET /orders` | **`GET /dispenses`** | Listado de dispensaciones (retiradas desde locker) con `product`, `locker`, `compartment`, `requested_by` enriquecidos. |
| `GET /orders/{id}` | **`GET /dispenses/{id}`** | Detalle de una dispensación. |
| `POST /orders/{id}/confirm-read` | **`POST /dispenses/{id}/confirm-read`** | Confirmar lectura/retiro de la dispensación. |
| `GET /inventory` | **`GET /inventory`** | Misma ruta. Cada ítem incluye `product`, `compartment`, `locker`. |
| `GET /lockers/{id}/compartments` | **`GET /lockers/{id}`** | Eliminado el subrecurso; el detalle del locker incluye `compartments[]`. |
| `GET /dashboard` | **`GET /dashboard`** | Misma ruta. Respuesta usa `pending_dispenses_count` y `latest_dispenses` (en lugar de `pending_orders_count` / `latest_orders`). |

**POST /inventory/remove:** La respuesta incluye `dispense` (no `order`) con el registro de dispensación creado.

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
| GET | `/dashboard` | Resumen: `active_products_count`, `available_lockers_count`, `pending_dispenses_count`, `has_low_stock`, `latest_dispenses` (enriquecidos). |

### Dispenses (dispensación/retirada desde locker)
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/dispenses` | Listado de dispensaciones para vista. Query: `?status=PENDING` \| `RETIRED`. Respuesta enriquecida. |
| GET | `/dispenses/{id}` | Detalle de una dispensación. Respuesta enriquecida. |
| POST | `/dispenses/{id}/confirm-read` | Confirmar lectura/retiro. Body opcional: `{ "occurred_at": "ISO8601" }`. |

*Las dispensaciones se crean al retirar desde inventario: `POST /inventory/remove`.*

### Inventory
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/inventory` | Listado para vista. Query opcional: `?compartment_id=<ULID>`. Respuesta enriquecida. |
| POST | `/inventory/adjust` | Ajustar stock (ADMIN/RESPONSABLE). |
| POST | `/inventory/add` | Añadir unidades (ADMIN/RESPONSABLE). |
| POST | `/inventory/remove` | Retirar unidades y crear dispensación PENDING (ADMIN/RESPONSABLE). Respuesta: `dispense` + `compartment_inventory`. |
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
CRUD estándar: `GET/POST /compartments`, `GET/PATCH/DELETE /compartments/{id}`; igual para `products` y `users`.  
Auditoría: `GET /audit-logs`.

---

## Formato de respuestas enriquecidas

### GET /dispenses (cada ítem del array)
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

### GET /dispenses/{id}
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

### GET /dashboard — latest_dispenses
Cada elemento de `latest_dispenses` tiene la misma forma que un ítem de **GET /dispenses** (con `product`, `locker`, `compartment`, `requested_by`).

---

Para el contrato completo en formato OpenAPI (tipos, códigos de respuesta, etc.), ver **`docs/openapi.yaml`**.
