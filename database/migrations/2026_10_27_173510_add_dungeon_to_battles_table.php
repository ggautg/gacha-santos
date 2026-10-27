<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->string('dungeon', 32)->nullable()->after('stage');

            $table->index(['player_id', 'dungeon']);
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropIndex(['player_id', 'dungeon']);
            $table->dropColumn('dungeon');
        });
    }
};
