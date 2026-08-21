<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureHealthChecks();
        $this->configureRouteBindings();
    }

    /**
     * Include the canonical database in Laravel's readiness check.
     */
    protected function configureHealthChecks(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('SELECT 1');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(1)
            ->letters()
            ->numbers());

        Model::preventLazyLoading(! app()->isProduction());

        // Required before Middleware::throttleApi() is enabled in
        // bootstrap/app.php — an unregistered "api" limiter throws
        // MissingRateLimiterException on every request (D10/R3).
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by(
            $request->user()?->id ?: $request->ip(),
        ));

        // Plan 1 ("Shared Lists and Collaboration"), Step 7 (N8/R8): a
        // tighter limit than the general "api" limiter above, applied only
        // to the invite-creation route — invitation creation is the
        // abuse-sensitive endpoint (email enumeration, spam invites), not
        // every API call.
        //
        // Step 8 code-review follow-up: a tripped limit on the *web* route
        // must render through Inertia (back() with an inline field error on
        // the invite form), matching every other validation/domain error on
        // that form — a plain 429 HTML page is not an Inertia response, so
        // Inertia's client can't reconcile it and pops the full-page error
        // modal instead. `response()` must always return a real Response
        // (ThrottleRequests::buildException() passes whatever it returns
        // straight into `new HttpResponseException($response)`, which is
        // not nullable) — so the non-Inertia branch builds the same 429
        // Laravel would have built on its own, rather than returning null.
        RateLimiter::for('invite', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn (Request $request, array $headers) => $request->header('X-Inertia')
                ? back()->withErrors(['email' => 'Too many invitations. Try again shortly.'])
                : response('Too Many Attempts.', 429, $headers)));
    }

    /**
     * Explicit route-model-binding for `{list}` (used identically by both
     * `routes/web.php` and `routes/api.php`), registered here rather than
     * left as `TaskList`'s implicit binding. `folder_id`/`position` are not
     * real `task_lists` columns (Plan 1, Step 2) — a plain implicit
     * `TaskList::query()->where('id', $value)` would leave every route-
     * bound list with no placement at all, silently breaking every
     * controller that resolves `{list}` (`TaskListResource`,
     * `WorkspacePresenter`) without touching any of them.
     *
     * This delegates to `TaskListRepositoryInterface::findForRouteBinding()`
     * so the query itself still lives in the repository layer, not here or
     * in the model — `request()->user()` is the viewer, read once, in the
     * one layer (routing) where reaching for the current request's
     * authenticated user is completely ordinary. `Route::bind()` runs
     * inside `SubstituteBindings`, which the framework's default middleware
     * priority always places after `Authenticate` — every `{list}` route
     * sits behind `auth`/`auth:sanctum`, so `request()->user()` is already
     * resolved by the time this closure runs for any request that reaches
     * it at all.
     *
     * Not scoped to the viewer's own lists on purpose: the list must still
     * resolve even when the viewer can't access it, so `TaskListPolicy` gets
     * the chance to deny it with 403 rather than the binding itself turning
     * a cross-user request into an indistinguishable 404 — exactly how
     * Laravel's own implicit binding would behave for an owned list.
     */
    protected function configureRouteBindings(): void
    {
        Route::bind('list', function (string $value): TaskList {
            return app(TaskListRepositoryInterface::class)
                ->findForRouteBinding((int) $value, request()->user())
                ?? throw (new ModelNotFoundException)->setModel(TaskList::class, [$value]);
        });

        $this->configureTaskRouteBinding();
        $this->configureInvitationRouteBinding();
    }

    /**
     * Explicit route-model-binding for `{task}` (used identically by both
     * `routes/web.php` and `routes/api.php`), registered here rather than
     * left as `Task`'s implicit binding — for the same reason `{list}` is
     * above. `is_starred` is not a real `tasks` column (Plan 1, Step 3) — a
     * plain implicit `Task::query()->where('id', $value)` would leave every
     * route-bound task with `is_starred` simply absent, silently breaking
     * every controller that resolves `{task}` and serializes it
     * (`TaskResource`) without touching any of them.
     *
     * This delegates to `TaskRepositoryInterface::findForRouteBinding()` so
     * the query itself still lives in the repository layer. Not scoped to
     * the viewer's own tasks on purpose: the task must still resolve even
     * when the viewer can't access it, so `TaskPolicy` gets the chance to
     * deny it with 403 rather than the binding itself turning a cross-user
     * request into an indistinguishable 404.
     */
    protected function configureTaskRouteBinding(): void
    {
        Route::bind('task', function (string $value): Task {
            return app(TaskRepositoryInterface::class)
                ->findForRouteBinding((int) $value, request()->user())
                ?? throw (new ModelNotFoundException)->setModel(Task::class, [$value]);
        });
    }

    /**
     * Explicit route-model-binding for `{invitation}` (Plan 1, Step 7
     * code-review follow-up), registered here for the same reason `{list}`/
     * `{task}` are above — but for a different guarantee: an invitation's
     * list can be soft-deleted out from under a still-`pending`
     * `task_list_members` row (the owner deleted the shared list before the
     * invitee responded). Plain implicit binding would resolve the row with
     * `taskList` null-able via a `belongsTo` that picks up `SoftDeletes`'
     * global scope, and every downstream consumer
     * (`ListSharingService::{accept,decline}()`, `TaskListMemberResource`)
     * assumes it is never null — that mismatch is exactly what produced a
     * 500 before this binding existed.
     * `TaskListMemberRepositoryInterface::findForRouteBinding()` excludes a
     * row whose list is gone and eager-loads `taskList` so nothing
     * downstream can hit that null. Not scoped to the viewer on purpose —
     * `TaskListMemberPolicy::respond()` is what decides whether this
     * particular caller may act on the result; a stranger's request should
     * 403, not 404, exactly like `{list}`/`{task}` above.
     */
    protected function configureInvitationRouteBinding(): void
    {
        Route::bind('invitation', function (string $value): TaskListMember {
            return app(TaskListMemberRepositoryInterface::class)
                ->findForRouteBinding((int) $value)
                ?? throw (new ModelNotFoundException)->setModel(TaskListMember::class, [$value]);
        });
    }
}
