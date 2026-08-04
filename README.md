# MyFabulist

A task manager organized as **Folders → Lists → Tasks** (Wunderlist-style), built with
Laravel 13, PHP 8.3+, and Livewire. Every user gets a default, undeletable "Inbox" list.
The application ships two delivery mechanisms over one shared application layer: a
Livewire web UI and a versioned JSON REST API (`/api/v1`).

## Stack

- PHP 8.3+, Laravel 13
- SQLite by default (MySQL supported via `.env` config)
- Livewire 4 + Flux UI, Tailwind CSS 4, Vite
- Laravel Sanctum (API auth), Laravel Fortify (login/2FA/passkeys)
- Pest-free PHPUnit test suite, Larastan (PHPStan level 7), Pint

## Setup

1. **Clone and install dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   By default `DB_CONNECTION=sqlite`. To use MySQL instead, uncomment and set the
   `DB_*` variables in `.env` and set `DB_CONNECTION=mysql`.

3. **Database**
   ```bash
   touch database/database.sqlite   # skip if using MySQL
   php artisan migrate
   php artisan db:seed              # optional: creates a demo user + sample data
   ```
   The seeder creates `test@example.com` with an Inbox, a "Work" folder containing a
   "Website launch" list (with a completed and a starred task), and an ungrouped
   "Groceries" list.

4. **Storage link** (required for profile photo uploads)
   ```bash
   php artisan storage:link
   ```

5. **Build frontend assets**
   ```bash
   npm run build      # one-off production build
   # or
   composer dev        # runs the app + queue + Vite dev server together
   ```

6. **Run the app**
   ```bash
   php artisan serve   # or `composer dev` from step 5
   ```

Steps 1–4 are also available as a single command: `composer setup`.

### Quality gates

```bash
composer test          # config:clear + Pint (check) + PHPStan + php artisan test
composer lint           # Pint, auto-fix
composer lint:check      # Pint, check only
composer types:check     # PHPStan / Larastan
php artisan test         # test suite only
```

CI (`.github/workflows/tests.yml`) runs `composer setup` then `composer ci:check` on
PHP 8.3 + Node 22.

## Architecture / Layers

The app follows a layered MVC architecture with a single application layer shared by
both delivery mechanisms:

```
API request  → routes/api.php (auth:sanctum + verified)
             → Form Request (app/Http/Requests/Api/V1)
             → Controller (app/Http/Controllers/Api/V1)
             → Service → Repository → Model / Database
             → API Resource (app/Http/Resources/Api/V1) → JSON

Web request  → routes/web.php or a Livewire component
             → Service → Repository → Model / Database
             → Blade view
```

| Layer | Responsibility |
|---|---|
| **Controllers** (`app/Http/Controllers`) | Thin HTTP entry points. Receive a validated request, call a Service, return a response. No business logic, no queries. |
| **Form Requests** (`app/Http/Requests`) | Own input validation and authorization for a request. API requests live under `Http/Requests/Api/V1`. |
| **Services** (`app/Services`) | Own business rules and use-case orchestration. Call Repositories for data access — never Eloquent directly. Throw named domain exceptions for rule violations. |
| **Repositories** (`app/Repositories`, contracts in `Repositories/Contracts`) | The only layer that queries the database. Bound to their interfaces in `app/Providers/RepositoryServiceProvider`. |
| **Models** (`app/Models`) | Eloquent relationships, casts, and attributes. No business logic or workflows. |
| **API Resources** (`app/Http/Resources/Api/V1`) | Shape the JSON wire format for API responses. Controllers never return raw models/arrays from an API endpoint. |
| **Livewire Components** (`app/Livewire`) | Reactive UI state for the web app. Delegate all data operations to Services/Repositories — never call the API over HTTP. |
| **Policies** (`app/Policies`) | Per-model authorization (a resource belongs to the requesting user). |
| **Exceptions** (`app/Exceptions`) | Named domain exceptions extending `DomainException`, each carrying an `error_code` and HTTP status, rendered centrally in `bootstrap/app.php`. |

This is enforced, not just conventional: `tests/Feature/Architecture/LayeringTest.php`
fails the build if Livewire calls the `Http` facade or `/api/v1`, or if a Service/Controller
builds a query directly instead of going through a Repository.

### Deletion semantics

- **Tasks and lists are soft deleted and recoverable.** Both models use Eloquent's
  `SoftDeletes` trait; deleting one sets `deleted_at` instead of destroying the row, and
  it is un-deleted via `TaskService::undelete(Task $task, User $user)` /
  `TaskListService::undelete(TaskList $taskList)`. Every existing read (list panel,
  Starred, API) already goes through an Eloquent query, so the soft-delete global scope
  hides trashed rows everywhere for free.
- **A deleted list's tasks are untouched and invisible.** Deleting a list never modifies
  its tasks; they become invisible only because reads reach them *through* the list.
  The one cross-list read, `EloquentTaskRepository::starredForUser()`, has an explicit
  `whereHas('taskList')` guard so a deleted list's starred tasks disappear from Starred
  too. Un-deleting a list brings every task back exactly as it was — completion, star,
  position — with zero extra bookkeeping.
- **`restore()` means un-complete; `undelete()` means un-delete.** These are two
  unrelated, deliberately differently-named operations — `TaskService::restore()`
  un-completes a completed task (used by the `POST /tasks/{task}/restore` route);
  `TaskService::undelete()` recovers a soft-deleted task. Never confuse the two.
- **Folders are hard deleted and irreversible.** Folder deletion (`deleteWithLists()`,
  `detachLists()`) is unchanged — `deleteWithLists()` explicitly `forceDelete()`s the
  folder's lists so the FK cascade still destroys their tasks, rather than merely
  soft-deleting lists into an unreachable state.
- **No purge job.** There is no scheduled cleanup of soft-deleted rows — they persist
  until the owning account is deleted (`users` → `tasks`/`task_lists` cascade deletes
  physically purge everything, trashed or not). A `PurgeDeletedItemsCommand` is a natural
  follow-up if storage growth ever matters, but is out of scope today.

## Web Routes

All web routes below sit behind the `auth` + `verified` middleware group (`routes/web.php`)
and render a page that mounts one or more Livewire components. The Livewire web UI **never**
calls `/api/v1` over HTTP — every page and component resolves its data by calling a Service
in-process, exactly like the API controllers do (enforced by `LayeringTest`).

| Method | Route | Description |
|---|---|---|
| GET | `/inbox` | The user's default Inbox list (auto-created if missing) — its own canonical URL. |
| GET | `/starred` | The user's starred tasks across every list. |
| GET | `/lists/{list}` | Any other list, by numeric id. Renders the same `TaskPanel` component as the Inbox. The Inbox's own id redirects here to `/inbox` instead of rendering a second URL for the same list. |

The sidebar (`<livewire:navigation.sidebar />`, included by the app layout on every
authenticated page) renders the folder → list tree and links each list to `/lists/{list}`
with `wire:navigate`. Folder and list create/rename/move/delete are owned by two sibling
components, `Navigation\FolderDialog` and `Navigation\ListDialog`, which the sidebar opens
via browser events (`folder-dialog-open` / `list-dialog-open`) and which announce successful
mutations back to it via a `navigation-changed` event.

### Reordering (tasks, lists, folders)

Every reorderable list in the web UI — active tasks in `Tasks\TaskPanel`, lists within a
folder or the ungrouped group, and folders themselves in `Navigation\Sidebar` — supports
two equivalent ways to reorder, both calling the same `TaskService::reorder()` /
`TaskListService::reorder()` / `FolderService::reorder()` methods the API's
`/task-order`, `/lists/order` and `/folders/order` endpoints use:

- **Drag and drop**, via Livewire 4's built-in `wire:sort` (no extra JS dependency). A drag
  updates the DOM immediately (`.renderless`) and saves in the background; a rejected write
  (a stale id set, most often from two open tabs) refreshes the list from the database and
  shows a toast instead of leaving the UI showing an order that was never persisted.
- **Move up / move down** menu items on every reorderable row — the accessible, pointer-free
  path, disabled at the first/last position.

Cross-folder list dragging and cross-list task dragging are both **out of scope**: moving a
list into a different folder, or a task into a different list, stays the explicit "Move"
action (`ListDialog`, or a task row's "Move to…" menu) — both are a single atomic write,
whereas a cross-container drag would be two.

### Undo

Completing a task, moving it to another list, and starring/unstarring it each show a brief,
dismissible undo bar (`<x-tasks.undo-bar>`, not a toast — `Flux::toast()` has no slot for an
action button) with a single "Undo" action, auto-dismissed client-side after ~8 seconds. Undo
re-resolves and re-authorizes the task and calls the same idempotent service method a second
time (`restore()`, `move()` back to the original list, or `setStarred()` back), so a double
click cannot corrupt anything. It holds exactly one action — undoing is a safety net for the
last thing you did, not a history stack.

**Deleting a task or a list still keeps its `wire:confirm` / confirmation dialog and does
not yet wire up an undo bar**, even though deletion is now a soft delete under the hood
(see "Deletion semantics" above) and `TaskService::undelete()` /
`TaskListService::undelete()` exist and are fully tested. Wiring the undo bar to deletion
is tracked as a follow-up to
`docs/04-08-2026-frontend-reordering-undo-and-polish-3.md` (its C4 decision) — see that
plan's amendment note for the precise remaining steps (trashed-aware re-resolution,
`TaskCannotBeUndeletedException` handling, and a home for list-deletion undo).

## API Reference

Base URL: `/api/v1`. Every route requires **Laravel Sanctum** authentication
(session/cookie for the SPA, or a bearer personal access token) plus a **verified**
email address (`auth:sanctum` + `verified` middleware). Unauthenticated requests get a
JSON `401`; unverified users get a `403`.

**Response envelope**
- Success: `{"data": ...}`
- Validation error: `{"message": "...", "errors": {...}}`
- Domain error: `{"message": "...", "error_code": "..."}`

### Auth

| Method | Endpoint | Description |
|---|---|---|
| GET | `/user` | Returns the authenticated user. |

### Folders

| Method | Endpoint | Description |
|---|---|---|
| GET | `/folders` | List the current user's folders (with their lists). |
| POST | `/folders` | Create a folder. |
| GET | `/folders/{folder}` | Show a folder and its lists. |
| PUT/PATCH | `/folders/{folder}` | Rename a folder. |
| DELETE | `/folders/{folder}` | Delete a folder. Requires `?lists=detach` (move lists to top level) or `?lists=delete` (delete lists too) if the folder isn't empty; otherwise fails with `folder_not_empty` (409). |
| PUT | `/folders/order` | Reorder folders. Body: `{"folder_ids": [int, ...]}`. |

### Lists

| Method | Endpoint | Description |
|---|---|---|
| GET | `/lists` | List the current user's lists. |
| POST | `/lists` | Create a list, optionally inside a folder (`folder_id`). |
| GET | `/lists/{list}` | Show a list. |
| PUT/PATCH | `/lists/{list}` | Rename a list and/or move it between folders. |
| DELETE | `/lists/{list}` | Soft-delete a list (its tasks are untouched but hidden). The default Inbox cannot be deleted (`default_task_list_cannot_be_deleted`, 422). The response contract is unchanged: `204`, then `404` on a later read — there is deliberately no V1 un-delete endpoint. |
| GET | `/lists/{list}/tasks` | Tasks in the list, split into `active` and `completed` (plus `completed_count`). |
| POST | `/lists/{list}/tasks` | Create a task in the list. |
| PUT | `/lists/{list}/task-order` | Reorder tasks within the list. Body: `{"task_ids": [int, ...]}`. Fails as `task_reorder_mismatch` (422) if the submitted ids don't match the list's current tasks. |
| PUT | `/lists/order` | Reorder lists, optionally scoped to a folder. Body: `{"folder_id": int|null, "task_list_ids": [int, ...]}`. |

### Tasks

| Method | Endpoint | Description |
|---|---|---|
| GET | `/tasks/{task}` | Show a task. |
| PUT | `/tasks/{task}` | Update a task's title, note, due date, or starred flag. |
| DELETE | `/tasks/{task}` | Soft-delete a task. The response contract is unchanged: `204`, then `404` on a later read — there is deliberately no V1 un-delete endpoint. |
| POST | `/tasks/{task}/complete` | Mark a task complete (idempotent). |
| POST | `/tasks/{task}/restore` | Mark a completed task active again (idempotent). |
| POST | `/tasks/{task}/move` | Move a task to another of the user's lists. Body: `{"task_list_id": int, "position": int|null}`. |

### Inbox & Starred

| Method | Endpoint | Description |
|---|---|---|
| GET | `/inbox` | The user's default Inbox list with its tasks (auto-created if missing). |
| GET | `/starred` | All of the user's starred tasks across every list, newest first. |

### Domain error codes

| `error_code` | HTTP status | Cause |
|---|---|---|
| `folder_not_found` | 404 | Referenced folder doesn't exist or isn't yours. |
| `task_list_not_found` | 404 | Referenced list doesn't exist or isn't yours. |
| `folder_not_empty` | 409 | Deleting a folder that still has lists without `?lists=detach\|delete`. |
| `invalid_task_title` | 422 | Task title is blank/whitespace-only. |
| `default_task_list_cannot_be_deleted` | 422 | Attempting to delete the Inbox. |
| `task_reorder_mismatch` | 422 | Submitted task order doesn't match the list's current tasks. |
| `task_cannot_be_undeleted` | 422 | Un-deleting a task whose list is itself deleted (not reachable from `/api/v1` today — thrown by `TaskService::undelete()`, which currently only the Livewire web layer will call). |

## Testing

```bash
php artisan test
```

Notable suites: `tests/Feature/Api/V1` (endpoint behavior), `tests/Feature/Architecture`
(layering rules), `tests/Feature/Repositories`, `tests/Feature/Services`,
`tests/Feature/Livewire`, `tests/Unit`.
