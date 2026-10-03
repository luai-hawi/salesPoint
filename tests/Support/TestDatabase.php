<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Picks how a test gets its database:
 *  - default (SQLite in memory): RefreshDatabase, a fresh schema per test run
 *  - DB_CONNECTION=mysql (a scratch schema such as `salespoint_test` that already contains every migration):
 *    every test runs inside a transaction that is rolled back, so the same suite can be executed against
 *    the real MySQL engine (strict SQL mode, real collations) to catch problems SQLite hides.
 *    MySQL commits implicitly on DDL (a test that runs a migration up()/down()), so rows left behind by such a test
 *    are purged afterwards; this only ever touches schemas whose name ends in _test / _testing.
 */
if (($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: '') === 'mysql') {
    trait TestDatabase
    {
        use DatabaseTransactions {
            beginDatabaseTransaction as private beginTransactionFromTrait;
        }

        public function beginDatabaseTransaction()
        {
            $leaked = false;

            $this->beforeApplicationDestroyed(function () use (&$leaked) {
                $leaked = ! DB::connection()->getPdo()->inTransaction();
            });

            $this->beginTransactionFromTrait();

            $this->beforeApplicationDestroyed(function () use (&$leaked) {
                if ($leaked) {
                    static::purgeLeakedRows();
                }
            });
        }

        protected static function purgeLeakedRows(): void
        {
            $connection = DB::connection();

            if (! preg_match('/_test(ing)?$/', (string) $connection->getDatabaseName())) {
                return;
            }

            $connection->statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($connection->select('SHOW TABLES') as $row) {
                $table = array_values((array) $row)[0];

                if ($table !== 'migrations') {
                    $connection->table($table)->truncate();
                }
            }

            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
} else {
    trait TestDatabase
    {
        use RefreshDatabase;
    }
}
