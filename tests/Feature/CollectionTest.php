<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Saint;
use App\Models\SaintCopy;
use Database\Seeders\SaintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SaintSeeder::class);
    }

    public function test_lighting_up_a_saint_costs_grace_and_raises_its_level(): void
    {
        $player = $this->createPlayer();
        $copy = $this->ownedCopy($player, level: 1);

        $this->postJson("/santos/{$copy->saint->slug}/iluminar")
            ->assertOk()
            ->assertJsonPath('level', 2)
            ->assertJsonPath('grace', 900 - config('sanctorum.level_cost_base'));

        $this->assertSame(2, $copy->fresh()->level);
    }

    public function test_a_saint_at_max_level_cannot_be_lit_again(): void
    {
        $player = $this->createPlayer();
        $copy = $this->ownedCopy($player, level: config('sanctorum.max_level'));

        $this->postJson("/santos/{$copy->saint->slug}/iluminar")
            ->assertStatus(422)
            ->assertJsonPath('errors.level.0', 'Este santo ya alcanzó la santidad plena (nivel '.config('sanctorum.max_level').').');
    }

    public function test_a_saint_that_is_not_owned_cannot_be_lit(): void
    {
        $this->createPlayer();

        $saint = Saint::orderByDesc('rarity')->firstOrFail();

        $this->postJson("/santos/{$saint->slug}/iluminar")
            ->assertStatus(422)
            ->assertJsonPath('errors.level.0', 'Todavía no has invocado a este santo.');
    }

    public function test_lighting_up_requires_enough_grace(): void
    {
        $player = $this->createPlayer(['grace' => 10]);
        $copy = $this->ownedCopy($player, level: 3);

        $this->postJson("/santos/{$copy->saint->slug}/iluminar")
            ->assertStatus(422)
            ->assertJsonPath('errors.level.0', 'Necesitas 135 de Gracia para iluminar a este santo.');
    }

    public function test_unowned_saints_are_completely_veiled(): void
    {
        $player = $this->createPlayer();
        $owned = Saint::orderBy('sort')->firstOrFail();

        SaintCopy::create([
            'player_id' => $player->id,
            'saint_id' => $owned->id,
            'copies' => 1,
            'level' => 1,
            'xp' => 0,
        ]);

        $page = $this->get('/coleccion')->assertOk()->viewData('page');
        $saints = collect($page['props']['saints']);
        $first = $saints->first();

        $this->assertCount(Saint::count(), $saints);
        $this->assertFalse($first['mystery']);
        $this->assertSame($owned->name, $first['name']);

        $veiled = $saints->firstWhere('mystery', true);

        $this->assertSame('Santo velado', $veiled['name']);
        $this->assertNull($veiled['rarity']);
        $this->assertNull($veiled['slug']);
        $this->assertNull($veiled['stats']);
        $this->assertNull($veiled['skill']);
        $this->assertNull($veiled['owned']);
        $this->assertSame('/retratos/velado', $veiled['art']);

        $this->assertTrue($saints->slice(0, 1)->every(fn (array $saint) => $saint['owned'] !== null));
        $this->assertTrue($saints->slice(1)->every(fn (array $saint) => $saint['mystery'] === true));

        $veiledJson = json_encode($saints->where('mystery', true)->all(), JSON_UNESCAPED_UNICODE);

        Saint::whereKeyNot($owned->id)->pluck('name')->each(
            fn (string $name) => $this->assertStringNotContainsString($name, $veiledJson),
        );
    }

    private function ownedCopy(Player $player, int $level = 1): SaintCopy
    {
        $saint = Saint::orderByDesc('rarity')->firstOrFail();

        return SaintCopy::create([
            'player_id' => $player->id,
            'saint_id' => $saint->id,
            'copies' => 1,
            'level' => $level,
            'xp' => 0,
        ])->setRelation('saint', $saint);
    }
}
