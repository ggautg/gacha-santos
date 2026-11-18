<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Récord de avance de un jugador en cada mazmorra.
 *
 * `stage` conserva el piso más profundo alcanzado. Es la fila que se bloquea
 * con `lockForUpdate()` durante la generación de salas para evitar duplicados.
 */
class DungeonProgress extends Model
{
    protected $table = 'dungeon_progress';

    protected $fillable = [
        'player_id', 'dungeon', 'stage',
    ];

    protected $casts = [
        'stage' => 'integer',
    ];

    /** Jugador propietario del récord. */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
