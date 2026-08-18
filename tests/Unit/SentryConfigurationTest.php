<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class SentryConfigurationTest extends TestCase
{
    public function test_sentry_delivery_is_disabled_outside_production(): void
    {
        $this->assertFalse($this->app->isProduction());
        $this->assertNull(config('sentry.dsn'));
    }
}
