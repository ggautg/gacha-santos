<?php

namespace App\Support;

use App\Models\DungeonProgress;
use App\Models\DungeonRun;
use App\Models\GospelVerse;
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
                'room' => 0,
                'cleared' => [],
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
        $run->room = 0;
        $run->cleared = [];
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
            'team' => self::normalizeIds($run->team),
            'vitals' => self::vitalsFor($player, $run),
            'blessing' => (int) $run->blessing,
            'encounter' => self::currentNode($player, $key, $run),
            'map' => self::mapView($key, $run),
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
        $node = self::currentNode($player, $key, $run);

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

        return self::roomNode($key, (int) $run->seed, (int) $run->floor, (int) $run->room);
    }

    /**
     * Resuelve el efecto de una sala que no es combate (trampa, altar,
     * tesoro o campamento) y marca la sala como resuelta.
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

        $node = self::currentNode($player, $key, $run);

        $outcome = match ($node['kind']) {
            'trap' => self::resolveTrap($player, $run, $node, $action),
            'altar' => self::resolveAltar($player, $run, $node, $action),
            'camp' => self::resolveCamp($player, $run, $node, $action),
            'treasure' => self::resolveTreasure($player, $run, $node, $action),
            'gospel' => self::resolveGospel($player, $run, $node, $action),
            default => throw ValidationException::withMessages([
                'action' => 'Este paso se resuelve con una batalla.',
            ]),
        };

        self::markRoomCleared($run, (int) $node['room']);

        return [
            'outcome' => $outcome,
            'run_floor' => (int) $run->floor,
            'best_floor' => self::bestFloor($player, $key),
            'room' => (int) $run->room,
            'team' => self::normalizeIds($run->team),
            'vitals' => self::vitalsFor($player, $run),
            'blessing' => (int) $run->blessing,
            'encounter' => self::currentNode($player, $key, $run),
            'map' => self::mapView($key, $run),
            'player' => ['grace' => $player->grace],
        ];
    }

    /**
     * Sala de un piso. El laberinto se deriva de la semilla y del piso, así que
     * no hace falta guardarlo: solo la posición y las salas resueltas.
     *
     * @return array<string, mixed>
     */
    public static function roomNode(string $key, int $seed, int $floor, int $room): array
    {
        $dungeon = self::get($key);
        $floor = max(1, $floor);
        $layout = self::layout($key, $seed, $floor);
        $cell = $layout['rooms'][$room] ?? $layout['rooms'][$layout['entrance']];

        $node = match ($cell['kind']) {
            'camp' => self::campNode($dungeon, $floor, $cell['id']),
            'boss' => self::bossNode($key, $dungeon, $seed, $floor, 0),
            'altar' => self::altarNode($dungeon, $floor, $cell['id']),
            'trap' => self::trapNode($dungeon, $seed, $floor, $cell['id']),
            'treasure' => self::treasureNode($dungeon, $floor, $cell['id']),
            'gospel' => self::gospelNode($key, $dungeon, $seed, $floor, $cell['id']),
            default => self::battleNode($key, $dungeon, $seed, $floor, $cell['id']),
        };

        return [
            ...$node,
            'room' => $cell['id'],
            'x' => $cell['x'],
            'y' => $cell['y'],
            'rooms' => count($layout['rooms']),
        ];
    }

    /**
     * Laberinto horizontal de un piso: salas conectadas, entrada en un extremo
     * y un jefe escondido en la sala más alejada.
     *
     * @var array<string, array{width: int, height: int, entrance: int, boss: int, rooms: array<int, array<string, mixed>>}>
     */
    private static array $layouts = [];

    /**
     * @return array{width: int, height: int, entrance: int, boss: int, rooms: array<int, array<string, mixed>>}
     */
    public static function layout(string $key, int $seed, int $floor): array
    {
        self::get($key);

        $floor = max(1, $floor);
        $cacheKey = $key.'|'.$seed.'|'.$floor;

        if (isset(self::$layouts[$cacheKey])) {
            return self::$layouts[$cacheKey];
        }

        $rng = self::rng($seed, $key, $floor);
        $width = 9 + $rng(5);
        $height = 3 + $rng(3);
        $index = fn (int $x, int $y): int => $y * $width + $x;
        // La entrada puede caer en cualquier columna: borde, mitad o fondo.
        $entrance = $index($rng($width), $rng($height));
        $bias = $rng(2) === 0 ? 1 : -1;

        $links = [$entrance => []];
        $inside = [$entrance];
        $target = min($width * $height, (int) floor($width * $height * 0.55) + $rng(5));
        $stalled = 0;

        // Árbol que crece sobre todo hacia la derecha: el piso se extiende en horizontal.
        while (count($inside) < $target && $stalled < 1000) {
            $stalled++;
            $from = $inside[$rng(count($inside))];
            $x = $from % $width;
            $y = intdiv($from, $width);
            $options = [];

            foreach ([[$bias, 0], [$bias, 0], [0, -1], [0, 1], [-$bias, 0]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx < 0 || $nx >= $width || $ny < 0 || $ny >= $height || isset($links[$index($nx, $ny)])) {
                    continue;
                }

                $options[] = $index($nx, $ny);
            }

            if ($options === []) {
                continue;
            }

            $to = $options[$rng(count($options))];
            $links[$to] = [$from];
            $links[$from][] = $to;
            $inside[] = $to;
            $stalled = 0;
        }

        // Algunos atajos rompen el árbol y dan formas de laberinto.
        for ($loops = $rng(4), $attempt = 0; $loops > 0 && $attempt < 40; $attempt++) {
            $a = $inside[$rng(count($inside))];
            [$dx, $dy] = [[1, 0], [-1, 0], [0, 1], [0, -1]][$rng(4)];
            $nx = $a % $width + $dx;
            $ny = intdiv($a, $width) + $dy;

            if ($nx < 0 || $nx >= $width || $ny < 0 || $ny >= $height) {
                continue;
            }

            $b = $index($nx, $ny);

            if (! isset($links[$b]) || in_array($b, $links[$a], true)) {
                continue;
            }

            $links[$a][] = $b;
            $links[$b][] = $a;
            $loops--;
        }

        $fromEntrance = self::distances($links, $entrance);
        $maxDistance = max($fromEntrance);
        $farthest = array_keys(array_filter(
            $fromEntrance,
            fn (int $distance): bool => $distance >= max(2, $maxDistance - 1),
        ));
        $boss = $farthest[$rng(count($farthest))];
        $fromBoss = self::distances($links, $boss);

        // Campamentos limitados: la entrada y, como mucho, dos más a mitad de camino.
        $camps = [$entrance];

        foreach ([0.4, 0.75] as $ratio) {
            $wanted = max(2, (int) round($maxDistance * $ratio));
            $candidates = array_values(array_filter(
                array_keys($fromEntrance),
                fn (int $id): bool => $fromEntrance[$id] === $wanted
                    && $id !== $boss
                    && $fromBoss[$id] >= 2
                    && ! in_array($id, $camps, true),
            ));

            if ($candidates !== []) {
                $camps[] = $candidates[$rng(count($candidates))];
            }
        }

        $rooms = [];

        foreach (array_keys($links) as $id) {
            if ($id === $boss) {
                $kind = 'boss';
            } elseif (in_array($id, $camps, true)) {
                $kind = 'camp';
            } else {
                $roll = $rng(100);
                $kind = match (true) {
                    $roll < 40 => 'battle',
                    $roll < 52 => 'treasure',
                    $roll < 64 => 'gospel',
                    $roll < 78 => 'altar',
                    default => 'trap',
                };
            }

            $rooms[$id] = [
                'id' => $id,
                'x' => $id % $width,
                'y' => intdiv($id, $width),
                'kind' => $kind,
                'links' => array_values($links[$id]),
            ];
        }

        // La entrada siempre es la sala 0: así el descenso arranca ahí sin más datos.
        $order = [$entrance, ...array_values(array_diff(array_keys($rooms), [$entrance]))];
        $renumber = array_flip($order);
        $numbered = [];

        foreach ($order as $old) {
            $room = $rooms[$old];
            $id = $renumber[$old];

            $numbered[$id] = [
                'id' => $id,
                'x' => $room['x'],
                'y' => $room['y'],
                'kind' => $room['kind'],
                'links' => array_map(fn (int $link): int => $renumber[$link], $room['links']),
            ];
        }

        return self::$layouts[$cacheKey] = [
            'width' => $width,
            'height' => $height,
            'entrance' => 0,
            'boss' => $renumber[$boss],
            'rooms' => $numbered,
        ];
    }

    /**
     * Vista del laberinto con niebla: solo se conocen la sala actual, las
     * resueltas y sus vecinas. El jefe permanece oculto hasta que lo tocas.
     *
     * @return array<string, mixed>
     */
    public static function mapView(string $key, DungeonRun $run): array
    {
        $layout = self::layout($key, (int) $run->seed, (int) $run->floor);
        $current = (int) $run->room;
        $cleared = array_map('intval', (array) $run->cleared);
        $known = [$current => true];

        foreach ([$current, ...$cleared] as $id) {
            $known[$id] = true;

            foreach ($layout['rooms'][$id]['links'] ?? [] as $neighbour) {
                $known[$neighbour] = true;
            }
        }

        $resolved = self::roomResolved($run, $layout, $current);
        $rooms = [];

        foreach ($layout['rooms'] as $id => $room) {
            $isKnown = isset($known[$id]);

            $rooms[] = [
                'id' => $id,
                'x' => $room['x'],
                'y' => $room['y'],
                'known' => $isKnown,
                'kind' => $isKnown ? $room['kind'] : null,
                'links' => $isKnown ? $room['links'] : [],
                'cleared' => in_array($id, $cleared, true),
                'current' => $id === $current,
                'reachable' => $resolved && in_array($id, $layout['rooms'][$current]['links'], true),
            ];
        }

        return [
            'width' => $layout['width'],
            'height' => $layout['height'],
            'room' => $current,
            'entrance' => $layout['entrance'],
            'resolved' => $resolved,
            'rooms' => $rooms,
        ];
    }

    /**
     * Mueve al equipo a una sala conectada. La sala actual debe estar resuelta.
     */
    public static function moveTo(Player $player, string $key, int $room): DungeonRun
    {
        $run = self::run($player, $key);
        $layout = self::layout($key, (int) $run->seed, (int) $run->floor);
        $current = (int) $run->room;

        if (! in_array($room, $layout['rooms'][$current]['links'], true)) {
            throw ValidationException::withMessages([
                'room' => 'Esa sala no está conectada con tu posición.',
            ]);
        }

        if (! self::roomResolved($run, $layout, $current)) {
            throw ValidationException::withMessages([
                'room' => 'Resuelve esta sala antes de seguir explorando.',
            ]);
        }

        $run->room = $room;
        $run->save();

        return $run;
    }

    public static function markRoomCleared(DungeonRun $run, int $room): DungeonRun
    {
        $cleared = array_map('intval', (array) $run->cleared);

        if (! in_array($room, $cleared, true)) {
            $cleared[] = $room;
            $run->cleared = $cleared;
            $run->save();
        }

        return $run;
    }

    /**
     * @param  array{width: int, height: int, entrance: int, boss: int, rooms: array<int, array<string, mixed>>}  $layout
     */
    private static function roomResolved(DungeonRun $run, array $layout, int $room): bool
    {
        return ($layout['rooms'][$room]['kind'] ?? null) === 'camp'
            || in_array($room, array_map('intval', (array) $run->cleared), true);
    }

    /**
     * Distancias en pasos desde una sala a todas las demás.
     *
     * @param  array<int, list<int>>  $links
     * @return array<int, int>
     */
    private static function distances(array $links, int $from): array
    {
        $distances = [$from => 0];
        $queue = [$from];

        for ($i = 0; $i < count($queue); $i++) {
            $current = $queue[$i];

            foreach ($links[$current] as $next) {
                if (! isset($distances[$next])) {
                    $distances[$next] = $distances[$current] + 1;
                    $queue[] = $next;
                }
            }
        }

        return $distances;
    }

    /**
     * Generador pseudoaleatorio determinista (xorshift) para un piso concreto.
     */
    private static function rng(int $seed, string $key, int $floor): \Closure
    {
        $state = ((int) hexdec(substr(md5($seed.'|'.$key.'|'.$floor.'|layout'), 0, 8))) | 1;

        return function (int $max) use (&$state): int {
            $state ^= ($state << 13) & 0xFFFFFFFF;
            $state ^= $state >> 17;
            $state ^= ($state << 5) & 0xFFFFFFFF;
            $state &= 0xFFFFFFFF;

            return $state % max(1, $max);
        };
    }

    /**
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    private static function treasureNode(array $dungeon, int $floor, int $room): array
    {
        $treasure = $dungeon['treasure'] ?? [
            'name' => 'Arca de Limosnas',
            'subtitle' => 'Monedas de fe olvidadas tras un arcón de piedra.',
        ];

        return [
            'floor' => $floor,
            'branch' => $room,
            'kind' => 'treasure',
            'name' => $treasure['name'],
            'subtitle' => $treasure['subtitle'],
            'enemy' => null,
            'sprite' => null,
            'hp' => 0,
            'atk' => 0,
            'def' => 0,
            'spd' => 0,
            'grace' => 0,
            'xp' => 0,
            'treasure' => 25 + 10 * $floor,
            'mobs' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveTreasure(Player $player, DungeonRun $run, array $node, string $action): array
    {
        if ($action !== 'open') {
            throw ValidationException::withMessages([
                'action' => 'Solo puedes abrir el cofre.',
            ]);
        }

        $player->grace += (int) $node['treasure'];
        $player->save();

        return [
            'type' => 'treasure',
            'text' => $node['name'].': encuentras '.$node['treasure'].' Puntos de Fe.',
            'amount' => (int) $node['treasure'],
        ];
    }

    /**
     * Fragmento de los Evangelios guardado en la base de datos. La misma sala
     * siempre muestra el mismo versículo dentro de una semilla.
     *
     * @param  array<string, mixed>  $dungeon
     * @return array<string, mixed>
     */
    private static function gospelNode(string $key, array $dungeon, int $seed, int $floor, int $room): array
    {
        $translation = (string) config('bible.translation');
        $pool = GospelVerse::where('translation', $translation);
        $count = (clone $pool)->count();
        $verse = $count > 0
            ? (clone $pool)->orderBy('book')->orderBy('chapter')->orderBy('verse')
                ->skip(self::roll($seed, $key, $floor, $room, 'gospel') % $count)
                ->first()
            : null;

        return [
            'floor' => $floor,
            'branch' => $room,
            'kind' => 'gospel',
            'name' => 'Fragmento del Evangelio',
            'subtitle' => $verse?->reference() ?? 'Las páginas están en blanco.',
            'enemy' => null,
            'sprite' => null,
            'hp' => 0,
            'atk' => 0,
            'def' => 0,
            'spd' => 0,
            'grace' => 0,
            'xp' => 0,
            'reward' => 15 + 5 * $floor,
            'verse' => $verse?->text,
            'reference' => $verse?->reference(),
            'mobs' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveGospel(Player $player, DungeonRun $run, array $node, string $action): array
    {
        if ($action !== 'read') {
            throw ValidationException::withMessages([
                'action' => 'Solo puedes leer el fragmento.',
            ]);
        }

        if (in_array((int) $node['room'], array_map('intval', (array) $run->cleared), true)) {
            throw ValidationException::withMessages([
                'action' => 'Ya leíste este fragmento.',
            ]);
        }

        $player->grace += (int) $node['reward'];
        $player->save();

        $run->blessing = max((int) $run->blessing, 10);
        $run->save();

        $quote = $node['verse'] !== null
            ? '«'.$node['verse'].'» ('.$node['reference'].')'
            : 'Las páginas están en blanco.';

        return [
            'type' => 'gospel',
            'text' => $quote.' Recibes '.$node['reward'].' Puntos de Fe y +10% de ataque para la próxima batalla.',
            'amount' => (int) $node['reward'],
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
            $run->room = 0;
            $run->cleared = [];
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
            'avoid_cost' => self::trapAvoidCost($floor),
            'mobs' => [],
        ];
    }

    /**
     * Coste en Puntos de Fe de rodear una trampa. Crece con la profundidad
     * para que sortear los peligros no sea gratis en los pisos hondos.
     */
    private static function trapAvoidCost(int $floor): int
    {
        return 10 + 5 * max(1, $floor);
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
     * @return array<string, mixed>
     */
    private static function resolveTrap(Player $player, DungeonRun $run, array $node, string $action): array
    {
        if ($action === 'avoid') {
            $cost = (int) ($node['avoid_cost'] ?? self::trapAvoidCost((int) $node['floor']));

            if ($player->grace < $cost) {
                throw ValidationException::withMessages([
                    'action' => 'Necesitas '.$cost.' Puntos de Fe para sortear la trampa.',
                ]);
            }

            $player->grace -= $cost;
            $player->save();

            return [
                'type' => 'avoid',
                'text' => $node['name'].': rodeas el peligro y gastas '.$cost.' Puntos de Fe.',
                'amount' => $cost,
            ];
        }

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
        if (in_array((int) $node['room'], array_map('intval', (array) $run->cleared), true)) {
            throw ValidationException::withMessages([
                'action' => 'Ya has descansado en este campamento.',
            ]);
        }

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
