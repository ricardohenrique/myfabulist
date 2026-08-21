<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OnboardingUseCase;
use App\Http\Presenters\WorkspacePresenter;
use App\Models\CompletionSound;
use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Services\CompletionSoundService;
use App\Services\NotificationCenterService;
use App\Services\WorkspaceBackgroundService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Middleware;

/** Shares account, notification-badge, and optional workspace props. */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly TaskListRepositoryInterface $taskLists,
        private readonly WorkspacePresenter $workspace,
        private readonly WorkspaceBackgroundService $backgrounds,
        private readonly CompletionSoundService $sounds,
        private readonly NotificationCenterService $notificationCenter,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name', 'Purplelist'),
            'passwordRequirements' => collect(Password::default()->appliedRules())
                ->only(['letters', 'numbers'])
                ->all(),
            'auth' => [
                'user' => fn (): ?array => $request->user() === null ? null : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatarUrl' => $request->user()->profile_photo_url,
                    'hasPassword' => $request->user()->password !== null,
                    'emailVerified' => $request->user()->hasVerifiedEmail(),
                    // Resolved server-side (image paths become public URLs
                    // here) so the workspace shell can apply it on first
                    // paint with no flash of unstyled background.
                    'workspaceBackground' => $this->resolvedWorkspaceBackground($request->user()),
                    'completionSound' => $this->resolvedCompletionSound($request->user()),
                ],
            ],
            'onboarding' => fn (): array => [
                'pending' => $request->user()?->needsOnboarding() ?? false,
                'useCaseOptions' => OnboardingUseCase::options(),
            ],
            // The catalog of currently selectable background types, plus
            // the user's own selection even if it has since been disabled
            // (WorkspaceBackgroundService::availableOptionsFor()) — the
            // profile modal's picker renders straight from this, no extra
            // round trip.
            'workspaceBackgroundOptions' => fn (): array => $request->user() === null
                ? []
                : $this->backgrounds->availableOptionsFor($request->user())
                    ->map(fn (WorkspaceBackgroundOption $option): array => [
                        'key' => $option->key,
                        'type' => $option->type,
                        'label' => $option->label,
                        'defaultConfig' => $option->default_config,
                        'isDefault' => $option->is_default,
                    ])
                    ->values()
                    ->all(),
            'completionSoundOptions' => fn (): array => $request->user() === null
                ? []
                : $this->sounds->availableOptionsFor($request->user())
                    ->map(fn (CompletionSound $sound): array => [
                        'key' => $sound->key,
                        'label' => $sound->label,
                        'url' => $sound->publicUrl(),
                        'isDefault' => $sound->is_default,
                    ])
                    ->values()
                    ->all(),
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'analyticsEvent' => fn (): ?array => $request->session()->get('analytics_event'),
            ],
            'notifications' => [
                'unreadCount' => fn (): int => $request->user() === null
                    ? 0
                    : $this->notificationCenter->unreadCountFor($request->user()),
            ],
            // A list row only carries navigation summary data. The complete
            // share-dialog roster is loaded on demand with a partial Inertia
            // reload, keeping the current workspace and URL unchanged.
            'sharingDialog' => Inertia::optional(function () use ($request): ?array {
                $user = $request->user();
                $listId = $request->integer('sharing_list_id');

                if ($user === null || $listId < 1) {
                    return null;
                }

                $list = $this->taskLists->findAccessibleFor($listId, $user);

                return $list === null ? null : $this->workspace->sharingDetails($user, $list);
            }),
        ];
    }

    /**
     * @return array{optionKey: string, type: string, config: array<string, mixed>, isCustomized: bool}|null
     */
    private function resolvedWorkspaceBackground(User $user): ?array
    {
        $background = $this->backgrounds->resolvedBackgroundFor($user);

        if ($background === null) {
            return null;
        }

        return [
            'optionKey' => $background->optionKey,
            'type' => $background->type,
            'config' => $background->config,
            'isCustomized' => $background->isCustomized,
        ];
    }

    /** @return array{key: string, label: string, url: string}|null */
    private function resolvedCompletionSound(User $user): ?array
    {
        $sound = $this->sounds->resolvedSoundFor($user);

        if ($sound === null) {
            return null;
        }

        return [
            'key' => $sound->key,
            'label' => $sound->label,
            'url' => $sound->url,
        ];
    }
}
