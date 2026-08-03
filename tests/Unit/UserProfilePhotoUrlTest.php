<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfilePhotoUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_no_photo_is_set(): void
    {
        $user = User::factory()->create(['profile_photo_path' => null]);

        $this->assertNull($user->profilePhotoUrl);
    }

    public function test_returns_the_public_disk_url_when_a_photo_path_is_set(): void
    {
        Storage::fake('public');

        $user = User::factory()->withProfilePhoto('profile-photos/avatar.jpg')->create();

        $this->assertNotNull($user->profilePhotoUrl);
        $this->assertStringEndsWith('profile-photos/avatar.jpg', $user->profilePhotoUrl);
    }

    public function test_with_profile_photo_state_persists_the_path(): void
    {
        $user = User::factory()->withProfilePhoto()->create();

        $this->assertSame('profile-photos/test-photo.jpg', $user->fresh()->profile_photo_path);
    }
}
