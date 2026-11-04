<?php

namespace Tests\Feature;

use App\Models\DungeonProgress;
use App\Models\DungeonRun;
use App\Models\Player;
use App\Models\Saint;
use App\Models\SaintCopy;
use App\Support\DungeonRules;
use Database\Seeders\SaintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DungeonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SaintSeeder::class);
    }

    public function test_the_hub_lists_only_the_visible_dungeon(): void
    {
        $this->createPlayer();

        $this->get('/mazmorras')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dungeons')
                ->has('dungeons', 1)
                ->where('dungeons.0.key', 'pecados')
                ->where('dungeons.0.unlocked', true)
                ->where('dungeons.0.theme', 'pecados')
                ->where('dungeons.0.next.floor', 1)
                ->where('dungeons.0.next.kind', 'camp')
                ->where('deepest', 0));
    }

    public function test_hidden_dungeons_and_unknown_ones_are_not_found(): void
    {
        $this->createPlayer();

        $this->get('/mazmorras/goetia')->assertNotFound();
        $this->get('/mazmorras/catacumbas')->assertNotFound();
        $this->get('/mazmorras/inexistente')->assertNotFound();

        $this->postJson('/mazmorras/goetia/batalla')->assertNotFound();
        $this->postJson('/mazmorras/catacumbas/paso', ['action' => 'rest'])->assertNotFound();
    }

    public function test_the_run_page_renders_the_entry_camp_and_the_owned_roster(): void
    {
        $player = $this->createPlayer();
        [$run, $team] = $this->prepareRun($player, 1, 12);
        $map = DungeonRules::mapFor('pecados', $run->seed, 1);

        $this->get('/mazmorras/pecados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DungeonRun')
                ->where('dungeon.key', 'pecados')
                ->where('dungeon.theme', 'pecados')
                ->where('dungeon.run_floor', 1)
                ->where('encounter.floor', 1)
                ->where('encounter.kind', 'camp')
                ->where('teamIds', $team)
                ->has('roster', 3)
                ->has('vitals', 3)
                ->where('blessing', 0)
                ->has('path', $map['length'])
                ->where('path.0.status', 'current')
                ->where('path.0.options.0.kind', 'camp')
                ->where('map.index', 1)
                ->where('map.room', 1)
                ->where('map.length', $map['length'])
                ->has('history'));
    }

    public function test_each_floor_map_has_a_procedural_length_with_the_boss_far_from_the_entrance(): void
    {
        $lengths = [];

        for ($seed = 1; $seed <= 40; $seed++) {
            $map = DungeonRules::mapFor('pecados', $seed, 1);
            $lengths[$map['length']] = true;

            $this->assertSame(1, $map['start']);
            $this->assertSame($map['start'] + $map['length'] - 1, $map['end']);
            $this->assertGreaterThanOrEqual(9, $map['length']);
            $this->assertLessThanOrEqual(13, $map['length']);
            $this->assertGreaterThanOrEqual(6, $map['end'] - $map['start']);
            $this->assertContains(0, $map['camps']);
            $this->assertLessThanOrEqual(3, count($map['camps']));
            $this->assertNotContains($map['length'] - 1, $map['camps']);

            $this->assertSame('camp', DungeonRules::nodeFor('pecados', $seed, $map['start'])['kind']);
            $this->assertSame('boss', DungeonRules::nodeFor('pecados', $seed, $map['end'])['kind']);

            foreach ($map['camps'] as $offset) {
                $floor = $map['start'] + $offset;

                $this->assertSame('camp', DungeonRules::nodeFor('pecados', $seed, $floor)['kind']);
                $this->assertSame(1, DungeonRules::forkCount('pecados', $seed, $floor));
            }

            $this->assertSame(1, DungeonRules::forkCount('pecados', $seed, $map['end']));

            for ($floor = $map['start'] + 1; $floor < $map['end']; $floor++) {
                $offset = $floor - $map['start'];
                $node = DungeonRules::nodeFor('pecados', $seed, $floor);

                if (in_array($offset, $map['camps'], true)) {
                    $this->assertSame('camp', $node['kind']);
                } else {
                    $this->assertContains($node['kind'], ['battle', 'altar', 'trap']);
                }
            }

            $next = DungeonRules::mapFor('pecados', $seed, $map['end'] + 1);

            $this->assertSame($map['end'] + 1, $next['start']);
            $this->assertSame($map['index'] + 1, $next['index']);
            $this->assertSame('camp', DungeonRules::nodeFor('pecados', $seed, $next['start'])['kind']);
        }

        $this->assertGreaterThan(1, count($lengths), 'La longitud de los pisos debe variar con la semilla.');
    }

    public function test_camps_and_the_boss_never_fork_but_corridors_can_offer_two_paths(): void
    {
        $single = 0;
        $forked = 0;

        for ($seed = 1; $seed <= 60; $seed++) {
            $map = DungeonRules::mapFor('pecados', $seed, 1);

            foreach ([...$map['camps'], $map['length'] - 1] as $offset) {
                $this->assertSame(1, DungeonRules::forkCount('pecados', $seed, $map['start'] + $offset));
            }

            for ($offset = 1; $offset < $map['length'] - 1; $offset++) {
                if (in_array($offset, $map['camps'], true)) {
                    continue;
                }

                $count = DungeonRules::forkCount('pecados', $seed, $map['start'] + $offset);

                $this->assertContains($count, [1, 2]);
                $count === 1 ? $single++ : $forked++;
            }
        }

        $this->assertGreaterThan(0, $single);
        $this->assertGreaterThan(0, $forked);
    }

    public function test_the_boss_carries_summon_templates_and_a_summon_chance(): void
    {
        $seed = 987654;
        $map = DungeonRules::mapFor('pecados', $seed, 1);
        $node = DungeonRules::nodeFor('pecados', $seed, $map['end']);
        $mobKeys = collect(config('sanctorum.dungeons.pecados.mobs'))->pluck('enemy')->all();

        $this->assertSame('boss', $node['kind']);
        $this->assertSame($map['length'], $node['room']);
        $this->assertCount(1, $node['mobs']);
        $this->assertSame(45, $node['summon_chance']);
        $this->assertNotEmpty($node['summons']);

        foreach ($node['summons'] as $summon) {
            $this->assertContains($summon['enemy'], $mobKeys);
            $this->assertGreaterThan(0, $summon['hp']);
            $this->assertLessThanOrEqual($node['mobs'][0]['hp'], $summon['hp']);
        }
    }

    public function test_the_path_preview_shows_the_whole_floor_with_visited_and_future_rooms(): void
    {
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, 1, 12);
        $run->seed = 987654;
        $run->floor = 1;
        $run->save();

        $map = DungeonRules::mapFor('pecados', 987654, 1);
        $path = DungeonRules::pathPreview($player, 'pecados', $run);

        $this->assertCount($map['length'], $path);
        $this->assertSame(1, $path[0]['floor']);
        $this->assertSame('current', $path[0]['status']);
        $this->assertSame('camp', $path[0]['options'][0]['kind']);
        $this->assertCount(1, $path[0]['options']);
        $this->assertSame('boss', $path[$map['length'] - 1]['options'][0]['kind']);
        $this->assertSame('future', $path[1]['status']);

        foreach ($path as $row) {
            $this->assertSame(
                DungeonRules::forkCount('pecados', $run->seed, $row['floor']),
                count($row['options']),
            );

            foreach ($row['options'] as $option) {
                $node = DungeonRules::nodeFor('pecados', $run->seed, $row['floor'], $option['branch']);

                $this->assertSame($node['kind'], $option['kind']);
                $this->assertSame($node['name'], $option['name']);
                $this->assertSame($node['enemy'], $option['enemy']);
            }
        }

        $run->floor = $map['start'] + 2;
        $run->save();

        $advanced = DungeonRules::pathPreview($player, 'pecados', $run);

        $this->assertSame('visited', $advanced[0]['status']);
        $this->assertSame('visited', $advanced[1]['status']);
        $this->assertSame('current', $advanced[2]['status']);
        $this->assertSame('future', $advanced[3]['status']);

        $this->get('/mazmorras/pecados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('path', $map['length'])
                ->where('path.0.options.0.branch', 0)
                ->where('path.2.status', 'current')
                ->where('map.index', 1)
                ->where('map.room', 3)
                ->where('map.length', $map['length'])
                ->where('dungeon.route', 0));
    }

    public function test_choosing_a_branch_is_saved_and_changes_the_encounter(): void
    {
        [$seed, $floor] = $this->seedForFork();
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, $floor, 12);
        $run->seed = $seed;
        $run->route = 0;
        $run->save();

        $first = DungeonRules::nodeFor('pecados', $seed, $floor, 0);
        $second = DungeonRules::nodeFor('pecados', $seed, $floor, 1);

        $this->assertNotSame($first['enemy'], $second['enemy']);

        $this->postJson('/mazmorras/pecados/ruta', ['branch' => 1])
            ->assertOk()
            ->assertJsonPath('branch', 1)
            ->assertJsonPath('encounter.enemy', $second['enemy'])
            ->assertJsonPath('run_floor', $floor)
            ->assertJsonPath('map.room', $second['room']);

        $this->assertSame(1, $run->fresh()->route);

        $this->get('/mazmorras/pecados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dungeon.route', 1)
                ->where('encounter.enemy', $second['enemy']));
    }

    public function test_the_team_can_only_be_changed_at_a_camp(): void
    {
        $player = $this->createPlayer();
        [$run, $team] = $this->prepareRun($player, 1, 12);
        $chosen = array_slice($team, 0, 2);

        $this->postJson('/mazmorras/pecados/equipo', ['team' => $chosen])
            ->assertOk()
            ->assertJsonPath('team', $chosen);

        $this->assertSame($chosen, $run->fresh()->team);

        $run->floor = 2;
        $run->save();

        $this->postJson('/mazmorras/pecados/equipo', ['team' => $team])
            ->assertStatus(422)
            ->assertJsonPath('errors.team.0', 'Solo puedes cambiar de equipo en un campamento.');
    }

    public function test_the_team_must_have_between_one_and_three_owned_saints(): void
    {
        $player = $this->createPlayer();
        [$run, $team] = $this->prepareRun($player, 1, 12);

        $this->postJson('/mazmorras/pecados/equipo', ['team' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('team');

        $this->postJson('/mazmorras/pecados/equipo', ['team' => [$team[0], $team[1], $team[2], 999999]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('team');

        $this->postJson('/mazmorras/pecados/equipo', ['team' => [999999]])
            ->assertStatus(422)
            ->assertJsonPath('errors.team.0', 'Ese santo no forma parte de tu santoral.');

        $this->assertSame($team, $run->fresh()->team);
    }

    public function test_winning_a_battle_advances_the_run_and_consumes_the_blessing(): void
    {
        $seed = 987654;
        $battleFloor = $this->firstFloorOfKind($seed, 'battle');
        $player = $this->createPlayer(['grace' => 0]);
        [$run, $team] = $this->prepareRun($player, $battleFloor, 20, 6);

        $run->seed = $seed;
        $run->floor = $battleFloor;
        $run->route = 0;
        $run->blessing = 25;
        $run->save();

        $encounter = DungeonRules::currentNode($player, 'pecados', $run);
        $next = DungeonRules::nodeFor('pecados', $seed, $battleFloor + 1);

        $this->assertSame('battle', $encounter['kind']);

        $response = $this->postJson('/mazmorras/pecados/batalla');

        $response->assertOk()
            ->assertJsonPath('result', 'win')
            ->assertJsonPath('kind', 'battle')
            ->assertJsonPath('floor', $battleFloor)
            ->assertJsonPath('map_index', $encounter['map_index'])
            ->assertJsonPath('room', $encounter['room'])
            ->assertJsonPath('run_floor', $battleFloor + 1)
            ->assertJsonPath('next_encounter.floor', $battleFloor + 1)
            ->assertJsonPath('next_encounter.kind', $next['kind'])
            ->assertJsonPath('rewards.grace', $encounter['grace'])
            ->assertJsonPath('blessing', 0)
            ->assertJsonPath('team', $team);

        $this->assertSame($encounter['grace'], $response->json('rewards.grace'));
        $this->assertSame(0, $run->fresh()->blessing);

        $this->assertDatabaseHas('dungeon_progress', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => $battleFloor,
        ]);

        $this->assertDatabaseHas('dungeon_runs', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'floor' => $battleFloor + 1,
        ]);

        $this->assertDatabaseHas('battles', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => $battleFloor,
            'result' => 'win',
        ]);

        $this->assertSame($team, $player->fresh()->teamIds());
        $this->assertSame($encounter['grace'], $player->fresh()->grace);
    }

    public function test_a_loss_keeps_the_floor_and_restores_the_vitals_for_a_retry(): void
    {
        $seed = 424242;
        $map = DungeonRules::mapFor('pecados', $seed, 1);
        $bossFloor = $map['end'];
        $player = $this->createPlayer(['grace' => 0]);
        [$run, $team] = $this->prepareRun($player, $bossFloor, 1, 1, 1);

        $run->seed = $seed;
        $run->floor = $bossFloor;
        $run->save();

        $encounter = DungeonRules::currentNode($player, 'pecados', $run);
        $maxHp = DungeonRules::vitalsFor($player, $run)[$team[0]]['max'];

        $this->assertSame('boss', $encounter['kind']);

        $response = $this->postJson('/mazmorras/pecados/batalla');

        $response->assertOk()
            ->assertJsonPath('result', 'lose')
            ->assertJsonPath('floor', $bossFloor)
            ->assertJsonPath('run_floor', $bossFloor)
            ->assertJsonPath('next_encounter.floor', $bossFloor)
            ->assertJsonPath('rewards.grace', (int) round($encounter['grace'] * 0.2));

        $this->assertDatabaseMissing('dungeon_progress', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => $bossFloor,
        ]);

        $this->assertDatabaseHas('battles', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => $bossFloor,
            'result' => 'lose',
        ]);

        $vitals = $run->fresh()->vitals;

        $this->assertSame($maxHp, $vitals[$team[0]]);
        $this->assertSame((int) round($encounter['grace'] * 0.2), $player->fresh()->grace);
    }

    public function test_a_fight_needs_a_team_and_a_combat_node(): void
    {
        $player = $this->createPlayer();

        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 987654;
        $run->floor = $this->firstFloorOfKind(987654, 'battle');
        $run->save();

        $this->postJson('/mazmorras/pecados/batalla')
            ->assertStatus(422)
            ->assertJsonPath('errors.team.0', 'Elige al menos un santo antes de entrar en la mazmorra.');

        $player = $this->createPlayer();
        $this->prepareRun($player, 1, 12);

        $this->postJson('/mazmorras/pecados/batalla')
            ->assertStatus(422)
            ->assertJsonPath('errors.battle.0', 'Este paso no se resuelve con una batalla.');
    }

    public function test_a_trap_can_wound_the_team_and_advance_the_run(): void
    {
        [$seed, $floor] = $this->seedForNode('trap', 'damage');
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, $floor, 12);

        $run->seed = $seed;
        $run->floor = $floor;
        $run->save();

        $node = DungeonRules::currentNode($player, 'pecados', $run);
        $before = DungeonRules::vitalsFor($player, $run);

        $response = $this->postJson('/mazmorras/pecados/paso', ['action' => 'cross']);

        $response->assertOk()
            ->assertJsonPath('outcome.type', 'damage')
            ->assertJsonPath('run_floor', $floor + 1);

        $after = $run->fresh()->vitals;

        foreach ($before as $id => $vital) {
            $damage = (int) round($vital['max'] * $node['power'] / 100);

            $this->assertSame(max(1, $vital['hp'] - $damage), $after[$id]);
        }

        $this->assertGreaterThan(0, $response->json('outcome.amount'));
    }

    public function test_a_trap_can_steal_grace_instead_of_vitals(): void
    {
        [$seed, $floor] = $this->seedForNode('trap', 'grace');
        $player = $this->createPlayer(['grace' => 1000]);
        [$run] = $this->prepareRun($player, $floor, 12);

        $run->seed = $seed;
        $run->floor = $floor;
        $run->save();

        $node = DungeonRules::currentNode($player, 'pecados', $run);
        $loss = min(1000, max(10, (int) round(1000 * $node['power'] / 100)));

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'cross'])
            ->assertOk()
            ->assertJsonPath('outcome.type', 'grace')
            ->assertJsonPath('outcome.amount', $loss)
            ->assertJsonPath('player.grace', 1000 - $loss)
            ->assertJsonPath('run_floor', $floor + 1);

        $this->assertSame(1000 - $loss, $player->fresh()->grace);
    }

    public function test_the_virgin_altar_can_heal_the_team(): void
    {
        [$seed, $floor] = $this->seedForNode('altar');
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, $floor, 12);

        $run->seed = $seed;
        $run->floor = $floor;
        $this->setAllVitals($run, 1);
        $run->save();

        $node = DungeonRules::currentNode($player, 'pecados', $run);
        $before = DungeonRules::vitalsFor($player, $run);

        $response = $this->postJson('/mazmorras/pecados/paso', ['action' => 'heal']);

        $response->assertOk()
            ->assertJsonPath('outcome.type', 'heal')
            ->assertJsonPath('run_floor', $floor + 1);

        $after = $run->fresh()->vitals;

        foreach ($before as $id => $vital) {
            $healed = min($vital['max'], 1 + (int) round($vital['max'] * $node['heal'] / 100));

            $this->assertSame($healed, $after[$id]);
        }

        $this->assertGreaterThan(0, $response->json('outcome.amount'));
    }

    public function test_the_virgin_altar_can_bless_the_next_battle(): void
    {
        [$seed, $floor] = $this->seedForNode('altar');
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, $floor, 12);

        $run->seed = $seed;
        $run->floor = $floor;
        $run->save();

        $node = DungeonRules::currentNode($player, 'pecados', $run);

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'bless'])
            ->assertOk()
            ->assertJsonPath('outcome.type', 'blessing')
            ->assertJsonPath('outcome.amount', $node['blessing'])
            ->assertJsonPath('blessing', $node['blessing'])
            ->assertJsonPath('run_floor', $floor + 1);

        $this->assertSame($node['blessing'], $run->fresh()->blessing);
    }

    public function test_a_camp_heals_the_team_and_can_update_the_loadout(): void
    {
        $player = $this->createPlayer();
        [$run, $team] = $this->prepareRun($player, 1, 12);
        $this->setAllVitals($run, 1);
        $run->save();

        $chosen = array_slice($team, 0, 2);

        $response = $this->postJson('/mazmorras/pecados/paso', [
            'action' => 'rest',
            'team' => $chosen,
        ]);

        $response->assertOk()
            ->assertJsonPath('outcome.type', 'camp')
            ->assertJsonPath('run_floor', 2)
            ->assertJsonPath('team', $chosen);

        $this->assertGreaterThan(0, $response->json('outcome.amount'));
        $this->assertSame($chosen, $run->fresh()->team);

        $after = $run->fresh()->vitals;

        foreach ($chosen as $id) {
            $this->assertGreaterThan(1, $after[$id]);
        }
    }

    public function test_a_new_rift_rerolls_the_seed_but_keeps_the_record(): void
    {
        $player = $this->createPlayer();
        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 1;
        $run->floor = 4;
        $run->route = 1;
        $run->blessing = 25;
        $run->save();

        foreach ([1, 2, 3] as $stage) {
            DungeonProgress::create([
                'player_id' => $player->id,
                'dungeon' => 'pecados',
                'stage' => $stage,
            ]);
        }

        $this->post('/mazmorras/pecados/reiniciar')
            ->assertRedirect('/mazmorras/pecados');

        $fresh = $run->fresh();

        $this->assertSame(1, $fresh->floor);
        $this->assertSame(0, $fresh->route);
        $this->assertSame(0, $fresh->blessing);
        $this->assertNotSame(1, $fresh->seed);
        $this->assertSame(3, DungeonRules::bestFloor($player, 'pecados'));
        $this->assertSame(3, DungeonRules::clearedCount($player, 'pecados'));
    }

    /**
     * @return array{0: DungeonRun, 1: list<int>}
     */
    private function prepareRun(Player $player, int $floor, int $level = 1, int $copies = 1, int $amount = 3): array
    {
        $team = $this->equip($player, $level, $copies, $amount);

        $player->team = $team;
        $player->save();

        $run = DungeonRules::run($player, 'pecados');
        $run->floor = $floor;
        $run->route = 0;
        $run->vitals = [];
        $run->blessing = 0;
        $run->save();

        return [DungeonRules::syncLoadout($player, $run), $team];
    }

    /**
     * @return list<int>
     */
    private function equip(Player $player, int $level = 1, int $copies = 1, int $amount = 3): array
    {
        return Saint::orderByDesc('rarity')
            ->take($amount)
            ->get()
            ->map(function (Saint $saint) use ($player, $level, $copies): int {
                SaintCopy::create([
                    'player_id' => $player->id,
                    'saint_id' => $saint->id,
                    'copies' => $copies,
                    'level' => $level,
                    'xp' => 0,
                ]);

                return $saint->id;
            })
            ->all();
    }

    /**
     * Busca una semilla y la primera sala de un piso con el tipo pedido.
     *
     * @return array{0: int, 1: int}
     */
    private function seedForNode(string $kind, ?string $effect = null): array
    {
        for ($seed = 1; $seed <= 20000; $seed++) {
            $map = DungeonRules::mapFor('pecados', $seed, 1);

            for ($floor = $map['start'] + 1; $floor < $map['end']; $floor++) {
                if (in_array($floor - $map['start'], $map['camps'], true)) {
                    continue;
                }

                $node = DungeonRules::nodeFor('pecados', $seed, $floor);

                if ($node['kind'] !== $kind) {
                    continue;
                }

                if ($effect !== null && ($node['effect'] ?? null) !== $effect) {
                    continue;
                }

                return [$seed, $floor];
            }
        }

        $this->fail("No se encontró una semilla para {$kind}.");
    }

    private function firstFloorOfKind(int $seed, string $kind, ?string $effect = null): int
    {
        $map = DungeonRules::mapFor('pecados', $seed, 1);

        for ($floor = $map['start'] + 1; $floor < $map['end']; $floor++) {
            if (in_array($floor - $map['start'], $map['camps'], true)) {
                continue;
            }

            $node = DungeonRules::nodeFor('pecados', $seed, $floor);

            if ($node['kind'] !== $kind) {
                continue;
            }

            if ($effect !== null && ($node['effect'] ?? null) !== $effect) {
                continue;
            }

            return $floor;
        }

        $this->fail("No se encontró una sala {$kind} en la semilla {$seed}.");
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedForFork(): array
    {
        for ($seed = 1; $seed <= 20000; $seed++) {
            $map = DungeonRules::mapFor('pecados', $seed, 1);

            for ($floor = $map['start'] + 1; $floor < $map['end']; $floor++) {
                if (in_array($floor - $map['start'], $map['camps'], true)) {
                    continue;
                }

                if (DungeonRules::forkCount('pecados', $seed, $floor) < 2) {
                    continue;
                }

                $first = DungeonRules::nodeFor('pecados', $seed, $floor, 0);
                $second = DungeonRules::nodeFor('pecados', $seed, $floor, 1);

                if ($first['enemy'] !== $second['enemy']) {
                    return [$seed, $floor];
                }
            }
        }

        $this->fail('No se encontró una bifurcación con encuentros distintos.');
    }

    private function setAllVitals(DungeonRun $run, int $hp): void
    {
        $vitals = [];

        foreach (array_keys((array) $run->vitals) as $id) {
            $vitals[$id] = $hp;
        }

        $run->vitals = $vitals;
    }
}
