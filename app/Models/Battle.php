<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Battle extends Model
{
    protected $fillable = [
        'player_id', 'stage', 'dungeon', 'enemy_key', 'result',
        'grace_reward', 'xp_reward', 'log',
    ];

    protected $casts = [
        'stage' => 'integer',
        'grace_reward' => 'integer',
        'xp_reward' => 'integer',
        'log' => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
