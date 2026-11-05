<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dungeon_runs', function (Blueprint $table) {
            $table->unsignedInteger('room')->default(0)->after('route');
            $table->json('cleared')->nullable()->after('room');
        });
    }

    public function down(): void
    {
        Schema::table('dungeon_runs', function (Blueprint $table) {
            $table->dropColumn(['room', 'cleared']);
        });
    }
};
