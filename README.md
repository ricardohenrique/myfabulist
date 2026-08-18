# Purplelist

Purplelist is a calm, Wunderlist-inspired personal task manager organized as
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
- MySQL

## Requirements

- PHP 8.4 with the PDO MySQL extension
- Composer 2
- Node.js 22 or newer with npm
- MySQL with an application database and user credentials

## Setup

### Automated setup

From the project root:

```bash
cp .env.example .env
```

Create the MySQL database named by `DB_DATABASE` and configure the `DB_*`
values in `.env`, then run:

```bash
composer setup
```

This installs PHP and JavaScript dependencies, generates the application key,
runs migrations against the configured MySQL database, and builds production
frontend assets.

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

   The example configuration uses MySQL. Update the `DB_*` values for your
   local MySQL database before migrating.

   To enable Google sign-in, create a Google OAuth 2.0 web client and set
   `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`. Register the exact callback
   URL from `GOOGLE_REDIRECT_URI` (by default
   `${APP_URL}/auth/google/callback`) as an authorized redirect URI in Google.

   To enable production Google Analytics, set `VITE_GA_MEASUREMENT_ID` to the
   GA4 web stream ID (`G-...`) in the production server's `.env`. Laravel adds
   the Google tag to every production page at request time, including public
   and authenticated pages, while Inertia navigations are recorded as page
   views without requiring a full browser reload. Rebuilds are not required
   when this value changes, but run `php artisan optimize` to refresh cached
   configuration. Cookie consent is managed by the separately configured
   CookieYes integration.

4. Create the MySQL database and run migrations. The example command below
   uses the default `DB_DATABASE=laravel`; adjust it if you changed that value.

   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
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

Guests can register, sign in with email and password, or continue with a
verified Google account. Matching verified email addresses are linked to the
existing Purplelist account instead of creating duplicates. Authenticated
users can:

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

New password and Google registrations begin with two simple tasks in Inbox.
On the first Inbox visit, a one-question onboarding dialog optionally records
the account's main Purplelist use case; choosing Skip dismisses it permanently
without recording a category.

Privacy and Terms pages are publicly available at `/privacy` and `/terms`,
with links in the login-page footer. Their highlighted operator, processor,
retention, and consumer-dispute details must be completed with the production
business and infrastructure information before launch.

Password recovery is available from the login page. Password-based registrations
receive a branded welcome email with an optional confirmation link; unverified
accounts can still sign in and use the application. 2FA, passkeys, expanded
account settings, search, reminders, and NativePHP screens remain outside the
current web release.

Authentication emails are queued and use the `MAIL_*` configuration. With the
default `QUEUE_CONNECTION=database`, production must keep a queue worker running
(for example under Supervisor or systemd):

```bash
php artisan queue:work --tries=3
```

Set `APP_URL` to the public HTTPS origin before sending mail because password
reset and email-confirmation links are generated from it.

SMTP uses opportunistic TLS by default. A trusted host-local relay that
explicitly does not support TLS, such as GoDaddy cPanel's `localhost:25`
relay, may set `MAIL_AUTO_TLS=false`; do not disable TLS for a remote SMTP
server.

Production pages are rendered by Inertia from `resources/js/pages`; the shared
shell lives in `resources/js/layouts/app-shell.tsx`.

### Add to Home Screen

The browser app includes a web app manifest, install-sized icons, and Apple
standalone metadata. On a supported mobile browser, use **Add to Home Screen**
or **Install app** to launch Purplelist in a standalone window. Production
must be served over HTTPS for browser installation. This does not enable
offline access or offline writes; the Laravel server remains canonical.

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

## Health check

`GET /health` is public and checks that Laravel can handle the request and
query the canonical database. Clients requesting JSON receive
`{"status":"up"}` with HTTP 200 when both are available, or
`{"status":"down"}` with HTTP 500 when the database check fails. Use this
endpoint for deployment readiness and external uptime checks; the production
JSON response does not expose credentials or exception details.

## Production error monitoring

Sentry delivery is enabled only when `APP_ENV=production` and
`SENTRY_LARAVEL_DSN` is configured. A DSN present in a local, testing, or
staging `.env` is ignored, so those environments cannot send errors, traces,
logs, or metrics to the production Sentry project. After changing production
Sentry settings, run `php artisan optimize` to refresh cached configuration.

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
