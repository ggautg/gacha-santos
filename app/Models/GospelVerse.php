<?php

namespace App\Models;

use Database\Factories\GospelVerseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Versículo bíblico importado y mostrado como fragmento en las mazmorras.
 *
 * `book` guarda la clave configurada en `config/bible.php`, de modo que el
 * nombre legible del libro se resuelve al formar la cita.
 */
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
