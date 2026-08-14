<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Str;

class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * Case-insensitive lookup, done here rather than trusted to whatever the
     * caller happened to normalize. Q3 requires "exact lowercase email
     * match", and `ListSharingService::invite()` does lowercase its input —
     * but `App\Actions\Fortify\CreateNewUser` stores the registration
     * email verbatim, with no normalization, so a user could be stored as
     * `Foo@Bar.com`. Comparing two independently-normalized strings only
     * works if both sides actually agree, and on SQLite (this project's
     * default and test connection) plain `=` is byte-exact — unlike MySQL's
     * default `utf8mb4_unicode_ci` collation, which would have hidden this
     * gap by accident. Folding case in the query itself (`lower(email) = ?`)
     * closes the gap regardless of what the caller passes in or how the row
     * was originally stored, and reads correctly on both supported drivers.
     *
     * A fuller fix — normalizing at registration, backfilling existing
     * rows, a validation rule preventing two accounts differing only by
     * case — is a separate follow-up; this only closes the gap this method
     * itself needs to be correct.
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->first();
    }
}
