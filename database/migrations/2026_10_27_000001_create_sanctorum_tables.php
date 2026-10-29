<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saints', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('epithet');
            $table->unsignedTinyInteger('rarity');
            $table->string('role', 32);
            $table->string('era', 64)->nullable();
            $table->string('feast_day', 64)->nullable();
            $table->string('patronage')->nullable();
            $table->string('region')->nullable();
            $table->string('quote', 255)->nullable();
            $table->text('bio');
            $table->json('art');
            $table->unsignedSmallInteger('virtud');
            $table->unsignedSmallInteger('fe');
            $table->unsignedSmallInteger('caridad');
            $table->unsignedSmallInteger('milagro');
            $table->string('skill_name');
            $table->string('skill_text', 255);
            $table->string('skill_type', 24);
            $table->unsignedTinyInteger('skill_power');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['rarity', 'sort']);
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('name', 32)->default('Peregrino');
            $table->unsignedInteger('grace')->default(0);
            $table->unsignedTinyInteger('pity')->default(0);
            $table->unsignedTinyInteger('ten_pity')->default(0);
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->timestamp('free_pull_at')->nullable();
            $table->json('team')->nullable();
            $table->timestamps();
        });

        Schema::create('saint_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saint_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('copies')->default(1);
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('xp')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'saint_id']);
        });

        Schema::create('pulls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saint_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rarity');
            $table->boolean('is_new')->default(true);
            $table->uuid('batch');
            $table->timestamps();

            $table->index(['player_id', 'created_at']);
        });

        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stage');
            $table->string('enemy_key', 48);
            $table->string('result', 16);
            $table->unsignedInteger('grace_reward')->default(0);
            $table->unsignedInteger('xp_reward')->default(0);
            $table->json('log');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battles');
        Schema::dropIfExists('pulls');
        Schema::dropIfExists('saint_copies');
        Schema::dropIfExists('players');
        Schema::dropIfExists('saints');
    }
};
