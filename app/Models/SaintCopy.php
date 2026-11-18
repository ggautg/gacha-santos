<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ejemplar de un santo en la colección de un jugador.
 *
 * `copies` acumula duplicados (suben el bono de estadísticas) y `level`/`xp`
 * controlan la progresión. `power()` ofrece una cifra orientativa que combina
 * estadísticas, rareza y copias.
 */
class SaintCopy extends Model
{
    protected $fillable = ['player_id', 'saint_id', 'copies', 'level', 'xp'];

    protected $casts = [
        'copies' => 'integer',
        'level' => 'integer',
        'xp' => 'integer',
    ];

    /** Jugador dueño del ejemplar. */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** Santos del catálogo al que pertenece el ejemplar. */
    public function saint(): BelongsTo
    {
        return $this->belongsTo(Saint::class);
    }

    /**
     * Experiencia necesaria para el siguiente nivel, o 0 si ya está al máximo.
     */
    public function xpForNextLevel(): int
    {
        if ($this->level >= config('sanctorum.max_level')) {
            return 0;
        }

        return (int) (60 * $this->level ** 1.35);
    }

    /**
     * Estadísticas de combate del ejemplar según nivel y copias.
     *
     * @return array{hp: int, atk: int, def: int, spd: int}
     */
    public function stats(): array
    {
        return $this->saint->statsForLevel($this->level, $this->copies);
    }

    /**
     * Potencia orientativa del ejemplar en colección y batallas.
     */
    public function power(): int
    {
        $stats = $this->stats();

        return (int) round(
            $stats['hp'] * 0.55
            + $stats['atk'] * 3.1
            + $stats['def'] * 2.2
            + $stats['spd'] * 1.4
            + $this->saint->rarity * 60
        );
    }
}
