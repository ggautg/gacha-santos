<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moneda y economía
    |--------------------------------------------------------------------------
    */
    'currency' => 'Gracia',
    'starting_grace' => 900,
    'pull_cost' => 60,
    'ten_pull_cost' => 540,
    'free_pull_hours' => 6,
    'max_level' => 20,
    'level_cost_base' => 45,

    /*
    |--------------------------------------------------------------------------
    | Probabilidades del gacha (por tirada)
    |--------------------------------------------------------------------------
    */
    'rates' => [
        1 => 0.400,
        2 => 0.300,
        3 => 0.200,
        4 => 0.085,
        5 => 0.015,
    ],

    // Tiradas sin 5★ antes del pity garantizado
    'pity' => 60,
    // Tiradas sin 4★ o superior antes del pity menor
    'ten_pity' => 10,

    // Gracia devuelta al obtener un santo repetido
    'duplicate_grace' => [
        1 => 10,
        2 => 20,
        3 => 40,
        4 => 90,
        5 => 240,
    ],

    'starter_slugs' => [
        'san-isidro-labrador',
        'santa-zita',
        'san-pancracio',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mazmorras procedurales
    |--------------------------------------------------------------------------
    |
    | Cada mazmorra es un descenso sin final. El jefe de cada piso se escoge al
    | azar entre su elenco usando la semilla del descenso, y sus estadísticas y
    | recompensas crecen con la profundidad. Cada mazmorra tiene su propia
    | paleta y tipografía, que se aplican a toda la web mientras se explora.
    |
    */
    'dungeons' => [
        'pecados' => [
            'name' => 'Los Siete Pecados',
            'subtitle' => 'Siete vicios que devoran el alma, prestos a repetirse sin fin',
            'tagline' => 'La soberbia abre la puerta; la pereza la cierra.',
            'theme' => 'pecados',
            'font_name' => 'Píxel carmesí',
            'accent' => '#d14a3f',
            'accent2' => '#f2c94c',
            'unlock' => ['type' => 'always'],
            'scaling' => [
                'hp' => 0.24, 'atk' => 0.13, 'def' => 0.10,
                'spd' => 0.02, 'grace' => 0.16, 'xp' => 0.16,
            ],
            'bosses' => [
                [
                    'name' => 'La Soberbia',
                    'subtitle' => 'El trono que se creyó más alto que el cielo',
                    'enemy' => 'soberbia',
                    'hp' => 820, 'atk' => 58, 'def' => 24, 'spd' => 20,
                    'skill' => 'buff', 'skill_name' => 'Corona de orgullo', 'skill_power' => 25,
                    'grace' => 220, 'xp' => 95,
                ],
                [
                    'name' => 'La Avaricia',
                    'subtitle' => 'Manos que guardan lo que nunca darán',
                    'enemy' => 'avaricia',
                    'hp' => 940, 'atk' => 64, 'def' => 28, 'spd' => 18,
                    'skill' => 'drain', 'skill_name' => 'Diezmo invertido', 'skill_power' => 150,
                    'grace' => 245, 'xp' => 110,
                ],
                [
                    'name' => 'La Lujuria',
                    'subtitle' => 'La serpiente que viste de terciopelo',
                    'enemy' => 'lujuria',
                    'hp' => 1040, 'atk' => 70, 'def' => 26, 'spd' => 27,
                    'skill' => 'stun', 'skill_name' => 'Beso de ceniza', 'skill_power' => 130,
                    'grace' => 270, 'xp' => 125,
                ],
                [
                    'name' => 'La Ira',
                    'subtitle' => 'El fuego que consume su propio templo',
                    'enemy' => 'ira',
                    'hp' => 1180, 'atk' => 82, 'def' => 30, 'spd' => 23,
                    'skill' => 'smite', 'skill_name' => 'Golpe de cólera', 'skill_power' => 200,
                    'grace' => 300, 'xp' => 145,
                ],
                [
                    'name' => 'La Gula',
                    'subtitle' => 'La mesa que nunca se sacia',
                    'enemy' => 'gula',
                    'hp' => 1340, 'atk' => 86, 'def' => 36, 'spd' => 16,
                    'skill' => 'heal', 'skill_name' => 'Banquete sin fondo', 'skill_power' => 22,
                    'grace' => 335, 'xp' => 165,
                ],
                [
                    'name' => 'La Envidia',
                    'subtitle' => 'El ojo que se alimenta de lo ajeno',
                    'enemy' => 'invidia',
                    'hp' => 1460, 'atk' => 94, 'def' => 34, 'spd' => 26,
                    'skill' => 'drain', 'skill_name' => 'Mirada verde', 'skill_power' => 165,
                    'grace' => 370, 'xp' => 185,
                ],
                [
                    'name' => 'La Pereza',
                    'subtitle' => 'El silencio que apaga las horas santas',
                    'enemy' => 'acidia',
                    'hp' => 1650, 'atk' => 104, 'def' => 42, 'spd' => 12,
                    'skill' => 'aoe', 'skill_name' => 'Letargo absoluto', 'skill_power' => 90,
                    'grace' => 450, 'xp' => 230,
                ],
            ],
        ],
        'goetia' => [
            'name' => 'Los Sellos de la Goetia',
            'subtitle' => 'Nueve nombres que no deberían pronunciarse',
            'tagline' => 'Quien lee el sello es leído por él.',
            'theme' => 'goetia',
            'font_name' => 'Rúbrica infernal',
            'accent' => '#8b6cf0',
            'accent2' => '#49b8c9',
            'unlock' => ['type' => 'dungeon', 'dungeon' => 'pecados', 'value' => 3],
            'scaling' => [
                'hp' => 0.26, 'atk' => 0.14, 'def' => 0.11,
                'spd' => 0.02, 'grace' => 0.17, 'xp' => 0.17,
            ],
            'bosses' => [
                [
                    'name' => 'Bael',
                    'subtitle' => 'El primer rey, de voz ronca y corona triple',
                    'enemy' => 'bael',
                    'hp' => 1500, 'atk' => 96, 'def' => 38, 'spd' => 24,
                    'skill' => 'stun', 'skill_name' => 'Sello de la garganta', 'skill_power' => 140,
                    'grace' => 400, 'xp' => 200,
                ],
                [
                    'name' => 'Agares',
                    'subtitle' => 'El duque que cabalga sobre un cocodrilo',
                    'enemy' => 'agares',
                    'hp' => 1620, 'atk' => 102, 'def' => 36, 'spd' => 30,
                    'skill' => 'aoe', 'skill_name' => 'Lengua rota', 'skill_power' => 90,
                    'grace' => 430, 'xp' => 215,
                ],
                [
                    'name' => 'Vassago',
                    'subtitle' => 'El rostro velado que encuentra lo perdido',
                    'enemy' => 'vassago',
                    'hp' => 1720, 'atk' => 108, 'def' => 40, 'spd' => 26,
                    'skill' => 'drain', 'skill_name' => 'Espejo ausente', 'skill_power' => 170,
                    'grace' => 460, 'xp' => 230,
                ],
                [
                    'name' => 'Samigina',
                    'subtitle' => 'El marqués de los huesos que responden',
                    'enemy' => 'samigina',
                    'hp' => 1840, 'atk' => 112, 'def' => 42, 'spd' => 22,
                    'skill' => 'heal', 'skill_name' => 'Coro de difuntos', 'skill_power' => 20,
                    'grace' => 490, 'xp' => 245,
                ],
                [
                    'name' => 'Marbas',
                    'subtitle' => 'El presidente que dicta remedios amargos',
                    'enemy' => 'marbas',
                    'hp' => 1960, 'atk' => 118, 'def' => 46, 'spd' => 28,
                    'skill' => 'smite', 'skill_name' => 'Bisturí de plaga', 'skill_power' => 215,
                    'grace' => 520, 'xp' => 260,
                ],
                [
                    'name' => 'Valefor',
                    'subtitle' => 'El duque de los pactos que susurra en sueños',
                    'enemy' => 'valefor',
                    'hp' => 2080, 'atk' => 124, 'def' => 44, 'spd' => 31,
                    'skill' => 'buff', 'skill_name' => 'Pacto de plumas', 'skill_power' => 30,
                    'grace' => 555, 'xp' => 280,
                ],
                [
                    'name' => 'Amon',
                    'subtitle' => 'El marqués de cabeza de lobo y cola de sierpe',
                    'enemy' => 'amon',
                    'hp' => 2200, 'atk' => 130, 'def' => 48, 'spd' => 29,
                    'skill' => 'aoe', 'skill_name' => 'Aliento bifurcado', 'skill_power' => 100,
                    'grace' => 590, 'xp' => 300,
                ],
                [
                    'name' => 'Barbatos',
                    'subtitle' => 'El duque que entiende el canto de los pájaros',
                    'enemy' => 'barbatos',
                    'hp' => 2340, 'atk' => 136, 'def' => 52, 'spd' => 27,
                    'skill' => 'drain', 'skill_name' => 'Canto del bosque hueco', 'skill_power' => 185,
                    'grace' => 630, 'xp' => 320,
                ],
                [
                    'name' => 'Paimon',
                    'subtitle' => 'El rey de las coronas que abre y cierra las puertas',
                    'enemy' => 'paimon',
                    'hp' => 2500, 'atk' => 146, 'def' => 56, 'spd' => 33,
                    'skill' => 'smite', 'skill_name' => 'Sello final', 'skill_power' => 240,
                    'grace' => 750, 'xp' => 380,
                ],
            ],
        ],
        'catacumbas' => [
            'name' => 'Las Catacumbas de los Doce',
            'subtitle' => 'Doce sombras enterradas que despiertan en cada descenso',
            'tagline' => 'La fe es la única antorcha que no se apaga.',
            'theme' => 'catacumbas',
            'font_name' => 'Letanía de hueso',
            'accent' => '#8fb26b',
            'accent2' => '#e4d8a8',
            'unlock' => ['type' => 'dungeon', 'dungeon' => 'goetia', 'value' => 3],
            'scaling' => [
                'hp' => 0.22, 'atk' => 0.12, 'def' => 0.09,
                'spd' => 0.02, 'grace' => 0.15, 'xp' => 0.15,
            ],
            'bosses' => [
                [
                    'name' => 'La Duda',
                    'subtitle' => 'Un susurro en el claustro vacío',
                    'enemy' => 'susurro',
                    'hp' => 300, 'atk' => 26, 'def' => 8, 'spd' => 14,
                    'skill' => 'smite', 'skill_name' => 'Susurro helado', 'skill_power' => 170,
                    'grace' => 120, 'xp' => 45,
                ],
                [
                    'name' => 'El Cuervo',
                    'subtitle' => 'El ave que cuenta tus dones',
                    'enemy' => 'cuervo',
                    'hp' => 390, 'atk' => 32, 'def' => 10, 'spd' => 19,
                    'skill' => 'drain', 'skill_name' => 'Picotazo amargo', 'skill_power' => 140,
                    'grace' => 150, 'xp' => 60,
                ],
                [
                    'name' => 'La Larva',
                    'subtitle' => 'La que devora las horas santas',
                    'enemy' => 'larva',
                    'hp' => 500, 'atk' => 37, 'def' => 16, 'spd' => 10,
                    'skill' => 'heal', 'skill_name' => 'Letargo', 'skill_power' => 18,
                    'grace' => 185, 'xp' => 80,
                ],
                [
                    'name' => 'La Peste',
                    'subtitle' => 'El médico de la ciudad sin campanas',
                    'enemy' => 'peste',
                    'hp' => 620, 'atk' => 45, 'def' => 19, 'spd' => 17,
                    'skill' => 'aoe', 'skill_name' => 'Aire viciado', 'skill_power' => 85,
                    'grace' => 225, 'xp' => 100,
                ],
                [
                    'name' => 'La Herejía',
                    'subtitle' => 'La capucha que niega la Luz',
                    'enemy' => 'herejia',
                    'hp' => 750, 'atk' => 54, 'def' => 23, 'spd' => 21,
                    'skill' => 'stun', 'skill_name' => 'Blasfemia', 'skill_power' => 120,
                    'grace' => 270, 'xp' => 125,
                ],
                [
                    'name' => 'La Idolatría',
                    'subtitle' => 'Un trono de oro para un dios mudo',
                    'enemy' => 'idolo',
                    'hp' => 890, 'atk' => 61, 'def' => 31, 'spd' => 15,
                    'skill' => 'shield', 'skill_name' => 'Efigie de oro', 'skill_power' => 20,
                    'grace' => 320, 'xp' => 150,
                ],
                [
                    'name' => 'La Sequía',
                    'subtitle' => 'El campo que olvidó la lluvia',
                    'enemy' => 'sequia',
                    'hp' => 1030, 'atk' => 69, 'def' => 27, 'spd' => 19,
                    'skill' => 'drain', 'skill_name' => 'Tierra reseca', 'skill_power' => 150,
                    'grace' => 375, 'xp' => 180,
                ],
                [
                    'name' => 'La Tentación',
                    'subtitle' => 'La serpiente que cita la Escritura',
                    'enemy' => 'tentacion',
                    'hp' => 1180, 'atk' => 78, 'def' => 30, 'spd' => 25,
                    'skill' => 'stun', 'skill_name' => 'Susurro antiguo', 'skill_power' => 130,
                    'grace' => 430, 'xp' => 210,
                ],
                [
                    'name' => 'El Miedo',
                    'subtitle' => 'La tormenta que apaga las velas',
                    'enemy' => 'tormenta',
                    'hp' => 1340, 'atk' => 87, 'def' => 34, 'spd' => 27,
                    'skill' => 'aoe', 'skill_name' => 'Viento del abismo', 'skill_power' => 95,
                    'grace' => 500, 'xp' => 245,
                ],
                [
                    'name' => 'El Dragón Escarlata',
                    'subtitle' => 'La antigua serpiente de siete cabezas',
                    'enemy' => 'dragon',
                    'hp' => 1520, 'atk' => 97, 'def' => 40, 'spd' => 23,
                    'skill' => 'smite', 'skill_name' => 'Aliento de fuego', 'skill_power' => 210,
                    'grace' => 580, 'xp' => 285,
                ],
                [
                    'name' => 'La Sombra del Abismo',
                    'subtitle' => 'Aquello que se niega a ser nombrado',
                    'enemy' => 'abismo',
                    'hp' => 1720, 'atk' => 108, 'def' => 46, 'spd' => 25,
                    'skill' => 'drain', 'skill_name' => 'Vacío devorador', 'skill_power' => 175,
                    'grace' => 670, 'xp' => 330,
                ],
                [
                    'name' => 'Leviatán del Orgullo',
                    'subtitle' => 'El rey de las aguas que se creyó Dios',
                    'enemy' => 'leviatan',
                    'hp' => 1950, 'atk' => 120, 'def' => 52, 'spd' => 29,
                    'skill' => 'aoe', 'skill_name' => 'Marea de soberbia', 'skill_power' => 110,
                    'grace' => 800, 'xp' => 400,
                ],
            ],
        ],
    ],
];
