# Persistent notification center

The notification center is a first-class workspace route at `/notifications`,
not a transient bell popover. Notifications use Laravel's database notification
channel and are never deleted by normal product workflows. Each recipient owns
their `read_at` state and can switch between All and Unread or toggle an item's
status directly.

Two notification types are currently supported:

- `list_invitation`: created for a fresh invitation and for every re-invite
  after a decline. Accepting or declining snapshots the final status onto that
  notification, marks it read, and disables both response actions. Re-inviting
  creates another row, preserving the earlier decision.
- `task_comment`: created for every other accepted member when someone comments
  on a task in a shared list. The author and pending invitees are excluded.

Comment fan-out is dispatched by `TaskCommentCreated` and handled by the queued
`SendTaskCommentNotifications` listener after the surrounding database commit.
The database queue is therefore required in deployed environments, matching
`.env.example`'s `QUEUE_CONNECTION=database` configuration.

Notification payloads retain actor, list, task, and comment snapshots so history
remains understandable after names change. Target links are resolved against
the recipient's current accepted memberships: inaccessible or deleted targets
do not leak, but their notification rows remain visible. Opening an accessible
comment routes to `/lists/{list}?task={task}` and opens the task-details panel.

The versioned API mirrors the browser behavior:

- `GET /api/v1/notifications` returns all notifications newest first.
- `GET /api/v1/notifications?filter=unread` returns only unread items.
- `PATCH /api/v1/notifications/{notification}` with `{ "read": true|false }`
  updates the authenticated recipient's read state.

Existing pending invitations are backfilled as unread notification rows by the
notifications-table migration.
