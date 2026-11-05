<?php

namespace App\Models;

use Database\Factories\GospelVerseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GospelVerse extends Model
{
    /** @use HasFactory<GospelVerseFactory> */
    use HasFactory;

    protected $fillable = [
        'translation', 'book', 'chapter', 'verse', 'text',
    ];

    protected $casts = [
        'chapter' => 'integer',
        'verse' => 'integer',
    ];

    /**
     * Cita legible, por ejemplo "Juan 3:16".
     */
    public function reference(): string
    {
        return config("bible.books.{$this->book}", $this->book).' '.$this->chapter.':'.$this->verse;
    }
}
