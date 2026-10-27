<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Player extends Model
{
    protected $fillable = [
        'token', 'name', 'grace', 'pity', 'ten_pity', 'depth',
        'wins', 'losses', 'free_pull_at', 'team',
    ];

    protected $casts = [
        'team' => 'array',
        'free_pull_at' => 'datetime',
        'grace' => 'integer',
        'pity' => 'integer',
        'ten_pity' => 'integer',
        'depth' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $player) {
            $player->token ??= (string) Str::uuid();
        });
    }

    public function copies(): HasMany
    {
        return $this->hasMany(SaintCopy::class);
    }

    public function pulls(): HasMany
    {
        return $this->hasMany(Pull::class);
    }

    public function dungeonProgress(): HasMany
    {
        return $this->hasMany(DungeonProgress::class);
    }

    public function dungeonRuns(): HasMany
    {
        return $this->hasMany(DungeonRun::class);
    }

    public function battles(): HasMany
    {
        return $this->hasMany(Battle::class);
    }

    public function freePullReady(): bool
    {
        return $this->free_pull_at === null || $this->free_pull_at->isPast();
    }

    public function secondsUntilFreePull(): int
    {
        if ($this->freePullReady()) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->free_pull_at));
    }

    public function teamIds(): array
    {
        return array_values(array_map('intval', $this->team ?? []));
    }

    public function addGrace(int $amount): void
    {
        $this->grace = max(0, $this->grace + $amount);
    }
}
