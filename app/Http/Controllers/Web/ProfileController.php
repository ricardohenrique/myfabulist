<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateProfileRequest;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AccountService $accounts,
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
}
