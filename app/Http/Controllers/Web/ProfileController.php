<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateCompletionSoundRequest;
use App\Http\Requests\Web\UpdateProfileRequest;
use App\Http\Requests\Web\UpdateWorkspaceBackgroundRequest;
use App\Services\AccountService;
use App\Services\CompletionSoundService;
use App\Services\WorkspaceBackgroundService;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly WorkspaceBackgroundService $backgrounds,
        private readonly CompletionSoundService $sounds,
    ) {}

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->accounts->updateProfile(
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
        );

        return back()->with('success', 'Profile updated.');
    }

    public function updateBackground(UpdateWorkspaceBackgroundRequest $request): RedirectResponse
    {
        $optionKey = $request->optionKey();

        if ($optionKey === null) {
            $this->backgrounds->clearSelection($request->user());
        } else {
            $this->backgrounds->updateSelection($request->user(), $optionKey, $request->backgroundConfig());
        }

        return back()->with('success', 'Workspace background updated.');
    }

    public function updateCompletionSound(UpdateCompletionSoundRequest $request): RedirectResponse
    {
        $this->sounds->updateSelection($request->user(), $request->completionSoundKey());

        return back()->with('success', 'Completion sound updated.');
    }
}
