# Completion sounds

Purplelist can play a short, user-selected sound after a task completion is
successfully saved. `sound-effect-01.mp3` is the platform default; a null
`users.completion_sound_id` is the explicit **No sound** choice.

## Catalog and ownership

`completion_sounds` is the canonical catalog. Each row has a stable `key`, a
display `label`, a public-root-relative `file_path`, availability and default
flags, and a display order. `users.completion_sound_id` references the selected
row and is nulled if that row is deleted.

The profile modal receives enabled catalog rows through shared Inertia props.
The user's already-selected row remains visible if it is later disabled, but a
disabled row cannot be newly selected. The same preference mutation is exposed
through the authenticated web and `/api/v1/profile/completion-sound` routes.

## Adding another sound

Adding a sound does not require frontend or service changes:

1. Place the audio file in `public/`.
2. Insert a `completion_sounds` row whose `file_path` matches that filename and
   set its `label`, `sort_order`, and flags.

The new enabled row appears automatically in the profile gallery and receives
its own Preview control. Keep exactly one catalog row marked `is_default`.

## Playback behavior

Preview playback is user-triggered and stops when the profile dialog closes or
the preference is saved. Completion playback happens only in the browser after
the server accepts a top-level task completion. Playback failure never blocks
or rolls back the task mutation. Restores, subtasks, and rejected completions do
not play the sound.
