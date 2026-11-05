<?php

namespace Tests\Feature;

use App\Models\DungeonProgress;
use App\Models\DungeonRun;
use App\Models\GospelVerse;
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
                ->where('dungeons.0.theme', 'pecados'));
    }

    public function test_the_run_page_renders_the_labyrinth_with_the_entrance_camp(): void
    {
        $player = $this->createPlayer();
        [$run, $team] = $this->prepareRun($player, 1, 12);

        $this->get('/mazmorras/pecados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DungeonRun')
                ->where('dungeon.key', 'pecados')
                ->where('dungeon.run_floor', 1)
                ->where('encounter.kind', 'camp')
                ->where('teamIds', $team)
                ->has('roster', 3)
                ->has('vitals', 3)
                ->where('map.room', 0)
                ->where('map.entrance', 0)
                ->where('map.resolved', true)
                ->has('map.rooms')
                ->has('history'));
    }

    public function test_every_floor_is_a_connected_horizontal_labyrinth(): void
    {
        for ($seed = 1; $seed <= 40; $seed++) {
            $layout = DungeonRules::layout('pecados', $seed, 1);
            $rooms = $layout['rooms'];

            $this->assertGreaterThanOrEqual(9, $layout['width']);
            $this->assertLessThanOrEqual(13, $layout['width']);
            $this->assertGreaterThanOrEqual(3, $layout['height']);
            $this->assertLessThanOrEqual(5, $layout['height']);
            $this->assertSame(count($rooms), count($this->reachableRooms($layout, $layout['entrance'])), "Seed {$seed} desconectado.");

            $distances = $this->distancesFrom($layout, $layout['entrance']);
            $maxDistance = max($distances);

            $this->assertGreaterThanOrEqual(max(2, $maxDistance - 1), $distances[$layout['boss']], "Seed {$seed}: el jefe está demasiado cerca.");
            $this->assertNotContains($layout['boss'], $rooms[$layout['entrance']]['links']);
            $this->assertSame('camp', $rooms[$layout['entrance']]['kind']);
            $this->assertSame('boss', $rooms[$layout['boss']]['kind']);
            $this->assertCount(1, array_filter($rooms, fn (array $room) => $room['kind'] === 'boss'));

            $camps = array_filter($rooms, fn (array $room) => $room['kind'] === 'camp');
            $this->assertLessThanOrEqual(3, count($camps));

            foreach ($camps as $camp) {
                $this->assertNotContains($layout['boss'], $camp['links'], "Seed {$seed}: campamento junto al jefe.");
            }
        }
    }

    public function test_the_entrance_can_start_at_the_edge_the_middle_or_the_deep_end(): void
    {
        $zones = ['edge' => 0, 'middle' => 0, 'deep' => 0];

        for ($seed = 1; $seed <= 120; $seed++) {
            $layout = DungeonRules::layout('pecados', $seed, 1);
            $width = $layout['width'];
            $entranceX = $layout['rooms'][$layout['entrance']]['x'];

            if ($entranceX === 0 || $entranceX === $width - 1) {
                $zones['edge']++;
            } elseif ($entranceX > $width / 3 && $entranceX < $width * 2 / 3) {
                $zones['middle']++;
            } else {
                $zones['deep']++;
            }
        }

        foreach ($zones as $zone => $count) {
            $this->assertGreaterThan(0, $count, "Ninguna entrada cae en {$zone}.");
        }
    }

    public function test_layouts_are_deterministic_and_vary_with_seed_and_floor(): void
    {
        $this->assertSame(
            DungeonRules::layout('pecados', 555, 2),
            DungeonRules::layout('pecados', 555, 2),
        );

        $shapes = [];

        for ($seed = 1; $seed <= 30; $seed++) {
            $layout = DungeonRules::layout('pecados', $seed, 1);
            $shapes[$layout['width'].'x'.$layout['height'].':'.$layout['boss']] = true;
        }

        $this->assertGreaterThan(5, count($shapes));
        $this->assertNotEquals(
            DungeonRules::layout('pecados', 555, 1),
            DungeonRules::layout('pecados', 555, 2),
        );
    }

    public function test_the_boss_stays_hidden_until_a_neighbouring_room_is_known(): void
    {
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, 1, 12);
        $run->seed = 987654;
        $run->save();

        $layout = DungeonRules::layout('pecados', 987654, 1);
        $view = DungeonRules::mapView('pecados', $run->fresh());
        $boss = collect($view['rooms'])->firstWhere('id', $layout['boss']);

        $this->assertFalse($boss['known']);
        $this->assertNull($boss['kind']);
        $this->assertSame(
            count(array_filter($view['rooms'], fn (array $room) => $room['known'])),
            count(array_unique(array_merge([0], $layout['rooms'][0]['links']))),
        );
    }

    public function test_only_connected_rooms_can_be_reached_and_only_once_the_current_room_is_resolved(): void
    {
        $seed = $this->seedWithNeighbour(['battle', 'treasure', 'altar', 'trap']);
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, 1, 12);
        $run->seed = $seed;
        $run->save();

        $layout = DungeonRules::layout('pecados', $seed, 1);
        $room = $this->neighbourOf($layout, 0, ['battle', 'treasure', 'altar', 'trap']);

        $this->postJson('/mazmorras/pecados/mover', ['room' => $layout['boss']])
            ->assertStatus(422)
            ->assertJsonPath('errors.room.0', 'Esa sala no está conectada con tu posición.');

        $this->postJson('/mazmorras/pecados/mover', ['room' => $room])
            ->assertOk()
            ->assertJsonPath('room', $room);

        $this->postJson('/mazmorras/pecados/mover', ['room' => 0])
            ->assertStatus(422)
            ->assertJsonPath('errors.room.0', 'Resuelve esta sala antes de seguir explorando.');
    }

    public function test_winning_a_battle_clears_only_that_room_and_keeps_the_floor(): void
    {
        $seed = $this->seedWithNeighbour(['battle']);
        $player = $this->createPlayer(['grace' => 0]);
        [$run, $team] = $this->prepareRun($player, 1, 20, 6);
        $run->seed = $seed;
        $run->save();

        $layout = DungeonRules::layout('pecados', $seed, 1);
        $room = $this->neighbourOf($layout, 0, ['battle']);

        $this->postJson('/mazmorras/pecados/mover', ['room' => $room])->assertOk();

        $this->postJson('/mazmorras/pecados/batalla')
            ->assertOk()
            ->assertJsonPath('result', 'win')
            ->assertJsonPath('room', $room)
            ->assertJsonPath('run_floor', 1);

        $fresh = $run->fresh();

        $this->assertSame(1, $fresh->floor);
        $this->assertContains($room, $fresh->cleared);
        $this->assertSame($room, $fresh->room);
        $this->assertSame($team, $player->fresh()->teamIds());
    }

    public function test_defeating_the_hidden_boss_advances_to_a_new_floor(): void
    {
        $seed = 987654;
        $player = $this->createPlayer(['grace' => 0]);
        [$run] = $this->prepareRun($player, 1, 20, 6);
        $layout = DungeonRules::layout('pecados', $seed, 1);

        $run->seed = $seed;
        $run->room = $layout['boss'];
        $run->cleared = [];
        $run->save();

        $this->postJson('/mazmorras/pecados/batalla')
            ->assertOk()
            ->assertJsonPath('result', 'win')
            ->assertJsonPath('kind', 'boss')
            ->assertJsonPath('run_floor', 2)
            ->assertJsonPath('room', $layout['boss']);

        $fresh = $run->fresh();

        $this->assertSame(2, $fresh->floor);
        $this->assertSame(0, $fresh->room);
        $this->assertSame([], $fresh->cleared);
        $this->assertDatabaseHas('dungeon_progress', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => 1,
        ]);
    }

    public function test_a_loss_keeps_the_floor_and_restores_the_vitals_for_a_retry(): void
    {
        $seed = 424242;
        $layout = DungeonRules::layout('pecados', $seed, 1);
        $player = $this->createPlayer(['grace' => 0]);
        [$run, $team] = $this->prepareRun($player, 1, 1, 1, 1);

        $run->seed = $seed;
        $run->room = $layout['boss'];
        $run->save();

        $encounter = DungeonRules::currentNode($player, 'pecados', $run);

        $this->postJson('/mazmorras/pecados/batalla')
            ->assertOk()
            ->assertJsonPath('result', 'lose')
            ->assertJsonPath('run_floor', 1)
            ->assertJsonPath('next_encounter.kind', 'boss')
            ->assertJsonPath('rewards.grace', (int) round($encounter['grace'] * 0.2));

        $this->assertDatabaseMissing('dungeon_progress', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => 1,
        ]);

        $copy = SaintCopy::where('player_id', $player->id)->where('saint_id', $team[0])->firstOrFail();

        $this->assertSame($copy->stats()['hp'], $run->fresh()->vitals[$team[0]]);
    }

    public function test_a_treasure_room_grants_points_and_is_cleared(): void
    {
        $seed = $this->seedWithNeighbour(['treasure']);
        $player = $this->createPlayer(['grace' => 100]);
        [$run] = $this->prepareRun($player, 1, 12);
        $layout = DungeonRules::layout('pecados', $seed, 1);
        $room = $this->neighbourOf($layout, 0, ['treasure']);
        $node = DungeonRules::roomNode('pecados', $seed, 1, $room);

        $run->seed = $seed;
        $run->room = $room;
        $run->cleared = [];
        $run->save();

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'open'])
            ->assertOk()
            ->assertJsonPath('outcome.type', 'treasure')
            ->assertJsonPath('outcome.amount', $node['treasure'])
            ->assertJsonPath('player.grace', 100 + $node['treasure'])
            ->assertJsonPath('room', $room);

        $this->assertContains($room, $run->fresh()->cleared);
        $this->assertSame(100 + $node['treasure'], $player->fresh()->grace);
    }

    public function test_a_gospel_room_grants_points_and_a_blessing_only_once(): void
    {
        $seed = $this->seedWithNeighbour(['gospel']);
        $player = $this->createPlayer(['grace' => 0]);
        [$run] = $this->prepareRun($player, 1, 12);
        $layout = DungeonRules::layout('pecados', $seed, 1);
        $room = $this->neighbourOf($layout, 0, ['gospel']);

        GospelVerse::factory()->create([
            'book' => 'JHN',
            'chapter' => 3,
            'verse' => 16,
            'text' => 'Porque de tal manera amó Dios al mundo.',
        ]);

        $run->seed = $seed;
        $run->room = $room;
        $run->cleared = [];
        $run->save();

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'read'])
            ->assertOk()
            ->assertJsonPath('outcome.type', 'gospel')
            ->assertJsonPath('blessing', 10)
            ->assertJsonPath('player.grace', 20);

        $this->assertContains($room, $run->fresh()->cleared);

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'read'])
            ->assertStatus(422)
            ->assertJsonPath('errors.action.0', 'Ya leíste este fragmento.');
    }

    public function test_a_trap_can_wound_the_team_and_clear_the_room(): void
    {
        $seed = $this->seedWithNeighbour(['trap']);
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, 1, 12);
        $layout = DungeonRules::layout('pecados', $seed, 1);
        $room = $this->neighbourOf($layout, 0, ['trap']);

        $run->seed = $seed;
        $run->room = $room;
        $run->save();

        $node = DungeonRules::currentNode($player, 'pecados', $run);
        $before = DungeonRules::vitalsFor($player, $run);

        $response = $this->postJson('/mazmorras/pecados/paso', ['action' => 'cross']);

        $response->assertOk()->assertJsonPath('outcome.type', $node['effect'] === 'grace' ? 'grace' : 'damage');

        if ($node['effect'] !== 'grace') {
            $after = $run->fresh()->vitals;

            foreach ($before as $id => $vital) {
                $damage = (int) round($vital['max'] * $node['power'] / 100);

                $this->assertSame(max(1, $vital['hp'] - $damage), $after[$id]);
            }
        }

        $this->assertContains($room, $run->fresh()->cleared);
    }

    public function test_the_virgin_altar_can_heal_or_bless_the_team(): void
    {
        $seed = $this->seedWithNeighbour(['altar']);
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, 1, 12);
        $layout = DungeonRules::layout('pecados', $seed, 1);
        $room = $this->neighbourOf($layout, 0, ['altar']);

        $run->seed = $seed;
        $run->room = $room;
        $this->setAllVitals($run, 1);
        $run->save();

        $node = DungeonRules::currentNode($player, 'pecados', $run);
        $before = DungeonRules::vitalsFor($player, $run);

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'heal'])
            ->assertOk()
            ->assertJsonPath('outcome.type', 'heal');

        foreach ($before as $id => $vital) {
            $healed = min($vital['max'], 1 + (int) round($vital['max'] * $node['heal'] / 100));

            $this->assertSame($healed, $run->fresh()->vitals[$id]);
        }

        $this->assertContains($room, $run->fresh()->cleared);
    }

    public function test_the_virgin_altar_can_bless_the_next_battle(): void
    {
        $seed = $this->seedWithNeighbour(['altar']);
        $player = $this->createPlayer();
        [$run] = $this->prepareRun($player, 1, 12);
        $layout = DungeonRules::layout('pecados', $seed, 1);
        $room = $this->neighbourOf($layout, 0, ['altar']);

        $run->seed = $seed;
        $run->room = $room;
        $run->save();

        $node = DungeonRules::currentNode($player, 'pecados', $run);

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'bless'])
            ->assertOk()
            ->assertJsonPath('outcome.type', 'blessing')
            ->assertJsonPath('outcome.amount', $node['blessing'])
            ->assertJsonPath('blessing', $node['blessing']);

        $this->assertSame($node['blessing'], $run->fresh()->blessing);
    }

    public function test_a_camp_heals_the_team_and_can_update_the_loadout(): void
    {
        $player = $this->createPlayer();
        [$run, $team] = $this->prepareRun($player, 1, 12);
        $this->setAllVitals($run, 1);
        $run->save();

        $chosen = array_slice($team, 0, 2);

        $this->postJson('/mazmorras/pecados/paso', [
            'action' => 'rest',
            'team' => $chosen,
        ])
            ->assertOk()
            ->assertJsonPath('outcome.type', 'camp')
            ->assertJsonPath('team', $chosen);

        $this->assertSame($chosen, $run->fresh()->team);

        foreach ($chosen as $id) {
            $this->assertGreaterThan(1, $run->fresh()->vitals[$id]);
        }
    }

    public function test_a_camp_can_only_be_rested_in_once_per_floor(): void
    {
        $player = $this->createPlayer();
        [$run, $team] = $this->prepareRun($player, 1, 12);
        $this->setAllVitals($run, 1);
        $run->save();

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'rest'])->assertOk();

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'rest'])
            ->assertStatus(422)
            ->assertJsonPath('errors.action.0', 'Ya has descansado en este campamento.');

        $this->postJson('/mazmorras/pecados/paso', ['action' => 'rest', 'team' => array_slice($team, 0, 2)])
            ->assertStatus(422);
    }

    public function test_the_team_can_only_be_changed_at_a_camp(): void
    {
        $seed = $this->seedWithNeighbour(['battle']);
        $player = $this->createPlayer();
        [$run, $team] = $this->prepareRun($player, 1, 12);
        $layout = DungeonRules::layout('pecados', $seed, 1);
        $room = $this->neighbourOf($layout, 0, ['battle']);

        $run->seed = $seed;
        $run->room = $room;
        $run->save();

        $this->postJson('/mazmorras/pecados/equipo', ['team' => array_slice($team, 0, 2)])
            ->assertStatus(422)
            ->assertJsonPath('errors.team.0', 'Solo puedes cambiar de equipo en un campamento.');
    }

    public function test_a_fight_needs_a_team_and_a_combat_room(): void
    {
        $player = $this->createPlayer();
        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 987654;
        $run->save();

        $this->postJson('/mazmorras/pecados/batalla')
            ->assertStatus(422)
            ->assertJsonPath('errors.battle.0', 'Este paso no se resuelve con una batalla.');
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

    public function test_a_new_rift_rerolls_the_labyrinth_but_keeps_the_record(): void
    {
        $player = $this->createPlayer();
        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 1;
        $run->floor = 4;
        $run->room = 5;
        $run->cleared = [2, 3];
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
        $this->assertSame(0, $fresh->room);
        $this->assertSame([], $fresh->cleared);
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
        $run->room = 0;
        $run->cleared = [];
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
     * Semilla cuya entrada tiene un vecino de alguno de los tipos pedidos.
     */
    private function seedWithNeighbour(array $kinds): int
    {
        for ($seed = 1; $seed <= 2000; $seed++) {
            $layout = DungeonRules::layout('pecados', $seed, 1);

            foreach ($layout['rooms'][$layout['entrance']]['links'] as $id) {
                if (in_array($layout['rooms'][$id]['kind'], $kinds, true)) {
                    return $seed;
                }
            }
        }

        $this->fail('No se encontró una semilla con el vecino pedido.');
    }

    /**
     * @param  array{rooms: array<int, array<string, mixed>>}  $layout
     */
    private function neighbourOf(array $layout, int $room, array $kinds): int
    {
        foreach ($layout['rooms'][$room]['links'] as $id) {
            if (in_array($layout['rooms'][$id]['kind'], $kinds, true)) {
                return $id;
            }
        }

        $this->fail('La sala no tiene un vecino con ese tipo.');
    }

    /**
     * @param  array{rooms: array<int, array<string, mixed>>}  $layout
     * @return list<int>
     */
    private function reachableRooms(array $layout, int $from): array
    {
        return array_keys($this->distancesFrom($layout, $from));
    }

    /**
     * @param  array{rooms: array<int, array<string, mixed>>}  $layout
     * @return array<int, int>
     */
    private function distancesFrom(array $layout, int $from): array
    {
        $distances = [$from => 0];
        $queue = [$from];

        for ($i = 0; $i < count($queue); $i++) {
            foreach ($layout['rooms'][$queue[$i]]['links'] as $next) {
                if (! isset($distances[$next])) {
                    $distances[$next] = $distances[$queue[$i]] + 1;
                    $queue[] = $next;
                }
            }
        }

        return $distances;
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
