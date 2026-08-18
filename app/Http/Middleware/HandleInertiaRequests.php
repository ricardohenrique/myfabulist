<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Presenters\WorkspacePresenter;
use App\Models\TaskListMember;
use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Services\WorkspaceBackgroundService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 8: shares the pending-
 * invitation notification props on every Inertia response.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly TaskListMemberRepositoryInterface $members,
        private readonly TaskListRepositoryInterface $taskLists,
        private readonly WorkspacePresenter $workspace,
        private readonly WorkspaceBackgroundService $backgrounds,
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
                ],
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
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'analyticsEvent' => fn (): ?array => $request->session()->get('analytics_event'),
            ],
            'notifications' => [
                // A plain closure, evaluated on every response — matching
                // 'auth.user' above — since a cheap indexed count is exactly
                // what a bell badge needs on every page load.
                'pendingInvitationCount' => fn (): int => $request->user() === null
                    ? 0
                    : $this->members->pendingCountFor($request->user()),
                // Inertia::optional() excludes this from every normal page
                // load; the frontend hydrates it with a partial reload
                // (router.reload({ only: ['notifications'] })) when the bell
                // opens (Step 9, per Architecture Decision 5) — 'notifications'
                // (the parent key), not 'notifications.invitations': Inertia's
                // partial-reload matching (PropsResolver::leadsToOnly()) walks
                // down from an "only" path to reach a nested prop, so naming
                // the parent is what actually resolves this closure; naming
                // the leaf path also happens to work (verified against the
                // vendor source), but 'notifications' is what the frontend
                // will send.
                'invitations' => Inertia::optional(fn (): array => $request->user() === null
                    ? []
                    : $this->members->pendingFor($request->user())
                        ->map(fn (TaskListMember $membership): array => [
                            'id' => $membership->id,
                            'list' => [
                                'id' => $membership->taskList->id,
                                'name' => $membership->taskList->name,
                            ],
                            // invitedBy is nullable on the model — mirrors
                            // the defensive guard ListInvitationResource
                            // applies for the same relation (Step 7 review).
                            'invitedBy' => $membership->invitedBy === null ? null : [
                                'id' => $membership->invitedBy->id,
                                'name' => $membership->invitedBy->name,
                                'avatarUrl' => $membership->invitedBy->profile_photo_url,
                            ],
                            'invitedAt' => $membership->invited_at?->toIso8601String(),
                        ])
                        ->all()),
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
}
