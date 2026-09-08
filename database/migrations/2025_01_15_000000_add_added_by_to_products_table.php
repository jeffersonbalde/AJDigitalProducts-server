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
            $table->unsignedBigInteger('added_by_user_id')->nullable()->after('is_active');
            $table->string('added_by_user_type')->nullable()->after('added_by_user_id'); // 'admin' or 'personnel'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['added_by_user_id', 'added_by_user_type']);
        });
    }
};

