<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change quantity columns from integer to decimal to allow half units (e.g., 0.5 boxes of eggs)
     * Using raw SQL for MySQL - no Doctrine DBAL required
     */
    public function up()
    {
        // Disable strict mode for this session to handle conversions on shared hosting
        DB::statement('SET SESSION sql_mode = ""');

        // Convert from INT to DECIMAL(20,2) - this can hold any integer value safely
        // We keep it at 20,2 (up to 99,999,999,999,999,999.99) which is plenty for any quantity
        // Disabling strict mode avoids issues with out-of-range values during conversion

        // bill_product - Sales transactions
        DB::statement('ALTER TABLE bill_product MODIFY quantity DECIMAL(20,2)');

        // products - Current stock
        DB::statement('ALTER TABLE products MODIFY quantity DECIMAL(20,2)');

        // batches - Inventory batches
        DB::statement('ALTER TABLE batches MODIFY quantity DECIMAL(20,2)');

        // purchase_bill_product - Purchases
        DB::statement('ALTER TABLE purchase_bill_product MODIFY quantity DECIMAL(20,2)');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Revert back to integer if needed
        DB::statement('ALTER TABLE bill_product MODIFY quantity INT');
        DB::statement('ALTER TABLE products MODIFY quantity INT');
        DB::statement('ALTER TABLE batches MODIFY quantity INT');
        DB::statement('ALTER TABLE purchase_bill_product MODIFY quantity INT');
    }
};
