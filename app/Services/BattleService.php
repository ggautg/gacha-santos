<?php

namespace App\Services;

use App\Models\Battle;
use App\Models\DungeonRun;
use App\Models\Player;
use App\Models\SaintCopy;
use App\Support\CardPresenter;
use App\Support\DungeonRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BattleService
{
    private const MAX_ROUNDS = 30;

    /**
     * Resuelve el piso actual de un descenso procedural.
     *
     * @param  array<int, int>  $teamIds
     * @return array<string, mixed>
     */
    public function fightDungeon(Player $player, string $dungeonKey, array $teamIds): array
    {
        DungeonRules::get($dungeonKey);

        if (! DungeonRules::isUnlocked($player, $dungeonKey)) {
            throw ValidationException::withMessages([
                'battle' => 'La mazmorra todavía está sellada.',
            ]);
        }

        $team = $this->resolveTeam($player, $teamIds);
        $run = DungeonRules::run($player, $dungeonKey);
        $floor = $run->floor;
        $encounter = DungeonRules::encounter($player, $dungeonKey, $floor, $run);

        return $this->resolve($player, $encounter, $floor, $dungeonKey, $team);
    }

    /**
     * Valida que el equipo exista en el santoral y respeta el orden elegido.
     *
     * @param  array<int, int>  $teamIds
     * @return list<SaintCopy>
     */
    private function resolveTeam(Player $player, array $teamIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $teamIds)));

        if (empty($ids)) {
            throw ValidationException::withMessages([
                'team' => 'Elige al menos un santo antes de entrar en la mazmorra.',
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

        $team = [];

        foreach ($ids as $id) {
            $copy = $copies->get($id);

            if ($copy === null) {
                throw ValidationException::withMessages([
                    'team' => 'Ese santo no forma parte de tu santoral.',
                ]);
            }

            $team[] = $copy;
        }

        return $team;
    }

    /**
     * @param  array<string, mixed>  $encounter
     * @param  list<SaintCopy>  $team
     * @return array<string, mixed>
     */
    private function resolve(Player $player, array $encounter, int $floor, string $dungeonKey, array $team): array
    {
        $units = [];

        foreach ($team as $i => $copy) {
            $stats = $copy->stats();
            $saint = $copy->saint;
            $id = 'a'.($i + 1);

            $units[$id] = [
                'id' => $id,
                'side' => 'ally',
                'name' => $saint->name,
                'short' => $this->shortName($saint->name),
                'level' => $copy->level,
                'hp' => $stats['hp'],
                'maxHp' => $stats['hp'],
                'atk' => $stats['atk'],
                'def' => $stats['def'],
                'spd' => $stats['spd'],
                'gauge' => 0,
                'shield' => 0,
                'buff' => 0.0,
                'buffTurns' => 0,
                'stun' => false,
                'skillType' => $saint->skill_type,
                'skillName' => $saint->skill_name,
                'skillPower' => $saint->skill_power,
                'sprite' => CardPresenter::artUrl($saint),
                'rarity' => $saint->rarity,
            ];
        }

        $units['b1'] = [
            'id' => 'b1',
            'side' => 'foe',
            'name' => $encounter['name'],
            'short' => $encounter['name'],
            'level' => $floor,
            'hp' => $encounter['hp'],
            'maxHp' => $encounter['hp'],
            'atk' => $encounter['atk'],
            'def' => $encounter['def'],
            'spd' => $encounter['spd'],
            'gauge' => 0,
            'shield' => 0,
            'buff' => 0.0,
            'buffTurns' => 0,
            'stun' => false,
            'skillType' => $encounter['skill'],
            'skillName' => $encounter['skill_name'],
            'skillPower' => $encounter['skill_power'],
            'sprite' => CardPresenter::foeUrl($encounter['enemy']),
            'rarity' => 5,
        ];

        $initial = $units;
        $log = [['t' => 'start', 'text' => $encounter['name'].' — '.$encounter['subtitle']]];

        $round = 0;
        while ($round < self::MAX_ROUNDS) {
            $allies = $this->alive($units, 'ally');
            $foes = $this->alive($units, 'foe');

            if (empty($allies) || empty($foes)) {
                break;
            }

            $round++;
            $log[] = ['t' => 'round', 'n' => $round];

            $order = $this->turnOrder($units);

            foreach ($order as $id) {
                if ($units[$id]['hp'] <= 0) {
                    continue;
                }

                $opponents = $this->alive($units, $units[$id]['side'] === 'ally' ? 'foe' : 'ally');
                if (empty($opponents)) {
                    break;
                }

                if ($units[$id]['stun']) {
                    $units[$id]['stun'] = false;
                    $log[] = ['t' => 'stun', 'actor' => $id, 'text' => $units[$id]['short'].' está aturdido y pierde el turno.'];

                    continue;
                }

                $units[$id]['gauge']++;

                if ($units[$id]['gauge'] >= 3) {
                    $units[$id]['gauge'] = 0;
                    $this->castSkill($units, $id, $log);
                } else {
                    $target = $opponents[array_rand($opponents)];
                    $result = $this->hit($units, $id, $target);

                    $log[] = [
                        't' => 'attack',
                        'actor' => $id,
                        'target' => $target,
                        'dmg' => $result['damage'],
                        'absorbed' => $result['absorbed'],
                        'crit' => $result['crit'],
                        'hp' => $units[$target]['hp'],
                        'maxHp' => $units[$target]['maxHp'],
                    ];

                    if ($result['down']) {
                        $log[] = $this->downEntry($units, $target);
                    }
                }
            }

            foreach ($units as &$unit) {
                if ($unit['buffTurns'] > 0) {
                    $unit['buffTurns']--;
                    if ($unit['buffTurns'] === 0) {
                        $unit['buff'] = 0.0;
                    }
                }
            }
            unset($unit);
        }

        $alliesAlive = $this->alive($units, 'ally');
        $foesAlive = $this->alive($units, 'foe');
        $win = ! empty($alliesAlive) && empty($foesAlive);

        $grace = $win ? (int) $encounter['grace'] : (int) round($encounter['grace'] * 0.2);
        $xp = $win ? (int) $encounter['xp'] : (int) round($encounter['xp'] * 0.4);

        $log[] = ['t' => 'end', 'result' => $win ? 'win' : 'lose'];

        return DB::transaction(function () use ($player, $encounter, $floor, $dungeonKey, $units, $initial, $log, $win, $grace, $xp, $team) {
            /** @var Player $player */
            $player = Player::whereKey($player->id)->lockForUpdate()->firstOrFail();

            $dungeon = DungeonRules::get($dungeonKey);
            $teamIds = array_map(fn (SaintCopy $copy) => $copy->saint_id, $team);

            $player->grace += $grace;
            $player->team = $teamIds;
            $win ? $player->wins++ : $player->losses++;
            $player->save();

            /** @var DungeonRun $run */
            $run = $win
                ? DungeonRules::markCleared($player, $dungeonKey, $floor)
                : DungeonRules::run($player, $dungeonKey);

            $bestFloor = DungeonRules::bestFloor($player, $dungeonKey);
            $nextEncounter = DungeonRules::encounter($player, $dungeonKey, $run->floor, $run);

            $levelUps = [];

            foreach ($team as $i => $copy) {
                $unitId = 'a'.($i + 1);
                $survived = ($units[$unitId]['hp'] ?? 0) > 0;
                $gained = $survived ? $xp : (int) round($xp * 0.6);
                $levelUps = array_merge($levelUps, $this->grantXp($copy, $gained));
            }

            $battle = Battle::create([
                'player_id' => $player->id,
                'stage' => $floor,
                'dungeon' => $dungeonKey,
                'enemy_key' => $encounter['enemy'],
                'result' => $win ? 'win' : 'lose',
                'grace_reward' => $grace,
                'xp_reward' => $xp,
                'log' => $log,
            ]);

            return [
                'result' => $win ? 'win' : 'lose',
                'floor' => $floor,
                'stage' => $floor,
                'stage_name' => $encounter['name'],
                'dungeon' => $dungeonKey,
                'dungeon_name' => $dungeon['name'],
                'theme' => $dungeon['theme'] ?? $dungeonKey,
                'initial' => array_values($initial),
                'units' => array_values($units),
                'log' => $log,
                'rewards' => ['grace' => $grace, 'xp' => $xp],
                'player' => [
                    'grace' => $player->grace,
                    'wins' => $player->wins,
                    'losses' => $player->losses,
                ],
                'team' => $teamIds,
                'levels' => $levelUps,
                'best_floor' => $bestFloor,
                'run_floor' => $run->floor,
                'next_encounter' => $nextEncounter,
                'history_entry' => [
                    'floor' => $floor,
                    'name' => $encounter['name'],
                    'enemy' => $encounter['enemy'],
                    'result' => $win ? 'win' : 'lose',
                    'grace' => $grace,
                    'at' => $battle->created_at->format('d/m H:i'),
                ],
            ];
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $units
     * @param  array<int, array<string, mixed>>  $log
     */
    private function castSkill(array &$units, string $actorId, array &$log): void
    {
        $actor = &$units[$actorId];
        $opponents = $this->alive($units, $actor['side'] === 'ally' ? 'foe' : 'ally');
        $allies = $this->alive($units, $actor['side']);

        if (empty($opponents)) {
            return;
        }

        $log[] = [
            't' => 'cast',
            'actor' => $actorId,
            'name' => $actor['skillName'],
            'text' => $actor['short'].' invoca «'.$actor['skillName'].'».',
        ];

        switch ($actor['skillType']) {
            case 'smite':
                $target = $opponents[array_rand($opponents)];
                $result = $this->hit($units, $actorId, $target, 2.0);
                $log[] = [
                    't' => 'attack', 'actor' => $actorId, 'target' => $target,
                    'dmg' => $result['damage'], 'absorbed' => $result['absorbed'],
                    'crit' => $result['crit'], 'skill' => true,
                    'hp' => $units[$target]['hp'], 'maxHp' => $units[$target]['maxHp'],
                ];
                if ($result['down']) {
                    $log[] = $this->downEntry($units, $target);
                }
                break;

            case 'aoe':
                foreach ($opponents as $target) {
                    $result = $this->hit($units, $actorId, $target, 0.8);
                    $log[] = [
                        't' => 'attack', 'actor' => $actorId, 'target' => $target,
                        'dmg' => $result['damage'], 'absorbed' => $result['absorbed'],
                        'crit' => $result['crit'], 'skill' => true,
                        'hp' => $units[$target]['hp'], 'maxHp' => $units[$target]['maxHp'],
                    ];
                    if ($result['down']) {
                        $log[] = $this->downEntry($units, $target);
                    }
                }
                break;

            case 'heal':
                $target = $this->mostWounded($units, $allies);
                if ($target === null) {
                    $target = $actorId;
                }
                $amount = (int) round($units[$target]['maxHp'] * $actor['skillPower'] / 100);
                $before = $units[$target]['hp'];
                $units[$target]['hp'] = min($units[$target]['maxHp'], $before + $amount);
                $log[] = [
                    't' => 'heal', 'actor' => $actorId, 'target' => $target,
                    'amount' => $units[$target]['hp'] - $before,
                    'hp' => $units[$target]['hp'], 'maxHp' => $units[$target]['maxHp'],
                ];
                break;

            case 'shield':
                foreach ($allies as $target) {
                    $amount = (int) round($units[$target]['maxHp'] * $actor['skillPower'] / 100);
                    $units[$target]['shield'] += $amount;
                    $log[] = [
                        't' => 'shield', 'actor' => $actorId, 'target' => $target,
                        'amount' => $amount, 'hp' => $units[$target]['hp'],
                        'maxHp' => $units[$target]['maxHp'], 'shield' => $units[$target]['shield'],
                    ];
                }
                break;

            case 'drain':
                $target = $opponents[array_rand($opponents)];
                $result = $this->hit($units, $actorId, $target, 1.6);
                $heal = (int) round($result['damage'] * 0.7);
                $before = $units[$actorId]['hp'];
                $units[$actorId]['hp'] = min($units[$actorId]['maxHp'], $before + $heal);
                $log[] = [
                    't' => 'attack', 'actor' => $actorId, 'target' => $target,
                    'dmg' => $result['damage'], 'absorbed' => $result['absorbed'],
                    'crit' => $result['crit'], 'skill' => true,
                    'hp' => $units[$target]['hp'], 'maxHp' => $units[$target]['maxHp'],
                ];
                $log[] = [
                    't' => 'heal', 'actor' => $actorId, 'target' => $actorId,
                    'amount' => $units[$actorId]['hp'] - $before,
                    'hp' => $units[$actorId]['hp'], 'maxHp' => $units[$actorId]['maxHp'],
                ];
                if ($result['down']) {
                    $log[] = $this->downEntry($units, $target);
                }
                break;

            case 'stun':
                $target = $opponents[array_rand($opponents)];
                $result = $this->hit($units, $actorId, $target, 1.2);
                $units[$target]['stun'] = true;
                $log[] = [
                    't' => 'attack', 'actor' => $actorId, 'target' => $target,
                    'dmg' => $result['damage'], 'absorbed' => $result['absorbed'],
                    'crit' => $result['crit'], 'skill' => true,
                    'hp' => $units[$target]['hp'], 'maxHp' => $units[$target]['maxHp'],
                ];
                if (! $result['down']) {
                    $log[] = [
                        't' => 'stun', 'actor' => $target,
                        'text' => $units[$target]['short'].' queda aturdido.',
                    ];
                } else {
                    $log[] = $this->downEntry($units, $target);
                }
                break;

            case 'buff':
                foreach ($allies as $target) {
                    $units[$target]['buff'] = $actor['skillPower'] / 100;
                    $units[$target]['buffTurns'] = 3;
                }
                $log[] = [
                    't' => 'buff', 'actor' => $actorId,
                    'text' => 'El equipo de '.$actor['short'].' arde de fervor (+'.($actor['skillPower']).'% ataque).',
                ];
                break;

            default:
                $target = $opponents[array_rand($opponents)];
                $result = $this->hit($units, $actorId, $target, 1.4);
                $log[] = [
                    't' => 'attack', 'actor' => $actorId, 'target' => $target,
                    'dmg' => $result['damage'], 'absorbed' => $result['absorbed'],
                    'crit' => $result['crit'], 'skill' => true,
                    'hp' => $units[$target]['hp'], 'maxHp' => $units[$target]['maxHp'],
                ];
                if ($result['down']) {
                    $log[] = $this->downEntry($units, $target);
                }
                break;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $units
     * @return array{damage: int, absorbed: int, crit: bool, down: bool}
     */
    private function hit(array &$units, string $attackerId, string $defenderId, float $multiplier = 1.0): array
    {
        $attacker = &$units[$attackerId];
        $defender = &$units[$defenderId];

        $attack = $attacker['atk'] * (1 + ($attacker['buffTurns'] > 0 ? $attacker['buff'] : 0));
        $defense = $defender['def'];
        $crit = mt_rand(1, 100) <= 13;
        $variance = mt_rand(90, 110) / 100;

        $damage = (int) round($attack * $multiplier * (110 / (110 + $defense)) * $variance * ($crit ? 1.7 : 1));
        $damage = max(3, $damage);

        $absorbed = min($defender['shield'], $damage);
        $defender['shield'] -= $absorbed;
        $damage -= $absorbed;

        $defender['hp'] = max(0, $defender['hp'] - $damage);

        return [
            'damage' => $damage,
            'absorbed' => $absorbed,
            'crit' => $crit,
            'down' => $defender['hp'] <= 0,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $units
     * @return array<string, mixed>
     */
    private function downEntry(array $units, string $id): array
    {
        return [
            't' => 'down',
            'unit' => $id,
            'text' => $units[$id]['short'].($units[$id]['side'] === 'ally' ? ' ha caído.' : ' ha sido derrotado.'),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $units
     * @return list<string>
     */
    private function alive(array $units, string $side): array
    {
        $ids = [];
        foreach ($units as $id => $unit) {
            if ($unit['side'] === $side && $unit['hp'] > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, array<string, mixed>>  $units
     * @return list<string>
     */
    private function turnOrder(array $units): array
    {
        $alive = array_keys(array_filter($units, fn ($unit) => $unit['hp'] > 0));

        usort($alive, function ($a, $b) use ($units) {
            $speedA = $units[$a]['spd'] + mt_rand(0, 3);
            $speedB = $units[$b]['spd'] + mt_rand(0, 3);

            return $speedB <=> $speedA;
        });

        return $alive;
    }

    /**
     * @param  array<string, array<string, mixed>>  $units
     * @param  list<string>  $ids
     */
    private function mostWounded(array $units, array $ids): ?string
    {
        $best = null;
        $ratio = 1.0;

        foreach ($ids as $id) {
            $r = $units[$id]['hp'] / max(1, $units[$id]['maxHp']);
            if ($r < $ratio) {
                $ratio = $r;
                $best = $id;
            }
        }

        return $best;
    }

    /**
     * @return array<int, array{slug:string,name:string,from:int,to:int}>
     */
    private function grantXp(SaintCopy $copy, int $amount): array
    {
        $max = config('sanctorum.max_level');
        $copy->xp += $amount;
        $from = $copy->level;
        $upgrades = [];

        while ($copy->level < $max) {
            $needed = (int) (60 * $copy->level ** 1.35);
            if ($copy->xp < $needed) {
                break;
            }
            $copy->xp -= $needed;
            $copy->level++;
        }

        if ($copy->level >= $max) {
            $copy->xp = 0;
        }

        $copy->save();

        if ($copy->level > $from) {
            $upgrades[] = [
                'slug' => $copy->saint->slug,
                'name' => $copy->saint->name,
                'from' => $from,
                'to' => $copy->level,
            ];
        }

        return $upgrades;
    }

    private function shortName(string $name): string
    {
        $name = preg_replace('/^(San|Santa|Santo|Beato|Beata)\s+/u', '', $name);

        return $name ?: $name;
    }
}
