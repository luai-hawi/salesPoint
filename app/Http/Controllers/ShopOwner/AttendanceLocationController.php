<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShopOwner\Concerns\ResolvesHrOwner;
use App\Models\AttendanceLocation;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AttendanceLocationController extends Controller
{
    use ResolvesHrOwner;

    public function store(Request $request)
    {
        $ownerId = $this->hrOwnerId();
        $validated = $this->validateLocation($request);

        $location = AttendanceLocation::create(array_merge($validated, [
            'user_id' => $ownerId,
            'is_active' => $request->boolean('is_active', true),
        ]));

        ActivityLogger::record('attendance_location.created', AttendanceLocation::class, $location, [
            'name' => $location->name,
        ], label: $location->name, ownerId: $ownerId);

        return back()->with('success', __('hr_owner.location_saved'));
    }

    public function update(Request $request, AttendanceLocation $location)
    {
        $this->authorizeLocation($location);
        $validated = $this->validateLocation($request);

        $location->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
        ]));

        ActivityLogger::record('attendance_location.updated', AttendanceLocation::class, $location, [
            'name' => $location->name,
        ], label: $location->name, ownerId: (int) $location->user_id);

        return back()->with('success', __('hr_owner.location_saved'));
    }

    public function destroy(AttendanceLocation $location)
    {
        $this->authorizeLocation($location);

        ActivityLogger::record('attendance_location.deleted', AttendanceLocation::class, $location, [
            'name' => $location->name,
        ], label: $location->name, ownerId: (int) $location->user_id);

        $location->delete();

        return back()->with('success', __('hr_owner.location_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateLocation(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:20', 'max:1000'],
        ]);
    }
}
