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
        Schema::create('dpts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('nik', 16);
            $table->date('birth_date');
            $table->string('wilayah')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'nik']);
            $table->index(['project_id', 'wilayah']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpts');
    }
};
