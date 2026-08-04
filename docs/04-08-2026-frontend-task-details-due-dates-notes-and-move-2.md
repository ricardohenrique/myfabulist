**Date:** 04-08-2026
**Plan ID:** 2
**Status:** Draft
**Complexity:** Large

## 0. Phase Boundary (read first)

**Depends on Phase A** (`04-08-2026-frontend-navigation-shell-folders-and-lists-1.md`): the
sidebar tree, `/lists/{list}` and folder/list CRUD must be merged first, because the move-task
UI and the details panel both need a list picker and a place to land.

**In this plan:** the task row gets its full anatomy (M4) — due-date indicator with overdue/today
recognition (S1), a note indicator (S3), a star that is visible in both sections (S2), and a row
menu; a task details flyout (title, due date, star, notes, destination list, delete); move-to-list
(S6); quick due-date actions; and list-header actions on the list page.

**Deferred to Phase C:** manual reordering (S4), undo (S7), completion animation, the visual-style
pass, and the responsive/accessibility sweep.

**Backend scope:** none. Every operation in this phase already exists as a service method
(`TaskService::update/rename/move/setStarred/delete`, `TaskListService::allFor`). No migrations,
no repository changes, no API changes. The **only** model-level addition is a pure read accessor
(D3 below).

## 1. Requirements Analysis

### Functional Requirements

- [ ] A task row shows: checkbox, title, star (both active and completed rows), due-date indicator
      when set, a note indicator when a note exists, and an affordance to open details (M4).
- [ ] Overdue tasks and tasks due today are visually distinguishable from other due dates (S1).
- [ ] Selecting a task opens a details panel containing title, due date, starred state, notes,
      destination list and a delete action (frontend.md "Task details").
- [ ] A due date can be added, changed and removed (S1); a note can be added, edited and removed (S3).
- [ ] A task can be moved to another list from the row menu and from the details panel (S6).
- [ ] The details panel stays simple — no priorities, assignees, subtasks or attachments.
- [ ] Completed tasks keep their information (due date, note indicator) while staying visually muted (M6).
- [ ] The list page header shows the list name and offers rename / move to folder / delete for a
      non-default list (M2), and nothing destructive for the Inbox.
- [ ] The Starred page uses the same row anatomy and can open the same details panel (S2).

### Non-Functional Requirements

- [ ] Blade + Livewire only; services injected directly; never `/api/v1` over HTTP.
- [ ] `TaskService::update()` has **replace semantics** — the details form must always submit all
      four fields (`title`, `note`, `dueDate`, `isStarred`) or it will silently clear data.
- [ ] All task input is validated before it reaches the service (title required/max 255, note
      max length, due date a valid date), and the service's own invariants remain the guarantee.
- [ ] No business logic in Blade. Any derived presentation value (e.g. "is this overdue?") lives
      in PHP with a unit test.
- [ ] Notes are user-supplied free text rendered back — `{{ }}` only, `nl2br` only over escaped
      output if line breaks are wanted (`{!! nl2br(e($task->note)) !!}` is the only sanctioned
      exception, and it must be written exactly that way).
- [ ] No N+1: the details panel loads one task and one list collection, nothing per row.
- [ ] `composer test` green after every step; `declare(strict_types=1);` everywhere.

## 2. Architecture Review

### Existing Codebase Patterns (verified)

- `TaskService::update(Task, TaskDetailsData): Task` where
  `TaskDetailsData(string $title, ?string $note, ?Carbon $dueDate, bool $isStarred)` — replace
  semantics, blank title rejected via `InvalidTaskTitleException`.
- `TaskService::move(Task $task, User $user, int $targetListId, ?int $position): Task` — resolves
  the target list scoped to the user and throws `TaskListNotFoundException` otherwise.
- `Task` casts `due_date` to `immutable_date` and `completed_at` to `immutable_datetime`;
  `Date::use(CarbonImmutable::class)` is active application-wide.
- `TaskPanel` currently renders due dates read-only (`$task->due_date->format('M j')`), has no note
  or move UI, and renders no star on completed rows.
- `StarredPanel` renders its own row markup, duplicating ~40 lines of what `TaskPanel` renders.
- Flux free tier has **no** date-picker/calendar component → `flux:input type="date"` (native).
- `flux:modal` supports `variant="flyout"` (right-hand panel, full height, `min-w-[25rem]` at md+)
  and is controllable from PHP via `Flux::modal('task-details')->show()`.
- Existing precedent for a pure read accessor on a model: `User::profilePhotoUrl` (documented
  deviation, plan `04-08-2026-login-entry-inbox-nav-profile-photo-1.md`, D7).

### Affected Areas

| Area | Change |
|---|---|
| `resources/views/components/tasks/task-row.blade.php` | **new** — shared row anatomy |
| `resources/views/livewire/tasks/task-panel.blade.php` | use the row component; add row menu |
| `resources/views/livewire/tasks/starred-panel.blade.php` | use the row component |
| `app/Livewire/Tasks/TaskPanel.php` | `+ openDetails`, quick due-date, move actions |
| `app/Livewire/Tasks/StarredPanel.php` | `+ openDetails` |
| `app/Livewire/Tasks/TaskDetails.php` | **new** — the flyout |
| `app/Livewire/Lists/ListHeader.php` | **new** — list name + list actions |
| `app/Models/Task.php` | **one** pure read accessor (`dueDateStatus`) |
| `resources/views/{inbox,starred,lists/show}.blade.php` | mount the flyout + header |
| `tests/Feature/Livewire/*`, `tests/Unit/Models/TaskDueDateStatusTest.php` | new/extended |

### Architecture Decision

**D1 — One shared anonymous Blade component for the task row.**
`<x-tasks.task-row :task="$task" :completed="false" />` is used by `TaskPanel` (active and
completed sections) and `StarredPanel`. Wire directives inside an anonymous Blade component still
target the enclosing Livewire component, so no event plumbing is needed. This removes the ~40 lines
already duplicated between the two panels and guarantees that a row looks the same everywhere —
which is exactly the "task row" component frontend.md asks for. Optional props (`showListName`,
`showDragHandle` for Phase C) keep the two call sites honest without a variant explosion.
Rejected: a full Livewire component per row (one network round-trip per row hydration, and 100 rows
means 100 component snapshots) and copy-paste divergence (the status quo).

**D2 — The details panel is one Livewire component in a Flux flyout, mounted once per page.**
`<livewire:tasks.task-details />` sits in the page view (inbox, starred, list page). It listens for
`#[On('task-details-open')] open(int $taskId)`, loads and authorizes the task, fills its form state,
and calls `Flux::modal('task-details')->show()`. On save it dispatches `tasks-changed` (panels bust
their computed cache) and `navigation-changed` (counts move when a task moves lists).
Rejected: rendering a details component per row (see D1) and a full `/tasks/{task}` page (a
route+page for a modal-shaped interaction, and it loses the list context frontend.md wants).
`variant="flyout"` gives a right-hand panel on desktop and a full-height sheet on mobile from the
same markup — no second mobile implementation.

**D3 — Due-date status is a pure read accessor on `Task`, not logic in Blade.**
`Task::dueDateStatus(): ?string` returning `overdue` | `today` | `upcoming` | `null` (null when no
due date; a completed task returns its plain status without the overdue emphasis — decided here so
completed rows do not scream red). Justification: it is pure derivation from one column, has no
queries and no workflow, is unit-testable without Livewire, and follows the existing
`User::profilePhotoUrl` precedent. Blade then only maps a string to Tailwind classes.
Rejected: (a) computing it in the Livewire component — it is needed in three components and would
be duplicated; (b) a `App\Support\DueDate` value object — cleaner on paper, but it is one method
over one column (YAGNI); worth revisiting the day due dates gain times/timezones.
Timezone note: `due_date` is a **date**, compared against `today()` in the app timezone. Recorded
explicitly because "why is my task overdue at 9pm" is the classic follow-up bug.

**D4 — Replace semantics are handled by always hydrating the whole form from the task.**
Every write path builds a complete `TaskDetailsData` from current component state, which was
initialised from the loaded task. Quick actions (e.g. "Due today" from the row menu) therefore
re-read the task first and pass its existing note/star through, rather than sending nulls. This is
stated once, here, because a partial `TaskDetailsData` silently deletes user data and no test will
catch it unless it is written to.

**D5 — Moving a task is `TaskService::move()` with `position: null`.**
Appending to the end of the destination list is the correct default; positioning within the target
list is a Phase C (drag-and-drop) concern. The move UI is a `flux:dropdown` submenu on the row menu
(fast path) and a `flux:select` in the details panel (deliberate path) — both call the same service
method. The picker only ever lists the user's own lists, and the service re-resolves the id scoped
to the owner regardless.

**D6 — List identity and actions move out of `TaskPanel` into `Lists\ListHeader`.**
`TaskPanel` currently renders the list name. Splitting the header out keeps each component to a
single UI concern (`TaskPanel` = tasks, `ListHeader` = the list itself), lets the Inbox render a
non-editable header from the same component, and gives Phase C a clean place for list-level state.
The header reuses `Navigation\ListDialog` by dispatching `list-dialog-open` — no second
implementation of rename/move/delete.

## 3. Step Breakdown

### Step 1: Extract the shared task row (M4)

- **What:** One row component used by both panels, with the full row anatomy: checkbox, title,
  star (in both sections), due-date badge, note indicator, and a row menu. No new behaviour beyond
  what already works — a refactor plus indicators.
- **Where:** `resources/views/components/tasks/task-row.blade.php` (new);
  `resources/views/livewire/tasks/task-panel.blade.php`;
  `resources/views/livewire/tasks/starred-panel.blade.php`;
  `app/Models/Task.php` (accessor); `tests/Unit/Models/TaskDueDateStatusTest.php`;
  extend `tests/Feature/Livewire/{TaskPanelTest,StarredPanelTest}.php`
- **How:**
  - `@props(['task', 'completed' => false, 'showListName' => false])`.
  - Due-date badge: `flux:badge size="sm"` (or a muted text span) whose colour maps from
    `$task->dueDateStatus()` — `overdue` → rose, `today` → amber/accent, `upcoming` → zinc.
    Format short (`M j`), with a `title`/`aria-label` carrying the full date.
  - Note indicator: `flux:icon.document-text` (or `bars-3-bottom-left`) shown when
    `filled($task->note)`, with an accessible label.
  - Star: shown on completed rows too (muted), so information is preserved (M6).
  - Row menu: `flux:dropdown` + `flux:menu` with Edit details / Move to… (Step 4) /
    Delete (`wire:confirm`). Menu items dispatch to the enclosing component's existing methods.
  - `StarredPanel` passes `:show-list-name="true"` so the folder/list breadcrumb it renders today
    survives the refactor.
  - `Task::dueDateStatus(): ?string` per D3, with `@property-read`-free plain method (phpstan-safe)
    and a unit test.
- **Test:** `TaskDueDateStatusTest` — null when no due date; `overdue` for yesterday; `today` for
  today; `upcoming` for tomorrow; a completed overdue task is not reported as overdue. Extend the
  panel tests: a task with a note renders the note indicator and one without does not; a starred
  completed task still shows its star; existing complete/restore/rename/delete/star tests still pass
  unchanged (this is the real proof the refactor was behaviour-preserving).
- **Complexity:** Medium

### Step 2: Task details flyout — read + title/star/notes (S3)

- **What:** Clicking a row (or Edit details) opens a flyout showing the task; title, starred and
  notes can be edited and saved.
- **Where:** `app/Livewire/Tasks/TaskDetails.php`;
  `resources/views/livewire/tasks/task-details.blade.php`;
  `resources/views/{inbox,starred,lists/show}.blade.php`; `app/Livewire/Tasks/{TaskPanel,StarredPanel}.php`
  (`openDetails(int $taskId)` → `$this->dispatch('task-details-open', taskId: $taskId)`);
  `tests/Feature/Livewire/TaskDetailsTest.php`
- **How:**
  - State: `?int $taskId`, `string $title`, `?string $note`, `?string $dueDate` (Y-m-d string, used
    in Step 3), `bool $isStarred`, `?int $taskListId` (used in Step 4).
  - `#[On('task-details-open')] open(int $taskId, TaskRepositoryInterface $tasks)`:
    `findForUser()` → `abort_if(null, 404)` → `Gate::authorize('view', $task)` → hydrate state →
    `Flux::modal('task-details')->show()`.
  - `save(TaskService $service)`: `$this->validate([...])` → `Gate::authorize('update', $task)` →
    `$service->update($task, new TaskDetailsData(title, note, dueDate, isStarred))` (all four
    fields, D4) → `Flux::modal('task-details')->close()` → `$this->dispatch('tasks-changed')`.
    Catch `DomainException` → `Flux::toast(variant: 'danger')`.
  - `delete(TaskService $service)`: authorize `delete` → `$service->delete($task)` → close →
    dispatch. Confirmation via `wire:confirm` (undo lands in Phase C).
  - Rules: `title` → `['required', 'string', 'max:255']`; `note` → `['nullable', 'string', 'max:5000']`
    (state the cap; the column is `text`); `dueDate` → `['nullable', 'date']`.
  - `TaskPanel` / `StarredPanel` gain `#[On('tasks-changed')] refresh()` → `unset($this->tasks)`.
  - Notes render with line breaks preserved **only** via `{!! nl2br(e($task->note)) !!}` — written
    exactly like that, or plain `{{ }}` in a `whitespace-pre-line` container (preferred, and the
    default recommendation).
- **Test:** `TaskDetailsTest` — opening hydrates the form from the task; saving persists title,
  note and star; clearing the note persists null; a blank title fails validation and persists
  nothing; a 5001-char note fails validation; another user's task id is refused; delete removes the
  task and closes the panel; `tasks-changed` causes `TaskPanel` to re-render (assert via a
  `Livewire::test` on the panel after the event).
- **Complexity:** Large

### Step 3: Due dates end to end (S1)

- **What:** Due dates can be set, changed and cleared from the details panel, plus quick actions
  from the row menu; rows show overdue/today emphasis.
- **Where:** `app/Livewire/Tasks/TaskDetails.php` (wire the `dueDate` field);
  `app/Livewire/Tasks/TaskPanel.php` (`setDueDate(int $taskId, ?string $date)`);
  `resources/views/components/tasks/task-row.blade.php` (menu items);
  `tests/Feature/Livewire/{TaskDetailsTest,TaskPanelTest}.php`
- **How:**
  - Details field: `<flux:input type="date" wire:model="dueDate" />` plus a "Clear" button setting
    it to `null` (no Flux date-picker exists in the free tier; the native control is fine, keyboard
    accessible and mobile-friendly).
  - Conversion at the boundary: `$this->dueDate === null || $this->dueDate === '' ? null :
    CarbonImmutable::parse($this->dueDate)` when building `TaskDetailsData`. Validate `['nullable','date']`
    first so `parse()` cannot throw.
  - Row quick actions (menu): Today / Tomorrow / Next week / Clear due date →
    `TaskPanel::setDueDate()`, which reloads the task and passes the **existing** note/star through
    a full `TaskDetailsData` (D4).
  - Row badge styling from `dueDateStatus()` (already built in Step 1).
- **Test:** setting a due date from the details panel persists it and the row renders the badge;
  clearing it persists null and removes the badge; a quick action sets the expected date **and does
  not wipe the note or the star** (this assertion is the whole point of D4); an invalid date string
  fails validation; an overdue task renders the overdue treatment (assert on a stable marker such as
  `data-due-status="overdue"`, not on Tailwind classes).
- **Complexity:** Medium

### Step 4: Move a task to another list (S6)

- **What:** A task can be moved from the row menu (fast) and from the details panel (deliberate).
- **Where:** `app/Livewire/Tasks/{TaskPanel,StarredPanel,TaskDetails}.php`;
  `resources/views/components/tasks/task-row.blade.php`;
  `resources/views/livewire/tasks/task-details.blade.php`; tests
- **How:**
  - The list picker comes from `TaskListService::allFor($user)` — exposed as a `#[Computed]` on the
    component so it is fetched once per render, never per row. In the row menu it is a
    `flux:menu.submenu` ("Move to…") listing lists (current list disabled); in the details panel it
    is a `flux:select`.
  - Action: `Gate::authorize('update', $task)` → `TaskService::move($task, $user, $targetListId, null)`
    (D5) → `$this->dispatch('tasks-changed')` **and** `$this->dispatch('navigation-changed')` (the
    sidebar counts changed) → success toast naming the destination.
  - Catch `TaskListNotFoundException` → danger toast.
  - In the details panel, a changed `taskListId` is applied on save, after `update()`, so a single
    save can rename and move.
- **Test:** moving from the Inbox to another list persists `task_list_id` and keeps `user_id`;
  the task disappears from the source panel and appears in the destination; moving to another user's
  list id is refused and mutates nothing; the sidebar's counts update (assert `navigation-changed`
  was dispatched); the current list is not offered as a destination.
- **Complexity:** Medium

### Step 5: List header with list actions (M2)

- **What:** The list page shows a proper header — list name plus rename / move to folder / delete —
  and the Inbox shows a header with no destructive actions.
- **Where:** `app/Livewire/Lists/ListHeader.php`; `resources/views/livewire/lists/list-header.blade.php`;
  `resources/views/{lists/show,inbox}.blade.php`; `resources/views/livewire/tasks/task-panel.blade.php`
  (remove the heading); `tests/Feature/Livewire/ListHeaderTest.php`
- **How:**
  - `ListHeader` takes `#[Locked] public int $taskListId`, resolves the list via
    `TaskListRepositoryInterface::findForUser()` in a `#[Computed]`, authorizes `view` on mount —
    the same shape as `TaskPanel`.
  - Renders the name, the active/completed counts as quiet metadata, and (when `! is_default`) a
    `flux:dropdown` whose items **dispatch `list-dialog-open`** so Phase A's `ListDialog` does the
    work (no duplicated rename/move/delete logic, D6).
  - `#[On('navigation-changed')]` busts the computed cache so a rename reflects immediately.
  - `TaskPanel`'s `flux:heading` is removed; its tests that assert on the list name move to
    `ListHeaderTest`.
- **Test:** the header renders the list name and, for a normal list, the actions menu; the Inbox
  renders the header with no rename/delete affordance; renaming through the dialog updates the
  header without a reload; another user's list id is refused.
- **Complexity:** Medium

### Step 6: Starred parity + empty states (S2, M10)

- **What:** The Starred page gains the same row anatomy, details panel, due-date badges and move
  action; both panels' empty/all-done states are finalised.
- **Where:** `app/Livewire/Tasks/StarredPanel.php`;
  `resources/views/livewire/tasks/starred-panel.blade.php`;
  `resources/views/livewire/tasks/task-panel.blade.php`; `tests/Feature/Livewire/StarredPanelTest.php`
- **How:**
  - Starred rows use `<x-tasks.task-row :show-list-name="true" />` and open the same details flyout;
    `starred.blade.php` mounts `<livewire:tasks.task-details />`.
  - Unstarring from the details panel removes the row from the Starred page on the next render
    (`starredFor()` only returns starred tasks) — assert it.
  - Empty-state copy audited against frontend.md exactly: "Nothing here yet. Add your first task."
    (list), "Your Inbox is clear." (Inbox — currently the generic copy is used for both; fix it by
    passing the list's `is_default` into the empty state), "Everything is done." (all complete),
    "No starred tasks yet." (Starred).
- **Test:** Starred rows show the parent list/folder, the due-date badge and the note indicator;
  opening details from Starred works; unstarring removes the row; the Inbox shows "Your Inbox is
  clear." while another empty list shows "Nothing here yet. Add your first task."; a fully completed
  list shows the all-done message.
- **Complexity:** Small

## 4. Risk Assessment

### Risks

- **R1 (High) — Replace semantics silently destroy data.** Any code path that builds a partial
  `TaskDetailsData` (e.g. a quick "due today" action that passes `note: null`) wipes the note and
  the star. It looks like it works; the loss is only noticed later.
- **R2 (Medium) — Notes are the first multi-line free-text field in the product.** Escaping,
  line-break rendering and a length cap all have to be right the first time; `{!! !!}` anywhere
  near a note is an XSS hole.
- **R3 (Medium) — Date/timezone handling.** `due_date` is a date cast to `CarbonImmutable`; comparing
  it to `now()` instead of `today()` makes "due today" flicker to "overdue" during the day, and the
  app timezone versus the user's local timezone can differ.
- **R4 (Medium) — The row refactor touches every existing task test.** `TaskPanel` and `StarredPanel`
  behaviour must be provably unchanged, or a "refactor" quietly becomes a regression.
- **R5 (Medium) — Event storms.** `tasks-changed` + `navigation-changed` fired from three components
  can trigger more re-renders than needed on every keystroke-ish action, making the UI feel slower
  rather than faster — the opposite of the goal.
- **R6 (Low/Medium) — Flyout on mobile.** `variant="flyout"` is `min-h-dvh` and `min-w-[25rem]` at
  md+; on a 360px phone the panel and the native date input need an actual look, not an assumption.
- **R7 (Low) — Row menu inside a row with an inline-edit input.** The existing title `<input>` and a
  dropdown in the same row can fight over click/focus, especially once drag handles arrive in Phase C.

### Mitigations

- **R1:** D4 makes "always hydrate the full DTO" a rule; Step 3's test asserts explicitly that a
  quick due-date action preserves the note and the star; code review rejects any `new TaskDetailsData`
  built from anything other than complete current state.
- **R2:** Default to plain `{{ $task->note }}` inside a `whitespace-pre-line` container; if `nl2br`
  is wanted it must be `{!! nl2br(e($task->note)) !!}` exactly. A test asserts that a note containing
  `<script>` renders escaped.
- **R3:** Compare with `today()` (start of day) in `dueDateStatus()`, unit-test the boundaries
  (yesterday/today/tomorrow), and record in the code comment that due dates are date-only in the app
  timezone. Per-user timezones are out of scope and noted as a follow-up.
- **R4:** Step 1 changes markup only; the existing `TaskPanelTest`/`StarredPanelTest` must pass
  **unmodified** except for genuinely moved assertions (the list name moving to `ListHeaderTest` in
  Step 5). Any other test edit in Step 1 is a signal the refactor changed behaviour.
- **R5:** One event per user-visible change, dispatched by the component that performed the mutation,
  never re-broadcast by listeners. Listeners only `unset()` a computed property — they never dispatch.
- **R6:** Manual check of the flyout and the native date input at 360/768/1280px at the end of Step 3.
  Fallback: `variant="floating"` or a plain centred modal on small screens.
- **R7:** The row menu is a trailing `flux:dropdown` with its own hit area; the inline title input
  keeps `wire:change` (not `wire:keydown`) so a click elsewhere commits cleanly. Revisit when drag
  handles land (Phase C uses `wire:sort:handle` precisely so the rest of the row stays interactive).

### Fallbacks

- If the details flyout proves fiddly, fall back to a standard centred `flux:modal` — same component,
  one attribute different.
- If `dueDateStatus()` on the model is rejected in review, move it to
  `App\Services\Data\DueDateStatus::for(Task): ?string` and inject nothing — the Blade call site
  changes, nothing else does.
- If the shared row component fights the two panels' differing needs, keep it for `TaskPanel` only
  and leave `StarredPanel` on its own markup for now (recording the duplication as debt) rather than
  growing a props matrix.
- If Step 2 grows beyond one session, split it: (2a) read-only details flyout, (2b) editing and delete.

## 5. Execution Checklist

- [ ] **Step 1:** `x-tasks.task-row` + `Task::dueDateStatus()` + unit test; both panels refactored
      onto it; note/star/due indicators; existing panel tests pass unmodified. `view:clear`,
      `composer test`.
- [ ] **Step 2:** `Tasks\TaskDetails` flyout (title, note, star, delete) mounted on inbox, starred and
      list pages; `task-details-open` / `tasks-changed` event contract; `TaskDetailsTest`. `composer test`.
- [ ] **Step 3:** Due date field + clear + quick actions; overdue/today treatment;
      `data-due-status` markers for tests; note/star preservation asserted. `composer test`.
- [ ] **Step 4:** Move-to-list from the row menu and the details panel; `navigation-changed` dispatch;
      cross-user refusal tested. `composer test`.
- [ ] **Step 5:** `Lists\ListHeader` with rename/move/delete via `list-dialog-open`; heading removed
      from `TaskPanel`; Inbox header has no destructive actions. `composer test`.
- [ ] **Step 6:** Starred parity; empty-state copy matched to frontend.md exactly (incl. the distinct
      Inbox copy); `StarredPanelTest` extended. `composer test`.
- [ ] **Follow-ups (not in scope):** per-user timezones for due dates; note formatting/markdown;
      exposing `active_tasks_count` and a note flag in `TaskResource` if the mobile client wants them.
```
