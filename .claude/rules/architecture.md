# Architecture Patterns

## Business Logic

- **Service Layer**: implemented via Service classes

## Frontend

- **Inertia.js 3 + React 19 + TypeScript**, styled with Tailwind CSS 4 (CSS-first
  config) and built with Vite 8.
- Pages live in `resources/js/pages`, shared UI in `resources/js/components`,
  page shells in `resources/js/layouts`, and client helpers/hooks in
  `resources/js/lib`.
- Use Laravel Wayfinder (`resources/js/routes`) for typed route/URL generation
  instead of hand-written route strings.

## Database

- Every DB structure change → new migration
- Every DB data change → update seeder + factory
- Prefer Eloquent models over raw queries (`DB::` facade)
- Prefer Eloquent relationships over manual joins
- Prefer Eloquent eager loading over lazy loading (N+1 prevention)

---

## Layers

### Controllers
- Thin entry points only: receive request, call service, return response.
- No business logic, no queries, no direct model access.

### Services
- Own all business logic and use-case orchestration.
- Call repositories for data access; never use Eloquent directly.
- Throw named domain exceptions for business rule violations.

### Repositories
- Only layer that directly queries the database.
- Return Eloquent models or collections — no raw arrays.
- Encapsulate all query logic; services must not build queries.

### Models
- Define Eloquent relationships, casts, fillable fields, and scopes.
- No business logic, no service calls, no application workflows.

### Inertia Pages and React Components
- Handle reactive UI state only; no business logic in components.
- Controllers pass data via `Inertia::render(...)` props — components never fetch
  domain data themselves over HTTP.
- Mutations go through Inertia form helpers (`useForm`, `router.post/put/delete`)
  posting to `routes/web.php`/`routes/settings.php` controllers, never to `/api/v1`.

### Exceptions
- Use named domain exceptions for every business rule violation.
- Never throw a generic `\Exception` from business logic.
- Place exception classes in `app/Exceptions/`.

### Form Requests
- Own all HTTP input validation and authorization.
- Controllers must not duplicate validation logic.
- Place in `app/Http/Requests/`; API-specific requests go in `app/Http/Requests/Api/V1/`.

### API Resources
- All JSON output from `/api/v1` endpoints goes through a `JsonResource` in
  `app/Http/Resources/Api/V1/`. Controllers never return raw models, arrays, or
  collections from an API endpoint.
- Successful responses are wrapped in `{"data": ...}` by the resource/collection.
- Validation errors use Laravel's default `{"message": ..., "errors": {...}}` shape.
- Domain exceptions render as `{"message": ..., "error_code": "..."}` via the
  `App\Exceptions\DomainException` contract (`errorCode()`, `httpStatus()`), rendered
  centrally in `bootstrap/app.php`. Never format API errors ad hoc in a controller.

### API Versioning
- All API routes live under the `/api/v1` prefix and the `api.v1.` route-name prefix
  (`routes/api.php`), inside a single `auth:sanctum` + `verified` middleware group.
- A breaking change to a V1 contract requires a new `Api\V2` namespace (controllers,
  requests, resources) rather than mutating V1 behavior in place.
- Every API route must sit inside the `auth:sanctum` group — this is enforced by
  `tests/Feature/Api/V1/ApiFoundationTest.php`.

### Layer boundary (enforced by test)
- `resources/js/{pages,layouts,components}` must never call `/api/v1`, `fetch(`, or
  `axios` — the browser UI uses Inertia web routes and calls Services/Repositories
  in-process via the Laravel controller, exactly like the API controllers do.
- `app/Services` and `app/Http/Controllers` must never build queries directly
  (`DB::`, `::query(`, `->where(`) — only Repositories query the database.
- This is a static assertion in `tests/Feature/Architecture/LayeringTest.php`;
  a change that breaks it is an architecture violation, not a style nit.
