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
        // bill_product - Sales transactions (most important for selling half boxes)
        DB::statement('ALTER TABLE bill_product MODIFY quantity DECIMAL(8,2)');

        // products - Current stock
        DB::statement('ALTER TABLE products MODIFY quantity DECIMAL(8,2)');

        // batches - Inventory batches
        DB::statement('ALTER TABLE batches MODIFY quantity DECIMAL(8,2)');

        // purchase_bill_product - Purchases
        DB::statement('ALTER TABLE purchase_bill_product MODIFY quantity DECIMAL(8,2)');
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
