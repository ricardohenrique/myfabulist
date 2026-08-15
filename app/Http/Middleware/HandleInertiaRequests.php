<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Presenters\WorkspacePresenter;
use App\Models\TaskListMember;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
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
            'appName' => config('app.name', 'My Fabulist'),
            'auth' => [
                'user' => fn (): ?array => $request->user() === null ? null : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatarUrl' => $request->user()->profile_photo_url,
                ],
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
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
}
