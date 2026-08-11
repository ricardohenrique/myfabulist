# Web rebuild — Phase 1: static interfaces

## Status

Approved and completed on 11 August 2026. The local-only `/prototype` review
routes remain available as isolated fixture-backed visual states; production
routes now use the Phase 2 service-backed implementation.

## Implementation status

**Ready for visual approval — 11 August 2026.**

Implemented in this checkpoint:

- reconciled PHP 8.4, Inertia 3, React 19, TypeScript, Tailwind 4, Vite 8,
  Wayfinder, and Pest 5 foundations;
- created the Inertia root view, middleware, TypeScript/Vite configuration, and
  generated typed routes;
- built original static login and registration pages guided by the
  bookmark-check identity;
- built the fixture-backed responsive application shell, sidebar, smart views,
  folders/lists, task composer, active/completed states, task details, dialogs,
  Undo presentation, empty states, and mobile navigation;
- added local/testing-only prototype routes for `inbox`, `list`, `starred`,
  `empty`, and `complete` review states; and
- added focused Pest coverage for all prototype states plus login and
  registration rendering.

Review URLs during local development:

```text
/login
/register
/prototype/inbox
/prototype/list
/prototype/starred
/prototype/empty
/prototype/complete
```

Verification completed:

- strict TypeScript check passes;
- production Vite build passes;
- Composer manifest validation passes;
- Pint passes for changed PHP files;
- seven focused Phase 1 Pest tests pass with 66 assertions;
- desktop and 390px mobile browser reviews pass with no console warnings or
  errors; and
- no handwritten Phase 1 runtime code fetches `/api/v1`, uses a request client,
  or persists domain state in browser storage.

The pre-existing full suite is not yet a Phase 1 gate because legacy Livewire,
settings, and excluded Fortify tests still resolve Blade files that were moved
to `old-resources`. Those tests were preserved, not weakened or deleted. They
must be replaced by Inertia integration coverage as each workflow is migrated
in Phase 2. The same move currently causes ten Larastan `view-string` errors in
legacy controllers/components because their runtime views no longer exist under
`resources/views`.

## Objective

Build the complete My Fabulist web interface with the target frontend stack,
using typed mock data only. This phase establishes the visual system, page
structure, reusable components, and responsive behaviour without reading or
mutating application data through Laravel services or `/api/v1`.

Phase 1 is intentionally static. Buttons, menus, dialogs, form controls, and
navigation states should be present and demonstrable, but product actions do
not need to persist or communicate with the backend.

## Approved decisions

- The web stack is Inertia.js 3, React 19, TypeScript, Tailwind CSS 4, Vite 8,
  and Laravel Wayfinder.
- The current Laravel domain, service, repository, policy, and API layers remain
  the backend foundation.
- `old-resources` is a read-only behavioural and visual reference during the
  rebuild. Do not restore new product code into that directory.
- The authenticated application should closely reproduce the successful
  Wunderlist-style layout from `old-resources` and the supplied original
  Wunderlist screenshot.
- Registration and login are included. Logout appears in the authenticated
  shell. Email verification, password reset, 2FA, passkeys, profile, security,
  and appearance settings are not part of this three-phase web rebuild.
- NativePHP is a separate roadmap and must not expand this phase.
- The generated bookmark-check logo and favicon guide the auth-page identity.
- Mock data is local, deterministic, and typed. No browser request should call
  `/api/v1` in this phase.

## Architecture boundary

Phase 1 may install and configure the declared frontend dependencies and create
the minimum Inertia root needed to render pages. It must not replace working
domain services, change database semantics, or duplicate backend rules in a
client-side data layer.

The static pages should use the same component tree and prop shapes expected in
Phase 2. The goal is to replace fixtures with Inertia props later, not rebuild
the interface a second time.

Suggested frontend structure:

```text
resources/
├── css/
│   └── app.css
├── js/
│   ├── app.tsx
│   ├── components/
│   │   ├── auth/
│   │   ├── navigation/
│   │   ├── tasks/
│   │   └── ui/
│   ├── fixtures/
│   ├── layouts/
│   ├── pages/
│   │   ├── auth/
│   │   ├── inbox/
│   │   ├── lists/
│   │   └── starred/
│   └── types/
└── views/
    └── app.blade.php
```

This is a planning shape, not permission to introduce extra frameworks or
top-level directories. Prefer the smallest structure that keeps page,
component, fixture, and type responsibilities clear.

## Stack foundation

Before interface work begins:

1. Reconcile `composer.json`, `package.json`, and the local PHP runtime with the
   baseline stack.
2. Install the Laravel and React Inertia adapters, React and React DOM,
   TypeScript and required type packages, and Laravel Wayfinder.
3. Configure Vite to use the new TypeScript/React entry point and Tailwind CSS
   entry point under `resources`.
4. Add the Inertia root Blade view and application bootstrap.
5. Configure CSS-first Tailwind design tokens in `resources/css/app.css`.
6. Introduce the Pest baseline without weakening or deleting the existing
   backend test coverage. PHPUnit-style tests may continue to execute while
   conversion is handled deliberately.
7. Keep `old-resources` out of Vite inputs and runtime view resolution.

Do not remove Livewire, Flux, or their old PHP components solely because the
new shell can render. Dependency removal belongs after functional parity is
proven.

## Visual direction

### Authenticated application

Reproduce the defining Wunderlist layout:

- a persistent left navigation column for account context, smart views,
  folders, and lists;
- a flexible center task column with list header, quick-add composer, active
  tasks, and completed tasks; and
- a right task-details panel on wide screens when a task is selected.

The visual reference is structural and experiential. Do not copy Wunderlist
wordmarks, proprietary illustrations, or unrelated branded assets.

Retain the strongest decisions from `old-resources`:

- approximately 272px desktop sidebar and 350px detail panel;
- green-to-warm task-area background inspired by the supplied screenshot;
- white task rows with restrained borders and shadows;
- blue selection treatment in the navigation;
- immediate, compact task controls;
- active work above a quieter completed section; and
- a mobile drawer for navigation and a full-height task details presentation.

Use the new coral brand color for the logo, important destructive emphasis, or
small identity accents without overwhelming the familiar green task canvas.
Exact token values should be centralized and visually verified rather than
scattered through components.

### Login and registration

The old auth pages are not a visual reference. Create a fresh, focused identity
using the bookmark-check logo:

- a warm neutral background with a restrained coral focal area;
- a clearly visible product mark and My Fabulist name;
- one compact, high-contrast form surface;
- direct switching between login and registration; and
- responsive layout that remains comfortable on small phones and large
  desktops.

Login fields: email, password, remember me, submit action, and registration
link.

Registration fields: name, email, password, password confirmation, submit
action, and login link.

Include representative static validation, disabled, submitting, and general
error presentations. Do not include email-verification, password-reset, 2FA,
or passkey controls.

## Static page inventory

### Public pages

- Login
- Registration

### Authenticated pages and states

- Inbox
- Ordinary list inside a folder
- Ungrouped list
- Starred smart view
- Empty Inbox
- Empty ordinary list
- All-active-work-complete state
- Selected task with details panel open
- Completed section expanded and collapsed
- Desktop, tablet, and mobile navigation states

Separate URLs may render the same fixture-backed shell. Persistence and
authorization are deferred to Phase 2.

## Component inventory

### Application shell

- `AppShell`
- desktop sidebar
- mobile navigation trigger and drawer
- task workspace
- responsive task-details region
- account/profile trigger with logout affordance

### Navigation

- Inbox navigation item with active count
- Starred navigation item with count
- folder row with expanded/collapsed states
- nested list row
- ungrouped lists section
- create folder action
- create list action
- folder/list context menus
- create, rename, move, and delete dialogs

### Task workspace

- list header and list actions
- quick-add task composer
- active task list
- task row
- completion checkbox
- star control
- due-date/status indicator
- task row action menu
- completed section and count
- empty, loading-shaped, and error states
- confirmation dialog
- single-action Undo bar

### Task details

- editable title presentation
- starred state
- due-date field and clear action
- notes field
- destination list selector
- delete, cancel, and save actions

Reminder, subtasks, comments, and attachments may appear only as intentionally
disabled future affordances if the design needs them. They must not appear
functional or expand current scope.

### Shared UI primitives

- button and icon button
- text input, textarea, checkbox, and select
- dropdown/context menu
- dialog and drawer/sheet
- avatar
- badge/count
- tooltip where icon meaning is not otherwise clear
- inline validation and alert
- skeleton or loading placeholder

Do not add a second component library. Reusable primitives should be local,
accessible, and styled with Tailwind tokens.

## Typed mock data

Create fixture types that match the intended Inertia payloads without importing
Eloquent concepts into React:

```ts
type UserSummary = {
    id: number;
    name: string;
    email: string;
    avatarUrl: string | null;
};

type NavigationList = {
    id: number;
    name: string;
    folderId: number | null;
    isDefault: boolean;
    activeTaskCount: number;
};

type NavigationFolder = {
    id: number;
    name: string;
    lists: NavigationList[];
};

type TaskSummary = {
    id: number;
    title: string;
    note: string | null;
    dueDate: string | null;
    dueDateStatus: 'overdue' | 'today' | 'upcoming' | null;
    isStarred: boolean;
    completedAt: string | null;
    taskListId: number;
};
```

Before Phase 2, reconcile these planning types with the actual API Resources and
service data objects. Do not preserve a fixture field merely because the mock
used it.

Fixtures should demonstrate long names, zero counts, large counts, overdue and
due-today tasks, notes, starred tasks, completed tasks, missing avatars, and
enough navigation items to test scrolling.

## Static interaction policy

Local component state is allowed for visual demonstration:

- opening and closing menus, drawers, and dialogs;
- expanding and collapsing folders or completed tasks;
- selecting a fixture task;
- toggling example checkboxes or stars;
- switching among documented visual states; and
- displaying mock validation or Undo feedback.

No state should claim durable persistence. Do not introduce fetch clients,
query caches, local-storage domain persistence, or mock API servers.

Drag-and-drop is deferred to Phase 3. Phase 1 should reserve handles, focus
styles, and layout space without choosing or installing a drag library.

## Responsive and accessibility requirements

- Wide desktop: three columns when details are open.
- Standard desktop: sidebar plus center workspace; details may overlay or reduce
  the center width without making tasks unusable.
- Tablet/mobile: sidebar becomes a drawer and task details become a sheet or
  full-height layer.
- Navigation, quick-add, task completion, starring, menus, dialogs, and
  completed-section disclosure must be keyboard usable.
- Use semantic controls, associated labels, visible focus, useful accessible
  names, and correctly restored focus after closing overlays.
- Respect reduced motion and ensure task status is not communicated by color
  alone.

## Non-goals

- Backend reads or writes
- `/api/v1` consumption
- Inertia mutation forms
- Real authentication submission
- Authorization behaviour
- Persistent Undo
- Drag-and-drop or reorder persistence
- NativePHP screens
- Passkeys, 2FA, password reset, email verification, or settings screens
- Search, recurrence, reminders, subtasks, sharing, comments, or attachments

## Deliverables

- Configured Inertia/React/TypeScript/Tailwind/Wayfinder frontend foundation
- Static login and registration pages
- Complete fixture-backed authenticated shell
- Reusable component and local primitive library
- Typed fixtures and page-prop definitions
- Responsive desktop/tablet/mobile layouts
- Documented design tokens and important component states
- Successful production frontend build

## Acceptance criteria

- Every page and state in the inventory can be reviewed without a database
  dependency.
- The authenticated desktop layout clearly reflects the supplied Wunderlist
  reference and successful parts of `old-resources`.
- Login and registration feel like My Fabulist and use the bookmark-check logo.
- No frontend code calls `/api/v1` or Laravel domain endpoints.
- No Passkey, email-verification, password-reset, 2FA, or settings UI remains in
  the new runtime path.
- Components render realistically with long, empty, error, and overflow data.
- Keyboard and responsive walkthroughs reveal no blocking navigation issues.
- `npm run build` passes.
- Existing backend tests have not been removed or weakened.

## Exit gate

Phase 1 is complete only after the static interface and responsive states are
approved. Visual approval freezes the basic component contracts for Phase 2;
small prop refinements remain allowed when real service payloads expose a
documented mismatch.
