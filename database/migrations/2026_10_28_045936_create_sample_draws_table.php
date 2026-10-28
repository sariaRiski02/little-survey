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
        Schema::create('sample_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seed');
            $table->unsignedInteger('population');
            $table->unsignedInteger('sample_size');
            $table->unsignedInteger('invitation_count');
            $table->unsignedInteger('selected_count');
            $table->json('parameters');
            $table->text('reason')->nullable();
            $table->timestamp('drawn_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sample_draws');
    }
};
