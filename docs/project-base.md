MoSCoW prioritization
Must have
These capabilities are required for the product to deliver its core value.
M1. Create and manage folders
A user must be able to:
Create a folder.
Rename a folder.
Delete a folder.
Expand and collapse a folder.
Place multiple lists inside a folder.
Move a list into or out of a folder.
Rule: Deleting a non-empty folder should not silently delete its lists. The user must either confirm deletion or move the lists outside the folder.
Example folders: Work, Personal, Home.

M2. Create and manage lists
A user must be able to:
Create a list.
Rename a list.
Delete a list.
Place the list inside a folder or leave it ungrouped.
Open a list to see its tasks.
Reorder lists manually.
A list is the MVP equivalent of a lightweight project or context.
Examples: Website Launch, Groceries, Books to Read.

M3. Create tasks quickly
A user must be able to:
Enter a task title.
Add it by pressing Enter or selecting an Add action.
Create another task immediately afterward.
Create tasks from inside a selected list.
Prevent blank tasks from being saved.
The task title should be the only required field.
Performance target: A user should be able to add a task in one interaction after opening a list.

M4. Display active tasks clearly
Each list must show its incomplete tasks in a clear primary section.
Every task row should include:
Completion checkbox.
Task title.
Optional due-date indicator.
Optional priority/star indicator.
An affordance to open or edit task details.
Active tasks should appear before completed tasks.

M5. Complete and uncomplete tasks
A user must be able to:
Mark a task complete through its checkbox.
Immediately see visual confirmation.
Restore a completed task to active status.
Retain the task rather than permanently deleting it.
The completion interaction should feel instantaneous.

M6. Show completed tasks beneath active tasks
Completed tasks must appear in a separate Completed section below the active-task list.
The section should:
Be collapsible.
Display the number of completed tasks.
Be collapsed or expanded according to the user’s last choice.
Show completed tasks with reduced opacity.
Show the title with a strikethrough.
Preserve the task’s original information.
Allow the task to be unchecked and restored.
Suggested visual treatment:
40–60% opacity.
Strikethrough title.
Muted metadata.
Completed tasks ordered by most recently completed first.
This reproduces the useful Wunderlist behavior you remembered: completed work remains visible for reassurance, but does not compete visually with active work.

M7. Edit and delete tasks
A user must be able to:
Edit a task title.
Delete a task.
Cancel an edit without losing the existing title.
Receive a confirmation or short undo period after deletion.
Deleting and completing must be clearly different actions.

M8. Persist user data
Folders, lists, tasks, ordering, and completion states must persist after:
Refreshing the page.
Closing and reopening the application.
Signing out and returning, when accounts are included.
For a temporary proof of concept, local browser storage is acceptable. For a real multi-device MVP, server-side persistence is required.

M9. Basic responsive web interface
The primary workflow must work on:
Desktop browsers.
Tablet widths.
Mobile browser widths.
The MVP can be a web app instead of separate native apps.
A practical layout would be:
Left navigation: folders and lists.
Main panel: selected list and tasks.
Optional details panel or modal: task details.

M10. Clear empty and error states
The application must explain what to do when:
No folders exist.
No lists exist.
A selected list has no tasks.
All tasks are complete.
A task cannot be saved.
Data fails to load.
Example empty-list message:
Nothing here yet. Add your first task.

Should have
These features add substantial usefulness but are not required to prove the core concept.
S1. Due dates
A user should be able to:
Assign a due date to a task.
Remove or change it.
See the date directly in the task row.
Recognize overdue tasks.
Recognize tasks due today.
Do not require a calendar view in the first release.

S2. Starred or important tasks
Wunderlist allowed users to star important tasks.
A user should be able to:
Star or unstar a task.
See the star in the task row.
Optionally place starred tasks above non-starred tasks.
For the first version, avoid adding multiple priority levels. A binary star is simpler.

S3. Task notes
A task should support an optional plain-text note.
The user should be able to:
Add a note.
Edit it.
Remove it.
See an indicator when a task has a note.
Wunderlist supported notes as part of its task-detail model.

S4. Manual reordering
Users should be able to reorder:
Tasks inside a list.
Lists inside a folder.
Folders in the sidebar.
Drag-and-drop is ideal, but accessible move-up and move-down actions should also exist.

S5. Search
A user should be able to search task titles across all lists.
Search results should show:
Task title.
Parent list.
Parent folder, when applicable.
Completion status.
Searching notes can be postponed.

S6. Inbox
Provide a default Inbox list for quick capture before tasks are organized. Wunderlist used an Inbox as a default collection point.
The Inbox should:
Always exist.
Not require a folder.
Be the default destination for globally added tasks.
Allow tasks to be moved to other lists.

S7. Undo recent actions
Provide a brief Undo option after:
Completing a task.
Deleting a task.
Deleting a list.
Moving a task.
This reduces accidental data loss and keeps the interface fast by avoiding excessive confirmation dialogs.

S8. Basic authentication
For a cloud-hosted product, users should be able to:
Create an account.
Sign in.
Sign out.
Reset a forgotten password.
Access only their own data.
This moves to Must when the MVP is expected to work across multiple devices.

Could have
These features resemble Wunderlist more closely, but should follow validation of the basic task workflow.
C1. Smart lists
Automatically generated views such as:
Today.
Upcoming.
Starred.
All tasks.
Completed.
Wunderlist included smart lists that collected tasks such as Today, Week, and Starred across ordinary lists.

C2. Recurring tasks
Support simple recurrence:
Daily.
Weekly.
Monthly.
Custom interval.
Wunderlist supported recurring tasks, but recurrence introduces substantial edge cases around due dates, completion history, and missed occurrences.

C3. Reminders and notifications
A user could receive a notification before or when a task is due.
Possible options:
At due time.
10 minutes before.
One hour before.
One day before.
This requires browser or device permissions and should not block the initial MVP.

C4. Subtasks
A task could contain a simple checklist of smaller steps.
For the first implementation:
Allow one level only.
Give subtasks a title and completion state.
Do not give subtasks their own dates, notes, or reminders.
Wunderlist supported subtasks, but making them equivalent to full tasks significantly expands the data model and interface.

C5. Shared lists
Allow a user to invite another person to a list.
Collaborators could:
View tasks.
Add tasks.
Edit tasks.
Complete tasks.
Wunderlist’s list sharing was an important feature for households and small teams.
Implemented: list sharing shipped as invite-by-email of a registered user,
with accept, decline, revoke, and leave. An accepted member sees and edits
the same canonical tasks, subtasks, and comments as the owner — one row, one
set of child records, identical for everyone. Only a list's placement
(folder and sidebar position) and starring stay per-member, and there is no
live sync; see development/scope.md for the full boundary. Task assignment
(C6, below) did not ship with it.

C6. Task assignment
In shared lists, assign a task to one collaborator.
Do not include complex permissions, workload dashboards, or multiple assignees initially.

C7. Comments
Collaborators could add a chronological discussion to a task. Wunderlist supported task comments.
The initial implementation is deliberately basic: plain text only, immediate
Enter-to-post, chronological ordering, and author attribution. Editing,
deleting, reactions, mentions, and notification delivery remain future work.

C8. Attachments
Allow users to attach files or images to a task. Wunderlist included file attachments in task details.
This should wait because it adds storage, security, upload, preview, deletion, and cost concerns.

C9. Natural-language task entry
Interpret entries such as:
Submit report tomorrow at 4 pm
and automatically extract the due date. Wunderlist introduced quick entry that could recognize date and time information.

C10. Themes and backgrounds
Allow users to customize the application background or theme.
This contributed to Wunderlist’s personality but does not materially validate the task-management concept.

Won’t have in the MVP
Explicitly exclude these to protect scope.
W1. Full project management
No:
Kanban boards.
Gantt charts.
Dependencies.
Time tracking.
Workload planning.
Project budgets.
Custom workflows.
The product is a task-list application, not a Jira or Asana replacement.

W2. Deep hierarchy
No nested folders or unlimited task nesting.
Use only:
Folder → List → Task
Optional subtasks can be introduced later as one additional level.

W3. Calendar synchronization
Do not initially sync with Google Calendar, Outlook, or Apple Calendar.
Due dates inside the app are enough for MVP validation.

W4. Email-to-task and third-party integrations
No Slack, email, Zapier, API, voice-assistant, or browser-extension integrations.

W5. Advanced collaboration controls
No:
Workspace roles.
Guests.
Per-task permissions.
Approval flows.
Audit logs.
Enterprise administration.

W6. Native desktop and mobile apps
Start with a responsive web application. Native clients can follow only after usage validates the concept.

W7. AI task planning
No automatic task breakdown, prioritization, scheduling, or productivity coaching in the MVP.

Recommended MVP release scope
For the smallest credible first release, implement:
Folders.
Lists.
Tasks.
Task completion and restoration.
Completed section beneath active tasks.
Editing and deletion.
Manual ordering.
Local or server persistence.
Responsive web interface.
Inbox.
Optional due dates and stars.
That is enough to test the central hypothesis:
People want a lightweight, visually calm way to organize lists and retain satisfying, unobtrusive evidence of completed work.
Core user stories
Organization
As a user, I can create folders so that related lists are grouped together.
As a user, I can create a list inside a folder so that I can organize a project or area.
As a user, I can move a list between folders without losing its tasks.
Task management
As a user, I can add a task with only a title so that capture is fast.
As a user, I can edit or delete a task when plans change.
As a user, I can reorder tasks so that the list reflects how I want to work.
Completion experience
As a user, I can check off a task so that it leaves the active section.
As a user, I can still see completed tasks beneath the list so that I know what I accomplished.
As a user, I see completed tasks with reduced opacity and strikethrough so that they do not distract me.
As a user, I can collapse the Completed section when I want a cleaner view.
As a user, I can uncheck a completed task if I marked it by mistake.
Reliability
As a user, my lists and tasks remain available after reopening the app.
As a user, I receive clear feedback when an action cannot be saved.
As a user, I can undo accidental destructive actions.
Suggested data model
User
- id
- name
- email

Folder
- id
- user_id
- name
- position
- created_at
- updated_at

List
- id
- user_id
- folder_id, nullable
- name
- position
- created_at
- updated_at

Task
- id
- list_id
- title
- note, nullable
- is_completed
- completed_at, nullable
- is_starred
- due_date, nullable
- position
- created_at
- updated_at
  Keep completed tasks in the same Task collection. Do not move them into a separate archive table. Their is_completed and completed_at fields are enough to render them beneath active tasks and restore them easily.
  Key acceptance criteria
  The MVP is ready when a new user can:
  Create a folder called “Work.”
  Create a list called “Website launch” inside it.
  Add five tasks without leaving the keyboard.
  Reorder those tasks.
  Mark one complete.
  See it move immediately into a muted Completed section beneath the active tasks.
  Restore it by unchecking it.
  Refresh the application without losing any data.
  Complete the whole flow on both desktop and mobile widths.
  API Suggestions

/api/v1/inbox
/api/v1/folders
/api/v1/folders/{folder}
/api/v1/lists
/api/v1/lists/{list}
/api/v1/lists/{list}/move
/api/v1/lists/{list}/tasks
/api/v1/tasks/{task}
/api/v1/tasks/{task}/complete
/api/v1/tasks/{task}/restore
/api/v1/tasks/{task}/move
/api/v1/lists/{list}/task-order
