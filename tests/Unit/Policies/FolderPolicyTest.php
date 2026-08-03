<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Folder;
use App\Models\User;
use App\Policies\FolderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_may_view_update_and_delete_their_folder(): void
    {
        $policy = new FolderPolicy;
        $owner = User::factory()->create();
        $folder = Folder::factory()->for($owner)->create();

        $this->assertTrue($policy->view($owner, $folder));
        $this->assertTrue($policy->update($owner, $folder));
        $this->assertTrue($policy->delete($owner, $folder));
    }

    public function test_a_non_owner_is_denied(): void
    {
        $policy = new FolderPolicy;
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $folder = Folder::factory()->for($owner)->create();

        $this->assertFalse($policy->view($stranger, $folder));
        $this->assertFalse($policy->update($stranger, $folder));
        $this->assertFalse($policy->delete($stranger, $folder));
    }
}
