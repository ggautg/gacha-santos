<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gospel_verses', function (Blueprint $table) {
            $table->id();
            $table->string('translation', 16);
            $table->string('book', 4);
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('text');
            $table->timestamps();

            $table->unique(['translation', 'book', 'chapter', 'verse']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gospel_verses');
    }
};
