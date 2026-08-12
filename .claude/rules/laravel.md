# Laravel Rules

Laravel-specific coding standards for the MyFabulist project.

---

## Eloquent

- Use Eloquent models and relationships instead of raw `DB::` queries.
- Define all relationships explicitly on models (`hasMany`, `belongsTo`, etc.).
- Use eager loading (`with()`) to prevent N+1 queries.
- Use scopes to encapsulate reusable query logic.
- Never put query logic in controllers or services — use repositories.

---

## Form Requests

- Validate all user input using Form Request classes (`php artisan make:request`).
- Authorization logic (`authorize()`) must live in the Form Request, not the controller.
- Controllers must remain thin: receive a validated request, call a service, return a response.

---

## Blade

- `resources/views/app.blade.php` is the single Inertia root view — it has no
  page-specific markup or business logic, only the Inertia head/body mount points.
- Never write business logic inside Blade templates.

---

## Inertia and React

- Web controllers return `Inertia::render('page/name', [...])`; props are the only
  channel for server data reaching a page component — never fetch domain data
  client-side over HTTP.
- Use TypeScript for all React code. Keep page prop shapes explicit and shared
  between the controller and the corresponding type in `resources/js/types`.
- Pages live in `resources/js/pages`, shared UI in `resources/js/components`, page
  shells in `resources/js/layouts`, client helpers/hooks in `resources/js/lib`.
- Submit mutations with Inertia's `useForm`/`router` helpers against
  `routes/web.php`/`routes/settings.php` controllers; validate with Laravel Form
  Requests server-side and surface `errors` from the Inertia response.
- Use Laravel Wayfinder (`resources/js/routes`) for typed route generation instead
  of hardcoded URL strings.
- Keep React components small and focused on a single UI concern; delegate all
  data operations to the Laravel controller/service layer, never to client-only
  state that duplicates server-owned data.

---

## Routing

- Define all routes in `routes/web.php` (web) or `routes/api.php` (API).
- Use named routes for all non-trivial routes.
- Group related routes with `Route::prefix()` and `Route::middleware()`.
- Use route model binding where it simplifies controller code.
- Constrain numeric route-model-bound parameters (e.g. `->where(['folder' => '[0-9]+'])`
  or `whereNumber()`) so they can't collide with literal path segments.
- Register literal-path routes (e.g. `folders/order`) **before** the resource routes
  that bind `{folder}` — otherwise the literal segment is swallowed as an id.
- API routes must all sit inside the `auth:sanctum` + `verified` middleware group
  in `routes/api.php`; this is asserted by `tests/Feature/Api/V1/ApiFoundationTest.php`.
- API Form Requests scope any `Rule::exists(...)` lookups by the authenticated user's
  `user_id` — never trust a foreign id to resolve just because it exists in the table.

---

## Service Providers

- Register bindings in `AppServiceProvider` or a dedicated service provider.
- Bind interfaces to implementations here — not in controllers or services.
- Avoid putting business logic inside service providers.

---

## Middleware

- Use middleware for cross-cutting concerns: authentication, authorization, throttling, logging.
- Do not put business logic in middleware.

---

## Artisan Commands

- Use Artisan commands for batch processing and scheduled tasks.
- Commands must delegate to services — no business logic in the `handle()` method.

---

## Error Handling

- Use named domain exceptions for business rule violations.
- Register custom exception rendering in `bootstrap/app.php` (Laravel 11+) or `Handler.php`.
- Never catch `\Throwable` silently — always log or re-throw.

---

## Authorization

- Use Laravel Policies for model-level authorization.
- Use Gates for non-model authorization logic.
- Never perform authorization checks inline in controllers beyond calling `$this->authorize()` or `Gate::authorize()`.
