# Web rebuild — Phase 2: Inertia and backend integration

## Objective

Replace Phase 1 fixtures and simulated actions with canonical Laravel data and
real mutations through Inertia, while preserving the approved interface.

The existing Laravel backend is the domain authority. The web application must
reuse its services, repositories, policies, domain exceptions, and serializers
in-process. The browser must not call My Fabulist's `/api/v1` endpoints to render
or mutate first-party web pages.

## Architecture decision

Yes: an API-first Laravel product can and should use services directly from its
Inertia web controllers.

```text
React/Inertia page
        │ Inertia request/response
        ▼
Web controller + web Form Request
        │ authorize and delegate
        ▼
Shared policy + service + repository
        │
        ▼
Canonical database

Remote/native client
        │ HTTPS JSON
        ▼
/api/v1 controller + API Form Request/Resource
        │ authorize and delegate
        └────────► same policy + service + repository
```

“API first” means the domain supports a stable remote contract; it does not
require the Laravel server to make HTTP calls to itself. Web and API transports
remain separate while sharing application logic.

## Approved authentication scope

The web authentication scope is:

- registration;
- login;
- logout; and
- session-based access to the authenticated application.

Remove the email-verification requirement for now. Also disable or remove the
new-runtime exposure of password reset, two-factor authentication, passkeys,
profile, security, and appearance settings.

Required integration work includes:

1. Configure Fortify's login and registration views to return the Phase 1
   Inertia pages.
2. Preserve the existing registration action that creates the user and default
   Inbox, unless tests expose a product mismatch.
3. Remove `verified` middleware from authenticated web and `/api/v1` domain
   routes, leaving the correct `auth` or `auth:sanctum` middleware.
4. Remove email verification, password reset, 2FA, and passkey features from
   the enabled Fortify feature list for this release.
5. Remove `MustVerifyEmail` from the user contract if it is no longer used by
   any retained flow.
6. Ensure successful registration/login redirects to Inbox and logout returns
   to login.
7. Preserve login rate limiting, session regeneration, CSRF protection, secure
   password handling, and cross-user authorization.

This is an intentional temporary product simplification. Reintroducing any
excluded authentication feature requires its own UI, tests, and scope update.

## Inertia application foundation

- Add the Inertia middleware and root view required by Laravel.
- Share only small, universal props globally, such as the authenticated user,
  flash messages, and stable application metadata.
- Do not globally serialize the full navigation tree or task collections when
  they are unnecessary on public pages.
- Use Laravel Wayfinder for named route integration instead of manually
  scattering URL strings through React.
- Keep page props explicit and serializable. Do not pass unrestricted Eloquent
  models, hidden attributes, authorization-sensitive relationships, or lazy
  database access into the frontend.
- Use API Resources, focused Inertia presenters, or existing service data
  objects to keep web payloads consistent with the API contract. The transport
  envelope may differ; domain field meaning must not.

## Read-side integration

### Application navigation

Use `NavigationService::treeFor()` to supply:

- Inbox identity and active count;
- Starred count;
- folders in persisted position order;
- lists within each folder;
- ungrouped lists; and
- per-list active task counts.

Navigation props belong only on authenticated application pages. The selected
route determines the active item; React must not create a second canonical
selection state.

### Inbox and ordinary lists

Use `TaskListService::inboxFor()` or the authorized selected list plus
`TaskService::tasksFor()` to supply:

- list identity and metadata;
- active tasks in manual position order;
- completed tasks in most-recently-completed order;
- completed count; and
- user-owned destination lists for move actions.

### Starred

Use `TaskService::starredFor()` for the smart view. A starred task remains owned
by its original list and should include enough list/folder context for the row
and task details experience.

### Task details

Task detail data must be authorized and may be included with the current page
when selected or loaded through a focused Inertia-compatible web endpoint. Do
not use the public API merely to open the panel.

## Web route and controller plan

Retain canonical read URLs:

- `/inbox`
- `/starred`
- `/lists/{list}`

Create named web mutation routes for the React forms. Keep them inside session
authentication middleware and use CSRF-protected non-GET methods. The exact
controller grouping should follow Laravel conventions, but it must cover:

- folder create, rename, delete/detach, and reorder;
- list create, rename/move, delete, and reorder;
- task create, update/rename, delete, complete, restore, star/unstar, move, and
  reorder; and
- logout through the retained Fortify/session flow.

Controllers must remain thin:

1. validate with a Form Request;
2. authorize the user and target resource;
3. call the existing service method;
4. translate known domain exceptions into validation or flash feedback; and
5. return an Inertia redirect or appropriate redirect-back response.

Do not move Eloquent queries into controllers. Do not have web controllers call
API controllers.

## Validation and authorization

- Reuse domain services as the final invariant boundary.
- Avoid duplicating API validation rules by extracting shared rule objects or
  focused request data where useful. Do not force an API-specific request class
  onto a web route when its response semantics do not fit.
- Authorize every user-owned folder, list, and task on the server.
- Route model binding must not leak another user's records. Preserve the
  project's intended forbidden/not-found semantics.
- Validate complete reorder ID sets, matching ownership, container membership,
  and duplicate IDs.
- Recheck folder deletion choices, default-Inbox protection, move destinations,
  and restore destinations inside transactional domain workflows.

## Replacing fixtures with props

Replace Phase 1 fixtures page by page:

1. authenticated user and navigation;
2. Inbox list and task collections;
3. ordinary list page;
4. Starred view;
5. task details and destination lists; and
6. empty/error/count variants derived from real data.

Keep React types synchronized with the serialized payload. Remove fixture-only
fields that have no canonical meaning. Fixtures may remain for isolated visual
development if they are clearly separated from runtime data and kept aligned
with production types.

## Mutation integration

Use Inertia forms and named route helpers for product mutations. Each flow must
define:

- pending/disabled behaviour;
- validation display;
- focus and input preservation;
- success feedback;
- rejected/stale-state recovery; and
- scroll/state preservation where it improves the workflow.

### Quick-add

- Submit the trimmed title to the selected list.
- Clear only after success.
- Restore focus after success.
- Preserve the title and show validation or domain feedback after failure.

### Completion and restoration

- Move tasks between active and completed sections after canonical success.
- Keep completed ordering consistent with the service result.
- Expose a single recent Undo action that calls the inverse service-backed web
  mutation.

### Starring

- Persist the binary flag through `TaskService::setStarred()`.
- A task removed from Starred should leave that smart view without being
  deleted.
- Undo calls the inverse mutation.

### Task editing and moving

- Submit complete task details with the service's replace semantics.
- Explicitly send title, note, due date, starred state, and destination list as
  required by the final request contract.
- Moving must use `TaskService::move()` and preserve ownership/container rules.

### Deletion

- Keep confirmation for task and list deletion until deletion Undo is completed
  and tested.
- Preserve soft-deletion semantics and default-Inbox protection.
- Folder deletion must require the explicit detach-lists or delete-lists choice.

### Reordering

Phase 2 wires accessible move-up/move-down operations and prepares the reorder
routes and prop refresh behaviour. Pointer drag-and-drop itself remains Phase 3.

## State ownership

Server state belongs to Laravel and arrives through Inertia props. React local
state is appropriate for transient presentation state such as:

- open menus and dialogs;
- mobile navigation visibility;
- selected task-detail panel state when reflected safely in the page flow;
- completed-section disclosure; and
- temporary draft input before submission.

Do not persist folders, lists, tasks, or mutation queues in browser local
storage. A small UI preference such as completed-section disclosure may use
browser storage if it is namespaced per user/list and failure is harmless.

Do not add a query-cache or global state framework unless a demonstrated need
is approved.

## Error and conflict handling

- Validation failures remain next to the relevant fields.
- Known domain exceptions become actionable messages rather than generic 500
  responses.
- Authorization failures do not reveal foreign resource details.
- Stale reorder sets refresh canonical navigation/tasks and explain why the
  visual order was reset.
- Unexpected failures keep user-entered form data where safe and provide a
  retry path.
- Flash and Undo messages are accessible and do not rely on transient color
  alone.

## Test plan

### Authentication

- guests can view login and registration;
- users can register and receive a default Inbox;
- users can log in and are redirected to Inbox;
- authenticated users can log out;
- application pages do not require verified email;
- excluded Fortify feature routes are unavailable; and
- login rate limiting and session security remain intact.

### Inertia reads

- guests cannot access application pages;
- Inbox, list, and Starred routes return the expected Inertia components and
  prop shapes;
- navigation ordering/counts are correct;
- empty and completed states serialize correctly; and
- another user's resources are inaccessible.

### Mutations

- every folder/list/task action delegates to the established domain behaviour;
- validation and authorization failures have the intended Inertia response;
- quick-add, completion, restore, star, move, task details, and deletion work;
- default Inbox and non-empty-folder protections remain intact;
- accessible reorder actions persist correctly; and
- undo performs the inverse canonical mutation safely.

Keep existing API tests passing. Web integration must not alter documented
`/api/v1` payloads or error shapes accidentally.

## Non-goals

- Browser HTTP consumption of `/api/v1`
- NativePHP or device-token UI
- Email verification, password reset, 2FA, passkeys, or settings pages
- Pointer drag-and-drop
- Offline support or client-side domain persistence
- Search or deferred product capabilities
- Broad visual redesign after Phase 1 approval

## Deliverables

- Functional Inertia login, registration, and logout
- Verified-email requirement removed for this release
- Authenticated Inertia application routes
- Real navigation, Inbox, list, Starred, and task-detail props
- Service-backed web mutation controllers and Form Requests
- Inertia forms with validation, pending, success, and failure behaviour
- Working accessible reorder controls
- Focused Inertia feature coverage plus preserved API/domain tests
- Fixture-free production data path

## Acceptance criteria

- A new user can register, receive an Inbox, and use the app without verifying
  an email address.
- A returning user can log in and log out.
- The complete folder/list/task workflow operates through Inertia and existing
  domain services.
- The web application makes no loopback HTTP calls to `/api/v1`.
- Cross-user access, default Inbox, reorder, move, and deletion invariants are
  still enforced.
- Validation errors preserve useful user input and focus behaviour.
- API contract tests continue to pass.
- `composer test` and `npm run build` pass.

## Exit gate

Phase 2 is complete when the Phase 1 interface has functional parity with the
approved existing product workflows, excluding pointer drag-and-drop and the
explicit Phase 3 refinements. Do not remove the old frontend dependencies until
the parity review is approved.
