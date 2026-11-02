<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_quizzes', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->primary();
            $table->string('name', 50);
            $table->text('description');
            $table->string('image_path', 200);
            $table->string('image_alt', 300);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_quizzes');
    }
};
