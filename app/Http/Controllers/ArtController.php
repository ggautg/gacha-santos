<?php

namespace App\Http\Controllers;

use App\Models\Saint;
use App\Support\PixelArt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve el arte pixel-art como SVG cacheable.
 */
class ArtController extends Controller
{
    /** Devuelve el retrato pixel-art del santo como SVG. */
    public function saint(Saint $saint): Response
    {
        return $this->svg(
            PixelArt::saint($saint->art, $saint->rarity, '', $saint->name),
        );
    }

    /** Devuelve el arte de batalla del santo como SVG. */
    public function battle(Saint $saint): Response
    {
        return $this->svg(PixelArt::battle($saint->art, '', $saint->name));
    }

    /** Devuelve el arte del enemigo indicado, o 404 si la clave no existe. */
    public function foe(string $key): Response
    {
        abort_unless(PixelArt::hasFoe($key), 404);

        return $this->svg(PixelArt::foe($key));
    }

    /** Devuelve el arte de la carta velada como SVG. */
    public function mystery(): Response
    {
        return $this->svg(PixelArt::mystery());
    }

    /** Envía el SVG con caché inmutable y cabeceras de tipo seguras. */
    private function svg(string $svg): Response
    {
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
