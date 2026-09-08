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
        Schema::create('landing_page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Internal identifier (e.g., 'new_arrivals', 'best_sellers')
            $table->string('title'); // Display title (e.g., 'Our New Arrivals')
            $table->string('source_type'); // 'tag' or 'collection'
            $table->string('source_value'); // Tag name ('new_arrival', 'bestseller', 'featured') or collection slug
            $table->integer('product_count')->default(4); // Number of products to display
            $table->string('display_style')->default('grid'); // 'grid' or 'slider'
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->text('description')->nullable(); // Optional section description
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_sections');
    }
};
