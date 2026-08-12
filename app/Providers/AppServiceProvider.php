<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\TaskList;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->configureRouteBindings();
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        Model::preventLazyLoading(! app()->isProduction());

        // Required before Middleware::throttleApi() is enabled in
        // bootstrap/app.php — an unregistered "api" limiter throws
        // MissingRateLimiterException on every request (D10/R3).
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by(
            $request->user()?->id ?: $request->ip(),
        ));
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
}
