# Purplelist product scope

## Product purpose

Purplelist is a personal task manager inspired by the speed, warmth, and
simplicity of Wunderlist. It helps people capture tasks quickly, organize them
into meaningful lists, and review completed work without letting it distract
from what remains active.

The product is intentionally lighter than a general project-management tool.
Its primary model is:

**Folder → List → Task**

Folders organize related lists. Lists act as lightweight projects or contexts.
Tasks are the smallest actionable item, and their title is the only required
field. Lists may also remain ungrouped.

## Success criteria

- A user can capture a task immediately after opening a list and can enter
  another without manually restoring focus.
- Active tasks are easy to scan and completed tasks remain available in a
  separate, visually quiet section.
- Folder, list, task, ordering, completion, comments, and task-detail state
  persist across sessions and are isolated by user.
- The primary workflow remains clear and usable on desktop, tablet, mobile web,
  and future native clients.
- Browser, API, and native clients share domain rules and do not create
  conflicting sources of truth.

## Core domain

### Accounts and ownership

- Authentication is required for product data.
- Users may register, sign in with email/password or a verified Google account,
  sign out, and update their name, email, and password from a basic in-app
  profile modal. A Google-only user may set their first password after signing
  in. Password recovery is available by email. New password-based accounts
  receive a branded welcome email with an optional confirmation link; email
  confirmation is recorded but never gates sign-in or product access.
  New accounts receive a lightweight, optional first-Inbox question about
  their primary use case; answering or skipping completes the prompt.
  Two-factor authentication, passkeys, and expanded account settings are
  intentionally excluded from the current release.
- A user may only view or mutate folders and tasks they own, and lists they
  own or hold an accepted membership on. Only a list's owner may invite or
  revoke a member; any accepted member, including a non-owner, may leave a
  shared list voluntarily.
- The Laravel server and its database are canonical for shared account and
  task-management data.

### Inbox

- Every user receives exactly one default Inbox list.
- A newly registered user's Inbox starts with two simple active example tasks
  that demonstrate quick capture and completion.
- Inbox is always ungrouped and shown prominently in navigation.
- Inbox cannot be renamed or deleted.
- Inbox is the default destination for global quick capture.
- Tasks may be moved from Inbox to another user-owned list.

### Folders

- Users can create, rename, reorder, expand/collapse, and delete folders.
- A folder can contain multiple lists; lists may be moved into or out of it.
- Deleting a non-empty folder must never silently orphan or destroy its lists.
  The user must explicitly detach the lists or choose the destructive workflow
  that deletes them.
- Folder ordering is scoped to the owning user.

### Lists

- Users can create, rename, open, reorder, move, and delete lists.
- A list belongs to at most one folder and can remain ungrouped.
- A list displays active tasks first and completed tasks below them.
- Lists are soft-deleted. The default Inbox is exempt from deletion.
- Moving a list between a folder and the ungrouped collection is an explicit
  atomic operation. Dragging onto a highlighted folder/list destination or
  the visible ungrouped drop zone performs that move; dropping between peers
  in the same container remains a reorder.

### Tasks

- Users can quickly create a task inside a selected list.
- A trimmed, non-blank title is required. Notes and due dates are optional.
- Users can rename, complete, restore, star/unstar, move, reorder, edit details,
  and delete tasks.
- A task row exposes completion, title, starred state, due-date state, and an
  affordance for details or additional actions when applicable.
- Completion and deletion are distinct. Tasks are soft-deleted and completed
  tasks remain part of the list until explicitly deleted.
- Active tasks use their persisted manual position order. Dragging rewrites
  that order, and every newly created task is inserted at the first position
  without disturbing the relative order of the existing tasks. Completed tasks
  are displayed by most recent completion.
- Cross-list movement is explicit and atomic. An active task may be dropped on
  a highlighted eligible sidebar list and is appended there. Reordering is
  scoped to one list and rejects stale, incomplete, duplicate, or foreign ID
  sets. Tasks never move into or out of shared lists in the current release.
- Users can add chronological plain-text comments to their tasks. Each comment
  records its author for the future shared-list model; comments cannot be blank
  and use the same `TEXT` storage boundary as notes.

### Completed tasks

- Completed tasks appear below active tasks in a collapsible section.
- The section shows its completed count and remembers the user's open/closed
  preference where supported.
- Completed rows use reduced emphasis and a struck-through title.
- A completed task can be restored through its checkbox.

### Starred view

- Starred is a smart cross-list view of the user's important tasks.
- Importance is binary; multiple priority levels are out of scope.
- Starred does not create or own duplicate task records.

## Product experience

- Use a responsive application shell with a folder/list sidebar and main task
  area. On narrow screens, navigation opens from a menu control.
- Keep the interface calm, friendly, spacious, and minimal. Use neutral-lilac
  surfaces, the light-purple `#8B6FD6` product accent, clear typography,
  subtle motion, and muted completed work.
- Pressing Enter in quick-add creates the task, clears the field, and keeps it
  focused after success. Failed saves retain enough context to recover.
- Reordering should feel immediate. If persistence rejects stale state, the UI
  refreshes from canonical data and explains the failure.
- The full task, list, and folder item surfaces must support pointer, touch, and
  keyboard dragging without separate grip icons. Ordinary clicks on their
  nested controls remain unchanged. Dedicated move-up/move-down menu actions
  are intentionally excluded; keyboard users reorder from the focused item.
- Cross-container drag destinations are typed and visibly highlighted:
  folders accept lists, the ungrouped zone accepts grouped lists, and eligible
  private lists accept active tasks. A drop outside a valid target is canceled.
- Completing, starring, and moving a task may offer a short single-action Undo
  period. Undo is a safety net for the most recent action, not a history stack.
- Destructive actions require explicit confirmation until a complete and
  tested restoration/undo interaction replaces it.
- Empty and error states tell the user what to do next. Preferred examples are
  “Nothing here yet. Add your first task.”, “Your Inbox is clear.”, and
  “Everything is done.”

## Delivery architecture

### Browser

- The target browser application uses Inertia.js 3, React 19, TypeScript,
  Tailwind CSS 4, Vite 8, and Laravel Wayfinder.
- The browser runtime is exclusively Inertia and React. Production pages use
  service-backed Inertia props and web mutations, with scoped drag-and-drop
  refinement completed in Phase 3.
- Browser authentication uses Laravel's stateful `web` guard, session cookies,
  and CSRF protection. Product routes do not require email verification in the
  current release.

### API

- Shared remote capabilities are exposed under the authenticated, versioned
  `/api/v1` namespace.
- Sanctum protects domain endpoints. Email verification is not required in the
  current release.
- API Resources provide stable folder, list, task, and task-comment payloads.
  Domain and validation errors retain consistent machine-readable response
  shapes.
- Browser controllers reuse domain services in-process and never call the API
  over loopback HTTP.

### Native

- NativePHP Mobile 4 and NativePHP Mobile UI are the planned native delivery
  stack.
- Native clients consume the remote versioned API; they do not become a second
  canonical database.
- Native authentication uses narrowly scoped per-device tokens stored in
  platform secure storage. Tokens must not be stored in local storage, SQLite,
  logs, URLs, bundled environment files, or plaintext files.
- Offline mutations and synchronization are not part of the initial scope.
  Any future cached reads must expose freshness and stale state explicitly.

## Current release scope

The current product slice includes:

- authenticated, user-isolated folders, lists, and tasks;
- basic profile management for name, email, and password;
- permanent Inbox creation and navigation;
- Starred smart view;
- quick task creation, rename, completion/restoration, notes, due dates,
  starring, moving, reordering, soft deletion, and attributed comments;
- folder/list creation, rename, move, reorder, and guarded deletion;
- list sharing and collaboration: inviting a registered user by email,
  accepting/declining/revoking, and leaving voluntarily, with a notification
  center for pending invitations and a share dialog on the list itself. Task,
  subtask, and comment content — title, notes, due dates, completion, and
  chronological comments — is identical for every accepted member, since a
  shared list stays exactly one canonical row with one set of child records.
  Only a list's *placement* (which folder it sits in, and its sidebar
  position) and *starring* are per-member: each member files and stars a
  shared list independently of every other member, including its owner.
  There is no live sync — members see one another's changes on their next
  page load or navigation, not in real time;
- responsive browser navigation and task details;
- focused undo for completion, moving, and starring;
- versioned JSON endpoints for the implemented domain workflows; and
- demo data tooling for local development, including seeded shared lists and
  a pending invitation for the first demo account.

## Near-term priorities

- Move the browser experience toward the declared Inertia/React/TypeScript
  baseline without regressing current workflows.
- Keep web and API behaviour aligned through shared services, repositories,
  policies, and resources.
- Complete consistent undo/restoration UX for soft-deleted tasks and lists.
- Add cross-list task-title search with list and folder context.
- Harden responsive, accessible, loading, empty, conflict, and error states.
- Establish the NativePHP shell and secure device authentication before adding
  native domain mutations.

## Deferred capabilities

The following may be considered after the core workflow and target clients are
stable:

- Today, Upcoming, All Tasks, and Completed smart views;
- recurring tasks;
- reminders and browser/native notifications;
- task assignment to a specific collaborator on a shared list;
- file or image attachments;
- natural-language date extraction;
- themes and custom backgrounds; and
- documented non-authoritative offline read snapshots.

Each deferred capability requires explicit acceptance criteria and a review of
its data model, authorization, API, native, privacy, and migration impact before
implementation.

## Explicitly out of scope

- Kanban boards, Gantt charts, time tracking, sprints, workload dashboards, and
  enterprise portfolio management.
- Multiple priority scales; starred remains the single importance signal.
- Unsignaled cross-container drops outside a highlighted valid destination.
- Offline writes or automatic conflict resolution without a designed sync
  model.
- Multiple canonical data stores.
- Client-side-only authorization or trusting resource IDs supplied by a client.
- Bundling server secrets or production credentials in a native application.

## Data and deletion boundaries

- MySQL is the application database for local development and deployment;
  connection credentials remain environment-configurable.
- Folder, list, task, order, completion, detail, comment, and starred state
  persist on the server.
- Task and list deletion is soft deletion. Folder deletion follows the explicit
  detach-or-delete-list workflow and must be transactional.
- Restoration is allowed only when ownership and destination invariants remain
  valid.
- User uploads such as profile photos require server-side validation, ownership
  checks, and safe storage.

## Release and quality expectations

- Every product behaviour requires authorization and focused automated tests.
- Reordering, moving, soft deletion, restoration, default Inbox protection, and
  cross-user access are high-risk paths and require explicit coverage.
- Verify responsive and keyboard interactions for browser changes and relevant
  simulator/device behaviour for native changes.
- Native release bundles must exclude project instructions, development docs,
  source/test tooling, credentials, and server-only environment values.
- Run the repository's supported formatting, static-analysis, test, and build
  commands before release.
