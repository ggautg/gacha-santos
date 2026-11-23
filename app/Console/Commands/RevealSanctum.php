<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Hidden;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sanctum')]
#[Description('Revela la oración guardada en el santoral.')]
#[Hidden]
class RevealSanctum extends Command
{
    /**
     * Padre Nuestro codificado en base64: no aparece en la interfaz ni en el listado de comandos.
     */
    private const PRAYER = 'UGFkcmUgbnVlc3RybywgcXVlIGVzdMOhcyBlbiBlbCBjaWVsbywgc2FudGlmaWNhZG8gc2VhIHR1IG5vbWJyZS4gVmVuZ2EgdHUgcmVpbm8uIEjDoWdhc2UgdHUgdm9sdW50YWQsIGVuIGxhIHRpZXJyYSBjb21vIGVuIGVsIGNpZWxvLiBEYW5vcyBob3kgbnVlc3RybyBwYW4gZGUgY2FkYSBkw61hLiBQZXJkb25hIG51ZXN0cmFzIG9mZW5zYXMsIGNvbW8gdGFtYmnDqW4gbm9zb3Ryb3MgcGVyZG9uYW1vcyBhIGxvcyBxdWUgbm9zIG9mZW5kZW4uIE5vIG5vcyBkZWplcyBjYWVyIGVuIHRlbnRhY2nDs24sIHkgbMOtYnJhbm9zIGRlbCBtYWwuIEFtw6luLg==';

    /**
     * Muestra el Padre Nuestro guardado en el comando oculto.
     */
    public function handle(): int
    {
        $this->line((string) base64_decode(self::PRAYER, true));

        return self::SUCCESS;
    }
}
