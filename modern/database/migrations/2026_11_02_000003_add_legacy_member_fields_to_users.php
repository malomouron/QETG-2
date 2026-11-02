<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->index('email');
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->text('legacy_login')->nullable();
            $table->string('legacy_last_name', 50)->nullable();
            $table->string('legacy_first_name', 50)->nullable();
            $table->integer('legacy_admin')->nullable();
            $table->string('legacy_avatar', 200)->nullable();
            $table->integer('legacy_newsletter')->nullable();
            $table->text('legacy_ip')->nullable();
            $table->string('legacy_registration_date', 50)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is unsafe after importing members. Restore a verified backup instead.');
    }
};
