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
        Schema::create('respondents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dpt_id')->nullable()->unique()->constrained('dpts')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('token', 64)->unique();
            $table->string('name');
            $table->string('nik', 16);
            $table->date('birth_date');
            $table->string('gender')->nullable();
            $table->string('wilayah')->nullable();
            $table->string('social_media')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('link_sent_at')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
            $table->unique(['project_id', 'nik']);
            $table->index(['project_id', 'wilayah']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('respondents');
    }
};
