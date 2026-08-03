# Implementation Plan: Login Entry Point, Inbox Rename, Sidebar Navigation & Profile Photo

**Date:** 04-08-2026
**Plan ID:** 1
**Status:** Draft
**Complexity:** Medium

## 1. Requirements Analysis

### Functional Requirements
- [ ] A guest hitting `/` lands on the login page (`GET /login`).
- [ ] An authenticated user hitting `/` lands on the post-login page.
- [ ] The post-login destination is called and addressed as "Inbox" (`/inbox`, route name `inbox`), replacing `dashboard`.
- [ ] Fortify's post-login / post-registration / post-verification redirect targets `/inbox`.
- [ ] An authenticated user hitting any `guest`-only route (e.g. `/login`) is redirected to `/inbox`.
- [ ] The sidebar shows exactly two primary nav items: **Inbox** (`inbox` icon) and **Starred** (`star` icon).
- [ ] `Starred` is a real, reachable, authenticated page with an honest empty state (no starring backend yet).
- [ ] Desktop and mobile sidebar navigation render from one shared source (no duplicated item markup).
- [ ] The user identity block (name + rounded avatar) appears at the **top** of the sidebar, not the bottom.
- [ ] `users` gains a nullable `profile_photo_path` column.
- [ ] The avatar renders the stored photo when present and falls back to Flux initials when null.
- [ ] The starter-kit `welcome` page is removed; no route/view references `dashboard` afterwards.

### Non-Functional Requirements
- [ ] **No redirect loops.** The guest-redirect target must be explicit and derived from `config('fortify.home')`, not from Laravel's `dashboard`/`home` route-name heuristic.
- [ ] **Deployable after every step.** No step may leave a dangling `route('dashboard')` / `route('starred')` reference.
- [ ] **Layering.** No business logic in Blade, Livewire or controllers. Controllers are thin. Photo-URL derivation is a pure read-model concern on the model; future upload logic goes to a Service.
- [ ] **Security.** `profile_photo_path` must NOT be mass-assignable. `/inbox` and `/starred` stay behind `auth` + `verified`. All output escaped with `{{ }}`.
- [ ] **Schema discipline.** Column added by migration; factory updated; seeder explicitly reviewed.
- [ ] **Quality gates.** `composer test` (pint --test, phpstan level 7, `php artisan test`) green after every step.
- [ ] **No new queries.** The sidebar must not introduce extra DB round-trips (`auth()->user()` is guard-cached; resolve it once per layout render).
- [ ] **Nav extensibility.** Nav structure must accept Folders/Lists groups later without restructuring.

## 2. Architecture Review

### Existing Codebase Patterns
- Laravel 13.17, PHP 8.3+ constraint (`composer.json`), Livewire 4.1, Flux 2.13.1 (free tier), Fortify 1.37.2, Blaze 1.0.
- `app/` currently has **no** `Services/`, `Repositories/`, `Exceptions/` directories — only `Providers`, `Livewire`, `Http/Controllers` (abstract base only), `Actions/Fortify`, `Concerns`, `Models`. This change set needs **no** repository or service: it introduces no queries and no business rules.
- Routing: `routes/web.php` uses `Route::view()` for static pages; `routes/settings.php` uses `Route::livewire()` for interactive pages. There are currently **zero** concrete controllers.
- Layout: `resources/views/layouts/app.blade.php` → `<x-layouts::app.sidebar>` → `resources/views/layouts/app/sidebar.blade.php`. `resources/views/layouts/app/header.blade.php` is an **unused alternative layout** shipped by the starter kit (verified: nothing references `app.header`).
- Tests: PHPUnit class-style (not Pest), `RefreshDatabase`, feature tests under `tests/Feature`.
- Static analysis: Larastan level 7 over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`.
- `User` uses PHP attributes for mass assignment: `#[Fillable(['name','email','password'])]` and `#[Hidden([...])]`, plus a `@property` docblock block that phpstan relies on.

### Affected Areas
Full grep result for `dashboard` (excluding `vendor/`, `node_modules/`, `storage/framework/views/` compiled caches):

| File | Reference |
|---|---|
| `routes/web.php:5,8` | `home` view route + `dashboard` view route |
| `config/fortify.php:76` | `'home' => '/dashboard'` |
| `resources/views/dashboard.blade.php` | whole file (placeholder skeleton) |
| `resources/views/welcome.blade.php:26,29` | `route('dashboard')` link + label |
| `resources/views/layouts/app/sidebar.blade.php:9,15,15` | brand href, item href, `routeIs()` |
| `resources/views/layouts/app/header.blade.php:10,13,13,50,56,56` | brand + navbar item + mobile sidebar item |
| `resources/views/components/passkey-verify.blade.php:36` | hardcoded JS fallback `'/dashboard'` |
| `app/Livewire/Settings/Profile.php:59` | `route('dashboard', absolute: false)` |
| `tests/Feature/DashboardTest.php` | whole file |
| `tests/Feature/Auth/AuthenticationTest.php:32` | `assertRedirect(route('dashboard'))` |
| `tests/Feature/Auth/RegistrationTest.php:37` | idem |
| `tests/Feature/Auth/EmailVerificationTest.php:50,83` | idem + `?verified=1` |
| `tests/Feature/ExampleTest.php:14` | `route('home')` `assertOk()` — **will break** once `/` redirects |
| `storage/framework/views/*` | compiled Blade cache — resolved by `php artisan view:clear`, not edited |

Also affected: `resources/views/components/desktop-user-menu.blade.php`, `database/migrations/0001_01_01_000000_create_users_table.php` (untouched — new migration instead), `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php`.

### Reusable Components
- `User::initials()` already exists — reuse as the avatar fallback, do not reimplement.
- `flux:avatar` (verified in `vendor/livewire/flux/stubs/resources/views/flux/avatar/index.blade.php`) supports `src`, `circle`, `size`, `initials`, `name`. It renders `<img src>` when `src` is set, otherwise initials — **the null-fallback is built in**, no conditional needed in Blade.
- `flux:sidebar.profile` (verified) accepts `avatar` (string → forwarded as `src`), `circle`, `name`, `initials`; its root has `has-data-[circle=true]:rounded-full`.
- `flux:profile` (mobile dropdown trigger) accepts the same `avatar`/`circle`/`name`/`initials` props.
- Icons `inbox` and `star` both exist (`vendor/livewire/flux/stubs/resources/views/flux/icon/{inbox,star}.blade.php`).
- `Fortify::redirects('login')` resolves `config('fortify.redirects.login') ?? config('fortify.home')` — use it as the single source of truth for auth redirects.
- `config/filesystems.php` already defines a `public` disk (`storage/app/public`, url `APP_URL/storage`) — the correct disk for future photo storage.

### Architecture Decision

**D1 — Entry point: thin invokable `HomeController` on `/`, `welcome` view deleted.**
`/` stays named `home` and dispatches: authenticated → `route('inbox')`, guest → `route('login')`.
Rejected alternatives: (a) `Route::redirect('/', '/login')->name('home')` — an authenticated user would bounce `/` → `/login` → guest middleware → `/` → `/login`, an **infinite loop** unless the guest redirect is reconfigured; also two hops. (b) Renaming the `home` route onto the login URL — confusing (`route('home')` and `route('login')` pointing at the same page) and breaks the semantic slot `/` will need later. The controller is a routing concern only (one `redirect()` call, no domain logic), so it satisfies "thin controller".

**D2 — Explicit guest-redirect configuration (critical).**
`Illuminate\Auth\Middleware\RedirectIfAuthenticated::defaultRedirectUri()` scans for a route named `dashboard`, then `home`, then falls back to `/`. After renaming `dashboard` → `inbox`, that heuristic would resolve to `route('home')` = `/`, which for a guest-only route means `/login` → `/` → `/login`. We therefore pin it explicitly in `FortifyServiceProvider`:
`RedirectIfAuthenticated::redirectUsing(fn () => Fortify::redirects('login'));`
This makes `config/fortify.php` `home` the single source of truth for both post-login and already-authenticated redirects, and removes all dependency on route-name magic. This is bootstrapping configuration, not business logic, so it belongs in the provider.

**D3 — Rename `dashboard` → `inbox` fully (route name, URI, view, config, tests).**
Recommended over "keep route name, relabel UI". The product concept is Inbox; keeping a `dashboard` name would mean every future reference (`route('dashboard')` for a page titled Inbox) carries a permanent lie, and the framework heuristic above silently couples behaviour to that exact name. The rename is a mechanical ~14-file change, fully covered by the existing test suite, done once, now, while the surface is tiny. Cost of deferring rises with every new reference.

**D4 — Controllers instead of `Route::view()` for `inbox` and `starred`.**
`InboxController` and `StarredController` are thin invokables returning a view. Justification: Inbox will need task data within the next feature slice (`docs/project-base.md` M3/S6), so `Route::view()` would be replaced almost immediately; the repo's architecture rules state the request flow goes through a controller; and `HomeController` is required anyway, so we are not introducing a new concept. Alternative considered: keep `Route::view()` for both (lower churn now, guaranteed churn later) — rejected.

**D5 — Starred is a real route with an empty state, not a disabled link.**
A nav item that 404s, or is greyed out with no destination, is a half-implemented feature and violates the "deployable state" rule. A real `/starred` page saying "No starred tasks yet" is honest, complete for what it claims, and when starring ships only the controller/view body changes — the nav, route and tests stay.

**D6 — Extract the shared sidebar nav to one anonymous Blade component.**
`resources/views/components/app-sidebar-nav.blade.php` holds the `flux:sidebar.group` + two `flux:sidebar.item`s and is included by both `layouts/app/sidebar.blade.php` (desktop) and `layouts/app/header.blade.php` (mobile drawer) — both use the identical `flux:sidebar.item` API, so this is a genuine DRY win with no abstraction cost. The `flux:navbar.item` variant inside `header.blade.php` stays inline: unifying two different Flux component APIs behind a variant prop would be premature abstraction (YAGNI).
*Open option for the user:* `layouts/app/header.blade.php` is dead code (nothing renders it). Deleting it would remove the duplication entirely and leave the codebase simpler. Default in this plan is **keep and sync**, because it is a starter-kit-provided alternative layout the user may want. Say the word and it becomes a one-line step.
Future Folders/Lists slot in as an additional `flux:sidebar.group` inside this component (or a sibling component), with no change to the layouts.

**D7 — `profile_photo_path` + `profilePhotoUrl` accessor on `User`; upload UI deferred.**
Schema and display land now; the upload control does not. Rationale: an upload needs `storage:link`, disk selection, mime/size validation, old-file deletion, and a security review — materially larger than "show identity in the sidebar", and none of it is needed for the requested outcome. Display-with-fallback is complete and deployable on its own.
URL derivation lives on the model as a read-only accessor (`Storage::disk('public')->url($this->profile_photo_path)`, `null` when unset). This is a deliberate, documented deviation from "keep infrastructure out of models": it is pure data derivation with no queries, no workflow and no business rule, and Laravel/Jetstream convention. The stricter alternative — an injected `ProfilePhotoService` reached from Blade via `@inject` or a view composer — adds indirection and hidden magic for a nullable string, and is rejected under KISS/YAGNI. When upload lands, the *write* side (validate, store, delete previous, update model) goes into `App\Services\ProfilePhotoService` and the accessor stays a pure read.
`profile_photo_path` is deliberately **excluded** from `#[Fillable]` (mass-assignment safety).

**D8 — Identity block placement.**
`flux:sidebar.header` keeps the app brand + collapse toggle (it is the app-home affordance). The user menu moves out of the sidebar footer and becomes the first element below the header, above `flux:sidebar.nav`. This reads as "top of sidebar" while preserving the brand and Flux's collapse behaviour.

## 3. Step Breakdown

### Step 1: Make login the entry point and pin the authenticated-redirect target
- **What:** `/` dispatches guests to login and authenticated users to the post-login page; the guest-redirect target becomes explicit; the starter-kit welcome page is deleted.
- **Where:**
  - New `app/Http/Controllers/HomeController.php` (invokable, extends `App\Http\Controllers\Controller`).
  - `routes/web.php` — replace `Route::view('/', 'welcome')->name('home')` with `Route::get('/', HomeController::class)->name('home')`.
  - Delete `resources/views/welcome.blade.php`.
  - `app/Providers/FortifyServiceProvider.php` — add `configureRedirects()` called from `boot()`.
  - `tests/Feature/ExampleTest.php` → rename to `tests/Feature/HomeRedirectTest.php`.
- **How:**
  - `HomeController::__invoke(): RedirectResponse` returns `redirect()->route(Auth::check() ? 'dashboard' : 'login')`. (Target becomes `inbox` in Step 2 — at this point `dashboard` still exists, so the app stays green.)
  - `configureRedirects()`: `RedirectIfAuthenticated::redirectUsing(fn () => Fortify::redirects('login'));` — resolves to `config('fortify.home')`, so Step 2 needs no further change here. See D2.
  - `declare(strict_types=1);`, typed return, constructor-free.
  - Run `php artisan view:clear` (compiled welcome view is cached in `storage/framework/views`).
- **Test:** `tests/Feature/HomeRedirectTest.php` — (a) guest `GET /` redirects to `route('login')`; (b) authenticated verified user `GET /` redirects to the post-login route; (c) **regression:** authenticated user `GET /login` redirects to the post-login route and does **not** loop (assert a single redirect to the expected path). Full suite must stay green.
- **Complexity:** Small

### Step 2: Rename `dashboard` → `inbox` end to end
- **What:** Route name, URI, view, Fortify home and every reference become `inbox`. No `dashboard` string remains outside `vendor/` and compiled caches.
- **Where:**
  - `routes/web.php` — `Route::get('inbox', InboxController::class)->name('inbox')` inside the existing `auth`+`verified` group.
  - New `app/Http/Controllers/InboxController.php` (thin invokable returning `view('inbox')`).
  - `resources/views/dashboard.blade.php` → `resources/views/inbox.blade.php`.
  - `config/fortify.php:76` — `'home' => '/inbox'`.
  - `app/Http/Controllers/HomeController.php` — target `inbox`.
  - `resources/views/layouts/app/sidebar.blade.php` (3 refs), `resources/views/layouts/app/header.blade.php` (6 refs).
  - `resources/views/components/passkey-verify.blade.php:36` — replace hardcoded `'/dashboard'` with `'{{ config('fortify.home') }}'`.
  - `app/Livewire/Settings/Profile.php:59`.
  - `tests/Feature/DashboardTest.php` → `tests/Feature/InboxTest.php`; `tests/Feature/Auth/AuthenticationTest.php:32`, `RegistrationTest.php:37`, `EmailVerificationTest.php:50,83`; `tests/Feature/HomeRedirectTest.php`.
- **How:**
  - Replace the placeholder skeleton in `inbox.blade.php` with `<x-layouts::app :title="__('Inbox')">` plus a `flux:heading` "Inbox" and a `flux:text` empty state. Decide in this step whether `resources/views/components/placeholder-pattern.blade.php` becomes unused; if so, delete it (leave the codebase simpler) — it has no other references today.
  - Keep the `auth` + `verified` middleware group exactly as-is.
  - Run `php artisan view:clear` and `php artisan config:clear` (fortify config is cached).
  - Final check: `rg -n "dashboard" --glob '!vendor' --glob '!node_modules' --glob '!storage'` returns only `docs/` prose.
- **Test:** `tests/Feature/InboxTest.php` — guest `GET /inbox` redirects to login; authenticated verified user gets 200 and sees "Inbox". Updated auth tests assert `route('inbox')` after login/registration/verification. Full suite + phpstan + pint green.
- **Complexity:** Medium

### Step 3: Add the Starred page
- **What:** An authenticated `/starred` page with an honest empty state, following the exact Inbox pattern.
- **Where:** New `app/Http/Controllers/StarredController.php`; new `resources/views/starred.blade.php`; `routes/web.php` (same `auth`+`verified` group); new `tests/Feature/StarredTest.php`.
- **How:** Mirror `InboxController`/`inbox.blade.php` exactly — thin invokable returning `view('starred')`, view renders `<x-layouts::app :title="__('Starred')">` with a `flux:heading` "Starred" and empty-state text such as "No starred tasks yet." No model, no query, no service (nothing to star yet). See D5.
- **Test:** `tests/Feature/StarredTest.php` — guest redirected to login; authenticated verified user gets 200 and sees "Starred".
- **Complexity:** Small

### Step 4: Extract and replace the sidebar navigation
- **What:** One shared nav component listing Inbox and Starred, used by the desktop sidebar and the mobile drawer; the "Platform" group heading and the single Dashboard item are gone.
- **Where:** New `resources/views/components/app-sidebar-nav.blade.php`; `resources/views/layouts/app/sidebar.blade.php` (replace lines 13-19); `resources/views/layouts/app/header.blade.php` (replace lines 54-60 and update the desktop `flux:navbar` items on lines 12-16 in place); new `tests/Feature/SidebarNavigationTest.php`.
- **How:**
  - Component body: a single `flux:sidebar.nav` containing
    `<flux:sidebar.item icon="inbox" :href="route('inbox')" :current="request()->routeIs('inbox')" wire:navigate>{{ __('Inbox') }}</flux:sidebar.item>`
    and the same with `icon="star"` / `starred`. Both icons verified present in Flux 2.13.
  - Drop the `:heading="__('Platform')"` group wrapper — with two top-level smart lists a heading adds noise. Future Folders/Lists arrive as their own `flux:sidebar.group` inside this component (D6).
  - Wrap the item list in a container carrying `data-nav="primary"` (or similar) so tests can assert ordering in Step 5 without depending on Flux's internal classes.
  - Leave the external Repository/Documentation links in the footer nav untouched for now (starter-kit links; removing them is a separate cosmetic decision).
  - `php artisan view:clear` (Blaze compiles/folds these components).
- **Test:** `tests/Feature/SidebarNavigationTest.php` — authenticated user on `/inbox` sees links to `route('inbox')` and `route('starred')` and does **not** see "Dashboard" or "Platform"; visiting `/starred` marks the Starred item current (assert on the rendered `data-current`/`aria-current` output of the Starred item).
- **Complexity:** Small

### Step 5: Add `profile_photo_path` (schema, model, factory, seeder)
- **What:** Nullable photo column, a read-only URL accessor, factory support, seeder reviewed.
- **Where:** New `database/migrations/2026_08_04_000000_add_profile_photo_path_to_users_table.php`; `app/Models/User.php`; `database/factories/UserFactory.php`; `database/seeders/DatabaseSeeder.php` (review only).
- **How:**
  - Migration `up()`: `$table->string('profile_photo_path', 2048)->nullable()->after('password');` — `down()`: `$table->dropColumn('profile_photo_path');`. Non-indexed, so the 2048 length is safe on MySQL utf8mb4.
  - `User`: add `@property string|null $profile_photo_path` to the docblock (phpstan level 7 relies on it). **Do not** add it to `#[Fillable]`. Add
    `protected function profilePhotoUrl(): Attribute` returning `Attribute::get(fn (): ?string => $this->profile_photo_path ? Storage::disk('public')->url($this->profile_photo_path) : null)`. Read-only, no queries, no workflow (D7).
  - `UserFactory::definition()`: add explicit `'profile_photo_path' => null`. Add state `withProfilePhoto(string $path = 'profile-photos/test-photo.jpg'): static` so display tests have a deterministic fixture.
  - `DatabaseSeeder`: **explicitly reviewed and intentionally unchanged** — the seeded "Test User" has no photo, which is the correct default for a nullable column and exercises the initials fallback in local dev. (Recording this decision satisfies the "every DB data change → update seeder + factory" rule.)
  - Run `php artisan migrate`.
- **Test:** `tests/Unit/UserProfilePhotoUrlTest.php` — accessor returns `null` when the column is null; returns the `public`-disk URL when a path is set (use `Storage::fake('public')` / assert the URL ends with the path). Feature-level: `User::factory()->withProfilePhoto()->create()` persists the path. Migration rollback works (`migrate:rollback` in CI or a `RefreshDatabase` run).
- **Complexity:** Small

### Step 6: Move the user identity block to the top of the sidebar with a rounded avatar
- **What:** Name + rounded profile picture at the top of the sidebar, photo when set, initials when not, in both desktop and mobile chrome.
- **Where:** `resources/views/components/desktop-user-menu.blade.php`; `resources/views/layouts/app/sidebar.blade.php` (move `<x-desktop-user-menu>` from line 33 to just after `flux:sidebar.header`, drop the now-pointless `flux:spacer` positioning if it only served the footer menu, and update the mobile `flux:profile`/`flux:avatar` on lines 43-55); `resources/views/layouts/app/header.blade.php` (line 44 usage stays, inherits the component change).
- **How:**
  - `desktop-user-menu.blade.php`: resolve the user once at the top (`@php $user = auth()->user(); @endphp`) instead of six `auth()->user()` calls, then
    `<flux:sidebar.profile :name="$user->name" :initials="$user->initials()" :avatar="$user->profilePhotoUrl" circle icon:trailing="chevrons-up-down" data-test="sidebar-menu-button" />`
    and in the dropdown body `<flux:avatar :name="$user->name" :initials="$user->initials()" :src="$user->profilePhotoUrl" circle />`. Flux renders `<img>` when the value is non-null and initials otherwise — **no conditional in Blade** (verified in `flux/avatar/index.blade.php`).
  - Mirror the same `:src` + `circle` on the mobile `flux:profile` and `flux:avatar` inside `sidebar.blade.php`.
  - Keep `data-test="sidebar-menu-button"` and `data-test="logout-button"` intact — existing tests and future ones anchor on them.
  - `php artisan view:clear` (Blaze memoises `flux:avatar`; stale compiled views are the likeliest source of "my avatar didn't change").
- **Test:** Extend `tests/Feature/SidebarNavigationTest.php` — (a) a user without a photo renders their initials and no `<img`; (b) a user created with `withProfilePhoto()` renders an `<img` whose `src` contains the stored path; (c) `assertSeeInOrder(['data-test="sidebar-menu-button"', 'data-nav="primary"'], false)` proves the identity block precedes the nav. Full suite green.
- **Complexity:** Medium

## 4. Risk Assessment

### Risks
- **R1 (High) — Infinite redirect loop.** Removing the `dashboard` route changes what `RedirectIfAuthenticated::defaultRedirectUri()` resolves to (`dashboard` → `home` → `/`). With `/` redirecting guests to `/login`, an authenticated user hitting `/login` could ping-pong forever. This is the single highest-severity risk in the plan.
- **R2 (Medium) — Missed `dashboard` reference.** Fourteen call sites across routes, config, views, a Livewire component, an Alpine string and five test files. A miss produces a `RouteNotFoundException` at runtime, and the Alpine one (`passkey-verify.blade.php`) is invisible to `route()`-based checks and to the test suite unless passkeys are exercised.
- **R3 (Medium) — Stale compiled Blade/Blaze views.** `storage/framework/views/` contains compiled copies referencing `route('dashboard')`. Blaze additionally folds/memoises Flux components at compile time, so avatar and nav changes can appear not to take effect.
- **R4 (Low/Medium) — Fortify view/redirect coupling.** `config('fortify.home')` is consumed by login, registration, email verification and password confirmation. Changing it affects four flows at once; three existing test files assert against it.
- **R5 (Low) — `flux:sidebar.profile` layout at the top of the sidebar.** The component is styled for a sidebar footer (`[ui-dropdown>&]:w-full`, collapsed-desktop rules); the dropdown currently opens `position="bottom" align="start"`. At the top of the sidebar the dropdown direction is still fine, but collapsed-desktop truncation should be eyeballed.
- **R6 (Low) — phpstan level 7 on the new accessor.** `Storage::disk('public')->url()` and the `Attribute` return type must be annotated precisely or level 7 will complain; the `@property` docblock on `User` must be extended or `$user->profile_photo_path` will be flagged.
- **R7 (Low) — Deleting `welcome.blade.php` / `placeholder-pattern`.** Only `ExampleTest` and `dashboard.blade.php` reference them, but deletion is irreversible without git — **and this project has no git repository initialised**.

### Mitigations
- **R1:** Step 1 pins `RedirectIfAuthenticated::redirectUsing(fn () => Fortify::redirects('login'))` *before* the rename, making `config/fortify.php` the single source of truth, and adds an explicit regression test that an authenticated user hitting `/login` lands on the post-login route in exactly one hop.
- **R2:** Step 2 ends with a repo-wide `rg "dashboard"` gate excluding `vendor/`, `node_modules/`, `storage/`; the only permitted survivors are `docs/` prose. `passkey-verify.blade.php` is listed explicitly in the step's file list and switches to `config('fortify.home')` so it can never drift again.
- **R3:** `php artisan view:clear` (+ `config:clear`) is an explicit action in Steps 1, 2, 4 and 6. Note that `composer test` already runs `config:clear` but **not** `view:clear`.
- **R4:** All four Fortify-dependent test files are updated inside Step 2, in the same commit as the config change, so the suite either passes wholly or fails loudly.
- **R5:** Visual check in a browser at the end of Step 6 at desktop, collapsed-desktop and mobile widths; the markup move is CSS-only and trivially revertible.
- **R6:** Add `@property string|null $profile_photo_path` and a `@return Attribute<string|null, never>`-style annotation; run `composer types:check` as part of Step 5's gate.
- **R7:** **Recommend initialising git before Step 1** (`git init` + initial commit). This is currently the largest process risk in the plan — six steps of destructive edits with no undo. If the user prefers not to, Steps 1 and 2 should move the files aside rather than delete them.

### Fallbacks
- If the guest-redirect behaviour misbehaves in a way the tests do not capture, fall back to keeping a `Route::redirect('dashboard', '/inbox')` alias for one release — it satisfies the framework heuristic *and* any bookmarked URL, at the cost of one dead route line.
- If extracting the shared nav component (Step 4) fights Blaze's compile-time folding, fall back to duplicating the two nav items inline in both layouts for now (the pre-existing situation) and revisit — or take the cleaner exit and delete the unused `layouts/app/header.blade.php` entirely.
- If `flux:sidebar.profile` at the top of the sidebar looks wrong when collapsed, fall back to rendering a plain `flux:avatar circle` + name row inside `flux:sidebar.header` and keeping the dropdown menu where it is.
- If the `profilePhotoUrl` accessor is judged too much infrastructure in the model during review, the escape hatch is a `App\Services\ProfilePhotoService::url(User $user): ?string` plus a `<x-user-avatar :user="$user" />` component that resolves it via the container — a contained change touching two files.

## 5. Execution Checklist

- [ ] **Step 0 (recommended):** initialise git and commit the current state before any edits.
- [ ] **Step 1:** `HomeController` on `/`, delete `welcome.blade.php`, pin `RedirectIfAuthenticated::redirectUsing()` in `FortifyServiceProvider`, replace `ExampleTest` with `HomeRedirectTest` (incl. the `/login` no-loop regression test). Run `composer test`.
- [ ] **Step 2:** Rename `dashboard` → `inbox` across routes, `InboxController`, `inbox.blade.php`, `config/fortify.php`, both layouts, `passkey-verify.blade.php`, `Settings/Profile.php` and all five test files; `view:clear` + `config:clear`; repo-wide `rg "dashboard"` gate. Run `composer test`.
- [ ] **Step 3:** `StarredController`, `starred.blade.php`, `/starred` route, `StarredTest`. Run `composer test`.
- [ ] **Step 4:** Extract `components/app-sidebar-nav.blade.php` with Inbox + Starred; wire into both layouts; `SidebarNavigationTest`. Run `composer test`.
- [ ] **Step 5:** Migration for `profile_photo_path`, `User` docblock + `profilePhotoUrl` accessor (not fillable), `UserFactory` null default + `withProfilePhoto()` state, seeder reviewed unchanged; unit test for the accessor. Run `php artisan migrate` then `composer test`.
- [ ] **Step 6:** Move the identity block to the top of the sidebar, rounded avatar with `src` + initials fallback in desktop and mobile chrome; extend `SidebarNavigationTest` with photo/initials/order assertions; `view:clear`; visual check at three breakpoints. Run `composer test`.
- [ ] **Post-plan follow-ups (not in scope):** profile photo upload UI in `app/Livewire/Settings/Profile.php` backed by `App\Services\ProfilePhotoService` (+ `storage:link`, mime/size validation, old-file cleanup); decide whether to delete the unused `layouts/app/header.blade.php`; decide the fate of the starter-kit Repository/Documentation footer links; Folders/Lists nav group.
