Build a simple task-management web application inspired by Wunderlist.

The application should have a clean, calm, and minimal interface. Its main purpose is to let users quickly capture tasks, organize them into lists, and see completed work without it distracting from active tasks.

## Main layout

Use a two-column layout.

The left sidebar contains:

* Inbox at the top
* Folders
* Lists inside each folder
* A button to create a new list
* A button to create a new folder

The main area displays the currently selected list and its tasks.

On mobile, the sidebar should open from a menu button.

The account menu opens a large profile settings modal over the current
workspace, preserving the user's list and task context behind it. The initial
profile surface stays deliberately small: users can update their name and
email, or change their password after confirming the current password. It does
not include avatars, account deletion, verification, password reset, 2FA, or
passkeys.

## Organization structure

The application uses this structure:

Folder → List → Task

Folders are used to group related lists.

For example:

* Work

    * Website Launch
    * Marketing
* Personal

    * Home
    * Shopping

Lists can also exist outside folders.

## Inbox

The Inbox is a permanent system list for quickly capturing tasks before organizing them.

The Inbox should:

* Always appear at the top of the sidebar
* Never be deleted or renamed
* Receive tasks created through the global quick-add action
* Show the number of active tasks
* Allow tasks to be moved into another list

The idea is:

“Capture now, organize later.”

## Lists

When the user opens a list, show:

* The list name
* A field for adding a new task
* Active tasks
* A Completed section below the active tasks

The user should be able to:

* Create a list
* Rename a list
* Delete a list
* Move a list into a folder
* Reorder lists
* Open a list by selecting it in the sidebar

## Tasks

Each task row should contain:

* A completion checkbox
* The task title
* An optional star for important tasks
* An optional due date
* A button or menu for editing and deleting the task

The task title is the only required field.

The user should be able to:

* Add a task quickly
* Edit its title
* Delete it
* Mark it complete
* Restore it
* Star it
* Add a due date
* Move it to another list
* Reorder tasks using drag and drop

Pressing Enter in the new-task field should immediately create the task and keep the field focused so another task can be added.

## Completed tasks

When a task is completed, move it from the active section into a Completed section at the bottom of the same list.

Completed tasks should:

* Remain visible
* Have a strikethrough title
* Use reduced opacity
* Look visually muted
* Be ordered by most recently completed
* Be restorable by clicking the checkbox again

The Completed section should:

* Appear below active tasks
* Show the number of completed tasks
* Be collapsible
* Remember whether the user left it open or closed

Completed tasks should not be permanently removed unless the user explicitly deletes them.

## Folders

Folders should appear in the sidebar and contain lists.

The user should be able to:

* Create a folder
* Rename it
* Delete it
* Expand or collapse it
* Move lists into or out of it
* Reorder folders

Deleting a folder should not silently delete its lists. The user should be asked whether to move the lists outside the folder or delete them.

## Task details

Selecting a task should open a details panel or modal.

The details view can contain:

* Task title
* Due date
* Important/starred status
* Notes
* Destination list
* One-level subtasks with title and completion state
* Chronological plain-text comments with author name and avatar
* Delete action

Keep the details interface simple and avoid making it feel like a complex project-management tool.

The comment composer posts immediately when the user presses Enter. Shift +
Enter inserts a new line. Comments render as plain text and display the
author's profile image when available, with a neutral user icon as fallback.
The task-details checkbox and star update completion and starred state
immediately; they do not wait for the user to press Save.
Subtasks appear between Reminder and Notes as compact, editable checkbox rows.
Enter creates a subtask, checking it completes or restores it immediately,
editing its title saves on Enter or blur, and its row action deletes it.
The task creation date appears directly above the footer actions. Recent dates
use relative language, while older dates use a localized calendar date.
The task-details footer uses an icon-only close action on the left, a slightly
larger purple Save button in the center, and an icon-only delete action on the
right.
Task-detail form controls use a soft purple focus ring and neutral-lilac focused
surface so keyboard focus stays visible while matching the product identity.

## Visual style

Use a friendly, lightweight visual style inspired by classic productivity applications.

The primary identity color is light purple `#8B6FD6`. It is used for the logo,
favicons, stars, selected navigation, focus treatments, progress feedback, and
non-destructive primary actions. Red remains reserved for destructive actions,
validation errors, and overdue states.

The interface should feel:

* Calm
* Fast
* Spacious
* Friendly
* Minimal
* Easy to understand

Use:

* A light neutral background
* White content surfaces
* Soft borders and shadows
* Rounded corners
* Clear typography
* Subtle animations
* Muted completed tasks
* One main accent color

Avoid:

* Dense dashboards
* Large data tables
* Kanban boards
* Too many settings
* Complex project-management features

## Interaction behavior

Interactions should feel immediate.

When the user completes a task:

1. Animate the task leaving the active section.
2. Move it into the Completed section.
3. Reduce its opacity and add a strikethrough.
4. Briefly show an Undo option.

When the user adds a task:

1. Add it immediately to the current list.
2. Keep the input focused.
3. Clear the input field.
4. Show an error if the task cannot be saved.

When the user moves or reorders something, update the interface immediately and save the new order in the background.

Reordering uses the full item surface for active tasks, lists, and folders,
without separate grip icons. Ordinary clicks on nested controls keep their
existing actions. Pointer, trackpad, touch, and keyboard are supported; a
keyboard user focuses the item, starts the drag with Space or Enter, changes
position with the arrow keys, and drops with Space or Enter. Dedicated
move-up/move-down menu items are intentionally not part of the final interface.

Task and list drops are constrained to their current container. Moving a task
to another list or a list to another folder remains an explicit details/dialog
operation, never an accidental drag side effect.

## Empty states

When a list has no tasks, show:

“Nothing here yet. Add your first task.”

When the Inbox is empty, show:

“Your Inbox is clear.”

When every task is completed, show a small positive message such as:

“Everything is done.”

## Main screens and components

Create these main components:

* Application shell
* Mobile navigation
* Sidebar
* Inbox navigation item
* Folder navigation item
* List navigation item
* New folder form
* New list form
* List header
* Quick task input
* Active task list
* Task row
* Completed task section
* Task details panel
* Move-task menu
* Confirmation dialog
* Undo notification
* Empty state
* Loading state
* Error state

The final result should look like a polished but simple task-list product, not a full project-management platform.

## Implemented frontend architecture

The production browser experience is an Inertia 3 and React 19 application.
Page entry points live in `resources/js/pages`, the responsive product shell in
`resources/js/layouts/app-shell.tsx`, shared components in
`resources/js/components`, and shared payload types in `resources/js/types`.
Wayfinder supplies typed Laravel route calls.

The browser never calls `/api/v1` over loopback HTTP. Laravel controllers build
Inertia props through shared services and presenters, and mutations delegate to
the same service/repository workflows used by API controllers. Server data is
canonical.

`@dnd-kit/react` provides sortable pointer, touch, and keyboard interaction.
Each drop optimistically updates the local collection, submits its complete ID
order, disables further drops while saving, and reconciles from returned
Inertia props. Active tasks initially display by `createdAt` descending. The
workspace Sort menu can switch to a non-persisted alphabetical A–Z view or the
persisted custom order; dragging from the recent/custom view switches to and
saves the custom order. Newly created tasks are inserted at its first position.
Completed tasks remain ordered by completion time. Inbox and Starred are fixed
navigation items rather than sortable user lists.

## Sharing and notification components

`resources/js/components/navigation/notification-center.tsx` implements the
sidebar bell. It is a lightweight anchored popover (not `components/ui/dialog.tsx`)
following the existing account-menu pattern in `sidebar.tsx`, since a short,
low-stakes list of pending invitations does not need a full modal. Opening it
triggers a partial Inertia reload (`router.reload({ only: ['notifications'] })`)
that hydrates `notifications.invitations`, an `Inertia::optional()` prop that is
otherwise absent from the page. Each row shows the inviter's name and avatar,
the list name, a relative "invited X ago" label, and per-row Accept/Decline
buttons with independent pending state. The panel traps and restores focus,
closes on Escape or an outside click, and reports its pending count through an
accessible label on the trigger button.

`resources/js/components/lists/share-dialog.tsx` implements the list-level
sharing UI, opened from the "Share" action in the workspace header or any
non-Inbox list's three-dot menu. A sidebar action requests the optional
`sharingDialog` prop with a partial Inertia reload and `preserveUrl`, so the
selected list, workspace content, and URL remain unchanged behind the dialog.
Unlike the notification
center, this uses `components/ui/dialog.tsx` directly — managing a list's full
member roster, pending invitations, and an invite form is a genuinely modal,
higher-stakes interaction with more content than an anchored popover suits. It
renders the accepted member list (avatar, name, an "Owner" label, and email
visible only to the owner per F18) and the pending-invitations list in the same
shape, each with its own relative "invited X ago" label. The list owner
additionally sees Remove/Revoke actions on every non-owner row and an
invite-by-email form; a non-owner accepted member sees the same rosters
read-only. All mutations (`inviteMember`, `revokeMembership`, `leaveList`) are
owned by `app-shell.tsx`, following the same `router.post`/`router.delete` plus
local pending-state pattern as every other mutation on that layout — the
dialog itself is presentational.
