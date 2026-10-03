<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * Business-local time helpers.
 *
 * The application (and every stored timestamp) runs in UTC, but a shop's "today" starts at its own local
 * midnight. Use these helpers for day boundaries and for showing times to staff, never change the
 * application timezone (that would shift every timestamp already saved).
 */
class ShopTime
{
    /**
     * IANA timezone of a shop: the owner's own setting, else config('app.shop_timezone'), else UTC.
     * An employee resolves to the timezone of the shop owner it works for.
     */
    public static function timezone(User|int|null $owner = null): string
    {
        $candidate = null;

        if ($owner instanceof User) {
            $ownerId = $owner->ownerId();

            $candidate = $ownerId !== null && $ownerId !== (int) $owner->id
                ? User::withoutGlobalScopes()->whereKey($ownerId)->value('timezone')
                : ($owner->timezone ?? null);
        } elseif (is_int($owner)) {
            $candidate = User::withoutGlobalScopes()->whereKey($owner)->value('timezone');
        }

        $candidate = $candidate ?: config('app.shop_timezone', 'UTC');

        return in_array($candidate, DateTimeZone::listIdentifiers(), true) ? $candidate : 'UTC';
    }

    /**
     * The same instant expressed in the shop's local time.
     */
    public static function local(CarbonInterface|string $time, User|int|null $owner = null): Carbon
    {
        return Carbon::parse($time)->setTimezone(self::timezone($owner));
    }

    /**
     * Local calendar date (Y-m-d) of an instant in the shop.
     */
    public static function localDate(CarbonInterface|string $time, User|int|null $owner = null): string
    {
        return self::local($time, $owner)->toDateString();
    }

    /**
     * "Today" in the shop's local time.
     */
    public static function today(User|int|null $owner = null): string
    {
        return self::localDate(now(), $owner);
    }

    /**
     * UTC [start, end] instants (inclusive) covering one or several local calendar days.
     * Use them in whereBetween() on created_at/occurred_at columns, which are stored in UTC.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function utcRange(string $fromDate, ?string $toDate = null, User|int|null $owner = null): array
    {
        $tz = self::timezone($owner);

        $start = Carbon::parse($fromDate, $tz)->startOfDay()->utc();
        $end = Carbon::parse($toDate ?? $fromDate, $tz)->endOfDay()->utc();

        return [$start, $end];
    }
}
