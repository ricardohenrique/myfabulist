# Web rebuild — Phase 3: refinement and parity

> **Status: completed 11 August 2026.**

## Objective

Finish the production Inertia browser experience with resilient ordering,
interaction polish, final documentation, and complete release verification.

## Delivered

- Same-container drag-and-drop for active tasks, lists, and folders using
  `@dnd-kit/react`.
- Dedicated handles with mouse, trackpad, touch, keyboard, and accessibility
  support. Keyboard sorting starts with Space or Enter, moves with arrow keys,
  and drops with Space or Enter.
- Optimistic local ordering, mutation locking while saving, and reconciliation
  from canonical Inertia props.
- Transactional complete-ID-set validation for folders, lists, and tasks.
  Incomplete, duplicate, stale, foreign, deleted, and cross-container payloads
  are rejected before positions are changed.
- Fixed Inbox and Starred navigation; completed tasks remain outside active
  sortable collections.
- Intentional removal of dedicated move-up/move-down menu actions. Explicit
  cross-list task and cross-folder list moves remain available through the
  details/dialog workflow.
- Responsive handle styling, dragging/drop-target feedback, visible focus,
  reduced-motion support, and usable touch targets.
- Final project, architecture, setup, scope, and frontend documentation.

## Final browser architecture

- Laravel 13 and PHP 8.4
- Inertia.js 3 and React 19 with TypeScript
- Tailwind CSS 4 and Vite 8
- Laravel Wayfinder typed routes
- `@dnd-kit/react` sortable interaction
- Fortify registration/login and Sanctum API authentication

The browser runtime is exclusively Inertia/React. The API remains available to
remote clients under authenticated `/api/v1` routes, while web controllers call
the shared application layer directly.

## Verification

Release verification covers formatting, PHP static analysis, the complete Pest
suite, TypeScript checks, the production Vite build, and a real browser
walkthrough of registration, authentication, CRUD, task details, Undo, and all
three persisted reorder scopes.

## Deferred scope

NativePHP, account settings, password reset, 2FA, passkeys, search, reminders,
recurrence, subtasks, collaboration, attachments, offline writes, and
cross-container drag operations remain separate future work.
