# Web rebuild — Phase 3: refinement and parity

> **Status: completed 11 August 2026.** The production Inertia interface now
> supports persisted same-container drag-and-drop for active tasks, lists, and
> folders. `@dnd-kit/react` was selected for React 19 pointer, touch, keyboard,
> and accessibility support. By explicit product direction, the temporary
> move-up/move-down menus were removed; keyboard sorting uses the drag handles.

## Objective

Refine the integrated Inertia application into a production-ready experience.
This phase adds pointer drag-and-drop, resolves remaining responsive and visual
issues, strengthens accessibility and failure recovery, proves parity, removes
obsolete frontend runtime code when safe, and updates project documentation.

Phase 3 is not a catch-all for new product features. Its purpose is to complete
and polish the product scope already approved in Phases 1 and 2.

## Entry criteria

- The Phase 1 design and responsive structure are approved.
- Login, registration, logout, navigation, Inbox, lists, Starred, task details,
  and service-backed mutations work through Inertia.
- The Phase 2 reorder endpoints and service workflows are functional.
- Existing domain and API tests remain green.
- Known gaps are recorded before refinement begins.

## Approved boundaries

- NativePHP remains a separate roadmap.
- Email verification, password reset, 2FA, passkeys, profile/settings, and
  deferred product capabilities remain excluded.
- The Laravel server remains canonical; refinement must not introduce local
  domain persistence or a second data source.
- The browser continues to use Inertia and shared domain services, not
  `/api/v1` loopback calls.
- Drag-and-drop is same-container reordering only. Cross-folder list movement
  and cross-list task movement remain explicit menu/dialog actions.

## Workstream 1: drag-and-drop

### Decision

`@dnd-kit/react` 0.5 was selected after checking its current React API,
sortable state model, default pointer/touch/keyboard sensors, accessibility
announcements, and scoped provider design. It is the only new runtime frontend
dependency introduced for Phase 3.

Dedicated move-up/move-down controls were removed by explicit product
direction. The drag handles themselves retain keyboard sorting: focus a handle,
start with Space or Enter, change position with the arrow keys, and drop with
Space or Enter.

### Tasks

- Drag active tasks within the currently selected list.
- Drag lists within one folder or within the ungrouped collection.
- Drag folders within the sidebar.
- Provide clear handles, lifted/dragging state, insertion position, and drop
  feedback.
- Support mouse, trackpad, and touch without turning row clicks into accidental
  drags.
- Keep completed tasks outside the sortable active-task container.
- Disable impossible operations while a reorder request is pending.
- Submit the complete expected ID order to the existing reorder workflow.
- On rejection, replace the optimistic order with canonical props and show an
  actionable message.
- Prevent cross-container drops from silently becoming move operations.

### Drag-and-drop acceptance criteria

- All three sortable collections persist valid orders.
- A stale, incomplete, duplicate, foreign, or cross-container set is rejected
  and visibly reconciled.
- Touch scrolling remains usable near sortable regions.
- Keyboard users retain complete reorder capability through the drag handles.
- Row action menus, task selection, checkboxes, and stars do not trigger drags.

## Workstream 2: interaction refinement

### Quick capture

- Verify Enter submission, focus restoration, input clearing after success, and
  draft preservation after failure.
- Prevent double submission and communicate pending state without making the
  composer jump.
- Confirm long task titles wrap or truncate consistently with the approved
  row design.

### Task state changes

- Add restrained transitions when tasks move between active and completed.
- Respect reduced-motion preferences.
- Ensure completed ordering updates immediately after canonical success.
- Confirm Starred-view removal does not feel like task deletion.

### Undo and destructive actions

- Verify the one-action Undo bar for complete, move, and star/unstar.
- Complete deletion Undo for soft-deleted tasks and lists only if restoration
  behaviour and expiry are fully implemented and tested.
- Until then, retain explicit deletion confirmation.
- Ensure Undo re-authorizes the target and remains idempotent under repeated
  clicks or expired state.

### Overlays and focus

- Restore focus to the triggering control after menus, dialogs, drawers, and
  task details close.
- Prevent background scroll and interaction while modal surfaces are open.
- Confirm Escape, outside-click, browser Back, and mobile Back behaviour where
  applicable.

## Workstream 3: visual and responsive polish

Review the implementation against the supplied Wunderlist screenshot,
`old-resources`, the Phase 1 design tokens, and the bookmark-check identity.

### Desktop

- Normalize the sidebar, task column, and details-panel widths.
- Align headers, quick-add, task rows, menus, and detail fields.
- Ensure opening the detail panel does not make task content unusably narrow.
- Test long folder/list/task names and scroll boundaries independently in all
  three columns.

### Tablet and mobile

- Verify navigation drawer sizing, overlay, close affordance, and focus trap.
- Present task details as a usable sheet or full-height layer.
- Account for software keyboards, safe areas, browser chrome, and viewport
  height changes.
- Keep quick-add and primary actions reachable without tiny touch targets.

### Design consistency

- Consolidate repeated colors, spacing, typography, radii, shadows, and motion
  values into CSS-first Tailwind tokens.
- Use the green-to-warm task canvas and blue navigation selection consistently.
- Use coral as an identity/accent color with adequate contrast.
- Remove accidental one-off CSS and component-specific magic numbers when a
  token or documented layout rule exists.
- Verify favicon, login/registration identity, and authenticated shell feel
  like one product.

## Workstream 4: accessibility

- Perform complete keyboard walkthroughs for auth, navigation, quick-add,
  task rows, details, dialogs, completed disclosure, and reorder controls.
- Validate heading order, landmarks, labels, descriptions, menu semantics,
  dialog names, live regions, and error associations.
- Ensure active, completed, starred, overdue, and selected states do not rely
  on color alone.
- Verify contrast and visible focus in every visual state.
- Ensure icons without adjacent text have accessible names or tooltips.
- Confirm dynamic success, failure, and Undo messages are announced without
  stealing focus unnecessarily.
- Test reduced motion and zoomed/text-enlarged layouts.

Accessibility defects that block navigation or task completion are release
blockers, not optional follow-up work.

## Workstream 5: performance and resilience

- Inspect navigation and task queries for N+1 behaviour and redundant reloads.
- Keep global Inertia shared props small.
- Use partial reloads, lazy props, deferred props, or prop merging only when a
  measured workflow benefits and semantics remain clear.
- Avoid rerendering the complete application shell for transient local UI.
- Prevent duplicate mutations and recover cleanly from network interruption.
- Test empty, small, long, and realistically large navigation/task collections.
- Verify asset bundle size and remove unused frontend imports.
- Do not add caching, client persistence, or optimistic queues without an
  explicit consistency design.

## Workstream 6: parity audit

Audit the rebuilt interface against approved current behaviour:

### Authentication

- registration
- login
- logout
- guest/authenticated redirects
- no verified-email requirement

### Navigation and organization

- permanent Inbox and counts
- Starred smart view and count
- folder create, rename, expand/collapse, reorder, and guarded deletion
- list create, rename, open, move, reorder, and soft deletion
- ungrouped lists
- responsive mobile navigation

### Tasks

- quick creation
- rename and detail editing
- complete and restore
- star and unstar
- due dates and due-date status
- notes
- explicit cross-list move
- same-list reorder
- soft deletion and approved recovery behaviour
- active/completed separation and completed count
- task details panel/modal
- Undo for approved actions

### States

- empty Inbox
- empty ordinary list
- everything completed
- validation failure
- authorization/not-found handling
- network/server failure
- stale reorder recovery
- long content and overflow

Record every discrepancy as one of:

- release blocker;
- approved post-release follow-up; or
- intentionally removed behaviour with documentation updated.

Do not declare parity based only on page appearance.

## Workstream 7: legacy cleanup

Cleanup occurs only after the parity audit and full quality gates pass.

1. Search for runtime references to Livewire, Flux, Blade task views, Alpine,
   and old Vite entries.
2. Remove obsolete routes, Livewire components, views, providers, and frontend
   entries in small reviewable changes.
3. Remove Livewire/Flux packages only when no retained feature depends on them.
4. Regenerate dependency locks and frontend assets using supported package
   commands.
5. Keep `old-resources` until the user explicitly approves deletion or archival
   outside the runtime repository. It remains a reference, not runtime code.
6. Do not delete historical documentation merely because implementation moved.
   Add superseding notes when a document would otherwise mislead.

Legacy removal must not remove backend services, API controllers/resources,
domain tests, or authentication actions still used by the Inertia application.

## Workstream 8: testing and release verification

### Automated coverage

- drag-and-drop reorder endpoints and stale-order recovery;
- keyboard drag-handle reordering;
- all parity-audit mutations and authorization boundaries;
- Inertia component names and critical prop shapes;
- auth scope with verification and excluded features absent;
- soft-deletion/restore edge cases;
- cross-user and cross-container rejection;
- flash/Undo lifecycle where server state is involved; and
- retained `/api/v1` behaviour.

Use Pest as the target test style while preserving valid existing coverage.
Do not weaken Larastan/PHPStan or formatting rules to make the migration pass.

### Manual matrix

- desktop Chromium, Firefox, and WebKit/Safari where available;
- representative tablet and mobile viewport sizes;
- keyboard-only navigation;
- touch drag-and-drop on a real or emulated touch environment;
- slow/throttled connection and rejected mutation cases;
- reduced motion and increased zoom/text size; and
- light system settings unless an approved dark theme exists.

### Quality gates

Run the supported project checks, including:

```bash
composer test
npm run build
```

Also run focused formatting and static-analysis commands while iterating. Update
the documented command list if package scripts change during the rebuild.

## Documentation updates

At the end of Phase 3, update at least:

- `README.md` for installation, development, and new frontend architecture;
- `AGENTS.md` to remove temporary migration guidance and reflect the final
  runtime stack;
- `development/scope.md` for completed, deferred, and removed scope;
- `docs/frontend.md` for the final component and interaction system;
- API documentation only if the remote contract actually changed;
- environment examples for any safe new configuration; and
- historical phase documents with completion notes and material deviations.

Document the deliberate removal of email verification and excluded auth
features so future work does not accidentally assume they still exist.

## Non-goals

- NativePHP implementation
- New smart views or search
- Recurrence, reminders, subtasks, collaboration, comments, or attachments
- Cross-container drag-and-drop
- Offline writes, sync, or browser-owned task persistence
- Dark mode or themes unless separately approved
- Replacing the established domain/API architecture

## Deliverables

- Accessible pointer, touch, and keyboard drag-and-drop handles
- Polished responsive Wunderlist-inspired web experience
- Resilient pending, error, stale, empty, and Undo states
- Completed parity audit with resolved blockers
- Measured performance and query improvements where needed
- Safely removed obsolete runtime frontend code and dependencies
- Updated project, scope, frontend, setup, and migration documentation
- Passing automated and manual release checks

## Acceptance criteria

- Tasks, lists, and folders reorder by drag-and-drop within valid containers and
  persist through the existing services.
- Keyboard users can perform equivalent reorder operations through the handles.
- Cross-container drops cannot bypass explicit move workflows.
- Desktop, tablet, and mobile layouts match the approved design and remain
  usable with details/navigation open.
- Core flows are accessible, resilient to rejected writes, and consistent with
  canonical server data.
- No runtime code imports from `old-resources`.
- No obsolete Livewire/Flux runtime dependency remains unless a documented,
  approved feature still requires it.
- Documentation accurately describes the final stack and product behaviour.
- `composer test` and `npm run build` pass.

## Exit gate

Phase 3 is complete when the parity audit has no unresolved release blockers,
the three-phase web scope is documented accurately, the approved quality gates
pass, and the rebuilt Inertia application is ready to replace the old runtime
interface. NativePHP planning begins separately after this web release is
accepted.
