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
        Schema::table('landing_page_sections', function (Blueprint $table) {
            // Drop the unique index on name first
            $table->dropUnique(['name']);
            // Drop the name column
            $table->dropColumn('name');
            // Add unique constraint to title
            $table->unique('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_page_sections', function (Blueprint $table) {
            // Remove unique constraint from title
            $table->dropUnique(['title']);
            // Add name column back
            $table->string('name')->unique()->after('id');
        });
    }
};
