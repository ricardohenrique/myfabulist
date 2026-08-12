<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 5: `ListSharingService`
 * needs to resolve an invitee by email, and `app/Services` may never build a
 * query directly (`LayeringTest::test_services_never_build_eloquent_queries_directly()`).
 * This is the first cross-entity user lookup the application layer needs, so
 * it gets its own repository, matching the project's one-repository-per-
 * entity convention.
 */
interface UserRepositoryInterface
{
    /**
     * Case-insensitive lookup by email (Q3: "exact lowercase email match").
     * Case-folding happens in the query itself — see the Eloquent
     * implementation's docblock for why trusting the caller to have already
     * normalized the string is not sufficient on its own. Does not trim
     * `$email`; `ListSharingService::invite()` still trims before calling.
     */
    public function findByEmail(string $email): ?User;
}
