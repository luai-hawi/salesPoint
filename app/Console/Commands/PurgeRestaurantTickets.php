<?php

namespace App\Console\Commands;

use App\Services\Restaurant\RestaurantHousekeeping;
use Illuminate\Console\Command;

class PurgeRestaurantTickets extends Command
{
    protected $signature = 'restaurant:purge-tickets {--owner=}';

    protected $description = 'Delete served or cancelled kitchen tickets older than 30 days';

    public function handle(RestaurantHousekeeping $housekeeping): int
    {
        $count = $housekeeping->purge($this->option('owner') ? (int) $this->option('owner') : null);

        $this->line("Purged {$count} ticket(s).");

        return self::SUCCESS;
    }
}
