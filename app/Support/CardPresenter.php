<?php

namespace App\Support;

use App\Models\Saint;
use App\Models\SaintCopy;

/**
 * Normaliza las cartas para enviarlas a Inertia (Vue).
 */
final class CardPresenter
{
    public static function artUrl(Saint $saint): string
    {
        return route('art.saint', ['saint' => $saint->slug], false);
    }

    public static function foeUrl(string $key): string
    {
        return route('art.foe', ['key' => $key], false);
    }

    public static function mysteryUrl(): string
    {
        return route('art.mystery', [], false);
    }

    public static function saint(Saint $saint, ?SaintCopy $copy = null): array
    {
        $meta = Saint::RARITIES[$saint->rarity];

        return [
            'id' => $saint->id,
            'slug' => $saint->slug,
            'name' => $saint->name,
            'epithet' => $saint->epithet,
            'rarity' => $saint->rarity,
            'rarity_label' => $meta['label'],
            'rarity_color' => $meta['color'],
            'stars' => $meta['stars'],
            'role' => $saint->role,
            'era' => $saint->era,
            'feast_day' => $saint->feast_day,
            'patronage' => $saint->patronage,
            'region' => $saint->region,
            'quote' => $saint->quote,
            'bio' => $saint->bio,
            'stats' => [
                'virtud' => $saint->virtud,
                'fe' => $saint->fe,
                'caridad' => $saint->caridad,
                'milagro' => $saint->milagro,
            ],
            'skill' => [
                'name' => $saint->skill_name,
                'text' => $saint->skill_text,
                'type' => $saint->skill_type,
                'power' => $saint->skill_power,
            ],
            'base_power' => $saint->basePower(),
            'art' => self::artUrl($saint),
            'owned' => $copy ? self::owned($copy) : null,
            'mystery' => false,
        ];
    }

    /**
     * Carta sin identificar: no revela nombre, rareza, arte ni estadísticas.
     */
    public static function veiled(Saint $saint): array
    {
        return [
            'id' => 'velado-'.substr(md5($saint->slug), 0, 10),
            'slug' => null,
            'name' => 'Santo velado',
            'epithet' => 'El velo aún no se ha rasgado',
            'rarity' => null,
            'rarity_label' => null,
            'rarity_color' => '#4a3f6e',
            'stars' => 0,
            'role' => null,
            'era' => null,
            'feast_day' => null,
            'patronage' => null,
            'region' => null,
            'quote' => 'Su rostro aguarda en el santuario.',
            'bio' => 'Un santo que todavía no has invocado. Su historia permanece oculta tras el velo.',
            'stats' => null,
            'skill' => null,
            'base_power' => null,
            'art' => self::mysteryUrl(),
            'owned' => null,
            'mystery' => true,
        ];
    }

    public static function owned(SaintCopy $copy): array
    {
        return [
            'copies' => $copy->copies,
            'level' => $copy->level,
            'xp' => $copy->xp,
            'xp_next' => $copy->xpForNextLevel(),
            'stats' => $copy->stats(),
            'power' => $copy->power(),
        ];
    }

    public static function unit(SaintCopy $copy): array
    {
        $saint = $copy->saint;
        $stats = $copy->stats();

        return [
            'id' => null,
            'name' => $saint->name,
            'short' => self::shortName($saint->name),
            'level' => $copy->level,
            'rarity' => $saint->rarity,
            'hp' => $stats['hp'],
            'maxHp' => $stats['hp'],
            'atk' => $stats['atk'],
            'def' => $stats['def'],
            'spd' => $stats['spd'],
            'skill_name' => $saint->skill_name,
            'sprite' => self::artUrl($saint),
            'power' => $copy->power(),
        ];
    }

    public static function shortName(string $name): string
    {
        return preg_replace('/^(San|Santa|Santo|Beato|Beata)\s+/u', '', $name) ?: $name;
    }
}
