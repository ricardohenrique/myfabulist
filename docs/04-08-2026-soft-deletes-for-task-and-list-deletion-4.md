# Implementation Plan: Soft Deletes for Task and List Deletion

**Date:** 04-08-2026
**Plan ID:** 4
**Status:** Draft
**Complexity:** Medium

> **Why this plan exists.** Phase C (`docs/04-08-2026-frontend-reordering-undo-and-polish-3.md`, decision C4)
> ships an undo affordance for completing, moving and starring a task, and explicitly **excludes**
> undo-after-deletion because deletion is a hard delete today — "undo" would mean re-creating a
> different row with a new id, which is lossy and dishonest. C4 recorded three options and
> recommended **soft deletes**, pending approval. The user has approved soft deletes. This plan is
> the backend change that makes undo-after-delete honest.
>
> **In scope:** task deletion (`TaskService::delete()`) and list deletion (`TaskListService::delete()`)
> — exactly the two deletion cases named in `docs/project-base.md` S7.
> **Out of scope:** folder deletion (S7 does not list it; Phase A already gives folder deletion its
> own confirm-or-move-lists flow, not undo), any "Trash" / recovery UI, any purge job, and the
> Livewire undo-bar wiring itself (that is Phase C, Step 4 — see the closing note in §6).

---

## 1. Requirements Analysis

### Functional Requirements

- [ ] Deleting a task marks it deleted instead of destroying the row; the task disappears from every
      read model (list panel active/completed sections, Starred, API reads) exactly as it does today.
- [ ] A deleted task can be un-deleted, restoring it with its original id, title, note, due date,
      star state, completion state, list membership and position intact.
- [ ] Deleting a list marks it deleted instead of destroying the row; the list disappears from the
      list index, sidebar reads, move-to-list targets and API reads exactly as it does today.
- [ ] A deleted list can be un-deleted, bringing back the list **and every task it contained**, with
      all task state intact.
- [ ] The tasks of a soft-deleted list are *not* touched by the delete, and are *not* visible
      anywhere while their list is deleted (specifically: they must not leak into Starred, which is
      the only cross-list task query in the codebase).
- [ ] "Un-delete" is a distinct, unambiguously-named operation from the existing
      `TaskService::restore()` ("un-complete a completed task"). The two must never be confusable at
      the service, repository or policy layer.
- [ ] The Inbox remains undeletable (`DefaultTaskListCannotBeDeletedException` + `TaskListPolicy::delete`)
      and therefore also un-undeletable — no new bypass is introduced.
- [ ] Folder deletion behaviour is unchanged and remains a real, irreversible delete, including the
      "delete the folder and its lists" path.

### Non-Functional Requirements

- [ ] **API contract stability.** `DELETE /api/v1/tasks/{task}` and `DELETE /api/v1/lists/{list}`
      keep returning `204 No Content`; a subsequent `GET` of the deleted resource keeps returning
      `404`; no new field (`deleted_at`) is added to any V1 resource. A soft-deleted resource is
      indistinguishable from a destroyed one across the V1 surface.
- [ ] **Layering.** All new query behaviour lives in `app/Repositories`; services keep their
      no-query rule; Livewire keeps calling services in-process.
      `tests/Feature/Architecture/LayeringTest.php` must stay green untouched.
- [ ] **Security.** A deleted resource must never become reachable by another user, and a deleted
      list must never become a valid move target or reorder target. Ownership scoping applies to the
      un-delete lookups exactly as it does to the live ones.
- [ ] **No silent behaviour drift.** Every place that previously relied on a physical row
      disappearing (FK cascade, `assertDatabaseMissing`, `Rule::exists`) is inspected and either
      preserved deliberately or changed deliberately — nothing is left to the global scope by luck.
- [ ] **Quality gate.** `composer test` (config clear → Pint → PHPStan level 7 → PHPUnit) green at
      the end of every step.
- [ ] **Deployability.** Every step leaves the app in a working, shippable state. The schema change
      is additive and nullable — no backfill, no downtime concern.

---

## 2. Architecture Review

### Existing Codebase Patterns

- **Layering.** `Livewire | API Controller → Service → Repository → Model`. Repositories are the only
  layer that builds queries; `tests/Feature/Architecture/LayeringTest.php` statically forbids `DB::`,
  `::query(` and `->where(` in `app/Services` and `app/Http/Controllers`.
- **Repository contracts.** `app/Repositories/Contracts/{TaskRepositoryInterface,TaskListRepositoryInterface,FolderRepositoryInterface}`,
  implemented by `app/Repositories/Eloquent*Repository.php`. `LayeringTest` asserts every class in
  `app/Repositories` implements one of these contracts, so new methods must be added to the interface,
  not just the implementation.
- **Domain exceptions.** `App\Exceptions\DomainException` (abstract, `errorCode()` + `httpStatus()`
  defaulting to 422), rendered centrally in `bootstrap/app.php` as
  `{"message": ..., "error_code": ...}`. Named static constructors, e.g.
  `DefaultTaskListCannotBeDeletedException::for($taskList)`.
- **Repository writes** use `forceFill([...])->save()` and `DB::transaction(...)` for multi-write
  operations (`EloquentFolderRepository::deleteWithLists/detachLists`, `applyOrder`).
- **Authorization** is policy-based (`TaskPolicy`, `TaskListPolicy`), auto-discovered; API controllers
  call `$this->authorize(...)`, Livewire calls `Gate::authorize(...)`.

### Affected Areas (verified by reading the files)

**Schema**
- `database/migrations/2026_08_04_100002_create_tasks_table.php` — `tasks.task_list_id` is
  `constrained()->cascadeOnDelete()`; `tasks.user_id` is `cascadeOnDelete()`. No unique constraint on
  `(task_list_id, position)`.
- `database/migrations/2026_08_04_100001_create_task_lists_table.php` — `task_lists.folder_id` is
  `constrained()->nullOnDelete()`; `task_lists.user_id` is `cascadeOnDelete()`.
- **New:** `database/migrations/2026_08_04_110000_add_deleted_at_to_tasks_and_task_lists_tables.php`.

**Models**
- `app/Models/Task.php`, `app/Models/TaskList.php` — add `SoftDeletes`, add `@property Carbon|null $deleted_at`
  to the class docblock (PHPStan level 7 + Larastan).

**Services**
- `app/Services/TaskService.php` — `delete()` (line 104) becomes a soft delete implicitly; **new**
  `undelete()`. Note the existing `restore()` (line 79) means *un-complete* — do not touch it.
- `app/Services/TaskListService.php` — `delete()` (line 76) becomes a soft delete implicitly; **new**
  `undelete()`. This class currently has no restore concept at all.

**Repositories**
- `app/Repositories/EloquentTaskRepository.php` — `delete()` (line 139) becomes soft via the trait;
  **new** `findDeletedForUser()` + `undelete()`. `starredForUser()` (line 48) needs a live-list filter.
  `nextPosition()` (line 144) and `applyOrder()`/`idsForList()` become trashed-blind for free via the
  global scope (correct).
- `app/Repositories/EloquentTaskListRepository.php` — `delete()` (line 86) becomes soft via the trait;
  **new** `findDeletedForUser()` + `undelete()`. `allForUser()`, `findForUser()`, `findDefaultFor()`,
  `nextPosition()`, `applyOrder()` all become trashed-blind for free (correct).
- `app/Repositories/EloquentFolderRepository.php` — **behaviour break to pin down.**
  `deleteWithLists()` (line 60) calls `$folder->taskLists()->delete()`. Once `TaskList` uses
  `SoftDeletes`, that mass delete silently becomes a mass *soft* delete, the FK cascade never fires,
  and the lists' tasks survive with no way to reach them. Must become `forceDelete()` (see D5).
  `hasLists()` (line 76) and `detachLists()` (line 68) become trashed-blind (acceptable, see D5).
- `app/Repositories/Contracts/TaskRepositoryInterface.php`, `.../TaskListRepositoryInterface.php` —
  new method signatures + docblocks.

**Exceptions**
- **New:** `app/Exceptions/TaskCannotBeUndeletedException.php`.

**HTTP / validation**
- `app/Http/Requests/Api/V1/MoveTaskRequest.php:28` and
  `app/Http/Requests/Api/V1/UpdateTaskListOrderRequest.php:32` use
  `Rule::exists('task_lists', 'id')->where('user_id', ...)`. The `exists` rule queries the **table**,
  not the model, so it ignores the soft-delete global scope and would accept a trashed list id.
  Add `->whereNull('deleted_at')`.
  (Note: this is a correctness/consistency fix, not a security hole — `TaskService::move()` re-resolves
  the target through `TaskListRepository::findForUser()`, which *is* scoped, so a trashed target would
  already throw `TaskListNotFoundException`. The fix turns a domain 422 into the right validation 422.)
- `app/Http/Controllers/Api/V1/TaskController.php::destroy` and
  `.../TaskListController.php::destroy` — **no code change**; response stays `204`.
- `routes/api.php` — **no change** (see D7: no un-delete endpoints in V1 for now).
- `app/Http/Resources/Api/V1/TaskResource.php`, `TaskListResource.php` — **no change** (no `deleted_at`).

**Livewire**
- `app/Livewire/Tasks/TaskPanel.php::deleteTask` and `app/Livewire/Tasks/StarredPanel.php` — **no code
  change in this plan.** The rows disappear exactly as before. Wiring undo is Phase C, Step 4 (§6).

**Tests that MUST change (not just new tests)**
- `tests/Feature/Api/V1/TaskTest.php:145` `test_delete_removes_the_task_which_is_distinct_from_completing_it`
  → `assertSoftDeleted`, keep `assertNoContent`, add "a later GET is 404".
- `tests/Feature/Livewire/TaskPanelTest.php:91` `test_delete_removes_the_row`
  → `assertSoftDeleted` + assert the row is gone from the rendered panel.
- `tests/Feature/Api/V1/TaskListTest.php:107` `test_deleting_a_normal_list_cascades_its_tasks`
  → redefined: `204`, list soft-deleted, its task rows physically survive but are invisible through
  every API read.
- `tests/Feature/Models/TaskRelationshipsTest.php:51` `test_deleting_a_list_deletes_its_tasks`
  → redefined: soft-deleting a list leaves its tasks intact and hidden; plus a new sibling test that
  `forceDelete()` on a list still cascades its tasks (proves the FK is still doing its job).
- `tests/Feature/Models/TaskRelationshipsTest.php:62` `test_deleting_a_user_removes_folders_lists_and_tasks`
  → extended with a soft-deleted task and a soft-deleted list, proving account deletion physically
  purges trashed rows too.
- `tests/Feature/Api/V1/FolderTest.php:126`, `tests/Feature/Services/FolderServiceTest.php:55`,
  `tests/Feature/Repositories/EloquentFolderRepositoryTest.php:86`
  → must stay green **unchanged** thanks to D5's `forceDelete()`; add an explicit assertion that the
  lists were force-deleted (`assertDatabaseMissing`, not merely soft-deleted) so a future regression
  is caught rather than passing silently.

### Reusable Components

- Laravel's `Illuminate\Database\Eloquent\SoftDeletes` trait + global scope — no custom package, no
  custom scope, no manual `whereNull('deleted_at')` in repositories.
- Laravel's model factories already provide a `trashed()` state automatically for soft-deletable
  models — no new factory state is needed in `TaskFactory` / `TaskListFactory`.
- PHPUnit's `assertSoftDeleted()` / `assertNotSoftDeleted()`.
- Existing `DomainException` base + `bootstrap/app.php` renderer for the one new exception.
- Existing `TaskListRepositoryInterface::findForUser()` — already user-scoped and (post-change)
  trashed-blind, so `TaskService` can use it to detect "this task's list is deleted" without writing
  a query.

### Architecture Decision

**D1 — Use Laravel's `SoftDeletes` trait; do not hand-roll a `deleted_at` filter.**
Every read in both repositories already goes through an Eloquent query builder (`Task::query()`,
`TaskList::query()`, relations) — there is no `DB::table()` anywhere, only `DB::transaction()`. The
global scope therefore covers 100% of existing reads for free. Hand-rolling `whereNull('deleted_at')`
per query would be more code and would fail open the first time someone forgets. *Alternative
considered:* an `is_deleted` boolean + explicit repository filters — rejected: same storage cost, no
framework support (no `trashed()`, no `assertSoftDeleted`, no factory state), and fails open.

**D2 — Soft-deleting a list does NOT touch its tasks. Visibility is enforced at read time.**
This is the explicit answer to "the FK cascade no longer fires". A soft-deleted list's task rows stay
physically intact and unmodified; they become invisible because every task read is reached *through*
a list (`activeForList`/`completedForList` take a `TaskList` that can only be resolved through
trashed-blind repository lookups). The one exception is `EloquentTaskRepository::starredForUser()`,
which queries tasks by `user_id` across lists — it gets `->whereHas('taskList')`, which applies the
`TaskList` soft-delete global scope inside the subquery and drops tasks whose list is trashed.
*Consequences, stated plainly:* un-deleting a list brings back every task exactly as it was, including
per-task completion, star and position state, with zero bookkeeping; and per-task delete/undelete
stays completely orthogonal to per-list delete/undelete.
*Alternative considered:* cascading the soft delete (soft-delete the list's tasks too, then un-delete
by matching `deleted_at` timestamps). Rejected — it is the classic cascading-soft-delete idiom and
it is fragile: it cannot distinguish a task the user deleted individually five seconds earlier from
one deleted with the list, so un-deleting a list can resurrect a task the user deliberately deleted.
D2 has exactly one cost (one `whereHas` per cross-list query — one query exists today, and Step 6
adds a regression test that pins the rule).

**D3 — "Un-delete" is named `undelete()`, never `restore()`.**
`TaskService::restore()` already exists and means *un-complete* (`markActive()`); it is called by
`TaskPanel`, `StarredPanel` and `Api\V1\RestoreTaskController` (route `POST /api/v1/tasks/{task}/restore`).
Reusing `restore` for un-deletion would be an outright trap. Therefore:
`TaskService::undelete()`, `TaskListService::undelete()`, `TaskRepositoryInterface::undelete()` +
`findDeletedForUser()`, same for lists. The Eloquent `$model->restore()` call stays hidden inside the
repository implementation, where it is unambiguous. Both service methods get a docblock pointing at
the other. Step 3 adds two behavioural guard tests: `restore()` must not resurrect a trashed task,
and `undelete()` must not alter `is_completed`.
*Alternative considered:* renaming `TaskService::restore()` → `uncomplete()` to kill the ambiguity at
the root. It is a purely internal, mechanical rename (3 callers + tests, no URL change). Rejected as
the default because the API route, controller name and README all say "restore" for un-completing, so
the rename trades one inconsistency for another while adding review churn. **If you want it, say so
and it becomes a small Step 2b** — it is cheap and fully covered by existing tests.

**D4 — Authorize un-delete with the existing `delete` ability; do NOT add `restore`/`forceDelete`
policy methods.** `Gate::authorize('restore', $task)` would resolve to `TaskPolicy::restore()` and
drag the naming collision into the policy layer. "Whoever may delete it may un-delete it" is the
correct rule and it is the same ownership check. Bonus: `TaskListPolicy::delete()` already returns
`false` for `is_default`, so the Inbox is automatically protected on the un-delete path too.

**D5 — Folder deletion stays a real, irreversible hard delete; `deleteWithLists()` is pinned with
`forceDelete()`.** Folder soft-deletion is out of scope (S7 does not ask for it), but folder deletion
*is* affected whether we like it or not: `EloquentFolderRepository::deleteWithLists()` calls
`$folder->taskLists()->delete()`, which silently becomes a mass soft delete once `TaskList` is
soft-deletable — leaving the lists' tasks alive and permanently unreachable (no Trash UI exists).
That is an accidental data-leak-by-accretion, not a feature. Fix: `$folder->taskLists()->forceDelete()`
inside the existing transaction, which preserves today's exact semantics (real DELETE → FK cascade
destroys the tasks) and keeps `FolderTest`/`FolderServiceTest`/`EloquentFolderRepositoryTest` green.
M1 already requires an explicit confirmation for this action, so no undo is promised.
Two knock-on behaviours, accepted and documented rather than "fixed":
(a) `hasLists()` now ignores trashed lists, so a folder whose only list is inside its undo window
counts as empty and can be hard-deleted; the FK `nullOnDelete` then nulls the trashed list's
`folder_id`, so un-deleting it returns it as an ungrouped list rather than failing. Honest and safe.
(b) `detachLists()` skips trashed lists for the same reason; the FK `nullOnDelete` covers them anyway.
No dangling FK is possible in either case, because a soft-deleted list is still a physical row the FK
action applies to.

**D6 — Un-deleting a task whose list is deleted is refused, not silently swallowed.**
Otherwise "Undo" would report success and put the task somewhere invisible. `TaskService::undelete()`
resolves `$task->task_list_id` through the already-injected `TaskListRepositoryInterface::findForUser()`
(trashed-blind, user-scoped) and throws `TaskCannotBeUndeletedException::becauseItsListIsDeleted($task)`
(`error_code: task_cannot_be_undeleted`, default 422) when it comes back null. No new query in the
service — it reuses an existing repository method.

**D7 — No `/api/v1` un-delete endpoints in this plan. Explicit non-goal, deliberately deferred.**
The only consumer of un-delete today is the Livewire undo bar, which calls the service in-process.
Shipping `POST /api/v1/tasks/{task}/undelete` now would (a) permanently commit V1 to un-delete
semantics (how long is a resource recoverable? what happens after a future purge job?) with zero
clients, (b) require `->withTrashed()` route-model binding, its own Form Requests/Resources/tests, and
(c) buy nothing, since adding a **new** route to V1 later is purely additive and non-breaking. The
shared application layer is preserved regardless — the API can expose `TaskService::undelete()` the
day something needs it, with no business logic duplicated. What *is* guaranteed by this plan is that
the existing V1 delete contract does not move: `204` on delete, `404` on a subsequent read, no
`deleted_at` in any resource.

**D8 — No purge job, no Trash UI, no `forceDelete` surface. YAGNI, with the trade-off named.**
Nothing in `docs/project-base.md` requires bounding storage growth, and this is a single-user-scale
local app on SQLite. The accepted consequence: **soft-deleted rows live forever.** The natural
follow-up when that matters is a scheduled `PurgeDeletedItemsCommand` that `forceDelete()`s rows with
`deleted_at < now()->subDays(N)` (a repository method + an Artisan command delegating to a service,
per `.claude/rules/laravel.md`). Recorded as a follow-up, not built. Account deletion is unaffected:
`users` → `tasks`/`task_lists` FKs are `cascadeOnDelete`, a real DELETE, so deleting an account
physically purges trashed rows too — Step 6 pins that with a test.

**D9 — No new indexes, no positional repair on un-delete.**
`softDeletes()` adds an unindexed nullable `deleted_at`; the existing composite indexes still serve
every query and the data volume does not justify speculative index churn. Separately: `nextPosition()`
ignores trashed tasks, so an un-deleted task can share a `position` with a task created during its
absence. There is no unique constraint on `(task_list_id, position)` (verified), reads order by
`position` then `id`, and the next reorder normalises positions — so the task reappears adjacent to
where it was. Accepted; not worth a transaction to "make room".

---

## 3. Step Breakdown

### Step 1: Additive `deleted_at` migration (inert)

- **What:** `deleted_at` columns on `tasks` and `task_lists`. No behaviour change whatsoever.
- **Where:** `database/migrations/2026_08_04_110000_add_deleted_at_to_tasks_and_task_lists_tables.php` (new).
- **How:**
  - `Schema::table('tasks', fn (Blueprint $t) => $t->softDeletes());` and the same for `task_lists`.
  - `down()` uses `dropSoftDeletes()` on both.
  - No index (D9). No data backfill — `NULL` already means "live".
  - Models are **not** touched in this step, so nothing reads or writes the column yet.
- **Test:** `composer test` fully green with **zero** existing test modified — this is the proof the
  step is inert. Add one migration test asserting both columns exist and default to `null`
  (`Schema::hasColumn` + a freshly created factory model has `deleted_at === null`).
- **Complexity:** Small

### Step 2: Turn on soft deletes for `Task`

- **What:** Deleting a task stops destroying the row. Everything else looks identical to the user.
- **Where:** `app/Models/Task.php`; `tests/Feature/Api/V1/TaskTest.php`;
  `tests/Feature/Livewire/TaskPanelTest.php`; `tests/Feature/Repositories/EloquentTaskRepositoryTest.php`;
  `tests/Feature/Models/TaskRelationshipsTest.php`.
- **How:**
  - Add `use SoftDeletes;` to `App\Models\Task` and `@property Carbon|null $deleted_at` to the class
    docblock (PHPStan level 7).
  - **No repository or service change is needed** and that is the point: every task read in
    `EloquentTaskRepository` is an Eloquent builder or relation, so the global scope covers
    `activeForList`, `completedForList`, `starredForUser`, `findForUser`, `nextPosition`, `idsForList`
    and `applyOrder` for free (D1). Verify this claim by reading the file, do not assume it.
  - Route-model binding for `{task}` now excludes trashed rows — a second `DELETE` returns `404`,
    which is exactly what it returned before. Confirm, don't change.
  - `TaskFactory` needs no new state — `trashed()` comes from the framework.
  - **Required existing-test updates:** `TaskTest:145` → `assertSoftDeleted` + still `204` + a
    follow-up `GET /api/v1/tasks/{id}` returns `404`; `TaskPanelTest:91` → `assertSoftDeleted` **and**
    `assertDontSee` the title in the rendered panel (proving invisibility, not just the column).
    `TaskRelationshipsTest:51` still passes here — the list is still a hard delete, and the FK cascade
    bypasses Eloquent — assert that explicitly so Step 4's change to it is a conscious edit.
- **Test:** deleting a task soft-deletes it; the task vanishes from `activeForList`, `completedForList`,
  `starredForUser`, `findForUser`, `idsForList` and the `tasks_count` on `TaskListRepository::allForUser`;
  a trashed task's id is rejected by `applyOrder` (id-set mismatch) rather than silently reordered;
  API delete is still `204`; the Livewire row disappears.
- **Complexity:** Medium

### Step 3: Task un-delete path

- **What:** A deleted task can be brought back, by an unambiguously named operation.
- **Where:** `app/Repositories/Contracts/TaskRepositoryInterface.php`;
  `app/Repositories/EloquentTaskRepository.php`; `app/Services/TaskService.php`;
  `tests/Feature/Repositories/EloquentTaskRepositoryTest.php`; `tests/Feature/Services/TaskServiceTest.php`
  (create if absent).
- **How:**
  - Repository: `findDeletedForUser(int $taskId, User $user): ?Task` — `Task::onlyTrashed()`, scoped by
    `user_id`, returns `null` for a live task, a foreign task or a non-existent id. And
    `undelete(Task $task): Task` — calls the model's `restore()` and returns the model.
  - Contract: both methods added to `TaskRepositoryInterface` with docblocks (LayeringTest requires
    the implementation to satisfy a contract).
  - Service: `TaskService::undelete(Task $task): Task` delegating to the repository, with a docblock
    stating in one line: *"Un-deletes a soft-deleted task. Not to be confused with `restore()`, which
    un-completes a completed task."* Add the mirror sentence to `restore()`'s docblock (D3).
    The "list is deleted" guard is **not** added here — a hard-deleted list still destroys its tasks
    via FK cascade, so the case cannot occur until Step 4 makes lists soft-deletable. It lands in
    Step 5, where it is testable.
  - No policy change (D4). No Livewire change. No route change (D7).
- **Test:** `findDeletedForUser` returns the task only when trashed **and** owned (four cases: trashed
  + owned, live + owned, trashed + foreign, missing); `undelete` clears `deleted_at` and the task
  reappears in `activeForList` at its original position with title/note/due date/star/completion
  intact; un-deleting twice is harmless. **Guard tests (D3):** `TaskService::restore()` on a trashed
  task does **not** clear `deleted_at`; `TaskService::undelete()` on a completed-then-deleted task
  leaves `is_completed`/`completed_at` untouched and the task returns to the *completed* section.
- **Complexity:** Medium

### Step 4: Turn on soft deletes for `TaskList` (with all fallout in the same step)

- **What:** Deleting a list stops destroying the list and its tasks. The trait and every consequence
  land together, because a deployable state must never allow moving a task into a deleted list or
  leaking a deleted list's tasks into Starred.
- **Where:** `app/Models/TaskList.php`; `app/Repositories/EloquentTaskRepository.php` (starred query);
  `app/Repositories/EloquentFolderRepository.php` (`deleteWithLists`);
  `app/Http/Requests/Api/V1/MoveTaskRequest.php`;
  `app/Http/Requests/Api/V1/UpdateTaskListOrderRequest.php`;
  `tests/Feature/Api/V1/TaskListTest.php`; `tests/Feature/Models/TaskRelationshipsTest.php`;
  `tests/Feature/Repositories/{EloquentTaskListRepositoryTest,EloquentFolderRepositoryTest,EloquentTaskRepositoryTest}.php`;
  `tests/Feature/Services/FolderServiceTest.php`; `tests/Feature/Livewire/StarredPanelTest.php`.
- **How:**
  - Add `use SoftDeletes;` + `@property Carbon|null $deleted_at` to `App\Models\TaskList`.
  - **`EloquentTaskRepository::starredForUser()`** gets `->whereHas('taskList')` (D2): the only
    cross-list task query, and the only place a trashed list's tasks could surface. Comment it with
    *why*, not *what*.
  - **`EloquentFolderRepository::deleteWithLists()`**: `$folder->taskLists()->delete()` →
    `->forceDelete()` (D5), inside the existing transaction, with a comment explaining that folder
    deletion is deliberately irreversible and must not leave unreachable soft-deleted lists.
  - **`Rule::exists('task_lists', 'id')->where('user_id', ...)`** in `MoveTaskRequest` and
    `UpdateTaskListOrderRequest` gains `->whereNull('deleted_at')` — the `exists` rule bypasses model
    scopes. Comment it, per the `.claude/rules/laravel.md` note about scoping `Rule::exists` lookups.
  - Everything else in `EloquentTaskListRepository` (`allForUser`, `findForUser`, `findDefaultFor`,
    `nextPosition`, `applyOrder`) is covered by the global scope — verify by reading, and note that
    `withCount('tasks')` also excludes trashed tasks automatically.
  - `TaskListService::delete()` keeps its `is_default` guard unchanged.
  - **Required existing-test updates:** `TaskListTest:107` redefined (204; list soft-deleted; its task
    rows still physically present; the list is absent from `GET /api/v1/lists`; `GET /api/v1/lists/{id}`
    is 404; its tasks are absent from `GET /api/v1/starred`); `TaskRelationshipsTest:51` redefined as
    "soft-deleting a list leaves its tasks intact but hidden", plus a new sibling proving
    `forceDelete()` on a list still cascades its tasks. The three folder tests
    (`FolderTest:126`, `FolderServiceTest:55`, `EloquentFolderRepositoryTest:86`) must pass
    **unchanged** — add an explicit `assertDatabaseMissing` on the lists so a future regression to
    soft-delete-by-accident fails loudly.
- **Test:** as above, plus — a task cannot be moved into a trashed list (validation error via the
  Form Request **and** `TaskListNotFoundException` when the service is called directly, proving
  defence in depth); a trashed list id in a reorder payload is rejected by validation; `StarredPanel`
  no longer renders tasks whose list was deleted; the Inbox path (`inboxFor`) is unaffected.
- **Complexity:** Large

### Step 5: List un-delete path + the "orphaned task" guard

- **What:** A deleted list can be brought back with all of its tasks; un-deleting a task whose list is
  deleted is refused with a named domain exception.
- **Where:** `app/Repositories/Contracts/TaskListRepositoryInterface.php`;
  `app/Repositories/EloquentTaskListRepository.php`; `app/Services/TaskListService.php`;
  `app/Services/TaskService.php`; `app/Exceptions/TaskCannotBeUndeletedException.php` (new);
  `tests/Feature/Repositories/EloquentTaskListRepositoryTest.php`;
  `tests/Feature/Services/{TaskListServiceTest,TaskServiceTest}.php`.
- **How:**
  - Repository: `findDeletedForUser(int $taskListId, User $user): ?TaskList` (`onlyTrashed()`, user-scoped)
    and `undelete(TaskList $taskList): TaskList`; both added to `TaskListRepositoryInterface`.
  - Service: `TaskListService::undelete(TaskList $taskList): TaskList` — thin delegation. No
    `is_default` guard is needed (the Inbox can never be deleted, hence never trashed), but state that
    in the docblock rather than leaving the reader to wonder.
  - `TaskCannotBeUndeletedException extends DomainException`, static constructor
    `becauseItsListIsDeleted(Task $task)`, `errorCode(): 'task_cannot_be_undeleted'`, default 422
    status (consistent with every other domain exception; no endpoint exposes it today anyway).
  - `TaskService::undelete()` gains the D6 guard: resolve the parent list via the already-injected
    `TaskListRepositoryInterface::findForUser($task->task_list_id, $user)` and throw when it is `null`.
    This changes the signature to `undelete(Task $task, User $user): Task` — decide this now, before
    Phase C calls it. (Deriving the user from `$task->user` inside the service would be a lazy-load
    and a hidden dependency; passing the acting user matches `move()`'s existing shape.)
- **Test:** un-deleting a list restores the list **and** makes all of its tasks visible again with
  original completion/star/position state; a list deleted while holding a completed task and a
  starred task returns both to the right sections and to Starred; `findDeletedForUser` is user-scoped
  (foreign trashed list → `null`); un-deleting a task whose list is trashed throws
  `TaskCannotBeUndeletedException` and leaves `deleted_at` set; un-deleting the same task after its
  list is restored succeeds.
- **Complexity:** Medium

### Step 6: Invariant sweep — one test class that pins the whole contract

- **What:** A dedicated regression suite that fails loudly if any future change lets deleted data leak
  or lets deletion stop being recoverable.
- **Where:** `tests/Feature/Deletion/SoftDeleteVisibilityTest.php` (new).
- **How:** One class, one assertion per invariant, each named after the rule it protects:
  - a deleted task is absent from: the list panel's active and completed sections, Starred, the
    list's `tasks_count`, `idsForList`, and `GET /api/v1/tasks/{id}` (404);
  - a deleted list is absent from: `TaskListRepository::allForUser`, `GET /api/v1/lists`,
    `GET /api/v1/lists/{id}` (404), move-target validation, and reorder validation;
  - **the tasks of a deleted list are absent from Starred** (the D2 tripwire — the single most
    likely future regression);
  - the Inbox can be neither deleted nor trashed (service exception + policy 403);
  - deleting a folder "with lists" still physically destroys the lists and their tasks (the D5
    tripwire);
  - deleting a **user account** physically purges their soft-deleted tasks and lists too
    (`assertDatabaseMissing` on trashed rows after `$user->delete()`);
  - the V1 delete contract is unchanged: `204` on delete, `404` on re-read, and no `deleted_at` key
    in the `TaskResource` / `TaskListResource` payloads (`assertJsonMissingPath`).
- **Test:** the class itself is the deliverable. It must fail if the trait, the `whereHas`, the
  `forceDelete` or the `whereNull('deleted_at')` is reverted — verify by temporarily reverting each
  one locally while writing it, then restoring.
- **Complexity:** Medium

### Step 7: Documentation

- **What:** The behaviour change is written down where a reader will actually find it.
- **Where:** `README.md`; `docs/04-08-2026-frontend-reordering-undo-and-polish-3.md` (amendment note only).
- **How:**
  - README **Architecture / Layers**: a short "Deletion semantics" note — tasks and lists are soft
    deleted and recoverable via `undelete()`; folders are hard deleted and irreversible; a deleted
    list's tasks are untouched and invisible; `restore()` means un-complete while `undelete()` means
    un-delete; there is no purge job, so deleted rows persist until the account is deleted.
  - README **API Reference → Lists / Tasks**: `DELETE` is a soft delete; the response contract is
    unchanged (`204`, then `404`); there is deliberately **no** V1 un-delete endpoint (D7).
  - README **Domain error codes**: add `task_cannot_be_undeleted`.
  - Phase C plan: append a short amendment under C4 pointing at this plan and at §6 below, so the
    "deletes set no undo state" instruction in its Step 4 is not implemented as written.
- **Test:** `composer test` green; a reader following the README can explain, without reading code,
  what happens to a list's tasks when the list is deleted.
- **Complexity:** Small

---

## 4. Risk Assessment

### Risks

- **R1 (High) — `restore()` vs `undelete()` confusion.** `TaskService::restore()` already means
  *un-complete* and is called from `TaskPanel`, `StarredPanel` and `Api\V1\RestoreTaskController`
  (`POST /api/v1/tasks/{task}/restore`). A contributor — or an AI executor — wiring undo could easily
  call the wrong one, producing an "Undo" that un-completes instead of un-deleting, or a policy
  ability named `restore` that means neither.
- **R2 (High) — `EloquentFolderRepository::deleteWithLists()` silently changes meaning.**
  `$folder->taskLists()->delete()` becomes a mass soft delete the instant `TaskList` gets the trait,
  the FK cascade stops firing, and the lists' tasks become permanently unreachable rows. This is the
  single easiest thing to miss in the whole change, and it fails *silently* — the folder still
  disappears from the UI.
- **R3 (Medium) — A deleted list's tasks leak into Starred.** `starredForUser()` filters by `user_id`,
  not by list, so without the `whereHas('taskList')` a deleted list's starred tasks stay visible on
  the Starred page, and `$task->taskList` is `null` there — a null-property crash in the Blade view
  on top of the data leak.
- **R4 (Medium) — `Rule::exists` ignores the soft-delete scope.** `MoveTaskRequest` and
  `UpdateTaskListOrderRequest` validate list ids straight against the table. Impact is contained
  (`TaskService::move()` re-resolves through a scoped repository lookup and throws), but a reorder
  payload containing a trashed id would pass validation and then write nothing, silently.
- **R5 (Medium) — Existing tests encode hard-delete behaviour.** Six test files assert
  `assertDatabaseMissing` on rows that will now survive. Updating them is required work, and the
  danger is updating them *carelessly* — mechanically weakening `assertDatabaseMissing` to
  `assertSoftDeleted` everywhere would silently destroy the folder-deletion guarantees in
  `FolderTest`/`FolderServiceTest`/`EloquentFolderRepositoryTest`, which must stay hard.
- **R6 (Medium) — Undo becomes dishonest again in a new way.** Un-deleting a task into a list that was
  itself deleted meanwhile would report success and hide the task. Low probability (the undo window is
  seconds and holds one action), high embarrassment.
- **R7 (Low) — Unbounded growth of deleted rows.** No purge job (D8), so trashed rows persist for the
  life of the account. Bounded in practice by single-user scale; unbounded in principle.
- **R8 (Low) — Position collisions after un-delete.** `nextPosition()` ignores trashed tasks, so an
  un-deleted task can share a `position` with a newer task.
- **R9 (Low) — Rolling back the trait resurrects deleted data.** If Step 2 or Step 4 is reverted
  without dropping the column, previously soft-deleted rows become visible again. If the *migration*
  is rolled back, the record of what was deleted is lost.
- **R10 (Low) — PHPStan level 7 / Larastan noise.** `deleted_at` is not in the model docblocks, and
  the new repository methods return nullable models.

### Mitigations

- **R1:** D3 — the name `undelete()` at every layer, cross-referencing docblocks on both methods, and
  two behavioural guard tests in Step 3 (`restore()` must not clear `deleted_at`; `undelete()` must not
  touch `is_completed`). D4 keeps the collision out of the policy layer by authorizing un-delete with
  the `delete` ability. The optional `restore()` → `uncomplete()` rename is available on request.
- **R2:** D5 — explicit `forceDelete()` in `deleteWithLists()`, landed in the *same step* as the trait
  (Step 4), plus an explicit `assertDatabaseMissing` in Step 6 that fails if it ever regresses to a
  soft delete.
- **R3:** D2 — `->whereHas('taskList')` on `starredForUser()`, landed in the same step as the trait,
  with a dedicated Step 6 tripwire test ("a starred task in a deleted list is absent from Starred").
- **R4:** `->whereNull('deleted_at')` on both `Rule::exists` calls in Step 4, tested from both the
  validation side and the service side (defence in depth).
- **R5:** §2 lists every affected test by **file and line**, and each step names the specific
  redefinition rather than saying "update tests". The folder tests are called out as
  **must-pass-unchanged**, and get a strengthening assertion rather than a weakening one.
- **R6:** D6 — `TaskCannotBeUndeletedException`, thrown from `TaskService::undelete()` using an
  existing scoped repository lookup (no new query, no layering violation).
- **R7:** Accepted and documented (D8), with the follow-up spelled out (a scheduled
  `PurgeDeletedItemsCommand` delegating to a service). Account deletion still physically purges
  everything — pinned by a Step 6 test.
- **R8:** Accepted (D9) — no unique constraint exists, reads order by `position` then `id`, and the
  next reorder normalises. Documented rather than engineered around.
- **R9:** Step 1 is a separate, inert, additive migration; the `down()` is a clean `dropSoftDeletes()`.
  Steps 2 and 4 are the behavioural switches and are individually revertible. There is no production
  data (local SQLite/MySQL), so the blast radius is a dev database.
- **R10:** `@property Carbon|null $deleted_at` added with the trait in Steps 2 and 4; nullable return
  types declared on `findDeletedForUser()`. `composer test` runs PHPStan before PHPUnit, so this
  surfaces immediately.

### Fallbacks

- **If Step 4's fallout proves wider than expected** (something else turns out to depend on the list's
  tasks physically vanishing), ship Steps 1–3 alone: task-level soft delete and undo is the more
  frequent S7 case and is independently deployable. List deletion keeps its confirmation, and Phase C's
  undo bar covers task deletion only.
- **If the "deleted list's tasks stay put" model (D2) turns out to be wrong** for a case not visible
  today, the fallback is cascading soft delete with `deleted_at` timestamp matching — but only with a
  test proving an individually-deleted task is *not* resurrected with its list.
- **If deleted rows ever become a real problem** (R7), add the purge command as its own small plan;
  the un-delete paths already fail cleanly on a missing row (`findDeletedForUser` returns `null`).
- **If the `restore()`/`undelete()` distinction still causes confusion in review**, execute the
  optional rename (`TaskService::restore()` → `uncomplete()`); it is mechanical, touches three callers,
  and changes no URL or API contract.

---

## 5. Execution Checklist

- [ ] **Step 1:** Additive `deleted_at` migration for `tasks` + `task_lists`; models untouched;
      **zero** existing tests modified. `composer test`.
- [ ] **Step 2:** `SoftDeletes` on `App\Models\Task` + docblock; verify the repository needs no change;
      update `TaskTest:145`, `TaskPanelTest:91`; add task-invisibility tests. `composer test`.
- [ ] **Step 3:** `findDeletedForUser()` + `undelete()` on `TaskRepositoryInterface`/`EloquentTaskRepository`;
      `TaskService::undelete()`; cross-referencing docblocks; the two `restore()`-vs-`undelete()` guard
      tests. `composer test`.
- [ ] **Step 4:** `SoftDeletes` on `App\Models\TaskList` **plus, in the same step**:
      `starredForUser()->whereHas('taskList')`, `deleteWithLists()` → `forceDelete()`,
      `->whereNull('deleted_at')` on both `Rule::exists('task_lists', ...)` calls; redefine
      `TaskListTest:107` and `TaskRelationshipsTest:51`; strengthen (do not weaken) the three folder
      tests. `composer test`.
- [ ] **Step 5:** `findDeletedForUser()` + `undelete()` on `TaskListRepositoryInterface`/`EloquentTaskListRepository`;
      `TaskListService::undelete()`; `TaskCannotBeUndeletedException`; the D6 guard in
      `TaskService::undelete(Task $task, User $user)`. `composer test`.
- [ ] **Step 6:** `tests/Feature/Deletion/SoftDeleteVisibilityTest.php` — invariant sweep, including the
      Starred-leak tripwire, the folder-hard-delete tripwire, the account-deletion purge, and the
      unchanged V1 delete contract. `composer test`.
- [ ] **Step 7:** README (deletion semantics, API note, `task_cannot_be_undeleted` error code) and the
      amendment note on the Phase C plan's C4. `composer test`.

**Quality gates:** `composer test` green at the end of every step, and `code-reviewer` approval before
the next step begins. `tests/Feature/Architecture/LayeringTest.php` must stay green **without being
edited** — if it needs editing, the change is an architecture violation, not a test problem.

**Explicit non-goals (do not build):** folder soft deletes; a Trash / deleted-items screen; a purge or
retention job; `/api/v1` un-delete endpoints; `deleted_at` in any API resource; the Livewire undo-bar
wiring (§6).

---

## 6. Closing note — the follow-up edit Phase C's Step 4 needs

Phase C (`docs/04-08-2026-frontend-reordering-undo-and-polish-3.md`) is **not yet implemented**, and
this plan has no dependency on it — the two can ship in either order. But Phase C's Step 4 currently
instructs the executor to make deletions set **no** undo state ("Deletes keep `wire:confirm` and set
**no** `lastAction` (C4)"), and its test list includes *"deleting sets no undo state"*. Once this plan
ships, that instruction is stale. The precise amendment:

1. **C4's rationale is superseded.** Replace the "deletion is a hard delete, so undo would be lossy"
   paragraph with a pointer to this plan: deletion is now recoverable via `TaskService::undelete()`
   and `TaskListService::undelete()`, so S7's "Undo after deleting a task / deleting a list" is
   honest and must be implemented.

2. **`lastAction` gains a `'delete'` type.** `TaskPanel::deleteTask()` sets
   `['type' => 'delete', 'taskId' => $task->id]` after calling `TaskService::delete()`. The undo bar
   copy becomes "Task deleted." with an Undo button.

3. **The undo re-resolution helper must become trashed-aware.** `TaskPanel::authorizedTask()` uses
   `TaskRepositoryInterface::findForUser()`, which will **not** find a trashed task. The `'delete'`
   branch of `undo()` must resolve through `findDeletedForUser($taskId, Auth::user())`
   (`abort_if(null, 404)`), authorize with `Gate::authorize('delete', $task)` (D4 — not `'restore'`,
   not `'update'`), then call `TaskService::undelete($task, Auth::user())` (note the two-argument
   signature from Step 5).

4. **Handle `TaskCannotBeUndeletedException`.** The `'delete'` branch must catch it and show a danger
   toast ("That task's list was deleted") instead of letting it bubble — the same pattern
   `renameTask()` already uses for `InvalidTaskTitleException`. Web/Livewire callers catch domain
   exceptions themselves; only the API renders them centrally.

5. **List deletion undo needs a home.** Task undo lives in `TaskPanel`, but list deletion is triggered
   from Phase A's sidebar / list dialog, which has no undo bar. Phase C's Step 4 must either extend
   `x-tasks.undo-bar` to that component or hoist the bar into the layout with a shared trait — decide
   this when Phase C is planned for execution; it is the only genuinely *new* work the amendment adds.
   Its `undo()` calls `TaskListRepositoryInterface::findDeletedForUser()` → `Gate::authorize('delete', $list)`
   → `TaskListService::undelete($list)`.

6. **Confirmation vs undo (recommendation, Phase C decides).** M7 permits "a confirmation **or** a
   short undo period". Now that undo is honest: drop `wire:confirm` on **task** delete in favour of
   undo (fast, Wunderlist-like), and keep the confirmation **and** add undo on **list** delete
   (higher blast radius). **Folder** delete keeps its confirm-or-move-lists flow with no undo —
   folder deletion remains irreversible by design (D5), and Phase A's copy should say so plainly.

7. **Phase C's Step 4 test list changes.** Delete the assertion *"deleting sets no undo state"* and
   replace it with: deleting a task sets `lastAction` and renders the bar; `undo` un-deletes the task
   and it reappears in the panel at its original position; `undo` on a task whose list was deleted
   shows a danger toast and does not clear the deleted state; `undo` on another user's deleted task is
   refused; `dismissUndo` clears the state and the task stays deleted.
