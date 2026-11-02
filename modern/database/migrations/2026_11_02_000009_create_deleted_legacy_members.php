<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_legacy_members', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->primary();
            $table->timestamp('deleted_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback would permit deleted members to be reimported. Restore a verified backup instead.');
    }
};
