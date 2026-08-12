<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Repositories\Contracts\FolderRepositoryInterface;
use App\Repositories\Contracts\SubtaskRepositoryInterface;
use App\Repositories\Contracts\TaskCommentRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;

/**
 * Automated tripwire for D1 ("one application layer, two thin delivery
 * mechanisms") and the repository-is-the-only-query-layer rule. Every
 * assertion here reads real source files rather than relying on convention
 * (R2/R5/R6 in the plan's risk assessment).
 */
class LayeringTest extends TestCase
{
    public function test_inertia_browser_never_re_enters_its_own_api(): void
    {
        foreach (['pages', 'layouts', 'components'] as $directory) {
            $this->assertNoneContain(
                resource_path("js/{$directory}"),
                ['/api/v1', 'fetch(', 'axios'],
                'must use Inertia web routes, never re-enter /api/v1 over HTTP (D1).',
            );
        }
    }

    public function test_services_never_use_the_http_facade(): void
    {
        $this->assertNoneContain(
            app_path('Services'),
            ['Illuminate\Support\Facades\Http', 'Http::'],
            'must never call the Http facade — services are the application layer, not an HTTP client (D1).',
        );
    }

    public function test_services_never_build_eloquent_queries_directly(): void
    {
        $this->assertNoneContain(
            app_path('Services'),
            ['DB::', '::query(', '->where('],
            'must not build Eloquent queries directly — only repositories may (D1/D12).',
        );
    }

    public function test_controllers_never_build_eloquent_queries_directly(): void
    {
        $this->assertNoneContain(
            app_path('Http/Controllers'),
            ['DB::', '::query(', '->where('],
            'must not build Eloquent queries directly — controllers call services (D1).',
        );
    }

    public function test_every_eloquent_repository_implements_a_contract(): void
    {
        $contracts = [
            FolderRepositoryInterface::class,
            TaskListRepositoryInterface::class,
            TaskCommentRepositoryInterface::class,
            TaskRepositoryInterface::class,
            SubtaskRepositoryInterface::class,
        ];

        $repositoriesDirectory = app_path('Repositories');
        $contractsDirectory = app_path('Repositories/Contracts');

        if (! File::isDirectory($repositoriesDirectory)) {
            $this->fail('app/Repositories does not exist.');
        }

        $checked = 0;

        foreach (File::allFiles($repositoriesDirectory) as $file) {
            if (str_starts_with($file->getPathname(), $contractsDirectory)) {
                continue;
            }

            $class = 'App\\Repositories\\'.$file->getFilenameWithoutExtension();

            $this->assertTrue(class_exists($class), "{$class} could not be loaded from {$file->getPathname()}.");

            $reflection = new ReflectionClass($class);

            $implementsAContract = collect($contracts)
                ->contains(fn (string $contract): bool => $reflection->implementsInterface($contract));

            $this->assertTrue(
                $implementsAContract,
                "{$class} must implement one of the App\\Repositories\\Contracts interfaces.",
            );

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'Expected at least one Eloquent repository to check.');
    }

    /**
     * Assert that no PHP file under $directory contains any of $forbidden.
     *
     * @param  array<int, string>  $forbidden
     */
    private function assertNoneContain(string $directory, array $forbidden, string $ruleDescription): void
    {
        if (! File::isDirectory($directory)) {
            $this->assertTrue(true, "{$directory} does not exist yet — nothing to check.");

            return;
        }

        foreach (File::allFiles($directory) as $file) {
            $contents = File::get($file->getPathname());

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $contents,
                    "{$file->getPathname()} {$ruleDescription}",
                );
            }
        }
    }
}
