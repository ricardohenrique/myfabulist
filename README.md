# My Fabulist

My Fabulist is a calm, Wunderlist-inspired personal task manager organized as
**Folder → List → Task**. Every account receives a permanent Inbox, with
Starred as a focused cross-list view.

The Laravel application and database are canonical. The browser uses Inertia
and shared application services in-process; remote clients use the versioned
Sanctum API under `/api/v1`.

## Stack

- PHP 8.4 and Laravel 13
- Inertia.js 3, React 19, and TypeScript
- Tailwind CSS 4 and Vite 8
- Laravel Wayfinder for typed client routes
- Sanctum for API authentication and Fortify for registration/login
- `@dnd-kit/react` for scoped pointer, touch, and keyboard reordering
- Pest 5, Larastan/PHPStan, and Pint
- SQLite by default; other Laravel-supported databases remain configurable

## Requirements

- PHP 8.4 with PDO SQLite (or the driver for your chosen database)
- Composer 2
- Node.js 22 or newer with npm

## Setup

### Automated setup

From the project root:

```bash
composer setup
```

This installs PHP and JavaScript dependencies, creates `.env`, generates the
application key, creates the SQLite file when needed, runs migrations, and
builds production frontend assets.

### Manual setup

1. Install PHP dependencies.

   ```bash
   composer install
   ```

2. Install JavaScript dependencies.

   ```bash
   npm install
   ```

3. Create and configure the environment file.

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   The example configuration uses SQLite. To use another database, update the
   `DB_*` values before migrating.

4. Create the default SQLite database file and run migrations.

   ```bash
   php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
   php artisan migrate
   ```

5. Optionally load the small development fixture.

   ```bash
   php artisan db:seed
   ```

6. Build frontend assets.

   ```bash
   npm run build
   ```

7. Start the application.

   ```bash
   php artisan serve
   ```

   For active development with the repository's combined Laravel and Vite
   processes, use:

   ```bash
   composer dev
   ```

### Demo data

`php artisan db:seed` creates the small stable local fixture. For a larger local
dataset, use:

```bash
php artisan db:fresh-seed
```

The latter is destructive and refuses to run outside `local` or `testing`. It
creates `demo1@example.com` through `demo20@example.com`, all with password
`password`, plus realistic folders, lists, tasks, due dates, and Starred data.

## Browser application

Guests can register and sign in. Authenticated users can:

- navigate Inbox, Starred, folders, and lists;
- create, rename, move, reorder, and delete folders/lists within their rules;
- quickly add, edit, complete/restore, star, move, reorder, and delete tasks;
- edit task title, note, due date, starred state, and destination list;
- add plain-text comments with author attribution from task details;
- share a list by inviting a registered user's email, and accept, decline,
  revoke, or leave a share, with a notification center for pending
  invitations and a share dialog for managing a list's members; and
- update the signed-in user's name, email address, and password from the
  account menu; and
- undo the latest completion, star change, or task move.

Email verification, password reset, 2FA, passkeys, expanded account settings,
search, reminders, and NativePHP screens are intentionally outside the current
web release.

Production pages are rendered by Inertia from `resources/js/pages`; the shared
shell lives in `resources/js/layouts/app-shell.tsx`.

### Reordering

The full surface of every active task, folder, and non-Inbox list is draggable;
no separate grip icon is required. Existing controls still respond to ordinary
clicks. For keyboard sorting, focus the sortable item, press Space or Enter, use
the arrow keys, then press Space or Enter again to drop.

Reordering is same-container only:

- active tasks within the currently open list;
- lists within their current folder or the ungrouped list collection; and
- folders within the current user's sidebar.

Cross-list task movement and cross-folder list movement remain explicit dialog
actions. The browser applies the dropped order immediately and submits the full
expected ID set. The repository validates that set transactionally and rejects
stale, incomplete, duplicate, foreign, or cross-container input before writing.
The next Inertia response reconciles the interface with canonical server state.

Dedicated move-up/move-down menu options were intentionally removed in Phase 3;
keyboard reordering is provided by the same sortable item surface.

## Architecture

```text
Browser request → web route / Form Request / Inertia controller
                → Service → Repository → Eloquent / database
                → Inertia presenter → React page

API request     → /api/v1 + auth:sanctum / Form Request
                → API controller → same Service → same Repository
                → API Resource → JSON
```

- Controllers are transport adapters and do not query directly.
- Services own workflows and domain rules.
- Repositories own persistence and transactional multi-record changes.
- Policies and scoped requests protect every user-owned resource.
- API Resources define the remote JSON contract.
- Browser controllers reuse the application layer directly and never call the
  application's own API over loopback HTTP.

Tasks and lists are soft-deleted. Folder deletion is explicit: users either
detach contained lists or choose the destructive list-deletion workflow. Inbox
is default, ungrouped, undeletable, and cannot be renamed.

## API

All `/api/v1` routes require Sanctum authentication; verified email is not
required. Successful resources use Laravel's `data` envelope. Validation errors
use Laravel's standard `message` and `errors` shape, while domain failures use:

```json
{
  "message": "Human-readable explanation",
  "error_code": "stable_machine_code"
}
```

The API covers Inbox, Starred, folder CRUD/order, list CRUD/order, task
CRUD/order, completion/restoration, starring, and explicit task moves. Route
definitions in `routes/api.php` are the authoritative endpoint inventory.

## Quality gates

```bash
composer test          # config clear + Pint check + PHPStan + full Pest suite
composer lint          # apply Pint formatting
composer lint:check    # check Pint formatting
composer types:check   # Larastan/PHPStan
php artisan test       # Pest suite only
npm run types:check    # TypeScript
npm run build          # production Vite build
```

Focused architecture and product notes live under `docs/`, with current scope
in `development/scope.md` and repository guidance in `AGENTS.md`.
