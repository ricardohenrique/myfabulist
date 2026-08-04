**Date:** 04-08-2026
**Plan ID:** 3
**Status:** Draft
**Complexity:** Large

## 0. Phase Boundary (read first)

**Depends on Phases A and B.** Reordering needs the sidebar tree and the list page (A); the undo
bar and the polish pass assume the row anatomy and details flyout (B).

**In this plan:** manual reordering of tasks, lists and folders (S4) — accessible move up/down
first, drag and drop second; undo for reversible actions (S7); the interaction behaviour described
in frontend.md (completion animation, immediate feedback, background saves); the visual-style pass;
and the responsive + accessibility sweep.

**Explicitly NOT in this plan:** undo of a *deletion*. See D4 — it requires soft deletes (a schema
change plus repository/service changes), which is outside the "frontend only, backend is done"
scope of this series and needs a separate decision.

**Backend scope:** none, unless the user chooses the soft-delete option in D4 (which would become
its own plan). Reordering uses the existing `TaskService::reorder()`, `TaskListService::reorder()`
and `FolderService::reorder()`.

## 1. Requirements Analysis

### Functional Requirements

- [ ] Tasks can be reordered within a list (S4).
- [ ] Lists can be reordered, within a folder and among the ungrouped lists (S4).
- [ ] Folders can be reordered in the sidebar (S4).
- [ ] Reordering is available by drag and drop **and** by accessible move-up / move-down actions (S4).
- [ ] A reorder updates the interface immediately and saves in the background (frontend.md
      "Interaction behavior").
- [ ] Completing a task animates out of the active section into the Completed section (frontend.md).
- [ ] A brief Undo is offered after completing a task, moving a task, and starring/unstarring (S7).
- [ ] Deletions keep an explicit confirmation (M7 allows confirmation *or* undo).
- [ ] The interface matches the frontend.md visual language: light neutral background, white
      surfaces, soft borders, rounded corners, subtle animation, muted completed tasks, one accent.
- [ ] The full workflow works at mobile, tablet and desktop widths (M9), and by keyboard.

### Non-Functional Requirements

- [ ] **No new npm dependency and no separate JS build for state** — reordering uses Livewire 4's
      built-in `wire:sort` (verified present in `vendor/livewire/livewire/dist/livewire.esm.js`,
      `js/features/supportWireSort.js`, backed by the bundled Alpine sort/SortableJS).
- [ ] A drag that fails server-side must leave the UI showing the truth (re-render from the database),
      not a lie.
- [ ] Reorder writes stay atomic — they already are, inside the repositories' `DB::transaction()`.
- [ ] Keyboard-only users can do everything drag-and-drop users can.
- [ ] `composer test` green after every step. Drag-and-drop *behaviour* is tested at the Livewire
      method level (the browser interaction itself is out of scope for PHPUnit).

## 2. Architecture Review

### Existing Codebase Patterns (verified)

- `TaskService::reorder(TaskList, array $taskIds): void` — the repository validates the submitted id
  set against the list's current ids and throws `TaskReorderMismatchException` **before writing
  anything**; the write is transactional.
- `TaskListService::reorder(User, ?int $folderId, array $taskListIds): void` — scoped by folder, so a
  list must already be **in** the target folder before its position is written there.
- `FolderService::reorder(User, array $folderIds): void`.
- `TaskRepositoryInterface::idsForList(TaskList)` exists for set validation.
- Livewire 4 `wire:sort` API (verified in the dist bundle): `wire:sort="handler($item, $position)"`
  on the container, `wire:sort:item="{{ $id }}"` on each child, optional `wire:sort:handle`,
  `wire:sort:group="name"` + `wire:sort:group-id="{{ $id }}"` (which appends a third `$id` argument
  identifying the destination container), and `.renderless` / `.async` modifiers.
- `Flux::toast()` supports only `heading`, `text` and a `link` (`<a href>`) — **it cannot host a
  Livewire action button** (verified in the toast stub). Undo cannot be a toast.
- Alpine `x-collapse`, `x-transition` and `$persist` are bundled with Livewire and already used.

### Architecture Decision

**C1 — Accessible move up/down ships first; drag and drop is layered on top.**
Both are S4 requirements, but the button version is pure server logic, fully unit-testable, and
becomes the correctness reference for the drag handler (both funnel into the same private
"compute the new id array" method). Shipping it first means Step 2 can fail without leaving
reordering unavailable.

**C2 — The `(item, position)` → full-ordered-array conversion happens in the Livewire component.**
`wire:sort` hands the component the moved id and its new index; the services take a complete ordered
array. The component reads the current order from the same computed read model it just rendered,
removes the moved id, splices it back at `$position`, and passes the result to `reorder()`. No
service or repository change, and the repository's set validation still catches any drift (a stale
client sending an id that no longer exists fails cleanly with `TaskReorderMismatchException` and
nothing is written).
Rejected: adding a `moveTo(Task, int $position)` service method — it would duplicate ordering rules
that already live in `applyOrder()`, and the API has no need for it.

**C3 — Drag uses `.renderless` plus an explicit refresh on failure.**
On a successful drag the DOM already shows the desired order, so re-rendering it is wasted work and
causes a visible flicker; `.renderless` is exactly the "update the interface immediately and save in
the background" behaviour frontend.md asks for. On a caught `DomainException` the handler dispatches
a refresh so the UI snaps back to the persisted truth and shows a danger toast. This is the one
place where "optimistic" is real rather than cosmetic, so the failure path is specified before the
happy path is written.

**C4 — Undo covers reversible state changes only; deletions keep a confirmation.**
Complete → `restore()`. Move → `move()` back to the original list. Star → `setStarred()` back.
All three are existing, idempotent service calls with no data loss, so undo is honest and cheap.
**Deleting** a task or a list is a hard delete today (`tasks.task_list_id` cascades), so "undo"
would mean re-creating a different row with a new id and, for a list, re-creating every task —
lossy and misleading. M7 explicitly permits "a confirmation **or** a short undo period", so deletes
keep `wire:confirm`. Three options were considered and are recorded for the user to choose from
later: (a) **soft deletes** — a `deleted_at` migration plus repository/service changes; correct and
the recommended eventual answer, but a backend change and therefore its own plan; (b) **deferred
delete** — hold the delete in component state for N seconds and commit on timeout; fragile (a
navigation or a refresh loses the intent, and the row is either a ghost or gone); (c) **snapshot and
re-create** — new ids, broken references, misleading. This plan does (none of the above) and ships
confirmations; approval is needed before (a).

> **Amendment (post-implementation).** Option (a) was approved and shipped as
> `docs/04-08-2026-soft-deletes-for-task-and-list-deletion-4.md` (Plan 4). Tasks and lists are now
> soft deleted and recoverable via `TaskService::undelete(Task $task, User $user)` and
> `TaskListService::undelete(TaskList $taskList)`, so the "deletion is a hard delete, so undo would
> be lossy" rationale above is stale — S7's "Undo after deleting a task / deleting a list" is now
> honest and should be implemented. Wiring that undo bar (Step 4 below still says deletes set **no**
> `lastAction`, which this amendment supersedes) is deliberately **not** done by Plan 4 itself — see
> that plan's closing §6 for the precise remaining steps: a `'delete'` `lastAction` type, a
> trashed-aware re-resolution helper (`findDeletedForUser`, not `findForUser`), authorizing with the
> existing `delete` ability (not a new `restore`/`undelete` ability — Plan 4's D4), catching
> `TaskCannotBeUndeletedException` with a danger toast, and a home for list-deletion undo (this
> component has no undo bar for lists today). Treat this as the accurate to-do list the next time
> Step 4 below is executed or re-planned.

**C5 — Undo is a component-owned inline bar, not a toast.**
The Flux toast has no action slot (verified), so `Tasks\TaskPanel` (and `StarredPanel`) render a
small dismissible bar with the last reversible action's description and an "Undo" button, held in
component state (`?array $lastAction` with `type`, `taskId`, and the minimal payload needed to
reverse it), auto-hidden client-side after ~8 seconds via Alpine. It holds exactly one action —
undo is a safety net for the last thing you did, not a history stack (YAGNI, and a stack invites
"undo an undo" semantics nobody asked for).

**C6 — Cross-folder list dragging is deliberately excluded from drag and drop.**
`TaskListService` moves a list between folders with `update()` (which also renames, replace
semantics) and orders it with `reorder()` (scoped per folder). A cross-folder drop is therefore two
non-atomic service calls — a half-failure leaves the list in the new folder at the wrong position.
Dragging is scoped **within** a folder (and within the ungrouped group) via `wire:sort:group` with
no cross-group drops; moving between folders stays the explicit "Move" action from Phase A's
`ListDialog`, which is atomic and already tested. Revisit if the user wants cross-folder dragging —
it wants a `TaskListService::moveTo(list, folderId, position)` method wrapping both writes, which is
a backend change.

**C7 — Dragging tasks between lists is out of scope for this phase.**
`wire:sort:group-id` would make sidebar lists drop targets and `TaskService::move()` already accepts
a position, so it is feasible — but it means the sidebar and the task panel become one drag context
across two Livewire components, which is a materially different risk profile from an in-list sort.
Move-to-list from Phase B covers the requirement; frontend.md only asks for drag and drop for
*reordering*.

## 3. Step Breakdown

### Step 1: Accessible task reordering (S4, keyboard path)

- **What:** Move up / move down actions on active task rows, persisting the new order.
- **Where:** `app/Livewire/Tasks/TaskPanel.php`;
  `resources/views/components/tasks/task-row.blade.php`; `tests/Feature/Livewire/TaskPanelTest.php`
- **How:**
  - `moveTaskUp(int $taskId)` / `moveTaskDown(int $taskId)` → authorize `update` on the task →
    build the current active id array from `$this->tasks->active` → swap with the neighbour → 
    `TaskService::reorder($list, $ids)`.
  - Extract `private function reorderedIds(array $current, int $movedId, int $position): array` now —
    Step 2's drag handler reuses it verbatim (C1/C2).
  - Menu items ("Move up" / "Move down") in the row menu, disabled at the ends, with clear
    `aria-label`s. Only active tasks are reorderable (completed tasks are ordered by completion time).
  - Catch `TaskReorderMismatchException` → danger toast + refresh.
- **Test:** moving a middle task up/down rewrites positions as expected; moving the first task up is
  a no-op; the completed section is unaffected; another user's task is refused; positions survive a
  re-render (assert the rendered order, not just the column values).
- **Complexity:** Medium

### Step 2: Task drag and drop (S4, pointer path)

- **What:** Active tasks can be dragged into a new order, saved in the background.
- **Where:** `resources/views/livewire/tasks/task-panel.blade.php`;
  `resources/views/components/tasks/task-row.blade.php`; `app/Livewire/Tasks/TaskPanel.php`;
  `tests/Feature/Livewire/TaskPanelTest.php`
- **How:**
  - Container: `<div wire:sort.renderless="reorderTask($item, $position)">`; each active row:
    `wire:sort:item="{{ $task->id }}"` and a dedicated `wire:sort:handle` grip (so the inline title
    input and the row menu stay clickable — R7 of Phase B).
  - `reorderTask(int $taskId, int $position)` → authorize → `reorderedIds(...)` (Step 1) →
    `TaskService::reorder()`. On `DomainException`: toast + `unset($this->tasks)` and dispatch a
    refresh so the DOM returns to the persisted order (C3).
  - `wire:key` on every row is mandatory for correct DOM diffing after a sort.
  - Completed rows are **not** sortable.
- **Test:** `Livewire::test(...)->call('reorderTask', $id, 2)` produces the expected persisted order
  (the same assertions as Step 1, through the drag entry point); an id from another list is refused
  and nothing is written; the order is unchanged after a failed reorder. Manual browser check that
  the handle drags and the row's other controls still work.
- **Complexity:** Medium

### Step 3: List and folder reordering in the sidebar (S4)

- **What:** Lists reorder within their folder (and within the ungrouped group); folders reorder among
  themselves. Buttons **and** drag.
- **Where:** `app/Livewire/Navigation/Sidebar.php`;
  `resources/views/livewire/navigation/sidebar.blade.php`;
  `tests/Feature/Livewire/Navigation/SidebarTest.php`
- **How:**
  - Component methods mirroring Step 1/2: `moveListUp/Down(int $listId)`,
    `reorderList(int $listId, int $position, ?int $folderId)`, `moveFolderUp/Down(int $folderId)`,
    `reorderFolder(int $folderId, int $position)` — each authorizes, computes the full id array from
    the current tree, then calls `TaskListService::reorder($user, $folderId, $ids)` or
    `FolderService::reorder($user, $ids)`.
  - Markup: one `wire:sort.renderless` container per folder's list group and one for the ungrouped
    group, each with `wire:sort:group="lists"` **but no cross-group drops** (C6) — i.e. distinct group
    names per container (`lists-{{ $folderId ?? 'root' }}`) so Sortable will not accept a foreign drag.
    A separate `wire:sort` container wraps the folder rows.
  - The Inbox and Starred items sit outside every sortable container (the Inbox is always first).
  - Move up/down items live in the existing per-folder and per-list dropdowns.
- **Test:** reordering lists inside a folder persists positions and does not touch another folder's
  lists; reordering ungrouped lists never moves the Inbox; folder reordering persists; a foreign id
  is refused; the tree re-renders in the new order.
- **Complexity:** Large

### Step 4: Undo for reversible actions (S7)

- **What:** After completing, moving or starring a task, a brief Undo affordance appears and works.
- **Where:** `app/Livewire/Tasks/{TaskPanel,StarredPanel}.php`;
  `resources/views/components/tasks/undo-bar.blade.php` (new);
  `resources/views/livewire/tasks/{task-panel,starred-panel}.blade.php`;
  `tests/Feature/Livewire/{TaskPanelTest,StarredPanelTest}.php`
- **How:**
  - `?array $lastAction` on the component: `['type' => 'complete'|'move'|'star', 'taskId' => int,
    'payload' => [...]]`. Set by `completeTask()`, the move actions and `toggleStar()`; cleared by
    `undo()` and by `dismissUndo()`.
  - `undo(TaskService $service)`: re-resolve and authorize the task, then `restore()` /
    `move()` back to the recorded original list / `setStarred()` back. Idempotent service calls mean
    a double-click cannot corrupt anything.
  - `x-tasks.undo-bar`: a compact strip anchored at the bottom of the panel, `x-transition`, with an
    Alpine timer that calls `$wire.dismissUndo()` after ~8s. Not a toast (C5). Keyboard reachable.
  - Deletes keep `wire:confirm` and set **no** `lastAction` (C4).
- **Test:** completing sets `lastAction` and the bar renders; `undo` restores the task and clears the
  bar; undoing a move returns the task to its original list; undoing a star reverts it; deleting sets
  no undo state; undo on another user's task is refused; `dismissUndo` clears state.
- **Complexity:** Medium

### Step 5: Interaction and visual polish

- **What:** The app matches frontend.md's "Interaction behavior" and "Visual style": calm, fast,
  spacious, with subtle motion and muted completed work.
- **Where:** `resources/views/components/tasks/task-row.blade.php`;
  `resources/views/livewire/**`; `resources/css/app.css`; `resources/views/layouts/app/sidebar.blade.php`
- **How:**
  - Completion: `x-transition` (or a short `transition` + opacity/translate class) as a row leaves the
    active section; the Completed section keeps the existing strikethrough + reduced opacity.
  - Quick add: verify the input keeps focus and clears after each save (M3 — currently the input
    clears; assert focus explicitly with `autofocus`/`x-ref` + `$refs...focus()` after the round trip).
  - `wire:loading.delay` states on every mutating control; `flux:skeleton` where a panel can be empty
    while loading.
  - Visual pass: light neutral page background with white content surfaces, soft borders, rounded
    corners, one accent colour (set the Flux accent once in `resources/css/app.css` rather than
    per-component classes), generous row spacing. Remove the starter-kit "Repository"/"Documentation"
    footer links from the sidebar — they are noise in a product UI.
  - Audit against frontend.md's "Avoid" list: no tables, no dense dashboards, no extra settings.
- **Test:** existing suite stays green (this step is mostly presentational); assert the quick-add
  input remains focused after adding; manual review of the completion animation and of the accent
  colour in light and dark mode.
- **Complexity:** Medium

### Step 6: Responsive and accessibility sweep (M9) + docs

- **What:** The whole workflow verified at three widths and by keyboard, and the documentation
  brought up to date.
- **Where:** all views touched in Phases A–C; `README.md`
- **How:**
  - Widths 375 / 768 / 1280px: sidebar drawer, list page, details flyout, dialogs, undo bar, row
    menus, native date input.
  - Keyboard: tab order through the sidebar tree and task rows; every icon-only control has an
    `aria-label` (several already do — audit the new ones); dropdowns and modals trap and restore
    focus (Flux handles this, verify); move up/down reachable without a pointer; the undo bar is
    reachable before it auto-dismisses.
  - Confirm every destructive action has a confirmation and every domain error surfaces as a toast
    rather than a stack trace.
  - README: document the reordering interactions (drag + keyboard), the undo scope (and the explicit
    non-support of undo-after-delete, with a pointer to C4's options), and refresh the run
    instructions if anything changed.
- **Test:** full `composer test`; a manual pass of the `docs/project-base.md` "Key acceptance
  criteria" walkthrough end to end on desktop **and** at mobile width, keyboard-only for the
  five-tasks-without-leaving-the-keyboard step.
- **Complexity:** Medium

## 4. Risk Assessment

### Risks

- **R1 (High) — A failed reorder leaves the UI lying.** With `.renderless`, the DOM is the drop
  result; if the server rejects the write and nothing forces a re-render, the user sees an order that
  is not persisted and only discovers it on refresh.
- **R2 (High) — Undo semantics drift into data loss.** An undo that "restores" a deleted row by
  re-creating it produces a different task with a new id, breaking any reference and misleading the
  user about what was recovered.
- **R3 (Medium) — Cross-folder list drags are two non-atomic writes.** A half-failure leaves a list in
  a folder at a position that was never written.
- **R4 (Medium) — Sortable and Livewire DOM diffing fight each other.** Missing or unstable `wire:key`
  values after a sort produce duplicated, vanished or mis-ordered rows — the classic `wire:sort` bug.
- **R5 (Medium) — Drag handles versus row interactivity.** Without `wire:sort:handle`, dragging
  hijacks clicks on the checkbox, the inline title input and the row menu, making the row feel broken
  on touch devices in particular.
- **R6 (Medium) — Reorder set mismatch under stale state.** Two tabs, or an undo racing a drag, can
  submit an id set that no longer matches the list; the repository correctly refuses, and the user
  sees an error for something that looked fine.
- **R7 (Low/Medium) — Touch drag versus scroll on mobile.** Long-press/scroll conflicts are the usual
  outcome of naive drag-and-drop on phones.
- **R8 (Low) — The visual pass touches everything.** A global accent/background change can quietly
  break contrast in dark mode or wreck a starter-kit auth screen.

### Mitigations

- **R1:** C3 specifies the failure path first: catch, toast, bust the computed cache and re-render.
  A test asserts that after a rejected reorder the rendered order matches the database.
- **R2:** C4 removes deletion from undo entirely for this phase and records the three options with a
  recommendation (soft deletes) requiring explicit approval and its own plan.
- **R3:** C6 keeps dragging inside a group; cross-folder movement stays the atomic, already-tested
  `ListDialog` path.
- **R4:** `wire:key` on every sortable row and every folder/list node is a stated requirement in
  Steps 2 and 3; the sortable container is `.renderless` so Livewire is not diffing the very DOM
  Sortable just mutated.
- **R5:** A dedicated `wire:sort:handle` grip, verified by a manual touch check in Step 6.
- **R6:** The repository already validates the whole id set inside a transaction and writes nothing on
  mismatch; the component surfaces `TaskReorderMismatchException` as a friendly "That list changed —
  refreshing." message plus a re-render, rather than a raw error.
- **R7:** Handle-only dragging plus the always-available move up/down actions; if touch drag proves
  unreliable, ship pointer drag on desktop and rely on the buttons on touch (an honest, accessible
  fallback rather than a fragile gesture).
- **R8:** Do the visual pass in one step, on a branch, with a before/after look at light and dark
  mode and at the auth screens; the accent is set once in CSS so it can be reverted in one line.

### Fallbacks

- If `wire:sort` misbehaves in this Livewire/Flux combination, Steps 1 and 3's button-based
  reordering already satisfy S4's accessibility requirement, and drag can be dropped without leaving
  a feature half-built (this is precisely why C1 orders them that way).
- If the undo bar proves noisy, reduce its scope to completion only — the single most common
  accidental action — and keep confirmations elsewhere.
- If cross-folder dragging turns out to be a hard requirement, the clean route is a new
  `TaskListService::moveTo(TaskList, ?int $folderId, int $position)` wrapping both writes in one
  repository transaction — a small, well-bounded backend change, planned separately.
- If the polish step balloons, split it: (5a) interaction (animation, focus, loading states),
  (5b) visual language (colour, spacing, surfaces).

## 5. Execution Checklist

- [ ] **Step 1:** `moveTaskUp/Down` + shared `reorderedIds()`; row menu items with `aria-label`s;
      mismatch handling. `composer test`.
- [ ] **Step 2:** `wire:sort.renderless` + `wire:sort:item` + `wire:sort:handle` on active tasks;
      `reorderTask()` reusing `reorderedIds()`; failure path re-renders from the database;
      `wire:key` audit. `composer test` + manual drag check.
- [ ] **Step 3:** List reordering per folder and for ungrouped lists (buttons + drag, no cross-group
      drops); folder reordering; Inbox excluded from sortable containers. `composer test`.
- [ ] **Step 4:** `lastAction` state + `x-tasks.undo-bar` for complete / move / star; deletes keep
      confirmations and set no undo. `composer test`.
- [ ] **Step 5:** Completion animation, quick-add focus assertion, loading/skeleton states, accent and
      surface pass, starter-kit footer links removed. `view:clear`, `composer test`.
- [ ] **Step 6:** Responsive + keyboard sweep at 375/768/1280; README updated (reordering, undo scope
      and its explicit limits); full acceptance-criteria walkthrough on desktop and mobile.
      `composer test`.
- [ ] **Decision required from the user (blocking any future undo-after-delete):** adopt soft deletes
      (migration + repository/service changes, own plan) or keep confirmations permanently — see C4.
```
