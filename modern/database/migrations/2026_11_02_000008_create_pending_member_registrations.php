<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_identity_lock', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('member_identity_lock')->insert(['id' => 1]);
        Schema::create('pending_member_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('login', 50);
            $table->string('email', 255);
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('password');
            $table->boolean('newsletter');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_member_registrations');
        Schema::dropIfExists('member_identity_lock');
    }
};
