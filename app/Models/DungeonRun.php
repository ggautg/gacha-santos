<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado de la partida de mazmorra actual de un jugador.
 *
 * `seed` hace reproducible el mapa, `route` y `room` señalan la sala activa,
 * y `cleared`, `team` y `vitals` guardan la vida y el equipo entre visitas.
 */
class DungeonRun extends Model
{
    protected $fillable = [
        'player_id', 'dungeon', 'seed', 'floor', 'route', 'room', 'cleared',
        'team', 'vitals', 'blessing',
    ];

    protected $casts = [
        'seed' => 'integer',
        'floor' => 'integer',
        'route' => 'integer',
        'room' => 'integer',
        'cleared' => 'array',
        'team' => 'array',
        'vitals' => 'array',
        'blessing' => 'integer',
    ];

    /** Jugador que está descendiendo. */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
