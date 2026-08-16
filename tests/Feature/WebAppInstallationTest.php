<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class WebAppInstallationTest extends TestCase
{
    public function test_the_application_shell_exposes_home_screen_metadata(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/site.webmanifest">', false)
            ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
            ->assertSee('viewport-fit=cover', false);
    }

    public function test_the_web_app_manifest_references_install_sized_icons(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('site.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('Purplelist', $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertFileExists(public_path('web-app-icon-192.png'));
        $this->assertFileExists(public_path('web-app-icon-512.png'));
        $this->assertSame(
            ['192x192', '512x512'],
            array_column($manifest['icons'], 'sizes'),
        );
    }
}
