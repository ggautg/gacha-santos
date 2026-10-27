<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Saint extends Model
{
    public const RARITIES = [
        1 => ['label' => 'Común', 'color' => '#9aa5b1', 'stars' => 1],
        2 => ['label' => 'Raro', 'color' => '#5bc0eb', 'stars' => 2],
        3 => ['label' => 'Épico', 'color' => '#b07cf0', 'stars' => 3],
        4 => ['label' => 'Legendario', 'color' => '#f2994a', 'stars' => 4],
        5 => ['label' => 'Milagroso', 'color' => '#f2c94c', 'stars' => 5],
    ];

    protected $fillable = [
        'slug', 'name', 'epithet', 'rarity', 'role', 'era', 'feast_day',
        'patronage', 'region', 'quote', 'bio', 'art', 'virtud', 'fe',
        'caridad', 'milagro', 'skill_name', 'skill_text', 'skill_type',
        'skill_power', 'sort',
    ];

    protected $casts = [
        'art' => 'array',
        'rarity' => 'integer',
        'virtud' => 'integer',
        'fe' => 'integer',
        'caridad' => 'integer',
        'milagro' => 'integer',
        'skill_power' => 'integer',
    ];

    public function copies(): HasMany
    {
        return $this->hasMany(SaintCopy::class);
    }

    public function rarityLabel(): string
    {
        return self::RARITIES[$this->rarity]['label'];
    }

    public function rarityColor(): string
    {
        return self::RARITIES[$this->rarity]['color'];
    }

    public function scopeByRarity(Builder $query, int $rarity): Builder
    {
        return $query->where('rarity', $rarity);
    }

    /**
     * Potencia orientativa usada en la colección y las batallas.
     */
    public function basePower(): int
    {
        return (int) round(
            $this->virtud * 2.2
            + $this->fe * 1.6
            + $this->caridad * 1.3
            + $this->milagro * 1.1
        );
    }

    public function statsForLevel(int $level, int $copies = 1): array
    {
        $growth = 1 + 0.06 * ($level - 1);
        $copyBonus = 1 + min(max($copies - 1, 0), 5) * 0.03;

        return [
            'hp' => (int) round((70 + $this->fe * 3.2) * $growth * $copyBonus),
            'atk' => (int) round((6 + $this->virtud * 1.35) * $growth * $copyBonus),
            'def' => (int) round($this->caridad * 0.8 * $growth * $copyBonus),
            'spd' => (int) round(8 + $this->milagro * 0.6 + ($level - 1) * 0.8),
        ];
    }
}
