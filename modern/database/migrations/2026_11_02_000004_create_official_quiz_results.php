<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_quiz_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quiz_id');
            $table->foreign('quiz_id')->references('legacy_id')->on('official_quizzes')->cascadeOnDelete();
            $table->boolean('favorite')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('legacy_date')->nullable();
            $table->integer('legacy_score')->nullable();
            $table->integer('legacy_favorite')->nullable();
            $table->integer('legacy_quiz_utilisateur')->nullable();
            $table->unique(['user_id', 'quiz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_quiz_results');
    }
};
