<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Lists\ListHeader;
use App\Models\TaskList;
use App\Models\User;
use App\Services\TaskListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_list_name_and_the_actions_menu_for_a_normal_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Website launch']);

        Livewire::actingAs($user)
            ->test(ListHeader::class, ['taskListId' => $list->id])
            ->assertSee('Website launch')
            ->assertSeeHtml("mode: 'delete', listId: {$list->id}");
    }

    public function test_the_inbox_renders_the_header_with_no_rename_or_delete_affordance(): void
    {
        $user = User::factory()->create();
        $inbox = app(TaskListService::class)->inboxFor($user);

        Livewire::actingAs($user)
            ->test(ListHeader::class, ['taskListId' => $inbox->id])
            ->assertSee('Inbox')
            ->assertDontSeeHtml("mode: 'delete'");
    }

    public function test_renaming_through_the_dialog_updates_the_header_without_a_reload(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Old name']);

        $header = Livewire::actingAs($user)->test(ListHeader::class, ['taskListId' => $list->id]);
        $header->assertSee('Old name');

        app(TaskListService::class)->update($list, $user, 'New name', $list->folder_id);

        $header->dispatch('navigation-changed');
        $header->assertSee('New name');
    }

    public function test_another_users_list_id_is_refused(): void
    {
        $stranger = User::factory()->create();
        $foreignList = TaskList::factory()->create();

        Livewire::actingAs($stranger)
            ->test(ListHeader::class, ['taskListId' => $foreignList->id])
            ->assertStatus(403);
    }
}
