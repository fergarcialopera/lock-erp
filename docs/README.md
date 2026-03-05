# Documentación API

## Contrato OpenAPI

El fichero **`openapi.yaml`** define el contrato entre el backend (Laravel) y el frontend en formato OpenAPI 3.0.

- **Ubicación:** `docs/openapi.yaml`
- **Uso:** Generación de clientes, mocks, validación de respuestas y documentación (Swagger UI, Redoc, etc.).

### Mantener el contrato actualizado

**Cada vez que se cree o modifique un endpoint en `routes/api.php`:**

1. Actualizar `docs/openapi.yaml`:
   - Añadir o editar el `path` correspondiente.
   - Definir `requestBody` y respuestas (`responses`) con los schemas adecuados.
   - Reutilizar o extender los `components/schemas` existentes si aplica.
2. Si aparece un nuevo tipo de recurso, añadir un schema en `components/schemas` y usarlo en los paths.

### Documentación expuesta (visible para el frontend)

La API expone la especificación OpenAPI para que el frontend pueda consumirla siempre actualizada:

| URL | Descripción |
|-----|-------------|
| `GET /api-docs` | Especificación en YAML (Content-Type: application/x-yaml) |
| `GET /api-docs.json` | Especificación en JSON (para clientes y Swagger UI) |
| `GET /docs` | Interfaz Swagger UI en el navegador |

El frontend puede hacer `GET /api-docs.json` contra la base URL del backend (ej. `http://localhost:8000/api-docs.json`) para obtener el contrato actualizado y generar tipos, clientes o validaciones.

### Servidor base

En el fichero OpenAPI el `servers[0].url` es `/api/v1`. Al usar herramientas (Swagger UI, Postman, etc.) configura la base URL completa (ej. `http://localhost:8000`) para que las peticiones apunten a `http://localhost:8000/api/v1/...`.

### Autenticación

Todos los endpoints excepto `POST /auth/login` requieren el header:

```
Authorization: Bearer <access_token>
```

El token se obtiene con `POST /auth/login` (campos `email` y `password`).
