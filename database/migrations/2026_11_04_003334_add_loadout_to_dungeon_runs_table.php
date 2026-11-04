<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dungeon_runs', function (Blueprint $table) {
            $table->json('team')->nullable()->after('route');
            $table->json('vitals')->nullable()->after('team');
            $table->unsignedTinyInteger('blessing')->default(0)->after('vitals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dungeon_runs', function (Blueprint $table) {
            $table->dropColumn(['team', 'vitals', 'blessing']);
        });
    }
};
