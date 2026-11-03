<?php

namespace Tests\Feature;

use App\Models\DungeonProgress;
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

    public function test_the_dungeon_hub_lists_every_dungeon_and_its_theme(): void
    {
        $this->createPlayer();

        $this->get('/mazmorras')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dungeons')
                ->has('dungeons', 3)
                ->where('dungeons.0.key', 'pecados')
                ->where('dungeons.0.unlocked', true)
                ->where('dungeons.0.theme', 'pecados')
                ->where('dungeons.0.next.floor', 1)
                ->where('dungeons.1.key', 'goetia')
                ->where('dungeons.1.unlocked', false)
                ->where('dungeons.1.theme', 'goetia')
                ->where('dungeons.2.key', 'catacumbas')
                ->where('dungeons.2.unlocked', false)
                ->where('dungeons.2.theme', 'catacumbas')
                ->has('deepest'));
    }

    public function test_the_camp_renders_the_procedural_floor_and_the_owned_roster(): void
    {
        $player = $this->createPlayer();
        $team = $this->equip($player, level: 12);
        $player->team = $team;
        $player->save();

        $encounter = DungeonRules::encounter($player, 'pecados');

        $this->get('/mazmorras/pecados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DungeonRun')
                ->where('dungeon.key', 'pecados')
                ->where('dungeon.theme', 'pecados')
                ->where('encounter.floor', 1)
                ->where('encounter.enemy', $encounter['enemy'])
                ->where('teamIds', $team)
                ->has('roster', 3)
                ->has('history'));
    }

    public function test_a_locked_dungeon_redirects_and_an_unknown_one_is_not_found(): void
    {
        $this->createPlayer();

        $this->get('/mazmorras/goetia')->assertRedirect('/mazmorras');
        $this->get('/mazmorras/catacumbas')->assertRedirect('/mazmorras');
        $this->get('/mazmorras/inexistente')->assertNotFound();
    }

    public function test_the_boss_of_a_floor_is_deterministic_and_never_repeats_the_previous_floor(): void
    {
        $bosses = collect(config('sanctorum.dungeons.pecados.bosses'))->pluck('enemy')->all();
        $previous = null;

        for ($floor = 1; $floor <= 40; $floor++) {
            $encounter = DungeonRules::encounterFor('pecados', 987654, $floor);

            $this->assertSame($floor, $encounter['floor']);
            $this->assertContains($encounter['enemy'], $bosses);
            $this->assertNotSame($encounter['enemy'], $previous);

            $previous = $encounter['enemy'];
        }

        $this->assertSame(
            DungeonRules::encounterFor('pecados', 987654, 12)['enemy'],
            DungeonRules::encounterFor('pecados', 987654, 12)['enemy'],
        );
    }

    public function test_floors_grow_harder_and_richer_with_depth(): void
    {
        $first = DungeonRules::encounterFor('pecados', 4242, 1);
        $deep = DungeonRules::encounterFor('pecados', 4242, 12);

        $this->assertGreaterThan($first['hp'], $deep['hp']);
        $this->assertGreaterThan($first['atk'], $deep['atk']);
        $this->assertGreaterThan($first['grace'], $deep['grace']);
        $this->assertGreaterThan($first['xp'], $deep['xp']);
    }

    public function test_clearing_a_floor_advances_the_run_and_saves_the_team(): void
    {
        $player = $this->createPlayer(['grace' => 0]);
        $team = $this->equip($player, level: 60, copies: 6);
        $encounter = DungeonRules::encounter($player, 'pecados');

        $response = $this->postJson('/mazmorras/pecados/batalla', ['team' => $team]);

        $response->assertOk()
            ->assertJsonPath('result', 'win')
            ->assertJsonPath('dungeon', 'pecados')
            ->assertJsonPath('floor', 1)
            ->assertJsonPath('run_floor', 2)
            ->assertJsonPath('next_encounter.floor', 2)
            ->assertJsonPath('rewards.grace', $encounter['grace'])
            ->assertJsonPath('team', $team);

        $this->assertDatabaseHas('dungeon_progress', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => 1,
        ]);

        $this->assertDatabaseHas('dungeon_runs', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'floor' => 2,
        ]);

        $this->assertDatabaseHas('battles', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => 1,
            'result' => 'win',
        ]);

        $fresh = $player->fresh();

        $this->assertSame($team, $fresh->teamIds());
        $this->assertSame($encounter['grace'], $fresh->grace);
    }

    public function test_a_loss_keeps_the_same_floor_and_only_pays_partial_grace(): void
    {
        mt_srand(20261029);

        $player = $this->createPlayer(['grace' => 0]);
        $team = $this->equip($player, level: 1, copies: 1, amount: 1);
        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 20261029;
        $run->save();
        $encounter = DungeonRules::encounter($player, 'pecados');

        $response = $this->postJson('/mazmorras/pecados/batalla', ['team' => $team]);

        $response->assertOk()
            ->assertJsonPath('result', 'lose')
            ->assertJsonPath('floor', 1)
            ->assertJsonPath('run_floor', 1)
            ->assertJsonPath('next_encounter.floor', 1)
            ->assertJsonPath('rewards.grace', (int) round($encounter['grace'] * 0.2));

        $this->assertDatabaseMissing('dungeon_progress', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => 1,
        ]);

        $this->assertDatabaseHas('battles', [
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => 1,
            'result' => 'lose',
        ]);
    }

    public function test_a_fight_needs_an_owned_team(): void
    {
        $player = $this->createPlayer();

        $this->postJson('/mazmorras/pecados/batalla', ['team' => []])
            ->assertStatus(422)
            ->assertJsonPath('errors.team.0', 'Elige al menos un santo antes de entrar en la mazmorra.');

        $this->postJson('/mazmorras/pecados/batalla', ['team' => [999999]])
            ->assertStatus(422)
            ->assertJsonPath('errors.team.0', 'Ese santo no forma parte de tu santoral.');

        $this->assertSame(0, $player->fresh()->wins);
    }

    public function test_a_sealed_dungeon_cannot_be_fought(): void
    {
        $player = $this->createPlayer();
        $team = $this->equip($player);

        $this->postJson('/mazmorras/goetia/batalla', ['team' => $team])
            ->assertStatus(422)
            ->assertJsonPath('errors.battle.0', 'La mazmorra todavía está sellada.');
    }

    public function test_goetia_and_catacumbas_open_with_prior_progress(): void
    {
        $player = $this->createPlayer();

        foreach ([1, 2] as $stage) {
            DungeonProgress::create([
                'player_id' => $player->id,
                'dungeon' => 'pecados',
                'stage' => $stage,
            ]);
        }

        $this->get('/mazmorras')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dungeons.1.unlocked', false)
                ->where('dungeons.2.unlocked', false));

        DungeonProgress::create([
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'stage' => 3,
        ]);

        $this->get('/mazmorras')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dungeons.1.unlocked', true)
                ->where('dungeons.2.unlocked', false));

        foreach ([1, 2, 3] as $stage) {
            DungeonProgress::create([
                'player_id' => $player->id,
                'dungeon' => 'goetia',
                'stage' => $stage,
            ]);
        }

        $this->get('/mazmorras')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dungeons.1.unlocked', true)
                ->where('dungeons.2.unlocked', true));
    }

    public function test_a_new_rift_rerolls_the_seed_but_keeps_the_best_floor(): void
    {
        $player = $this->createPlayer();
        $run = DungeonRules::run($player, 'pecados');
        $run->floor = 4;
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
        $this->assertNotSame($run->seed, $fresh->seed);
        $this->assertSame(3, DungeonRules::bestFloor($player, 'pecados'));
        $this->assertSame(3, DungeonRules::clearedCount($player, 'pecados'));
    }

    public function test_the_camp_exposes_the_procedural_path_of_the_next_floors(): void
    {
        $player = $this->createPlayer();
        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 987654;
        $run->save();

        $path = DungeonRules::pathPreview($player, 'pecados', $run);

        $this->assertCount(3, $path);
        $this->assertSame($run->floor, $path[0]['floor']);
        $this->assertCount(2, $path[0]['options']);
        $this->assertNotSame($path[0]['options'][0]['enemy'], $path[0]['options'][1]['enemy']);

        foreach ($path as $row) {
            $this->assertSame(
                DungeonRules::forkCount('pecados', $run->seed, $row['floor']),
                count($row['options']),
            );

            foreach ($row['options'] as $option) {
                $this->assertSame(
                    DungeonRules::encounterFor('pecados', $run->seed, $row['floor'], $option['branch'])['enemy'],
                    $option['enemy'],
                );
            }
        }

        $this->get('/mazmorras/pecados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('path', 3)
                ->where('path.0.options.0.branch', 0)
                ->where('path.0.options.1.branch', 1)
                ->where('dungeon.route', 0));
    }

    public function test_every_fork_offers_two_different_bosses(): void
    {
        foreach (range(1, 25) as $seed) {
            for ($floor = 1; $floor <= 8; $floor++) {
                if (DungeonRules::forkCount('pecados', $seed, $floor) < 2) {
                    continue;
                }

                $this->assertNotSame(
                    DungeonRules::encounterFor('pecados', $seed, $floor, 0)['enemy'],
                    DungeonRules::encounterFor('pecados', $seed, $floor, 1)['enemy'],
                );
            }
        }
    }

    public function test_choosing_a_branch_is_saved_and_changes_the_encounter(): void
    {
        $player = $this->createPlayer();
        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 987654;
        $run->save();

        $encounters = [];

        foreach ([0, 1] as $branch) {
            $encounters[$branch] = DungeonRules::encounterFor('pecados', $run->seed, 1, $branch)['enemy'];
        }

        $this->assertNotSame($encounters[0], $encounters[1]);

        $this->postJson('/mazmorras/pecados/ruta', ['branch' => 1])
            ->assertOk()
            ->assertJsonPath('branch', 1)
            ->assertJsonPath('encounter.enemy', $encounters[1]);

        $this->assertSame(1, $run->fresh()->route);

        $this->get('/mazmorras/pecados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dungeon.route', 1)
                ->where('encounter.enemy', $encounters[1]));
    }

    public function test_the_chosen_branch_is_used_by_the_floor_battle(): void
    {
        $player = $this->createPlayer(['grace' => 0]);
        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 987654;
        $run->save();

        $team = $this->equip($player, level: 60, copies: 6);

        $this->postJson('/mazmorras/pecados/ruta', ['branch' => 1])->assertOk();

        $this->postJson('/mazmorras/pecados/batalla', ['team' => $team])
            ->assertOk()
            ->assertJsonPath('result', 'win')
            ->assertJsonPath(
                'history_entry.enemy',
                DungeonRules::encounterFor('pecados', $run->seed, 1, 1)['enemy'],
            );
    }

    public function test_clearing_a_floor_resets_the_chosen_branch(): void
    {
        $player = $this->createPlayer(['grace' => 0]);
        $run = DungeonRules::run($player, 'pecados');
        $run->seed = 987654;
        $run->save();

        $team = $this->equip($player, level: 60, copies: 6);

        $this->postJson('/mazmorras/pecados/ruta', ['branch' => 1])->assertOk();
        $this->postJson('/mazmorras/pecados/batalla', ['team' => $team])
            ->assertOk()
            ->assertJsonPath('run_floor', 2);

        $fresh = $run->fresh();

        $this->assertSame(2, $fresh->floor);
        $this->assertSame(0, $fresh->route);
    }

    public function test_a_branch_cannot_be_chosen_in_a_sealed_dungeon(): void
    {
        $player = $this->createPlayer();
        $this->equip($player);

        $this->postJson('/mazmorras/goetia/ruta', ['branch' => 1])
            ->assertStatus(403);
    }

    public function test_the_branch_must_be_zero_or_one(): void
    {
        $player = $this->createPlayer();
        DungeonRules::run($player, 'pecados');

        $this->postJson('/mazmorras/pecados/ruta', ['branch' => 3])
            ->assertStatus(422)
            ->assertJsonValidationErrors('branch');
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
}
