<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('landing_page_sections', function (Blueprint $table) {
            $table->string('section_type')->default('product_grid')->after('title');
            $table->json('config')->nullable()->after('description');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->after('is_active');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->timestamp('starts_at')->nullable()->after('published_at');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
        });

        // Use raw SQL to modify existing columns (doesn't require doctrine/dbal)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE landing_page_sections MODIFY COLUMN source_type VARCHAR(255) NULL');
            DB::statement('ALTER TABLE landing_page_sections MODIFY COLUMN source_value VARCHAR(255) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_page_sections', function (Blueprint $table) {
            $table->dropColumn(['section_type', 'config', 'status', 'published_at', 'starts_at', 'ends_at']);
        });

        // Use raw SQL to revert column changes
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE landing_page_sections MODIFY COLUMN source_type VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE landing_page_sections MODIFY COLUMN source_value VARCHAR(255) NOT NULL');
        }
    }
};
