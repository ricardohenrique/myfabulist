# Web rebuild — Phase 2: Inertia integration

> **Status: completed 11 August 2026.**

## Objective

Connect the Phase 1 React interface to canonical Laravel data and workflows
without making the browser call the application's own API over HTTP.

## Delivered

- Service-backed Inertia pages for Inbox, ordinary lists, and Starred.
- Shared navigation and workspace presenters with explicit TypeScript payloads.
- Registration, login, logout, guest redirects, and authenticated product
  routes without an email-verification requirement.
- Folder and list create, rename, move, order, and guarded delete workflows.
- Task quick-add, details editing, completion/restoration, starring, explicit
  cross-list movement, ordering, and soft deletion.
- One-action Undo for completion, starred-state changes, and task movement.
- Actionable validation/domain feedback and canonical Inertia reconciliation.
- Authorization and transport coverage for web and `/api/v1` workflows.

## Architecture decision

The Laravel application layer is shared in-process:

```text
Inertia controller ─┐
                    ├─→ Service → Repository → database
API controller ─────┘
```

Browser controllers return redirects or Inertia responses. API controllers
return API Resources and stable JSON error envelopes. Neither duplicates domain
rules, and the server never calls `/api/v1` through loopback HTTP.

## Result

Phase 2 replaced all fixture behavior on production routes. The temporary
fixture harness was removed during final cleanup; Phase 3 refined ordering,
responsive behavior, and documentation.
