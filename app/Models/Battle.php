<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Batalla librada por un jugador, con resultado, recompensas y crónica.
 *
 * El campo `log` se serializa como array para reconstruir la animación
 * del combate en el frontend. `dungeon` distingue los duelos del santuario
 * de los encuentros dentro de una mazmorra.
 */
class Battle extends Model
{
    protected $fillable = [
        'player_id', 'stage', 'dungeon', 'enemy_key', 'result',
        'grace_reward', 'xp_reward', 'log',
    ];

    protected $casts = [
        'stage' => 'integer',
        'grace_reward' => 'integer',
        'xp_reward' => 'integer',
        'log' => 'array',
    ];

    /** Jugador que disputó la batalla. */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
