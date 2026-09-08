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
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->boolean('is_bestseller')->default(false)->after('is_featured');
            $table->boolean('is_new_arrival')->default(false)->after('is_bestseller');
            $table->integer('featured_order')->nullable()->after('is_new_arrival');
            $table->integer('bestseller_order')->nullable()->after('featured_order');
            $table->integer('new_arrival_order')->nullable()->after('bestseller_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_featured',
                'is_bestseller',
                'is_new_arrival',
                'featured_order',
                'bestseller_order',
                'new_arrival_order',
            ]);
        });
    }
};

