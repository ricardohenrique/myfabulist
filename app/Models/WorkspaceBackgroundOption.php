<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkspaceBackgroundOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row in the workspace-background catalog — a selectable *type*
 * ('flat_color', 'image', 'gradient', ...), not a user's chosen value. A
 * user's actual selection lives on `users.workspace_background_option_id` +
 * `users.workspace_background_config`.
 *
 * @property int $id
 * @property string $key
 * @property string $type
 * @property string $label
 * @property array<string, mixed>|null $default_config
 * @property bool $enabled
 * @property bool $is_default
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'type', 'label', 'default_config', 'enabled', 'is_default', 'sort_order'])]
class WorkspaceBackgroundOption extends Model
{
    /** @use HasFactory<WorkspaceBackgroundOptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_config' => 'array',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
