<?php

namespace Tests\Feature;

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
            ->assertJsonPath('free', false)
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
            ->assertJsonPath('errors.gacha.0', 'No tienes suficiente Gracia. Gana más descendiendo a las mazmorras.');
    }

    public function test_the_free_pull_can_only_be_used_once_per_window(): void
    {
        $player = $this->createPlayer(['grace' => 0]);

        $this->postJson('/gacha', ['count' => 1, 'free' => true])
            ->assertOk()
            ->assertJsonPath('free', true);

        $this->assertNotNull($player->fresh()->free_pull_at);

        $this->postJson('/gacha', ['count' => 1, 'free' => true])
            ->assertStatus(422);
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
}
