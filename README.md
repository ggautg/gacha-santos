# Sanctorum · Gacha de Santos

Gacha de santos católicos con retratos en pixel art, colección, equipo y descensos procedurales contra los vicios, los sellos de la Goetia y las catacumbas. Construido con **Laravel 13**, **MySQL**, **Inertia 3** y **Vue 3**.

## Características

- **Oraciones**: tiradas simples (60 Puntos de Fe), de diez (540, con 4★ o superior garantizado) y una gratuita cada 6 horas.
- **47 santos** repartidos en cinco rarezas (1★ a 5★), con habilidades de curación, escudo, golpe de fe, fervor, drenaje y aturdimiento.
- **Pity** a las 60 tiradas para asegurar un 5★; los repetidos devuelven Puntos de Fe según su rareza.
- **Colección** con filtros por rareza y progreso; cada santo puede iluminarse para subir de nivel (hasta 20).
- **Santos velados**: los que aún no se han revelado ocultan por completo su nombre, rareza, arte, historia y estadísticas.
- **Equipo** de hasta tres santos que se elige en el campamento antes de entrar a una mazmorra y se guarda en el servidor.
- **Mazmorras procedurales**: tres descensos sin final, con jefes elegidos al azar mediante la semilla de la partida, estadísticas y botín que crecen con la profundidad y progreso propio por piso.
- **Tema por mazmorra**: cada descenso impone su paleta de colores y su tipografía a toda la web mientras se explora.
- **Selector de temas**: el botón de paleta de la barra superior cambia entre las paletas Sanctorum, Pecados, Goetia y Catacumbas, y entre las tipografías Píxel, Rúbrica, Hueso y Clásica. La elección se guarda en el navegador.
- **Interfaz tipo launcher**: barra superior con botón **Jugar** y marca, riel lateral de navegación con Inicio, Orar, Colección y Mazmorras, y barra de estado inferior.
- **Arte pixelado generado en el servidor**: cada retrato y enemigo es un SVG cacheado en una rejilla de 24×28, sin imágenes externas.

## Requisitos

- PHP 8.3 o superior con las extensiones habituales de Laravel
- Composer 2
- Node.js 20+ y npm
- MySQL 8 (o MariaDB compatible)

## Instalación

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Configura la base de datos en `.env`:

```dotenv
APP_NAME=Sanctorum
APP_LOCALE=es
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gacha_santos
DB_USERNAME=root
DB_PASSWORD=
```

Crea la base de datos y siembra el santoral:

```bash
mysql -u root -p -e "CREATE DATABASE gacha_santos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
npm install
npm run build
```

Arranca el servidor:

```bash
php artisan serve
```

La aplicación queda en `http://127.0.0.1:8000`. También puedes usar `composer run dev` para levantar servidor, cola y Vite a la vez.

## Reglas del juego

| Concepto | Valor |
| --- | --- |
| Puntos de Fe iniciales | 0 |
| Tirada simple | 60 |
| Diez tiradas | 540 (garantiza 4★+) |
| Tirada gratuita | Cada 6 horas |
| Pity 5★ | 60 tiradas |
| Iluminar (subir nivel) | 45 por nivel, máximo 20 |

Probabilidades por tirada: 1★ 40 %, 2★ 30 %, 3★ 20 %, 4★ 8,5 %, 5★ 1,5 %.

Los santos iniciales son los cuatro evangelistas: San Juan, San Mateo, San Lucas y San Marcos. Todos llegan al santoral del nuevo peregrino y el equipo arranca con los tres primeros; Marcos queda libre para entrar al equipo desde el campamento. Todo el balance vive en `config/sanctorum.php`: economía, probabilidades, reembolsos por repetidos, el elenco y el escalado de cada mazmorra.

### Colección velada

Mientras un santo no se haya revelado, el servidor no envía su identidad: la carta muestra «Santo velado», un retrato genérico, rareza desconocida y ninguna estadística ni habilidad. Los velados solo aparecen al quitar los filtros y la búsqueda; se ordenan de forma determinista para no delatar su rareza. Al revelarlos, la carta muestra su nombre, su arte y su historia.

## Mazmorras

| Mazmorra | Elenco | Desbloqueo | Tema |
| --- | --- | --- | --- |
| Los Siete Pecados | 7 vicios | Siempre abierta | Carmesí y oro, tipografía de píxel |
| Los Sellos de la Goetia | 9 sellos (Bael a Paimon) | Alcanzar el piso 3 de Los Siete Pecados | Violeta y cian, serif de rúbrica |
| Las Catacumbas de los Doce | 12 sombras | Alcanzar el piso 3 de Los Sellos de la Goetia | Verde hueso, letanía de máquina de escribir |

Cada descenso es procedural: al entrar se genera una semilla y el jefe de cada piso se elige al azar entre el elenco de esa mazmorra, sin repetir el del piso anterior. Los pisos no tienen final: vida, ataque, defensa y recompensas crecen con la profundidad según el campo `scaling` de la configuración. La semilla se guarda en `dungeon_runs` y los pisos limpiados en `dungeon_progress`; las batallas de mazmorra se registran en `battles` con su `dungeon` y su piso en `stage`.

Antes de presentar batalla se elige el equipo en el campamento (de uno a tres santos ya revelados). El servidor valida la pertenencia, guarda el equipo y lo usa en el combate. Si caes, puedes reintentar el mismo piso; el botón **Abrir grieta nueva** cambia la semilla y reinicia el descenso al piso 1 sin borrar tu mejor piso. Los combates no cuestan Puntos de Fe.

Cada mazmorra define una paleta y una tipografía propias en `config/sanctorum.php`. La página del descenso las aplica a todo el documento mediante `html[data-theme="..."]` y `html[data-font="..."]`, de modo que paneles, botones, arena y textos cambian de identidad al bajar. El hub muestra una previsualización de cada fuente y sus colores.

El menú del perfil (barra superior) permite cambiar la paleta y la tipografía por separado, también fuera de las mazmorras. Cada paleta propone su fuente por defecto, pero puedes combinarlas libremente; la elección se guarda en `localStorage` y se aplica antes de montar Vue para evitar parpadeos.

## Interfaz

La aplicación se presenta como un launcher de escritorio a pantalla completa:

- **Barra superior**: botón dorado **Jugar** y marca a la izquierda; Puntos de Fe y perfil a la derecha. Al pulsar el perfil se despliega el menú con navegación, Puntos de Fe, apariencia (paletas y tipografías) y sonido.
- **Riel lateral**: Inicio, Orar, Colección y Mazmorras; se convierte en una barra horizontal en pantallas pequeñas.
- **Orar**: sección propia para las tiradas, con modos de oración, pity, probabilidades, actividad reciente y vitrina de los 5★.
- **Santuario**: de momento el inicio queda como una página en blanco; el resto de secciones sigue disponible desde el riel.
- **Barra inferior**: conexión, nombre del juego y estado de Puntos de Fe con la cuenta atrás de la tirada gratuita.

El armazón vive en `resources/js/Layouts/AppLayout.vue` y sus estilos en el bloque **Launcher** de `resources/css/app.css`. El contenido de cada página se renderiza dentro de `.launcher-main`, que es el área con scroll propio.

## Estructura

```
app/
  Http/Controllers/    Sanctuary, Gacha, Collection, Team, Dungeon, Art
  Models/              Saint, Player, SaintCopy, Pull, Battle, DungeonProgress, DungeonRun
  Services/            GachaService (tiradas, pity, repetidos), BattleService (turnos, recompensas y descensos)
  Support/             PixelArt (SVG procedural), CardPresenter (payloads y URLs de arte), DungeonRules
config/sanctorum.php   Economía, probabilidades, elenco y escalado de mazmorras
resources/js/
  Pages/               Sanctuary, Gacha, Collection, Team, Dungeons, DungeonRun
  Components/          SaintCard, SaintDetail, PullReveal, ToastStack, ThemePicker, AppLayout
  lib/                 api, player (estado compartido), theme (paletas y fuentes), toast, sfx
database/seeders/      SaintSeeder con los 47 santos
tests/Feature/         Gacha, Collection, Team, Dungeon y páginas
```

El jugador se identifica con un token de sesión (`player_token`) y se crea automáticamente en el middleware `EnsurePlayer`; no hay registro ni login.

## Comandos útiles

```bash
php artisan test --compact     # pruebas (SQLite en memoria)
vendor/bin/pint --format agent # estilo de PHP
npm run build                  # compilar assets
npm run dev                    # Vite en modo desarrollo
php artisan migrate:fresh --seed
```

## Créditos

Arte, textos y mecánicas son representaciones devocionales originales con fines de entretenimiento; no representan a las instituciones religiosas ni a las personas retratadas.

Hecho con fe y píxeles.
