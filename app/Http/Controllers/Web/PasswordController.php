<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdatePasswordRequest;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function __construct(
        private readonly AccountService $accounts,
    ) {}

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->accounts->updatePassword($request->user(), $request->validated('password'));

        return back()->with('success', 'Password updated.');
    }
}
