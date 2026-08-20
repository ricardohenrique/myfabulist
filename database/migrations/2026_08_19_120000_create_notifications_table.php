<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        // Existing pending invitations predate the persistent notification
        // center. Backfill each one as an unread history item so deploying
        // this migration never makes an outstanding invitation invisible.
        DB::table('task_list_members')
            ->join('task_lists', 'task_lists.id', '=', 'task_list_members.task_list_id')
            ->leftJoin('users as inviters', 'inviters.id', '=', 'task_list_members.invited_by_user_id')
            ->where('task_list_members.status', 'pending')
            ->whereNull('task_lists.deleted_at')
            ->select([
                'task_list_members.id as membership_id',
                'task_list_members.user_id',
                'task_list_members.invited_at',
                'task_list_members.created_at',
                'task_lists.id as list_id',
                'task_lists.name as list_name',
                'inviters.id as inviter_id',
                'inviters.name as inviter_name',
                'inviters.avatar as inviter_avatar',
            ])
            ->orderBy('task_list_members.id')
            ->chunkById(200, function ($memberships): void {
                DB::table('notifications')->insert($memberships->map(function ($membership): array {
                    $createdAt = $membership->invited_at ?? $membership->created_at ?? now();
                    $invitedAt = $membership->invited_at === null
                        ? null
                        : CarbonImmutable::parse($membership->invited_at)->toIso8601String();

                    return [
                        'id' => (string) Str::uuid(),
                        'type' => 'App\\Notifications\\ListInvitationNotification',
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $membership->user_id,
                        'data' => json_encode([
                            'kind' => 'list_invitation',
                            'membership_id' => $membership->membership_id,
                            'status' => 'pending',
                            'invited_at' => $invitedAt,
                            'list' => [
                                'id' => $membership->list_id,
                                'name' => $membership->list_name,
                            ],
                            'actor' => $membership->inviter_id === null ? null : [
                                'id' => $membership->inviter_id,
                                'name' => $membership->inviter_name,
                                'avatar_url' => $membership->inviter_avatar,
                            ],
                        ], JSON_THROW_ON_ERROR),
                        'read_at' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                })->all());
            }, 'task_list_members.id', 'membership_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
