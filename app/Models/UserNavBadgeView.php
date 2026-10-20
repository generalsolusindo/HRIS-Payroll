<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property string $badge_key
 * @property Carbon $last_seen_at
 */
class UserNavBadgeView extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'badge_key', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
