<?php

namespace Tests\Feature;

use App\Models\Saint;
use Database\Seeders\SaintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SanctuaryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SaintSeeder::class);
    }

    public function test_the_sanctuary_renders_the_featured_saint_and_gacha_offers(): void
    {
        $this->createPlayer();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sanctuary')
                ->where('featured.slug', 'san-miguel-arcangel')
                ->where('costs.single', (int) config('sanctorum.pull_cost'))
                ->where('costs.ten', (int) config('sanctorum.ten_pull_cost'))
                ->where('player.dungeons.total', 3)
                ->where('player.dungeons.open', 1)
                ->where('player.dungeons.deepest', 0)
                ->has('rates', 5)
                ->has('banner')
                ->has('rarityProgress', 5));
    }

    public function test_the_collection_team_and_dungeon_pages_render(): void
    {
        $this->createPlayer();

        $this->get('/coleccion')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Collection')
                ->has('saints', Saint::count())
                ->has('level'));

        $this->get('/equipo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team')
                ->has('roster')
                ->has('teamIds'));

        $this->get('/mazmorras')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dungeons')
                ->has('dungeons', 3));

        $this->get('/mazmorras/pecados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DungeonRun')
                ->has('encounter')
                ->has('roster')
                ->has('teamIds'));

        $this->get('/batallas')->assertNotFound();
    }

    public function test_saint_and_foe_art_are_served_as_svg(): void
    {
        $this->createPlayer();

        $this->get('/retratos/san-miguel-arcangel')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertSee('<svg', false);

        $this->get('/enemigos/susurro')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertSee('<svg', false);

        $this->get('/enemigos/inexistente')->assertNotFound();
    }
}
