<?php

namespace App\Support;

use App\Models\DungeonProgress;
use App\Models\DungeonRun;
use App\Models\Player;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de los descensos procedurales: semillas, jefes al azar, escalado de
 * estadísticas, desbloqueos y progreso por piso.
 */
final class DungeonRules
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return config('sanctorum.dungeons', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        $dungeon = self::all()[$key] ?? null;

        if ($dungeon === null) {
            throw ValidationException::withMessages([
                'dungeon' => 'Esa mazmorra no existe.',
            ]);
        }

        return $dungeon;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * Semilla del descenso activo. Se crea la primera vez que se entra.
     */
    public static function run(Player $player, string $key): DungeonRun
    {
        self::get($key);

        return DungeonRun::firstOrCreate(
            ['player_id' => $player->id, 'dungeon' => $key],
            ['seed' => random_int(1, 2_147_483_647), 'floor' => 1],
        );
    }

    /**
     * Reinicia el descenso con una semilla nueva sin borrar el mejor piso.
     */
    public static function restart(Player $player, string $key): DungeonRun
    {
        self::get($key);

        $run = self::run($player, $key);
        $run->seed = random_int(1, 2_147_483_647);
        $run->floor = 1;
        $run->save();

        return $run;
    }

    /**
     * Jefe del piso actual (o del piso indicado) con sus estadísticas escaladas.
     *
     * @return array<string, mixed>
     */
    public static function encounter(Player $player, string $key, ?int $floor = null, ?DungeonRun $run = null): array
    {
        $run ??= self::run($player, $key);
        $floor = max(1, $floor ?? $run->floor);

        return self::encounterFor($key, $run->seed, $floor);
    }

    /**
     * Genera el encuentro de un piso a partir de la semilla del descenso.
     *
     * @return array<string, mixed>
     */
    public static function encounterFor(string $key, int $seed, int $floor): array
    {
        $dungeon = self::get($key);
        $boss = self::bossFor($key, $seed, $floor);
        $scaled = self::scale($boss, $floor, $dungeon);

        return [
            ...$scaled,
            'floor' => $floor,
            'enemy' => $boss['enemy'],
            'skill' => $boss['skill'],
            'sprite' => CardPresenter::foeUrl($boss['enemy']),
        ];
    }

    /**
     * Elige un jefe del elenco sin repetir el del piso anterior.
     *
     * @return array<string, mixed>
     */
    public static function bossFor(string $key, int $seed, int $floor): array
    {
        $dungeon = self::get($key);
        $bosses = $dungeon['bosses'];
        $count = count($bosses);
        $floor = max(1, $floor);

        $index = self::roll($seed, $key, 1) % $count;

        for ($step = 2; $step <= $floor; $step++) {
            $next = self::roll($seed, $key, $step) % $count;

            if ($next === $index) {
                $next = ($next + 1) % $count;
            }

            $index = $next;
        }

        return $bosses[$index];
    }

    /**
     * @param  array<string, mixed>  $boss
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    public static function scale(array $boss, int $floor, array $dungeon): array
    {
        $growth = $dungeon['scaling'] ?? [];
        $steps = max(0, $floor - 1);
        $factor = fn (string $field): float => 1 + (float) ($growth[$field] ?? 0) * $steps;

        return [
            ...$boss,
            'name' => $boss['name'],
            'subtitle' => $boss['subtitle'],
            'hp' => max(1, (int) round($boss['hp'] * $factor('hp'))),
            'atk' => max(1, (int) round($boss['atk'] * $factor('atk'))),
            'def' => max(0, (int) round($boss['def'] * $factor('def'))),
            'spd' => min(80, (int) round($boss['spd'] + (float) ($growth['spd'] ?? 0) * $steps)),
            'grace' => max(1, (int) round($boss['grace'] * $factor('grace'))),
            'xp' => max(1, (int) round($boss['xp'] * $factor('xp'))),
        ];
    }

    /**
     * Piso más profundo alcanzado (los pisos ya limpiados).
     */
    public static function bestFloor(Player $player, string $key): int
    {
        return (int) DungeonProgress::where('player_id', $player->id)
            ->where('dungeon', $key)
            ->max('stage');
    }

    public static function clearedCount(Player $player, string $key): int
    {
        return DungeonProgress::where('player_id', $player->id)
            ->where('dungeon', $key)
            ->count();
    }

    public static function floorCleared(Player $player, string $key, int $floor): bool
    {
        return DungeonProgress::where('player_id', $player->id)
            ->where('dungeon', $key)
            ->where('stage', $floor)
            ->exists();
    }

    /**
     * Marca un piso como limpiado y avanza el descenso si corresponde.
     */
    public static function markCleared(Player $player, string $key, int $floor, ?DungeonRun $run = null): DungeonRun
    {
        DungeonProgress::firstOrCreate([
            'player_id' => $player->id,
            'dungeon' => $key,
            'stage' => $floor,
        ]);

        $run ??= self::run($player, $key);

        if ($run->floor <= $floor) {
            $run->floor = $floor + 1;
            $run->save();
        }

        return $run;
    }

    public static function isUnlocked(Player $player, string $key): bool
    {
        $unlock = self::get($key)['unlock'] ?? ['type' => 'always'];

        return match ($unlock['type']) {
            'dungeon' => self::bestFloor($player, (string) $unlock['dungeon']) >= (int) $unlock['value'],
            default => true,
        };
    }

    public static function unlockText(string $key): string
    {
        $unlock = self::get($key)['unlock'] ?? ['type' => 'always'];

        return match ($unlock['type']) {
            'dungeon' => 'Se abre al alcanzar el piso '.$unlock['value'].' de '.
                (self::all()[$unlock['dungeon']]['name'] ?? 'otra mazmorra').'.',
            default => 'Siempre abierta.',
        };
    }

    /**
     * Nombre legible de un jefe a partir de su sprite, para las crónicas.
     */
    public static function bossName(string $key, string $enemy): string
    {
        foreach (self::get($key)['bosses'] as $boss) {
            if ($boss['enemy'] === $enemy) {
                return $boss['name'];
            }
        }

        return 'Criatura sin nombre';
    }

    private static function roll(int $seed, string $key, int $floor): int
    {
        return (int) hexdec(substr(md5($seed.'|'.$key.'|'.$floor), 0, 8));
    }
}
