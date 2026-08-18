<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OnboardingUseCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_onboarding_is_shared_with_the_inbox_page(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)->get(route('inbox'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('onboarding.pending', true)
                ->where('onboarding.useCaseOptions', OnboardingUseCase::options()));
    }

    public function test_a_user_can_answer_the_onboarding_question(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)
            ->from(route('inbox'))
            ->patch(route('onboarding.complete'), ['use_case' => 'school_studies'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inbox'));

        $user->refresh();

        $this->assertSame(OnboardingUseCase::SchoolStudies, $user->onboarding_use_case);
        $this->assertNotNull($user->onboarding_completed_at);

        $this->actingAs($user)->get(route('inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('onboarding.pending', false));
    }

    public function test_a_user_can_skip_the_onboarding_question(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)
            ->from(route('inbox'))
            ->patch(route('onboarding.complete'), ['use_case' => null])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inbox'));

        $user->refresh();

        $this->assertNull($user->onboarding_use_case);
        $this->assertNotNull($user->onboarding_completed_at);
    }

    public function test_an_invalid_use_case_is_rejected_without_completing_onboarding(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)
            ->patch(route('onboarding.complete'), ['use_case' => 'unsupported'])
            ->assertSessionHasErrors('use_case');

        $user->refresh();

        $this->assertNull($user->onboarding_use_case);
        $this->assertNull($user->onboarding_completed_at);
    }

    public function test_completed_onboarding_cannot_be_overwritten(): void
    {
        $user = User::factory()->create([
            'onboarding_use_case' => OnboardingUseCase::Work,
        ]);

        $this->actingAs($user)
            ->patch(route('onboarding.complete'), ['use_case' => 'projects'])
            ->assertSessionHasNoErrors();

        $this->assertSame(OnboardingUseCase::Work, $user->fresh()->onboarding_use_case);
    }

    public function test_guests_cannot_complete_onboarding(): void
    {
        $this->patch(route('onboarding.complete'), ['use_case' => 'work'])
            ->assertRedirect(route('login'));
    }
}
