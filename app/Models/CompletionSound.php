<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompletionSoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string $file_path
 * @property bool $enabled
 * @property bool $is_default
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'label', 'file_path', 'enabled', 'is_default', 'sort_order'])]
class CompletionSound extends Model
{
    /** @use HasFactory<CompletionSoundFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function publicUrl(): string
    {
        return '/'.ltrim($this->file_path, '/');
    }
}
