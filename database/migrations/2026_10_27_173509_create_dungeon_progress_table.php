<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dungeon_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('dungeon', 32);
            $table->unsignedInteger('stage');
            $table->timestamps();

            $table->unique(['player_id', 'dungeon', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dungeon_progress');
    }
};
