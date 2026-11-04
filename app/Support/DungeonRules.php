<?php

namespace App\Support;

use App\Models\DungeonProgress;
use App\Models\DungeonRun;
use App\Models\Player;
use App\Models\SaintCopy;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de los descensos procedurales: semillas, caminos, tipos de paso,
 * enemigos, trampas, altares, campamentos y estado del equipo.
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
     * Mazmorras que ya se muestran en el hub.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function visible(): array
    {
        return array_filter(
            self::all(),
            fn (array $dungeon): bool => ($dungeon['visible'] ?? true) !== false,
        );
    }

    public static function isVisible(string $key): bool
    {
        return isset(self::visible()[$key]);
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

        $run = DungeonRun::firstOrCreate(
            ['player_id' => $player->id, 'dungeon' => $key],
            [
                'seed' => random_int(1, 2_147_483_647),
                'floor' => 1,
                'route' => 0,
                'team' => [],
                'vitals' => [],
                'blessing' => 0,
            ],
        );

        return self::syncLoadout($player, $run);
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
        $run->route = 0;
        $run->team = [];
        $run->vitals = [];
        $run->blessing = 0;
        $run->save();

        return self::syncLoadout($player, $run);
    }

    /**
     * Sincroniza el equipo guardado en el descenso con las copias que el jugador posee.
     */
    public static function syncLoadout(Player $player, DungeonRun $run): DungeonRun
    {
        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->get()
            ->keyBy('saint_id');

        $team = array_values(array_filter(
            self::normalizeIds($run->team),
            fn (int $id): bool => $copies->has($id),
        ));

        if (empty($team)) {
            $team = array_values(array_filter(
                array_map('intval', $player->teamIds()),
                fn (int $id): bool => $copies->has($id),
            ));
        }

        $team = array_slice(array_values(array_unique($team)), 0, 3);

        $stored = self::normalizeVitals($run->vitals);
        $vitals = [];

        foreach ($team as $id) {
            $copy = $copies->get($id);
            $max = $copy !== null ? max(1, (int) $copy->stats()['hp']) : 1;
            $hp = array_key_exists($id, $stored) ? (int) $stored[$id] : $max;
            $vitals[$id] = max(0, min($hp, $max));
        }

        if (self::normalizeIds($run->team) !== $team || self::normalizeVitals($run->vitals) != $vitals) {
            $run->team = $team;
            $run->vitals = $vitals;
            $run->save();
        }

        return $run;
    }

    /**
     * @return list<SaintCopy>
     */
    public static function teamCopies(Player $player, DungeonRun $run): array
    {
        $run = self::syncLoadout($player, $run);
        $ids = self::normalizeIds($run->team);

        if (empty($ids)) {
            return [];
        }

        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->whereIn('saint_id', $ids)
            ->get()
            ->keyBy('saint_id');

        $team = [];

        foreach ($ids as $id) {
            $copy = $copies->get($id);

            if ($copy !== null) {
                $team[] = $copy;
            }
        }

        return $team;
    }

    /**
     * @return array<int, array{hp: int, max: int}>
     */
    public static function vitalsFor(Player $player, DungeonRun $run): array
    {
        $run = self::syncLoadout($player, $run);
        $stored = self::normalizeVitals($run->vitals);
        $vitals = [];

        foreach (self::teamCopies($player, $run) as $copy) {
            $max = max(1, (int) $copy->stats()['hp']);
            $hp = (int) ($stored[$copy->saint_id] ?? $max);
            $vitals[$copy->saint_id] = [
                'hp' => max(0, min($hp, $max)),
                'max' => $max,
            ];
        }

        return $vitals;
    }

    /**
     * Estado completo del descenso para la interfaz.
     *
     * @return array<string, mixed>
     */
    public static function state(Player $player, string $key, ?DungeonRun $run = null): array
    {
        $run ??= self::run($player, $key);
        $run = self::syncLoadout($player, $run);

        return [
            'run_floor' => (int) $run->floor,
            'route' => (int) $run->route,
            'team' => self::normalizeIds($run->team),
            'vitals' => self::vitalsFor($player, $run),
            'blessing' => (int) $run->blessing,
            'encounter' => self::currentNode($player, $key, $run),
            'path' => self::pathPreview($player, $key, $run),
            'map' => self::mapInfo($key, (int) $run->seed, (int) $run->floor),
            'best_floor' => self::bestFloor($player, $key),
            'cleared' => self::clearedCount($player, $key),
        ];
    }

    /**
     * Cambia el equipo. Solo se permite en un campamento.
     *
     * @param  array<int, int>  $ids
     */
    public static function setTeam(Player $player, string $key, array $ids): DungeonRun
    {
        $run = self::run($player, $key);
        $node = self::nodeFor($key, $run->seed, $run->floor, $run->route);

        if ($node['kind'] !== 'camp') {
            throw ValidationException::withMessages([
                'team' => 'Solo puedes cambiar de equipo en un campamento.',
            ]);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            throw ValidationException::withMessages([
                'team' => 'Elige al menos un santo antes de continuar.',
            ]);
        }

        if (count($ids) > 3) {
            throw ValidationException::withMessages([
                'team' => 'Solo puedes llevar tres santos al descenso.',
            ]);
        }

        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->whereIn('saint_id', $ids)
            ->get()
            ->keyBy('saint_id');

        if ($copies->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'team' => 'Ese santo no forma parte de tu santoral.',
            ]);
        }

        $stored = self::normalizeVitals($run->vitals);
        $vitals = [];

        foreach ($ids as $id) {
            $max = max(1, (int) $copies->get($id)->stats()['hp']);
            $hp = $stored[$id] ?? $max;
            $vitals[$id] = max(1, min($hp, $max));
        }

        $run->team = $ids;
        $run->vitals = $vitals;
        $run->save();

        return $run;
    }

    /**
     * Paso actual del descenso.
     *
     * @return array<string, mixed>
     */
    public static function currentNode(Player $player, string $key, ?DungeonRun $run = null): array
    {
        $run ??= self::run($player, $key);

        return self::nodeFor($key, $run->seed, $run->floor, $run->route);
    }

    /**
     * Fija el camino elegido para el piso actual.
     */
    public static function chooseRoute(Player $player, string $key, int $branch): DungeonRun
    {
        $run = self::run($player, $key);
        $run->route = max(0, min(self::forkCount($key, $run->seed, $run->floor) - 1, $branch));
        $run->save();

        return $run;
    }

    /**
     * Cantidad de caminos que se abren en una sala: los campamentos, la
     * entrada y la sala del jefe son siempre de paso único.
     */
    public static function forkCount(string $key, int $seed, int $floor): int
    {
        self::get($key);

        $map = self::mapFor($key, $seed, $floor);

        if (in_array($map['offset'], $map['camps'], true) || $map['offset'] === $map['length'] - 1) {
            return 1;
        }

        $roll = (int) hexdec(substr(md5($seed.'|fork|'.$key.'|'.max(1, $floor)), 0, 8));

        return $roll % 100 < 35 ? 1 : 2;
    }

    /**
     * Metadatos del piso (mapa) que se está recorriendo.
     *
     * @return array{index: int, start: int, end: int, length: int, offset: int, room: int, camps: int}
     */
    public static function mapInfo(string $key, int $seed, int $floor): array
    {
        $map = self::mapFor($key, $seed, $floor);

        return [
            'index' => $map['index'],
            'start' => $map['start'],
            'end' => $map['end'],
            'length' => $map['length'],
            'offset' => $map['offset'],
            'room' => $map['offset'] + 1,
            'camps' => count($map['camps']),
        ];
    }

    /**
     * El piso al que pertenece un paso global. Cada piso es un mapa de salas
     * con una longitud procedural y un jefe al fondo, lejos de la entrada.
     *
     * @return array{index: int, start: int, end: int, length: int, offset: int, camps: list<int>}
     */
    public static function mapFor(string $key, int $seed, int $floor): array
    {
        self::get($key);

        $floor = max(1, $floor);
        $index = 1;
        $start = 1;

        while (true) {
            $length = self::mapLength($key, $seed, $index);
            $end = $start + $length - 1;

            if ($floor <= $end) {
                return [
                    'index' => $index,
                    'start' => $start,
                    'end' => $end,
                    'length' => $length,
                    'offset' => $floor - $start,
                    'camps' => self::campOffsets($key, $seed, $index, $length),
                ];
            }

            $start = $end + 1;
            $index++;
        }
    }

    /**
     * Mapa completo del piso actual: entrada, salas intermedias y jefe.
     *
     * @return list<array{floor: int, offset: int, status: string, options: list<array<string, mixed>>}>
     */
    public static function pathPreview(Player $player, string $key, ?DungeonRun $run = null): array
    {
        $run ??= self::run($player, $key);
        $map = self::mapFor($key, (int) $run->seed, (int) $run->floor);
        $rows = [];

        for ($floor = $map['start']; $floor <= $map['end']; $floor++) {
            $options = [];

            foreach (range(0, self::forkCount($key, (int) $run->seed, $floor) - 1) as $branch) {
                $options[] = self::preview(self::nodeFor($key, (int) $run->seed, $floor, $branch));
            }

            $status = $floor < $run->floor ? 'visited' : ($floor === $run->floor ? 'current' : 'future');

            $rows[] = [
                'floor' => $floor,
                'offset' => $floor - $map['start'],
                'status' => $status,
                'options' => $options,
            ];
        }

        return $rows;
    }

    /**
     * Longitud procedural de un piso: entre nueve y trece salas.
     */
    private static function mapLength(string $key, int $seed, int $index): int
    {
        $roll = self::roll($seed, $key, $index, 0, 'map-length');

        return 9 + $roll % 5;
    }

    /**
     * Salas de descanso de un piso: la entrada y, como mucho, dos campamentos
     * intermedios. Nunca hay un campamento junto al jefe.
     *
     * @return list<int>
     */
    private static function campOffsets(string $key, int $seed, int $index, int $length): array
    {
        $last = max(2, $length - 1);
        $shift = self::roll($seed, $key, $index, 0, 'camp-shift') % 3 - 1;
        $offsets = [0];

        if ($length >= 11) {
            $first = max(2, min($last - 4, (int) round($last * 0.33) + $shift));
            $second = max($first + 2, min($last - 2, (int) round($last * 0.67) + $shift));
            $offsets[] = $first;
            $offsets[] = $second;
        } else {
            $offsets[] = max(2, min($last - 2, (int) round($last / 2) + $shift));
        }

        $offsets = array_values(array_unique(array_filter(
            $offsets,
            fn (int $offset): bool => $offset >= 0 && $offset < $last,
        )));

        sort($offsets);

        return $offsets;
    }

    /**
     * Genera la sala de un piso a partir de la semilla del descenso.
     *
     * @return array<string, mixed>
     */
    public static function nodeFor(string $key, int $seed, int $floor, int $route = 0): array
    {
        $dungeon = self::get($key);
        $floor = max(1, $floor);
        $route = max(0, min(self::forkCount($key, $seed, $floor) - 1, $route));
        $map = self::mapFor($key, $seed, $floor);

        if (in_array($map['offset'], $map['camps'], true)) {
            $node = self::campNode($dungeon, $floor, $route);
        } elseif ($map['offset'] === $map['length'] - 1) {
            $node = self::bossNode($key, $dungeon, $seed, $floor, $route);
        } else {
            $roll = self::roll($seed, $key, $floor, $route, 'node') % 100;

            if ($roll < 20) {
                $node = self::altarNode($dungeon, $floor, $route);
            } elseif ($roll < 36) {
                $node = self::trapNode($dungeon, $seed, $floor, $route);
            } else {
                $node = self::battleNode($key, $dungeon, $seed, $floor, $route);
            }
        }

        return [
            ...$node,
            'map_index' => $map['index'],
            'room' => $map['offset'] + 1,
            'rooms' => $map['length'],
        ];
    }

    /**
     * Aplica el efecto de un paso que no es combate y avanza al siguiente.
     *
     * @param  array<int, int>|null  $team
     * @return array<string, mixed>
     */
    public static function resolveStep(Player $player, string $key, string $action, ?array $team = null): array
    {
        $run = self::run($player, $key);

        if ($team !== null) {
            $run = self::setTeam($player, $key, $team);
        }

        $node = self::nodeFor($key, $run->seed, $run->floor, $run->route);

        $outcome = match ($node['kind']) {
            'trap' => self::resolveTrap($player, $run, $node, $action),
            'altar' => self::resolveAltar($player, $run, $node, $action),
            'camp' => self::resolveCamp($player, $run, $node, $action),
            default => throw ValidationException::withMessages([
                'action' => 'Este paso se resuelve con una batalla.',
            ]),
        };

        $run->floor++;
        $run->route = 0;
        $run->save();

        return [
            'outcome' => $outcome,
            'run_floor' => $run->floor,
            'best_floor' => self::bestFloor($player, $key),
            'route' => $run->route,
            'team' => self::normalizeIds($run->team),
            'vitals' => self::vitalsFor($player, $run),
            'blessing' => $run->blessing,
            'encounter' => self::currentNode($player, $key, $run),
            'path' => self::pathPreview($player, $key, $run),
            'map' => self::mapInfo($key, (int) $run->seed, (int) $run->floor),
            'player' => ['grace' => $player->grace],
        ];
    }

    /**
     * Elige un jefe del elenco sin repetir el del piso anterior.
     *
     * @return array<string, mixed>
     */
    public static function bossFor(string $key, int $seed, int $floor, int $route = 0): array
    {
        $dungeon = self::get($key);
        $bosses = $dungeon['bosses'];
        $count = count($bosses);
        $floor = max(1, $floor);

        $index = self::roll($seed, $key, 1, $route) % $count;
        $previousIndex = null;

        for ($step = 2; $step <= $floor; $step++) {
            $previousIndex = $index;
            $next = self::roll($seed, $key, $step, $route) % $count;

            if ($next === $index) {
                $next = ($next + 1) % $count;
            }

            $index = $next;
        }

        // En una bifurcación los dos caminos deben llevar a jefes distintos.
        if ($route > 0) {
            $baseEnemy = self::bossFor($key, $seed, $floor)['enemy'];

            if ($bosses[$index]['enemy'] === $baseEnemy) {
                for ($offset = 1; $offset < $count; $offset++) {
                    $candidate = ($index + $offset) % $count;

                    if ($bosses[$candidate]['enemy'] === $baseEnemy || $candidate === $previousIndex) {
                        continue;
                    }

                    $index = $candidate;
                    break;
                }
            }
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
            'def' => max(0, (int) round(($boss['def'] ?? 0) * $factor('def'))),
            'spd' => min(80, (int) round(($boss['spd'] ?? 1) + (float) ($growth['spd'] ?? 0) * $steps)),
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
            $run->route = 0;
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
     * Nombre legible de un enemigo a partir de su sprite, para las crónicas.
     */
    public static function bossName(string $key, string $enemy): string
    {
        $dungeon = self::get($key);

        foreach (['bosses', 'mobs'] as $pool) {
            foreach ($dungeon[$pool] ?? [] as $entry) {
                if ($entry['enemy'] === $enemy) {
                    return $entry['name'];
                }
            }
        }

        return 'Criatura sin nombre';
    }

    /**
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    private static function campNode(array $dungeon, int $floor, int $route): array
    {
        $camp = $dungeon['camp'] ?? [
            'name' => 'Campamento',
            'subtitle' => 'Un círculo de velas concede una tregua.',
            'rest' => 35,
        ];

        return [
            'floor' => $floor,
            'branch' => $route,
            'kind' => 'camp',
            'name' => $camp['name'],
            'subtitle' => $camp['subtitle'],
            'enemy' => null,
            'sprite' => null,
            'hp' => 0,
            'atk' => 0,
            'def' => 0,
            'spd' => 0,
            'grace' => 0,
            'xp' => 0,
            'rest' => (int) ($camp['rest'] ?? 35),
            'mobs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    private static function altarNode(array $dungeon, int $floor, int $route): array
    {
        $altar = $dungeon['altar'] ?? [
            'name' => 'Altar de la Virgen',
            'subtitle' => 'Una imagen de mármol sostiene una vela que nunca se apaga.',
            'heal' => 40,
            'blessing' => 25,
        ];

        return [
            'floor' => $floor,
            'branch' => $route,
            'kind' => 'altar',
            'name' => $altar['name'],
            'subtitle' => $altar['subtitle'],
            'enemy' => null,
            'sprite' => null,
            'hp' => 0,
            'atk' => 0,
            'def' => 0,
            'spd' => 0,
            'grace' => 0,
            'xp' => 0,
            'heal' => (int) ($altar['heal'] ?? 40),
            'blessing' => (int) ($altar['blessing'] ?? 25),
            'mobs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    private static function trapNode(array $dungeon, int $seed, int $floor, int $route): array
    {
        $traps = $dungeon['traps'] ?? [[
            'name' => 'Pinchos oxidados',
            'subtitle' => 'El suelo cruje bajo tus pies.',
            'effect' => 'damage',
            'power' => 12,
        ]];

        $trap = $traps[self::roll($seed, (string) ($dungeon['theme'] ?? 'dungeon'), $floor, $route, 'trap') % count($traps)];

        return [
            'floor' => $floor,
            'branch' => $route,
            'kind' => 'trap',
            'name' => $trap['name'],
            'subtitle' => $trap['subtitle'],
            'enemy' => null,
            'sprite' => null,
            'hp' => 0,
            'atk' => 0,
            'def' => 0,
            'spd' => 0,
            'grace' => 0,
            'xp' => 0,
            'effect' => $trap['effect'] ?? 'damage',
            'power' => (int) ($trap['power'] ?? 12),
            'mobs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    private static function battleNode(string $key, array $dungeon, int $seed, int $floor, int $route): array
    {
        $mobs = $dungeon['mobs'];

        $sizeRoll = self::roll($seed, $key, $floor, $route, 'group') % 100;
        $size = $floor <= 2 ? 1 : ($sizeRoll < 55 ? 1 : ($sizeRoll < 85 ? 2 : 3));
        $factor = [1 => 1.0, 2 => 0.62, 3 => 0.48][$size];

        $chosen = [];

        for ($i = 0; $i < $size; $i++) {
            $index = (self::roll($seed, $key, $floor, $route, 'mob'.$i) + $i * 3) % count($mobs);
            $chosen[] = self::mobUnit($mobs[$index], $floor, $dungeon, $factor);
        }

        $primary = $chosen[0];
        $name = $size === 1 ? $primary['name'] : ($dungeon['group_name'] ?? 'Hueste de condenados');
        $subtitle = $size === 1
            ? $primary['subtitle']
            : ($dungeon['group_subtitle'] ?? 'Criaturas menores que se arrastran fuera de la grieta');

        return [
            'floor' => $floor,
            'branch' => $route,
            'kind' => 'battle',
            'name' => $name,
            'subtitle' => $subtitle,
            'enemy' => $primary['enemy'],
            'sprite' => $primary['sprite'],
            'hp' => array_sum(array_column($chosen, 'hp')),
            'atk' => max(array_column($chosen, 'atk')),
            'def' => max(array_column($chosen, 'def')),
            'spd' => max(array_column($chosen, 'spd')),
            'skill' => $primary['skill'],
            'skill_name' => $primary['skill_name'],
            'skill_power' => $primary['skill_power'],
            'grace' => array_sum(array_column($chosen, 'grace')),
            'xp' => array_sum(array_column($chosen, 'xp')),
            'mobs' => $chosen,
            'summons' => [],
            'mob_count' => $size,
        ];
    }

    /**
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    private static function bossNode(string $key, array $dungeon, int $seed, int $floor, int $route): array
    {
        $boss = self::bossFor($key, $seed, $floor, $route);
        $scaled = self::scale($boss, $floor, $dungeon);
        $sprite = CardPresenter::foeUrl($boss['enemy']);

        $unit = [
            'name' => $scaled['name'],
            'subtitle' => $scaled['subtitle'],
            'enemy' => $boss['enemy'],
            'hp' => $scaled['hp'],
            'atk' => $scaled['atk'],
            'def' => $scaled['def'],
            'spd' => $scaled['spd'],
            'skill' => $scaled['skill'],
            'skill_name' => $scaled['skill_name'],
            'skill_power' => $scaled['skill_power'],
            'grace' => $scaled['grace'],
            'xp' => $scaled['xp'],
            'sprite' => $sprite,
            'level' => $floor,
        ];

        return [
            'floor' => $floor,
            'branch' => $route,
            'kind' => 'boss',
            'name' => $scaled['name'],
            'subtitle' => $scaled['subtitle'],
            'enemy' => $boss['enemy'],
            'sprite' => $sprite,
            'hp' => $scaled['hp'],
            'atk' => $scaled['atk'],
            'def' => $scaled['def'],
            'spd' => $scaled['spd'],
            'skill' => $scaled['skill'],
            'skill_name' => $scaled['skill_name'],
            'skill_power' => $scaled['skill_power'],
            'grace' => $scaled['grace'],
            'xp' => $scaled['xp'],
            'mobs' => [$unit],
            'summons' => self::summonTemplates($dungeon, $floor),
            'mob_count' => 1,
            'summon_chance' => (int) ($dungeon['summon_chance'] ?? 45),
        ];
    }

    /**
     * @param  array<string, mixed>  $dungeon
     * @return list<array<string, mixed>>
     */
    private static function summonTemplates(array $dungeon, int $floor): array
    {
        $templates = [];
        $mobs = $dungeon['mobs'] ?? [];

        foreach ($dungeon['summons'] ?? [] as $enemy) {
            foreach ($mobs as $mob) {
                if ($mob['enemy'] === $enemy) {
                    $templates[] = self::mobUnit($mob, $floor, $dungeon, 0.6);
                    break;
                }
            }
        }

        return $templates;
    }

    /**
     * @param  array<string, mixed>  $mob
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    private static function mobUnit(array $mob, int $floor, array $dungeon, float $factor = 1.0): array
    {
        $scaled = self::scale($mob, $floor, $dungeon);

        return [
            'name' => $mob['name'],
            'subtitle' => $mob['subtitle'],
            'enemy' => $mob['enemy'],
            'hp' => max(1, (int) round($scaled['hp'] * $factor)),
            'atk' => max(1, (int) round($scaled['atk'] * $factor)),
            'def' => max(0, (int) round($scaled['def'] * $factor)),
            'spd' => max(1, (int) $scaled['spd']),
            'skill' => $mob['skill'],
            'skill_name' => $mob['skill_name'],
            'skill_power' => $mob['skill_power'],
            'grace' => max(1, (int) round($scaled['grace'] * $factor)),
            'xp' => max(1, (int) round($scaled['xp'] * $factor)),
            'sprite' => CardPresenter::foeUrl($mob['enemy']),
            'level' => $floor,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function preview(array $node): array
    {
        return [
            'branch' => $node['branch'],
            'floor' => $node['floor'],
            'kind' => $node['kind'],
            'name' => $node['name'],
            'subtitle' => $node['subtitle'],
            'enemy' => $node['enemy'] ?? null,
            'sprite' => $node['sprite'] ?? null,
            'hp' => $node['hp'] ?? 0,
            'atk' => $node['atk'] ?? 0,
            'grace' => $node['grace'] ?? 0,
            'xp' => $node['xp'] ?? 0,
            'mob_count' => count($node['mobs'] ?? []),
            'rest' => $node['rest'] ?? 0,
            'heal' => $node['heal'] ?? 0,
            'blessing' => $node['blessing'] ?? 0,
            'effect' => $node['effect'] ?? null,
            'power' => $node['power'] ?? 0,
            'map_index' => $node['map_index'] ?? 1,
            'room' => $node['room'] ?? 1,
            'rooms' => $node['rooms'] ?? 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveTrap(Player $player, DungeonRun $run, array $node, string $action): array
    {
        if ($action !== 'cross') {
            throw ValidationException::withMessages([
                'action' => 'Solo puedes cruzar la trampa.',
            ]);
        }

        if (($node['effect'] ?? 'damage') === 'grace') {
            $loss = max(10, (int) round($player->grace * ((int) $node['power'] / 100)));
            $loss = min($loss, $player->grace);
            $player->grace = max(0, $player->grace - $loss);
            $player->save();

            return [
                'type' => 'grace',
                'text' => $node['name'].': pierdes '.$loss.' Puntos de Fe.',
                'amount' => $loss,
            ];
        }

        $vitals = self::normalizeVitals($run->vitals);
        $copies = collect(self::teamCopies($player, $run))->keyBy('saint_id');
        $lost = 0;

        foreach ($vitals as $id => $hp) {
            $max = max(1, (int) ($copies->get($id)?->stats()['hp'] ?? 1));
            $damage = (int) round($max * ((int) $node['power'] / 100));
            $before = $hp;
            $vitals[$id] = max(1, $hp - $damage);
            $lost += $before - $vitals[$id];
        }

        $run->vitals = $vitals;
        $run->save();

        return [
            'type' => 'damage',
            'text' => $node['name'].': el equipo pierde '.$lost.' de vida.',
            'amount' => $lost,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveAltar(Player $player, DungeonRun $run, array $node, string $action): array
    {
        if ($action === 'bless') {
            $run->blessing = max((int) $run->blessing, (int) $node['blessing']);
            $run->save();

            return [
                'type' => 'blessing',
                'text' => 'La Virgen bendice al equipo: +'.$node['blessing'].'% de ataque en la próxima batalla.',
                'amount' => (int) $node['blessing'],
            ];
        }

        if ($action !== 'heal') {
            throw ValidationException::withMessages([
                'action' => 'Elige entre curación o bendición.',
            ]);
        }

        $vitals = self::normalizeVitals($run->vitals);
        $copies = collect(self::teamCopies($player, $run))->keyBy('saint_id');
        $healed = 0;

        foreach ($vitals as $id => $hp) {
            $max = max(1, (int) ($copies->get($id)?->stats()['hp'] ?? 1));
            $before = $hp;
            $vitals[$id] = min($max, $hp + (int) round($max * ((int) $node['heal'] / 100)));
            $healed += $vitals[$id] - $before;
        }

        $run->vitals = $vitals;
        $run->save();

        return [
            'type' => 'heal',
            'text' => 'La Virgen venda las heridas: +'.$healed.' de vida.',
            'amount' => $healed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveCamp(Player $player, DungeonRun $run, array $node, string $action): array
    {
        if ($action !== 'rest') {
            throw ValidationException::withMessages([
                'action' => 'En el campamento solo puedes descansar.',
            ]);
        }

        $vitals = self::normalizeVitals($run->vitals);
        $copies = collect(self::teamCopies($player, $run))->keyBy('saint_id');
        $healed = 0;

        foreach ($vitals as $id => $hp) {
            $max = max(1, (int) ($copies->get($id)?->stats()['hp'] ?? 1));
            $before = $hp;
            $vitals[$id] = min($max, $hp + (int) round($max * ((int) $node['rest'] / 100)));
            $healed += $vitals[$id] - $before;
        }

        $run->vitals = $vitals;
        $run->save();

        return [
            'type' => 'camp',
            'text' => 'El equipo descansa junto a las velas: +'.$healed.' de vida.',
            'amount' => $healed,
        ];
    }

    /**
     * @return list<int>
     */
    private static function normalizeIds(mixed $ids): array
    {
        return array_values(array_map('intval', (array) $ids));
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeVitals(mixed $vitals): array
    {
        $normalized = [];

        foreach ((array) $vitals as $id => $hp) {
            $normalized[(int) $id] = (int) $hp;
        }

        return $normalized;
    }

    private static function roll(int $seed, string $key, int $floor, int $route = 0, string $tag = ''): int
    {
        $salt = $route > 0 ? '|r'.$route : '';
        $salt .= $tag !== '' ? '|'.$tag : '';

        return (int) hexdec(substr(md5($seed.'|'.$key.'|'.$floor.$salt), 0, 8));
    }
}
