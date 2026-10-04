<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Proof that a live preview check matched the enrolled face, shown to the
 * final absen/masuk|pulang submit so the server doesn't have to re-run a
 * full check from a cold start — but it only shows a preview passed, it
 * never substitutes for re-verifying the actual final photo (see
 * AttendanceController), and it's consumed exactly once.
 *
 * @property int $id
 * @property string $token
 * @property int $user_id
 * @property string $session_id
 * @property string $action
 * @property string $attempt_id
 * @property Carbon|null $face_enrolled_at_snapshot
 * @property string|null $image_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
class FaceVerificationReceipt extends Model
{
    protected $fillable = [
        'token', 'user_id', 'session_id', 'action', 'attempt_id',
        'face_enrolled_at_snapshot', 'image_hash', 'expires_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'face_enrolled_at_snapshot' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function issue(
        User $user,
        string $sessionId,
        string $action,
        string $attemptId,
        ?string $imageHash,
        int $ttlSeconds,
    ): self {
        return self::create([
            'token' => Str::random(64),
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'action' => $action,
            'attempt_id' => $attemptId,
            'face_enrolled_at_snapshot' => $user->face_enrolled_at,
            'image_hash' => $imageHash,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);
    }

    /**
     * Non-atomic — fine for display/debugging, but the final submit must
     * re-check this inside a locked transaction before consuming it (see
     * AttendanceController), not rely on this alone.
     */
    public function isValidFor(User $user, string $sessionId, string $action): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->user_id === $user->id
            && $this->session_id === $sessionId
            && $this->action === $action
            && $this->face_enrolled_at_snapshot?->equalTo($user->face_enrolled_at);
    }
}
