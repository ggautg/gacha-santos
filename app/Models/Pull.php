<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pull extends Model
{
    protected $fillable = ['player_id', 'saint_id', 'rarity', 'is_new', 'batch'];

    protected $casts = [
        'is_new' => 'boolean',
        'rarity' => 'integer',
    ];

    public function saint(): BelongsTo
    {
        return $this->belongsTo(Saint::class);
    }
}
