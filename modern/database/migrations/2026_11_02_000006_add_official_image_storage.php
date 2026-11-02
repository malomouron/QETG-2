<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['official_quizzes', 'official_questions', 'official_answers'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('image_storage_key', 78)->nullable();
                $table->string('image_storage_source', 200)->nullable();
                $table->string('image_mime', 30)->nullable();
            });
        }
        Schema::table('official_quizzes', function (Blueprint $table) {
            $table->string('media_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('official_quizzes', function (Blueprint $table) {
            $table->dropColumn('media_hash');
        });
        foreach (['official_quizzes', 'official_questions', 'official_answers'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['image_storage_key', 'image_storage_source', 'image_mime']);
            });
        }
    }
};
