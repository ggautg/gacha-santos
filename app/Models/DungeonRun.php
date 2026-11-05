<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DungeonRun extends Model
{
    protected $fillable = [
        'player_id', 'dungeon', 'seed', 'floor', 'route', 'room', 'cleared',
        'team', 'vitals', 'blessing',
    ];

    protected $casts = [
        'seed' => 'integer',
        'floor' => 'integer',
        'route' => 'integer',
        'room' => 'integer',
        'cleared' => 'array',
        'team' => 'array',
        'vitals' => 'array',
        'blessing' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
