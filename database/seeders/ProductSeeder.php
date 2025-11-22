<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user (assuming there's at least one user)
        $user = User::first();

        if (!$user) {
            $this->command->error('No users found. Please create a user first.');
            return;
        }

        // Create a product that will appear in the warning list
        // This product has been out of stock for 5 months (more than the default 4-month warning period)
        Product::create([
            'name' => 'Warning Test Product',
            'category' => 'Test Category',
            'barcode' => 'WARN123456',
            'quantity' => 5, // Has some stock
            'cost_price' => 10.00,
            'selling_price' => 15.00,
            'has_tags' => false,
            'is_active' => true, // Active product
            'user_id' => $user->id,
            'last_sale_date' => Carbon::now()->subMonths(5), // 5 months ago - triggers warning
            'deactivation_warning_months' => 4, // Default warning period
            'deactivation_months' => 6, // Default deactivation period
            'extended_until' => null, // No extension
            'created_at' => Carbon::now()->subMonths(6), // Created 6 months ago
            'updated_at' => Carbon::now(),
        ]);

        // Create another product that will be nearing deactivation
        Product::create([
            'name' => 'Deactivation Test Product',
            'category' => 'Test Category',
            'barcode' => 'DEACT123456',
            'quantity' => 2,
            'cost_price' => 20.00,
            'selling_price' => 30.00,
            'has_tags' => false,
            'is_active' => true,
            'user_id' => $user->id,
            'last_sale_date' => Carbon::now()->subMonths(7), // 7 months ago
            'deactivation_warning_months' => 4,
            'deactivation_months' => 6,
            'extended_until' => null,
            'created_at' => Carbon::now()->subMonths(8),
            'updated_at' => Carbon::now(),
        ]);

        // Create a normal active product for comparison
        Product::create([
            'name' => 'Active Product',
            'category' => 'Test Category',
            'barcode' => 'ACTIVE123456',
            'quantity' => 10,
            'cost_price' => 5.00,
            'selling_price' => 8.00,
            'has_tags' => false,
            'is_active' => true,
            'user_id' => $user->id,
            'last_sale_date' => Carbon::now()->subDays(30), // Recently sold
            'deactivation_warning_months' => 4,
            'deactivation_months' => 6,
            'extended_until' => null,
            'created_at' => Carbon::now()->subMonths(2),
            'updated_at' => Carbon::now(),
        ]);

        $this->command->info('Test products created successfully!');
        $this->command->info('Warning Test Product: Should appear in warning list (5 months out of stock)');
        $this->command->info('Deactivation Test Product: Should appear in deactivation list (7 months out of stock)');
        $this->command->info('Active Product: Should not appear in warning/deactivation lists');
    }
}