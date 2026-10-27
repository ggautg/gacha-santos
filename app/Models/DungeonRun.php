<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DungeonRun extends Model
{
    protected $fillable = [
        'player_id', 'dungeon', 'seed', 'floor',
    ];

    protected $casts = [
        'seed' => 'integer',
        'floor' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
