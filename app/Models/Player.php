<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Identidad de la sesión: no hay login, el jugador se reconoce por `token`.
 *
 * Reúne la economía (Puntos de Fe), la garantía de oraciones, el progreso en
 * mazmorras y el equipo seleccionado en formato de array de IDs.
 */
class Player extends Model
{
    protected $fillable = [
        'token', 'name', 'grace', 'pity', 'ten_pity', 'depth',
        'wins', 'losses', 'team',
    ];

    protected $casts = [
        'team' => 'array',
        'grace' => 'integer',
        'pity' => 'integer',
        'ten_pity' => 'integer',
        'depth' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
    ];

    /** Genera un token UUID antes de crear el jugador si no se indicó uno. */
    protected static function booted(): void
    {
        static::creating(function (self $player) {
            $player->token ??= (string) Str::uuid();
        });
    }

    /** Ejemplares de santos que posee el jugador. */
    public function copies(): HasMany
    {
        return $this->hasMany(SaintCopy::class);
    }

    /** Oraciones realizadas por el jugador. */
    public function pulls(): HasMany
    {
        return $this->hasMany(Pull::class);
    }

    /** Récords de profundidad por mazmorra. */
    public function dungeonProgress(): HasMany
    {
        return $this->hasMany(DungeonProgress::class);
    }

    /** Partidas de mazmorra del jugador. */
    public function dungeonRuns(): HasMany
    {
        return $this->hasMany(DungeonRun::class);
    }

    /** Historial de batallas del jugador. */
    public function battles(): HasMany
    {
        return $this->hasMany(Battle::class);
    }

    /**
     * IDs del equipo, limpios y convertidos a enteros.
     *
     * @return array<int, int>
     */
    public function teamIds(): array
    {
        return array_values(array_map('intval', $this->team ?? []));
    }
}
