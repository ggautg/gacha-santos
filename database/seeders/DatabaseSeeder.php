<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Poblador principal: carga el catálogo de santos. */
class DatabaseSeeder extends Seeder
{
    /** Ejecuta los pobladores necesarios para que la aplicación arranque. */
    public function run(): void
    {
        $this->call(SaintSeeder::class);
    }
}
