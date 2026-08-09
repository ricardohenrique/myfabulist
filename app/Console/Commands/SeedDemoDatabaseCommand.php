<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Config;

/**
 * `php artisan db:fresh-seed` — local-dev-only destructive reset that wipes
 * every table, re-runs every migration and seeds a realistic, 20-user demo
 * dataset (D1). The command is pure orchestration: guard, report the
 * target, delegate to `migrate:fresh --seeder=DemoSeeder` (D2), report the
 * result. Every row is built by `DemoSeeder`; no query, model or service
 * is touched here.
 */
class SeedDemoDatabaseCommand extends Command
{
    use ConfirmableTrait;

    /**
     * @var string
     */
    protected $signature = 'db:fresh-seed
        {--force : Force the operation to run when not in a local environment}';

    /**
     * @var string
     */
    protected $description = 'Wipe the database, re-run all migrations and seed realistic demo data (local development only)';

    public function handle(): int
    {
        // D11: mirrors migrate:fresh/db:wipe's own guard, extended to
        // refuse staging and any unknown environment too, not just
        // production. Composes with the pre-existing
        // DB::prohibitDestructiveCommands(app()->isProduction()) guard
        // (AppServiceProvider), which throws inside migrate:fresh in
        // production even if --force is passed.
        if (! $this->confirmToProceed(
            'Application In Production!',
            fn (): bool => ! $this->getLaravel()->environment(['local', 'testing']),
        )) {
            return self::FAILURE;
        }

        $this->reportTarget();

        $startedAt = microtime(true);

        $exitCode = $this->call('migrate:fresh', [
            '--force' => true,
            '--seed' => true,
            '--seeder' => DemoSeeder::class,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->components->error('db:fresh-seed failed while running migrate:fresh.');

            return self::FAILURE;
        }

        $this->reportSuccess($startedAt);

        return self::SUCCESS;
    }

    /**
     * Print the connection and database about to be destroyed, before
     * anything is dropped (D12) — read entirely from config, never from a
     * query. Creates a missing SQLite file up front so a fresh clone does
     * not fail before migrate:fresh's own --force auto-touch() runs (R8).
     */
    private function reportTarget(): void
    {
        $connection = Config::string('database.default');
        $driver = Config::string("database.connections.{$connection}.driver");
        $database = (string) Config::get("database.connections.{$connection}.database");

        if ($driver === 'sqlite' && $database !== ':memory:' && ! file_exists($database)) {
            touch($database);

            $this->components->info("Created missing SQLite database file: {$database}");
        }

        $this->components->info("Target: {$connection} -> {$database}");
    }

    private function reportSuccess(float $startedAt): void
    {
        $elapsedSeconds = round(microtime(true) - $startedAt, 2);

        $this->components->info("Demo database seeded in {$elapsedSeconds}s.");
        $this->components->warn('Every session and API token was wiped — you have been logged out.');
        $this->line('Log in as demo1@example.com … demo20@example.com, password: password');
    }
}
