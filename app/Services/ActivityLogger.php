<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Writes audit entries. Logging must never break the business operation that triggered it,
 * so every failure is swallowed (and reported to the log file).
 */
class ActivityLogger
{
    private static ?bool $tableExists = null;

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $action,
        string $subjectType,
        ?Model $subject = null,
        array $properties = [],
        ?float $amount = null,
        ?string $label = null,
        ?int $ownerId = null,
        ?string $actorName = null,
    ): ?ActivityLog {
        try {
            if (! self::enabled()) {
                return null;
            }

            $actor = auth()->user();
            $ownerId ??= $actor?->ownerId();
            if ($ownerId === null && $subject !== null) {
                $ownerId = $subject->getAttribute('user_id') ?: $subject->getAttribute('shop_owner_id');
            }

            return ActivityLog::create([
                'owner_id' => $ownerId,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name ?? $actorName,
                'actor_role' => $actor?->role,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $label !== null ? mb_substr($label, 0, 255) : null,
                'amount' => $amount,
                'properties' => $properties ?: null,
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ActivityLogger failed: ' . $e->getMessage());

            return null;
        }
    }

    private static function enabled(): bool
    {
        if (self::$tableExists === null) {
            self::$tableExists = Schema::hasTable('activity_logs');
        }

        return self::$tableExists;
    }

    /** Test helper: forget the cached "table exists" answer. */
    public static function flushCache(): void
    {
        self::$tableExists = null;
    }
}
