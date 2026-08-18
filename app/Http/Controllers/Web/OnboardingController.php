<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CompleteOnboardingRequest;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly AccountService $accounts,
    ) {}

    public function __invoke(CompleteOnboardingRequest $request): RedirectResponse
    {
        $this->accounts->completeOnboarding($request->user(), $request->useCase());

        return back();
    }
}
