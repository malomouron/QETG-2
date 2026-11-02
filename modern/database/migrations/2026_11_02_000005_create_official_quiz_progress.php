<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_quiz_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quiz_id');
            $table->foreign('quiz_id')->references('legacy_id')->on('official_quizzes')->cascadeOnDelete();
            $table->unsignedBigInteger('question_id')->nullable();
            $table->foreign('question_id')->references('legacy_id')->on('official_questions')->nullOnDelete();
            $table->unsignedBigInteger('legacy_question_id')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'quiz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_quiz_progress');
    }
};
