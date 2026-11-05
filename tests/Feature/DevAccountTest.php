<?php

namespace Tests\Feature;

use App\Models\DungeonRun;
use App\Models\Player;
use Database\Seeders\SaintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DevAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SaintSeeder::class);
    }

    public function test_the_home_offers_the_account_reset_outside_production(): void
    {
        $this->createPlayer();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sanctuary')
                ->where('canResetAccount', true));
    }

    public function test_resetting_the_account_deletes_progress_and_starts_a_new_player(): void
    {
        $player = $this->createPlayer(['grace' => 500]);
        $oldToken = $player->token;

        DungeonRun::create([
            'player_id' => $player->id,
            'dungeon' => 'pecados',
            'floor' => 4,
            'seed' => 1,
            'route' => 1,
            'team' => [],
            'vitals' => [],
            'blessing' => 0,
        ]);

        $this->post('/desarrollo/reiniciar-cuenta')
            ->assertRedirect('/')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('players', ['id' => $player->id]);
        $this->assertDatabaseMissing('dungeon_runs', ['player_id' => $player->id]);

        $this->get('/')->assertOk();

        $fresh = Player::sole();

        $this->assertNotSame($oldToken, $fresh->token);
        $this->assertSame(0, $fresh->grace);
    }
}
