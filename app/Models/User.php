<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\WelcomeVerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $google_id
 * @property string|null $avatar
 * @property string|null $profile_photo_path
 * @property int|null $workspace_background_option_id
 * @property array<string, mixed>|null $workspace_background_config
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $profile_photo_url
 * @property-read WorkspaceBackgroundOption|null $workspaceBackgroundOption
 * @property-read Collection<int, Folder> $folders
 * @property-read Collection<int, TaskList> $taskLists
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, TaskComment> $taskComments
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function sendEmailVerificationNotification(): void
    {
        $this->notify($this->wasRecentlyCreated
            ? new WelcomeVerifyEmailNotification
            : new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'workspace_background_config' => 'array',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Get the URL to the user's profile photo, or null when none is set.
     *
     * @return Attribute<covariant string|null, never>
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->profile_photo_path
                ? Storage::disk('public')->url($this->profile_photo_path)
                : $this->avatar,
        );
    }

    /**
     * The catalog row for this user's currently selected background type, if
     * any — resolving/validating the user's actual selection is
     * `WorkspaceBackgroundService`'s job, not this relation's.
     *
     * @return BelongsTo<WorkspaceBackgroundOption, $this>
     */
    public function workspaceBackgroundOption(): BelongsTo
    {
        return $this->belongsTo(WorkspaceBackgroundOption::class);
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * @return HasMany<TaskList, $this>
     */
    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }
}
