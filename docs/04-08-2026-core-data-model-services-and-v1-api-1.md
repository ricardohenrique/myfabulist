# Implementation Plan: Core Data Model, Shared Application Layer & v1 JSON API

**Date:** 04-08-2026
**Plan ID:** 1
**Status:** Draft
**Complexity:** Large

## 0. Phase Boundary (read first)

The full MVP (M1–M10 + S1–S7) does not fit in 1–10 deployable steps. This plan is **Phase 1** and is scoped to the layer the rest of the product stands on:

**In Phase 1 (this plan):** the `folders` / `task_lists` / `tasks` schema, models, factories and seeder; the `app/Repositories` + `app/Services` + `app/Exceptions` + `app/Policies` foundation; Sanctum + `/api/v1/*` covering every route suggested in `docs/project-base.md`; Inbox as a first-class default list (S6); Starred as a cross-list query (S2); and just enough Livewire to make `/inbox` and `/starred` render real data with quick-capture, completion and restore (M3–M7 for the Inbox).

**Deferred to Phase 2 ("Folders, Lists & Task UX" — separate plan):** folder/list management UI and the sidebar folder→list tree, drag-and-drop reordering UX (S4 — the `position` column and the reorder *endpoints* land in Phase 1; the JS interaction does not), due-date UI (S1 — the column lands now), notes UI (S3 — the column lands now), search (S5), undo (S7), server-side persistence of the Completed-section collapse state (M6 — client-side `$persist` in Phase 1), smart lists beyond Inbox/Starred (C1), API **token issuance** for mobile clients (see D9 and R11), and OpenAPI/Scramble documentation.

**Why this boundary:** the API contract and the service signatures are the expensive things to get wrong — every UI screen and the future mobile client are cheap to rewrite against a correct application layer, and ruinous to rewrite against a wrong one. The user's stated priority is the data model + service/repository/API layer; UI polish is explicitly secondary. Phase 1 still ends with a genuinely usable product (capture, complete, restore, star in the Inbox), so it is not a "backend-only" release that nobody can see.

**Honest scope admission:** at the end of Phase 1 a user can create folders and lists **only through the API**, not through the web UI. That is deliberate, and it is the single biggest gap against the `docs/project-base.md` "Key acceptance criteria" walkthrough (which begins "create a folder called Work"). Phase 2 closes it.

## 1. Requirements Analysis

### Functional Requirements

- [ ] `folders`, `lists` and `tasks` persist per user with the fields given in the `docs/project-base.md` "Suggested data model" (M8).
- [ ] Folder CRUD (M1): create, rename, delete, list. Deleting a **non-empty** folder must not silently delete its lists — the caller must either confirm cascade deletion or detach the lists first.
- [ ] List CRUD (M2): create, rename, delete, place in a folder or leave ungrouped, move between folders, manual reordering.
- [ ] Quick task creation (M3): title is the only required field; blank/whitespace-only titles are rejected.
- [ ] Task read model (M4/M6): active tasks ordered by `position`, completed tasks below them ordered by **most recently completed first**, with a completed count.
- [ ] Complete / uncomplete (M5): sets and clears `completed_at`; the task is retained, never deleted.
- [ ] Task edit and delete (M7); deleting and completing are distinct operations.
- [ ] Inbox (S6): always exists for every user, never lives in a folder, is the default destination for global capture, cannot be deleted, and tasks can be moved out of it.
- [ ] Starred (S2): binary `is_starred` on a task; `/starred` is a cross-list query over the current user's tasks, not a list or folder.
- [ ] `/api/v1/*` exposes every route listed in `docs/project-base.md` "API Suggestions", plus `GET /api/v1/starred`.
- [ ] `/inbox` and `/starred` stop being placeholders and render real data with working interactions.
- [ ] Empty states (M10) for: no tasks in the Inbox, all tasks complete, no starred tasks, and a save failure.

### Non-Functional Requirements

- [ ] **One application layer, two delivery mechanisms.** Services hold all business rules and are called **in-process** by both Livewire components and API controllers. Livewire must never issue an HTTP request to `/api/v1/*`. Enforced by an automated architecture test, not by convention alone (D1).
- [ ] **Layering.** Repositories are the only code that touches Eloquent query builders. Services never build queries. Controllers (web and API) contain no business logic and no queries.
- [ ] **Security.** Every `/api/v1/*` route is behind `auth:sanctum` + `verified`; every folder/list/task read or write is authorized by a Policy; referenced foreign keys (`folder_id`, `task_list_id`) are ownership-checked, not merely `exists`-checked (IDOR defence); `user_id` is never mass-assignable.
- [ ] **No N+1.** Every collection endpoint eager-loads what its Resource renders; `Model::preventLazyLoading()` is enabled outside production so a regression fails the test suite.
- [ ] **Deployable after every step.** No step leaves a route pointing at a missing controller or a Resource referencing a missing column.
- [ ] **Schema discipline.** Every column ships in a migration, with the factory updated in the same step and the seeder reviewed.
- [ ] **Quality gates.** `composer test` (pint --test, phpstan level 7, `php artisan test`) green after every step.
- [ ] **Strict typing.** `declare(strict_types=1);` in every new PHP file; typed properties, parameters and returns; generics annotated for phpstan level 7.
- [ ] **API stability.** The wire format is owned by API Resources so that internal model changes do not silently break the future mobile client.

## 2. Architecture Review

### Existing Codebase Patterns

- Laravel 13.17, PHP 8.3+ constraint, Livewire 4.1, Flux 2.13.1, Fortify 1.37.2, Blaze 1.0, Chisel 0.1. MySQL.
- `app/` has **no** `Services/`, `Repositories/`, `Exceptions/`, `Policies/`, `Http/Requests/`, `Http/Resources/`, `Listeners/` directories. This plan creates all of them; the base shape established here is the one every later feature copies.
- Existing controllers (`HomeController`, `InboxController`, `StarredController`) are thin invokables. `App\Http\Controllers\Controller` is an empty abstract class — it does **not** use `AuthorizesRequests`, so `$this->authorize()` is currently unavailable.
- `bootstrap/app.php` registers `web`, `commands`, `health` only — **no `api:` key**, so no API routing exists. It already declares `$exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson())`, which this plan reuses rather than duplicates.
- `routes/settings.php` uses `Route::livewire()`; `routes/web.php` uses controllers. Both styles are sanctioned.
- `AppServiceProvider::configureDefaults()` already centralises framework-level defaults (`Date::use(CarbonImmutable::class)`, `DB::prohibitDestructiveCommands`, `Password::defaults`) — the correct home for `Model::preventLazyLoading()` and the `api` rate limiter.
- `App\Models\User` uses attribute-based mass assignment (`#[Fillable([...])]`, `#[Hidden([...])]`) plus a `@property` docblock that phpstan level 7 depends on. New models must follow the same style.
- Fortify's `App\Actions\Fortify\CreateNewUser` creates users; Fortify fires `Illuminate\Auth\Events\Registered`.
- Tests are PHPUnit class-style (not Pest) with `RefreshDatabase`, `test_snake_case_names`, and `declare(strict_types=1);`.
- phpstan level 7 over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`.
- No git history. Every destructive edit is unrecoverable.

### Affected Areas

| Area | Change |
|---|---|
| `database/migrations/` | 3 new tables (+ Sanctum's `personal_access_tokens`) |
| `app/Models/` | new `Folder`, `TaskList`, `Task`; `User` gains relationships + `HasApiTokens` |
| `app/Repositories/` | **new directory** — contracts + Eloquent implementations |
| `app/Services/` | **new directory** — `FolderService`, `TaskListService`, `TaskService` + `Data/` DTOs |
| `app/Exceptions/` | **new directory** — `DomainException` base + 5 named exceptions |
| `app/Policies/` | **new directory** — `FolderPolicy`, `TaskListPolicy`, `TaskPolicy` (auto-discovered) |
| `app/Http/Requests/` | **new directory** — Form Requests owning validation + authorization |
| `app/Http/Resources/Api/V1/` | **new directory** — wire format |
| `app/Http/Controllers/Api/V1/` | **new directory** — thin JSON controllers |
| `app/Http/Controllers/` | `InboxController`, `StarredController` gain a service call |
| `app/Livewire/Tasks/` | **new** — `TaskPanel`, `StarredPanel` |
| `app/Listeners/` | **new** — `CreateDefaultTaskList` |
| `app/Providers/` | `AppServiceProvider` (bindings, rate limiter, lazy-loading guard); new `RepositoryServiceProvider` |
| `bootstrap/app.php` | `api:` routing, `throttleApi()`, domain-exception render hook |
| `routes/api.php` | **new file** |
| `resources/views/inbox.blade.php`, `starred.blade.php` | host Livewire components |
| `database/factories/`, `database/seeders/` | 3 new factories; `DatabaseSeeder` gains demo data |
| `composer.json` | `laravel/sanctum: ^4.0` |

`app/Policies`, `app/Http/Resources`, `app/Listeners` and `app/Repositories/Contracts` are not in the `.claude/CLAUDE.md` directory listing. They are standard Laravel locations and direct expressions of the mandated rules (Policies for authorization; contracts for "encapsulate persistence details behind repository interfaces"). **Action for the executor: update the structure block in `.claude/CLAUDE.md` in Step 2** so the documented tree stays true.

### Reusable Components

- `shouldRenderJsonWhen()` in `bootstrap/app.php` — already routes `api/*` exceptions to JSON. The domain-exception renderer plugs into the same `withExceptions` closure.
- `AppServiceProvider::configureDefaults()` — extend, do not create a parallel bootstrap path.
- `Illuminate\Foundation\Console\ApiInstallCommand` (verified in vendor) — `php artisan install:api` requires `laravel/sanctum:^4.0`, publishes `routes/api.php` from the framework stub, publishes the `personal_access_tokens` migration, and **auto-edits `bootstrap/app.php`** by replacing `web: __DIR__.'/../routes/web.php',` with the same line plus `api: __DIR__.'/../routes/api.php',`. Our `bootstrap/app.php` matches that exact string, so the automatic edit will apply.
- `Illuminate\Foundation\Configuration\ApplicationBuilder::withRouting()` (verified) — `api:` routes are wrapped in `Route::middleware('api')->prefix($apiPrefix)` with `apiPrefix` defaulting to `'api'`. So a `Route::prefix('v1')` group inside `routes/api.php` yields `/api/v1/...`.
- Laravel 13's `api` middleware group (verified in `Configuration/Middleware.php`) contains **only** `SubstituteBindings` unless `statefulApi()` / `throttleApi()` are called. There is **no** default `api` rate limiter anywhere in the framework.
- `UserFactory` states (`unverified()`, `withProfilePhoto()`) — the model for the new factories' state style.
- Existing test style: `tests/Feature/InboxTest.php`.

### Architecture Decision

**D1 — Shared application layer, two thin delivery mechanisms (the core decision).**

```
Livewire component ─┐
                    ├─► Service (all business rules) ─► Repository ─► Eloquent ─► MySQL
API controller ─────┘
```

Services are the single application layer. They contain every business rule, depend only on repository **interfaces** (and other services), and return JSON-serializable results (Eloquent models/collections, or small readonly `*Data` DTOs). Both delivery mechanisms call the **same service methods, in-process**.

Explicitly forbidden: Livewire (or any other server-side code) calling `Http::get('/api/v1/...')`, `Route::dispatch()`, or otherwise re-entering the HTTP stack to reach its own API. Rejected alternatives: (a) *Livewire consumes the API* — doubles latency, loses the DB transaction and the authenticated session, turns a method call into a serialization boundary, and makes every UI bug an HTTP debugging session. (b) *API controllers duplicate business rules* — guarantees drift between web and mobile behaviour. The chosen pattern is standard and is what the user asked for.

Enforcement is automated, not aspirational: `tests/Feature/Architecture/LayeringTest.php` (Step 2) fails the build if `app/Livewire` or `app/Services` references the `Http` facade, if `app/Services` contains query-builder calls (`->where(`, `::query(`, `DB::`), or if `app/Http/Controllers` contains query-builder calls.

**D2 — `List` is a reserved PHP keyword. The model is `TaskList`, the table is `task_lists`, the FK is `task_list_id`.**
`class List` is a fatal parse error in PHP 8.4 — `list` is a reserved word, namespacing does not help. The user-facing vocabulary stays "list": URLs remain `/api/v1/lists/{list}`, route names remain `lists.*`, and implicit route model binding resolves `{list}` against the `TaskList` type-hint (binding matches on parameter *name*, not class name). Rejected alternative: model `TaskList` with `protected $table = 'lists'` and `list_id` — matches the spec's table names but then every relationship, factory and eager-load needs explicit key overrides, and every future developer pays for the mismatch. The table-name deviation from `docs/project-base.md` is deliberate and documented here.

**D3 — Services take the *subject* as a model and *references* as IDs.**
`TaskService::rename(Task $task, string $title)` — the subject is already resolved and Policy-authorized at the boundary. `TaskListService::create(User $user, string $name, ?int $folderId)` — the referenced folder arrives as an ID and the **service** resolves it through `FolderRepository::findForUser()`, throwing `FolderNotFoundException` when it does not exist *or does not belong to the user*. This makes cross-user reference injection impossible regardless of which delivery mechanism calls in, instead of relying on a Form Request rule that Livewire might forget. Form Requests still validate the reference (scoped `Rule::exists`) for a clean 422 instead of a 404, but the service is the guarantee.

**D4 — Return shape: Eloquent models/collections, with API Resources owning the wire format; DTOs only for composites.**
Repositories return models/collections (never arrays, never builders). Services return the same for single-aggregate results. Where a use case returns a **composite**, it returns a small readonly DTO in `app/Services/Data/` — the one case in Phase 1 is `ListedTasks` (`active`, `completed`, `completedCount`), which encodes the M4/M6 read model once and is consumed identically by `TaskPanel` and `TaskListTaskController`. For writes taking ≥3 fields there is one input DTO, `TaskDetailsData` (`title`, `note`, `dueDate`, `isStarred`), built by `UpdateTaskRequest::toData()` on the API side and directly by Livewire. Everything else uses typed scalars. Rejected: (a) *services return arrays everywhere* — loses type safety at phpstan level 7 and forces the JSON shape into the service; (b) *a DTO per entity* — pure mapping overhead for models that already serialize cleanly (YAGNI).

**D5 — Inbox is a real `task_lists` row flagged `is_default`, created by a `Registered` listener with an idempotent lazy fallback.**
Rejected alternatives: (a) *Inbox = tasks with `task_list_id = null`* — every task query grows a null branch and "move a task out of the Inbox" becomes a special case; (b) *a `type` enum column* — more flexible for future smart lists, but Starred/Today are queries, not rows, so the enum would only ever hold two values (YAGNI).
Creation path: `CreateDefaultTaskList` listener on `Illuminate\Auth\Events\Registered` (which Fortify fires) is the primary path; `TaskListService::inboxFor(User): TaskList` is **idempotent and self-healing**, creating the row if it is missing. The fallback exists because model factories do not fire `Registered`, so tests and the existing seeded user would otherwise have no Inbox — and it removes the need for a data-backfill migration. Every read path that needs the Inbox goes through `inboxFor()`; `TaskListRepository::createDefaultFor()` is the single creation point.
The Inbox cannot be deleted (`DefaultTaskListCannotBeDeletedException`) and cannot be moved into a folder (`folder_id` forced to null in the service).

**D6 — Folder deletion (M1): three named operations, one endpoint, one Form Request choosing the strategy.**
`FolderService::delete(Folder)` throws `FolderNotEmptyException` (HTTP 409) when the folder still holds lists. `FolderService::deleteWithLists(Folder)` cascades explicitly. `FolderService::detachLists(Folder)` moves lists to ungrouped. `DELETE /api/v1/folders/{folder}` accepts an optional validated `lists=detach|delete`; absent it, a non-empty folder returns 409 with `error_code: folder_not_empty` — which is exactly the "confirm or move out first" rule expressed over HTTP. Defence in depth at the schema level: `task_lists.folder_id` is `nullOnDelete()`, so even a raw `DELETE FROM folders` cannot destroy lists.

**D7 — `tasks` carries a denormalized `user_id` (deviation from the spec's data model).**
Without it, `TaskPolicy` must walk `$task->taskList->user_id` (a lazy load per authorization) and the Starred query needs a join through `task_lists`. With it: zero-query task authorization, an index-friendly `(user_id, is_starred)` Starred query, and cheap ownership scoping in the repository. The sync risk is nil in this domain — a task's owner is immutable, because a task can only move between lists **belonging to the same user** (enforced in `TaskService::move()`), and lists never change owner. The column is set once at creation from the list's owner and never written again. `user_id` is not mass-assignable on any of the three models.

**D8 — API versioning: `Route::prefix('v1')` in a single `routes/api.php`, controllers namespaced `App\Http\Controllers\Api\V1\`, route names `api.v1.*`.**
The namespace is what actually enables a v2 to coexist; the route file split can happen when there is a second version (YAGNI). Rejected: header/content-negotiation versioning — invisible in `route:list`, harder for a mobile client to pin, and unnecessary at v1.

**D9 — Sanctum is installed now; token *issuance* is deferred with an explicit reason.**
Install `laravel/sanctum:^4.0` via `php artisan install:api --without-migration-prompt`, add `HasApiTokens` to `User`, and put **every** `/api/v1/*` route behind `auth:sanctum` + `verified` from the first commit. Retrofitting auth onto a shipped API is far more painful than installing it before there are clients. Sanctum's guard falls back to the configured session guard when no bearer token is present, so the browser session authenticates against the API too — which means the API is testable and usable today without any token infrastructure.
**Token issuance (`POST /api/v1/tokens`) is deliberately NOT in this plan.** This app has Fortify two-factor authentication *and* passkeys enabled; a naive email+password token endpoint would hand out a full-access token while bypassing the 2FA challenge — a real authentication-bypass hole, not a theoretical one. Designing token issuance that respects the 2FA/passkey flow (plus token abilities, device naming, revocation UI and rate limiting) is its own plan, and nothing consumes it until the mobile app exists. Recorded as R11.

**D10 — Rate limiting must be configured explicitly.**
`$middleware->throttleApi()` in `bootstrap/app.php` references a *named* limiter `api`. Verified in `ThrottleRequests::resolveMaxAttempts()`: if that limiter is undefined, the middleware throws `MissingRateLimiterException` — i.e. **every API request 500s**. There is no default `api` limiter in Laravel 13. Therefore `RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()))` is registered in `AppServiceProvider` in the *same step*. `statefulApi()` is **not** enabled — no browser client calls the API cross-origin (D1), and enabling it would widen CSRF surface for no benefit.

**D11 — Implicit route model binding + Policies, with `AuthorizesRequests` added to the base controller.**
Route model binding is framework routing infrastructure, not application query logic; treating it as a layering violation would force every controller to hand-roll `find`-or-404. Accepted and documented. Policies (`FolderPolicy`, `TaskListPolicy`, `TaskPolicy`) are auto-discovered by Laravel 11+ naming convention — no registration needed. `App\Http\Controllers\Controller` gains `use AuthorizesRequests;` so `$this->authorize()` is available, which `.claude/rules/laravel.md` explicitly sanctions. Standardisation: **routes with a Form Request authorize in `authorize()`; routes without one authorize via `$this->authorize()` in the controller.** Livewire components call `Gate::authorize()` before every service mutation.

**D12 — Transactions live inside repository methods.**
The two Phase 1 operations needing atomicity — cascade folder deletion and bulk reorder — each fit in a single repository method (`FolderRepository::deleteWithLists()`, `TaskRepository::applyOrder()`), so `DB::transaction()` stays in the persistence layer and out of services. No `TransactionManagerInterface` abstraction is introduced (YAGNI); if a use case ever spans two repositories, that abstraction is the documented next move.

**D13 — `is_completed` + `completed_at` are both kept (per the spec), with the invariant enforced in one place.**
Invariant: `is_completed === (completed_at !== null)`. Only `TaskRepository::markCompleted()` / `markActive()` may write either column, so there is exactly one place for drift to occur, and a unit test asserts it. Considered and rejected: deriving `is_completed` from `completed_at` and dropping the boolean — cleaner (single source of truth) but deviates from the data model the user supplied, and the boolean is what makes the `(task_list_id, is_completed, position)` index useful.

**D14 — Ordering.** Integer `position`, appended as `max(position) + 1` within its scope, with bulk reorder rewriting the whole sequence in one transaction. Scopes: `folders.position` per user; `task_lists.position` per `(user_id, folder_id)`; `tasks.position` per list. Positions are not DB-unique; ties break on `id`, so every ordering clause is `ORDER BY position, id`. Rejected: fractional/LexoRank indexing — solves a contention problem this app does not have.

## 3. Step Breakdown

### Step 1: Schema, models, factories and seeder

- **What:** `folders`, `task_lists` and `tasks` tables with Eloquent models, relationships, factories and demo seed data. Nothing consumes them yet.
- **Where:**
  - `database/migrations/2026_08_04_100000_create_folders_table.php`
  - `database/migrations/2026_08_04_100001_create_task_lists_table.php`
  - `database/migrations/2026_08_04_100002_create_tasks_table.php`
  - `app/Models/Folder.php`, `app/Models/TaskList.php`, `app/Models/Task.php`
  - `app/Models/User.php` (relationships + `@property-read` docblocks)
  - `database/factories/FolderFactory.php`, `TaskListFactory.php`, `TaskFactory.php`
  - `database/seeders/DatabaseSeeder.php`
  - `app/Providers/AppServiceProvider.php` (`Model::preventLazyLoading(! app()->isProduction())`)
- **How:**
  - `folders`: `id`, `foreignId('user_id')->constrained()->cascadeOnDelete()`, `string('name')`, `unsignedInteger('position')->default(0)`, timestamps. Index `['user_id', 'position']`.
  - `task_lists`: `id`, `user_id` (cascade), `foreignId('folder_id')->nullable()->constrained()->nullOnDelete()`, `string('name')`, `boolean('is_default')->default(false)`, `unsignedInteger('position')->default(0)`, timestamps. Indexes `['user_id', 'folder_id', 'position']` and `['user_id', 'is_default']`.
  - `tasks`: `id`, `user_id` (cascade, see D7), `foreignId('task_list_id')->constrained()->cascadeOnDelete()`, `string('title')`, `text('note')->nullable()`, `boolean('is_completed')->default(false)`, `timestamp('completed_at')->nullable()`, `boolean('is_starred')->default(false)`, `date('due_date')->nullable()`, `unsignedInteger('position')->default(0)`, timestamps. Indexes `['task_list_id', 'is_completed', 'position']`, `['user_id', 'is_starred']`, `['user_id', 'due_date']`.
  - Models follow the `User` style: `#[Fillable([...])]` attribute, `@property` docblocks for phpstan level 7, `casts()` method. **`user_id` is excluded from every `#[Fillable]`** — it is always set from the authenticated user in the service/repository.
  - Casts: `'is_completed' => 'boolean'`, `'is_starred' => 'boolean'`, `'completed_at' => 'immutable_datetime'`, `'due_date' => 'immutable_date'`, `'is_default' => 'boolean'`. (`Date::use(CarbonImmutable::class)` is already active.)
  - Relationships: `User hasMany Folder|TaskList|Task`; `Folder belongsTo User`, `hasMany TaskList`; `TaskList belongsTo User`, `belongsTo Folder` (nullable), `hasMany Task`; `Task belongsTo User`, `belongsTo TaskList`. Annotate relation generics with **two** type parameters (`HasMany<Task, TaskList>`) — Laravel 11+/Larastan 3 requires it.
  - No business logic and no scopes that encode workflow on the models (`.claude/rules/architecture.md`). Simple ordering scopes are acceptable but the repository is the preferred home; keep models data-only in this step.
  - `DatabaseSeeder`: keep the existing Test User, then seed the acceptance-criteria walkthrough — an Inbox list (`is_default`), a "Work" folder with a "Website launch" list holding 5 tasks (one completed, one starred), and an ungrouped "Groceries" list. Uses factories only.
  - `Model::preventLazyLoading(! app()->isProduction())` goes in `configureDefaults()`. Verify the existing suite still passes — if any starter-kit path (passkeys, Fortify two-factor) lazy-loads, scope the guard rather than delete it, and record which path forced it.
- **Test:**
  - `tests/Feature/Models/TaskRelationshipsTest.php` — a user's folder → list → task chain persists and reads back; deleting a folder leaves its lists with `folder_id = null`; deleting a list deletes its tasks; deleting a user removes all three.
  - Factory smoke test: each factory + each state produces a valid persisted row.
  - `php artisan migrate:fresh --seed` succeeds; `php artisan migrate:rollback` reverses all three migrations cleanly (drop order: tasks → task_lists → folders).
- **Complexity:** Medium

### Step 2: Application-layer foundation — repositories, exceptions, policies, layering test

- **What:** The `app/Repositories`, `app/Exceptions` and `app/Policies` directories, container bindings, and the automated architecture test that keeps Step 8 honest. Still no services and no HTTP surface.
- **Where:**
  - `app/Repositories/Contracts/{FolderRepositoryInterface,TaskListRepositoryInterface,TaskRepositoryInterface}.php`
  - `app/Repositories/{EloquentFolderRepository,EloquentTaskListRepository,EloquentTaskRepository}.php`
  - `app/Providers/RepositoryServiceProvider.php` + registration in `bootstrap/providers.php`
  - `app/Exceptions/DomainException.php` + `FolderNotEmptyException`, `FolderNotFoundException`, `DefaultTaskListCannotBeDeletedException`, `InvalidTaskTitleException`, `TaskReorderMismatchException`
  - `app/Policies/{FolderPolicy,TaskListPolicy,TaskPolicy}.php`
  - `app/Http/Controllers/Controller.php` (add `use AuthorizesRequests;`)
  - `.claude/CLAUDE.md` (add `Policies/`, `Http/Resources/`, `Listeners/`, `Repositories/Contracts/` to the structure block)
  - `tests/Feature/Architecture/LayeringTest.php`
- **How:**
  - **Interfaces** define only what Phase 1 needs — no speculative CRUD. Suggested shape:
    - `FolderRepositoryInterface`: `allForUser(User): Collection` (with `taskLists` eager-loaded), `findForUser(int, User): ?Folder`, `create(User, string, int $position): Folder`, `rename(Folder, string): Folder`, `delete(Folder): void`, `deleteWithLists(Folder): void`, `detachLists(Folder): void`, `hasLists(Folder): bool`, `nextPosition(User): int`, `applyOrder(User, array $ids): void`.
    - `TaskListRepositoryInterface`: `allForUser(User): Collection` (eager `folder`, `withCount('tasks')`), `findForUser(int, User): ?TaskList`, `findDefaultFor(User): ?TaskList`, `createDefaultFor(User): TaskList`, `create(...)`, `update(...)`, `delete(TaskList): void`, `nextPosition(User, ?int $folderId): int`, `applyOrder(...)`.
    - `TaskRepositoryInterface`: `activeForList(TaskList): Collection`, `completedForList(TaskList): Collection`, `starredForUser(User): Collection` (eager `taskList.folder`), `findForUser(int, User): ?Task`, `create(...)`, `update(...)`, `markCompleted(Task): Task`, `markActive(Task): Task`, `setStarred(Task, bool): Task`, `moveToList(Task, TaskList, ?int $position): Task`, `delete(Task): void`, `nextPosition(TaskList): int`, `applyOrder(TaskList, array $ids): void`, `idsForList(TaskList): Collection`.
    - Annotate every collection return as `Collection<int, Model>` for phpstan.
  - **Repositories** are the only place Eloquent query builders appear. `applyOrder()` and `deleteWithLists()` wrap their writes in `DB::transaction()` (D12) — the only sanctioned `DB::` usage in the codebase, and only for transactions, never for raw queries.
  - `markCompleted()`/`markActive()` are the **sole** writers of `is_completed`/`completed_at` (D13): `completed_at = now()` + `is_completed = true`, and `null` + `false` respectively. Completing an already-completed task leaves `completed_at` untouched (idempotent).
  - **`DomainException`**: `abstract class DomainException extends \RuntimeException` with `abstract public function errorCode(): string;` and `public function httpStatus(): int { return 422; }`. `FolderNotEmptyException::httpStatus()` returns `409`; `FolderNotFoundException` returns `404`. Each concrete exception exposes a named static constructor with a meaningful message (e.g. `FolderNotEmptyException::for(Folder $folder)`).
  - **Policies** are query-free: `$user->id === $model->user_id` for all three (possible for `Task` because of D7). Methods: `viewAny`, `view`, `create`, `update`, `delete`; `TaskListPolicy::delete()` additionally returns `false`/denies for the default list as defence in depth behind the service's exception.
  - **`LayeringTest`** — reads every file under the relevant directories and asserts:
    1. no file in `app/Livewire/` or `app/Services/` mentions `Illuminate\Support\Facades\Http` or `Http::`;
    2. no file in `app/Livewire/` contains the string `api/v1`;
    3. no file in `app/Services/` contains `DB::`, `::query(`, `->where(` or `use App\Models\...` query calls;
    4. no file in `app/Http/Controllers/` contains `DB::`, `::query(` or `->where(`;
    5. every class in `app/Repositories/` (excluding `Contracts/`) implements an interface from `app/Repositories/Contracts/`.
    Keep it a plain string/reflection test — no new dependency.
- **Test:** Integration tests per repository under `tests/Feature/Repositories/` using `RefreshDatabase` — happy path, empty result, cross-user isolation (`findForUser` returns `null` for another user's row), `nextPosition` on an empty and a populated scope, `applyOrder` rewriting positions and rolling back on a mismatched ID set, `markCompleted`/`markActive` invariant. Unit tests per policy under `tests/Unit/Policies/` — owner allowed, non-owner denied, default list not deletable. `LayeringTest` green.
- **Complexity:** Large

### Step 3: API foundation — Sanctum, routing, rate limiting, error envelope

- **What:** `/api/v1` exists, is authenticated, is rate-limited, and renders domain exceptions as a consistent JSON envelope. One smoke route proves it end to end.
- **Where:**
  - `composer.json` / `composer.lock` (`laravel/sanctum:^4.0`)
  - `database/migrations/*_create_personal_access_tokens_table.php` (published)
  - `bootstrap/app.php` (`api:` routing, `throttleApi()`, domain-exception renderer)
  - `routes/api.php` (rewritten from the stub)
  - `app/Models/User.php` (`Laravel\Sanctum\HasApiTokens`)
  - `app/Providers/AppServiceProvider.php` (`RateLimiter::for('api', ...)`)
  - `app/Http/Resources/Api/V1/` (created, empty until Step 4)
  - `tests/Feature/Api/V1/ApiFoundationTest.php`
- **How:**
  - Run `php artisan install:api --without-migration-prompt`, then `php artisan migrate`. Verify it added `api: __DIR__.'/../routes/api.php',` to `bootstrap/app.php` (it matches on the exact `web:` line, which our file has); add it by hand if not.
  - Add `HasApiTokens` to `User` (the command prints this reminder; it does not do it).
  - `bootstrap/app.php` → `withMiddleware(fn (Middleware $m) => $m->throttleApi())`. **Do not** call `statefulApi()` (D9/D10).
  - `AppServiceProvider::configureDefaults()` → `RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()))`. This is mandatory, not optional — see D10/R3.
  - `routes/api.php`: replace the stub body with
    `Route::middleware(['auth:sanctum', 'verified'])->prefix('v1')->name('api.v1.')->group(function () { ... });`
    containing only `Route::get('user', ...)` for now. All later steps add routes **inside this group** — never outside it.
  - `bootstrap/app.php` → `withExceptions`: add
    `$exceptions->render(function (DomainException $e, Request $request) { if ($request->is('api/*') || $request->expectsJson()) { return response()->json(['message' => $e->getMessage(), 'error_code' => $e->errorCode()], $e->httpStatus()); } });`
    Web/Livewire callers keep catching the concrete exception themselves (Step 8). Leave the existing `shouldRenderJsonWhen()` in place — it already produces JSON for 403/404/422 on `api/*`.
  - Document the envelope in the plan and in code comments: success `{"data": ...}` (Laravel Resource default), error `{"message": "...", "error_code": "..."}`, validation `{"message": "...", "errors": {...}}`.
- **Test:** `tests/Feature/Api/V1/ApiFoundationTest.php` —
  (a) unauthenticated `GET /api/v1/user` → 401 JSON (not an HTML redirect);
  (b) `Sanctum::actingAs($user)` → 200 with the user payload;
  (c) an unverified user → 403;
  (d) **route guard test**: iterate `Route::getRoutes()` and assert every route whose URI starts with `api/v1` has `auth:sanctum` in its gathered middleware — this test is the tripwire for every later step;
  (e) a request that triggers a domain exception (temporary throwaway route, or defer this assertion to Step 5) returns the error envelope with the right status.
  Confirm no `MissingRateLimiterException` and that `X-RateLimit-Limit` headers are present.
- **Complexity:** Medium

### Step 4: Inbox as a first-class default list (S6)

- **What:** Every user has an Inbox; `GET /api/v1/inbox` returns it with its tasks; the web `InboxController` resolves it through the **same service method**.
- **Where:**
  - `app/Services/TaskListService.php` (new — `inboxFor()` only, for now)
  - `app/Services/TaskService.php` (new — `tasksFor()` only, for now)
  - `app/Services/Data/ListedTasks.php`
  - `app/Listeners/CreateDefaultTaskList.php`
  - `app/Http/Resources/Api/V1/{TaskResource,TaskListResource}.php`
  - `app/Http/Controllers/Api/V1/InboxController.php`
  - `app/Http/Controllers/InboxController.php` (web — pass the inbox id to the view)
  - `routes/api.php`
  - `database/factories/TaskListFactory.php` (add the `inbox()` state if not already added in Step 1)
- **How:**
  - `TaskListService::inboxFor(User $user): TaskList` — `findDefaultFor()` or `createDefaultFor()`; idempotent (D5). `createDefaultFor()` sets `name = 'Inbox'` (translatable at the presentation layer, stored untranslated), `is_default = true`, `folder_id = null`, `position = 0`.
  - `CreateDefaultTaskList` listener on `Illuminate\Auth\Events\Registered`, calling the same service method. Laravel 11+ auto-discovers listeners by type-hint; verify with `php artisan event:list`, and register explicitly in `AppServiceProvider` if discovery is off.
  - `TaskService::tasksFor(TaskList $list): ListedTasks` — composes `activeForList()` + `completedForList()` into the readonly DTO (D4). Active ordered by `position, id`; completed ordered by `completed_at DESC, id DESC` (M6).
  - `TaskResource`/`TaskListResource`: explicit field lists, `whenLoaded()` for relations, `whenCounted()` for `tasks_count`. Dates as ISO-8601 (`completed_at`) and `Y-m-d` (`due_date`). Never expose `user_id`.
  - `Api\V1\InboxController` (invokable): `$list = $service->inboxFor($request->user()); return TaskListResource::make($list)->additional(['data' => ...])` — or, clearer, return a small array assembled from `TaskListResource` + `ListedTasks`; shape: `{"data": {"list": {...}, "tasks": {"active": [...], "completed": [...], "completed_count": n}}}`. Pick one shape here and reuse it for `GET /lists/{list}/tasks` in Step 7.
  - Web `InboxController` calls `TaskListService::inboxFor()` and passes `$taskListId` to `inbox.blade.php`. **This is the demonstration of D1**: the same service method, two delivery mechanisms, zero HTTP between them. The view still renders the placeholder until Step 8 — that is fine and deployable.
  - `routes/api.php`: `Route::get('inbox', InboxController::class)->name('inbox');` inside the v1 group.
- **Test:**
  - `tests/Feature/Services/TaskListServiceInboxTest.php` — `inboxFor()` creates once and returns the same row on the second call; two users get separate Inboxes; a user created via the registration flow already has one.
  - `tests/Feature/Api/V1/InboxTest.php` — 200 with the documented shape; active/completed split and completed ordering correct; another user's tasks never appear; unauthenticated → 401.
  - Update `tests/Feature/InboxTest.php` — the web page still returns 200 and the controller resolved a real Inbox.
- **Complexity:** Medium

### Step 5: Folder CRUD (M1) — service + API

- **What:** Folders are fully manageable over the API, including the non-empty-deletion rule.
- **Where:**
  - `app/Services/FolderService.php`
  - `app/Http/Requests/Api/V1/{StoreFolderRequest,UpdateFolderRequest,DestroyFolderRequest,UpdateFolderOrderRequest}.php`
  - `app/Http/Resources/Api/V1/FolderResource.php`
  - `app/Http/Controllers/Api/V1/{FolderController,FolderOrderController}.php`
  - `routes/api.php`
- **How:**
  - Service: `allFor(User)`, `create(User, string $name)` (position = `nextPosition`), `rename(Folder, string)`, `delete(Folder)` → `FolderNotEmptyException` when `hasLists()`, `deleteWithLists(Folder)`, `detachLists(Folder)`, `reorder(User, array $folderIds)`.
  - Routes (all inside the v1 group): `GET folders`, `POST folders`, `GET folders/{folder}`, `PUT folders/{folder}`, `DELETE folders/{folder}`, `PUT folders/order` — register `folders/order` **before** `folders/{folder}` or constrain the parameter with `->whereNumber('folder')`, otherwise `order` binds as a folder id.
  - `DestroyFolderRequest` validates `lists` ∈ `{detach, delete}` (nullable) and dispatches to the matching service method; absent + non-empty ⇒ the service throws ⇒ 409 `folder_not_empty` via the Step 3 renderer.
  - Form Requests own authorization: `StoreFolderRequest::authorize()` → `Gate::allows('create', Folder::class)`; the rest → `$this->user()->can('update'|'delete', $this->route('folder'))`. `prepareForValidation()` trims `name`; rules `['required','string','max:255']`.
  - `FolderResource` eager-loads via the repository (`with('taskLists')`) and exposes `lists` through `whenLoaded('taskLists')` — note the wire name is `lists` even though the relation is `taskLists` (D2).
  - Status codes: 201 store, 200 update/show/index, 204 destroy, 409 non-empty, 403 foreign folder, 422 validation.
- **Test:** `tests/Feature/Api/V1/FolderTest.php` — CRUD happy paths; blank/oversized name → 422; deleting an empty folder → 204; deleting a non-empty folder → 409 with `error_code`; `?lists=detach` → 204 and lists survive with `folder_id = null`; `?lists=delete` → 204 and lists **and their tasks** are gone; another user's folder → 403 (not 404 — do not leak existence via a different code than the Policy produces; assert whichever the Policy actually returns and keep it consistent across all three resources); `PUT folders/order` rewrites positions and rejects a foreign id. Plus `tests/Feature/Services/FolderServiceTest.php` for the exception paths called directly.
- **Complexity:** Medium

### Step 6: List CRUD (M2) — service + API

- **What:** Lists are fully manageable over the API: create in a folder or ungrouped, rename, move between folders, reorder, delete — with the Inbox protected.
- **Where:**
  - `app/Services/TaskListService.php` (extend)
  - `app/Http/Requests/Api/V1/{StoreTaskListRequest,UpdateTaskListRequest,UpdateTaskListOrderRequest}.php`
  - `app/Http/Controllers/Api/V1/{TaskListController,TaskListOrderController}.php`
  - `routes/api.php`
- **How:**
  - Service: `allFor(User)`, `create(User, string $name, ?int $folderId)`, `update(TaskList, string $name, ?int $folderId)` (rename + move in one call — the API exposes it as `PUT /lists/{list}`), `delete(TaskList)` → `DefaultTaskListCannotBeDeletedException` when `is_default`, `reorder(User, ?int $folderId, array $listIds)`.
  - Folder references resolve **inside the service** via `FolderRepositoryInterface::findForUser()`, throwing `FolderNotFoundException` when missing or foreign (D3). The Form Request additionally applies `Rule::exists('folders', 'id')->where('user_id', $this->user()->id)` so a legitimate client gets 422 rather than 404 — belt and braces, and the service is the guarantee.
  - Moving a list between folders recomputes `position` as `nextPosition(user, targetFolderId)`; moving to `folder_id = null` means "ungrouped" (M2).
  - The Inbox may be renamed but never moved into a folder and never deleted — `folder_id` is forced to `null` in the service for the default list.
  - Routes: `GET lists`, `POST lists`, `GET lists/{list}`, `PUT lists/{list}`, `DELETE lists/{list}`, `PUT lists/order`. Same ordering caveat as Step 5.
- **Test:** `tests/Feature/Api/V1/TaskListTest.php` — create ungrouped and in a folder; create referencing another user's folder → 422/404 (assert the chosen code) and nothing persisted; rename; move into/out of a folder without losing tasks (assert task count before/after — the user story explicitly calls this out); delete a normal list → 204 and its tasks cascade; delete the Inbox → 422 `default_task_list_cannot_be_deleted`; reorder within a folder. Service-level test for the two exception paths.
- **Complexity:** Medium

### Step 7: Task CRUD, completion, move, reorder and Starred — service + API

- **What:** The full task surface: create, read, update, delete, complete, restore, move, bulk reorder, and the cross-list starred query.
- **Where:**
  - `app/Services/TaskService.php` (extend), `app/Services/Data/TaskDetailsData.php`
  - `app/Http/Requests/Api/V1/{StoreTaskRequest,UpdateTaskRequest,MoveTaskRequest,UpdateTaskOrderRequest}.php`
  - `app/Http/Controllers/Api/V1/{TaskController,TaskListTaskController,CompleteTaskController,RestoreTaskController,MoveTaskController,TaskOrderController,StarredTaskController}.php`
  - `routes/api.php`
- **How:**
  - Service methods (intent-named): `tasksFor(TaskList)` (from Step 4), `create(User, TaskList, string $title)`, `update(Task, TaskDetailsData)`, `rename(Task, string)`, `complete(Task)`, `restore(Task)`, `setStarred(Task, bool)`, `move(Task, int $targetListId, ?int $position)`, `delete(Task)`, `reorder(TaskList, array $taskIds)`, `starredFor(User)`.
  - **`create()` trims the title and throws `InvalidTaskTitleException` when the result is empty** (M3). Form Requests and Livewire both validate too, but the service is the invariant — this is precisely the kind of rule that must not live only in a delivery mechanism.
  - `move()` resolves the target list via `TaskListRepositoryInterface::findForUser($targetListId, $task->user)`; a foreign list throws, which preserves the D7 `user_id` immutability invariant.
  - `reorder()` compares the submitted ID set against `idsForList()` and throws `TaskReorderMismatchException` on any difference (missing, extra or foreign ids) before writing anything.
  - `complete()`/`restore()` are idempotent no-ops when already in the target state.
  - `TaskDetailsData` — readonly, `title`, `note`, `dueDate`, `isStarred`; built by `UpdateTaskRequest::toData()`. **`PUT /tasks/{task}` has replace semantics** for these four fields (`title` required, the other three nullable, null = clear). This removes the "null means unchanged vs. null means clear" ambiguity entirely; partial intents have their own endpoints.
  - Route/verb mapping (all inside the v1 group):

    | Method | URI | Controller | Notes |
    |---|---|---|---|
    | GET | `lists/{list}/tasks` | `TaskListTaskController@index` | same shape as `/inbox` |
    | POST | `lists/{list}/tasks` | `TaskListTaskController@store` | 201; title-only |
    | PUT | `lists/{list}/task-order` | `TaskOrderController` | full ordered id array |
    | GET | `tasks/{task}` | `TaskController@show` | |
    | PUT | `tasks/{task}` | `TaskController@update` | replace semantics |
    | DELETE | `tasks/{task}` | `TaskController@destroy` | 204 |
    | POST | `tasks/{task}/complete` | `CompleteTaskController` | idempotent command |
    | POST | `tasks/{task}/restore` | `RestoreTaskController` | idempotent command |
    | POST | `tasks/{task}/move` | `MoveTaskController` | `task_list_id`, optional `position` |
    | GET | `starred` | `StarredTaskController` | **addition** beyond the spec list, needed for S2 |

    `complete`/`restore`/`move` are POST commands (not PUT) for consistency; idempotency is guaranteed server-side, not by the verb. `task-order` is PUT because it replaces an ordering resource wholesale.
  - Action endpoints are single-action invokable controllers, matching the repo's existing controller style.
  - `starredFor()` uses the `(user_id, is_starred)` index and eager-loads `taskList.folder` so `StarredTaskController` can render the parent list/folder without N+1.
- **Test:** `tests/Feature/Api/V1/TaskTest.php` and `StarredTaskTest.php` —
  create with title only → 201; whitespace-only title → 422 and nothing persisted; create in another user's list → 403; index returns active-before-completed with completed ordered by `completed_at DESC`; `complete` sets both `is_completed` and `completed_at` and is idempotent on a second call; `restore` clears both; update replaces note/due date/star and clears them when null is sent; delete → 204 and the row is gone (delete ≠ complete, M7); move to another of the user's lists succeeds and keeps `user_id`; move to a foreign list fails and does not mutate; `task-order` rewrites positions, and a mismatched/foreign id set → 422 `task_reorder_mismatch` **with the original order intact** (transaction rollback); starred index spans lists and excludes other users. Plus `tests/Unit/Services/TaskServiceTest.php` for the trim/exception logic and `tests/Unit/Data/TaskDetailsDataTest.php`.
  Assert query counts (or rely on `preventLazyLoading`) on the index and starred endpoints to lock in eager loading.
- **Complexity:** Large

### Step 8: Livewire Inbox panel (M3–M7, M10)

- **What:** `/inbox` becomes a real, interactive task page: quick capture, active list, collapsible Completed section, complete/restore, inline rename, delete, star toggle — all calling services in-process.
- **Where:**
  - `app/Livewire/Tasks/TaskPanel.php`
  - `resources/views/livewire/tasks/task-panel.blade.php`
  - `resources/views/inbox.blade.php`
  - `tests/Feature/Livewire/TaskPanelTest.php`
- **How:**
  - `inbox.blade.php` renders `<livewire:tasks.task-panel :task-list-id="$taskListId" />` using the id the web `InboxController` resolved in Step 4. The component is written generically (it takes a list id) so Phase 2 can reuse it verbatim for any list page.
  - The component holds `#[Locked] public int $taskListId` and `public string $newTaskTitle = ''`, resolves the `TaskList` through `TaskListRepositoryInterface::findForUser()` in a `#[Computed]` property, calls `Gate::authorize('view', $list)` on mount, and exposes `#[Computed] tasks()` returning `TaskService::tasksFor($list)` (the `ListedTasks` DTO).
  - Every mutation follows the identical three lines: `Gate::authorize(<ability>, $model);` → `$this->service-><intent>(...)` → let the computed property recompute. **No business logic, no queries, and absolutely no `Http::` call — the API is not involved** (D1; enforced by the Step 2 `LayeringTest`).
  - Domain exceptions are caught per action and surfaced as UI feedback: `catch (InvalidTaskTitleException $e) { $this->addError('newTaskTitle', $e->getMessage()); }`, and `Flux::toast(variant: 'danger', ...)` for the rest. This is the web-side counterpart to the API's JSON envelope, and it is why the exceptions carry meaningful messages.
  - Quick capture (M3): input with `wire:model` + `wire:keydown.enter`/`wire:submit`, clears and refocuses after each save so five tasks can be added without leaving the keyboard.
  - Completed section (M6): collapsible via Alpine `x-data` with `$persist` (bundled with Livewire 4) so the collapse state survives a refresh client-side; shows the count; rows at reduced opacity with a strikethrough title. Server-side persistence of that preference is Phase 2.
  - Empty/error states (M10): "Nothing here yet. Add your first task." when empty; a distinct "All done." state when everything is complete; validation and toast feedback when a save fails.
  - Reordering is **not** wired to drag-and-drop here (Phase 2). If accessible move-up/move-down buttons are cheap, they may be included — they call `TaskService::reorder()` with a recomputed id array. Optional, not required for the step to pass.
  - Responsive (M9): the panel must be usable at mobile, tablet and desktop widths using Flux/Tailwind utilities already in the project.
  - Run `php artisan view:clear` — Blaze folds Flux components at compile time.
- **Test:** `tests/Feature/Livewire/TaskPanelTest.php` using `Livewire::test()` — adding a task persists it and clears the input; a blank/whitespace title adds a validation error and persists nothing; completing moves the task into the completed group and sets `completed_at`; restoring reverses it; inline rename persists; delete removes the row; star toggles; a component instantiated with another user's list id is forbidden. Update `tests/Feature/InboxTest.php` to assert the page renders seeded task titles. Assert no lazy-loading violation is raised (the guard from Step 1 makes this automatic).
- **Complexity:** Large

### Step 9: Livewire Starred page (S2)

- **What:** `/starred` lists the user's starred tasks across all lists, showing each task's parent list, with unstar and complete actions.
- **Where:**
  - `app/Livewire/Tasks/StarredPanel.php`
  - `resources/views/livewire/tasks/starred-panel.blade.php`
  - `resources/views/starred.blade.php`
  - `app/Http/Controllers/StarredController.php` (unchanged or trivially unchanged)
  - `tests/Feature/StarredTest.php` (extend), `tests/Feature/Livewire/StarredPanelTest.php`
- **How:** Same shape as Step 8, backed by `TaskService::starredFor($user)`. Each row shows the title, the parent list name (and folder name when present) from the eager-loaded relations, and completion state. Actions: unstar (`setStarred(false)` — the row leaves the view) and complete/restore. Empty state: "No starred tasks yet." (already the copy in the placeholder view). No new service or repository methods beyond Step 7.
- **Test:** `StarredPanelTest` — only the current user's starred tasks appear; tasks from multiple lists appear together with the correct parent names; unstarring removes the row and persists; completing persists; the empty state renders for a user with no starred tasks. Query-count/lazy-loading assertion to lock in the eager load.
- **Complexity:** Small

## 4. Risk Assessment

### Risks

- **R1 (High) — `List` is a reserved PHP keyword.** `app/Models/List.php` is a fatal parse error, and the mistake is only discovered after migrations, factories and relationships have been written against it. Everything downstream (FK names, route bindings, resource names) depends on catching this at Step 1.
- **R2 (High) — an unauthenticated route slips into `/api/v1`.** A single route registered outside the `auth:sanctum` group exposes another user's data to the internet. This is the highest-severity *security* risk in the plan and it grows with every step that adds routes.
- **R3 (High) — `throttleApi()` without a registered `api` limiter 500s every API request.** Verified in `ThrottleRequests::resolveMaxAttempts()`: a non-numeric, unresolvable limiter name throws `MissingRateLimiterException`. There is no default `api` limiter in Laravel 13, and this failure is total, not partial.
- **R4 (Medium) — `install:api` side effects.** It runs `composer require`, publishes a migration, and rewrites `bootstrap/app.php` by string replacement. With no git repository, a bad edit to `bootstrap/app.php` is unrecoverable. It also does **not** add `HasApiTokens` to `User` — it only prints a reminder, which is easy to miss.
- **R5 (Medium) — business logic leaking into Livewire.** The path of least resistance in Step 8 is to inline "if title is blank, don't save" or a quick `Task::where(...)` in the component. That silently breaks the single-application-layer guarantee and produces web/mobile behaviour drift. The mirror-image risk — a developer "reusing" the API by calling it over HTTP from Livewire — is the exact thing the user asked to prevent.
- **R6 (Medium) — N+1 regressions.** The Starred page (`task → list → folder`) and the folder index (`folder → lists`) are the obvious offenders. Without a guard, this only surfaces in production.
- **R7 (Medium) — `Model::preventLazyLoading()` breaks existing starter-kit paths.** Fortify's two-factor and passkey flows are untested against it and may lazy-load relations, turning a safety net into a broken login page.
- **R8 (Medium) — phpstan level 7 on the new layer.** Relation generics need two type parameters in Laravel 11+ (`HasMany<Task, TaskList>`), `Collection<int, Task>` annotations are required on every repository return, and new models need `@property` docblocks like `User` has. Getting this wrong fails `composer test` at every step.
- **R9 (Medium) — the `is_completed` / `completed_at` pair drifts.** Two columns encoding one fact; any write path that bypasses the repository's two methods creates a task that is "completed" with no completion time, or vice versa, and the completed-ordering silently breaks.
- **R10 (Low/Medium) — duplicate Inbox rows.** Two concurrent first requests could both see "no default list" and create one. No DB-level uniqueness enforces it, because MySQL cannot express "at most one row per user where `is_default` is true" with a plain unique index.
- **R11 (Low today, High at mobile-integration time) — no token issuance.** Phase 1 ships an API that the future mobile client cannot actually authenticate against, because the token endpoint is deferred (D9). If someone adds a naive `POST /tokens` later without accounting for Fortify's 2FA and passkey flows, they create an authentication-bypass hole.
- **R12 (Low) — route ordering.** `folders/order` and `lists/order` will be swallowed by `folders/{folder}` / `lists/{list}` if registered after them, producing a confusing 404/403 instead of a reorder.
- **R13 (Low) — cascade semantics mismatch.** `tasks.task_list_id` cascades on delete while `task_lists.folder_id` nullifies. A reviewer reading only the service, or only the migration, can reach opposite conclusions about what "delete a folder" does.
- **R14 (Low) — stale compiled Blade/Blaze views** in Steps 8–9, making UI changes appear not to take effect.
- **R15 (Process, High) — still no git history.** Nine steps of additive-but-large changes, including an automated rewrite of `bootstrap/app.php`, with no undo.

### Mitigations

- **R1:** D2 fixes the naming before any code exists; Step 1's file list names `TaskList.php` and `task_lists` explicitly. First action of Step 1: create `app/Models/TaskList.php`, not `List.php`.
- **R2:** Step 3 adds a route-introspection test that iterates `Route::getRoutes()` and asserts **every** `api/v1` route carries `auth:sanctum`. It runs in every subsequent step, so a route added outside the group fails the build immediately. Reinforced by every endpoint test asserting a 401 for guests and a 403 for a non-owner.
- **R3:** `RateLimiter::for('api', ...)` and `throttleApi()` land in the **same commit** in Step 3, and Step 3's test asserts a 200 plus `X-RateLimit-*` headers — a missing limiter cannot pass.
- **R4:** Commit before running `install:api` (see R15). After it runs, diff `bootstrap/app.php` by eye, confirm the `api:` line, and confirm `HasApiTokens` was added to `User` manually. Fallback: `composer require laravel/sanctum:^4.0` plus a hand-written `routes/api.php` and a one-line edit to `withRouting()` — the command saves perhaps two minutes and is entirely optional.
- **R5:** `tests/Feature/Architecture/LayeringTest.php` (Step 2) is the automated tripwire for `Http::`, `api/v1` strings in Livewire, and query-builder calls in services and controllers. Additionally, code-reviewer must reject any Livewire method longer than authorize → service call → feedback.
- **R6:** Eager loading is specified in the repository method contracts (Step 2), not left to callers; `Model::preventLazyLoading()` (Step 1) converts a regression into a test failure; Steps 7 and 9 assert query counts on the index endpoints.
- **R7:** Enable `preventLazyLoading` in Step 1 and run the **full existing suite** immediately. If a starter-kit path breaks, scope the guard (e.g. skip in the Fortify/passkey tests) rather than removing it, and record which path forced the exception in the step notes.
- **R8:** Copy the annotation style from `App\Models\User` verbatim, and run `composer types:check` before `php artisan test` at every step so type failures surface first.
- **R9:** `markCompleted()`/`markActive()` in `EloquentTaskRepository` are declared the sole writers (D13); a repository test asserts the invariant in both directions; code review rejects any other write to either column.
- **R10:** Accepted risk. A user registers exactly once, and the listener runs inside that request, so a genuine race requires two concurrent first-ever requests for the same brand-new user. `inboxFor()` is idempotent, so a duplicate would be cosmetic rather than corrupting. Fallback if duplicates ever appear: add a nullable `default_marker` column holding `1` or `NULL` with a unique index on `(user_id, default_marker)` — MySQL does not collide on NULLs. Not done now because it is clever-over-clear for a risk this small.
- **R11:** Documented explicitly in D9, in this risk list, and in the README note for Step 3. The follow-up plan must design token issuance **through** the Fortify 2FA/passkey flow, not around it. Until then the API is reachable only by the same-origin session, which is exactly the current set of consumers.
- **R12:** Steps 5 and 6 call out registering `order` routes before parameterised ones, or constraining with `->whereNumber()`. A test hitting `PUT /api/v1/folders/order` catches it.
- **R13:** The cascade behaviour is stated in D6, in the migration step, and asserted from both directions in Step 1's relationship test and Step 5's API test.
- **R14:** `php artisan view:clear` is an explicit action in Steps 8 and 9. Note that `composer test` runs `config:clear` but **not** `view:clear`.
- **R15:** **Initialise git and commit the current state before Step 1**, then commit after every step. This is the cheapest insurance in the plan and the second time it has been raised.

### Fallbacks

- If `install:api` mangles `bootstrap/app.php`, revert that file and wire the four lines by hand (`api:` key, `throttleApi()`, plus the Sanctum requirement in `composer.json`).
- If Sanctum proves disruptive, the interim fallback is the plain `auth` (session) guard on `/api/v1/*`: the API works for the browser and every test, and the mobile story moves entirely into the token-issuance plan. Cost: one middleware string to change later, so this is a genuine escape hatch rather than a dead end.
- If `Model::preventLazyLoading()` fights the starter kit, scope it to the test environment only (`app()->runningUnitTests()`), which still catches regressions in CI.
- If the `ListedTasks` DTO feels like ceremony during review, the fallback is `array{active: Collection, completed: Collection, completed_count: int}` with a phpstan array shape — same data, less structure. Recommended only if the DTO proves to be pure overhead, since the DTO is also the documentation of the M4/M6 read model.
- If Step 8 grows beyond one session, split it: (8a) read-only rendering of active + completed sections; (8b) mutations (add, complete, restore, edit, delete, star). Both halves are independently deployable.
- If drag-and-drop turns out to be needed sooner than Phase 2, the endpoints (`PUT lists/{list}/task-order`, `PUT folders/order`, `PUT lists/order`) already exist from Steps 5–7 and only the client interaction is missing.

## 5. Execution Checklist

- [ ] **Step 0 (do this first):** `git init` + commit the current state. Nine steps of edits, including an automated rewrite of `bootstrap/app.php`, with no undo otherwise.
- [ ] **Step 1:** Migrations for `folders`, `task_lists`, `tasks`; `Folder`/`TaskList`/`Task` models with relationships, casts and `#[Fillable]` (no `user_id`); `User` relationships; three factories with states; `DatabaseSeeder` demo data; `Model::preventLazyLoading()` in `AppServiceProvider`. `php artisan migrate:fresh --seed`, verify rollback, then `composer test`.
- [ ] **Step 2:** Repository contracts + Eloquent implementations + `RepositoryServiceProvider`; `DomainException` base + 5 named exceptions; three Policies; `AuthorizesRequests` on the base `Controller`; `LayeringTest`; update the structure block in `.claude/CLAUDE.md`. Run `composer test`.
- [ ] **Step 3:** `php artisan install:api --without-migration-prompt` + `php artisan migrate`; `HasApiTokens` on `User`; verify the `api:` key in `bootstrap/app.php`; `throttleApi()`; `RateLimiter::for('api', ...)`; rewrite `routes/api.php` as the `auth:sanctum` + `verified` + `v1` group; domain-exception JSON renderer; `ApiFoundationTest` **including the every-api-route-is-authenticated guard**. Run `composer test`.
- [ ] **Step 4:** `TaskListService::inboxFor()`, `TaskService::tasksFor()`, `ListedTasks` DTO, `CreateDefaultTaskList` listener, `TaskResource`/`TaskListResource`, `GET /api/v1/inbox`, web `InboxController` calling the same service. Verify with `php artisan event:list`. Run `composer test`.
- [ ] **Step 5:** `FolderService` (incl. `FolderNotEmptyException` and the detach/cascade strategies), Form Requests, `FolderResource`, folder controllers, routes (order route registered first). Run `composer test`.
- [ ] **Step 6:** `TaskListService` CRUD + move + reorder + default-list protection, Form Requests with user-scoped `exists`, controllers, routes. Run `composer test`.
- [ ] **Step 7:** `TaskService` full surface + `TaskDetailsData`, Form Requests, seven controllers, the route table from the step, `GET /api/v1/starred`. Assert query counts on index endpoints. Run `composer test`.
- [ ] **Step 8:** `Tasks\TaskPanel` Livewire component + view, hosted by `inbox.blade.php`; authorize → service → feedback in every action; domain exceptions surfaced as validation errors/toasts; collapsible Completed section; empty and error states; responsive check at three widths; `php artisan view:clear`. Run `composer test`.
- [ ] **Step 9:** `Tasks\StarredPanel` + view hosted by `starred.blade.php`; unstar and complete actions; parent list/folder shown; empty state; `php artisan view:clear`. Run `composer test`.
- [ ] **Phase 2 (separate plan, not in scope here):** folder/list management UI and the sidebar folder→list tree; drag-and-drop reordering UX (S4); due-date UI (S1); notes UI (S3); search (S5); undo (S7); server-side persistence of the Completed-section collapse state (M6); smart lists beyond Inbox/Starred (C1); **API token issuance for mobile, designed through the Fortify 2FA/passkey flow (D9/R11)**; OpenAPI documentation for the mobile team.
- [ ] **README:** update run instructions if `install:api` changes the setup flow, and document the `/api/v1` surface, the response envelope and the current authentication story (session-only until token issuance ships).
