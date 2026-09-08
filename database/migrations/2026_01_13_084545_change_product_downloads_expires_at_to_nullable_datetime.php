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
        Schema::table('product_downloads', function (Blueprint $table) {
            // Change expires_at from TIMESTAMP to nullable DATETIME
            // This allows NULL values for unlimited/no expiration downloads
            $table->dateTime('expires_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_downloads', function (Blueprint $table) {
            // Revert back to non-nullable TIMESTAMP
            // Use a far future date as default (within TIMESTAMP range)
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
