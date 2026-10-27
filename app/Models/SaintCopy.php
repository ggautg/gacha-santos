<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaintCopy extends Model
{
    protected $fillable = ['player_id', 'saint_id', 'copies', 'level', 'xp'];

    protected $casts = [
        'copies' => 'integer',
        'level' => 'integer',
        'xp' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function saint(): BelongsTo
    {
        return $this->belongsTo(Saint::class);
    }

    public function xpForNextLevel(): int
    {
        if ($this->level >= config('sanctorum.max_level')) {
            return 0;
        }

        return (int) (60 * $this->level ** 1.35);
    }

    public function stats(): array
    {
        return $this->saint->statsForLevel($this->level, $this->copies);
    }

    public function power(): int
    {
        $stats = $this->stats();

        return (int) round(
            $stats['hp'] * 0.55
            + $stats['atk'] * 3.1
            + $stats['def'] * 2.2
            + $stats['spd'] * 1.4
            + $this->saint->rarity * 60
        );
    }
}
