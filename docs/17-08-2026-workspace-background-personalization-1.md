# Implementation Plan: Workspace Background Personalization

**Date:** 17-08-2026
**Plan ID:** 1
**Status:** Draft
**Complexity:** Medium

## 1. Requirements Analysis

### Functional Requirements
- [ ] A user can choose a workspace background from a fixed set of **types**: Flat color, Image, Gradient.
- [ ] The chosen background is applied to the workspace canvas, and `.workspace-header` and `.task-composer` are visually adapted (readable overlay/tint derived from the same background) rather than left mismatched.
- [ ] Each background **type** can be independently enabled/disabled in the database by an operator (no admin UI required in this phase — direct DB/seeder toggle is acceptable for MVP).
- [ ] Disabling a type only hides it from the type picker for **new selections**; users who already selected a now-disabled type keep seeing/using it unchanged.
- [ ] The background option is managed from the existing Profile settings modal (`app-shell.tsx`), as a new section alongside "Personal details" and "Change password".
- [ ] The system must be structurally ready to add a 4th+ background type later (e.g. "Pattern", "Video") without restructuring the schema or the service contract.

### Non-Functional Requirements
- [ ] No visual regression for existing users: with no preference set, the workspace must render exactly as it does today (hard-coded colors become the default fallback).
- [ ] Background config validation must be per-type and extensible (Open/Closed — adding a type must not require editing a large switch/if-chain across the codebase).
- [ ] Persisted server-side (on the user), not client-only (cookie/localStorage) — consistent with §7 research: no existing client-persistence convention exists, and this preference must be available on first paint via Inertia shared props to avoid a flash of unstyled background.
- [ ] Uploaded background images follow the same storage/validation conventions already used for profile photos (size/mime limits).
- [ ] Authorization: a user can only update their own background (mirrors `ProfileController`/`FolderPolicy`-style ownership checks — no new roles needed for this phase).
- [ ] Web and API stay in sync per the architecture doc — the mutation goes through a shared `WorkspaceBackgroundService`, and web/API controllers stay thin wrappers.

## 2. Architecture Review

### Existing Codebase Patterns
- No settings *page* exists — profile editing is a modal inside `resources/js/layouts/app-shell.tsx`, backed by `ProfileController::update()` → `AccountService::updateProfile()` → `UserRepositoryInterface::save()`. New background settings follow this exact modal-section + Service + Repository chain.
- Contract → Eloquent Repository → Service is the enforced pattern (`LayeringTest`), e.g. `FolderRepositoryInterface` / `EloquentFolderRepository` / `FolderService`, bound in `app/Providers/RepositoryServiceProvider.php`.
- Additive user columns already have precedent: `2026_08_04_000000_add_profile_photo_path_to_users_table.php` plus a computed `profilePhotoUrl()` accessor on `User`. The image background type reuses this exact storage/accessor convention.
- No dark/light theme system and no client-persisted preference convention exist yet — this feature is the first "visual preference," so it must be designed server-side rather than imitating a (nonexistent) client pattern.

### Affected Areas
- **Database**: new `workspace_background_options` lookup table (type catalog + `enabled` flag) + two new nullable columns on `users` (chosen option + JSON config).
- **Backend**: new Repository/Service pair, a domain exception, a Form Request, a Web controller action (+ matching API action for parity), and a shared Inertia prop.
- **Frontend**: `app-shell.tsx` (apply background via CSS custom properties, add the new modal section or extract a component), `resources/css/app.css` (`.workspace-header`/`.task-composer` become variable-driven with today's colors as the fallback), a new small background-picker component.
- **Not affected**: Policies/roles (no new authorization concept needed — ownership check only), API resource shapes for Folders/Lists/Tasks.

### Reusable Components
- `AccountService` / `UserRepositoryInterface::save()` pattern → same shape for the new preference service.
- `profile_photo_path` + `profilePhotoUrl()` accessor pattern → reused verbatim for the Image background type's storage/URL resolution.
- `UpdateProfileRequest` + `app/Concerns/ProfileValidationRules.php` trait pattern → mirrored as `WorkspaceBackgroundValidationRules` shared between Web and API requests.
- Existing `Dialog`/`profile-modal__section` markup in `app-shell.tsx` → new section, no new dialog primitive needed.

### Architecture Decision
Model background **types** as rows in a small lookup table (`workspace_background_options`: `key`, `type`, `label`, `enabled`, `sort_order`) rather than a hard-coded enum. This is what makes the "prepare for more options" and "hide a type without affecting existing users" requirements cheap: enabling/disabling is a data change, not a code change, and a disabled type simply gets excluded from the *selectable* list while still being resolvable for users already on it. The user's actual choice lives on `users` as `workspace_background_option_id` (FK, nullable) + `workspace_background_config` (JSON, nullable) — the FK identifies *which type*, the JSON holds the type-specific value (hex color / image path / gradient stops). Per-type validation is done via a small Strategy interface (`BackgroundConfigValidator`) with one implementation per type key, so a new type only adds one new validator class + one new lookup row, honoring Open/Closed. No new admin role/policy is introduced in this phase — toggling `enabled` is done via seeder/tinker, matching the "start to build this" scope; a real admin UI is called out as a future step, not built here.

## 3. Step Breakdown

### Step 1: Schema — background option catalog + user preference columns
- **What:** Migrations for `workspace_background_options` (catalog) and two new nullable columns on `users`; model, factory, and seeder for the catalog seeded with the 3 initial types.
- **Where:**
  - `database/migrations/2026_08_17_XXXXXX_create_workspace_background_options_table.php`
  - `database/migrations/2026_08_17_XXXXXX_add_workspace_background_to_users_table.php`
  - `app/Models/WorkspaceBackgroundOption.php`
  - `database/factories/WorkspaceBackgroundOptionFactory.php`
  - `database/seeders/WorkspaceBackgroundOptionSeeder.php` (called from `DatabaseSeeder.php`)
  - `database/factories/UserFactory.php` (no default background — must render like today)
- **How:** `workspace_background_options` columns: `id`, `key` (string, unique — `flat_color` / `image` / `gradient`), `type` (string — currently equal to `key`, kept separate so future types can share a rendering `type` while having a distinct catalog `key`, e.g. multiple curated gradients), `label` (string), `enabled` (boolean, default `true`), `sort_order` (unsigned int, default `0`), timestamps. `users` gets `workspace_background_option_id` (nullable FK, `nullOnDelete()`) and `workspace_background_config` (nullable `json`). Seeder inserts the 3 rows with fixed `key`s so application code can reference them by key, not by fragile numeric id.
- **Test:** `php artisan migrate:fresh --seed` succeeds; a Unit/Feature test asserts the 3 seeded rows exist with `enabled = true` and expected `key`s; `UserFactory` output still has null background columns (no regression to existing factories/tests).
- **Complexity:** Small

### Step 2: Domain layer — Repository, Service, per-type validation
- **What:** `WorkspaceBackgroundOptionRepositoryInterface` (+ Eloquent impl) to list enabled options and resolve by key; `WorkspaceBackgroundService` to compute the user-visible option list and to update a user's selection; a `BackgroundConfigValidator` contract with one implementation per type (`FlatColorConfigValidator`, `ImageConfigValidator`, `GradientConfigValidator`); a domain exception for invalid/disabled selections.
- **Where:**
  - `app/Repositories/Contracts/WorkspaceBackgroundOptionRepositoryInterface.php`
  - `app/Repositories/EloquentWorkspaceBackgroundOptionRepository.php`
  - `app/Services/WorkspaceBackgroundService.php`
  - `app/Services/BackgroundValidators/BackgroundConfigValidator.php` (interface) + `FlatColorConfigValidator.php` / `ImageConfigValidator.php` / `GradientConfigValidator.php`
  - `app/Exceptions/InvalidBackgroundSelectionException.php` (extends the project's `DomainException` base)
  - `app/Providers/RepositoryServiceProvider.php` (bind the new repository interface)
- **How:** `WorkspaceBackgroundService::availableOptionsFor(User $user)` returns enabled options **plus** the user's currently selected option even if disabled (so the UI can still show "your current background" without offering it to others). `WorkspaceBackgroundService::updateSelection(User $user, string $optionKey, array $config)`: resolves the option by key, throws `InvalidBackgroundSelectionException` if the key doesn't exist, or if it's disabled **and** isn't already the user's current selection; picks the matching `BackgroundConfigValidator` by the option's `type` (a small `key => validator` map injected or resolved via the container — Open/Closed: new type = new map entry, not a rewritten conditional); validator normalizes/validates the config (hex format for color, stored path for image, two valid colors for gradient) and returns a sanitized array; service persists via the repository/`User::save()`. Image uploads are handled the same way `profile_photo_path` is today — stored via `Storage`, only the resulting path kept in `workspace_background_config`.
- **Test:** Unit tests per validator (valid/invalid hex, invalid gradient stops, oversized/invalid image); Unit tests for `WorkspaceBackgroundService`: selecting an enabled option succeeds, selecting a disabled option fails, a user already on a disabled option can re-save the *same* option (e.g. changing only its config) without being blocked, selecting an unknown key throws.
- **Complexity:** Medium

### Step 3: Web (and API) endpoints + Inertia shared props
- **What:** Form Request + controller action to persist the selection; extend `HandleInertiaRequests` to share the enabled options catalog and the current user's resolved background so the workspace renders correctly on first paint; matching `Api\V1` endpoint for parity.
- **Where:**
  - `app/Http/Requests/Web/UpdateWorkspaceBackgroundRequest.php` (+ shared rule trait `app/Concerns/WorkspaceBackgroundValidationRules.php`)
  - `app/Http/Controllers/Web/ProfileController.php` (new `updateBackground()` action) — keeps the existing controller rather than adding a new one, mirroring how it already owns profile + password updates
  - `routes/web.php` — `Route::patch('profile/background', [ProfileController::class, 'updateBackground'])->name('profile.background.update');`
  - `app/Http/Middleware/HandleInertiaRequests.php` — add `workspaceBackgroundOptions` (catalog, enabled-for-user) and extend the shared `auth.user` payload with a resolved `workspaceBackground` object
  - `app/Http/Controllers/Api/V1/ProfileController.php` (new, first of its kind per research §7), `app/Http/Requests/Api/V1/UpdateWorkspaceBackgroundRequest.php`, `app/Http/Resources/Api/V1/WorkspaceBackgroundResource.php`, route in `routes/api.php` under the existing `auth:sanctum` + `verified` group
- **How:** Both controllers call the same `WorkspaceBackgroundService::updateSelection()` — no duplicated business logic, per the architecture doc. Web returns `back()->with('success', ...)`; API returns the resource wrapped in `{"data": ...}`, and `InvalidBackgroundSelectionException` renders via the existing central `DomainException` handling in `bootstrap/app.php` (no ad-hoc error formatting).
- **Test:** Feature tests for the web route (success, validation failure shape, disabled-option rejection, unauthenticated rejection, cannot set another user's background); Feature tests for the API route (auth:sanctum enforcement per `ApiFoundationTest` conventions, `{"data": ...}` envelope, `error_code` on rejection); `LayeringTest` still passes (no direct queries in controllers/services).
- **Complexity:** Medium

### Step 4: Frontend — apply the background to the workspace shell
- **What:** Make `.workspace-header` and `.task-composer` (and the workspace canvas itself) driven by CSS custom properties instead of hard-coded colors, and set those properties from the user's resolved background in `app-shell.tsx`.
- **Where:** `resources/js/layouts/app-shell.tsx`, `resources/css/app.css`, new helper `resources/js/lib/workspace-background.ts`
- **How:** `workspace-background.ts` exports a pure function mapping `{ type, config }` → `{ '--workspace-bg': string; '--workspace-header-bg': string; '--workspace-composer-bg': string }` (flat color: the color itself for `--workspace-bg`, a fixed-alpha overlay of the same color for header/composer via `color-mix(in srgb, <color> 85%, black)`-style CSS so they visibly match; image: `url(...)` cover for `--workspace-bg`, a translucent dark/light scrim for header/composer so text stays legible; gradient: a CSS `linear-gradient(...)` for `--workspace-bg`, an interpolated mid-tone for header/composer). `app-shell.tsx` applies these as an inline `style` object on `.app-frame`. `app.css` changes `.workspace-header { background: rgba(71,151,111,0.98); }` → `.workspace-header { background: var(--workspace-header-bg, rgba(71,151,111,0.98)); }` (and similarly for `.task-composer`), so the **fallback preserves current behavior exactly** when no preference is set — zero visual change for existing users.
- **Test:** Manual verification in the browser (dev server) for all three types + the no-preference default, confirming header/composer legibility and no regression to the default look; a lightweight Vitest/RTL test (if the project has a JS test runner — verify during implementation) for the pure mapping function's outputs per type.
- **Complexity:** Medium

### Step 5: Frontend — background picker in Profile settings
- **What:** New "Workspace background" section in the existing profile modal, listing the enabled types (from the shared Inertia prop) with a type-specific mini-form and live preview, submitting via Inertia `useForm().patch()`.
- **Where:** `resources/js/layouts/app-shell.tsx` (new `profile-modal__section`) or extracted to `resources/js/components/settings/workspace-background-section.tsx` if the modal is already large (check current line count during implementation and extract if it improves readability); `resources/js/routes/profile/index.ts` (Wayfinder-generated — regenerate, don't hand-edit) for the new `background` route helper.
- **How:** Renders one card per entry in `workspaceBackgroundOptions` (already filtered server-side to "enabled, plus mine if currently selected"), each with its type's inputs (color input for flat color, file input + preview for image reusing existing upload UI conventions, two color inputs for gradient). Selecting + saving calls `background.update.url()` via `useForm().patch()`, mirroring the existing `saveProfile` handler; on success, updates the live preview immediately (Inertia's returned shared props already carry the fresh `auth.user.workspaceBackground`, so `app-shell.tsx` reacts automatically — no extra client state needed).
- **Test:** Manual browser walkthrough: pick each of the 3 types, save, confirm the workspace re-renders immediately and persists across a full page reload; confirm a disabled type (toggled via tinker/seeder) no longer appears for new selections; confirm a user already on a since-disabled type still sees their background applied and their current choice still shown as selected in the picker.
- **Complexity:** Medium

### Step 6: Test suite hardening + quality gate
- **What:** Fill any coverage gaps from Steps 1–5 (edge cases: switching between types, clearing a background back to default, malformed JSON config defensively rejected) and run the full quality gate.
- **Where:** `tests/Feature/...`, `tests/Unit/...`, root of the repo for `composer test`.
- **How:** Add the "clear/reset background to default" path if not already covered (nullable FK/config = default fallback, so `updateSelection` should also support a null/"none" selection); confirm `LayeringTest` and `ApiFoundationTest` still pass unmodified; run `composer test` (config clear, Pint, PHPStan, PHPUnit) and `npm run build` (typecheck) as the final gate.
- **Test:** `composer test` green, `npm run build` green, manual smoke test in browser covering: default look unchanged, all 3 types selectable and applied, disabled-type persistence for existing users confirmed.
- **Complexity:** Small

## 4. Risk Assessment

### Risks
- **Header/composer legibility across arbitrary user-chosen colors/images**: a user could pick a background that makes text on `.workspace-header`/`.task-composer` hard to read.
- **Image background storage/security**: uploaded images need mime/size validation and safe storage, same class of risk as any file upload.
- **JSON config drift**: `workspace_background_config` is loosely-typed JSON; a malformed or type-mismatched config (e.g. gradient config on a flat-color row) could reach the frontend if validation is bypassed (e.g. direct DB edit, future migration bug).
- **First introduction of an admin-controllable flag with no admin system**: scope creep risk if "hide a background option" is interpreted as requiring a full admin UI/role system now.
- **Inertia shared-prop payload growth**: adding the options catalog + resolved background to every shared request adds a small payload/query cost to every Inertia response.

### Mitigations
- Fixed-alpha overlay/scrim approach (Step 4) rather than passing user colors straight through to text-bearing elements — keeps contrast within a predictable range without a full contrast-calculation engine in v1.
- Reuse the exact validation/storage conventions already established for `profile_photo_path` (Step 2) rather than inventing new upload handling.
- Server-side validation is the single source of truth (`BackgroundConfigValidator` per type, Step 2) — the frontend never writes raw JSON, only submits through the validated endpoint; add a defensive Unit test asserting the service rejects a config shape that doesn't match the resolved type.
- Explicitly scope the admin flag to a DB-level toggle only (seeder/tinker) for this plan, and call out a future "admin panel for background management" as an explicit non-goal here — keeps this plan Medium instead of Large.
- Eager-load the (small, cached-by-request) enabled options list once per request; it's a handful of rows, not a per-item query — acceptable cost, no caching layer needed at this scale.

### Fallbacks
- If per-type CSS overlay legibility proves insufficient in review, fall back to a simpler v1: only tint `.workspace-header`/`.task-composer` with a fixed semi-opaque neutral (light/dark) scrim rather than a color derived from the background, deferring color-matched overlays to a follow-up.
- If image upload storage/security review raises concerns, fall back to URL-only image backgrounds for v1 (no file upload), revisiting upload support as a follow-up once storage policy is confirmed.

## 5. Execution Checklist

- [ ] Step 1: Migrations, model, factory, seeder for `workspace_background_options` + user preference columns
- [ ] Step 2: Repository, `WorkspaceBackgroundService`, per-type `BackgroundConfigValidator`s, domain exception
- [ ] Step 3: Web + API endpoints, Form Requests, Inertia shared props
- [ ] Step 4: CSS-variable-driven `.workspace-header`/`.task-composer`, applied from `app-shell.tsx`
- [ ] Step 5: Profile settings "Workspace background" section + picker UI
- [ ] Step 6: Coverage gaps, `composer test`, `npm run build`, manual smoke test
