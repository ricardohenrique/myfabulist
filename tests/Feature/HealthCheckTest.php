<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_reports_up_when_the_database_is_available(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([(object) ['1' => 1]]);

        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'up']);
    }

    public function test_health_endpoint_reports_down_when_the_database_is_unavailable(): void
    {
        config(['app.debug' => false]);

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new RuntimeException('Database unavailable.'));

        $this->getJson('/health')
            ->assertStatus(500)
            ->assertExactJson(['status' => 'down']);
    }
}
