# Local database reset and demo seeding

> **Status: implemented.**

## Purpose

`php artisan db:fresh-seed` rebuilds a disposable local or testing database and
loads a realistically sized dataset for navigation, ordering, Starred, due
dates, notes, and completed-task testing.

## Safety

- The command is destructive: it drops every table before migrating and
  seeding.
- It refuses to run outside `local` and `testing` unless `--force` is passed.
- It prints the selected connection and database before destruction.
- Never run it against an unidentified or production database.

## Dataset

- 20 users: `demo1@example.com` through `demo20@example.com`.
- Shared password: `password`.
- One permanent Inbox per user.
- 3–5 folders, 5–10 foldered lists, and 2–4 ungrouped lists per user.
- 10–20 tasks per list with realistic titles and a mix of notes, completed
  states, starred states, and overdue/today/upcoming due dates.
- Contiguous positions within every folder, list, and task ordering scope.

The small `php artisan db:seed` fixture remains separate and is appropriate for
quick setup or focused acceptance checks.

## Implementation boundaries

The command orchestrates database reset, migrations, and `DemoSeeder`; factories
own valid relationship construction. Application services and repositories are
not bypassed by production code—this direct fixture construction exists only for
local development data.

## Verification

Feature coverage confirms environment refusal, explicit-force behavior,
expected dataset shape, ownership integrity, exactly one Inbox per user, and
valid contiguous positions.
