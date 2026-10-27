<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Saint;
use App\Models\SaintCopy;
use Database\Seeders\SaintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SaintSeeder::class);
    }

    public function test_saving_a_team_keeps_only_owned_saints_and_returns_its_power(): void
    {
        $player = $this->createPlayer();

        $first = $this->ownedCopy($player, level: 5);
        $second = $this->ownedCopy($player, level: 3);

        $response = $this->postJson('/equipo', [
            'team' => [$first->saint_id, $second->saint_id, 999999],
        ]);

        $response->assertOk()
            ->assertJsonPath('team', [$first->saint_id, $second->saint_id])
            ->assertJsonPath('ok', true);

        $this->assertGreaterThan(0, $response->json('power'));
        $this->assertSame([$first->saint_id, $second->saint_id], $player->fresh()->teamIds());
    }

    public function test_a_team_is_deduplicated_and_limited_to_three_saints(): void
    {
        $player = $this->createPlayer();

        $copies = collect(range(1, 4))
            ->map(fn (): SaintCopy => $this->ownedCopy($player));

        $ids = $copies->pluck('saint_id')->all();

        $this->postJson('/equipo', ['team' => [$ids[0], $ids[0], $ids[1]]])
            ->assertOk()
            ->assertJsonPath('team', [$ids[0], $ids[1]]);

        $this->postJson('/equipo', ['team' => [$ids[0], $ids[1], $ids[2], $ids[3]]])
            ->assertStatus(422);
    }

    public function test_an_empty_team_is_rejected(): void
    {
        $this->createPlayer();

        $this->postJson('/equipo', ['team' => []])
            ->assertStatus(422)
            ->assertJsonPath('errors.team.0', 'Tu equipo necesita al menos un santo.');
    }

    private function ownedCopy(Player $player, int $level = 1): SaintCopy
    {
        $saint = Saint::whereNotIn('id', SaintCopy::where('player_id', $player->id)->pluck('saint_id'))
            ->inRandomOrder()
            ->firstOrFail();

        return SaintCopy::create([
            'player_id' => $player->id,
            'saint_id' => $saint->id,
            'copies' => 1,
            'level' => $level,
            'xp' => 0,
        ]);
    }
}
