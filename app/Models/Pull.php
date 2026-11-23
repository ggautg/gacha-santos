<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado de una oración: qué santo salió, con qué rareza y si era nuevo.
 *
 * `batch` agrupa las cartas reveladas en una misma invocación múltiple.
 */
class Pull extends Model
{
    protected $fillable = ['player_id', 'saint_id', 'rarity', 'is_new', 'batch'];

    protected $casts = [
        'is_new' => 'boolean',
        'rarity' => 'integer',
    ];

    /** Santo obtenido en la oración. */
    public function saint(): BelongsTo
    {
        return $this->belongsTo(Saint::class);
    }

    /** Jugador que hizo la oración. */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
