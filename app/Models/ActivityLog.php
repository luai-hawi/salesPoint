<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail of what people did in a shop (and what the platform admin did).
 * Not tenant-scoped on purpose: query it explicitly by owner_id.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'owner_id', 'actor_id', 'actor_name', 'actor_role', 'action',
        'subject_type', 'subject_id', 'subject_label', 'amount', 'properties', 'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Human readable, translated sentence for this entry.
     */
    public function describe(): string
    {
        $subjectType = (string) $this->subject_type;
        $key = 'activity.events.' . $subjectType . '.' . $this->action;
        $params = array_merge($this->properties ?? [], [
            'id' => $this->subject_id,
            'label' => $this->subject_label,
            'amount' => $this->amount !== null ? number_format((float) $this->amount, 2) : null,
        ]);
        $params = array_map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value), $params);

        $translated = __($key, $params);
        if ($translated !== $key) {
            return $translated;
        }

        return __('activity.events.generic', [
            'action' => __('activity.actions.' . $this->action),
            'subject' => __('activity.subjects.' . $subjectType),
            'label' => $this->subject_label ?? ('#' . $this->subject_id),
        ]);
    }
}
