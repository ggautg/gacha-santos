<?php

namespace App\Console\Commands;

use App\Services\BibleImporter;
use Illuminate\Console\Command;
use Throwable;

/** Importa los Evangelios desde la API externa a la base de datos. */
class BibleImport extends Command
{
    protected $signature = 'bible:import {--translation= : Traducción de la API (por defecto la de config/bible.php)}';

    protected $description = 'Importa los Evangelios desde la API de la Biblia a la base de datos';

    /** Ejecuta la importación y devuelve el estado de salida de la consola. */
    public function handle(BibleImporter $importer): int
    {
        $this->components->info('Importando los cuatro Evangelios…');

        try {
            $saved = $importer->importGospels($this->option('translation') ?: null);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Versículos guardados: {$saved}.");

        return self::SUCCESS;
    }
}
