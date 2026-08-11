# My Fabulist Agent Guide

My Fabulist is a calm, fast task manager inspired by Wunderlist. Its core
hierarchy is **Folder → List → Task**, with a permanent Inbox for quick capture
and focused views such as Starred. Act as a senior Laravel product engineer:
protect the simplicity of the task workflow, preserve user ownership and data
integrity, and prefer small, conventional, tested changes.

This is a product repository, not a reusable boilerplate. Product-specific
behaviour belongs here and should remain consistent across the browser UI,
versioned API, and future native clients.

## Sources of truth

Read the relevant source before changing behaviour:

1. `development/scope.md` defines product goals, boundaries, and priorities.
2. `composer.json` and `package.json` define the installed dependencies and
   available quality commands.
3. `routes/web.php` and `routes/api.php` define the public application
   surfaces.
4. `app/Services`, `app/Repositories`, `app/Policies`, and `app/Models` define
   the domain and persistence boundaries.
5. `resources/js`, `resources/css`, and `resources/views/app.blade.php` define
   the Inertia browser experience.
6. `README.md` and the focused files under `docs/` document setup, implemented
   behaviour, and historical decisions.
7. `.env.example` documents safe environment configuration.

When implementation and documented product behaviour disagree, inspect tests
and recent project documentation before deciding which is stale. Do not silently
invent a third behaviour. Update documentation whenever a change affects the
architecture, setup, API contract, data ownership, or user-visible conventions.

## Product principles

- Optimize for quick capture: after opening a list, creating a task should take
  one short interaction and leave the user ready to enter another.
- Keep active work visually dominant. Completed work stays recoverable and
  visible in a separate, muted, collapsible section.
- Preserve the simple hierarchy. A list is a lightweight project or context;
  do not turn the product into a dense enterprise project-management suite.
- Inbox is permanent, ungrouped, and user-owned. It cannot be renamed or
  deleted and is the default destination for global quick capture.
- Starred is a focused cross-list view, not another persisted list.
- Interactions should feel immediate, but optimistic UI must reconcile rejected
  writes and never imply that unsaved state was persisted.
- Prefer reversible actions and clear recovery. Completion is not deletion;
  destructive operations require confirmation or a deliberately implemented
  undo path.
- Accessibility, responsive behaviour, and keyboard workflows are product
  requirements, not optional polish.

## Scope validation before implementation

Before implementing a feature, review the request, `development/scope.md`, and
the relevant existing behaviour. Identify conflicts, missing acceptance
criteria, and assumptions that affect the implementation.

- For minor ambiguities, choose the safest conventional interpretation,
  document the assumption, and continue.
- For major ambiguities, pause the affected implementation and request
  clarification. This includes choices that materially change the data model,
  API contract, authorization boundary, canonical data ownership, dependency
  choices, platform support, or core interaction model.
- Do not block on facts that can be discovered from the repository or on small,
  reversible implementation details.
- Do not implement items listed as out of scope merely because they resemble a
  Wunderlist feature.

## Baseline stack

Use the versions constrained by the dependency manifests. The current baseline
is:

- PHP `8.4+` and Laravel `13.x`
- Inertia.js `3.x`
- React `19.x` with TypeScript
- Tailwind CSS `4.x`
- Vite `8.x`
- NativePHP Mobile `4.x` and NativePHP Mobile UI
- Pest `5.x`
- Larastan/PHPStan for static analysis
- Laravel Wayfinder for typed route integration

Do not add dependencies, replace framework choices, or create new top-level
directories without a demonstrated need. Obtain explicit approval for changes
that materially expand the boilerplate's stack or maintenance burden.

## Target application architecture

- The remote Laravel application and its database are canonical for account,
  folder, list, task, ordering, completion, due-date, note, and starred data.
- The target browser UI uses Inertia and React with TypeScript. Pages belong in
  `resources/js/pages`, shared product components in
  `resources/js/components`, and focused client logic in `resources/js/lib` or
  hooks as appropriate.
- The browser runtime is exclusively Inertia and React. Do not introduce a
  second server-rendered component runtime or duplicate product workflows.
- Native screens use NativePHP 4 native components and NativePHP Mobile UI.
  Keep platform-independent domain logic outside native components so it can
  be tested without an emulator.
- Browser controllers, API controllers, and native components are transport
  adapters. They delegate domain reads and mutations to shared services,
  repositories, policies, actions, and serializers.
- Never make the Laravel server call its own API over loopback HTTP. Browser
  controllers reuse application logic in-process; remote native clients use
  the versioned HTTPS API.

### API-first multi-platform contract

- Keep externally consumed endpoints under `/api/v1` and authenticated with
  Sanctum. Email verification is intentionally disabled for the current
  release.
- API Resources are the canonical JSON payload contract. Keep corresponding
  TypeScript and native client types synchronized as those clients are built.
- Maintain the established response shapes: successful resources use Laravel's
  `data` envelope; domain errors include `message` and `error_code`; validation
  errors use Laravel's standard `message` and `errors` structure.
- Browser sessions use the `web` guard and CSRF protection. Remote clients use
  bearer tokens and do not send browser cookies or CSRF tokens.
- Apply policies or equivalent authorization to every user-owned folder, list,
  and task. Hidden UI is never an authorization boundary.
- Keep browser redirects and API JSON in separate controllers.

## Domain invariants

- Every folder, list, and task belongs to exactly one user. A task and its list
  must have the same owner; a list and its folder must have the same owner.
- Every user has exactly one default Inbox list. The Inbox is ungrouped,
  undeletable, and cannot be renamed through product workflows.
- Lists may be ungrouped or belong to one folder. Deleting a non-empty folder
  must use an explicit choice: detach its lists or delete them through the
  documented destructive workflow.
- Task titles are required after trimming; blank titles are invalid. Notes and
  due dates are optional. Importance is a binary starred flag.
- Active and completed tasks remain distinct. Completing records completion;
  restoring returns the task to the active collection.
- Positions are scoped to their container: folders per user, lists per
  folder/ungrouped collection, and tasks per list. Reorder operations must
  validate the complete expected ID set and reject stale or foreign IDs.
- Moving a task or list across containers is an explicit atomic operation, not
  an accidental side effect of same-container reordering.
- Tasks and lists use soft deletion. Restoration must re-authorize ownership
  and validate that the destination still exists. Do not expose deleted records
  through ordinary reads.

## Laravel conventions

- Inspect sibling files and follow established conventions before creating or
  editing code. Reuse existing services and repositories before adding another
  abstraction.
- Use descriptive names, explicit PHP parameter and return types, constructor
  property promotion, and useful array-shape PHPDoc for structured arrays.
- Use environment variables only in configuration files; use `config()` in
  application code.
- Generate application links with named routes. Keep Wayfinder types
  synchronized with backend route changes once the React client consumes them.
- Register middleware, exception handling, and routing in `bootstrap/app.php`;
  providers in `bootstrap/providers.php`; console routes and scheduling in
  `routes/console.php`.
- Prefer Eloquent relationships and deliberate eager loading over raw SQL.
  Keep controllers thin and free of direct persistence queries.
- Use repositories for persistence, services/actions for domain workflows,
  form requests for non-trivial HTTP validation, policies for authorization,
  and API Resources for stable JSON.
- Enforce ownership, uniqueness, and integrity in the database where practical,
  in addition to validation and policies.
- Wrap multi-record writes, reordering, moves, and destructive workflows in
  transactions. Recheck destructive preconditions inside the transaction.
- Preserve the established domain exceptions and stable API error codes when
  extending failure cases.

## Frontend and interaction conventions

- Use TypeScript for new React code and keep shared payload types explicit.
- Build around the application shell: responsive sidebar, selected list view,
  task panel, and optional task-details panel or modal.
- Keep quick-add focused after successful creation. Prevent blank submissions
  and display actionable failure states without losing the user's input.
- Keep active tasks above completed tasks. Completed rows are muted,
  struck-through, restorable, and ordered by most recently completed.
- Make drag handles operable by pointer, touch, and keyboard. Dedicated
  move-up/move-down actions are intentionally absent; keyboard users start a
  drag from the handle and use arrow keys to choose the drop position.
- Preserve focus visibility, semantic labels, keyboard navigation, reduced
  motion, sufficient contrast, and usable touch targets.
- Keep Tailwind CSS 4 configuration CSS-first in `resources/css/app.css` and
  express reusable visual decisions as design tokens.
- Use the project's coral accent and warm-neutral identity consistently. Avoid
  dense dashboards, large data tables, Kanban-first layouts, and ornamental UI
  that slows down capture.
- Do not add another component library or state-management framework without a
  demonstrated need and explicit approval.

## Native boundaries

- Treat everything bundled into a native application as public. Never bundle
  server database credentials, mail credentials, storage credentials, private
  keys, signing secrets, or other server-only values.
- Store native access tokens and sensitive device values in platform secure
  storage, not local storage, plain files, bundled environment files, or SQLite.
- Decide and document caching and offline behaviour before adding either. A
  cached read snapshot must expose freshness and stale state and must not
  silently accept offline mutations.
- Install and allowlist NativePHP plugins deliberately. Review permissions,
  native manifests, data collection, and platform behaviour.
- Do not hand-edit generated `nativephp/` projects for lasting behaviour.
  Prefer supported configuration and regeneration-safe plugins or hooks.
- Verify safe areas, keyboards, app lifecycle, intermittent connectivity, back
  navigation, and deep links on the relevant simulator or device.

## Authentication, files, and security

- First-party browser requests use Laravel sessions and CSRF protection.
  Sanctum protects the versioned API.
- Keep login, uploads, and other abuse-sensitive endpoints appropriately
  rate-limited. Verification and password reset are not exposed in the current
  release.
- Validate uploads by size and actual file type. Profile photos and future user
  files require ownership checks and safe storage; never trust extensions or
  client-provided MIME types alone.
- Never log passwords, passkeys, recovery codes, tokens, secrets, or sensitive
  user content.
- Queue slow or failure-prone external work when appropriate. Jobs must be
  idempotent and safe to retry.

## Testing and quality gates

Add focused coverage for every behavioural change. Prefer feature tests for
HTTP/API behaviour, Inertia flows, authentication, authorization,
validation, transactions, and database invariants. Use unit tests for isolated
domain transformations and services.

Do not remove or weaken tests without explicit approval. Use model factories,
specific response assertions, and the test style already established in the
repository. The current repository uses PHPUnit; the baseline calls for Pest,
so migrate test syntax deliberately rather than mixing styles within one
feature without reason.

Run focused checks while iterating, then the relevant full gates:

```bash
composer test
npm run build
```

Use `composer lint:check` and `composer types:check` independently when a full
test run is not yet warranted. Do not advertise npm scripts that are not
present in `package.json`.

Never run `migrate:fresh`, `db:wipe`, destructive SQL, or production migrations
against an unidentified database. Destructive database commands are permitted
only on a confirmed disposable local/test database or with explicit approval
for the exact target.

## Generated and sensitive files

Do not commit `.env`, credentials, `vendor/`, `node_modules/`, `public/build/`,
generated native build products, user uploads, private media, local databases,
or IDE/simulator state. Use `.env.example` only for safe examples and blank
secret placeholders.

## Documentation expectations

Keep implementation, tests, `AGENTS.md`, `development/scope.md`, dependency
manifests, environment examples, and focused docs consistent. Update the scope
when a feature changes priority or product boundaries; update focused docs when
implementation, setup, architecture, API contracts, or operational behaviour
changes.
