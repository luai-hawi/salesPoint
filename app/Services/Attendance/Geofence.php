<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLocation;

class Geofence
{
    /**
     * @return array{inside: bool, distance_m: ?int, location_id: ?int, location_name: ?string}
     */
    public function evaluate(int $ownerId, ?float $lat, ?float $lng, ?float $accuracy): array
    {
        if (! $this->validCoordinate($lat, -90, 90) || ! $this->validCoordinate($lng, -180, 180)) {
            return [
                'inside' => false,
                'distance_m' => null,
                'location_id' => null,
                'location_name' => null,
            ];
        }

        $locations = AttendanceLocation::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('is_active', true)
            ->get();

        if ($locations->isEmpty()) {
            return [
                'inside' => false,
                'distance_m' => null,
                'location_id' => null,
                'location_name' => null,
            ];
        }

        $tolerance = 0;
        if (is_numeric($accuracy) && $accuracy !== null && $accuracy >= 0) {
            $settings = AttendanceService::settingsForOwner($ownerId);
            $tolerance = (int) min((float) $accuracy, (float) ($settings['accuracy_tolerance_m'] ?? 50));
        }

        $nearest = null;
        $nearestInside = null;
        foreach ($locations as $location) {
            $distance = (int) round($this->haversine($lat, $lng, (float) $location->latitude, (float) $location->longitude));
            $candidate = [
                'inside' => $distance <= ((int) $location->radius_m + $tolerance),
                'distance_m' => $distance,
                'location_id' => (int) $location->id,
                'location_name' => (string) $location->name,
            ];

            if ($nearest === null || $distance < $nearest['distance_m']) {
                $nearest = $candidate;
            }

            if ($candidate['inside'] && ($nearestInside === null || $distance < $nearestInside['distance_m'])) {
                $nearestInside = $candidate;
            }
        }

        return $nearestInside ?? $nearest ?? [
            'inside' => false,
            'distance_m' => null,
            'location_id' => null,
            'location_name' => null,
        ];
    }

    private function validCoordinate(?float $value, float $min, float $max): bool
    {
        return $value !== null && is_finite($value) && $value >= $min && $value <= $max;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }
}
