<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->primary();
            $table->unsignedBigInteger('quiz_id');
            $table->foreign('quiz_id')->references('legacy_id')->on('official_quizzes')->cascadeOnDelete();
            $table->integer('number');
            $table->text('introduction')->nullable();
            $table->text('text');
            $table->string('difficulty')->nullable();
            $table->string('image_path', 200)->nullable();
            $table->string('image_alt', 300)->nullable();
            $table->index(['quiz_id', 'number']);
        });

        Schema::create('official_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->primary();
            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->references('legacy_id')->on('official_questions')->cascadeOnDelete();
            $table->integer('number')->nullable();
            $table->string('text', 200);
            $table->boolean('correct');
            $table->text('explanation')->nullable();
            $table->string('image_path', 200)->nullable();
            $table->string('image_alt', 300)->nullable();
            $table->index(['question_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_answers');
        Schema::dropIfExists('official_questions');
    }
};
