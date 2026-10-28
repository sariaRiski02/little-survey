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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('data_mode')->default('dpt_lengkap')->after('wilayah_label');
            $table->unsignedSmallInteger('sample_confidence')->default(95)->after('data_mode');
            $table->decimal('sample_margin_error', 5, 2)->default(5)->after('sample_confidence');
            $table->decimal('sample_proportion', 4, 3)->default(0.5)->after('sample_margin_error');
            $table->unsignedSmallInteger('sample_reserve')->default(20)->after('sample_proportion');
            $table->unsignedBigInteger('sample_population')->nullable()->after('sample_reserve');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'data_mode',
                'sample_confidence',
                'sample_margin_error',
                'sample_proportion',
                'sample_reserve',
                'sample_population',
            ]);
        });
    }
};
