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
    public function saint(Saint $saint): Response
    {
        return $this->svg(
            PixelArt::saint($saint->art, $saint->rarity, '', $saint->name),
        );
    }

    public function foe(string $key): Response
    {
        abort_unless(PixelArt::hasFoe($key), 404);

        return $this->svg(PixelArt::foe($key));
    }

    public function mystery(): Response
    {
        return $this->svg(PixelArt::mystery());
    }

    private function svg(string $svg): Response
    {
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
