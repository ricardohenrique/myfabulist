    # Implementation Plan: Full-Item Task Drag-and-Drop

**Date:** 09-08-2026
**Plan ID:** 1
**Status:** Draft
**Complexity:** Small

## 1. Requirements Analysis

### Functional Requirements
- [ ] The entire task row is a valid drag surface — no dedicated handle icon, no other visual change.
- [ ] A task can be dragged from any position in the active list to any other position in the same list.
- [ ] Clicking the row (anywhere not covered by a control below) still opens the task details flyout
      (`openDetails`) — unchanged.
- [ ] Clicking the checkbox still completes/restores the task (`completeTask`/`restoreTask`) — unchanged,
      and must never also start a drag.
- [ ] Clicking the star still toggles star, clicking the "…" menu still opens the row menu, and editing
      the inline title `<input>` still works — none of these may start a drag either.
- [ ] The keyboard move-up/move-down menu items keep working as the accessible alternative — untouched.
- [ ] Completed tasks stay outside the sortable container (unchanged: they are not reorderable).

### Non-Functional Requirements
- [ ] No new npm dependency, no separate JS build — this stays inside Livewire's bundled `wire:sort`
      (Alpine directive backed by SortableJS, already used for tasks/lists/folders elsewhere).
- [ ] No new frontend elements (no handle icon, no layout change) — pure behavioural wiring.
- [ ] A plain click must not be misread as a drag because of a stray pixel of mouse jitter.
- [ ] `composer test` green after every step.

## 2. Architecture Review

### Existing Codebase Patterns (verified)
- `resources/views/livewire/tasks/task-panel.blade.php` already wraps the active tasks in
  `<div wire:sort.renderless="reorderTask($item, $position)" class="wunder-task-list">`, and each
  `<x-tasks.task-row>` already carries `wire:sort:item="{{ $task->id }}"`. The container/item wiring
  is correct and untouched by this plan.
- `App\Livewire\Tasks\TaskPanel::reorderTask()` → `applyReorder()` → `TaskService::reorder()` →
  repository `applyOrder()` already exists, is transactional, validates the submitted id set (throws
  `TaskReorderMismatchException` on a foreign/stale id before writing), and resets the computed
  `tasks` cache plus toasts on failure. **This backend path is already implemented and covered by
  tests** (`tests/Feature/Livewire/TaskPanelTest.php`: `test_dragging_a_task_to_a_new_position_...`,
  `test_dragging_in_an_id_from_another_list_is_refused_...`, `test_after_a_failed_reorder_...`). No
  backend change is required — this plan is a frontend wiring fix, not new integration work.
- The regression: `resources/views/components/tasks/task-row.blade.php` currently puts
  `wire:sort:handle` on the checkbox `<button>` (both the active and completed branches). This is a
  leftover from the last "frontend updates" commit, which deleted the dedicated drag-handle `<span>`
  (bars-2 icon) but left the `wire:sort:handle` attribute stranded on the checkbox instead of removing
  it. Verified in `vendor/livewire/livewire/dist/livewire.js` (`initSortable`): when *any* element in
  the container carries `wire:sort:handle`, Sortable's `handle` option is set to that selector and
  **only** matching elements can start a drag anywhere in the whole container. Today that means the
  checkbox is the only thing that can start a drag — the opposite of "whole row draggable" — and it
  conflicts with the checkbox's own click-to-complete behaviour.
- Also verified in the same file: when **no** element in the container has `wire:sort:handle`, Sortable's
  `handle` option is `null`, i.e. the *entire* item element becomes the drag surface — exactly what's
  wanted here, with no extra element required.
- Livewire's sort plugin also ships a `filter` built in: any pointerdown on an element carrying
  `wire:sort:ignore` (or a descendant of one) is excluded from starting a drag, and the plugin sets
  `preventOnFilter: false` — so the ignored element's own click/keydown/typing behaviour fires exactly
  as normal. This is the mechanism the task row's checkbox, star button, "…" menu and inline title
  input need, replacing the handle.
- `wire:sort:config` (an Alpine-evaluated JS object) is read by `getConfigurationOverrides()` and
  merged on top of Sortable's default options — this is how a `distance` threshold can be added
  without any custom JS.

### Affected Areas
- `resources/views/components/tasks/task-row.blade.php` (drag-surface + ignore wiring).
- `resources/views/livewire/tasks/task-panel.blade.php` (drag-distance threshold on the container).
- `resources/css/wunderlist.css` (dead rule cleanup — see Step 3).
- `tests/Feature/Livewire/TaskPanelTest.php` (one existing test asserts the very `wire:sort:handle`
  behaviour this plan removes; it needs to assert the new contract instead).

### Reusable Components
- Livewire's built-in `wire:sort:ignore` / `wire:sort:config` — no new abstraction needed.

### Architecture Decision
**D1 — Remove the handle, don't add one.** Deleting `wire:sort:handle` from the checkbox (rather than
moving it to some other single element) is what makes the *whole row* draggable per Sortable's own
handle-less default. Anything that must resist starting a drag is marked `wire:sort:ignore` instead —
an allow-list of exceptions on top of a fully-draggable row, which matches "drag from any position" and
requires no new DOM.

**D2 — Add a small `distance` threshold via `wire:sort:config`, not custom JS.** With the whole row
draggable and zero threshold, a click with a stray pixel of mouse movement (common with a trackpad)
could be read by Sortable as the start of a drag. `wire:sort:config="{ distance: 6 }"` on the existing
container makes Sortable require a deliberate few pixels of movement before a drag begins, while a
real drag (moving toward another position) is unaffected. This is a one-line addition to an attribute
that already exists as an escape hatch in the bundled plugin — no new dependency.

## 3. Step Breakdown

### Step 1: Make the task row a full-item drag surface
- **What:** Remove the stray `wire:sort:handle` from the checkbox in both the active and completed
  branches of the task row; mark every remaining interactive control inside the row `wire:sort:ignore`
  so it keeps its own click/keydown behaviour without starting a drag; add a small drag-distance
  threshold to the sortable container.
- **Where:**
  - `resources/views/components/tasks/task-row.blade.php`:
    - Delete `@if ($reorderable) wire:sort:handle @endif` from both `<button class="wunder-task-checkbox...">` elements.
    - Add `wire:sort:ignore` next to the existing `x-on:click.stop` on: both checkbox `<button>`s, the
      inline title `<input>` (rename field), the `<span>` wrapping the star `<flux:button>`, and the
      `<flux:dropdown>` wrapping the "…" row menu.
  - `resources/views/livewire/tasks/task-panel.blade.php`: add `wire:sort:config="{ distance: 6 }"` to
    the `<div wire:sort.renderless="reorderTask($item, $position)" ...>` container.
- **How:** Rely entirely on Livewire's existing `wire:sort` plugin semantics (verified above) — no new
  JS, no new element, no CSS/layout change. The row's own `wire:click="openDetails(...)"` and
  `wire:keydown.enter/.space` are untouched and keep firing on a genuine click exactly as before,
  since Sortable never calls `preventDefault` unless an actual drag (past the distance threshold)
  started outside an ignored element.
- **Test:** `php artisan test --filter=TaskPanelTest` (existing reorder/backend tests must still pass
  unchanged); manual browser check deferred to Step 4.
- **Complexity:** Small.

### Step 2: Update the test that pins the old handle contract
- **What:** `test_the_active_section_is_a_renderless_sort_container_with_a_handle_and_item_key_on_every_row`
  currently asserts `wire:sort:handle` is present — that's the behaviour being removed. Replace it
  with assertions matching the new contract, and add coverage for the ignore wiring.
- **Where:** `tests/Feature/Livewire/TaskPanelTest.php`.
- **How:**
  - Rename/rewrite the existing test to assert the container/item wiring (`wire:sort.renderless=...`,
    `wire:sort:item="{id}"`) stays intact, and add `assertDontSeeHtml('wire:sort:handle')` to lock in
    "whole row draggable, no handle".
  - Add a new test asserting `wire:sort:ignore` is present on the row (e.g. count via
    `assertSeeHtmlInOrder` or a simple substring count against the rendered HTML) so a future edit
    that accidentally deletes the ignore markers is caught.
- **Test:** `php artisan test --filter=TaskPanelTest`.
- **Complexity:** Small.

### Step 3: Remove the dead drag-handle CSS
- **What:** Delete the now-fully-unused `.wunder-task-drag` rule (styled the bars-2 handle `<span>`
  that was already removed from the markup in the last commit; nothing references this class anymore).
- **Where:** `resources/css/wunderlist.css`.
- **How:** Confirm zero references first (`grep -rn "wunder-task-drag" resources/`), then delete the
  rule. Purely a cleanup — no visual change, since the class is already orphaned.
- **Test:** `grep -rn "wunder-task-drag" resources/` returns nothing; `npm run build` (or `vite build`)
  succeeds.
- **Complexity:** Small.

### Step 4: Manual browser verification
- **What:** Confirm the real interaction end to end — this is UI behaviour PHPUnit can't see.
- **Where:** Running app, Inbox or any list with 3+ tasks.
- **How:** Use the `run` skill to launch the app and drive it in a browser. Check, in order:
  1. Drag a task from the middle of the list to the top, and to the bottom — order persists after a
     manual page reload.
  2. Drag starting from the title text area, from empty row space, and from the due-date/note badges —
     all start a drag (whole row, not just one spot).
  3. A plain click (no drag) on the row background opens the task details flyout.
  4. A plain click on the checkbox completes/restores the task and does **not** open details or start
     a drag.
  5. A plain click on the star toggles it; a plain click on "…" opens the row menu; both without
     starting a drag.
  6. Click into the title `<input>`, edit it, blur — rename still saves, no drag interference.
  7. Repeat 3–6 with a quick, slightly jittery click (small mouse movement) to confirm the `distance: 6`
     threshold absorbs it.
  8. Keyboard: Enter/Space on a focused row still opens details; the row menu's Move up/Move down items
     still work.
- **Test:** All eight checks pass visually; no console errors during drag.
- **Complexity:** Small.

## 4. Risk Assessment

### Risks
- **Filter reliability across input types.** `wire:sort:ignore`'s `preventOnFilter: false` behaviour is
  a bundled Livewire/Sortable default, not new code, but it hasn't been exercised against a checkbox
  `<button>` + inline `<input>` combination in this exact row markup before.
- **Distance threshold tuning.** `distance: 6` (px) is a starting value; too low reintroduces
  accidental drags on a click, too high makes a deliberate short drag feel unresponsive.
- **Touch devices.** Sortable's touch handling generally mirrors mouse behaviour, but hasn't been
  manually checked on a touch/tablet viewport as part of this plan.

### Mitigations
- Step 4 manually exercises exactly the checkbox/star/menu/input paths before calling this done, on
  both a clean click and a jittery click.
- The `distance` value is a single number in `wire:sort:config` — trivial to tune up or down after
  Step 4's manual pass without touching any other code.

### Fallbacks
- If `wire:sort:ignore` does not fully suppress drag-start on some control in practice (i.e. a control
  still occasionally triggers a drag), fall back to wrapping just that one control's pointerdown with
  `x-on:pointerdown.stop` in addition to the existing `x-on:click.stop`, rather than reintroducing a
  dedicated handle element.
- If the distance threshold can't be tuned to a value that feels right for both "reliable click" and
  "responsive drag", that's a signal to revisit with the user rather than guessing further.

## 5. Execution Checklist

- [ ] Step 1: Remove the stray checkbox handle, add `wire:sort:ignore` to interactive controls, add the
      drag-distance threshold.
- [ ] Step 2: Update `TaskPanelTest` to assert the new no-handle / ignore-marked contract.
- [ ] Step 3: Delete the dead `.wunder-task-drag` CSS rule.
- [ ] Step 4: Manual browser verification of drag-from-anywhere, click-to-open, checkbox-to-complete,
      star, menu, rename, and keyboard paths.
