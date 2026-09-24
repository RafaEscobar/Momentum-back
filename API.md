# Momentum API

Referencia del contrato HTTP actual de Momentum. La especificacion mecanica tambien
esta disponible en `docs/openapi.yaml`; este documento explica reglas de negocio,
parametros y respuestas relevantes para integrar un cliente.

## Base y formato

- Base local: `http://localhost:8000/api`
- Base de produccion: `https://<dominio>/api`
- Request y response: `application/json`
- Fechas: `YYYY-MM-DD`
- Timestamps: ISO 8601, por ejemplo `2026-09-24T18:30:00.000000Z`
- Identificadores: enteros positivos.
- Los campos desconocidos se rechazan en los contratos estrictos.
- Salvo `register` y `login`, todos los endpoints requieren autenticacion.

```http
Authorization: Bearer <token-sanctum>
Accept: application/json
Content-Type: application/json
```

El token completo solo se devuelve al registrar o iniciar sesion. El cliente debe
guardarlo como secreto y no incluirlo en logs, URLs ni mensajes de error.

## Codigos de respuesta

| Codigo | Significado |
|---|---|
| `200` | Consulta o actualizacion correcta. |
| `201` | Recurso creado. |
| `204` | Operacion correcta sin cuerpo. |
| `401` | Token ausente, invalido, expirado o revocado. |
| `403` | Accion no autorizada. |
| `404` | Recurso inexistente o no visible para el usuario. |
| `413` | Cuerpo de solicitud mayor al limite configurado. |
| `422` | Error de validacion o regla de negocio. |
| `429` | Limite de solicitudes excedido. |
| `500` | Error interno; en produccion no incluye detalles tecnicos. |

Error simple:

```json
{ "message": "Unauthenticated." }
```

Error de validacion:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

## Paginacion

Los listados paginados usan `page`, devuelven 15 elementos por pagina y conservan
los filtros en los enlaces. `page` debe ser entero positivo y no superar el limite
configurado por el servidor.

```json
{
  "data": [],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": null,
    "last_page": 1,
    "per_page": 15,
    "to": null,
    "total": 0
  }
}
```

## Valores permitidos

| Campo | Valores |
|---|---|
| Project status | `active`, `paused`, `completed`, `archived` |
| Priority | `low`, `medium`, `high`, `critical` |
| Task type | `story`, `task`, `bug`, `improvement` |
| Task status | `backlog`, `todo`, `in_progress`, `blocked`, `done` |
| Sprint status | `planned`, `active`, `completed` |
| Story points | `null`, `1`, `2`, `3`, `5`, `8`, `13` |
| Unfinished action | `backlog`, `next_sprint` |

## Contratos de recursos

### User

```json
{ "id": 1, "name": "Ada", "email": "ada@example.test" }
```

### ProjectSummary

Usado en listados, dashboard y busqueda. No incluye `description`.

```json
{
  "id": 1,
  "name": "Momentum",
  "status": "active",
  "priority": "high",
  "color": "#2563EB",
  "icon": "rocket",
  "progress": 40,
  "tasks_count": 12,
  "created_at": "2026-09-24T18:30:00.000000Z",
  "updated_at": "2026-09-24T18:30:00.000000Z"
}
```

`Project` agrega `description`, `start_date` y `target_date`. `tasks_count` puede
omitirse cuando el endpoint no solicita ese agregado.

### TaskSummary

Usado en listados y busqueda. No incluye `description`.

```json
{
  "id": 10,
  "project_id": 1,
  "sprint_id": 3,
  "title": "Documentar API",
  "type": "task",
  "priority": "high",
  "status": "in_progress",
  "story_points": 3,
  "position": 2,
  "completed_at": null,
  "created_at": "2026-09-24T18:30:00.000000Z",
  "updated_at": "2026-09-24T18:30:00.000000Z"
}
```

`Task` agrega `description` y `tags` cuando la relacion fue cargada. Las tareas del
board agregan contadores de checklist y etiquetas, pero no la descripcion.

### SprintSummary

```json
{
  "id": 3,
  "project_id": 1,
  "name": "Sprint 1",
  "start_date": "2026-09-21",
  "end_date": "2026-10-02",
  "status": "active",
  "planned_points": 21,
  "completed_points": 8,
  "progress_percentage": 38.1,
  "completed_at": null
}
```

`Sprint` agrega `goal`, `created_at` y `updated_at`.

### Tag

```json
{
  "id": 4,
  "name": "backend",
  "color": "#22C55E",
  "created_at": "2026-09-24T18:30:00.000000Z",
  "updated_at": "2026-09-24T18:30:00.000000Z"
}
```

### ChecklistItem

```json
{
  "id": 20,
  "task_id": 10,
  "title": "Agregar ejemplos",
  "is_completed": false,
  "position": 0,
  "created_at": "2026-09-24T18:30:00.000000Z",
  "updated_at": "2026-09-24T18:30:00.000000Z"
}
```

### ProjectNote

El detalle incluye `content` como Markdown crudo. El cliente debe sanitizarlo antes
de renderizar; el listado y la busqueda solo incluyen identificador, proyecto y titulo.

### Activity

```json
{
  "id": 30,
  "project_id": 1,
  "type": "task_status_changed",
  "description": "Estado de Documentar API: todo -> in_progress",
  "metadata": { "from": "todo", "to": "in_progress" },
  "subject": { "id": 10, "type": "task", "label": "Documentar API" },
  "created_at": "2026-09-24T18:30:00.000000Z"
}
```

Tipos: `project_created`, `project_updated`, `task_created`,
`task_status_changed`, `task_completed`, `sprint_created`, `sprint_started` y
`sprint_completed`.

---

# Autenticacion

## Registrar usuario

`POST /register` - publico - responde `201`.

| Campo | Regla |
|---|---|
| `name` | Requerido, string, maximo 255. |
| `email` | Requerido, email unico, normalizado a minusculas, maximo 255. |
| `password` | Requerido, entre 8 y 255, debe coincidir con `password_confirmation`. |
| `password_confirmation` | Requerido por confirmacion. |
| `device_name` | Opcional, maximo 100, sin caracteres de control. |

```json
{
  "name": "Ada",
  "email": "ada@example.test",
  "password": "correct-horse-battery-staple",
  "password_confirmation": "correct-horse-battery-staple",
  "device_name": "Firefox laptop"
}
```

Respuesta: `{ "user": User, "token": "<token>" }`.

## Iniciar sesion

`POST /login` - publico.

Body: `email` requerido, `password` requerido y `device_name` opcional. Responde
`{ "user": User, "token": "<token>" }`. Credenciales incorrectas producen `422`.

## Usuario actual

`GET /user` - responde `{ "user": User }`.

## Cerrar sesion actual

`POST /logout` - revoca solo el token Bearer actual - responde `204`.

## Cerrar todas las sesiones

`POST /logout-all` - body `{ "password": "..." }` - revoca todos los tokens del
usuario si la contrasena actual es correcta - responde `204`.

---

# Proyectos

## Listar proyectos

`GET /projects`

Query opcional: `page`, `status`, `priority`, `search` (maximo 255). La busqueda
se aplica al nombre. Responde una pagina de `ProjectSummary`, ordenada por creacion
descendente.

## Crear proyecto

`POST /projects` - responde `201` con `{ "data": Project }`.

| Campo | Regla |
|---|---|
| `name` | Requerido, maximo 255. |
| `description` | Nullable, maximo 10 000. |
| `status` | Opcional, Project status. |
| `priority` | Opcional, Priority. |
| `color` | Opcional, hexadecimal `#RRGGBB`. |
| `icon` | Nullable, maximo 50. |
| `start_date` | Nullable, `YYYY-MM-DD`. |
| `target_date` | Nullable, `YYYY-MM-DD`, no anterior a `start_date`. |

## Consultar, actualizar y eliminar

| Metodo | Ruta | Resultado |
|---|---|---|
| `GET` | `/projects/{project}` | `{ "data": Project }` |
| `PUT/PATCH` | `/projects/{project}` | Actualiza los campos del contrato de proyecto. |
| `DELETE` | `/projects/{project}` | `204`; elimina recursos dependientes. |

En update los campos son opcionales, pero `name` no puede ser nulo si se envia.

## Estadisticas de proyecto

`GET /projects/{project}/stats`

```json
{
  "progress": 40,
  "story_points": { "total": 20, "completed": 8 },
  "tasks": {
    "total": 10,
    "backlog": 2,
    "todo": 3,
    "in_progress": 2,
    "blocked": 1,
    "done": 2
  },
  "active_sprint": null
}
```

---

# Tareas

## Listar tareas

`GET /projects/{project}/tasks`

Query opcional: `page`, `status`, `priority`, `type`, `sprint_id`, `tag_id` y
`search` (titulo, maximo 255). Sprint y etiqueta deben pertenecer al proyecto o
usuario autenticado. Responde una pagina de `TaskSummary`, ordenada por `position`
y luego `id`.

## Crear tarea

`POST /projects/{project}/tasks` - responde `201` con `{ "data": Task }`.

| Campo | Regla |
|---|---|
| `title` | Requerido, maximo 255. |
| `description` | Nullable, maximo 10 000. |
| `type` | Opcional, Task type. |
| `priority` | Opcional, Priority. |
| `status` | Opcional, Task status. |
| `story_points` | Nullable: 1, 2, 3, 5, 8 o 13. |
| `sprint_id` | Nullable; sprint del mismo proyecto. |
| `position` | Opcional, entero entre 0 y 4 294 967 295. |

## Consultar, actualizar y eliminar

| Metodo | Ruta | Resultado |
|---|---|---|
| `GET` | `/projects/{project}/tasks/{task}` | `{ "data": Task }` |
| `PUT/PATCH` | `/projects/{project}/tasks/{task}` | Actualiza los campos enviados. |
| `DELETE` | `/projects/{project}/tasks/{task}` | `204`. |

Al establecer estado `done`, el servidor fija `completed_at`; al salir de `done`,
lo limpia. El cliente no puede escribir `completed_at` directamente.

## Cambiar estado

`PATCH /projects/{project}/tasks/{task}/status`

```json
{ "status": "in_progress" }
```

Responde `{ "data": Task }` y registra actividad cuando existe un cambio real.

## Cambiar sprint

`PATCH /projects/{project}/tasks/{task}/sprint`

```json
{ "sprint_id": 3 }
```

`sprint_id` debe estar presente y puede ser `null`. Al mover al backlog, el estado
pasa a `backlog`; al asignar una tarea desde backlog a un sprint, pasa a `todo`.

## Reordenar tareas

`PATCH /projects/{project}/tasks/reorder` - entre 1 y 200 elementos.

```json
{
  "tasks": [
    { "id": 10, "status": "todo", "position": 0 },
    { "id": 11, "status": "in_progress", "position": 1 }
  ]
}
```

Todos los IDs deben ser distintos y pertenecer al proyecto. La operacion es
transaccional y devuelve una coleccion de `TaskSummary`.

---

# Backlog y board

## Backlog

`GET /projects/{project}/backlog`

Query opcional: `page`, `priority`, `type`, `search` y `tag_ids[]` (maximo 50 IDs
distintos). Devuelve tareas sin sprint y agrega `meta.story_points_total` para el
conjunto filtrado completo.

## Board

`GET /projects/{project}/board?sprint_id={id}`

`sprint_id` es opcional y debe pertenecer al proyecto. Si se omite, usa el sprint
activo. Incluye tareas del sprint seleccionado y del backlog, agrupadas en:
`backlog`, `todo`, `in_progress`, `blocked` y `done`.

```json
{
  "sprint": null,
  "backlog": [],
  "todo": [],
  "in_progress": [],
  "blocked": [],
  "done": []
}
```

Cada tarea incluye resumen de checklist `{ total, completed }` y etiquetas.

---

# Sprints

## Listar sprints

`GET /projects/{project}/sprints`

Query opcional: `page`, `status` y `search` por nombre, maximo 255. Devuelve una
pagina de `SprintSummary`.

## Crear sprint

`POST /projects/{project}/sprints` - responde `201`.

| Campo | Regla |
|---|---|
| `name` | Requerido, maximo 255, unico dentro del proyecto. |
| `goal` | Nullable, maximo 10 000. |
| `start_date` | Nullable, `YYYY-MM-DD`. |
| `end_date` | Nullable, no anterior a `start_date`. |
| `status` | Opcional: `planned`, `active`, `completed`. |

Solo puede existir un sprint activo por proyecto.

## Consultar, actualizar y eliminar

| Metodo | Ruta | Resultado |
|---|---|---|
| `GET` | `/projects/{project}/sprints/{sprint}` | `{ "data": Sprint }` |
| `PUT/PATCH` | `/projects/{project}/sprints/{sprint}` | Actualiza campos y ejecuta transiciones de estado. |
| `DELETE` | `/projects/{project}/sprints/{sprint}` | `204`; sus tareas vuelven a no tener sprint. |

Actualizar a `active` usa el flujo de activacion. Actualizar a `completed` completa
el sprint y envia tareas pendientes al backlog.

## Iniciar sprint

`POST /projects/{project}/sprints/{sprint}/start`

Activa un sprint planificado. Rechaza la operacion si ya existe otro sprint activo.

## Completar sprint

`POST /projects/{project}/sprints/{sprint}/complete`

| Campo | Regla |
|---|---|
| `unfinished_action` | Requerido: `backlog` o `next_sprint`. |
| `next_sprint_id` | Requerido solo para `next_sprint`; mismo proyecto, distinto y no completado. |
| `include_completed_tasks` | Boolean opcional, por defecto `false`. |

Respuesta:

```json
{
  "sprint": {},
  "summary": {
    "planned_points": 21,
    "completed_points": 13,
    "completed_tasks": 4,
    "unfinished_tasks": 2
  },
  "moved_tasks": [],
  "completed_tasks": []
}
```

`completed_tasks` solo aparece cuando se solicita. Un sprint ya completado produce
`422`; la operacion completa es transaccional.

---

# Etiquetas

| Metodo | Ruta | Body/resultado |
|---|---|---|
| `GET` | `/tags` | Coleccion completa del usuario, ordenada por nombre. |
| `POST` | `/tags` | `{ "name": "backend", "color": "#22C55E" }`, responde `201`. |
| `PUT/PATCH` | `/tags/{tag}` | Actualiza `name` o `color`. |
| `DELETE` | `/tags/{tag}` | `204`; desasocia la etiqueta de las tareas. |

El nombre tiene maximo 50 caracteres y es unico por usuario. El color debe ser
`#RRGGBB`.

## Sincronizar etiquetas de tarea

`PUT /tasks/{task}/tags`

```json
{ "tag_ids": [1, 4, 8] }
```

`tag_ids` debe estar presente, acepta un array vacio, maximo 50 IDs distintos y
todos deben pertenecer al usuario. Reemplaza por completo las asociaciones actuales.

---

# Checklist

## Crear elemento

`POST /tasks/{task}/checklist` - body `title` requerido, maximo 255, y `position`
opcional - responde `201`.

## Actualizar elemento

`PATCH /tasks/{task}/checklist/{checklistItem}` - acepta `title` e `is_completed`.

## Eliminar elemento

`DELETE /tasks/{task}/checklist/{checklistItem}` - responde `204`.

## Reordenar checklist

`PATCH /tasks/{task}/checklist/reorder` - entre 1 y 200 elementos.

```json
{
  "items": [
    { "id": 20, "position": 0 },
    { "id": 21, "position": 1 }
  ]
}
```

Todos los IDs deben ser distintos y pertenecer a la tarea. Devuelve una coleccion
de `ChecklistItem` y hace rollback si cualquier elemento es invalido.

---

# Notas

| Metodo | Ruta | Resultado |
|---|---|---|
| `GET` | `/projects/{project}/notes` | Pagina de notas resumidas, sin `content`. |
| `POST` | `/projects/{project}/notes` | Crea nota y responde `201`. |
| `GET` | `/projects/{project}/notes/{note}` | Detalle con Markdown crudo. |
| `PUT/PATCH` | `/projects/{project}/notes/{note}` | Actualiza titulo o contenido. |
| `DELETE` | `/projects/{project}/notes/{note}` | `204`. |

Body de creacion: `title` requerido, maximo 255; `content` requerido, maximo
50 000. En actualizacion ambos se pueden omitir, pero deben ser strings no nulos
cuando se envian.

---

# Actividad

`GET /projects/{project}/activities`

Query opcional:

- `page`: pagina positiva.
- `type`: uno de los Activity types.
- `date_from`: `YYYY-MM-DD` inclusivo.
- `date_to`: `YYYY-MM-DD` inclusivo y no anterior a `date_from`.

Devuelve 15 actividades por pagina, de mas reciente a mas antigua. `subject` es un
resumen de tarea o sprint y puede ser `null` si el recurso ya no existe.

---

# Dashboard

`GET /dashboard`

Devuelve proyectos activos, sprints activos, resumen global y las 10 actividades
mas recientes del usuario.

```json
{
  "projects": [],
  "active_sprints": [],
  "summary": {
    "active_projects": 0,
    "planned_story_points": 0,
    "completed_story_points": 0,
    "pending_tasks": 0,
    "in_progress_tasks": 0,
    "completed_tasks": 0
  },
  "recent_activity": []
}
```

---

# Busqueda global

`GET /search?q={texto}`

- `q`: requerido, se recortan espacios, entre 2 y 100 caracteres.
- `projects_page`, `tasks_page`, `notes_page`: paginas independientes.
- Busca en nombre/descripcion de proyectos, titulo/descripcion de tareas y
  titulo/contenido de notas.
- Devuelve hasta 10 resultados por tipo.
- Las respuestas son resumidas y nunca incluyen descripciones o contenido de notas.

```json
{
  "projects": [],
  "tasks": [],
  "notes": [],
  "meta": {
    "projects": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 0 },
    "tasks": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 0 },
    "notes": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 0 }
  }
}
```

---

# Metadata

Todos requieren autenticacion y responden `{ "data": [{ "value": "...", "label": "..." }] }`.

| Metodo | Ruta | Contenido |
|---|---|---|
| `GET` | `/meta/task-types` | Tipos de tarea. |
| `GET` | `/meta/task-statuses` | Estados de tarea. |
| `GET` | `/meta/priorities` | Prioridades. |
| `GET` | `/meta/story-points` | Valores Fibonacci permitidos. |
| `GET` | `/meta/project-statuses` | Estados de proyecto. |

---

# Limites y seguridad del contrato

Los limites actuales por minuto son acumulativos cuando una ruta tiene mas de una
capa:

| Grupo | Limite |
|---|---|
| API autenticada general | 120 por usuario o IP. |
| Login | 20 por IP y 5 por combinacion identidad/IP. |
| Registro | 5 por IP y 5 por combinacion identidad/IP. |
| Busqueda | 30 por usuario o IP. |
| Dashboard, board, stats y actividad | 30 por usuario o IP. |
| Reorder y sincronizacion de etiquetas | 20 por usuario o IP. |

Una respuesta `429` indica que el cliente debe respetar la ventana y headers de
rate limiting. El cuerpo maximo por defecto es 1024 KiB.

Todos los recursos se limitan al propietario autenticado. Las rutas anidadas validan
la relacion entre padre e hijo. Un recurso ajeno normalmente se oculta mediante
`404`; el cliente no debe inferir su existencia.

## Ejemplo cURL

```bash
curl --request GET \
  --url https://api.dominio.com/api/projects?page=1 \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer TOKEN'
```

## Compatibilidad

La API aun no usa un prefijo de version (`/v1`). Cualquier cambio incompatible debe
introducir versionado o coordinarse con todos los clientes. Los campos nuevos pueden
agregarse de forma compatible; los clientes deben ignorar campos de respuesta que no
utilicen.
