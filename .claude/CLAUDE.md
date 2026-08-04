# MyFabulist

Laravel 13, PHP 8.3+, SQLite (default) / MySQL
Folders -> Lists -> Tasks (Wunderlist-style task manager)

Two delivery mechanisms sit on top of one shared application layer: a Livewire web UI
and a versioned JSON REST API (`/api/v1`, Sanctum-authenticated). Neither delivery layer
talks to the other — Livewire components never call the API over HTTP, and both call the
same Services/Repositories directly. See `.claude/rules/architecture.md` for the enforced
invariant (`tests/Feature/Architecture/LayeringTest.php`).

## Structure

```
app/
├── Actions/Fortify/     # Fortify user actions (create user, reset password)
├── Concerns/            # Shared validation-rule traits
├── Exceptions/          # Domain exceptions (extend App\Exceptions\DomainException)
├── Http/
│   ├── Controllers/     # Web controllers + Http/Controllers/Api/V1 (REST API)
│   ├── Requests/        # Form requests; Http/Requests/Api/V1 for the API
│   └── Resources/       # API wire-format transformers (Http/Resources/Api/V1)
├── Listeners/           # Event listeners (e.g. provisioning defaults on registration)
├── Livewire/            # Reactive UI components (server-driven frontend)
│   └── Navigation/      # Sidebar tree + folder/list create-rename-move-delete dialogs
├── Models/              # Eloquent models and domain entities
├── Policies/            # Model-level authorization rules (auto-discovered)
├── Providers/           # Service container bindings and application bootstrapping
├── Repositories/        # Data access layer (database persistence and queries)
│   └── Contracts/       # Repository interfaces the Eloquent implementations bind to
└── Services/            # Business logic and application use cases
    └── Data/            # Immutable DTOs passed between Services and callers

routes/
├── web.php              # Web routes (Livewire pages)
├── api.php              # REST API routes, all under /api/v1, auth:sanctum + verified
├── settings.php         # Livewire-routed settings pages
└── console.php          # Artisan console routes

database/
├── migrations/          # Database schema definitions and versioning
├── seeders/             # Development and test data population
└── factories/           # Model factories for generating test data

tests/
├── Feature/             # HTTP, database, service, repository, and Livewire tests
│   ├── Api/V1/          # REST API endpoint tests
│   └── Architecture/    # Layering rules enforced by static test assertions
└── Unit/                # Isolated tests for individual classes and methods
```

### Execution Flow

```
User
  ↓
/command-plan
  ↓
tech-lead-architect
  ↓
Creates date-plan-title.md
  ↓
User approval
  ↓
/command-implement
  ↓
code-executor
  ↓
Implements plan
  ↓
Runs tests & static analysis
  ↓
/command-review
  ↓
code-reviewer
  ↓
Reviews architecture, security, performance and maintainability
  ↓
Generates date-plan-title-review.md
  ↓
User approval
  ↓
Merge
```

## Architecture
This application follows a layered MVC architecture:

Controllers handle HTTP requests and responses.
Services contain business logic and application use cases.
Repositories encapsulate data access and persistence concerns.
Models represent domain entities and database records.
Livewire Components implement interactive UI behavior.

## Request Flow

Two delivery paths, one application layer:

```
API request  → routes/api.php (auth:sanctum + verified)
             → Form Request (Http/Requests/Api/V1)
             → Controller (Http/Controllers/Api/V1)
             → Service → Repository → Model / Database
             → Http/Resources/Api/V1 → JSON response

Web request  → routes/web.php or Livewire component
             → Service → Repository → Model / Database
             → Blade view
```

Both paths converge on the same Services and Repositories — business logic is never
duplicated between the web and API surfaces.

## Layer Responsibilities
Controllers should be thin and contain no business logic.
Services coordinate business rules and workflows.
Repositories are the only layer that communicates directly with the database.
Models should represent data and relationships, not application workflows.
Business logic should live in Services, not Controllers, Models, or Repositories.
API Resources are the only layer allowed to shape the JSON wire format — controllers
never return raw models or arrays from `/api/v1` endpoints.

See the app/ directory for the complete project structure.

## Tests
Run the full quality gate (config clear, Pint, PHPStan, PHPUnit):
```bash
composer test
```
Run just the test suite:
```bash
php artisan test
```

## Conventions
# Design Principles
- Follow SOLID principles.
- Depend on abstractions, not concrete implementations.
- Keep business logic independent from framework, database, and infrastructure concerns.
- Favor explicit code over magic and hidden behavior.

# Class Design
- Keep classes small and focused on a single responsibility.
- Keep methods short and expressive.
- Use constructor dependency injection.
- Prefer immutable value objects when appropriate.
- Avoid static state and hidden side effects.

# Error Handling
- Use named domain exceptions for business rule violations.
- Never throw generic \Exception for domain logic.
- Fail fast and provide meaningful exception messages.

# Code Quality
- Write code for readability first.
- Favor clarity over cleverness.
- Follow DRY, but avoid premature abstraction.
- Optimize for maintainability rather than brevity.
- Leave the codebase simpler than you found it.

# Database Access
- Access the database through Repositories.
- Keep query logic out of Controllers and Services.
- Encapsulate persistence details behind repository interfaces.
