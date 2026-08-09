# Implementation Plan: Local Dev Database Reset + Demo Seeding Command

**Date:** 10-08-2026
**Plan ID:** 1
**Status:** Approved — ready for implementation
**Complexity:** Medium

> **Why this plan exists.** The dev currently has to remember `php artisan migrate:fresh --seed`, which
> produces exactly one user (`test@example.com`) with three lists and five tasks
> (`database/seeders/DatabaseSeeder.php`). That dataset is deliberately minimal — it encodes the
> acceptance-criteria walkthrough — and is useless for exercising the sidebar tree, drag-and-drop
> reordering across folders, Starred, due-date badges, or list/folder pagination-ish density. This plan
> adds a single command that wipes and rebuilds the local database with a realistic, multi-user dataset.
>
> **In scope:** one Artisan command, one demo seeder, one demo-content value source, new `TaskFactory`
> states, tests, README.
> **Out of scope:** any change to `app/` beyond the new command; any migration; any change to
> `DatabaseSeeder`; production/staging usage of any kind.

---

## 1. Requirements Analysis

### Functional Requirements

- [ ] A single custom Artisan command fully resets the local database: **every** table dropped, schema
      rebuilt from migrations, demo data seeded — in one invocation, with no arguments.
- [ ] Running it twice in a row produces the same *shape* of database, never accumulated data
      (idempotent in effect: it always destroys first).
- [ ] The dataset contains exactly **20 users**, all email-verified (the whole app sits behind
      `verified` middleware), all sharing one known password so any of them can be logged into.
- [ ] Each user gets **3–5 folders** (random per user).
- [ ] Each user gets **5–10 lists that live inside those folders** (random per user).
- [ ] Each user additionally gets **2–4 standalone lists** with `folder_id = null`.
- [ ] Each user gets **an Inbox** (`is_default = true`, `folder_id = null`, `position = 0`) — the
      app's core invariant, which factory-created users never get automatically because
      `App\Listeners\CreateDefaultTaskList` only fires on the `Registered` event.
- [ ] Each list (foldered, standalone, **and** Inbox) gets **10–20 tasks** (random per list) — see A1.
- [ ] Seeded data is *realistic*: human-readable folder/list/task names (not `fake()->sentence()` lorem),
      a mix of completed / active / starred tasks, notes on some tasks, and due dates spread across
      overdue / today / upcoming so `Task::dueDateStatus()` renders all three badge states.
- [ ] Every seeded user has at least one starred task, one overdue task and one due-today task, so no
      demo account lands on an empty Starred page.
- [ ] Data is built by **factories and a seeder**; the command only orchestrates (guard → wipe →
      migrate → seed → report). No inline row creation in the command.

### Non-Functional Requirements

- [ ] **Destructive-command safety.** The command refuses to run outside `local`/`testing` unless
      `--force` is passed, using Laravel's own `ConfirmableTrait` — the same mechanism `migrate:fresh`
      and `db:wipe` use. It must print the connection and database it is about to destroy *before*
      destroying it.
- [ ] **Layering.** No business logic in the command; no Service/Repository/Model changes; no new
      queries in `app/Services` or `app/Http/Controllers`.
      `tests/Feature/Architecture/LayeringTest.php` must stay green **without being edited**.
- [ ] **Relationship integrity.** Every seeded row satisfies the invariants the repositories assume:
      `task.user_id === task.taskList.user_id`, `taskList.user_id === folder.user_id` when foldered,
      exactly one `is_default` list per user, and contiguous `position` values within each ordering
      bucket (`folders` per user; `task_lists` per `(user_id, folder_id)`; `tasks` per list) — because
      `applyOrder()` rewrites positions as `0..n-1` and reads sort on `position` then `id`.
- [ ] **Runtime.** A full run must be comfortably fast on SQLite (target: a few seconds, not minutes),
      which at ~3,800 inserts means the writes must be batched inside transactions rather than issued
      as ~3,800 individual auto-commits.
- [ ] **No lazy loading.** `AppServiceProvider` enables `Model::preventLazyLoading()` outside
      production, so the seeder must hold explicit variables and never traverse an unloaded relation.
- [ ] **Quality gate.** `composer test` (config:clear → Pint → PHPStan level 7 → PHPUnit) green at the
      end of every step. New code is `declare(strict_types=1)`, fully typed, constructor-injected where
      it has dependencies.
- [ ] **Test-suite safety.** The tests for this feature must not corrupt the shared in-memory SQLite
      connection that `RefreshDatabase` reuses across the suite (see R1 — this is the single most
      dangerous thing in this plan).

### Assumptions requiring confirmation (A1–A5)

- **A1 — "10–20 tasks" is *per list*, not per user.** The data model is Folders → Lists → Tasks
  (`README.md`), and a per-user total of 10–20 spread over ~11 lists would give ~1 task per list, which
  demos nothing. **Assumed: 10–20 tasks per list, including the Inbox and the standalone lists.**
  Volume consequence: ~230 lists × ~15 tasks ≈ **3,450 tasks**, ~3,800 rows total. If that is too heavy
  for your machine, say so and the ranges become constants you can lower in one line.
- **A2 — The 2–4 standalone lists are *in addition to* the Inbox.** The Inbox is also
  `folder_id = null`, but it is a system invariant, not user content, so it is not counted in the 2–4.
  Standalone lists therefore occupy positions `1..n` in the ungrouped bucket (Inbox holds `0`).
- **A3 — Demo identities are deterministic.** Users are `demo1@example.com` … `demo20@example.com`,
  password `password`, names from Faker. Random Faker emails would make the dataset unusable (you could
  not log in without querying the DB). `test@example.com` is deliberately **not** reused, so that
  `php artisan db:seed` (the documented `DatabaseSeeder` path) can never collide with a demo database
  on the unique email index.
- **A4 — Content is random per run, identities are not.** No fixed Faker seed: each reset gives fresh
  variety, which is what "realistic demo data" implies. If you would rather have byte-identical data
  every run (easier bug reproduction), it is a one-line `fake()->seed(...)`.
- **A5 — `DatabaseSeeder` is left untouched.** It is documented in `README.md` step 3 as the small
  acceptance-criteria walkthrough, and `php artisan db:seed` must keep producing it. The demo dataset
  is a separate seeder class. (Alternative considered in D3.)

---

## 2. Architecture Review

### Existing Codebase Patterns

- **Layering:** `Livewire | API Controller → Service → Repository → Model`. Repositories are the only
  query layer; `tests/Feature/Architecture/LayeringTest.php` statically forbids `DB::`, `::query(` and
  `->where(` under `app/Services` and `app/Http/Controllers`. **It does not scan `app/Console`** — but
  the spirit of the rule still applies: the command must not query.
- **Seeders/factories:** `database/seeders/DatabaseSeeder.php` uses `WithoutModelEvents`, composes
  factories, and puts the dataset shape in private methods. That is the precedent to follow.
- **Factories already carry the relationship glue:**
  - `TaskListFactory::inFolder(Folder $folder)` copies `user_id` from the folder and sets `folder_id`.
  - `TaskListFactory::inbox()` sets `name = 'Inbox'`, `is_default = true`, `folder_id = null`,
    `position = 0` — mirroring `EloquentTaskListRepository::createDefaultFor()`.
  - `TaskFactory::forTaskList(TaskList $list)` copies `user_id` and sets `task_list_id`;
    `TaskFactory::configure()` has an `afterMaking` fallback that resolves `user_id` from the list
    **only when it is unset** — using `forTaskList()` short-circuits it, so no per-task extra query.
  - `TaskFactory::completed()` / `starred()` already exist.
  - `UserFactory` memoizes the bcrypt hash in a static, so 20 users cost one hash.
- **Positions:** `folders` ordered per user; `task_lists` per `(user_id, folder_id)` bucket
  (`EloquentTaskListRepository::nextPosition`); `tasks` per list. `applyOrder()` rewrites to `0..n-1`.
- **Environment guards already in the codebase** (`app/Providers/AppServiceProvider.php`):
  - `DB::prohibitDestructiveCommands(app()->isProduction())` — `migrate:fresh`, `db:wipe`,
    `migrate:refresh/reset/rollback` **throw in production even with `--force`**. This is the existing
    precedent for wrong-environment protection, and it already covers half of our requirement.
  - `Model::preventLazyLoading(! app()->isProduction())` — active in `local` and `testing`.
  - `Date::use(CarbonImmutable::class)` — `now()` returns a `CarbonImmutable`.
- **Command auto-registration:** verified in
  `vendor/laravel/framework/src/Illuminate/Foundation/Application.php:241` — `Application::configure()`
  calls `->withCommands()` by default, which registers `app_path('Console/Commands')`.
  **No `bootstrap/app.php` change is needed**; creating the directory with the command class is enough.
  (`app/Console/` does not exist yet — this is the project's first custom command.)
- **`migrate:fresh` supports `--seeder`** (verified in `FreshCommand.php:151`), and with `--force` it
  will `touch()` a missing SQLite file rather than prompting (`MigrateCommand.php:211-215`).

### Affected Areas

**New**
- `app/Console/Commands/SeedDemoDatabaseCommand.php` — the orchestrator (new directory).
- `database/seeders/DemoSeeder.php` — the dataset builder.
- `database/seeders/DemoContent.php` — realistic name pools + weighted pickers (no DB access).
- `tests/Feature/Console/SeedDemoDatabaseCommandTest.php`
- `tests/Feature/Database/DemoSeederTest.php`
- `tests/Unit/Database/DemoContentTest.php`

**Modified**
- `database/factories/TaskFactory.php` — new states `withNote()`, `dueToday()`, `overdue()`,
  `dueUpcoming()`, `completedAt(CarbonInterface $at)`.
- `tests/Feature/Database/FactorySmokeTest.php` — one assertion per new state.
- `README.md` — Setup §3 and a short "Resetting your local database" note.

**Explicitly untouched**
- `app/Models/*`, `app/Services/*`, `app/Repositories/*`, `app/Http/*`, `app/Listeners/*`
- `database/migrations/*` (no schema change → no migration, per `.claude/rules/architecture.md`)
- `database/seeders/DatabaseSeeder.php` (A5/D3)
- `bootstrap/app.php`, `phpunit.xml`, `composer.json`

### Reusable Components

- `Illuminate\Console\ConfirmableTrait::confirmToProceed()` — the framework's own destructive-command
  guard, gives us `--force` semantics for free.
- `Illuminate\Database\Console\Seeds\WithoutModelEvents`.
- Existing factory states: `inbox()`, `inFolder()`, `forTaskList()`, `completed()`, `starred()`.
- `Illuminate\Database\Eloquent\Factories\Sequence` for the deterministic `demoN@example.com` emails.
- `Seeder::$command` (nullable) for progress output without coupling the seeder to a command.

### Architecture Decisions

**D1 — The command orchestrates; it never builds rows and never queries.**
`handle()` is: guard → report target → `migrate:fresh --force --seed --seeder=DemoSeeder` → print login
hint + elapsed time → `SUCCESS`. All dataset knowledge lives in the seeder. This keeps the command
thin for exactly the reason controllers are thin, and it means the dataset is testable without a
console at all. *Alternative considered:* a self-contained command doing everything — rejected, it
duplicates seeder responsibilities and cannot be unit-tested without shelling through Artisan.

**D2 — One `migrate:fresh --seed --seeder=` call, not `db:wipe` + `migrate` + `db:seed`.**
`migrate:fresh` already drops **all** tables (not just migrated ones — satisfying "wipe the entire
database"), re-runs every migration, and seeds afterwards in the right order. `--force` is passed down
so the sub-command never re-prompts after our own confirmation, and so a missing SQLite file is created
rather than prompted for. *Alternative considered:* three separate calls for finer progress output —
rejected as more moving parts for cosmetic gain.

**D3 — A separate `DemoSeeder`; `DatabaseSeeder` stays as-is.**
`DatabaseSeeder` is referenced by `README.md` as "creates a demo user + sample data" and encodes the
original acceptance-criteria walkthrough; overwriting it would silently change what
`php artisan db:seed` means and delete a documented fixture. Two seeders with distinct purposes is the
idiomatic Laravel answer (`--class` / `--seeder` exist precisely for this). No test calls `$this->seed()`
(verified), so nothing else depends on `DatabaseSeeder`. *Alternative considered:* fold the demo data
into `DatabaseSeeder` — rejected: it would make every `db:seed` a 4,000-row operation and destroy the
minimal fixture. *Alternative considered:* have `DemoSeeder` `call()` `DatabaseSeeder` — rejected: it
would produce 21 users and a duplicate-ish dataset for no benefit.

**D4 — Explicit loops in the seeder, not nested factory `has()` chains.**
`User::factory()->count(20)->has(Folder::factory()->count(rand(3,5)))` evaluates the count **once**, so
all 20 users would get the *same* number of folders — the requirement explicitly says random *per user*.
Nested `has()` also cannot propagate `user_id` down to grandchildren (`Task` needs both `user_id` and
`task_list_id`), and cannot express "distribute 5–10 lists across 3–5 folders". Explicit per-user loops
composing the existing `inFolder()`/`forTaskList()` states are the correct and clearest tool. This is
still "idiomatic factories + seeder": the factories own the row shape, the seeder owns the composition.

**D5 — Realistic names live in `DemoContent`, not in the factories.**
Factories are shared with the test suite, where `fake()->words(2)` is exactly right (fast, unique-ish,
meaningless). Baking a demo vocabulary into `FolderFactory`/`TaskListFactory` would change what every
existing test creates. `database/seeders/DemoContent.php` (namespace `Database\Seeders`) holds
`array<int, string>` pools for folder names (~8), list names (~30) and task titles (~60), plus small
pure helpers (`pickUnique(array $pool, int $count): array`). It touches no database, so it is unit-
testable and PHPStan-clean. Names are unique **within a user** (folders, lists) and **within a list**
(task titles); repetition across users is fine and realistic.

**D6 — New `TaskFactory` states carry the variety; the seeder chooses weights.**
`withNote()`, `dueToday()`, `overdue()` (1–14 days past), `dueUpcoming()` (1–21 days ahead) and
`completedAt(CarbonInterface $at)`. Rationale: `dueDateStatus()` has three branches plus a
completed-suppresses-overdue rule, and a demo dataset that never exercises them is not a demo. The
existing `completed()` state is left untouched (tests depend on it); `completedAt()` exists so completed
tasks get *spread* timestamps — `completedForList()` orders by `completed_at DESC`, and identical
`now()` values would collapse that ordering to `id DESC`. Weights (`~30%` completed, then of the rest
`~15%` starred, `~35%` with a due date, `~25%` with a note) live as named private constants in
`DemoSeeder` so a reviewer can see and tune them in one place.

**D7 — Guaranteed showcase states per user.**
Pure randomness can leave a user with an empty Starred page or no due-date badges. After the random
pass, each user's Inbox is forced to contain at least one starred, one overdue and one due-today task.
Cheap, deterministic, and it makes "log in as any demo user and the UI is fully populated" true.

**D8 — Positions are assigned explicitly and contiguously, never left at the factory default `0`.**
Folders: `0..n-1` per user. Lists: `0..n-1` within each folder; ungrouped lists `1..n` because the Inbox
owns `0`. Tasks: `0..n-1` per list. This mirrors what `applyOrder()` writes, so the very first
drag-and-drop on demo data behaves exactly like drag-and-drop on real data. All-zero positions would
"work" (reads fall back to `id`) but would misrepresent the system.

**D9 — Lists are distributed round-robin across folders first, then randomly.**
Minimum lists (5) ≥ maximum folders (5), so a first round-robin pass guarantees **no empty folder**;
remaining lists go to random folders, producing uneven, realistic fill. (Empty-folder rendering is
already covered by `tests/Feature/Livewire/Navigation/SidebarTest.php`, so we do not need to seed one.)

**D10 — One transaction per user.**
~3,800 individual inserts on SQLite with auto-commit is tens of seconds; wrapping each user's subtree in
`DB::transaction()` collapses that to a handful of commits and keeps memory bounded. Per-user (rather
than one giant transaction) means a mid-run failure leaves whole, consistent users behind and keeps the
progress bar honest. `DB::` in a seeder is fine — `LayeringTest` scans `app/`, and seeders are a
database concern by definition. *Alternative considered:* `insert()` bulk writes — rejected: loses model
ids needed for child rows and bypasses casts for marginal extra speed.

**D11 — Guard with `ConfirmableTrait` + a stricter callback, not a hand-rolled `if`.**
```php
if (! $this->confirmToProceed('Application In Production!', fn () => ! $this->getLaravel()->environment(['local', 'testing']))) {
    return self::FAILURE;
}
```
This mirrors `migrate:fresh`/`db:wipe` exactly (`--force` escape hatch, "Command Canceled!" output,
non-interactive default = refuse), and the custom callback extends the refusal to `staging` and any
unknown environment, not just `production`. It composes with the *existing*
`DB::prohibitDestructiveCommands(app()->isProduction())` guard, which throws inside `migrate:fresh` in
production even if someone passes `--force` — two independent layers, one of which already exists.

**D12 — The command pre-flights the target and prints it.**
Before wiping, print `connection` + `database` read from `config()` (never from a query), e.g.
`sqlite → /…/database/database.sqlite`. If the driver is `sqlite`, the path is not `:memory:`, and the
file is missing, create it and say so. Cost: ~6 lines; benefit: the command works on a fresh clone and
the dev can see they are not about to wipe a MySQL database they forgot they configured.

**D13 — No `--users` option, no config file.**
`DemoSeeder::run(int $userCount = self::DEFAULT_USER_COUNT)` takes the count as a defaulted parameter,
so `migrate:fresh --seeder=DemoSeeder` (which invokes `run()` with no arguments) gets 20, while tests can
call `run(2)` for a fast, focused run. That gives the testability a CLI option would have given, with no
option parsing and no config surface. YAGNI: if you later want `php artisan db:fresh-seed --users=5`, it is a
`$this->option()` plus resolving the seeder from the container — additive, non-breaking.

**D14 — Command name: `db:fresh-seed`.**
Groups with `db:seed` / `db:wipe`; description makes the destruction explicit: *"Wipe the database,
re-run all migrations and seed realistic demo data (local development only)."* Class:
`App\Console\Commands\SeedDemoDatabaseCommand` (the `Command` suffix matches the project's
suffix-explicit naming convention). Alternatives, both a one-line change if you prefer them:
`db:fresh-seed` (most literal) or `demo:refresh`. **Please pick one at approval time.**

---

## 3. Step Breakdown

### Step 1: `TaskFactory` variety states

- **What:** Give `TaskFactory` the states a realistic demo needs, without changing any existing state.
- **Where:** `database/factories/TaskFactory.php`; `tests/Feature/Database/FactorySmokeTest.php`.
- **How:**
  - Add `withNote(): static` — `note` from a short realistic sentence (accept a `?string` argument so
    the seeder can pass a `DemoContent` note; default to `fake()->sentence()`).
  - Add `dueToday(): static` → `today()`; `overdue(): static` → `today()->subDays(rand(1, 14))`;
    `dueUpcoming(): static` → `today()->addDays(rand(1, 21))`.
  - Add `completedAt(CarbonInterface $at): static` → `is_completed = true`, `completed_at = $at`.
    Do **not** modify `completed()` — `FactorySmokeTest` and several feature tests rely on it.
  - `due_date` casts to `immutable_date`; `Date::use(CarbonImmutable::class)` is active, so `today()`
    already returns a `CarbonImmutable` — keep the declared parameter type `CarbonInterface` for
    PHPStan level 7.
- **Test:** one assertion per new state in `FactorySmokeTest`, each asserting the *behaviour* rather
  than the literal date: `dueToday()` → `dueDateStatus() === 'today'`; `overdue()` → `'overdue'`;
  `dueUpcoming()` → `'upcoming'`; `overdue()->completed()` → `null` (the completed-suppresses-overdue
  rule); `withNote()` → non-empty `note`; `completedAt($t)` → `is_completed` true and `completed_at`
  equals `$t`.
- **Complexity:** Small

### Step 2: `DemoContent` — realistic vocabulary, zero database

- **What:** The name pools and the pure helpers that sample them.
- **Where:** `database/seeders/DemoContent.php` (new); `tests/Unit/Database/DemoContentTest.php` (new).
- **How:**
  - `declare(strict_types=1)`, namespace `Database\Seeders`, `final class DemoContent`.
  - `private const FOLDER_NAMES` (~8: Work, Personal, Home, Travel, Finance, Health, Learning,
    Side Projects), `LIST_NAMES` (~30), `TASK_TITLES` (~60), `NOTES` (~15). All
    `array<int, non-empty-string>`.
  - Public static methods: `folderNames(int $count): array`, `listNames(int $count): array`,
    `taskTitles(int $count): array`, `note(): string`. Each returns *distinct* values, shuffled.
  - Contract to assert and document: the requested count never exceeds the pool
    (max folders 5 ≤ 8; max lists per user 14 ≤ 30; max tasks per list 20 ≤ 60). Throw an
    `InvalidArgumentException` if it ever does, rather than silently returning duplicates or fewer.
  - No `use App\...`, no Eloquent, no `fake()` state beyond shuffling.
- **Test:** returns exactly `n` values; values are unique; repeated calls vary (shuffled); requesting
  more than the pool size throws; requesting `0` returns an empty array.
- **Complexity:** Small

### Step 3: `DemoSeeder` — the dataset

- **What:** The whole demo dataset, built from factories, with every relational and positional
  invariant satisfied.
- **Where:** `database/seeders/DemoSeeder.php` (new); `tests/Feature/Database/DemoSeederTest.php` (new).
- **How:**
  - `class DemoSeeder extends Seeder` with `use WithoutModelEvents;`.
  - Constants (all `private const`, all named): `DEFAULT_USER_COUNT = 20`, `FOLDERS_MIN/MAX = 3/5`,
    `FOLDERED_LISTS_MIN/MAX = 5/10`, `STANDALONE_LISTS_MIN/MAX = 2/4`, `TASKS_MIN/MAX = 10/20`,
    `COMPLETED_CHANCE`, `STARRED_CHANCE`, `DUE_DATE_CHANCE`, `NOTE_CHANCE`.
  - `public function run(int $userCount = self::DEFAULT_USER_COUNT): void` (D13). Create the users in
    one factory call with a `Sequence` assigning `demo{n}@example.com`; `UserFactory` already sets
    `email_verified_at` and the shared `password` hash. Then loop users, and for each wrap the subtree
    in `DB::transaction()` (D10).
  - Per user, in order:
    1. `TaskList::factory()->inbox()->create(['user_id' => $user->id])` (Inbox, position 0).
    2. Folders: `DemoContent::folderNames($n)` → `Folder::factory()->create(['user_id' =>, 'name' =>, 'position' => $i])`.
    3. Foldered lists: pick `5–10` names, distribute round-robin then randomly across the folders (D9);
       create with `TaskList::factory()->inFolder($folder)->create(['name' =>, 'position' => $indexWithinFolder])`.
    4. Standalone lists: `2–4`, `folder_id = null`, positions `1..n` (D8/A2).
    5. Tasks: for **every** list (Inbox included), `10–20` tasks, titles from
       `DemoContent::taskTitles($n)`, positions `0..n-1`, each rolled against the weight constants into
       one of: completed (`completedAt()` at a random point in the last 14 days), starred, due
       (overdue/today/upcoming), noted, or plain. Also spread `created_at`/`updated_at` over the last
       ~60 days so `starredForUser()`'s `created_at DESC` ordering is meaningful (factories run
       unguarded, so timestamp overrides stick).
    6. D7 showcase pass: force one starred, one overdue and one due-today task in the Inbox.
  - Progress + summary via `$this->command?->getOutput()` — **null-safe**, because the seeder is called
    directly (no command) in tests. The seeder prints the final counts (users/folders/lists/tasks); the
    command does not query for them (D1).
  - No lazy loading: hold `$user`, `$folders`, `$lists` in local variables; never traverse
    `$folder->taskLists` (`Model::preventLazyLoading()` is on in local/testing).
- **Test:** `DemoSeederTest` with `RefreshDatabase`, driving `(new DemoSeeder)->run(3)` for speed, plus
  one assertion that `DemoSeeder::run()`'s default is 20 (assert the constant / reflection default,
  not a full 20-user run):
  - counts: 3 users; each user's folders in `[3,5]`; foldered lists in `[5,10]`; standalone
    (non-default, `folder_id = null`) lists in `[2,4]`; tasks per list in `[10,20]`;
  - invariants: exactly one `is_default` list per user, with `folder_id === null` and `position === 0`;
    every task's `user_id` matches its list's `user_id`; every foldered list's `user_id` matches its
    folder's `user_id`; **zero** cross-user references anywhere;
  - positions: contiguous `0..n-1` for folders per user, for lists within each folder, and for tasks
    within each list; ungrouped lists occupy `0..n` with the Inbox at `0`;
  - emails: `demo1@example.com`…`demo3@example.com`, all verified, all authenticate against `password`;
  - variety: each user has ≥1 starred, ≥1 overdue, ≥1 due-today and ≥1 completed task; completed tasks
    do not all share one `completed_at`;
  - integration with the real read path: `TaskListService::inboxFor($user)` returns the seeded Inbox
    **without creating a second row** (proves the factory state matches `createDefaultFor()`), and
    `NavigationService::treeFor($user)` renders folders + ungrouped lists + a non-zero starred count
    without tripping `preventLazyLoading`;
  - idempotency-in-effect: calling `run(1)` twice **does** produce two users (the seeder is additive by
    design) — the reset guarantee belongs to the command, and this test documents where the boundary is.
- **Complexity:** Large

### Step 4: `SeedDemoDatabaseCommand` — the orchestrator + its guards

- **What:** `php artisan db:fresh-seed` — guard, report target, wipe, migrate, seed, report.
- **Where:** `app/Console/Commands/SeedDemoDatabaseCommand.php` (new directory);
  `tests/Feature/Console/SeedDemoDatabaseCommandTest.php` (new).
- **How:**
  - `protected $signature = 'db:fresh-seed {--force : Force the operation to run when not in a local environment}';`
    plus a `$description` that says it destroys data. `use ConfirmableTrait;`.
  - `handle(): int`:
    1. `if (! $this->confirmToProceed('Application In Production!', fn () => ! $this->getLaravel()->environment(['local', 'testing']))) return self::FAILURE;` (D11).
    2. Print the target connection + database from `config()`; create a missing SQLite file (D12).
    3. `$this->call('migrate:fresh', ['--force' => true, '--seed' => true, '--seeder' => DemoSeeder::class]);`
       — propagate a non-zero exit code as `self::FAILURE`.
    4. On success print the login hint (`demo1@example.com` / `password`) and elapsed seconds; warn that
       sessions and API tokens were wiped, so the browser session is gone.
    5. `return self::SUCCESS;`
  - No queries, no models, no services (D1). No registration needed in `bootstrap/app.php` (verified:
    `Application::configure()` calls `withCommands()`).
- **Test:** `SeedDemoDatabaseCommandTest` — **and read R1 before writing it**:
  - *Guard, no execution:* with `$this->app->detectEnvironment(fn () => 'production')` (and again with
    `'staging'`), `$this->artisan('db:fresh-seed')` fails, prints the cancellation, and **the database is
    untouched** (a row created before the call still exists). This test must never reach `migrate:fresh`.
  - *Happy path, one test only:* `$this->artisan('db:fresh-seed --force')->assertSuccessful()`, then assert
    `users` has 20 rows, every user has an Inbox, and `tasks` is non-empty. This test class does **not**
    use `RefreshDatabase`; instead its `tearDown()` runs `migrate:fresh` to hand the shared in-memory
    connection back empty-but-migrated (R1).
  - *Flag surface:* `--force` exists and is documented in `$signature`.
- **Complexity:** Medium

### Step 5: Documentation

- **What:** The command is discoverable without reading source.
- **Where:** `README.md`.
- **How:**
  - Setup §3: after `php artisan db:seed`, add
    `php artisan db:fresh-seed   # wipe + reseed with 20 demo users of realistic data (local only)`.
  - New short subsection "Resetting your local database": what it destroys (**everything**, including
    sessions and Sanctum tokens — you will be logged out), what it creates (the volume table below),
    the login credentials (`demo1@example.com` … `demo20@example.com` / `password`), the `--force`
    behaviour, and the fact that it refuses to run outside `local`/`testing`.
  - State the two seeders' distinct purposes so nobody "fixes" the duplication:
    `db:seed` → `DatabaseSeeder` (minimal walkthrough fixture); `db:fresh-seed` → `DemoSeeder` (bulk demo).
  - Volume table: 20 users · 3–5 folders · 5–10 foldered lists · 2–4 standalone lists · 1 Inbox ·
    10–20 tasks per list ≈ 3,500 tasks.
- **Test:** `composer test` green; a reader can reset their database and log in without opening a PHP
  file.
- **Complexity:** Small

---

## 4. Risk Assessment

### Risks

- **R1 (High) — The command test corrupts the shared in-memory SQLite connection.** Laravel keeps the
  `:memory:` PDO connection alive **across tests** and tracks "already migrated" in a static
  (`RefreshDatabaseState`). A test that actually runs `migrate:fresh` performs DDL (which implicitly
  commits in SQLite, blowing away `RefreshDatabase`'s wrapping transaction) and then **commits ~3,800
  demo rows**. Subsequent tests would inherit that data while `RefreshDatabase` believes the database is
  clean — producing failures far away from the cause, with ordering-dependent flakiness.
- **R2 (Medium) — Seed volume makes the suite slow.** ~3,800 rows in the one full-execution test, plus
  whatever `DemoSeederTest` creates.
- **R3 (Medium) — Silent invariant drift between `TaskListFactory::inbox()` and
  `EloquentTaskListRepository::createDefaultFor()`.** Two places encode "what an Inbox is". If they ever
  disagree, `TaskListService::inboxFor()` would create a *second* Inbox on first page load and the demo
  database would violate the app's core invariant.
- **R4 (Medium) — Someone runs it against a real database.** The whole point of the command is
  destruction; a mis-set `DB_CONNECTION=mysql` pointing at something that matters is one env var away.
- **R5 (Low) — `preventLazyLoading` blows up mid-seed.** A convenience traversal like
  `$folder->taskLists` in the seeder would throw in local/testing. (Freshly-created models are exempt
  from the violation handler, so this can pass in one place and fail in another — genuinely confusing.)
- **R6 (Low) — Unique email collision.** Running `php artisan db:seed` *after* `db:fresh-seed` (or vice
  versa) — mitigated by design (A3: `test@` vs `demoN@` never collide), but worth stating so nobody
  "simplifies" the emails later.
- **R7 (Low) — Orphaned uploads.** `migrate:fresh` wipes `users` but not
  `storage/app/public/profile-photos`, so previously uploaded photos become orphan files. Harmless
  (demo users have `profile_photo_path = null`), but it accumulates.
- **R8 (Low) — Missing SQLite file on a fresh clone.** `db:wipe` (inside `migrate:fresh`) can fail
  before `migrate`'s auto-`touch()` ever runs.
- **R9 (Low) — Randomness makes a test flaky.** Assertions written against a specific count instead of
  the documented range would fail roughly once in N runs.
- **R10 (Low) — PHPStan level 7 friction.** `$this->command` is nullable on `Seeder`; `fake()` returns
  a loosely-typed generator; `const` arrays need `array<int, string>` annotations.

### Mitigations

- **R1:** Split the testing responsibility: `DemoSeederTest` (data correctness) uses `RefreshDatabase`
  and **never** calls `migrate:fresh`; `SeedDemoDatabaseCommandTest` does **not** use `RefreshDatabase`,
  contains exactly **one** executing test, and its `tearDown()` runs `migrate:fresh` so the shared
  connection is handed back empty-and-migrated regardless of test ordering. The guard tests never reach
  execution at all. If the executing test still proves unstable in practice, delete it and rely on the
  guard tests plus `DemoSeederTest` — the command would then be the one genuinely untested line
  (`$this->call('migrate:fresh', ...)`), which is an acceptable, explicitly-recorded trade.
- **R2:** `DemoSeeder::run(int $userCount = 20)` (D13) lets `DemoSeederTest` use 3 users; the one full
  20-user run is confined to the single command test; per-user transactions (D10) keep even that fast.
  If the full run exceeds ~5 s, drop it to a smaller assertion set — do not shard it into more tests.
- **R3:** `DemoSeederTest` asserts through the real read path: `TaskListService::inboxFor($user)` must
  return the seeded row **and** leave the `task_lists` count unchanged. That fails loudly the day the two
  definitions diverge.
- **R4:** Three layers — `ConfirmableTrait` with a local/testing-only callback (D11), the pre-existing
  `DB::prohibitDestructiveCommands(app()->isProduction())` which throws inside `migrate:fresh` even with
  `--force`, and the printed target (D12) that shows the connection and database before anything is
  dropped. The README states plainly that this command is local-only.
- **R5:** No relation traversal in the seeder — every parent is held in a local variable and passed to
  `inFolder()` / `forTaskList()`. Called out in Step 3's "How" and reviewable in one pass.
- **R6:** A3's naming (`test@example.com` vs `demoN@example.com`) plus a README line explaining *why*
  the demo seeder does not reuse `test@example.com`.
- **R7:** Documented in the README note; not engineered around. If it ever matters, a `--clear-photos`
  flag deleting `storage/app/public/profile-photos` is a small follow-up (explicit non-goal here).
- **R8:** D12's pre-flight `touch()` for a non-`:memory:` SQLite path, plus `--force` on the inner
  `migrate:fresh` (which `touch()`es as well, per `MigrateCommand:213`).
- **R9:** Every count assertion is written as a range (`assertGreaterThanOrEqual` /
  `assertLessThanOrEqual`) against the same constants the seeder uses, never a magic number; the
  "guaranteed showcase states" (D7) are the only exact-count assertions, and they are deterministic by
  construction.
- **R10:** Null-safe `$this->command?->…`; `array<int, non-empty-string>` docblocks on the pools;
  `CarbonInterface` parameter types on the new factory states. `composer test` runs PHPStan before
  PHPUnit, so this surfaces on the first run of each step.

### Fallbacks

- **If R1 proves unmanageable**, drop the executing command test entirely (keep the guard tests) and
  rely on `DemoSeederTest` for correctness. Record it in the README's testing note.
- **If the volume is too heavy for comfortable local use** (A1), lower `TASKS_MIN/MAX` to `5/12` — a
  one-line constant change, no structural impact, tests still pass because they assert against the
  constants.
- **If you want the demo dataset to *be* the default**, `DatabaseSeeder::run()` can `call(DemoSeeder::class)`
  later; that is additive and does not invalidate anything here (but it makes every `db:seed` heavy —
  see D3).
- **If `db:fresh-seed` turns out to be the wrong name** (D14), it is a single `$signature` string plus two
  README lines.

---

## 5. Execution Checklist

- [ ] **Step 1:** `TaskFactory` gains `withNote()`, `dueToday()`, `overdue()`, `dueUpcoming()`,
      `completedAt()`; `completed()` and `starred()` untouched; `FactorySmokeTest` extended with one
      behavioural assertion per state (including `overdue()->completed()` → `dueDateStatus() === null`).
      `composer test`.
- [ ] **Step 2:** `database/seeders/DemoContent.php` with folder/list/task/note pools and
      unique-sampling helpers; throws when asked for more than the pool holds;
      `tests/Unit/Database/DemoContentTest.php`. No database access. `composer test`.
- [ ] **Step 3:** `database/seeders/DemoSeeder.php` — `run(int $userCount = 20)`, deterministic
      `demoN@example.com` sequence, per-user transaction, Inbox + 3–5 folders + 5–10 foldered lists
      (round-robin, no empty folder) + 2–4 standalone lists + 10–20 tasks per list, contiguous
      positions, weighted variety, D7 showcase pass, null-safe progress output.
      `tests/Feature/Database/DemoSeederTest.php` with `RefreshDatabase` and `run(3)`, asserting counts
      as ranges, relational/positional invariants, zero cross-user references, and `inboxFor()` not
      creating a second Inbox. `composer test`.
- [ ] **Step 4:** `app/Console/Commands/SeedDemoDatabaseCommand.php` (`db:fresh-seed`) — `ConfirmableTrait`
      with the local/testing callback, target report + SQLite pre-flight, single
      `migrate:fresh --force --seed --seeder=DemoSeeder` call, login hint + timing, exit codes.
      `tests/Feature/Console/SeedDemoDatabaseCommandTest.php` — production **and** staging refusal with
      the database provably untouched, one executing happy-path test, **no `RefreshDatabase`**, and a
      `tearDown()` that runs `migrate:fresh` (R1). `composer test`.
- [ ] **Step 5:** README — Setup §3 line, "Resetting your local database" subsection (what it destroys,
      credentials, `--force`, environment refusal), the two-seeders explanation, and the volume table.
      `composer test`.

**Quality gates:** `composer test` green at the end of every step; `code-reviewer` approval before the
next step begins. `tests/Feature/Architecture/LayeringTest.php` must stay green **without being edited**.
No new migration is created (no schema change).

**Explicit non-goals (do not build):** changes to `DatabaseSeeder`; any change under `app/` other than
the new command; a `--users` CLI option; a `config/demo.php`; profile-photo/storage cleanup; a
`composer demo` script; seeding of soft-deleted rows, two-factor secrets, passkeys or personal access
tokens; any production or staging usage.

---

## 6. Approval decisions

1. **A1** — Confirmed: 10–20 tasks **per list** (≈3,500 tasks total).
2. **A3** — Confirmed: `demo1@example.com` … `demo20@example.com` / `password`;
   `test@example.com` remains exclusive to `DatabaseSeeder` / `php artisan db:seed`.
3. **D14** — Confirmed: command name is `db:fresh-seed`
   (class `App\Console\Commands\SeedDemoDatabaseCommand`).
4. **D3/A5** — Confirmed: `DatabaseSeeder` stays untouched; `DemoSeeder` is a separate class
   used only by `db:fresh-seed`.
5. **R1's fallback** — Standing: if the single executing command test proves flaky during
   implementation, drop it and rely on the guard tests plus `DemoSeederTest` for coverage,
   noting the trade-off in the PR description.
