<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Saint;
use App\Models\SaintCopy;
use Database\Seeders\SaintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GachaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SaintSeeder::class);
    }

    public function test_a_single_pull_costs_grace_and_returns_one_card(): void
    {
        $player = $this->createPlayer();

        $this->postJson('/gacha', ['count' => 1])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'cards');

        $this->assertSame(900 - config('sanctorum.pull_cost'), $player->fresh()->grace);
    }

    public function test_a_ten_pull_costs_the_discounted_price_and_guarantees_a_four_star(): void
    {
        $player = $this->createPlayer();

        $response = $this->postJson('/gacha', ['count' => 10]);

        $response->assertOk()->assertJsonCount(10, 'cards');

        $rarities = collect($response->json('cards'))->pluck('rarity');

        $this->assertGreaterThanOrEqual(4, $rarities->max());

        $refund = collect($response->json('cards'))
            ->where('is_new', false)
            ->sum(fn (array $card) => config('sanctorum.duplicate_grace')[$card['rarity']]);

        $this->assertSame(
            900 - config('sanctorum.ten_pull_cost') + $refund,
            $player->fresh()->grace,
        );
    }

    public function test_pity_guarantees_a_five_star_pull(): void
    {
        $player = $this->createPlayer(['pity' => config('sanctorum.pity') - 1]);

        $this->postJson('/gacha', ['count' => 1])
            ->assertOk()
            ->assertJsonPath('cards.0.rarity', 5)
            ->assertJsonPath('pity', 0);

        $this->assertSame(0, $player->fresh()->pity);
    }

    public function test_a_pull_without_enough_grace_is_rejected(): void
    {
        $this->createPlayer(['grace' => 10]);

        $this->postJson('/gacha', ['count' => 1])
            ->assertStatus(422)
            ->assertJsonPath('errors.gacha.0', 'No tienes suficientes Puntos de Fe. Gana más descendiendo a las mazmorras.');
    }

    public function test_there_is_no_free_daily_pull(): void
    {
        $this->createPlayer(['grace' => 0]);

        $this->postJson('/gacha', ['count' => 1, 'free' => true])
            ->assertStatus(422)
            ->assertJsonPath('errors.gacha.0', 'No tienes suficientes Puntos de Fe. Gana más descendiendo a las mazmorras.');
    }

    public function test_pulling_an_owned_saint_refunds_extra_grace(): void
    {
        $player = $this->createPlayer();

        Saint::query()->each(function (Saint $saint) use ($player): void {
            SaintCopy::create([
                'player_id' => $player->id,
                'saint_id' => $saint->id,
                'copies' => 1,
                'level' => 1,
                'xp' => 0,
            ]);
        });

        $response = $this->postJson('/gacha', ['count' => 1]);

        $response->assertOk()->assertJsonPath('cards.0.is_new', false);

        $refund = config('sanctorum.duplicate_grace')[$response->json('cards.0.rarity')];

        $this->assertSame(
            900 - config('sanctorum.pull_cost') + $refund,
            $player->fresh()->grace,
        );
    }

    public function test_a_new_player_receives_the_three_starter_saints_as_the_team(): void
    {
        $this->get('/')->assertOk();

        $player = Player::query()->latest('id')->firstOrFail();
        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->get();

        $this->assertCount(3, $copies);
        $this->assertSame(0, $player->grace);
        $this->assertEqualsCanonicalizing(
            config('sanctorum.starter_slugs'),
            $copies->pluck('saint.slug')->all(),
        );

        $this->assertSame(
            Saint::whereIn('slug', config('sanctorum.starter_slugs'))->pluck('id')->sort()->values()->all(),
            collect($player->teamIds())->sort()->values()->all(),
        );
    }
}
