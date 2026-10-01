<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $leave_request_id
 * @property string $file_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 */
class LeaveRequestAttachment extends Model
{
    protected $fillable = ['leave_request_id', 'file_name', 'file_path', 'mime_type', 'file_size'];

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
