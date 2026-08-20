<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'type' => $this['type'],
            'read_at' => $this['readAt'],
            'created_at' => $this['createdAt'],
            'actor' => [
                'id' => $this['actor']['id'],
                'name' => $this['actor']['name'],
                'avatar_url' => $this['actor']['avatarUrl'],
            ],
            'list' => $this['list'],
            'task' => $this['task'],
            'body' => $this['body'],
            'invitation' => $this['invitation'] === null ? null : [
                'id' => $this['invitation']['id'],
                'status' => $this['invitation']['status'],
                'can_respond' => $this['invitation']['canRespond'],
            ],
            'target_url' => $this['targetUrl'],
        ];
    }
}
