<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->string('legacy_date', 50)->nullable();
            $table->timestamps();
            $table->unique(['follower_id', 'followed_id']);
            $table->index(['followed_id', 'active']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback would discard member subscriptions and unsubscribe markers. Restore a verified backup instead.');
    }
};
