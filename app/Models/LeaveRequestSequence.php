<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $year
 * @property int $last_sequence
 */
class LeaveRequestSequence extends Model
{
    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = ['year', 'last_sequence'];
}
