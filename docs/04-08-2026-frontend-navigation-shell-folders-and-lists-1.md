# Implementation Plan: Frontend Phase A — Navigation Shell, Folders & Lists

**Date:** 04-08-2026
**Plan ID:** 1
**Status:** Draft
**Complexity:** Large

## 0. Phase Boundary (read first)

The remaining frontend work does not fit in 1–10 deployable steps. It is split into three
sequential, independently deployable plans:

- **Phase A (this plan)** — the navigation shell: a reactive sidebar folder→list tree,
  a route+page for any list, and full folder/list CRUD (M1, M2, M9, M10). Ends with the
  `docs/project-base.md` "Key acceptance criteria" walkthrough working end to end in the
  browser for the first time (create folder "Work" → create list "Website launch" inside
  it → add tasks → complete → restore → refresh).
- **Phase B** (`04-08-2026-frontend-task-details-due-dates-notes-and-move-2.md`) — task row
  anatomy, due dates (S1), notes (S3), the task details flyout, move-to-list, list header
  actions.
- **Phase C** (`04-08-2026-frontend-reordering-undo-and-polish-3.md`) — manual reordering
  (S4, accessible buttons + drag and drop), undo (S7), interaction/visual polish,
  responsive + accessibility pass.

**Not in any of the three plans:** search (S5), smart lists beyond Inbox/Starred (C1),
API token issuance (see plan `04-08-2026-core-data-model-services-and-v1-api-1.md`, D9/R11),
and anything in the Could/Won't buckets.

**Backend scope:** the domain backend is complete and is not re-planned. This phase makes
exactly **one** additive repository change (an `active_tasks_count` alias, Step 1) and adds
**one** new read-model service (`NavigationService`). No migrations, no schema changes, no
changes to `app/Http/Controllers/Api/V1/*`.

## 1. Requirements Analysis

### Functional Requirements

- [ ] The sidebar shows, in order: Inbox (always first, with its **active** task count),
      Starred, then folders with their lists nested inside, then ungrouped lists (M1, M2, S6).
- [ ] Folders expand and collapse, and the open/closed state survives a page reload (M1).
- [ ] "New list" and "New folder" affordances exist in the sidebar; a folder row also offers
      "New list in this folder" (frontend.md "Main layout").
- [ ] Selecting a list in the sidebar opens it in the main area, with the active item marked
      (M2, frontend.md).
- [ ] Folder CRUD (M1): create, rename, delete. Deleting a non-empty folder asks the user to
      either move its lists out or delete them — it never silently deletes lists.
- [ ] List CRUD (M2): create (inside a folder or ungrouped), rename, delete, move into/out of
      a folder.
- [ ] The Inbox is never renamed or deleted from the UI, and never appears inside a folder.
- [ ] Every folder/list operation updates the interface without a full page reload.
- [ ] The sidebar opens from a menu button at mobile widths (M9).
- [ ] Empty and error states (M10): no folders, no lists, save failure, load failure.

### Non-Functional Requirements

- [ ] **Blade + Livewire only.** No SPA framework, no client-side state library, no new npm
      dependency in this phase.
- [ ] **Layering.** Livewire components call Services/Repositories **in-process**. No `Http::`,
      no `api/v1` string, no query building outside repositories.
      `tests/Feature/Architecture/LayeringTest.php` stays green.
- [ ] **Authorization on every mutation.** `Gate::authorize()` before every service call;
      cross-user ids must be impossible to act on (the services already re-resolve references
      scoped by owner — the UI does not weaken that).
- [ ] **No N+1.** The whole sidebar renders in a bounded, asserted number of queries.
      `Model::preventLazyLoading()` is active outside production.
- [ ] **Deployable after every step.** No step leaves a dangling route, nav link or view.
- [ ] **Quality gates.** `composer test` (config clear → pint → phpstan level 7 → phpunit)
      green after every step. `declare(strict_types=1);` in every new PHP file.
- [ ] **Escaped output.** Folder and list names are user input rendered in the chrome of every
      page — `{{ }}` only, never `{!! !!}`.

## 2. Architecture Review

### Existing Codebase Patterns (verified)

- `app/Services/{FolderService,TaskListService,TaskService}` + `app/Repositories/*` +
  `app/Policies/*` are complete for this phase's needs. Signatures worth memorising:
  - `TaskListService::create(User, string $name, ?int $folderId): TaskList`
  - `TaskListService::update(TaskList, User, string $name, ?int $folderId): TaskList`
    — **replace semantics**: rename and move are the same call, so the caller must always
    pass the current name when it only wants to move (and vice versa).
  - `TaskListService::delete(TaskList)` throws `DefaultTaskListCannotBeDeletedException`.
  - `FolderService::delete(Folder)` throws `FolderNotEmptyException` when it still holds lists.
  - `FolderService::detachLists(Folder)` **moves the lists out AND deletes the folder**
    (verified in `EloquentFolderRepository::detachLists()`). Do **not** call `delete()` after it.
  - `FolderService::deleteWithLists(Folder)` cascades folder + lists + tasks.
  - `FolderRepositoryInterface::hasLists(Folder): bool` — how the dialog decides which
    confirmation to show.
- `app/Livewire/Tasks/TaskPanel.php` is already written generically against `taskListId`
  (`#[Locked] public int $taskListId`, `Gate::authorize('view', ...)` on mount) and is reusable
  for any list without modification.
- Web controllers (`InboxController`) are thin, constructor-inject a service, return a view.
- `resources/views/components/app-sidebar-nav.blade.php` is a static Blade partial included by
  both `layouts/app/sidebar.blade.php` (line 15) and `layouts/app/header.blade.php` (line 57 —
  a starter-kit layout nothing currently renders).
- `flux:sidebar` already has `collapsible="mobile"` and a `flux:sidebar.toggle` in the mobile
  header — **M9's "sidebar opens from a menu button" already works** and is not re-implemented.
- `flux:sidebar.item` supports a `badge` prop; `flux:sidebar.group` supports
  `expandable`/`expanded`/`heading`/`icon`, but its open state lives in a `<ui-disclosure>` web
  component that we cannot bind to Alpine — see D5.
- `flux:modal` (free tier) supports `name`, `wire:model`, `variant="flyout"`, and
  `Flux::modal('name')->show()/->close()` from PHP.
- Tests are PHPUnit class-style with `RefreshDatabase`, `test_snake_case` names,
  `Livewire::actingAs(...)->test(Component::class, [...])`.

### Affected Areas

| Area | Change |
|---|---|
| `app/Repositories/EloquentTaskListRepository.php` | **+1 `withCount` alias** (`active_tasks_count`) |
| `app/Repositories/Contracts/TaskListRepositoryInterface.php` | docblock only (documents both counts) |
| `app/Models/TaskList.php` | `@property-read int\|null $active_tasks_count` docblock |
| `app/Services/NavigationService.php` | **new** — the sidebar read model |
| `app/Services/Data/{NavigationTree,NavigationFolder}.php` | **new** — readonly DTOs |
| `app/Livewire/Navigation/{Sidebar,FolderDialog,ListDialog}.php` | **new** |
| `resources/views/livewire/navigation/*.blade.php` | **new** |
| `resources/views/components/app-sidebar-nav.blade.php` | **deleted** (replaced by the component) |
| `resources/views/layouts/app/{sidebar,header}.blade.php` | render `<livewire:navigation.sidebar />` |
| `app/Http/Controllers/TaskListController.php` | **new** — the `/lists/{list}` page |
| `resources/views/lists/show.blade.php` | **new** |
| `routes/web.php` | `+ lists/{list}` route |
| `tests/Feature/{Services,Livewire/Navigation,TaskListPageTest}` | **new**; `SidebarNavigationTest` updated |

Nothing under `app/Http/Controllers/Api/V1`, `routes/api.php`, `database/` or `app/Models`
(beyond one docblock) is touched.

### Reusable Components

- `TaskPanel` — mounted unchanged by the new list page.
- `FolderService` / `TaskListService` — every mutation in this phase already exists as a
  service method. **No new business logic is written in this phase.**
- `Flux::toast(variant: 'danger', text: ...)` — the existing error-feedback pattern from
  `TaskPanel::renameTask()`.
- Alpine `$persist` — already used for the Completed section collapse state; reused verbatim
  for folder disclosure state.
- `flux:sidebar.item` `badge` prop — the Inbox/list counts, no custom markup.

### Architecture Decision

**A1 — The sidebar becomes a Livewire component rendered by the layout.**
`<livewire:navigation.sidebar />` replaces `<x-app-sidebar-nav />` in both layouts.
Rejected: (a) keep it a static Blade partial and full-page-reload after every folder/list
mutation — violates the explicit "no full-page reloads" constraint; (b) hoist nav state into
each page component — duplicated in three-plus pages and couples navigation to page identity.
The component is **not** wrapped in `@persist`: persisting it across `wire:navigate`
navigations would freeze the "current item" highlight, which is the one thing it must get right.

**A2 — Current-selection state is captured at mount, not read from `request()` at render.**
Once the sidebar is a Livewire component, a later AJAX re-render happens on the
`livewire.update` route, so `request()->routeIs('inbox')` / `request()->route('list')` would
silently return the wrong thing. `Sidebar::mount()` therefore reads the route once into
`public ?int $currentTaskListId` and `public string $currentRouteName`, and the view compares
against those. `wire:navigate` re-mounts the component on every page change, so the values stay
correct. This is subtle, non-obvious, and is the single likeliest source of a "why is nothing
highlighted" bug.

**A3 — Opening a list is a real route: `GET /lists/{list}` → `TaskListController` → view →
`TaskPanel`, linked with `wire:navigate`.**
`wire:navigate` gives SPA-feel navigation (fetch + DOM swap, no full reload) while keeping deep
links, browser back/forward, and the existing thin-controller pattern. Rejected: a single-page
shell that swaps the panel via Livewire events — needs `#[Url]` state juggling, breaks
bookmarking of a list, and makes the Inbox a special case of itself. The Inbox keeps its own
canonical `/inbox` URL; `/lists/{inboxId}` redirects there so there is exactly one URL per list.

**A4 — The nav tree is a read model owned by a new `NavigationService`, not assembled in Blade
or in the component.**
`NavigationService::treeFor(User): NavigationTree` composes `TaskListService::inboxFor()`,
`FolderRepositoryInterface::allForUser()` and `TaskListRepositoryInterface::allForUser()` into
a readonly DTO (`inbox`, `folders[] { folder, lists }`, `ungroupedLists`). Rejected:
(a) grouping folders and lists inside the Livewire component — that composition *is* a read
model, it would be untestable without Livewire, and it would be duplicated the day a
`GET /api/v1/navigation` endpoint is wanted; (b) adding `treeFor()` to `TaskListService` — the
tree spans folders *and* lists and is a distinct reason to change (SRP).
Grouping is done on the `folder_id` **attribute** (`$lists->groupBy('folder_id')`), never by
touching `$list->folder`, so `preventLazyLoading()` cannot be tripped.

**A5 — Active counts are an additive repository change, not a new query in the UI.**
`EloquentTaskListRepository::allForUser()` gains
`->withCount(['tasks as active_tasks_count' => fn (Builder $q) => $q->where('is_completed', false)])`
alongside the existing `withCount('tasks')`. Additive and invisible to the API
(`TaskListResource` exposes `whenCounted('tasks')` only, so the wire format does not change).
Rejected: a separate `countsFor()` repository method (a second query for data the same query can
carry) and computing counts in PHP from loaded tasks (loads every task in the account to render
a sidebar).

**A6 — Folder open/closed state is client-side (`$persist` → localStorage), keyed by folder id.**
Matches the existing precedent (`task-panel-completed-open-{id}`), needs no schema change, and
satisfies the requirement as written ("remember whether the user left it open or closed").
Trade-off, stated openly: it is per-browser, not per-account, and does not follow the user to
another device. The alternative — a `user_preferences` table or a JSON column plus a service —
is a schema change and is deliberately deferred; it can be added later without touching the
markup, because the only thing that changes is where the initial `open` value comes from.
Because Flux's `sidebar.group expandable` state lives in a `<ui-disclosure>` element we cannot
bind, folder rows use our own markup (button + `x-show` + `x-collapse`) inside
`flux:sidebar.nav`, containing standard `flux:sidebar.item`s.

**A7 — Three focused Livewire components, not one god component.**
`Navigation\Sidebar` renders the tree and owns navigation state only.
`Navigation\FolderDialog` owns folder create/rename/delete (including the three-way
non-empty-delete choice). `Navigation\ListDialog` owns list create/rename/move/delete.
They communicate with one documented event contract:
- `folder-dialog-open` / `list-dialog-open` (payload: `mode`, optional `folderId`/`listId`) —
  dispatched by the sidebar, listened to by the dialogs.
- `navigation-changed` — dispatched by the dialogs after any successful mutation; the sidebar
  listens and busts its computed cache (`unset($this->tree)`).
Rejected: putting all CRUD inside `Sidebar` (a ~300-line component with six modals, against the
"small, single UI concern" rule) and one generic `CrudDialog` (premature abstraction over two
genuinely different validation/branching shapes).

**A8 — A destructive action that removes the page you are on redirects to the Inbox.**
Deleting the currently-open list (directly, or via "delete folder and its lists") would leave
the user on a 403/404. The dialogs receive the current list id and, when they destroyed it,
call `$this->redirectRoute('inbox', navigate: true)`. Stated as a rule so it is not
rediscovered as a bug in three separate places.

**A9 — The Inbox's rename/delete affordances are simply not rendered.**
`TaskListService::update()` would happily rename the Inbox (only *moving into a folder* and
*deleting* are blocked server-side). frontend.md says the Inbox is never renamed. This phase
enforces it in the UI (`@if (! $list->is_default)`) and records the mismatch as a follow-up
(optional service-level guard), rather than silently changing service behaviour that the API
contract already depends on.

## 3. Step Breakdown

### Step 1: Navigation read model — `NavigationService`, DTOs, active counts

- **What:** A tested, UI-free read model for the sidebar. Nothing renders it yet.
- **Where:**
  - `app/Services/NavigationService.php`
  - `app/Services/Data/NavigationTree.php`, `app/Services/Data/NavigationFolder.php`
  - `app/Repositories/EloquentTaskListRepository.php` (+ interface docblock)
  - `app/Models/TaskList.php` (docblock only)
  - `tests/Feature/Services/NavigationServiceTest.php`
  - `tests/Feature/Repositories/EloquentTaskListRepositoryTest.php` (extend)
- **How:**
  - Repository: add the `active_tasks_count` alias described in A5. Import
    `Illuminate\Database\Eloquent\Builder` and type the closure for phpstan level 7. Update the
    interface docblock on `allForUser()` to state that both `tasks_count` and
    `active_tasks_count` are present. Add `@property-read int|null $active_tasks_count` (and
    `$tasks_count`, currently missing) to `TaskList`.
  - `NavigationFolder`: `final readonly` with `public Folder $folder` and
    `public Collection<int, TaskList> $lists`.
  - `NavigationTree`: `final readonly` with `public TaskList $inbox`,
    `public array<int, NavigationFolder> $folders`, `public Collection<int, TaskList> $ungroupedLists`.
  - `NavigationService::treeFor(User): NavigationTree` — constructor-inject
    `FolderRepositoryInterface`, `TaskListRepositoryInterface`, `TaskListService`.
    Resolve the inbox via `TaskListService::inboxFor()` (idempotent/self-healing, D5 of the
    Phase 1 plan). Fetch all lists once, `reject(is_default)`, `groupBy('folder_id')` on the
    **attribute**, then map ordered folders to `NavigationFolder`s. Ungrouped = the `null` group.
    Ordering comes from the repositories (`position`, `id`) — no sorting in the service.
- **Test:** `NavigationServiceTest` — the tree exposes the inbox and excludes it from
  `ungroupedLists`; folders come back in position order with their lists in position order;
  a folder with no lists yields an empty collection (not missing); another user's folders and
  lists never appear; `active_tasks_count` counts only incomplete tasks; a user with no folders
  and no extra lists still gets a valid tree. Wrap one assertion in `DB::listen`/`assertQueryCount`
  style (or rely on `preventLazyLoading` plus an explicit expected-query-count assertion) to lock
  the tree at ≤ 4 queries.
- **Complexity:** Medium

### Step 2: `Navigation\Sidebar` component — render the tree read-only

- **What:** The static nav partial is replaced by a live tree: Inbox (+ active badge), Starred,
  folders (expand/collapse, persisted), nested lists, ungrouped lists, current-item highlight.
  No create/rename/delete yet — fully deployable.
- **Where:**
  - `app/Livewire/Navigation/Sidebar.php`
  - `resources/views/livewire/navigation/sidebar.blade.php`
  - `resources/views/layouts/app/sidebar.blade.php`, `resources/views/layouts/app/header.blade.php`
  - delete `resources/views/components/app-sidebar-nav.blade.php`
  - `tests/Feature/Livewire/Navigation/SidebarTest.php`; update `tests/Feature/SidebarNavigationTest.php`
- **How:**
  - Component: `#[Computed] tree(): NavigationTree` → `NavigationService::treeFor(Auth::user())`.
    `mount()` sets `$this->currentRouteName = (string) request()->route()?->getName()` and
    `$this->currentTaskListId = (int) request()->route('list') ?: null` (A2), and aborts 403 for
    a guest. `#[On('navigation-changed')] public function refreshTree(): void { unset($this->tree); }`.
    No other public methods in this step.
  - View: keep the existing `flux:sidebar.nav` + `data-nav="primary"` wrapper (`SidebarNavigationTest`
    asserts on it). Inbox item: `icon="inbox"`, `:href="route('inbox')"`,
    `:current="$currentRouteName === 'inbox'"`, `:badge="$this->tree->inbox->active_tasks_count ?: null"`,
    `wire:navigate`. Starred unchanged.
  - Folder row: our own markup (A6) —
    `<div x-data="{ open: $persist(true).as('folder-open-{{ $folder->id }}') }">`, a button with a
    chevron that toggles `open`, and `<div x-show="open" x-collapse>` holding one
    `flux:sidebar.item icon="list-bullet"` per list (`:href="route('lists.show', $list)"` —
    added in Step 3, so **do Step 3 before wiring hrefs**, or land Step 2 with the lists rendered
    as non-links and convert them in Step 3; either order is deployable, the plan assumes the latter).
  - Ungrouped lists render after the folders, at the top level.
  - Escape everything with `{{ }}`; `truncate` long names; `wire:key` on every folder and list row.
  - Both layouts now render `<livewire:navigation.sidebar />`. Run `php artisan view:clear`
    (Blaze folds Flux components at compile time).
- **Test:** `SidebarTest` — folders and their lists render with names; another user's folders/lists
  do not; the Inbox badge shows the active count and disappears at zero; the current list is marked
  `data-current` when mounted on a list route; `navigation-changed` refreshes the tree after a list
  is created out-of-band. Update `SidebarNavigationTest` (still no "Dashboard"/"Platform", Inbox and
  Starred links still present). Assert no lazy-loading violation.
- **Complexity:** Large

### Step 3: `/lists/{list}` page

- **What:** Any list can be opened at its own URL and shows the existing `TaskPanel`.
- **Where:** `routes/web.php`, `app/Http/Controllers/TaskListController.php`,
  `resources/views/lists/show.blade.php`, sidebar view (link the list items),
  `tests/Feature/TaskListPageTest.php`
- **How:**
  - `Route::get('lists/{list}', TaskListController::class)->whereNumber('list')->name('lists.show');`
    inside the existing `auth` + `verified` group. `whereNumber` mirrors the API convention and
    keeps future literal segments (`lists/order`) from colliding.
  - Controller: invokable, `__invoke(TaskList $list): View|RedirectResponse`. Implicit route model
    binding (accepted framework infrastructure, D11 of the Phase 1 plan), then
    `$this->authorize('view', $list)` — `AuthorizesRequests` is already on the base controller.
    If `$list->is_default`, `return redirect()->route('inbox')` (A3). No queries, no services.
  - View: `<x-layouts::app :title="$list->name">` wrapping
    `<livewire:tasks.task-panel :task-list-id="$list->id" :wire:key="'list-'.$list->id" />`.
  - Sidebar list items get `:href="route('lists.show', $list)"` +
    `:current="$currentTaskListId === $list->id"` + `wire:navigate`.
- **Test:** `TaskListPageTest` — owner gets 200 and sees the list name and its task titles;
  another user's list → 403; guest → redirect to login; the Inbox's id redirects to `/inbox`;
  `/lists/abc` does not match the route (404). Extend `SidebarTest` to assert the list link href.
- **Complexity:** Small

### Step 4: Folder create, rename and delete (M1)

- **What:** Folders are fully manageable from the sidebar, including the "move lists out vs
  delete lists" choice.
- **Where:** `app/Livewire/Navigation/FolderDialog.php`,
  `resources/views/livewire/navigation/folder-dialog.blade.php`, sidebar view (buttons),
  `tests/Feature/Livewire/Navigation/FolderDialogTest.php`
- **How:**
  - `FolderDialog` public state: `?int $folderId`, `string $name = ''`, `string $mode`
    (`create|rename|delete`), `bool $folderHasLists`, `?int $currentTaskListId` (passed in from
    the sidebar view for A8).
  - Opened by `#[On('folder-dialog-open')] open(string $mode, ?int $folderId = null)`, which
    resolves the folder via `FolderRepositoryInterface::findForUser()` (404 when null),
    `Gate::authorize(...)`, sets `$folderHasLists = $folders->hasLists($folder)` for the delete
    mode, then `Flux::modal('folder-dialog')->show()`.
  - Submit paths, each `Gate::authorize` → service call → `Flux::modal(...)->close()` →
    `$this->dispatch('navigation-changed')` → success toast:
    - create → `FolderService::create($user, $this->name)`
    - rename → `FolderService::rename($folder, $this->name)`
    - delete, empty folder → `FolderService::delete($folder)`
    - delete, non-empty → **two labelled buttons**: "Move lists out" →
      `FolderService::detachLists($folder)` (**which also deletes the folder** — do not call
      `delete()` afterwards), and "Delete folder and its lists" → `FolderService::deleteWithLists($folder)`.
  - Validation: `$this->validate(['name' => ['required', 'string', 'max:255']])` before create/rename;
    catch `FolderNotEmptyException` (defence in depth) and any `DomainException` → `Flux::toast(variant: 'danger')`.
  - After `deleteWithLists`, if `$currentTaskListId` belonged to that folder,
    `$this->redirectRoute('inbox', navigate: true)` (A8).
  - Sidebar view adds: a "New folder" button in the nav footer/header area
    (`wire:click="$dispatch('folder-dialog-open', { mode: 'create' })"`), and a per-folder
    `flux:dropdown` menu with Rename / Delete.
- **Test:** `FolderDialogTest` — creating persists and dispatches `navigation-changed`; blank and
  256-char names fail validation and persist nothing; rename persists; deleting an empty folder
  removes it; "move lists out" deletes the folder and leaves its lists with `folder_id = null`
  **and their tasks intact**; "delete with lists" removes lists and tasks; opening the dialog for
  another user's folder is denied; deleting the folder containing the open list redirects to the inbox.
- **Complexity:** Large

### Step 5: List create, rename, move and delete (M2)

- **What:** Lists are fully manageable from the sidebar, including moving into/out of a folder.
- **Where:** `app/Livewire/Navigation/ListDialog.php`,
  `resources/views/livewire/navigation/list-dialog.blade.php`, sidebar view,
  `tests/Feature/Livewire/Navigation/ListDialogTest.php`
- **How:**
  - State: `?int $listId`, `string $name = ''`, `?int $folderId = null`, `string $mode`
    (`create|rename|delete`), `?int $currentTaskListId`.
  - Opened by `#[On('list-dialog-open')] open(string $mode, ?int $listId = null, ?int $folderId = null)`
    — the `folderId` argument pre-selects the folder when the user clicked "+" on a folder row.
  - The folder `<flux:select>` is populated from `FolderService::allFor($user)` with a
    "No folder" option (`null`) — this is how a list is moved out of a folder.
  - Submit:
    - create → `TaskListService::create($user, $this->name, $this->folderId)`
    - rename/move → `TaskListService::update($list, $user, $this->name, $this->folderId)`
      — **always send both** (replace semantics; see Architecture Review).
    - delete → confirm copy naming the list, then `TaskListService::delete($list)`; if it was the
      open list, redirect to the inbox (A8).
  - Validation: `name` required/string/max:255; `folder_id` nullable integer. Do **not** re-implement
    ownership checking in the component — `TaskListService` re-resolves the folder scoped to the user
    and throws `FolderNotFoundException`; catch `DomainException` → danger toast. (The select can
    only offer the user's own folders, so this is defence in depth.)
  - Sidebar adds: a "New list" button at nav level, a "+" on each folder row (dispatches with that
    `folderId`), and a per-list `flux:dropdown` with Rename / Move / Delete — **rendered only when
    `! $list->is_default`** (A9).
- **Test:** `ListDialogTest` — create ungrouped and create in a folder (appears nested); rename;
  move into a folder and back out **without losing tasks** (assert task count before/after — this
  is an explicit user story); delete removes the list and its tasks and redirects when it was open;
  the Inbox exposes no rename/delete affordance and `TaskListService::delete()` on it surfaces the
  domain message as a toast rather than a 500; another user's list is denied.
- **Complexity:** Large

### Step 6: Empty, loading and error states + docs (M10)

- **What:** The navigation and list pages explain themselves when there is nothing there, show
  progress while working, and fail loudly but gracefully.
- **Where:** `resources/views/livewire/navigation/*.blade.php`,
  `resources/views/lists/show.blade.php`, `app/Livewire/Navigation/Sidebar.php`,
  `README.md`, `.claude/CLAUDE.md` (structure block: `app/Livewire/Navigation`),
  `tests/Feature/Livewire/Navigation/SidebarTest.php` (extend)
- **How:**
  - Sidebar empty state: when there are no folders and no non-default lists, render a short
    prompt plus the two CTA buttons ("Create your first list" / "Create a folder") instead of an
    empty gap.
  - Loading: `wire:loading.attr="disabled"` + `wire:loading.delay` spinner on every dialog submit
    button; `wire:loading.class="opacity-50"` on the tree during a refresh. (Cheap, and it is what
    makes the UI feel responsive rather than frozen — frontend.md "Interaction behavior".)
  - Error: one shared pattern — catch `App\Exceptions\DomainException` in the dialogs, surface
    `$e->getMessage()` via `Flux::toast(variant: 'danger')`; validation errors stay inline via
    `flux:error`. Nothing catches `\Throwable`.
  - Confirm the mobile drawer renders the whole tree correctly at ~375px (the toggle already exists).
  - README: document the new `/lists/{list}` route and that the web UI calls services in-process
    (never `/api/v1`), so the invariant is written down where a newcomer will read it.
- **Test:** Extend `SidebarTest` — a brand-new user sees the empty-state copy and the CTAs; a user
  with lists does not. Manual check at 375 / 768 / 1280px. Full `composer test` green.
- **Complexity:** Small

## 4. Risk Assessment

### Risks

- **R1 (High) — Stale "current item" highlighting.** Once the sidebar is a Livewire component,
  `request()->routeIs()` evaluates against `livewire.update` on every AJAX re-render, so the
  highlight silently moves or vanishes. Easy to miss because the first page load looks correct.
- **R2 (High) — `detachLists()` also deletes the folder.** A reader of the method name will assume
  it only moves lists out and will follow it with `delete()`, producing a "folder not found" error
  or, worse, deleting the wrong thing.
- **R3 (Medium) — Destructive actions orphan the current page.** Deleting the open list (directly or
  via folder cascade) leaves the user on a 403/404 with a sidebar that still looks fine.
- **R4 (Medium) — Sidebar N+1.** The tree touches folders, lists and counts on every page render;
  a careless `$list->folder->name` in Blade turns into one query per list — and with
  `preventLazyLoading()` on, into a hard exception in local/CI (which is the good outcome).
- **R5 (Medium) — Replace semantics on `TaskListService::update()`.** Calling it to move a list while
  passing an empty `$name` silently renames the list to an empty string, which then fails validation
  nowhere because the service only trims.
- **R6 (Medium) — Every page now boots an extra Livewire component.** The sidebar renders on all
  authenticated pages, including settings and auth-adjacent screens using the same layout. A bug or
  an exception in it takes down every page, not one.
- **R7 (Low/Medium) — Alpine `$persist` keys leak between accounts on a shared browser.** Folder ids
  are per-user; user B on the same browser inherits user A's open/closed keys for colliding ids.
  Cosmetic only, but worth knowing before someone files it as a bug.
- **R8 (Low) — Blaze compile-time folding / stale compiled views.** Flux components are folded at
  compile time; `composer test` runs `config:clear` but **not** `view:clear`, so nav changes can
  appear not to take effect.
- **R9 (Low) — Route collision.** A future literal `lists/order` web route would be swallowed by
  `lists/{list}` if registered after it.

### Mitigations

- **R1:** A2 makes it a design rule, Step 2 captures route state in `mount()`, and `SidebarTest`
  asserts the current marker **after** a component update, not just on first render.
- **R2:** Called out in the Architecture Review, in Step 4's How, and asserted from both directions
  in `FolderDialogTest` (folder gone **and** lists surviving with `folder_id = null`).
- **R3:** A8 makes the redirect a rule; both dialog tests assert it.
- **R4:** The tree is built by `NavigationService` from two repository calls with eager loading
  already specified in the contracts; Step 1's test pins the query count; `preventLazyLoading()`
  converts a regression into a failing test.
- **R5:** Step 5 states "always send both"; `ListDialogTest` covers move-without-rename and
  rename-without-move explicitly, and validation runs before the service call.
- **R6:** Keep `Sidebar` to two computed reads and one event listener; every mutation lives in the
  dialogs. `mount()` aborts cleanly for guests. If the sidebar ever needs to do real work, make it
  `#[Lazy]` with a `flux:skeleton` placeholder — noted as the escape hatch, not done now.
- **R7:** Accepted. If it ever matters, key the persist string with the user id
  (`.as('u{{ auth()->id() }}-folder-open-{{ $folder->id }}')`) — a one-line change.
- **R8:** `php artisan view:clear` is an explicit action in Steps 2, 4, 5 and 6.
- **R9:** `->whereNumber('list')` on the route from the moment it is created (Step 3), matching the
  API's convention.

### Fallbacks

- If making the sidebar reactive proves disruptive, the interim fallback is to keep it a Blade
  partial and have each dialog `redirect()` after mutating (correct data, one full reload per
  folder/list change). Ugly against the "no full reload" constraint, but shippable, and the
  component swap is contained to two layout lines.
- If `NavigationService` feels like ceremony in review, the fallback is `treeFor()` on
  `TaskListService` returning the same DTO — same data, one fewer class, weaker SRP.
- If the custom folder disclosure fights Flux's collapsed-desktop styling, fall back to
  `flux:sidebar.group expandable` with `:expanded="true"` and drop cross-reload persistence for
  folders in this phase (record it as a known gap against M1 rather than shipping broken chrome).
- If Step 4 or Step 5 grows beyond one session, split each into (a) create + rename, (b) delete
  and its dialogs. Both halves are independently deployable.

## 5. Execution Checklist

- [ ] **Step 1:** `active_tasks_count` alias + docblocks; `NavigationService` + `NavigationTree` /
      `NavigationFolder` DTOs; `NavigationServiceTest` incl. query-count and cross-user isolation.
      Run `composer test`.
- [ ] **Step 2:** `Navigation\Sidebar` + view; route state captured in `mount()`; folder disclosure
      with `$persist`; Inbox active-count badge; both layouts updated; delete
      `components/app-sidebar-nav.blade.php`; `view:clear`; `SidebarTest` + updated
      `SidebarNavigationTest`. Run `composer test`.
- [ ] **Step 3:** `lists/{list}` route (`whereNumber`), `TaskListController`, `lists/show.blade.php`
      mounting `TaskPanel`, Inbox-id redirect, sidebar links with `wire:navigate`; `TaskListPageTest`.
      Run `composer test`.
- [ ] **Step 4:** `Navigation\FolderDialog` + view + sidebar affordances; three-way non-empty delete;
      `navigation-changed` event; redirect-when-open-list-destroyed; `FolderDialogTest`;
      `view:clear`. Run `composer test`.
- [ ] **Step 5:** `Navigation\ListDialog` + view + sidebar affordances; create/rename/move/delete;
      Inbox affordances hidden; `ListDialogTest` incl. move-without-losing-tasks; `view:clear`.
      Run `composer test`.
- [ ] **Step 6:** Empty/loading/error states; mobile drawer check at three widths; README + 
      `.claude/CLAUDE.md` structure block updated. Run `composer test`.
- [ ] **Follow-ups (not in scope):** server-side (cross-device) persistence of folder collapse state;
      an optional `TaskListService` guard preventing the Inbox from being renamed (A9);
      `GET /api/v1/navigation` if a mobile client wants the same tree.
```
