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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('text');
            $table->string('description')->nullable();
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('has_refuse_option')->default(false);
            $table->foreignId('branch_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->string('branch_answer')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
