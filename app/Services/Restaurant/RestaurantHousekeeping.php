<?php

namespace App\Services\Restaurant;

use App\Models\KitchenTicket;
use Carbon\Carbon;

class RestaurantHousekeeping
{
    public function purge(?int $ownerId = null): int
    {
        $cutoff = Carbon::now()->subDays(30);
        $deleted = 0;

        $deleted += $this->purgeByStatus($ownerId, 'served', 'served_at', $cutoff);
        $deleted += $this->purgeByStatus($ownerId, 'cancelled', 'cancelled_at', $cutoff);

        return $deleted;
    }

    private function purgeByStatus(?int $ownerId, string $status, string $timestampColumn, Carbon $cutoff): int
    {
        $deleted = 0;

        $deleted += $this->purgeChunk($ownerId, $status, fn ($query) => $query
            ->whereNotNull($timestampColumn)
            ->where($timestampColumn, '<', $cutoff));

        $deleted += $this->purgeChunk($ownerId, $status, fn ($query) => $query
            ->whereNull($timestampColumn)
            ->where('updated_at', '<', $cutoff));

        return $deleted;
    }

    private function purgeChunk(?int $ownerId, string $status, callable $scope): int
    {
        $deleted = 0;

        do {
            $batch = KitchenTicket::withoutGlobalScopes()
                ->select('id')
                ->where('status', $status)
                ->when($ownerId !== null, fn ($query) => $query->where('user_id', $ownerId))
                ->where($scope)
                ->orderBy('id')
                ->limit(500)
                ->pluck('id');

            if ($batch->isEmpty()) {
                break;
            }

            $deleted += KitchenTicket::withoutGlobalScopes()
                ->whereIn('id', $batch)
                ->delete();
        } while (true);

        return $deleted;
    }
}
