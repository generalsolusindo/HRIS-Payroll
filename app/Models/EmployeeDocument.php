<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string|null $label
 * @property string $file_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property int|null $uploaded_by
 */
class EmployeeDocument extends Model
{
    public const TYPES = ['ktp', 'kk', 'ktm', 'ijazah', 'lainnya'];

    protected $fillable = ['user_id', 'type', 'label', 'file_name', 'file_path', 'mime_type', 'file_size', 'uploaded_by'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
