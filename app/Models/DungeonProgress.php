<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DungeonProgress extends Model
{
    protected $table = 'dungeon_progress';

    protected $fillable = [
        'player_id', 'dungeon', 'stage',
    ];

    protected $casts = [
        'stage' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
