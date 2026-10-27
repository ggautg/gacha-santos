<?php

namespace Tests;

use App\Models\Player;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Crea un peregrino y lo enlaza a la sesión de la petición actual.
     */
    protected function createPlayer(array $attributes = []): Player
    {
        $player = Player::create(array_merge([
            'name' => 'Peregrino',
            'grace' => 900,
            'depth' => 0,
        ], $attributes));

        $this->withSession(['player_token' => $player->token]);

        return $player;
    }
}
