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
* Delete action

Keep the details interface simple and avoid making it feel like a complex project-management tool.

## Visual style

Use a friendly, lightweight visual style inspired by classic productivity applications.

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
