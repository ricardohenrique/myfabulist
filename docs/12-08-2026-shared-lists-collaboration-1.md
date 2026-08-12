# Implementation Plan: Shared Lists and Collaboration

**Date:** 12-08-2026
**Plan ID:** 1
**Status:** Approved — Section 6 resolved (explicit decisions + accepted defaults), ready to begin Step 1
**Complexity:** Large (3 phases, 10 steps)

---

## Ground-truth corrections found during planning

- `EloquentFolderRepository::deleteWithLists()` (line 61-74) `forceDelete()`s every list in the folder. Once a collaborator can file a shared list into their own folder, this path hard-deletes another user's list and cascades its tasks. This is the single most dangerous pre-existing behaviour for this feature.
- `app/Http/Presenters/WorkspacePresenter.php` builds every Inertia workspace prop and reads `folder_id`/`position`/`is_starred` directly. It is a required touch-point (not listed in CLAUDE.md's structure diagram).
- Web page controllers are split across two namespaces: `app/Http/Controllers/{Inbox,Starred,TaskList,Home}Controller` (Inertia page renders) vs `app/Http/Controllers/Web/*` (mutations).
- There is no API star endpoint. Starring over `/api/v1` happens through `PUT /api/v1/tasks/{task}` with `is_starred` in `TaskDetailsData`.
- `LayeringTest::test_every_eloquent_repository_implements_a_contract()` hardcodes the contract list — any new repository must be added to that array or the suite fails.
- There is no `TaskCommentPolicy`; comment authorization is `TaskPolicy::comment()`. There is no `notifications` table migration, no broadcasting package installed — confirmed.
- `task_lists.deleted_at` comes from the later `2026_08_04_110000_...` migration, not the create migration.

---

## 1. Requirements Analysis

### Confirmation of the user's reading (requirement 5)

**Correct.** A shared list stays exactly one `task_lists` row, one set of `tasks`, `subtasks`, and `task_comments` rows. Every member reads and writes the same records, so a rename, completion, reorder, new task, or new comment is immediately identical for everyone. Nothing is copied or fanned out. The only things that become *per-user* are (a) where the list sits in **your** sidebar (folder + position), (b) whether a task is starred **for you**, and (c) your membership status. This is the "single canonical row, per-viewer projection" model, and it is the only reading compatible with requirements 3, 4, 5 and 8 simultaneously.

### Functional Requirements (stated)

- [ ] F1. A list can be shared with N other users (N bounded — see F17).
- [ ] F2. Only a list is shareable. Folders and individual tasks are never shareable. Sharing a folder is not implied by sharing the lists inside it.
- [ ] F3. An accepted member sees the entire list: all tasks, subtasks, comments, notes, due dates, completion state, and task order.
- [ ] F4. The sharer's `folder_id` is theirs alone. A new member sees the list ungrouped and may file it into one of their own folders, at their own sidebar position, without affecting anyone else.
- [ ] F5. One database row per list. All edits, completions, additions, and deletions are reflected identically for every member.
- [ ] F6. A notification center opens from the existing bell button (`resources/js/components/navigation/sidebar.tsx:407`), currently `disabled`.
- [ ] F7. A user can accept or decline an invitation to a shared list.
- [ ] F8. Starring is per-viewing-user. Your star is invisible to other members; the Starred smart view and its sidebar badge show only your own stars.

### Functional Requirements (gaps found — requirement 9)

These are not in the stated list but are unavoidable consequences. Each one that materially changes the data model, authorization boundary, or UX is repeated as an explicit question in Section 6.

- [ ] F9. **Inbox is never shareable.** `AGENTS.md` states Inbox is "permanent, ungrouped, and user-owned" and is the destination for global quick capture. Sharing it would leak every quick-captured task. Sharing must be rejected for `is_default` lists at the service layer, not just hidden in the UI.
- [ ] F10. **Non-owner "delete list" must mean "leave list".** Today the list overflow menu offers Delete. For a member, Delete must remove *their* membership only. Reusing the existing delete route for a non-owner would soft-delete the list for everyone, including the owner.
- [ ] F11. **Folder deletion must never destroy a list you do not own.** `deleteWithLists()` currently `forceDelete()`s. Under sharing, a member who files a shared list into a folder and then chooses the destructive folder workflow would irreversibly destroy the owner's list and cascade-delete its tasks. Folder deletion must only ever force-delete lists the acting user owns; shared lists in that folder are detached to the member's ungrouped collection.
- [ ] F12. **Owner deletes a shared list.** One row, one lifecycle: a soft delete removes it for everyone. This needs an explicit confirmation naming the number of collaborators, and it needs a decision on whether it is allowed at all (Q6).
- [ ] F13. **Owner deletes their account.** `task_lists.user_id` is `cascadeOnDelete`, so today deleting a user hard-deletes their lists and cascades to tasks — destroying collaborators' shared work with no warning. There is no account-deletion feature in the current release, so the safe move is to keep it out of scope and record the constraint in `docs/` so it is designed for before account deletion ships (Q7).
- [ ] F14. **Task ownership is reframed.** `tasks.user_id` currently must equal the list owner. Under sharing it becomes the *creator* (attribution only), exactly like `task_comments.user_id`. Authorization moves entirely to list membership. `AGENTS.md`'s domain invariant "a task and its list must have the same owner" must be rewritten to "a task's creator must be a member of its list".
- [x] F15. **Task move across the sharing boundary — DECIDED (Q8): out of scope for v1.** A task can never be moved out of a shared list into a private one, or vice versa, regardless of who initiates it (owner or member). `TaskService::move()` keeps its existing ownership-scoped `findForUser()` target resolution unchanged and gains an explicit guard rejecting the move whenever the source or destination list is shared (more than one accepted member). This removes a whole sub-feature from Step 6 rather than adding one.
- [ ] F16. **Reorder conflicts become real.** `applyOrder()` rejects the whole submission when the id set is stale (`TaskReorderMismatchException`). With two people in a list this is now a routine event, not an anomaly. The UI already reconciles from canonical props, but the message must read as a collaboration conflict, not a failure.
- [ ] F17. **Abuse guards:** a maximum number of members per list, no self-invite, no duplicate pending invite, an explicit rule for re-inviting after a decline, and rate limiting on invitation creation.
- [ ] F18. **Member privacy.** Members become visible to each other. Recommended: expose name + avatar to all members; expose email only to the list owner (who typed it). Do not expose emails between collaborators.
- [ ] F19. **No live sync.** No Reverb/Pusher/broadcasting is installed. Members see each other's changes on the next request or navigation only. This must be stated in `development/scope.md` so it is a documented product boundary rather than a perceived bug (Q9).
- [ ] F20. **Revoke and leave.** The owner can remove any member (accepted or pending). Any member can leave voluntarily. Both are idempotent and reversible only by a fresh invitation.
- [ ] F21. **Undo interaction.** Existing single-action Undo covers complete/star/move. Star-undo is now per-user and safe. Complete-undo can stomp a concurrent change by another member; acceptable for v1, documented.
- [ ] F22. **Search (near-term priority in scope.md) must include shared lists.** Not built here, but the repository method it will use must be membership-scoped from day one.
- [ ] F23. **Demo data must exercise sharing:** at least one shared list with accepted members, one pending invitation for `demo1@example.com`, and per-user stars, or the notification center has nothing to render locally.

### Non-Functional Requirements

- [ ] N1. **Authorization is the whole feature.** Every read and write of a list, task, subtask, or comment must resolve through membership. Hidden UI is never an authorization boundary (`AGENTS.md`). Every policy method needs a "non-member is denied" test, and every existing "other user is denied" test must keep passing.
- [ ] N2. **Web/API parity.** Every capability ships on both `routes/web.php` (Inertia) and `/api/v1` (Sanctum), sharing one service, per the established convention for Task/TaskComment/Subtask.
- [ ] N3. **Layering invariant holds.** `tests/Feature/Architecture/LayeringTest.php` must stay green: no `/api/v1`, `fetch(`, or `axios` under `resources/js/{pages,layouts,components}`; no `DB::`, `::query(`, or `->where(` in Services or Controllers; every new repository implements a registered contract (and is added to that test's contract array).
- [ ] N4. **No N+1.** The sidebar renders every list for a user; the pivot join and the star-exists subquery must not add a query per row. Membership and star lookups are indexed and batched.
- [ ] N5. **Transactional integrity.** Accept, revoke, leave, folder deletion, and list deletion each touch multiple rows and must be wrapped in a transaction with preconditions re-checked inside it (`AGENTS.md`).
- [ ] N6. **V1 API contract stability.** Field names in `TaskListResource`/`TaskResource` stay identical; their values become viewer-relative. For a list with one member (every list today) behaviour is byte-identical, so this is not a breaking V1 change and does not require an `Api\V2` namespace. This justification must be written into `docs/`.
- [ ] N7. **Migrations are reversible and data-preserving.** Every backfill migration must have a working `down()` and be verified against the seeded demo database before columns are dropped.
- [ ] N8. **Rate limiting** on invitation creation (web and API), per `AGENTS.md`'s abuse-sensitive endpoint rule.
- [ ] N9. **Accessibility.** The notification center is a real popover/dialog with focus management, escape-to-close, labelled controls, and a badge with an accessible name — matching the existing dialog conventions.

---

## 2. Architecture Review

### Existing Codebase Patterns

- **Layering:** `Controller → Service → Repository → Model`, with repository interfaces in `app/Repositories/Contracts` bound in `AppServiceProvider`/`RepositoryServiceProvider`. Statically enforced.
- **Dual delivery:** thin API controllers returning `JsonResource`s, thin web controllers returning `Inertia::render(...)` or `back()->with('success', ...)`, both calling the same service.
- **`WorkspacePresenter`** (`app/Http/Presenters/WorkspacePresenter.php`) is the single builder of Inertia workspace props — the web-side analogue of the API resources. Every prop shape change lands here plus `resources/js/types/index.ts`.
- **Domain errors:** named subclasses of `App\Exceptions\DomainException` with static constructors, `errorCode()`, `httpStatus()`, rendered centrally in `bootstrap/app.php` (JSON envelope for API, `withErrors(['domain' => ...])` for Inertia).
- **Policies** are auto-discovered and uniformly `$user->id === $model->user_id`.
- **Repositories own all query logic**, including transactional multi-row writes (`applyOrder`, `deleteWithLists`).
- **Attribution precedent already exists:** `task_comments.user_id` is the author, `TaskComment::author()`, and `WorkspacePresenter` already renders `comment.author.{id,name,avatarUrl}`. The multi-author UI pattern is built.
- **Factories carry semantic states** (`inbox()`, `inFolder()`, `forTaskList()`, `starred()`), and `DemoSeeder` composes them per user inside a transaction.

### Affected Areas

| Area | Impact |
|---|---|
| `database/migrations` | 4 new migrations (membership create+backfill, placement column drop, `task_stars` create+backfill, `tasks.is_starred` drop) |
| `app/Models` | New `TaskListMember`; `TaskList`, `Task`, `User`, `Folder` gain relations; `Task` loses `is_starred` fillable |
| `app/Repositories` | New `TaskListMemberRepository` (+contract); heavy rewrite of `EloquentTaskListRepository` and `EloquentTaskRepository`; scoping fix in `EloquentFolderRepository` |
| `app/Services` | New `ListSharingService`; changes to `TaskListService`, `TaskService`, `NavigationService`, `FolderService` |
| `app/Policies` | `TaskListPolicy`, `TaskPolicy`, `SubtaskPolicy` all move from ownership to membership; new abilities `share`, `manageMembers`, `leave` |
| `app/Exceptions` | ~7 new domain exceptions |
| `app/Http/Controllers` | New Web + Api/V1 sharing and invitation controllers |
| `app/Http/Requests` | New Web + Api/V1 requests; `UpdateTaskListRequest` semantics change (folder is per-user) |
| `app/Http/Resources/Api/V1` | New member/invitation resources; `TaskListResource` and `TaskResource` become viewer-relative |
| `app/Http/Presenters` | `WorkspacePresenter` gains members, sharing flags, pending invitations |
| `app/Http/Middleware` | `HandleInertiaRequests::share()` gains the notification props |
| `routes/{web,api}.php` | ~7 new routes each |
| `resources/js` | New notification center + share dialog components, sidebar changes, types, Wayfinder regeneration |
| `database/{factories,seeders}` | New factories; `TaskFactory::starred()` reworked; `DemoSeeder` shares lists and seeds stars/invitations |
| `tests` | ~15 files touched, ~10 new; `LayeringTest` contract array; `ApiFoundationTest` route coverage |
| Docs | `AGENTS.md` invariants, `development/scope.md` (move sharing out of Deferred), `docs/project-base.md`, `README.md` |

### Reusable Components

- `DomainException` base + central renderer — no new error plumbing needed.
- `Rule::exists(...)` scoped by `user_id` convention in Form Requests (`.claude/rules/laravel.md`) — extends naturally to membership scoping.
- `resources/js/components/ui/dialog.tsx` for the share dialog; the existing account-menu popover pattern in `sidebar.tsx` for the notification panel.
- The comment author rendering pattern for member avatars.
- Inertia `useForm`/`router` + flash + `withErrors` for accept/decline — no client fetching, so `LayeringTest` stays green.
- Existing optimistic-reorder reconciliation for conflict handling.

### Architecture Decision 1 — Per-user list placement

**Option A (recommended): a single rich pivot, `task_list_members`, owns placement for *every* member including the owner.**

```
task_list_members
  id
  task_list_id  FK -> task_lists  cascadeOnDelete
  user_id       FK -> users       cascadeOnDelete
  status        enum('pending','accepted','declined')  default 'pending'
  folder_id     FK -> folders     nullable  nullOnDelete   -- this member's placement
  position      unsigned int      default 0               -- this member's sidebar order
  invited_by_user_id FK -> users  nullable  nullOnDelete
  invited_at / responded_at  nullable timestamps
  timestamps
  unique  (task_list_id, user_id)
  index   (user_id, status)
  index   (user_id, folder_id, position)
```

`task_lists.folder_id` and `task_lists.position` are **dropped** after backfill. `task_lists.user_id` is retained as the authoritative owner pointer.

**No `role` column.** Ownership is `task_lists.user_id === member.user_id`. A `role` column would be a second, divergable source of truth for the same fact, and there are no viewer/editor tiers in scope (YAGNI). If per-list roles are ever wanted, adding `role` later is additive.

*Trade-offs:* the cleanest model — one code path for "where does this list sit for this user", so `NavigationService`, list reorder, list move, and folder deletion cannot accidentally special-case shared lists. Costs a real expand/contract migration and touches every list read/write and their tests. It also forces the `deleteWithLists` landmine (F11) to surface immediately, because `Folder::taskLists()` stops being a `hasMany` on a column and becomes a relation through the pivot — which is exactly the review moment we want.

**Option B: hybrid — `task_lists.folder_id`/`position` stay as the owner's placement; the pivot only carries collaborators.**

*Trade-offs:* far cheaper migration, no backfill, zero churn in existing tests. But every placement read becomes `if (owner) column else pivot`, duplicated across the repository, navigation service, reorder validation, and folder deletion. Two sources of truth for one concept, which the project's conventions explicitly reject ("favour explicit code over magic", DRY). The bug class it invites — a shared list ordered correctly for the owner and randomly for everyone else — is the kind of thing that only shows up after release. **Rejected.**

**Option C: fan-out — give each member their own `task_lists` row linked by a `shared_group_id`.**

*Trade-offs:* placement and starring become trivially per-user with no pivot at all. But it directly contradicts requirement 5, and every task/comment/subtask write would need replication with conflict resolution. **Rejected**, listed only to show it was considered.

**Recommendation: Option A**, executed as expand → migrate → contract (Steps 1 and 2) so the backfill is independently verifiable before any column is dropped.

### Architecture Decision 2 — Invitation state: one table or two

**Option A (recommended): status lives on the membership row.** A pending invitation *is* a `task_list_members` row with `status = 'pending'`. Accept flips it to `accepted` and assigns `position`. Decline flips it to `declined` and is retained (so the invite does not reappear, and the unique constraint makes re-invite an explicit update rather than a duplicate row). Access = `status = 'accepted'`. Revoke deletes the row.

**Option B: separate `task_list_invitations` + `task_list_members` tables.** Semantically cleaner (an invitation is an event; membership is a state) and a natural home for tokens/expiry if email invitations to unregistered addresses ever ship. Costs a second table, a transactional two-table accept, and two places to check before answering "can this user see this list".

**Recommendation: Option A** on KISS/YAGNI grounds. One table, one access predicate, one unique constraint doing the duplicate-invite work. If Q3 is answered "invite unregistered emails too", revisit — that variant needs a token and an email column and pushes toward Option B.

### Architecture Decision 3 — Per-user starring

**Recommended: a `task_stars` pivot** — `task_id`, `user_id`, `created_at`, unique `(task_id, user_id)`, index `(user_id)`; backfilled from `tasks.is_starred = true` (one row per starred task for its current `user_id`); then `tasks.is_starred` and the `[user_id, is_starred]` index are dropped.

The key implementation choice that keeps the ripple small: **read the star as an aliased exists-subquery, not a loaded relation.**

```php
->withExists(['stars as is_starred' => fn ($q) => $q->where('user_id', $viewer->id)])
```

Eloquent hydrates an `is_starred` attribute, the existing `'is_starred' => 'boolean'` cast still applies, and `TaskResource`, `WorkspacePresenter`, and every TypeScript type keep working unchanged. One extra correlated subquery per task query, no N+1.

The write path does change: `TaskRepository::setStarred(Task, bool)` becomes `star(Task, User)` / `unstar(Task, User)`, and `TaskService::update()` (which currently writes `is_starred` out of `TaskDetailsData`) must receive the acting user and route that field to the star pivot rather than the task row. `Task`'s `#[Fillable]` loses `is_starred`. `TaskFactory::starred()` becomes `starredBy(User)`.

The alternative — keeping `is_starred` for the owner and a pivot for others — is the same dual-source-of-truth mistake as placement Option B. **Rejected.**

### Architecture Decision 4 — Task ownership semantics

**Recommended: `tasks.user_id` is reframed as the creator, exactly like `task_comments.user_id`.** The column stays; a `creator()` relation is added alongside the existing `user()` relation (keeping `user()` avoids churning every factory and test in one step, and `Task::creator()` documents intent at every new call site). Authorization moves entirely to list membership.

**Migration path for existing rows: none required.** Every existing task already has `user_id === taskList.user_id`, which trivially satisfies the new invariant "the creator is a member of the list" once owner membership rows exist. What changes is *enforcement*, not data:

- `TaskRepository::findForUser(int, User)` → `findAccessibleFor(int, User)`, scoped by membership rather than `tasks.user_id`.
- `TaskPolicy::{view,update,comment,delete}` check membership on `$task->taskList`.
- `TaskService::create()` keeps stamping the acting user as creator.
- `AGENTS.md`'s "a task and its list must have the same owner" invariant is rewritten.

Renaming the column to `created_by_user_id` is *not* recommended for this plan — it is pure churn across factories, seeder, repository, and tests for a documentation benefit. Noted as an optional follow-up in Q10.

### Architecture Decision 5 — Notification center

**Option A (recommended): the pending membership row is the notification.** No new table. The bell badge is `count(task_list_members where user_id = me and status = 'pending')`, shared on every Inertia request as a cheap indexed count. The panel's full invitation list is an `Inertia::optional()` shared prop fetched by a partial reload (`router.reload({ only: ['notifications'] })`) when the bell opens — which keeps the browser on Inertia web routes and `LayeringTest` green. Accept/decline post to web routes and return `back()` with flash.

**Option B: Laravel's built-in database notifications** (`Notifiable` is already on `User`; only the `notifications` table migration is missing). Generic and extensible, but it creates a second state machine that must be reconciled with membership state — accepting an invite has to mark the notification read, a stale notification must decline idempotently, and revoking must delete the notification. More moving parts for zero current benefit.

**Recommendation: Option A.** The project's conventions say KISS and YAGNI and "avoid premature abstraction". List invitations are the only notification type that exists. When a second type arrives (reminders are a Deferred capability), introduce Laravel database notifications *then* and render both sources in the same panel — the panel component is the extension point, not the table.

### Architecture Decision 6 — What stays global

Per requirement 5, these remain single-valued on the shared row, with **no** per-user variant: task `title`, `note`, `due_date`, `is_completed`/`completed_at`, task `position`, subtasks (title + completion), comments, and the list `name`. Task order in a shared list is deliberately global — a per-user task order would require a second pivot the size of `tasks`, and Wunderlist's shared lists behaved this way. Only list *placement* (folder + sidebar position) and *starring* are per-user. This split should be stated verbatim in `docs/` and `development/scope.md`.

---

## 3. Step Breakdown

Three phases, matching the precedent set by `docs/11-08-2026-web-rebuild-phase-*.md`. Every step leaves the app deployable and green.

**Phase A (Steps 1–4): data model and authorization.** No user-visible change; every list ends with exactly one member (its owner). This is where the risk lives.
**Phase B (Steps 5–8): sharing lifecycle, guardrails, and both delivery surfaces.**
**Phase C (Steps 9–10): browser experience, demo data, documentation.**

---

### Step 1: Membership table, model, repository, backfill (expand)

- **What:** `task_list_members` exists, is backfilled with one accepted owner row per existing list, and is written on every list create — while `task_lists.folder_id`/`position` remain canonical for reads. Pure expand phase; nothing reads the pivot yet.
- **Where:** new migration `..._create_task_list_members_table.php` (create + backfill from `task_lists`); `app/Models/TaskListMember.php`; `database/factories/TaskListMemberFactory.php`; `app/Repositories/Contracts/TaskListMemberRepositoryInterface.php` + `app/Repositories/EloquentTaskListMemberRepository.php`; binding in `app/Providers/RepositoryServiceProvider.php`; contract array in `tests/Feature/Architecture/LayeringTest.php`; `EloquentTaskListRepository::{create,createDefaultFor}` dual-write; relations on `TaskList`, `User`, `Folder`.
- **How:** schema exactly as Architecture Decision 1. Backfill inside the migration with a chunked insert-select copying `user_id`, `folder_id`, `position`, `status='accepted'`, `responded_at = created_at`. `down()` drops the table. Repository surface: `findMembership(TaskList, User)`, `acceptedMembersFor(TaskList)`, `pendingFor(User)`, `pendingCountFor(User)`, `createOwnerMembership(TaskList)`, `create/updateStatus/updatePlacement/delete`, `nextPositionFor(User, ?int $folderId)`, `countAcceptedFor(TaskList)`. Dual-write for one step only, explicitly temporary and removed in Step 2.
- **Test:** migration test asserting every pre-existing list gained exactly one accepted owner row with the same `folder_id`/`position` (run against a demo-seeded database); repository feature test per method; a data-integrity test asserting `every list has exactly one membership whose user_id equals task_lists.user_id`; `LayeringTest` still green with the new contract registered.
- **Complexity:** Medium

### Step 2: Cut list placement over to the pivot (contract)

- **What:** placement is read and written exclusively from `task_list_members`; `task_lists.folder_id` and `task_lists.position` are dropped. Still no user-visible change.
- **Where:** new migration dropping the two columns and the `[user_id, folder_id, position]` index; `EloquentTaskListRepository` (`allForUser`, `create`, `update`, `nextPosition`, `applyOrder`); `EloquentFolderRepository` (`detachLists`, `deleteWithLists`, `hasLists`, `allForUser`'s `with('taskLists')`); `TaskListService::{create,update,reorder}`; `NavigationService`; `WorkspacePresenter::list()`; `TaskListResource`; `Folder`/`TaskList` relations; `TaskListFactory::inFolder()`; `DemoSeeder`; all list/folder/navigation tests.
- **How:** `allForUser` joins `task_list_members` on `(task_list_id, user_id = $user->id, status = 'accepted')` and selects the member's `folder_id`/`position` as aliased attributes so `NavigationService`'s existing `groupBy('folder_id')` keeps working untouched. `applyOrder` locks and rewrites *membership* rows scoped to `(user_id, folder_id)`, preserving the complete-id-set validation and its exception verbatim. `TaskListService::update()` splits conceptually: `name` is shared state, `folder_id` is the caller's own placement. `Folder::taskLists()` becomes a relation through the pivot. Keep `TaskListResource`'s keys `folder_id`/`position` identical (N6).
- **Test:** every existing list, folder, navigation, and reorder test must pass unmodified in behaviour; new tests that two users' placements of the same list are independent (constructed directly via factories, ahead of any invite UI); reorder still rejects stale/foreign/incomplete id sets; `SoftDeleteVisibilityTest` unaffected; schema test asserting the dropped columns are gone.
- **Complexity:** Large — the highest-risk step in the plan. Review before proceeding.

### Step 3: Per-user starring

- **What:** stars move to a `task_stars` pivot; the Starred view and badge become per-viewer.
- **Where:** migration `..._create_task_stars_table.php` (create + backfill from `tasks.is_starred`); migration dropping `tasks.is_starred` and its index; `Task` (`stars()` relation, `#[Fillable]`, casts docblock); `User::starredTasks()`; `TaskRepositoryInterface`/`EloquentTaskRepository` (`starredForUser`, `starredCountForUser`, `activeForList`, `completedForList`, `findAccessibleFor`, `loadDetails`, `star`, `unstar`, remove `setStarred`); `TaskService::{setStarred,update,starredFor,tasksFor}`; `Services/Data/TaskDetailsData` usage; `Web/StarTaskController`, `Api/V1/TaskController`; `TaskFactory`; `DemoSeeder::applyShowcase()`; `tests/Unit/Services/TaskServiceTest.php` (mocks the repository interface — signatures change).
- **How:** every task-returning repository method takes the viewing `User` and applies the `withExists(['stars as is_starred' => ...])` alias from Architecture Decision 3, so `TaskResource`, `WorkspacePresenter`, and TypeScript types are untouched. `starredForUser` joins the pivot instead of filtering `tasks.user_id`, keeping the existing `whereHas('taskList')` guard so trashed-list tasks stay hidden. Star writes are `upsert`/`delete` on the pivot and are idempotent.
- **Test:** starring by user A does not star for user B on the same shared task (constructed via factories); Starred view and sidebar badge count only the viewer's stars; star/unstar idempotent; `PUT /api/v1/tasks/{task}` with `is_starred` toggles only the caller's star; backfill migration test asserting previously starred tasks produce exactly one star row for their creator; existing `StarredTaskTest` and `EloquentTaskRepositoryTest` updated.
- **Complexity:** Large

### Step 4: Membership-based authorization and creator reframing

- **What:** every policy and every user-scoped lookup resolves through membership instead of `user_id`. Behaviour is unchanged (only owners are members), so this is a pure, fully testable refactor.
- **Where:** `TaskListPolicy`, `TaskPolicy`, `SubtaskPolicy`; new abilities `TaskListPolicy::{share,manageMembers,leave}`; `EloquentTaskListRepository::findForUser` → `findAccessibleFor`; `EloquentTaskRepository::{findForUser,findDeletedForUser}` → membership-scoped; `TaskService::undelete`; `Task::creator()`; `AGENTS.md` domain invariants. (`TaskService::move()` is deliberately *not* touched here — per Q8 it keeps ownership-scoped resolution; see Step 6.)
- **How:** a single `TaskListPolicy::view()` predicate — "an accepted membership exists" — that every other check delegates to, so there is exactly one definition of access. `update`/`delete` on `TaskList` stay owner-only where ownership matters (`delete` also keeps its `is_default` guard); `share`/`manageMembers` are owner-only pending Q4. `TaskPolicy` and `SubtaskPolicy` delegate to list access. Memberships are eager-loaded or resolved through a single repository call per request to avoid a policy-driven N+1.
- **Test:** existing "another user gets 403/404" tests all still pass; new tests that a `pending` or `declined` membership grants no access at all; a non-member cannot read, update, complete, star, comment on, reorder, or delete a task in a list they were never invited to; `Task::creator()` returns the creating user for a task created by a non-owner (factory-constructed).
- **Complexity:** Medium

### Step 5: Sharing domain — invite, accept, decline, revoke, leave

- **What:** `ListSharingService` implements the full lifecycle with all business rules. No routes yet; service-level only.
- **Where:** `app/Services/ListSharingService.php`; `app/Exceptions/{TaskListCannotBeSharedException, UserNotFoundForInvitationException, AlreadyMemberException, CannotInviteSelfException, TaskListMemberLimitReachedException, NotAMemberException, OwnerCannotLeaveListException}.php`; `config/sharing.php` (member cap); `TaskListMemberRepository` additions.
- **How:**
  - `invite(TaskList $list, User $inviter, string $email)` — rejects `is_default` lists (F9), self-invite, an existing accepted membership, and a list already at the cap; resolves the invitee by exact lowercase email; re-invite after a decline flips the existing row back to `pending` and refreshes `invited_at` (the unique constraint makes this the natural path); a duplicate pending invite is idempotent.
  - `accept(TaskListMember $membership, User $user)` — inside a transaction: re-check the membership still belongs to the caller and is still `pending`, set `accepted`, `responded_at = now()`, `folder_id = null` (F4), `position = nextPositionFor($user, null)`.
  - `decline` — sets `declined`, `responded_at`.
  - `revoke(TaskList $list, User $actor, User $member)` — owner only, deletes the row, cannot target the owner.
  - `leave(TaskList $list, User $user)` — deletes the caller's row; the owner cannot leave (Q6).
  - Every mutation transactional with preconditions re-checked inside (N5). No queries in the service (`LayeringTest`).
- **Test:** unit/feature coverage for each happy path plus every rejection; the accepted member's list appears ungrouped at the end of their own ungrouped collection and the sharer's `folder_id` is unaffected; declining twice is idempotent; re-invite after decline works; cap enforced; Inbox rejected.
- **Complexity:** Medium

### Step 6: Destructive-path guardrails under sharing

- **What:** close the paths where one member's ordinary action would destroy another member's data. This must land *before* any sharing UI exists.
- **Where:** `EloquentFolderRepository::{deleteWithLists,detachLists}`; `FolderService`; `TaskListService::delete`; `TaskService::move`; web/API list-destroy controllers; `TaskReorderMismatchException` message; `Web/DestroyFolderRequest`.
- **How:**
  - **Folder deletion (F11):** `deleteWithLists($folder, $actor)` force-deletes only lists where `task_lists.user_id === $actor->id`; every shared list in that folder has the actor's *membership placement* detached to ungrouped instead. `detachLists` likewise nulls the actor's membership `folder_id`. Both stay transactional.
  - **List deletion (F10/F12):** `TaskListService::delete()` requires ownership; a non-owner calling destroy is routed to `leave()` instead (or rejected with `NotAMemberException`/403 — see Q6). The owner's delete surfaces the collaborator count so the UI can confirm meaningfully.
  - **Task move (F15 — DECIDED, Q8: out of scope):** `TaskService::move()` keeps its current ownership-scoped `findForUser()` resolution unchanged, plus one new guard: reject the move (a new domain exception, e.g. `TaskMoveAcrossSharedListBoundaryException`) whenever either the source or the destination list has more than one accepted member. No membership-based widening of move at all — this is the simplest possible implementation of "out of scope."
  - **Reorder conflict (F16):** reword the exception message to a collaboration conflict and confirm the existing optimistic-UI reconciliation path handles it.
- **Test:** a member who filed a shared list into their folder and then deletes that folder with the destructive workflow **still leaves the owner's list and all its tasks intact**, merely ungrouped for themselves (this test is the reason the step exists); a non-owner's delete removes only their membership; the owner's delete soft-deletes for everyone; a move attempt into or out of a shared list is rejected regardless of actor role; a stale reorder from a second member is rejected without writing.
- **Complexity:** Medium

### Step 7: API surface (`/api/v1`)

- **What:** the full sharing lifecycle over Sanctum.
- **Where:** `routes/api.php` (inside the existing `auth:sanctum` group, literal segments before `{list}` bindings); `app/Http/Controllers/Api/V1/{TaskListMemberController, ListInvitationController, TaskListMembershipController}.php`; `app/Http/Requests/Api/V1/{StoreTaskListInvitationRequest, DestroyTaskListMemberRequest, RespondToInvitationRequest}.php`; `app/Http/Resources/Api/V1/{TaskListMemberResource, ListInvitationResource}.php`; `TaskListResource` gains `is_shared`, `is_owner`, `member_count`.
- **How:** routes —
  `GET lists/{list}/members`, `POST lists/{list}/members` (invite by email), `DELETE lists/{list}/members/{user}` (revoke), `DELETE lists/{list}/membership` (leave), `GET invitations`, `POST invitations/{invitation}/accept`, `POST invitations/{invitation}/decline`.
  Authorization in Form Requests via the new policy abilities; `throttle:` on the invite route (N8). Errors flow through the existing `DomainException` envelope with new stable `error_code`s. Member payloads expose name + avatar; email only when the requester owns the list (F18).
- **Test:** endpoint tests for each route covering owner, member, non-member, and unauthenticated; `ApiFoundationTest` still asserts every `api/v1` route carries `auth:sanctum`; invite rate limit returns 429; every new `error_code` asserted so the contract is pinned.
- **Complexity:** Medium

### Step 8: Web surface and shared notification props

- **What:** the same lifecycle over Inertia web routes, plus the shared props the notification center will render.
- **Where:** `routes/web.php`; `app/Http/Controllers/Web/{TaskListMemberController, ListInvitationController, TaskListMembershipController}.php`; matching `app/Http/Requests/Web/*`; `HandleInertiaRequests::share()`; `WorkspacePresenter`; `resources/js/types/index.ts`; Wayfinder regeneration.
- **How:** mirror the API routes as web routes returning `back()->with('success'|'error', ...)`, per the existing web mutation controllers. `share()` gains `notifications.pendingInvitationCount` (always, one indexed count) and `notifications.invitations` as `Inertia::optional(...)` so the full list is only computed on a partial reload — verify the exact helper name in the installed `inertiajs/inertia-laravel` ^3.0. `WorkspacePresenter::forList()` adds `currentList.members`, `currentList.isShared`, `currentList.isOwner`, `currentList.canManageSharing`; `list()` adds `isShared` so the sidebar can badge shared lists.
- **Test:** Inertia feature tests asserting prop shapes; accept/decline via web routes redirect back with flash and the invitation disappears from the shared prop; a non-member posting to a share route gets 403; `LayeringTest` still green.
- **Complexity:** Medium

### Step 9: Notification center (bell)

- **What:** the disabled bell at `sidebar.tsx:407` becomes a working notification center listing pending list invitations with Accept and Decline.
- **Where:** `resources/js/components/navigation/notification-center.tsx` (new); `resources/js/components/navigation/sidebar.tsx`; `resources/css/app.css` (tokens/styles); `resources/js/types/index.ts`.
- **How:** the bell becomes enabled with an accessible label and a count badge (`aria-label="Notifications, N pending"`). Opening triggers `router.reload({ only: ['notifications'] })` to hydrate the full list, then renders a popover following the existing account-menu pattern: inviter name + avatar, list name, relative time, Accept/Decline buttons posting via `useForm` to the Step 8 web routes with per-row pending state. Empty state per `AGENTS.md`'s empty-state convention ("You're all caught up."). Accept flashes success and the list appears ungrouped in the sidebar on the same response. Escape closes, focus is trapped and restored, reduced-motion respected.
- **Test:** Inertia feature test that a user with a pending invitation receives the count prop and that accepting adds the list to their navigation tree; frontend verified via `npm run build` typecheck plus a manual keyboard/screen-reader pass (documented in the step's verification notes).
- **Complexity:** Medium

### Step 10: Share dialog, shared-list affordances, demo data, documentation

- **What:** the list-level sharing UI, the visual signal that a list is shared, the non-owner action set, refreshed demo data, and all documentation.
- **Where:** `resources/js/components/lists/share-dialog.tsx` (new, built on `components/ui/dialog.tsx`); `resources/js/pages/workspace/show.tsx`; `resources/js/components/navigation/sidebar.tsx`; `database/seeders/DemoSeeder.php`; `database/factories/TaskListMemberFactory.php`; `AGENTS.md`; `development/scope.md`; `docs/project-base.md`; `docs/frontend.md`; `README.md`.
- **How:** a "Share" action on non-Inbox lists opens a dialog showing current members (avatar + name; email for the owner only), pending invitations with a Revoke action, and an invite-by-email field surfacing server-side `errors` from the Form Request or the domain-exception error bag. Non-owners see a member list and a "Leave list" action instead of Delete; the sidebar shows a shared indicator on shared lists. `DemoSeeder` shares 2–3 lists between demo users, seeds per-user stars via the new factory state, and leaves at least one pending invitation for `demo1@example.com` (F23). Docs: move list sharing out of `scope.md`'s Deferred capabilities into Current release scope, state the global-vs-per-user split verbatim, record the "no live sync" boundary (F19), rewrite the `AGENTS.md` task-ownership invariant (F14), and record the N6 justification for keeping the change inside V1.
- **Test:** `DemoSeederTest` extended to assert shared lists, per-user stars, and a pending invitation exist and satisfy every invariant; Inertia test for share-dialog props; owner and non-owner see different action sets; full gate `composer test` + `npm run build`; manual walkthrough of invite → notification → accept → file into a folder → star independently → edit visible to both.
- **Complexity:** Large

---

## 4. Risk Assessment

### Risks

- **R1 (Critical) — `deleteWithLists()` force-deletes across owners.** Today `EloquentFolderRepository::deleteWithLists()` calls `forceDelete()` on every list in a folder. The moment a collaborator can file a shared list into a folder, the documented destructive folder workflow irreversibly destroys the owner's list *and* cascades to its tasks. There is no Trash UI to recover from.
- **R2 (Critical) — Authorization regression.** Rewriting every policy from a one-line ownership check to a membership lookup is the highest-consequence change here. A mistake exposes one user's tasks to another.
- **R3 (High) — Step 2 placement cutover.** Dropping `task_lists.folder_id`/`position` touches navigation, list ordering, folder deletion, the presenter, the API resource, the seeder, and roughly a dozen test files at once. A bad backfill silently scrambles every user's sidebar.
- **R4 (High) — Star migration data loss.** Dropping `tasks.is_starred` after a backfill is irreversible in practice; a chunking or scoping error loses every star silently, with no user-visible error.
- **R5 (Medium) — N+1 from membership and star lookups.** The sidebar renders every list; the workspace renders every task. A policy that lazy-loads membership per model, or a star relation loaded per task, turns one page into hundreds of queries.
- **R6 (Medium) — Cross-user data leakage through eager loads.** `with('comments.author')` already loads other users' `User` models. Careless resource/presenter changes could expose emails or profile paths between collaborators (F18).
- **R7 (Medium) — Reorder and edit conflicts with no live sync.** Two members in one list will hit stale reorder rejections and will see each other's edits only after a page load. Without clear messaging this reads as data loss.
- **R8 (Medium) — Email enumeration via the invite form.** "Invite by email of a registered user" tells an attacker which addresses have accounts.
- **R9 (Medium) — Owner account/list deletion destroys shared work.** `task_lists.user_id` is `cascadeOnDelete`; deleting the owner user hard-deletes shared lists and cascades to tasks with no warning to collaborators.
- **R10 (Low) — V1 contract drift.** `is_starred`, `folder_id`, and `position` keep their names but become viewer-relative; a future native client author could reasonably misread them as global.
- **R11 (Low) — `LayeringTest` / `ApiFoundationTest` breakage.** The layering test enumerates repository contracts explicitly and forbids `->where(` in services; the API foundation test requires every route inside the Sanctum group. Both fail loudly and early, which is the intended behaviour.
- **R12 (Low) — `Inertia::optional()` helper name.** The exact API for deferred shared props in the installed `inertiajs/inertia-laravel` ^3.0 should be confirmed against the vendor source before Step 8.

### Mitigations

- **R1:** Step 6 exists specifically for this, and its acceptance test is "the owner's list survives a collaborator's destructive folder delete." Do not ship any sharing UI (Steps 9–10) before Step 6 is reviewed and merged.
- **R2:** Step 4 lands *before* anyone can be a member, so it is a behaviour-preserving refactor verifiable by the existing cross-user denial tests. Add explicit tests that `pending` and `declined` memberships grant zero access. Define access in exactly one policy predicate that every other check delegates to.
- **R3:** Expand/migrate/contract across two steps — Step 1's backfill is verified against a demo-seeded database before Step 2 drops anything. Take a database file copy before running the drop migration locally. Both migrations get working `down()` methods and a schema assertion test.
- **R4:** Same expand/contract discipline; the backfill migration gets its own test asserting a one-to-one mapping from `is_starred = true` rows to `task_stars` rows before the drop migration runs.
- **R5:** Use the `withExists` alias rather than a loaded relation for stars; join membership in `allForUser` rather than resolving it per row; add an explicit query-count assertion to the navigation and workspace tests.
- **R6:** Introduce a dedicated `TaskListMemberResource`/presenter shape that whitelists `id`, `name`, `avatarUrl`, and conditionally `email` for the owner only. Never pass a raw `User` model into a member payload.
- **R7:** Reword `TaskReorderMismatchException` as a collaboration conflict; confirm the existing optimistic reconciliation refreshes from canonical props; document "no live sync in v1" in `scope.md`.
- **R8:** Rate-limit the invite endpoint on both surfaces, and treat the enumeration exposure as an accepted, documented trade-off — or adopt the generic-response variant (Q3).
- **R9:** Keep account deletion out of scope (it does not exist in the current release) and record the constraint in `docs/` so it is designed for before that feature ships. Decide list-deletion semantics via Q6.
- **R10:** Document the viewer-relative semantics in `docs/project-base.md` and in the resource docblocks, and state the N6 justification for staying in V1.
- **R11:** Add the new contract to `LayeringTest`'s array in Step 1; register every new route inside the Sanctum group in Step 7.
- **R12:** Read the installed adapter's source before writing Step 8; fall back to always-computed props if the deferred helper differs.

### Fallbacks

- **If Step 2 proves too destabilising:** fall back to placement Option B (hybrid) *temporarily*, behind a single `ListPlacementResolver` seam so the hybrid branch exists in exactly one class and can be collapsed later. Accept the documented debt; do not scatter the branch.
- **If per-user starring is too large to land with the rest:** ship Phase A Steps 1–2 and 4 plus all of Phase B with starring still global, and gate sharing behind the star migration — i.e. do not release sharing to users until Step 3 lands. Do **not** release shared lists with global stars; requirement 8 is explicit and a shared global star is a visible data-correctness bug.
- **If the notification center slips:** invitations can be surfaced temporarily as a banner on the workspace page driven by the same shared prop, with the bell enabled later. The backend contract is unchanged.
- **If email-based invitation resolution is contentious:** ship invite-by-exact-email for registered users only behind a rate limit, and defer unregistered-email invitations entirely — the membership table needs no schema change to add them later beyond a nullable `invited_email` column and a token.

---

## 5. Execution Checklist

- [x] **Step 0:** User answered Section 6 (see decisions inline above). Ready to begin Step 1.
- [ ] **Step 1:** `task_list_members` table, model, factory, repository + contract (registered in `LayeringTest`), backfill migration, dual-write on list creation. *(Medium)*
- [ ] **Step 2:** Placement cutover to the pivot; drop `task_lists.folder_id` and `position`; update repositories, services, navigation, presenter, resource, seeder, tests. *(Large — review carefully)*
- [ ] **Step 3:** `task_stars` pivot with backfill; drop `tasks.is_starred`; viewer-relative star reads via `withExists`; star/unstar writes; factories, seeder, tests. *(Large)*
- [ ] **Step 4:** Membership-based policies and lookups; `Task::creator()`; `AGENTS.md` invariant rewrite. *(Medium)*
- [ ] **Step 5:** `ListSharingService` — invite, accept, decline, revoke, leave — with domain exceptions and the member cap. *(Medium)*
- [ ] **Step 6:** Destructive-path guardrails: folder deletion, list deletion vs leave, cross-boundary task move, reorder conflict messaging. *(Medium)*
- [ ] **Step 7:** `/api/v1` sharing and invitation endpoints, requests, resources, throttling, tests. *(Medium)*
- [ ] **Step 8:** Web sharing routes and controllers; `HandleInertiaRequests` notification props; presenter and TypeScript types; Wayfinder. *(Medium)*
- [ ] **Step 9:** Notification center behind the existing bell button, with accept/decline. *(Medium)*
- [ ] **Step 10:** Share dialog, shared-list affordances, non-owner action set, demo seeder, all documentation. *(Large)*

Quality gate after every step: `composer test` and, for Steps 8–10, `npm run build`. `code-reviewer` approval required before starting the next step. Steps 2, 3, and 6 additionally require a manual review of the migration/guardrail diff before merge.

---

## 6. Open Questions for the User

Implementation should not start until these are answered. Each one materially changes the data model, the authorization boundary, or the UX — the cases `AGENTS.md` says to pause on.

**Q1 — Placement model.** Confirm Architecture Decision 1: a single `task_list_members` pivot owns folder + position for *every* member including the owner, and `task_lists.folder_id`/`position` are dropped. The alternative (hybrid: columns for the owner, pivot for collaborators) is cheaper but creates two sources of truth. *Recommendation: the unified pivot.*

**Q2 — Who can manage sharing? DECIDED: owner only.** Only the list owner may invite or revoke. `TaskListPolicy::{share,manageMembers}` are owner-only checks (`$user->id === $taskList->user_id`); any accepted member may still `leave` regardless of who owns the list (Q6).

**Q3 — Invitation identity. DECIDED: strictly by email of an existing registered user.** No pending-signup flow, no token, no unregistered-email invitations. This locks in Architecture Decision 2 exactly as written (status lives on the membership row, no separate invitations table) and confirms `invite()` resolves by exact lowercase email match, rejecting unknown addresses (`UserNotFoundForInvitationException`, per Step 5). The email-enumeration trade-off (R8) is accepted as-is, mitigated only by rate limiting (N8) — no generic silent-response variant.

**Q4 — Can a collaborator rename a shared list? DECIDED: yes, any accepted member can rename.** Matches the recommendation — the list name is shared state on the single row, consistent with requirement 5.

**Q5 — Task creator semantics.** Confirm that `tasks.user_id` becomes creator/attribution only (like `task_comments.user_id`), with all authorization moving to list membership. Existing rows need no data change. *Recommendation: yes.* **Still open — assuming yes unless you object; this is what Architecture Decision 4 and Step 4 are already written against.**

**Q6 — Owner deletes a shared list, and owner leaving. DECIDED: (a) — only the owner can delete, and it soft-deletes for everyone (behind a confirmation naming the collaborator count).** Any member — including one who already accepted — can leave voluntarily at any time; leaving never requires the owner's involvement. **One sub-point assumed rather than explicitly stated: can the owner themselves leave (as opposed to delete)?** Adopting the original recommendation — **the owner cannot leave**, since there is no ownership-transfer mechanism in v1; their only way out is to delete the list outright (which removes it for every member). Flag if you actually want owner-leave (it would need an ownership-transfer or "list becomes orphaned" rule, which is real added scope).

**Q7 — Owner account deletion.** Account deletion does not exist in the current release, and `task_lists.user_id` is `cascadeOnDelete`, so adding it later would silently destroy collaborators' shared lists. *Recommendation: keep out of scope and record the constraint in `docs/` as a prerequisite for that feature.* Confirm you are comfortable deferring it.

**Q8 — Cross-boundary task moves. DECIDED: out of scope.** Reversed from the original recommendation. A task can never move into or out of a shared list, in either direction, regardless of who initiates it — this applies uniformly to the owner and to members, not just to non-owner members moving content out. See F15 and the simplified Step 6 for the implementation (a rejection guard, not a widened `findAccessibleFor` resolution).

**Q9 — No live sync.** With no broadcasting installed, members see each other's changes only on the next request or navigation. *Recommendation: accept for v1 and document it in `scope.md`; do not add polling or Reverb in this plan.* Confirm.

**Q10 — Scope confirmations:**
- (a) Inbox can never be shared — **assumed yes** (unchanged from recommendation; object if not).
- (b) Task order and completion stay global (no per-user divergence) — **assumed yes**.
- (c) Member cap per list — **DECIDED: 10** (owner + 9 collaborators), configurable via `config/sharing.php`.
- (d) Re-inviting a user who previously declined is allowed (rate-limited) — **assumed yes**.
- (e) Members see each other's name and avatar; email is visible to the owner only — **assumed yes**.
- (f) No email notification for invitations in v1 (in-app notification center only) — **assumed yes**.
- (g) Renaming `tasks.user_id` to `created_by_user_id` is deliberately *not* done (churn without behaviour change) — **assumed yes (not renamed)**.

Items (a), (b), (d), (e), (f), (g) above, plus **Q1** (unified `task_list_members` pivot for placement), **Q5** (`tasks.user_id` becomes creator/attribution only), **Q7** (account deletion stays out of scope), and **Q9** (no live sync — changes visible only on next request/navigation) are being treated as adopted by default per their stated recommendation, since none of them were objected to and each already has a documented, reasoned trade-off above. Flag any of these before implementation starts if you disagree — otherwise the plan proceeds with all of Section 6 resolved.

---

**Key files this plan touches (absolute paths, for handoff):**

- `/Users/ricardomota/Documents/projects/myfabulist/app/Repositories/EloquentFolderRepository.php` — the `deleteWithLists()` force-delete landmine (R1)
- `/Users/ricardomota/Documents/projects/myfabulist/app/Repositories/EloquentTaskListRepository.php`
- `/Users/ricardomota/Documents/projects/myfabulist/app/Repositories/EloquentTaskRepository.php`
- `/Users/ricardomota/Documents/projects/myfabulist/app/Services/{TaskListService,TaskService,NavigationService,FolderService}.php`
- `/Users/ricardomota/Documents/projects/myfabulist/app/Policies/{TaskListPolicy,TaskPolicy,SubtaskPolicy}.php`
- `/Users/ricardomota/Documents/projects/myfabulist/app/Http/Presenters/WorkspacePresenter.php`
- `/Users/ricardomota/Documents/projects/myfabulist/app/Http/Middleware/HandleInertiaRequests.php`
- `/Users/ricardomota/Documents/projects/myfabulist/app/Http/Resources/Api/V1/{TaskListResource,TaskResource}.php`
- `/Users/ricardomota/Documents/projects/myfabulist/resources/js/components/navigation/sidebar.tsx` (bell at line 407)
- `/Users/ricardomota/Documents/projects/myfabulist/resources/js/types/index.ts`
- `/Users/ricardomota/Documents/projects/myfabulist/tests/Feature/Architecture/LayeringTest.php` (hardcoded contract array)
- `/Users/ricardomota/Documents/projects/myfabulist/database/seeders/DemoSeeder.php`
- `/Users/ricardomota/Documents/projects/myfabulist/AGENTS.md` and `/Users/ricardomota/Documents/projects/myfabulist/development/scope.md`
