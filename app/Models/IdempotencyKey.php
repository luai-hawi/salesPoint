<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'key',
        'request_key',
        'method',
        'path',
        'status',
        'body',
        'body_truncated',
        'location',
        'content_type',
        'created_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'body_truncated' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function pruneExpired(int $days = 7): int
    {
        return static::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
