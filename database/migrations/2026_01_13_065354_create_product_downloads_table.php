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
        Schema::create('product_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('guest_email')->nullable();
            $table->string('download_token', 64)->unique();
            $table->integer('download_count')->default(0);
            $table->integer('max_downloads')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('download_token');
            $table->index('order_id');
            $table->index('product_id');
            $table->index('customer_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_downloads');
    }
};
