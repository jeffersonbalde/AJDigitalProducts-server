<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove columns that are not needed for digital products
            $table->dropColumn([
                'availability',
                'image_type',
                'color',
                'accent_color',
                'stock_quantity',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Restore columns if migration is rolled back
            $table->enum('availability', ['In Stock', 'Low Stock', 'Out of Stock', 'Pre-order'])->default('In Stock')->after('category');
            $table->string('image_type')->nullable()->after('accent_color');
            $table->string('color')->nullable()->after('slug');
            $table->string('accent_color')->nullable()->after('color');
            $table->integer('stock_quantity')->default(0)->after('is_active');
        });
    }
};
