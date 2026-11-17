<?php

namespace Tests\Feature;

use App\Console\Commands\RevealSanctum;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SanctumCommandTest extends TestCase
{
    public function test_the_prayer_is_only_revealed_by_the_hidden_sanctum_command(): void
    {
        $command = Artisan::all()['sanctum'];

        $this->assertInstanceOf(RevealSanctum::class, $command);
        $this->assertTrue($command->isHidden());

        Artisan::call('sanctum');

        $output = Artisan::output();

        $this->assertStringContainsString('Padre nuestro, que estás en el cielo', $output);
        $this->assertStringContainsString('líbranos del mal. Amén.', $output);
    }

    public function test_the_prayer_is_not_written_in_the_interface(): void
    {
        foreach (File::allFiles(resource_path()) as $file) {
            if (in_array($file->getExtension(), ['vue', 'js', 'css', 'php'], true)) {
                $this->assertStringNotContainsString('Padre nuestro', (string) File::get($file->getPathname()));
            }
        }
    }
}
