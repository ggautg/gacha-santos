<?php

namespace App\Services;

use App\Models\GospelVerse;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BibleImporter
{
    /**
     * Descarga los capítulos de los cuatro Evangelios y guarda cada versículo.
     *
     * @return int Número de versículos guardados o actualizados.
     */
    public function importGospels(?string $translation = null): int
    {
        $translation ??= config('bible.translation');
        $saved = 0;

        foreach (config('bible.chapters') as $book => $chapters) {
            for ($chapter = 1; $chapter <= $chapters; $chapter++) {
                $saved += $this->importChapter($translation, $book, $chapter);
            }
        }

        return $saved;
    }

    public function importChapter(string $translation, string $book, int $chapter): int
    {
        $url = sprintf('%s/%s/%s/%d.json', config('bible.base_url'), $translation, $book, $chapter);
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withOptions(['verify' => config('bible.ca_bundle') ?: true])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("No se pudo descargar {$book} {$chapter} ({$response->status()}).");
        }

        $now = now();
        $rows = [];

        foreach ($response->json('chapter.content', []) as $block) {
            if (($block['type'] ?? null) !== 'verse') {
                continue;
            }

            $rows[] = [
                'translation' => $translation,
                'book' => $book,
                'chapter' => $chapter,
                'verse' => (int) $block['number'],
                'text' => trim(implode(' ', $block['content'] ?? [])),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        GospelVerse::upsert($rows, ['translation', 'book', 'chapter', 'verse'], ['text', 'updated_at']);

        return count($rows);
    }
}
