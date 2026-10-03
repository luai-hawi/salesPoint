<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Illuminate\Console\Command;

class CloseStaleAttendance extends Command
{
    protected $signature = 'attendance:close-stale {--owner=}';

    protected $description = 'Close stale open attendance records that exceed the configured shift limit.';

    public function handle(AttendanceService $attendance): int
    {
        $owners = User::withoutGlobalScopes()
            ->whereIn('role', User::OWNER_ROLES)
            ->when($this->option('owner'), fn ($query, $ownerId) => $query->whereKey($ownerId))
            ->get();

        $closed = 0;
        foreach ($owners as $owner) {
            $closed += $attendance->closeStaleForOwner($owner);
        }

        $this->info((string) $closed);

        return self::SUCCESS;
    }
}
