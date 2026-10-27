<?php

use App\Http\Controllers\ArtController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DungeonController;
use App\Http\Controllers\GachaController;
use App\Http\Controllers\SanctuaryController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SanctuaryController::class, 'index'])->name('sanctuary');

Route::get('/retratos/velado', [ArtController::class, 'mystery'])->name('art.mystery');
Route::get('/retratos/{saint:slug}', [ArtController::class, 'saint'])->name('art.saint');
Route::get('/enemigos/{key}', [ArtController::class, 'foe'])->name('art.foe');

Route::post('/gacha', [GachaController::class, 'pull'])->name('gacha.pull');

Route::get('/coleccion', [CollectionController::class, 'index'])->name('collection');
Route::post('/santos/{saint:slug}/iluminar', [CollectionController::class, 'levelUp'])->name('saints.level');

Route::get('/equipo', [TeamController::class, 'index'])->name('team');
Route::post('/equipo', [TeamController::class, 'save'])->name('team.save');

Route::get('/mazmorras', [DungeonController::class, 'index'])->name('dungeons');
Route::get('/mazmorras/{dungeon}', [DungeonController::class, 'show'])->name('dungeons.show');
Route::post('/mazmorras/{dungeon}/batalla', [DungeonController::class, 'fight'])->name('dungeons.fight');
Route::post('/mazmorras/{dungeon}/reiniciar', [DungeonController::class, 'restart'])->name('dungeons.restart');
