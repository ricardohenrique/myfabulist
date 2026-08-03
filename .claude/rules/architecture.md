# Architecture Patterns

## Business Logic

- **Service Layer**: implemented via Service classes

## Frontend

- **blade** build with blade

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

### Livewire Components
- Handle reactive UI state only.
- Delegate all data operations to services or repositories.
- No business logic inside component methods.

### Exceptions
- Use named domain exceptions for every business rule violation.
- Never throw a generic `\Exception` from business logic.
- Place exception classes in `app/Exceptions/`.

### Form Requests
- Own all HTTP input validation and authorization.
- Controllers must not duplicate validation logic.
- Place in `app/Http/Requests/`.
