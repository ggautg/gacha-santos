<?php

namespace App\Support;

/**
 * Generador de arte pixel-art en SVG.
 *
 * Cada santo se dibuja en una rejilla de 24x28 píxeles usando una paleta y
 * un conjunto de rasgos (halo, hábito, tocado, barba, atributo). Así cada
 * carta tiene un retrato único sin depender de imágenes externas.
 */
final class PixelArt
{
    public const W = 24;

    public const H = 28;

    private const SAINT_DEFAULTS = [
        'bg1' => '#171334',
        'bg2' => '#231d4d',
        'skin' => '#e6b184',
        'hair' => '#4a2c1a',
        'robe' => '#3d5a9e',
        'mantle' => '#8c2f39',
        'trim' => '#f2c94c',
        'halo' => '#ffd75e',
        'headgear' => 'none',
        'hairStyle' => 'short',
        'beard' => 'none',
        'accessory' => 'none',
        'side' => 'left',
        'eye' => '#2a1b33',
        'blush' => false,
        'veil' => '#31355a',
    ];

    private const FOES = [
        'susurro' => [
            'bg1' => '#151129', 'bg2' => '#241a3d', 'body' => '#4b3d6b', 'dark' => '#2a2142',
            'eye' => '#9df3ff', 'aura' => '#7a5cc4', 'features' => ['hood', 'wisp'],
        ],
        'cuervo' => [
            'bg1' => '#101b23', 'bg2' => '#1a2c35', 'body' => '#26323c', 'dark' => '#151d24',
            'eye' => '#ffcf4d', 'aura' => '#3f7d8a', 'features' => ['wings', 'beak', 'feathers'],
        ],
        'larva' => [
            'bg1' => '#131f19', 'bg2' => '#1d3226', 'body' => '#6d8f4a', 'dark' => '#435c2c',
            'eye' => '#f4f08a', 'aura' => '#8fbf5e', 'features' => ['wisp', 'teeth'],
        ],
        'peste' => [
            'bg1' => '#151d1d', 'bg2' => '#22302d', 'body' => '#2f4741', 'dark' => '#1d2d29',
            'eye' => '#e8e2cf', 'aura' => '#6f9c8c', 'features' => ['hood', 'mask', 'wisp'],
        ],
        'herejia' => [
            'bg1' => '#1e1016', 'bg2' => '#331722', 'body' => '#5f2233', 'dark' => '#3a1420',
            'eye' => '#ff6b6b', 'aura' => '#a8324a', 'features' => ['hood', 'horns', 'wisp'],
        ],
        'idolo' => [
            'bg1' => '#211a10', 'bg2' => '#372a16', 'body' => '#c9a13b', 'dark' => '#8a6b21',
            'eye' => '#ffe9a8', 'aura' => '#f2c94c', 'features' => ['crown', 'statue'],
        ],
        'sequia' => [
            'bg1' => '#241a12', 'bg2' => '#3a2a1b', 'body' => '#a8814f', 'dark' => '#6e5130',
            'eye' => '#ffd08a', 'aura' => '#c98f45', 'features' => ['skull', 'wisp'],
        ],
        'tentacion' => [
            'bg1' => '#101f17', 'bg2' => '#1c3324', 'body' => '#4f8a3d', 'dark' => '#2f5a24',
            'eye' => '#ffe066', 'aura' => '#7fc25a', 'features' => ['snake', 'teeth'],
        ],
        'tormenta' => [
            'bg1' => '#0f1726', 'bg2' => '#1b2840', 'body' => '#3d5a86', 'dark' => '#243a5c',
            'eye' => '#a9d6ff', 'aura' => '#5b8fd1', 'features' => ['hood', 'wisp', 'horns'],
        ],
        'dragon' => [
            'bg1' => '#230f12', 'bg2' => '#3b171c', 'body' => '#a3352f', 'dark' => '#6b1e1e',
            'eye' => '#ffd54d', 'aura' => '#e05a3d', 'features' => ['horns', 'wings', 'tail', 'teeth', 'dragon'],
        ],
        'abismo' => [
            'bg1' => '#0d0b18', 'bg2' => '#191428', 'body' => '#2a2340', 'dark' => '#171226',
            'eye' => '#b68cff', 'aura' => '#6b4ec4', 'features' => ['horns', 'wisp', 'eyes3', 'aura'],
        ],
        'leviatan' => [
            'bg1' => '#0b1c22', 'bg2' => '#123039', 'body' => '#2f7d8a', 'dark' => '#1c5360',
            'eye' => '#a8f0ff', 'aura' => '#49b8c9', 'features' => ['horns', 'tail', 'teeth', 'crown', 'snake'],
        ],
        'soberbia' => [
            'bg1' => '#1b1428', 'bg2' => '#2b1f3d', 'body' => '#d4af37', 'dark' => '#8a6b21',
            'eye' => '#fff0b0', 'aura' => '#f2c94c', 'features' => ['crown', 'statue', 'aura'],
        ],
        'avaricia' => [
            'bg1' => '#131f14', 'bg2' => '#1f331d', 'body' => '#7a9a3d', 'dark' => '#4a5f24',
            'eye' => '#ffe066', 'aura' => '#b8c94c', 'features' => ['crown', 'teeth', 'wisp'],
        ],
        'lujuria' => [
            'bg1' => '#23101c', 'bg2' => '#3a1a2d', 'body' => '#b4497a', 'dark' => '#7a2b52',
            'eye' => '#ff9ad5', 'aura' => '#e05a9a', 'features' => ['snake', 'horns'],
        ],
        'ira' => [
            'bg1' => '#230d0d', 'bg2' => '#3b1414', 'body' => '#c0392b', 'dark' => '#7d1f16',
            'eye' => '#ffd54d', 'aura' => '#ff6b3d', 'features' => ['horns', 'wings', 'teeth'],
        ],
        'gula' => [
            'bg1' => '#211810', 'bg2' => '#372a18', 'body' => '#a8792f', 'dark' => '#6e4c1c',
            'eye' => '#ffcf4d', 'aura' => '#d19a3f', 'features' => ['teeth', 'wisp'],
        ],
        'invidia' => [
            'bg1' => '#0f2017', 'bg2' => '#1a3325', 'body' => '#3f8a4a', 'dark' => '#245c30',
            'eye' => '#c6ff8a', 'aura' => '#6fbf5e', 'features' => ['eyes3', 'snake'],
        ],
        'acidia' => [
            'bg1' => '#14161f', 'bg2' => '#222533', 'body' => '#4a5568', 'dark' => '#2b3140',
            'eye' => '#9fb3c8', 'aura' => '#5c7a99', 'features' => ['hood', 'wisp'],
        ],
        'bael' => [
            'bg1' => '#1b1210', 'bg2' => '#2f2018', 'body' => '#8a5a2b', 'dark' => '#543116',
            'eye' => '#ffb347', 'aura' => '#c98f45', 'features' => ['crown', 'horns', 'eyes3', 'tail'],
        ],
        'agares' => [
            'bg1' => '#121a20', 'bg2' => '#1e2c35', 'body' => '#4a6a7a', 'dark' => '#2a404c',
            'eye' => '#a9d6ff', 'aura' => '#5b8fd1', 'features' => ['beak', 'wings', 'feathers'],
        ],
        'vassago' => [
            'bg1' => '#141024', 'bg2' => '#231a40', 'body' => '#6b5aa8', 'dark' => '#40306e',
            'eye' => '#d4bfff', 'aura' => '#8c6bd9', 'features' => ['mask', 'hood', 'aura'],
        ],
        'samigina' => [
            'bg1' => '#171a17', 'bg2' => '#252b24', 'body' => '#7d8a6a', 'dark' => '#4a5440',
            'eye' => '#e8e2cf', 'aura' => '#9aa88a', 'features' => ['skull', 'wisp'],
        ],
        'marbas' => [
            'bg1' => '#200f0c', 'bg2' => '#381912', 'body' => '#a33a2f', 'dark' => '#6b2118',
            'eye' => '#ffcf4d', 'aura' => '#e05a3d', 'features' => ['horns', 'teeth', 'dragon', 'tail'],
        ],
        'valefor' => [
            'bg1' => '#1e1a10', 'bg2' => '#332c18', 'body' => '#b08a3a', 'dark' => '#6e5520',
            'eye' => '#ffe9a8', 'aura' => '#d1a94c', 'features' => ['wings', 'beak', 'feathers'],
        ],
        'amon' => [
            'bg1' => '#170d18', 'bg2' => '#2b1428', 'body' => '#6a2a4a', 'dark' => '#40172c',
            'eye' => '#ff6b8a', 'aura' => '#a83a5c', 'features' => ['horns', 'snake', 'tail', 'eyes3'],
        ],
        'barbatos' => [
            'bg1' => '#101b17', 'bg2' => '#1c2e25', 'body' => '#3f6a4a', 'dark' => '#24402c',
            'eye' => '#9df3c8', 'aura' => '#5cc48a', 'features' => ['hood', 'horns', 'wisp'],
        ],
        'paimon' => [
            'bg1' => '#1a1024', 'bg2' => '#2f1a40', 'body' => '#7a4aa8', 'dark' => '#4a2a6e',
            'eye' => '#ffd54d', 'aura' => '#c94cd1', 'features' => ['crown', 'eyes3', 'aura', 'tail'],
        ],
    ];

    public static function saint(array $art, int $rarity = 3, string $class = '', string $label = ''): string
    {
        $a = self::saintDefaults($art);
        $g = self::blank($a['bg1']);

        self::background($g, $a);
        self::halo($g, $a, $rarity);
        self::body($g, $a);
        self::head($g, $a);
        self::hair($g, $a);
        self::beard($g, $a);
        self::headgear($g, $a);
        self::accessory($g, $a);
        self::sparkles($g, $a, $rarity);

        return self::render($g, $class, $label);
    }

    public static function hasFoe(string $key): bool
    {
        return array_key_exists($key, self::FOES);
    }

    public static function foe(string $key, string $class = '', string $label = ''): string
    {
        $f = self::FOES[$key] ?? self::FOES['susurro'];
        $g = self::blank($f['bg1']);

        self::foeBackground($g, $f);
        self::foeAura($g, $f);

        $features = $f['features'];

        if (in_array('wings', $features, true)) {
            self::foeWings($g, $f);
        }
        if (in_array('tail', $features, true)) {
            self::foeTail($g, $f);
        }

        self::foeBody($g, $f, $features);
        self::foeHead($g, $f, $features);

        if (in_array('aura', $features, true)) {
            self::sparkle($g, 3, 8, $f['aura']);
            self::sparkle($g, 20, 16, $f['aura']);
            self::sparkle($g, 2, 20, $f['aura']);
        }

        return self::render($g, $class, $label);
    }

    /**
     * Retrato velado para los santos que aún no se han invocado.
     */
    public static function mystery(string $class = '', string $label = 'Santo velado'): string
    {
        $bg1 = '#0d0a18';
        $bg2 = '#141026';
        $g = self::blank($bg1);

        for ($y = 0; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                if ((($x * 3) + ($y * 5)) % 9 === 0) {
                    $g[$y][$x] = $bg2;
                }
            }
        }

        // Ventanal apenas insinuado
        for ($y = 2; $y < self::H; $y++) {
            $half = $y < 8 ? 2.0 + ($y - 2) * 1.15 : 7.4;
            $from = (int) ceil(11.5 - $half);
            $to = (int) floor(11.5 + $half);
            for ($x = $from; $x <= $to; $x++) {
                if ($x >= 0 && $x < self::W) {
                    $g[$y][$x] = '#181231';
                }
            }
        }

        foreach ([8, 15] as $lead) {
            for ($y = 9; $y < self::H; $y++) {
                if (($g[$y][$lead] ?? null) !== null) {
                    $g[$y][$lead] = '#120e24';
                }
            }
        }
        for ($x = 9; $x <= 14; $x++) {
            $g[13][$x] = '#120e24';
        }

        // Suelo
        for ($y = 26; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                $g[$y][$x] = '#0a0714';
            }
        }

        // Silueta encapuchada
        self::ellipse($g, 12, 25, 10.0, 6.0, '#171128');
        self::ellipse($g, 12, 25, 8.8, 5.2, '#241d3d');
        self::ellipse($g, 12, 11.5, 7.4, 8.4, '#171128');
        self::ellipse($g, 12, 11.5, 6.4, 7.4, '#2b2349');
        self::ellipse($g, 12, 12.5, 4.1, 5.0, '#090614');

        // Borde de la capucha
        self::set($g, 12, 3, '#3b3164');
        self::set($g, 9, 4, '#3b3164');
        self::set($g, 15, 4, '#3b3164');
        self::set($g, 7, 7, '#3b3164');
        self::set($g, 17, 7, '#3b3164');
        self::set($g, 6, 11, '#332a58');
        self::set($g, 18, 11, '#332a58');

        // Interrogación grabada en el pecho
        $sigil = '#6d5fa0';
        self::rect($g, 11, 17, 13, 17, $sigil);
        self::set($g, 13, 18, $sigil);
        self::set($g, 12, 19, $sigil);
        self::set($g, 12, 20, $sigil);
        self::set($g, 12, 22, $sigil);
        self::set($g, 11, 22, '#4b3f75');
        self::set($g, 13, 22, '#4b3f75');

        // Tenuísimo resplandor inferior
        self::set($g, 4, 24, '#2a2148');
        self::set($g, 19, 24, '#2a2148');
        self::set($g, 2, 21, '#221a3a');
        self::set($g, 21, 21, '#221a3a');

        return self::render($g, $class, $label);
    }

    private static function saintDefaults(array $art): array
    {
        $a = array_merge(self::SAINT_DEFAULTS, array_filter($art, fn ($v) => $v !== null && $v !== ''));
        foreach ($a as $key => $value) {
            if (is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                $a[$key] = $value;
            }
        }
        $a['bg2'] = $a['bg2'] ?? self::shade($a['bg1'], 0.10);
        $a['blush'] = (bool) ($a['blush'] ?? false);

        return $a;
    }

    private static function background(array &$g, array $a): void
    {
        $bg1 = $a['bg1'];
        $bg2 = $a['bg2'];
        $bg3 = self::shade($bg1, -0.14);

        for ($y = 0; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                $g[$y][$x] = $bg1;
                if ((($x * 3) + ($y * 5)) % 9 === 0) {
                    $g[$y][$x] = $bg2;
                }
            }
        }

        // Ventanal gótico detrás del santo
        for ($y = 2; $y < self::H; $y++) {
            $half = $y < 8 ? 2.0 + ($y - 2) * 1.15 : 7.4;
            $from = (int) ceil(11.5 - $half);
            $to = (int) floor(11.5 + $half);
            for ($x = $from; $x <= $to; $x++) {
                if ($x < 0 || $x >= self::W) {
                    continue;
                }
                $g[$y][$x] = $bg2;
                if (($x + $y) % 4 === 0) {
                    $g[$y][$x] = self::shade($bg2, 0.08);
                }
            }
        }

        // Junquillos del ventanal
        foreach ([8, 15] as $lead) {
            for ($y = 9; $y < self::H; $y++) {
                if (($g[$y][$lead] ?? null) !== null) {
                    $g[$y][$lead] = $bg3;
                }
            }
        }
        for ($x = 9; $x <= 14; $x++) {
            $g[13][$x] = $bg3;
        }

        // Suelo
        for ($y = 26; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                if ($g[$y][$x] !== $bg1) {
                    $g[$y][$x] = self::shade($bg1, -0.18);
                }
            }
        }
    }

    private static function halo(array &$g, array $a, int $rarity): void
    {
        $halo = $a['halo'];
        $dark = self::shade($halo, -0.32);

        for ($y = 0; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                $dx = ($x + 0.5 - 11.5) / 7.4;
                $dy = ($y + 0.5 - 9.0) / 6.9;
                $d = sqrt($dx * $dx + $dy * $dy);

                if ($d >= 0.55 && $d <= 0.9) {
                    $g[$y][$x] = ($y + 0.5 > 10.5) ? $dark : $halo;
                } elseif ($d > 0.9 && $d <= 0.98) {
                    $g[$y][$x] = $dark;
                }
            }
        }

        if ($rarity >= 4) {
            foreach ([[1, 3], [22, 4], [2, 17], [21, 18]] as [$x, $y]) {
                self::sparkle($g, $x, $y, $halo);
            }
        }
    }

    private static function body(array &$g, array $a): void
    {
        $robe = $a['robe'];
        $robeDark = self::shade($robe, -0.22);
        $robeLight = self::shade($robe, 0.14);
        $mantle = $a['mantle'];
        $mantleDark = self::shade($mantle, -0.25);
        $trim = $a['trim'];

        $shapes = [
            16 => 2,
            17 => 3,
            18 => 4,
            19 => 5,
            20 => 6,
            21 => 7,
        ];

        for ($y = 16; $y < self::H; $y++) {
            $hw = $shapes[$y] ?? 7;
            $from = 11 - $hw;
            $to = 12 + $hw;

            for ($x = $from; $x <= $to; $x++) {
                $g[$y][$x] = $robe;
                $edge = ($x - $from) <= 0 || ($to - $x) <= 0;
                $g[$y][$x] = $edge ? $robeDark : $robe;

                if ($y >= 18) {
                    $frame = 3;
                    if (($x - $from) < $frame || ($to - $x) < $frame) {
                        $g[$y][$x] = ($x === $from || $x === $to) ? $mantleDark : $mantle;
                    }
                    if (($x - $from) === $frame || ($to - $x) === $frame) {
                        $g[$y][$x] = self::shade($mantle, 0.18);
                    }
                }
            }
        }

        // Cuello y alzacuello
        self::rect($g, 10, 15, 13, 17, $robeDark);
        self::rect($g, 10, 16, 13, 16, $trim);

        // Estola
        for ($y = 18; $y <= 26; $y++) {
            $g[$y][9] = $trim;
            $g[$y][14] = $trim;
        }

        // Manos orantes
        self::rect($g, 10, 21, 12, 23, $a['skin']);
        self::set($g, 13, 22, self::shade($a['skin'], -0.12));
        self::set($g, 9, 22, $robeDark);
        self::set($g, 14, 23, $robeDark);

        // Detalles del hábito
        for ($y = 19; $y <= 26; $y += 3) {
            self::set($g, 11, $y, $robeLight);
            self::set($g, 12, $y + 1, $robeLight);
        }
        self::rect($g, 5, 26, 18, 27, $robeDark);
    }

    private static function head(array &$g, array $a): void
    {
        $skin = $a['skin'];
        $shade = self::shade($skin, -0.14);

        // Forma de la cara
        foreach ([
            7 => [9, 14],
            8 => [8, 15],
            9 => [8, 15],
            10 => [8, 15],
            11 => [8, 15],
            12 => [8, 15],
            13 => [9, 14],
            14 => [9, 14],
            15 => [10, 13],
        ] as $y => [$from, $to]) {
            self::rect($g, $from, $y, $to, $y, $skin);
        }

        // Orejas
        self::rect($g, 7, 10, 7, 11, $shade);
        self::rect($g, 16, 10, 16, 11, $shade);

        // Sombra lateral y mentón
        self::rect($g, 15, 9, 15, 13, $shade);
        self::set($g, 11, 14, $shade);
        self::set($g, 12, 14, $shade);

        // Cejas, ojos, nariz y boca
        $eye = $a['eye'];
        self::set($g, 10, 9, $a['hair']);
        self::set($g, 13, 9, $a['hair']);
        self::set($g, 10, 10, $eye);
        self::set($g, 13, 10, $eye);
        self::set($g, 10, 11, self::shade($skin, 0.10));
        self::set($g, 13, 11, self::shade($skin, 0.10));
        self::set($g, 12, 11, $shade);
        self::set($g, 11, 13, self::shade($skin, -0.34));
        self::set($g, 12, 13, self::shade($skin, -0.34));

        if ($a['blush']) {
            self::set($g, 9, 12, '#e08a86');
            self::set($g, 14, 12, '#e08a86');
        }
    }

    private static function hair(array &$g, array $a): void
    {
        $hair = $a['hair'];
        $dark = self::shade($hair, -0.22);
        $light = self::shade($hair, 0.16);

        switch ($a['hairStyle']) {
            case 'long':
                self::rect($g, 8, 5, 15, 6, $hair);
                self::rect($g, 9, 4, 14, 4, $hair);
                self::rect($g, 7, 7, 7, 18, $dark);
                self::rect($g, 16, 7, 16, 18, $dark);
                self::rect($g, 8, 7, 9, 8, $hair);
                self::rect($g, 14, 7, 15, 8, $hair);
                self::set($g, 9, 7, $light);
                self::set($g, 15, 8, $light);
                break;

            case 'waves':
                self::rect($g, 9, 4, 14, 4, $hair);
                self::rect($g, 8, 5, 15, 5, $hair);
                self::rect($g, 7, 6, 16, 6, $hair);
                self::rect($g, 8, 7, 9, 7, $hair);
                self::rect($g, 14, 7, 15, 7, $hair);
                self::set($g, 10, 4, $light);
                self::set($g, 13, 5, $light);
                self::rect($g, 7, 8, 7, 11, $dark);
                self::rect($g, 16, 8, 16, 11, $dark);
                break;

            case 'curly':
                foreach ([[8, 5], [10, 4], [12, 4], [14, 5], [16, 6], [7, 7], [16, 7]] as [$x, $y]) {
                    self::ellipse($g, $x + 0.5, $y + 0.5, 1.2, 1.2, $hair);
                }
                self::rect($g, 9, 6, 14, 6, $hair);
                self::rect($g, 8, 7, 8, 8, $dark);
                self::rect($g, 15, 7, 15, 8, $dark);
                break;

            case 'tonsure':
                self::rect($g, 7, 7, 8, 8, $hair);
                self::rect($g, 15, 7, 16, 8, $hair);
                self::rect($g, 8, 6, 15, 6, $hair);
                self::set($g, 9, 7, self::shade($hair, 0.2));
                break;

            case 'bald':
                self::set($g, 8, 8, $dark);
                self::set($g, 15, 8, $dark);
                self::set($g, 9, 7, self::shade($hair, 0.05));
                break;

            case 'veil':
                break;

            default: // short
                self::rect($g, 8, 5, 15, 6, $hair);
                self::rect($g, 9, 4, 14, 4, $hair);
                self::rect($g, 8, 7, 9, 8, $hair);
                self::rect($g, 14, 7, 15, 8, $hair);
                self::rect($g, 8, 9, 8, 10, $dark);
                self::rect($g, 15, 9, 15, 10, $dark);
                self::set($g, 10, 5, $light);
                break;
        }
    }

    private static function beard(array &$g, array $a): void
    {
        if ($a['beard'] === 'none') {
            return;
        }

        $hair = $a['hair'];
        $dark = self::shade($hair, -0.2);

        switch ($a['beard']) {
            case 'short':
                self::rect($g, 10, 12, 13, 12, $hair);
                self::rect($g, 9, 13, 14, 14, $hair);
                self::rect($g, 10, 15, 13, 15, $hair);
                self::set($g, 10, 14, $dark);
                break;

            case 'goatee':
                self::rect($g, 10, 12, 13, 12, $hair);
                self::rect($g, 11, 13, 12, 17, $hair);
                self::set($g, 11, 16, $dark);
                break;

            case 'full':
                self::rect($g, 10, 12, 13, 12, $hair);
                self::rect($g, 8, 13, 15, 14, $hair);
                self::rect($g, 9, 15, 14, 16, $hair);
                self::rect($g, 10, 17, 13, 17, $dark);
                break;

            case 'long':
                self::rect($g, 10, 12, 13, 12, $hair);
                self::rect($g, 9, 13, 14, 14, $hair);
                self::rect($g, 9, 15, 14, 16, $hair);
                self::rect($g, 10, 17, 13, 18, $hair);
                self::rect($g, 11, 19, 12, 20, $dark);
                self::set($g, 10, 16, $dark);
                break;
        }
    }

    private static function headgear(array &$g, array $a): void
    {
        $trim = $a['trim'];

        switch ($a['headgear']) {
            case 'mitre':
                $cream = '#f2ead4';
                self::rect($g, 7, 7, 16, 7, $cream);
                self::rect($g, 8, 6, 15, 6, $cream);
                self::rect($g, 9, 5, 14, 5, $cream);
                self::rect($g, 10, 4, 13, 4, $cream);
                self::rect($g, 11, 3, 12, 3, $cream);
                self::rect($g, 11, 3, 12, 7, $trim);
                self::rect($g, 7, 7, 16, 7, self::shade($trim, -0.2));
                self::set($g, 11, 2, $trim);
                self::set($g, 12, 2, $trim);
                break;

            case 'veil':
                $veil = $a['veil'];
                $veilDark = self::shade($veil, -0.18);
                $veilLight = self::shade($veil, 0.16);
                self::rect($g, 9, 3, 14, 3, $veil);
                self::rect($g, 7, 4, 16, 4, $veil);
                self::rect($g, 6, 5, 17, 6, $veil);
                self::rect($g, 6, 7, 7, 20, $veil);
                self::rect($g, 16, 7, 17, 20, $veil);
                self::rect($g, 5, 20, 7, 23, $veilDark);
                self::rect($g, 16, 20, 18, 23, $veilDark);
                self::rect($g, 8, 7, 8, 16, $veilLight);
                self::rect($g, 15, 7, 15, 16, $veilLight);
                self::rect($g, 9, 3, 14, 3, $veilLight);
                break;

            case 'hood':
                $hood = $a['mantle'];
                $hoodDark = self::shade($hood, -0.24);
                self::rect($g, 8, 4, 15, 4, $hood);
                self::rect($g, 7, 5, 16, 5, $hood);
                self::rect($g, 6, 6, 17, 6, $hood);
                self::rect($g, 6, 7, 7, 13, $hood);
                self::rect($g, 16, 7, 17, 13, $hood);
                self::rect($g, 7, 14, 16, 14, $hood);
                self::rect($g, 8, 8, 8, 13, $hoodDark);
                self::rect($g, 15, 8, 15, 13, $hoodDark);
                break;

            case 'crown':
                self::rect($g, 7, 6, 16, 6, $trim);
                foreach ([7, 9, 12, 15, 16] as $x) {
                    self::set($g, $x, 5, $trim);
                }
                foreach ([7, 10, 13, 16] as $x) {
                    self::set($g, $x, 4, self::shade($trim, 0.2));
                }
                self::set($g, 11, 6, '#e05a5a');
                self::set($g, 12, 6, '#e05a5a');
                break;

            case 'tiara':
                self::rect($g, 8, 6, 15, 6, $trim);
                self::rect($g, 8, 4, 15, 4, $trim);
                self::rect($g, 9, 5, 14, 5, self::shade($trim, -0.15));
                foreach ([8, 11, 14, 15] as $x) {
                    self::set($g, $x, 3, $trim);
                }
                self::set($g, 11, 2, $trim);
                self::set($g, 12, 2, $trim);
                self::set($g, 11, 1, $trim);
                self::set($g, 10, 2, $trim);
                self::set($g, 13, 2, $trim);
                break;

            case 'laurel':
                $leaf = '#6fae5c';
                foreach ([[7, 6], [8, 5], [10, 4], [13, 4], [15, 5], [16, 6], [17, 8], [17, 10]] as [$x, $y]) {
                    self::set($g, $x, $y, $leaf);
                    self::set($g, $x, $y + 1, self::shade($leaf, -0.2));
                }
                break;
        }
    }

    private static function accessory(array &$g, array $a): void
    {
        if ($a['accessory'] === 'none') {
            return;
        }

        $tmp = self::blank(null);
        $trim = $a['trim'];
        $wood = '#6b4a2f';
        $woodLight = '#8a6440';
        $steel = '#cfd8e3';
        $steelDark = '#8c9aab';

        switch ($a['accessory']) {
            case 'staff':
                self::rect($tmp, 4, 6, 4, 27, $wood);
                self::rect($tmp, 5, 8, 5, 27, $woodLight);
                self::set($tmp, 4, 5, $trim);
                self::set($tmp, 5, 4, $trim);
                self::set($tmp, 6, 5, $trim);
                self::set($tmp, 6, 6, $trim);
                break;

            case 'cross':
                self::rect($tmp, 4, 8, 4, 24, $trim);
                self::rect($tmp, 2, 13, 6, 13, $trim);
                self::set($tmp, 5, 9, self::shade($trim, 0.2));
                self::set($tmp, 3, 14, self::shade($trim, -0.25));
                break;

            case 'book':
                $cover = '#8c2f39';
                self::rect($tmp, 2, 17, 6, 23, $cover);
                self::rect($tmp, 2, 17, 2, 23, self::shade($cover, -0.25));
                self::rect($tmp, 6, 18, 6, 22, '#f2ead4');
                self::set($tmp, 4, 20, $trim);
                self::set($tmp, 4, 19, $trim);
                break;

            case 'sword':
                self::rect($tmp, 4, 7, 4, 21, $steel);
                self::rect($tmp, 5, 8, 5, 20, $steelDark);
                self::set($tmp, 4, 6, self::shade($steel, 0.3));
                self::rect($tmp, 2, 21, 6, 21, $trim);
                self::rect($tmp, 4, 22, 4, 24, $wood);
                self::set($tmp, 4, 25, $trim);
                break;

            case 'lily':
                self::rect($tmp, 3, 12, 3, 26, '#4f8a3d');
                self::set($tmp, 4, 18, '#6fae5c');
                self::set($tmp, 2, 20, '#6fae5c');
                self::rect($tmp, 2, 9, 4, 10, '#f7f3e6');
                self::set($tmp, 3, 8, '#ffffff');
                self::set($tmp, 3, 11, $trim);
                break;

            case 'keys':
                foreach ([3, 6] as $i => $x) {
                    self::rect($tmp, $x, 12 + $i, $x, 23, $trim);
                    self::set($tmp, $x - 1, 11 + $i, $trim);
                    self::set($tmp, $x + 1, 11 + $i, $trim);
                    self::set($tmp, $x - 1, 12 + $i, $trim);
                    self::set($tmp, $x + 1, 12 + $i, $trim);
                    self::set($tmp, $x + 1, 21 + $i, $trim);
                    self::set($tmp, $x + 2, 21 + $i, $trim);
                }
                break;

            case 'dove':
                self::rect($tmp, 3, 10, 6, 12, '#f7f3e6');
                self::set($tmp, 5, 9, '#f7f3e6');
                self::set($tmp, 6, 9, $trim);
                self::set($tmp, 3, 9, '#e6e1d4');
                self::set($tmp, 7, 10, '#e6e1d4');
                self::set($tmp, 3, 15, '#6fae5c');
                self::set($tmp, 2, 16, '#6fae5c');
                break;

            case 'chalice':
                self::rect($tmp, 2, 17, 6, 18, $trim);
                self::rect($tmp, 3, 19, 5, 20, self::shade($trim, -0.15));
                self::rect($tmp, 4, 21, 4, 23, $trim);
                self::rect($tmp, 2, 24, 6, 24, $trim);
                self::set($tmp, 3, 16, '#f2ead4');
                self::set($tmp, 4, 16, '#f2ead4');
                self::set($tmp, 5, 16, '#f2ead4');
                break;

            case 'rosary':
                foreach ([[3, 15], [2, 16], [2, 17], [2, 18], [2, 19], [3, 20], [3, 21]] as [$x, $y]) {
                    self::set($tmp, $x, $y, $trim);
                }
                self::rect($tmp, 3, 22, 3, 24, $wood);
                self::rect($tmp, 2, 23, 4, 23, $wood);
                break;

            case 'arrows':
                foreach ([[0, 0], [1, 2], [2, 4]] as [$dx, $dy]) {
                    for ($i = 0; $i < 5; $i++) {
                        self::set($tmp, 2 + $i + $dx, 20 - $i + $dy, $wood);
                    }
                    self::set($tmp, 6 + $dx, 15 + $dy, $steel);
                    self::set($tmp, 2 + $dx, 21 + $dy, '#f7f3e6');
                    self::set($tmp, 3 + $dx, 21 + $dy, '#f7f3e6');
                }
                break;

            case 'lamp':
                self::rect($tmp, 2, 18, 6, 20, '#b98a3c');
                self::rect($tmp, 2, 20, 6, 20, '#8a6420');
                self::rect($tmp, 3, 15, 5, 17, '#e8c96a');
                self::set($tmp, 4, 14, '#fff0b0');
                self::set($tmp, 4, 13, '#fff7cc');
                self::set($tmp, 4, 21, '#6b4a2f');
                self::set($tmp, 2, 22, '#6b4a2f');
                self::set($tmp, 3, 22, '#6b4a2f');
                self::set($tmp, 5, 22, '#6b4a2f');
                self::set($tmp, 6, 22, '#6b4a2f');
                break;

            case 'scroll':
                self::rect($tmp, 2, 16, 6, 20, '#f2ead4');
                self::rect($tmp, 2, 15, 2, 21, $woodLight);
                self::rect($tmp, 6, 15, 6, 21, $woodLight);
                self::set($tmp, 4, 17, '#8a6440');
                self::set($tmp, 4, 19, '#8a6440');
                self::set($tmp, 4, 21, '#8c2f39');
                break;
        }

        self::merge($g, $tmp, $a['side'] === 'right');
    }

    private static function sparkles(array &$g, array $a, int $rarity): void
    {
        $points = [[2, 3], [21, 5], [1, 15], [22, 15], [3, 24], [20, 24]];

        if ($rarity <= 3) {
            return;
        }

        $count = $rarity === 4 ? 3 : 6;
        foreach (array_slice($points, 0, $count) as [$x, $y]) {
            self::sparkle($g, $x, $y, self::shade($a['halo'], 0.25));
        }
    }

    /* ---------------------------------------------------------------------
     | Enemigos
     * ------------------------------------------------------------------- */

    private static function foeBackground(array &$g, array $f): void
    {
        for ($y = 0; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                $g[$y][$x] = $f['bg1'];
                if ((($x * 5) + ($y * 3)) % 11 === 0) {
                    $g[$y][$x] = $f['bg2'];
                }
            }
        }

        for ($x = 0; $x < self::W; $x++) {
            $g[27][$x] = self::shade($f['bg1'], -0.2);
            $g[26][$x] = ((int) ($x / 2) % 2 === 0) ? self::shade($f['bg1'], -0.1) : $f['bg1'];
        }
    }

    private static function foeAura(array &$g, array $f): void
    {
        $aura = $f['aura'];
        for ($y = 0; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                $dx = ($x + 0.5 - 11.5) / 9.4;
                $dy = ($y + 0.5 - 15.0) / 11.0;
                $d = sqrt($dx * $dx + $dy * $dy);
                if ($d > 0.86 && $d < 0.94 && ((($x + $y) % 3) === 0)) {
                    $g[$y][$x] = self::shade($aura, -0.25);
                }
            }
        }
    }

    private static function foeBody(array &$g, array $f, array $features): void
    {
        $body = $f['body'];
        $dark = $f['dark'];
        $light = self::shade($body, 0.16);

        if (in_array('snake', $features, true)) {
            // Cuerpo serpentino enroscado
            self::ellipse($g, 12, 24, 9.5, 3.4, $dark);
            self::ellipse($g, 12, 23.5, 8.6, 2.7, $body);
            self::ellipse($g, 8, 20, 4.4, 3.0, $body);
            self::ellipse($g, 16, 20, 4.4, 3.0, $body);
            self::set($g, 8, 19, $light);
            self::set($g, 16, 19, $light);
            self::rect($g, 11, 14, 13, 22, $dark);
            self::rect($g, 11, 15, 12, 21, $body);
        } elseif (in_array('wisp', $features, true)) {
            self::ellipse($g, 12, 20, 8.4, 8.0, $dark);
            self::ellipse($g, 12, 19.6, 7.6, 7.2, $body);
            self::ellipse($g, 12, 20.5, 4.6, 4.4, self::shade($body, 0.08));
            // Jirones inferiores
            for ($x = 5; $x < 20; $x += 2) {
                $g[26][$x] = $dark;
                $g[27][$x] = null;
            }
        } else {
            self::ellipse($g, 12, 19, 8.6, 8.4, $dark);
            self::ellipse($g, 12, 18.6, 7.8, 7.6, $body);
            self::ellipse($g, 12, 19.5, 4.8, 4.6, self::shade($body, 0.08));
            // Brazos
            self::ellipse($g, 4, 18, 2.4, 3.4, $dark);
            self::ellipse($g, 20, 18, 2.4, 3.4, $dark);
            self::ellipse($g, 4, 17.6, 1.8, 2.8, $body);
            self::ellipse($g, 20, 17.6, 1.8, 2.8, $body);
        }

        if (in_array('statue', $features, true)) {
            for ($y = 20; $y <= 24; $y += 2) {
                self::rect($g, 6, $y, 18, $y, self::shade($body, -0.18));
            }
            self::rect($g, 8, 24, 16, 25, $dark);
        }

        self::set($g, 10, 17, $light);
        self::set($g, 11, 16, $light);
    }

    private static function foeHead(array &$g, array $f, array $features): void
    {
        $body = $f['body'];
        $dark = $f['dark'];
        $eye = $f['eye'];
        $headY = in_array('snake', $features, true) ? 9.5 : 9.0;

        if (in_array('hood', $features, true)) {
            self::ellipse($g, 12, $headY + 0.5, 7.6, 7.0, $dark);
            self::ellipse($g, 12, $headY + 1.0, 6.2, 5.6, self::shade($dark, 0.12));
            self::ellipse($g, 12, $headY + 1.5, 4.6, 4.4, '#0d0a14');
        } else {
            self::ellipse($g, 12, $headY, 6.4, 5.6, $dark);
            self::ellipse($g, 12, $headY, 5.6, 4.9, $body);
        }

        if (in_array('horns', $features, true)) {
            $horn = self::shade($dark, 0.22);
            // Cuerno izquierdo
            self::set($g, 7, 6, $horn);
            self::set($g, 6, 5, $horn);
            self::set($g, 6, 4, $horn);
            self::set($g, 5, 3, $horn);
            self::set($g, 6, 3, $horn);
            self::set($g, 7, 5, $horn);
            // Cuerno derecho
            self::set($g, 16, 6, $horn);
            self::set($g, 17, 5, $horn);
            self::set($g, 17, 4, $horn);
            self::set($g, 18, 3, $horn);
            self::set($g, 17, 3, $horn);
            self::set($g, 16, 5, $horn);
        }

        if (in_array('crown', $features, true)) {
            self::rect($g, 7, 5, 16, 5, '#f2c94c');
            foreach ([7, 10, 13, 16] as $x) {
                self::set($g, $x, 4, '#f2c94c');
            }
        }

        if (in_array('mask', $features, true)) {
            self::ellipse($g, 12, 10, 5.2, 4.6, '#e8e2cf');
            self::set($g, 10, 9, '#20262a');
            self::set($g, 14, 9, '#20262a');
            self::set($g, 11, 9, '#20262a');
            self::set($g, 13, 9, '#20262a');
            for ($i = 0; $i < 4; $i++) {
                self::rect($g, 10 - $i, 12 + $i, 14 + $i, 12 + $i, '#d6cfb8');
            }
        } else {
            // Ojos encendidos
            self::rect($g, 9, 9, 10, 10, $eye);
            self::rect($g, 14, 9, 15, 10, $eye);
            self::set($g, 9, 9, '#ffffff');
            self::set($g, 14, 9, '#ffffff');
            self::set($g, 8, 9, self::shade($eye, -0.3));
            self::set($g, 16, 10, self::shade($eye, -0.3));

            if (in_array('eyes3', $features, true)) {
                self::set($g, 12, 7, $eye);
                self::set($g, 11, 7, self::shade($eye, -0.2));
                self::set($g, 13, 7, self::shade($eye, -0.2));
            }

            if (in_array('beak', $features, true)) {
                self::rect($g, 10, 11, 14, 12, '#c9a13b');
                self::set($g, 11, 12, '#8a6b21');
                self::set($g, 12, 12, '#8a6b21');
                self::set($g, 13, 12, '#8a6b21');
            } elseif (in_array('teeth', $features, true)) {
                self::rect($g, 10, 12, 14, 12, '#1a0f16');
                foreach ([10, 12, 14] as $x) {
                    self::set($g, $x, 12, '#f2ead4');
                }
            } else {
                self::rect($g, 10, 12, 13, 12, self::shade($body, -0.4));
            }
        }

        if (in_array('skull', $features, true)) {
            self::ellipse($g, 12, 9, 5.4, 5.0, '#e8e2cf');
            self::set($g, 10, 9, '#1a0f16');
            self::set($g, 13, 9, '#1a0f16');
            self::set($g, 11, 12, '#1a0f16');
            self::set($g, 12, 12, '#1a0f16');
            self::set($g, 13, 12, '#1a0f16');
        }
    }

    private static function foeWings(array &$g, array $f): void
    {
        $dark = self::shade($f['dark'], -0.1);
        $membrane = self::shade($f['body'], -0.25);

        foreach ([[1, 1], [-1, 1]] as [$dir, $_]) {
            $cx = $dir === 1 ? 5.0 : 19.0;
            self::ellipse($g, $cx, 12, 5.2, 6.4, $dark);
            self::ellipse($g, $cx + $dir * 0.6, 12.5, 3.8, 5.0, $membrane);
            // Bordes puntiagudos
            self::set($g, (int) ($cx - $dir * 5), 6, $dark);
            self::set($g, (int) ($cx - $dir * 4), 9, $dark);
            self::set($g, (int) ($cx - $dir * 4), 15, $dark);
        }
    }

    private static function foeTail(array &$g, array $f): void
    {
        $dark = $f['dark'];
        $body = $f['body'];

        $points = [[20, 22], [21, 22], [22, 21], [22, 20], [21, 19], [21, 18]];
        foreach ($points as $i => [$x, $y]) {
            self::set($g, $x, $y, $i % 2 === 0 ? $body : $dark);
            self::set($g, $x, $y + 1, $dark);
        }
        self::set($g, 20, 17, self::shade($body, 0.2));
    }

    /* ---------------------------------------------------------------------
     | Utilidades de rejilla
     * ------------------------------------------------------------------- */

    private static function blank(?string $color): array
    {
        $row = array_fill(0, self::W, $color);

        return array_fill(0, self::H, $row);
    }

    private static function set(array &$g, int $x, int $y, string $color): void
    {
        if ($x >= 0 && $x < self::W && $y >= 0 && $y < self::H) {
            $g[$y][$x] = $color;
        }
    }

    private static function rect(array &$g, int $x0, int $y0, int $x1, int $y1, string $color): void
    {
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                self::set($g, $x, $y, $color);
            }
        }
    }

    private static function ellipse(array &$g, float $cx, float $cy, float $rx, float $ry, string $color): void
    {
        for ($y = (int) floor($cy - $ry); $y <= (int) ceil($cy + $ry); $y++) {
            for ($x = (int) floor($cx - $rx); $x <= (int) ceil($cx + $rx); $x++) {
                $dx = ($x + 0.5 - $cx) / max($rx, 0.001);
                $dy = ($y + 0.5 - $cy) / max($ry, 0.001);
                if (($dx * $dx) + ($dy * $dy) <= 1.0) {
                    self::set($g, $x, $y, $color);
                }
            }
        }
    }

    private static function sparkle(array &$g, int $x, int $y, string $color): void
    {
        self::set($g, $x, $y, $color);
        self::set($g, $x - 1, $y, $color);
        self::set($g, $x + 1, $y, $color);
        self::set($g, $x, $y - 1, $color);
        self::set($g, $x, $y + 1, $color);
    }

    private static function merge(array &$g, array $tmp, bool $mirror): void
    {
        for ($y = 0; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                $color = $tmp[$y][$x];
                if ($color === null) {
                    continue;
                }
                $target = $mirror ? self::W - 1 - $x : $x;
                $g[$y][$target] = $color;
            }
        }
    }

    private static function shade(string $hex, float $amount): string
    {
        if (! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return $hex;
        }

        $target = $amount >= 0 ? 255 : 0;
        $factor = abs($amount);

        $r = (int) round(((int) hexdec($m[1])) * (1 - $factor) + $target * $factor);
        $g = (int) round(((int) hexdec($m[2])) * (1 - $factor) + $target * $factor);
        $b = (int) round(((int) hexdec($m[3])) * (1 - $factor) + $target * $factor);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function render(array $grid, string $class, string $label): string
    {
        $runs = [];

        for ($y = 0; $y < self::H; $y++) {
            $x = 0;
            while ($x < self::W) {
                $color = $grid[$y][$x];
                if ($color === null) {
                    $x++;

                    continue;
                }

                $start = $x;
                while ($x + 1 < self::W && $grid[$y][$x + 1] === $color) {
                    $x++;
                }

                $runs[$color][] = [$start, $y, $x - $start + 1];
                $x++;
            }
        }

        $paths = '';
        foreach ($runs as $color => $list) {
            $d = '';
            foreach ($list as [$x, $y, $width]) {
                $d .= 'M'.$x.' '.$y.'h'.$width.'v1h-'.$width.'z';
            }
            $paths .= '<path fill="'.$color.'" d="'.$d.'"/>';
        }

        $safeClass = htmlspecialchars($class, ENT_QUOTES);
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $classAttr = $safeClass !== '' ? ' class="pixel-art '.$safeClass.'"' : '';

        return '<svg'.$classAttr.' viewBox="0 0 '.self::W.' '.self::H.'" width="'.self::W.'" height="'.self::H.'"'
            .' preserveAspectRatio="xMidYMid meet"'
            .' xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges" role="img"'
            .($safeLabel !== '' ? ' aria-label="'.$safeLabel.'"' : ' aria-hidden="true"')
            .'>'.$paths.'</svg>';
    }
}
