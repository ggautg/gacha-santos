<?php

namespace Tests\Feature;

use App\Models\GospelVerse;
use App\Services\BibleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BibleImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_gospels_are_imported_and_stored_once_per_verse(): void
    {
        Http::fake(fn (Request $request) => Http::response($this->chapter($request->url())));

        $saved = app(BibleImporter::class)->importGospels('spa_r09');

        // 89 capítulos de los cuatro Evangelios, dos versículos cada uno.
        $this->assertSame(178, $saved);
        $this->assertSame(178, GospelVerse::count());

        $this->assertDatabaseHas('gospel_verses', [
            'translation' => 'spa_r09',
            'book' => 'JHN',
            'chapter' => 3,
            'verse' => 2,
            'text' => 'Segunda parte',
        ]);

        $this->assertSame('Juan 3:2', GospelVerse::where('book', 'JHN')->where('chapter', 3)->where('verse', 2)->firstOrFail()->reference());

        app(BibleImporter::class)->importGospels('spa_r09');

        $this->assertSame(178, GospelVerse::count());
    }

    public function test_the_import_command_reports_a_download_failure(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('bible:import')->assertExitCode(1);

        $this->assertSame(0, GospelVerse::count());
    }

    /**
     * Capítulo falso con la misma forma que devuelve la API.
     *
     * @return array<string, mixed>
     */
    private function chapter(string $url): array
    {
        preg_match('#/([A-Z0-9]+)/(\d+)\.json$#', $url, $match);

        return [
            'chapter' => [
                'content' => [
                    ['type' => 'line_break', 'paragraph' => 'p'],
                    ['type' => 'verse', 'number' => 1, 'content' => ["Texto {$match[1]} {$match[2]}:1"]],
                    ['type' => 'verse', 'number' => 2, 'content' => ['Segunda', 'parte']],
                ],
                'footnotes' => [],
            ],
        ];
    }
}
